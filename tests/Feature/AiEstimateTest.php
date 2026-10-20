<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiEstimateService;
use App\Services\GeminiVisionService;
use App\Support\DecorAreaBreakdown;
use App\Support\EventDesignBrief;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Indicative AI ranges for guests and clients (AiEstimateService): per picked area plus a
 * separate Shared Materials group, or one overall range; point = AI material cost × the Admin AI
 * markup + labour & setup; range = point × 0.85 to × 1.15, low end rounded down and high end up
 * to whole pesos. The Admin settings that drive it are validated and audited.
 */
class AiEstimateTest extends TestCase
{
    use RefreshDatabase;

    private const AREAS = ['decor_areas' => ['entrance', 'stage']];

    // ------------------------------------------------------------------ Calculation

    public function test_each_picked_area_and_shared_materials_get_their_own_range_without_a_grand_total(): void
    {
        $estimate = $this->estimate([
            $this->row('Roses', 10, 50, 'entrance'),      // ₱500 raw
            $this->row('Lilies', 4, 100, 'stage'),        // ₱400 raw
            $this->row('Floral Foam', 2, 50, 'shared'),   // ₱100 raw
        ]);

        $this->assertSame('available', $estimate['status']);
        $this->assertSame('areas', $estimate['mode']);
        $this->assertSame(['entrance', 'stage', 'shared'], array_column($estimate['groups'], 'key'));
        $this->assertSame('Shared Materials', $estimate['groups'][2]['label']);
        // 3× markup, no labour: ₱1,500 → ₱1,275 – ₱1,725
        $this->assertSame('₱1,275 – ₱1,725', $estimate['groups'][0]['display']);
        $this->assertSame('₱1,020 – ₱1,380', $estimate['groups'][1]['display']);
        $this->assertSame('₱255 – ₱345', $estimate['groups'][2]['display']);
        $this->assertNotContains(AiEstimateService::OVERALL, array_column($estimate['groups'], 'key'));
        $this->assertFalse($estimate['labour_included']);
    }

    public function test_rounding_widens_the_range_to_whole_pesos_and_keeps_it_ordered(): void
    {
        // ₱333.33 raw × 3 = ₱999.99 → exact bounds ₱849.99 and ₱1,149.99 → shown ₱849 – ₱1,150
        $estimate = $this->estimate([$this->row('Roses', 1, 333.33, 'entrance')]);

        $this->assertSame('₱849 – ₱1,150', $estimate['groups'][0]['display']);
        $this->assertSame(['low_centavos' => 84999, 'high_centavos' => 114999, 'low' => 849, 'high' => 1150], AiEstimateService::range(99999));
        $this->assertSame(['low_centavos' => 85, 'high_centavos' => 115, 'low' => 0, 'high' => 2], AiEstimateService::range(100));
        foreach ([1, 99, 12345, 9999999] as $point) {
            $range = AiEstimateService::range($point);
            $this->assertLessThanOrEqual($range['low_centavos'], $range['low'] * 100);
            $this->assertGreaterThanOrEqual($range['high_centavos'], $range['high'] * 100);
            $this->assertLessThan($range['high'], $range['low']);
        }
    }

    public function test_one_material_is_enough_for_an_area_range_and_empty_areas_say_why(): void
    {
        $estimate = $this->estimate([$this->row('Arch Roses', 20, 30, 'entrance')]);

        $this->assertSame('complete', $estimate['groups'][0]['status']);
        $this->assertSame('₱1,530 – ₱2,070', $estimate['groups'][0]['display']);
        $this->assertSame('unavailable', $estimate['groups'][1]['status']);
        $this->assertNull($estimate['groups'][1]['display']);
        $this->assertStringContainsString('No AI suggestions for this area yet', $estimate['groups'][1]['note']);
    }

    public function test_missing_prices_make_an_area_incomplete_or_unavailable_never_complete(): void
    {
        $estimate = $this->estimate([
            $this->row('Roses', 10, 50, 'entrance'),
            $this->row('Pampas', 5, null, 'entrance'),    // no AI price
            $this->row('Lilies', null, 80, 'stage'),      // no AI quantity
        ]);

        $this->assertSame('incomplete', $estimate['groups'][0]['status']);
        $this->assertSame(1, $estimate['groups'][0]['unpriced_count']);
        $this->assertSame('₱1,275 – ₱1,725', $estimate['groups'][0]['display']);
        $this->assertStringContainsString('covers 1 of 2 materials', $estimate['groups'][0]['note']);
        $this->assertSame('unavailable', $estimate['groups'][1]['status']);
        $this->assertNull($estimate['groups'][1]['display']);
    }

    public function test_without_picked_areas_one_overall_range_is_shown_only_with_priced_materials(): void
    {
        $service = app(AiEstimateService::class);
        $overall = $service->estimate([$this->row('Roses', 10, 50, null), $this->row('Foam', 2, 50, null)], null);

        $this->assertSame('overall', $overall['mode']);
        $this->assertSame([AiEstimateService::OVERALL], array_column($overall['groups'], 'key'));
        $this->assertSame('₱1,530 – ₱2,070', $overall['groups'][0]['display']);

        $unpriced = $service->estimate([$this->row('Roses', 10, null, null)], null);
        $this->assertSame('unavailable', $unpriced['status']);
        $this->assertNull($unpriced['groups'][0]['display']);
    }

    public function test_rows_reused_from_a_completed_booking_carry_raflora_costs_and_never_count(): void
    {
        $estimate = $this->estimate([
            $this->row('Roses', 10, 50, 'entrance') + ['source' => 'completed_template'],
            $this->row('Lilies', 4, 100, 'stage'),
        ]);

        $this->assertSame('unavailable', $estimate['groups'][0]['status']);
        $this->assertSame('₱1,020 – ₱1,380', $estimate['groups'][1]['display']);
    }

    public function test_the_admin_ai_markup_drives_the_estimate_and_an_unusable_value_pauses_it(): void
    {
        Setting::setSetting(Setting::KEY_AI_ESTIMATE_MARKUP, 2.5, 'float');
        $this->assertSame('₱1,062 – ₱1,438', $this->estimate([$this->row('Roses', 10, 50, 'entrance')])['groups'][0]['display']);
        $this->assertSame(2.5, (new GeminiVisionService())->buildPricingSummary([$this->row('Roses', 10, 50, 'entrance')])['markup_multiplier']);

        Setting::setSetting(Setting::KEY_AI_ESTIMATE_MARKUP, 'abc', 'string');
        $paused = $this->estimate([$this->row('Roses', 10, 50, 'entrance')]);
        $this->assertSame('unavailable', $paused['status']);
        $this->assertSame('settings', $paused['reason']);
        $this->assertNull($paused['groups'][0]['display']);

        Setting::setSetting(Setting::KEY_AI_ESTIMATE_MARKUP, 0, 'float');
        $this->assertNull(Setting::aiEstimateMarkup());
    }

    public function test_a_fixed_labour_allowance_is_added_once_and_split_by_material_cost(): void
    {
        Setting::setSetting(Setting::KEY_AI_LABOUR_MODE, 'fixed', 'string');
        Setting::setSetting(Setting::KEY_AI_LABOUR_VALUE, 1000, 'float');

        // Raw ₱100 and ₱200: labour ₱333.33 + ₱666.67 = ₱1,000 exactly.
        // Points ₱633.33 and ₱1,266.67 (±15% covers labour too).
        $estimate = $this->estimate([$this->row('Roses', 1, 100, 'entrance'), $this->row('Lilies', 1, 200, 'stage')]);

        $this->assertTrue($estimate['labour_included']);
        $this->assertSame('₱538 – ₱729', $estimate['groups'][0]['display']);
        $this->assertSame('₱1,076 – ₱1,457', $estimate['groups'][1]['display']);
    }

    public function test_a_percentage_labour_allowance_is_taken_of_material_cost_before_markup(): void
    {
        Setting::setSetting(Setting::KEY_AI_LABOUR_MODE, 'percentage', 'string');
        Setting::setSetting(Setting::KEY_AI_LABOUR_VALUE, 10, 'float');

        // Raw ₱100: ₱300 marked up + 10% of ₱100 = ₱310 → ₱263.50 – ₱356.50 → ₱263 – ₱357
        $estimate = $this->estimate([$this->row('Roses', 1, 100, 'entrance')]);

        $this->assertSame('₱263 – ₱357', $estimate['groups'][0]['display']);
    }

    public function test_estimates_never_carry_item_prices_costs_or_settings(): void
    {
        Setting::setSetting(Setting::KEY_AI_LABOUR_MODE, 'fixed', 'string');
        Setting::setSetting(Setting::KEY_AI_LABOUR_VALUE, 1234.56, 'float');
        $json = json_encode($this->estimate([$this->row('Roses', 10, 47.25, 'entrance')]));

        foreach (['unit_cost', 'raw', 'markup', '47.25', '472.5', '1234', 'Roses'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json);
        }
    }

    // ------------------------------------------------------------------ Admin settings

    public function test_admin_sets_the_ai_markup_and_labour_with_an_audit_trail(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'ai_estimate_markup' => '2.75',
            'ai_labour_mode' => 'percentage',
            'ai_labour_value' => '12.5',
            'change_reason' => 'Supplier prices rose',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(2.75, Setting::aiEstimateMarkup());
        $this->assertSame(['mode' => 'percentage', 'value' => 12.5], Setting::aiLabourAllowance());
        $messages = AuditLog::where('action', 'setting_updated')->get()->map(fn (AuditLog $log) => (string) (((array) $log->details)['message'] ?? ''));
        $this->assertCount(2, $messages);
        $this->assertTrue($messages->contains(fn (string $message) => str_contains($message, 'AI estimate markup updated from 3× to 2.75×') && str_contains($message, 'Supplier prices rose')));
        $this->assertTrue($messages->contains(fn (string $message) => str_contains($message, 'labour & setup updated from not set to 12.5% of AI material cost')));
    }

    public function test_invalid_ai_settings_are_rejected_and_nothing_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        foreach ([
            ['ai_estimate_markup' => '0'],
            ['ai_estimate_markup' => '-2'],
            ['ai_estimate_markup' => '10.01'],
            ['ai_estimate_markup' => '1.234'],
            ['ai_estimate_markup' => 'INF'],
            ['ai_labour_mode' => 'hourly', 'ai_labour_value' => '10'],
            ['ai_labour_mode' => 'percentage', 'ai_labour_value' => '150'],
            ['ai_labour_mode' => 'fixed', 'ai_labour_value' => '-1'],
            ['ai_labour_mode' => 'fixed'],
        ] as $input) {
            $this->actingAs($admin)->post(route('admin.settings.update'), $input)->assertSessionHasErrors();
        }

        $this->assertSame(Setting::DEFAULT_AI_ESTIMATE_MARKUP, Setting::aiEstimateMarkup());
        $this->assertSame(['mode' => null, 'value' => null], Setting::aiLabourAllowance());
    }

    public function test_only_admins_can_change_the_ai_settings_and_the_settings_page_shows_them(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->actingAs($staff)->post(route('admin.settings.update'), ['ai_estimate_markup' => '5'])->assertForbidden();
        $this->assertSame(Setting::DEFAULT_AI_ESTIMATE_MARKUP, Setting::aiEstimateMarkup());

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->get(route('admin.settings', ['tab' => 'administration']))
            ->assertOk()
            ->assertSee('AI Estimate Pricing')
            ->assertSee('name="ai_estimate_markup"', false)
            ->assertSee('name="ai_labour_mode"', false);
    }

    public function test_changing_the_ai_markup_never_alters_an_issued_quotation(): void
    {
        $booking = Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(30)->toDateString(), 'venue' => 'Hall', 'status' => 'quotation_sent', 'multiplier' => 3.0, 'final_quoted_price' => 9000]);
        $quotation = Quotation::create(['booking_id' => $booking->id, 'status' => Quotation::STATUS_ISSUED, 'version' => 1, 'multiplier' => 3.0, 'final_quoted_price' => 9000, 'items_snapshot' => [], 'valid_until' => now()->addDays(7)]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.settings.update'), ['ai_estimate_markup' => '5'])->assertSessionHasNoErrors();

        $this->assertSame(5.0, Setting::aiEstimateMarkup());
        $this->assertSame('9000.00', $quotation->fresh()->final_quoted_price);
        $this->assertSame('3.00', $quotation->fresh()->multiplier);
        $this->assertSame(3.0, (float) $booking->fresh()->multiplier);
    }

    public function test_the_prompt_budget_rule_follows_the_ai_markup(): void
    {
        Setting::setSetting(Setting::KEY_AI_ESTIMATE_MARKUP, 2.5, 'float');

        $this->assertStringContainsString('about 2.5x your raw material total', EventDesignBrief::promptRules(['budget_range' => '15k_30k'], null));
    }

    // ------------------------------------------------------------------ Helpers

    private function estimate(array $rows): array
    {
        return app(AiEstimateService::class)->estimate($rows, self::AREAS);
    }

    private function row(string $name, ?float $quantity, ?float $unitCost, ?string $decorArea): array
    {
        return array_filter([
            'item_name' => $name,
            'category' => 'flower',
            'quantity' => $quantity,
            'unit_type' => 'stem',
            'unit_cost_php' => $unitCost,
            'decor_area' => $decorArea,
        ], fn ($value) => $value !== null);
    }
}

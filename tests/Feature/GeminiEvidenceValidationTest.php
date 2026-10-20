<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\GeminiVisionService;
use App\Services\QuotationIssuanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * README_Gemini_AI_Accuracy_and_Evidence_Validation: Gemini output is decision support only.
 * Missing prices and quantities are not invented, AI prices never become the quoted price,
 * unconfirmed AI suggestions do not count as reserved stock, and the event date, model and
 * time of each analysis are kept with the result.
 */
class GeminiEvidenceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Cache::flush();
    }

    // ------------------------------------------------------------------ Normalisation

    public function test_missing_ai_price_is_marked_unavailable_instead_of_invented(): void
    {
        $analysis = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            ['item_name' => 'Garden Roses', 'category' => 'flower', 'unit_type' => 'stem', 'quantity' => 24],
            ['item_name' => 'Eucalyptus', 'category' => 'foliage', 'unit_type' => 'bunch', 'quantity' => 4, 'unit_cost_php' => 80],
        ]]);

        [$roses, $eucalyptus] = $analysis['suggested_materials'];
        $this->assertNull($roses['unit_cost_php']);
        $this->assertNull($roses['estimated_unit_cost_php']);
        $this->assertSame('unavailable', $roses['price_status']);
        $this->assertSame(80.0, $eucalyptus['unit_cost_php']);
        $this->assertSame('ai_estimate', $eucalyptus['price_status']);

        $summary = $analysis['pricing_summary'];
        $this->assertSame(320.0, $summary['raw_materials_total_php']);
        $this->assertSame(960.0, $summary['estimated_grand_total_php']);
        $this->assertFalse($summary['is_complete']);
        $this->assertSame(1, $summary['unpriced_item_count']);
        $this->assertSame(['Garden Roses'], $summary['unpriced_items']);
        $this->assertSame('ai_estimate_unverified', $summary['price_basis']);
    }

    public function test_missing_or_invalid_quantity_is_not_replaced_with_a_guess(): void
    {
        $analysis = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            ['item_name' => 'Ribbon', 'category' => 'supply', 'unit_type' => 'set', 'unit_cost_php' => 50],
            ['item_name' => 'Moss', 'category' => 'foliage', 'unit_type' => 'bunch', 'quantity' => -2, 'unit_cost_php' => 40],
            ['item_name' => 'Baby Breath', 'category' => 'flower', 'unit_type' => 'bunch', 'quantity' => 0.5, 'unit_cost_php' => 120],
        ]]);

        [$ribbon, $moss, $babyBreath] = $analysis['suggested_materials'];
        $this->assertNull($ribbon['quantity']);
        $this->assertNull($ribbon['estimated_quantity']);
        $this->assertSame('unavailable', $ribbon['quantity_status']);
        $this->assertNull($moss['quantity']);
        $this->assertSame('unavailable', $moss['quantity_status']);
        // A fractional AI estimate is kept as-is instead of being rounded up to 1.
        $this->assertSame(0.5, $babyBreath['quantity']);
        $this->assertSame('ai_estimate', $babyBreath['quantity_status']);

        $summary = $analysis['pricing_summary'];
        $this->assertSame(60.0, $summary['raw_materials_total_php']);
        $this->assertFalse($summary['is_complete']);
        $this->assertSame(['Ribbon', 'Moss'], $summary['unpriced_items']);
    }

    public function test_complete_ai_estimate_is_still_labelled_unverified(): void
    {
        $summary = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            ['item_name' => 'White Roses', 'category' => 'flower', 'unit_type' => 'stem', 'quantity' => 10, 'unit_cost_php' => 35],
        ]])['pricing_summary'];

        $this->assertTrue($summary['is_complete']);
        $this->assertSame(0, $summary['unpriced_item_count']);
        $this->assertSame('ai_estimate_unverified', $summary['price_basis']);
        $this->assertSame(1050.0, $summary['estimated_grand_total_php']);
    }

    public function test_payload_validation_accepts_unpriced_rows_but_rejects_malformed_values(): void
    {
        $vision = new GeminiVisionService();
        $vision->validateAnalysisPayload($vision->normalizeAiAnalysis(['suggested_materials' => [
            ['item_name' => 'Garden Roses', 'category' => 'flower', 'unit_type' => 'stem'],
        ]]));

        $this->expectExceptionMessage('incomplete_analysis_item');
        $vision->validateAnalysisPayload(['suggested_materials' => [
            ['item_name' => 'Garden Roses', 'category' => 'flower', 'unit_type' => 'stem', 'quantity' => 10, 'unit_cost_php' => -5],
        ]]);
    }

    // ------------------------------------------------------------------ Client booking persistence

    public function test_client_ai_booking_keeps_ai_prices_as_recommendations_only(): void
    {
        Storage::fake('local');
        $user = $this->clientUser('client-ai@example.com');
        InventoryItem::create([
            'name' => 'White Roses', 'category' => 'Flowers', 'item_code' => 'FLW-0101',
            'current_stock' => 500, 'min_stock' => 0, 'unit_cost' => 40, 'unit' => 'stems', 'is_perishable' => true,
        ]);

        $image = UploadedFile::fake()->createWithContent('inspiration.png', $this->pngBytes());
        $analysis = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            ['item_name' => 'White Roses', 'category' => 'flower', 'unit_type' => 'stem', 'quantity' => 20, 'unit_cost_php' => 90],
            ['item_name' => 'Satin Ribbon', 'category' => 'supply', 'unit_type' => 'set', 'quantity' => 10, 'unit_cost_php' => 15],
            ['item_name' => 'Pampas Grass', 'category' => 'foliage', 'unit_type' => 'bunch', 'quantity' => 6],
            ['item_name' => 'Floral Foam', 'category' => 'supply', 'unit_type' => 'piece', 'unit_cost_php' => 25],
        ]]);
        $token = (string) Str::uuid();

        $this->actingAs($user)->withSession([
            "booking_analysis_payloads.{$token}" => [
                'image_hash' => sha1($this->pngBytes()),
                'analysis' => $analysis,
                'raw_response' => 'mocked',
            ],
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(40)->toDateString(),
            'venue' => 'Tagaytay',
            'guest_count' => 80,
            'analysis_token' => $token,
            'inspiration_image' => $image,
        ])->assertSessionHasNoErrors();

        $booking = Booking::firstOrFail();
        $items = $booking->bookingItems()->get()->keyBy('item_name');

        // Every AI row is kept for staff review, including rows without a price or quantity.
        $this->assertCount(4, $items);
        $this->assertTrue($items->every(fn (BookingItem $item) => $item->is_ai_suggested && $item->confirmed_at === null));

        // Raflora's own price record is used; the AI price is only a recommendation.
        $this->assertSame(40.0, (float) $items['White Roses']->quoted_unit_price);
        $this->assertSame(90.0, (float) $items['White Roses']->ai_recommended_price);
        $this->assertSame(0.0, (float) $items['Satin Ribbon']->quoted_unit_price);
        $this->assertSame(15.0, (float) $items['Satin Ribbon']->ai_recommended_price);
        $this->assertNull($items['Pampas Grass']->ai_recommended_price);
        $this->assertSame(6.0, (float) $items['Pampas Grass']->quantity);
        // No quantity was estimated, so none is recorded; staff must set it.
        $this->assertSame(0.0, (float) $items['Floral Foam']->quantity);

        // Totals come from verified prices only: 20 x 40 x 3 = 2400 (not the AI 20 x 90 + 10 x 15 estimate).
        $this->assertSame(800.0, (float) $booking->raw_materials_sum);
        $this->assertSame(2400.0, (float) $booking->final_quoted_price);
    }

    public function test_claimed_guest_request_keeps_unpriced_ai_rows_for_staff_review(): void
    {
        $user = $this->clientUser('guest-claim@example.com');
        $temp = new TemporaryGuestBooking();
        $temp->fill([
            'guest_name' => 'Guest Claimer',
            'guest_email' => 'guest-claim@example.com',
            'guest_phone' => '09170000000',
            'guest_address' => 'Manila',
            'booking_type' => 'custom_ai',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Makati',
            'analysis_data' => (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
                ['item_name' => 'Sunflowers', 'category' => 'flower', 'unit_type' => 'stem', 'quantity' => 12, 'unit_cost_php' => 60],
                ['item_name' => 'Burlap Wrap', 'category' => 'supply', 'unit_type' => 'set', 'quantity' => 2],
                ['item_name' => 'Twine', 'category' => 'supply', 'unit_type' => 'set', 'unit_cost_php' => 20],
            ]]),
            'expires_at' => now()->addHours(12),
        ]);
        $rawToken = $temp->generateToken();
        $temp->save();

        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', $rawToken))->assertRedirect();

        $items = Booking::firstOrFail()->bookingItems()->get()->keyBy('item_name');
        $this->assertCount(3, $items);
        $this->assertNull($items['Burlap Wrap']->ai_recommended_price);
        $this->assertSame(0.0, (float) $items['Burlap Wrap']->quoted_unit_price);
        $this->assertSame(0.0, (float) $items['Twine']->quantity);
        $this->assertSame(20.0, (float) $items['Twine']->ai_recommended_price);
    }

    // ------------------------------------------------------------------ Quotation boundary

    public function test_quotation_cannot_be_issued_while_a_confirmed_ai_item_has_no_admin_price(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-gemini@example.com', 'password' => bcrypt('password123'), 'role' => 'admin']);
        $booking = Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(20)->toDateString(), 'status' => 'pending', 'multiplier' => 3.0]);
        BookingItem::create([
            'booking_id' => $booking->id, 'item_name' => 'Pampas Grass', 'quantity' => 6,
            'quoted_unit_price' => 0, 'ai_recommended_price' => null, 'is_ai_suggested' => true,
            'confirmed_at' => now(), 'procurement_status' => 'pending',
        ]);

        try {
            app(QuotationIssuanceService::class)->issue($booking, $admin->id);
            $this->fail('A quotation was issued with an unpriced AI item.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Pampas Grass', $e->getMessage());
        }

        $this->assertSame(0, $booking->quotations()->count());
    }

    // ------------------------------------------------------------------ Inventory boundary

    public function test_unconfirmed_ai_suggestions_are_not_counted_as_reserved_stock(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-inv@example.com', 'password' => bcrypt('password123'), 'role' => 'admin']);
        $item = InventoryItem::create([
            'name' => 'Pink Roses', 'category' => 'Flowers', 'item_code' => 'FLW-0202',
            'current_stock' => 10, 'min_stock' => 0, 'unit_cost' => 45, 'unit' => 'stems', 'is_perishable' => true,
        ]);
        $suggested = Booking::create(['event_type' => 'wedding', 'event_date' => Carbon::today()->addDays(10), 'status' => 'pending']);
        $suggested->inventoryItems()->attach($item->id, ['quantity' => 25, 'quoted_unit_price' => 0, 'is_ai_suggested' => true, 'procurement_status' => 'pending']);
        $confirmed = Booking::create(['event_type' => 'birthday', 'event_date' => Carbon::today()->addDays(12), 'status' => 'confirmed']);
        $confirmed->inventoryItems()->attach($item->id, ['quantity' => 4, 'quoted_unit_price' => 45, 'procurement_status' => 'reserved', 'confirmed_at' => now()]);

        $this->actingAs($admin)->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertViewHas('inventoryItems', function ($items) {
                $row = collect($items->items())->firstWhere('name', 'Pink Roses');

                return $row !== null && (float) $row->reserved_stock === 4.0 && (float) $row->net_available === 6.0;
            });
    }

    // ------------------------------------------------------------------ Event-date context and evidence metadata

    public function test_client_analysis_sends_event_date_and_records_analysis_metadata(): void
    {
        Storage::fake('local');
        $user = $this->clientUser('client-date@example.com');
        Http::fake(['*' => Http::sequence()
            ->push($this->geminiText(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($this->geminiText(['suggested_materials' => [$this->roseRow()]]))
            ->push($this->geminiText(['suggested_materials' => [$this->roseRow()]])),
        ]);

        $first = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
            'event_date' => '2026-12-12',
            'end_time' => '21:00',
        ])->assertOk()->assertJsonPath('success', true);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->body(), 'Event Date: 2026-12-12')
            && str_contains($request->body(), 'Event End Time: 21:00'));

        $meta = $first->json('analysis.analysis_meta');
        $this->assertSame('gemini', $meta['source']);
        $this->assertSame('gemini-3.5-flash-lite', $meta['model']);
        $this->assertSame('2026-12-12', $meta['event_date']);
        $this->assertTrue($meta['event_date_provided']);
        $this->assertNotEmpty($meta['analyzed_at']);

        // Same image for a different event date must not reuse the earlier date's analysis.
        $second = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
            'event_date' => '2027-03-20',
        ])->assertOk();
        $this->assertSame('gemini', $second->json('analysis.analysis_meta.source'));
        $this->assertSame('2027-03-20', $second->json('analysis.analysis_meta.event_date'));
        Http::assertSentCount(3);

        // Same image and context again is served from cache and says so, keeping the original model and time.
        $third = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
            'event_date' => '2027-03-20',
        ])->assertOk();
        $this->assertSame('cache', $third->json('analysis.analysis_meta.source'));
        $this->assertSame('gemini-3.5-flash-lite', $third->json('analysis.analysis_meta.model'));
        $this->assertSame($second->json('analysis.analysis_meta.analyzed_at'), $third->json('analysis.analysis_meta.analyzed_at'));
        Http::assertSentCount(3);
    }

    public function test_analysis_without_event_date_is_flagged_as_not_date_checked(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::sequence()
            ->push($this->geminiText(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($this->geminiText(['suggested_materials' => [$this->roseRow()]])),
        ]);

        $response = $this->actingAs($this->clientUser('client-nodate@example.com'))->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
        ])->assertOk();

        $this->assertFalse($response->json('analysis.analysis_meta.event_date_provided'));
        $this->assertNull($response->json('analysis.analysis_meta.event_date'));
    }

    // ------------------------------------------------------------------ Helpers

    private function clientUser(string $email): User
    {
        return User::create([
            'name' => 'Gemini Client',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }

    private function roseRow(): array
    {
        return [
            'item_name' => 'White Roses', 'category' => 'flower', 'quantity' => 20, 'unit_type' => 'stem',
            'unit_cost_php' => 35, 'area' => 'tables', 'is_detected' => true, 'is_recommendation' => false,
        ];
    }

    private function geminiText(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\GeminiVisionService;
use App\Support\DecorAreaBreakdown;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The AI's materials are grouped by the areas the client asked to decorate (or "shared"), with
 * the counting behind each quantity. Prices never come from the AI: area subtotals split the
 * booking's verified Raflora line prices, admin sees raw costs, staff see quantities, and the
 * client sees each area's share of the quoted price once a quotation is issued.
 */
class DecorAreaBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Cache::flush();
    }

    // ------------------------------------------------------------------ Grouping and tags

    public function test_rows_are_grouped_by_picked_area_or_shared_and_empty_picked_areas_stay(): void
    {
        $rows = [
            $this->row('White Roses', 144, 35, 'centerpieces', '12 tables × 12 stems = 144 stems'),
            $this->row('Glass Vases', 12, 80, 'centerpieces', null, 'prop'),
            $this->row('White Roses', 40, 35, 'entrance'),
            $this->row('Floral Foam', 20, 25, 'shared', null, 'supply'),
            $this->row('Eucalyptus', null, 60, 'centerpieces'),                     // no AI quantity
            $this->row('Pampas Grass', 6, 90, null, null, 'foliage', 'aisle'),      // aisle was not picked: shared
            $this->row('Ribbon', 2, 50, null, null, 'supply', 'tables'),            // tables could be two picked areas: shared
            $this->row('Candles', 10, 40, 'vip_table', null, 'prop', 'tables'),     // tagged with an area the client did not pick
        ];

        $breakdown = DecorAreaBreakdown::build($rows, ['decor_areas' => ['centerpieces', 'entrance', 'cake_table']]);

        // Picked areas in the order the form lists them, then shared. Nothing else.
        $this->assertSame(['entrance', 'centerpieces', 'cake_table', 'shared'], array_column($breakdown['areas'], 'key'));
        $this->assertSame([false, false, true, false], array_column($breakdown['areas'], 'missing'));
        $this->assertSame([['key' => 'cake_table', 'label' => 'Cake table']], $breakdown['missing_areas']);
        $this->assertSame(['Floral Foam', 'Pampas Grass', 'Ribbon', 'Candles'], array_column($breakdown['areas'][3]['items'], 'name'));

        $centerpieces = $breakdown['areas'][1];
        $this->assertSame('Table centerpieces', $centerpieces['label']);
        $this->assertSame('#059669', $centerpieces['color']);
        $this->assertSame('12 tables × 12 stems = 144 stems', $centerpieces['items'][0]['quantity_basis']);
        $this->assertNull($centerpieces['items'][2]['quantity']);

        // Forms see quantities only: no AI price reaches the breakdown.
        $this->assertSame(DecorAreaBreakdown::MODE_QUANTITIES, $breakdown['mode']);
        $this->assertNull($breakdown['raw_total_php']);
        $this->assertSame([null], array_unique(array_column($centerpieces['items'], 'amount')));
        $this->assertNull($centerpieces['subtotal_php']);
    }

    public function test_the_server_retags_every_row_to_a_picked_area_or_shared(): void
    {
        $analysis = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', 120, 35, 'centerpieces'),
            $this->row('Candles', 10, 40, 'vip_table', null, 'prop', 'tables'),   // unpicked area, visual "tables": centerpieces
            $this->row('Arch Roses', 40, 35, 'entrance', null, 'flower', 'entrance'), // unpicked area, no picked match: shared
            $this->row('Ribbon', 2, 50, 'garden', null, 'supply'),                // not an area at all
            ['item_name' => 'Floral Foam', 'category' => 'supply', 'quantity' => 4, 'unit_type' => 'piece', 'unit_cost_php' => 25],
        ]], ['decor_areas' => ['centerpieces', 'aisle']]);

        $this->assertSame(['centerpieces', 'centerpieces', 'shared', 'shared', 'shared'], array_column($analysis['suggested_materials'], 'decor_area'));
    }

    public function test_there_is_no_breakdown_without_areas_to_decorate(): void
    {
        $rows = [$this->row('White Roses', 20, 35, 'centerpieces')];

        $this->assertNull(DecorAreaBreakdown::build($rows, null));
        $this->assertNull(DecorAreaBreakdown::build($rows, ['theme' => 'rustic']));
        $this->assertNull(DecorAreaBreakdown::build([], ['decor_areas' => ['centerpieces']]));
    }

    public function test_ai_area_fields_are_cleaned_and_rows_without_them_are_left_alone(): void
    {
        $analysis = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', 20, 35, ' Centerpieces ', "  12 tables ×\n 12 stems  "),
            $this->row('Floral Foam', 4, 25, 'ceiling', ['not text']),
            ['item_name' => 'Baby\'s Breath', 'category' => 'flower', 'quantity' => 5, 'unit_type' => 'bunch', 'unit_cost_php' => 120],
        ]]);

        [$roses, $foam, $filler] = $analysis['suggested_materials'];
        $this->assertSame('centerpieces', $roses['decor_area']);
        $this->assertSame('12 tables × 12 stems', $roses['quantity_basis']);
        $this->assertNull($foam['decor_area']);
        $this->assertNull($foam['quantity_basis']);
        $this->assertArrayNotHasKey('decor_area', $filler);
        $this->assertArrayNotHasKey('quantity_basis', $filler);
    }

    // ------------------------------------------------------------------ Counting

    public function test_a_counting_that_does_not_give_the_quantity_is_flagged_for_review(): void
    {
        foreach ([
            ['15 tables × 1 centerpiece × 12 stems = 180 stems', 180, null],
            ['15 tables × 1 centerpiece × 12 stems = 180 stems', 150, 'The AI counting gives 180 but its quantity is 150.'],
            ['15 tables × 12 stems = 160 stems', 160, 'The AI counting multiplies to 180, not 160.'],
            ['12 tables x 12 stems = 144 stems', 144, null],
            ['1 box × 4 bunches = 4 bunches', 4, null],
            ['150 guests ÷ 10 per table = 15 tables', 15, null],
            ['12 tables × 12 stems + 10% spare = 158 stems', 158, null],
            ['10-12 stems per vase × 5 vases = 55 stems', 55, null],
            ['one arch, fully covered', 1, null],
        ] as [$basis, $quantity, $problem]) {
            $this->assertSame($problem, DecorAreaBreakdown::basisProblem($basis, (float) $quantity), $basis);
        }

        $row = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', 150, 35, 'centerpieces', '15 tables × 12 stems = 180 stems'),
        ]], ['decor_areas' => ['centerpieces']])['suggested_materials'][0];

        // The quantity itself is never changed; staff decide.
        $this->assertSame(150.0, $row['quantity']);
        $this->assertTrue($row['needs_review']);
        $this->assertStringContainsString('The AI counting gives 180 but its quantity is 150.', $row['review_message']);
    }

    public function test_prompt_requires_a_picked_area_or_shared_and_a_counting_for_every_row(): void
    {
        Http::fake(['*' => Http::response($this->geminiText(['suggested_materials' => [$this->row('White Roses', 20, 35, 'centerpieces')]]))]);
        $vision = new GeminiVisionService();

        $vision->analyzeImageFromPath($this->tempImage(), null, 'wedding', scaleContext: 'Tables: 12.', designBrief: ['decor_areas' => ['entrance', 'centerpieces']]);
        $vision->analyzeImageFromPath($this->tempImage(), null, 'wedding', designBrief: ['theme' => 'rustic']);

        $prompts = [];
        Http::assertSent(function (HttpRequest $request) use (&$prompts) {
            $prompts[] = (string) (json_decode($request->body(), true)['contents'][0]['parts'][0]['text'] ?? '');

            return true;
        });

        $this->assertStringContainsString('`decor_area` must be exactly one of "entrance" (Entrance or arch), "centerpieces" (Table centerpieces), or "shared"', $prompts[0]);
        $this->assertStringContainsString('No other value is accepted', $prompts[0]);
        $this->assertStringContainsString('leave out materials that serve none of the listed areas', $prompts[0]);
        $this->assertStringContainsString('ending with "= <quantity> <unit>"', $prompts[0]);
        $this->assertStringContainsString('a material used in two areas gets one row per area', $prompts[0]);
        $this->assertStringNotContainsString('decor_area', $prompts[1]);
    }

    // ------------------------------------------------------------------ Raflora prices

    public function test_area_subtotals_split_verified_line_prices_and_add_up_to_the_lines(): void
    {
        $rows = [
            $this->row('White Roses', 120, 35, 'centerpieces', '10 tables × 12 stems = 120 stems'),
            $this->row('White Roses', 40, 35, 'entrance'),
            $this->row('Floral Foam', 10, 25, 'shared', null, 'supply'),
            $this->row('Peonies', 30, 200, 'centerpieces'),                     // never made it onto the booking
        ];
        $brief = ['decor_areas' => ['entrance', 'centerpieces', 'aisle']];
        $lines = [
            ['id' => 1, 'name' => 'White Roses', 'quantity' => 200, 'unit_price' => 30],   // admin raised 160 to 200
            ['id' => 2, 'name' => 'floral foam', 'quantity' => 10, 'unit_price' => 0],     // no Raflora price yet
            ['id' => 3, 'name' => 'Table Runner', 'quantity' => 12, 'unit_price' => 50],   // added by Raflora
        ];

        $breakdown = DecorAreaBreakdown::build($rows, $brief, $lines);
        [$entrance, $centerpieces, $aisle, $shared, $raflora] = $breakdown['areas'];

        $this->assertSame(DecorAreaBreakdown::MODE_COSTS, $breakdown['mode']);
        $this->assertSame(['entrance', 'centerpieces', 'aisle', 'shared', 'raflora'], array_column($breakdown['areas'], 'key'));

        // The roses line (200 × ₱30 = ₱6,000) is split 120:40 between the two areas that use it.
        $this->assertSame(150.0, $centerpieces['items'][0]['quantity']);
        $this->assertSame(120.0, $centerpieces['items'][0]['ai_quantity']);
        $this->assertSame(4500.0, $centerpieces['items'][0]['amount']);
        $this->assertSame(50.0, $entrance['items'][0]['quantity']);
        $this->assertSame(1500.0, $entrance['items'][0]['amount']);
        $this->assertSame(30.0, $entrance['items'][0]['unit_price']);

        $this->assertFalse($centerpieces['items'][1]['in_booking']);
        $this->assertNull($centerpieces['items'][1]['amount']);
        $this->assertSame(4500.0, $centerpieces['subtotal_php']);
        $this->assertSame(1, $centerpieces['pending_count']);
        $this->assertTrue($aisle['missing']);
        $this->assertNull($shared['items'][0]['amount']);
        $this->assertSame(['Table Runner'], array_column($raflora['items'], 'name'));
        $this->assertSame(600.0, $raflora['subtotal_php']);

        // Area subtotals only add up existing line amounts: 6,000 + 0 + 600.
        $this->assertSame(6600.0, $breakdown['raw_total_php']);
        $this->assertSame(6600.0, array_sum(array_column($breakdown['areas'], 'subtotal_php')));
    }

    public function test_quote_shares_follow_the_material_cost_and_add_up_to_the_quote(): void
    {
        $rows = [
            $this->row('White Roses', 120, 35, 'centerpieces'),
            $this->row('White Roses', 40, 35, 'entrance'),
            $this->row('Floral Foam', 10, 25, 'shared', null, 'supply'),
        ];
        $lines = [
            ['name' => 'White Roses', 'quantity' => 160, 'unit_price' => 30],
            ['name' => 'Floral Foam', 'quantity' => 10, 'unit_price' => 25],
            ['name' => 'Table Runner', 'quantity' => 1, 'unit_price' => 10],
        ];

        // ₱5,060 of materials quoted at ₱15,180 + ₱1,000.01 labor.
        $breakdown = DecorAreaBreakdown::build($rows, ['decor_areas' => ['entrance', 'centerpieces']], $lines, 16180.01);
        $shares = array_column($breakdown['areas'], 'share_php', 'key');

        $this->assertSame(DecorAreaBreakdown::MODE_SHARE, $breakdown['mode']);
        $this->assertEquals(round(1200 / 5060 * 16180.01, 2), $shares['entrance']);
        $this->assertEquals(round(250 / 5060 * 16180.01, 2), $shares['shared']);
        $this->assertSame(16180.01, round(array_sum($shares), 2));
        $this->assertSame(71.1, $breakdown['areas'][1]['share_percent']);
    }

    // ------------------------------------------------------------------ Requests and pages

    public function test_client_photo_analysis_returns_quantities_per_picked_area(): void
    {
        Storage::fake('local');
        $user = $this->clientUser();
        Http::fake(['*' => Http::sequence()
            ->push($this->geminiText(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($this->geminiText(['suggested_materials' => [
                $this->row('White Roses', 120, 35, 'centerpieces', '10 tables × 12 stems = 120 stems'),
                $this->row('Floral Foam', 10, 25, 'shared', null, 'supply'),
            ]]))
            ->push($this->geminiText(['suggested_materials' => [$this->row('White Roses', 20, 35, 'centerpieces')]])),
        ]);
        $request = fn (array $brief) => [
            'inspiration_image' => UploadedFile::fake()->createWithContent('tables.png', $this->pngBytes()),
            'event_type' => 'wedding',
            'table_count' => 10,
        ] + $brief;

        $response = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), $request(['decor_areas' => ['centerpieces', 'aisle']]))
            ->assertOk()
            ->assertJsonPath('analysis.area_breakdown.mode', 'quantities')
            ->assertJsonPath('analysis.area_breakdown.areas.0.key', 'centerpieces')
            ->assertJsonPath('analysis.area_breakdown.areas.0.items.0.line_id', 'm1')
            ->assertJsonPath('analysis.area_breakdown.areas.0.items.0.quantity_basis', '10 tables × 12 stems = 120 stems')
            ->assertJsonPath('analysis.area_breakdown.areas.1.key', 'aisle')
            ->assertJsonPath('analysis.area_breakdown.areas.1.missing', true)
            ->assertJsonPath('analysis.area_breakdown.areas.2.key', 'shared')
            ->assertJsonPath('analysis.area_breakdown.missing_areas.0.label', 'Aisle')
            ->assertJsonPath('analysis.area_breakdown.raw_total_php', null)
            ->assertJsonPath('analysis.area_breakdown.areas.0.subtotal_php', null)
            ->assertJsonPath('analysis.suggested_materials.0.decor_area', 'centerpieces');
        $this->assertArrayHasKey('area_breakdown', json_decode($response->json('analysis_data'), true));

        $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), $request(['theme' => 'rustic']))
            ->assertOk()
            ->assertJsonMissingPath('analysis.area_breakdown');
    }

    public function test_a_material_split_across_areas_keeps_both_quantities_on_one_booking_line(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = $this->clientUser();
        $roses = InventoryItem::create(['name' => 'White Roses', 'category' => 'flowers', 'is_perishable' => true, 'current_stock' => 500, 'min_stock' => 10, 'unit_cost' => 30, 'unit' => 'stem']);
        $image = UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes());
        $token = Str::random(40);

        $this->actingAs($user)->withSession([
            "booking_analysis_payloads.{$token}" => [
                'image_hash' => sha1_file($image->getRealPath()),
                'temp_path' => 'bookings/inspiration-images/arch.png',
                'analysis' => ['suggested_materials' => [
                    $this->row('White Roses', 120, 35, 'centerpieces'),
                    $this->row('White Roses', 40, 35, 'entrance'),
                ]],
                'raw_response' => 'mocked',
            ],
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Manila Hotel',
            'inspiration_image' => $image,
            'decor_areas' => ['centerpieces', 'entrance'],
            'analysis_token' => $token,
        ])->assertSessionHasNoErrors();

        $booking = Booking::firstOrFail();
        $line = BookingItem::where('booking_id', $booking->id)->where('inventory_item_id', $roses->id)->sole();
        $this->assertSame(['decor_areas' => ['entrance', 'centerpieces']], $booking->design_brief);
        $this->assertSame(160.0, (float) $line->quantity);
        // Both AI rows point at that line, so each area's cost is read from Raflora's price on it.
        $this->assertSame([$line->id, $line->id], array_column(AiAnalysisResult::where('booking_id', $booking->id)->sole()->suggested_materials, 'booking_item_id'));
    }

    public function test_admin_sees_raw_costs_staff_quantities_and_the_client_a_share_once_quoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $clientUser = $this->clientUser();
        $booking = Booking::create([
            'client_id' => Client::where('email', $clientUser->email)->value('id'),
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Manila Hotel, Manila',
            'design_brief' => ['decor_areas' => ['centerpieces', 'aisle']],
            'staff_id' => $staff->id,
            'status' => 'approved',
            'total_quoted' => 15000,
        ]);
        $roses = BookingItem::create(['booking_id' => $booking->id, 'item_name' => 'White Roses', 'quantity' => 144, 'quoted_unit_price' => 30, 'ai_recommended_price' => 35, 'is_ai_suggested' => true]);
        BookingItem::create(['booking_id' => $booking->id, 'item_name' => 'Floral Foam', 'quantity' => 20, 'quoted_unit_price' => 0, 'ai_recommended_price' => 25, 'is_ai_suggested' => true]);
        BookingItem::create(['booking_id' => $booking->id, 'item_name' => 'Table Runner', 'quantity' => 12, 'quoted_unit_price' => 50, 'is_ai_suggested' => false]);
        AiAnalysisResult::create([
            'booking_id' => $booking->id,
            'raw_gemini_response' => 'mocked',
            'suggested_materials' => [
                $this->row('White Roses', 144, 35, 'centerpieces', '12 tables × 12 stems = 144 stems') + ['line_id' => 'm1', 'booking_item_id' => $roses->id],
                $this->row('Floral Foam', 20, 25, 'shared', null, 'supply') + ['line_id' => 'm2'],
            ],
            'analyzed_at' => now(),
        ]);

        // Admin: Raflora's raw costs per area (144 × ₱30), never the AI's ₱35.
        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSeeInOrder(['Materials per area', 'Raw total (Raflora prices)', '₱4,920.00', 'Table centerpieces', '₱4,320.00', '144 stem', 'White Roses', '₱30.00', '12 tables × 12 stems = 144 stems', 'Aisle', DecorAreaBreakdown::MISSING_MESSAGE, 'Shared & general', 'Floral Foam', 'Price pending', 'Added by Raflora', 'Table Runner', '₱600.00']);

        // Staff: the same groups and booking quantities, no prices.
        $this->actingAs($staff)->get(route('staff.events.show', $booking))
            ->assertOk()
            ->assertSeeInOrder(['Materials per area', 'Table centerpieces', '144 stem', 'White Roses', '12 tables × 12 stems = 144 stems', 'Aisle', DecorAreaBreakdown::MISSING_MESSAGE, 'Added by Raflora', 'Table Runner'])
            ->assertDontSee('₱4,320.00')
            ->assertDontSee('raw cost')
            ->assertDontSee('Price pending');

        // Client before a quotation: quantities only.
        $this->actingAs($clientUser)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSeeInOrder(['Materials per area', 'Table centerpieces', '144 stem', 'White Roses', 'Aisle', DecorAreaBreakdown::MISSING_MESSAGE, 'Each area\'s share of the price appears here once Raflora sends your quotation.'])
            ->assertDontSee('₱4,320.00')
            ->assertDontSee('% of your quote');

        // Client once the quotation is issued: each area's share of ₱14,760 (₱4,920 × 3), adding up to it.
        Quotation::create([
            'booking_id' => $booking->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 1,
            'raw_materials_sum' => 4920,
            'multiplier' => 3,
            'final_quoted_price' => 14760,
            'valid_until' => now()->addDays(7)->toDateString(),
            'items_snapshot' => [
                ['item_name' => 'White Roses', 'quantity' => 144, 'quoted_unit_price' => 30, 'amount' => 4320],
                ['item_name' => 'Table Runner', 'quantity' => 12, 'quoted_unit_price' => 50, 'amount' => 600],
            ],
        ]);
        $booking->update(['status' => 'quotation_sent', 'final_quoted_price' => 14760]);

        $this->actingAs($clientUser)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSeeInOrder(['Materials per area', 'Quoted price', '₱14,760.00', 'Table centerpieces', '₱12,960.00', '87.8% of your quote', 'Shared & general', 'Floral Foam', 'Not in your quotation', 'Added by Raflora', '₱1,800.00'])
            ->assertDontSee('₱4,320.00');
    }

    public function test_guest_request_page_shows_quantities_per_area(): void
    {
        Mail::fake();
        $analysis = ['suggested_materials' => [$this->row('White Roses', 96, 35, 'centerpieces', '8 tables × 12 stems = 96 stems')]];

        $this->post(route('guest.booking.store'), [
            'guest_name' => 'Samantha Cruz',
            'guest_email' => 'samantha.cruz@example.com',
            'guest_phone' => '09179876543',
            'guest_address' => '78 Magnolia Lane, Quezon City',
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['token-1']),
            'analysis_data' => json_encode($analysis),
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'Tagaytay City',
            'venue_specific' => 'The Glasshouse Tagaytay',
            'venue' => 'The Glasshouse Tagaytay, Tagaytay City',
            'decor_areas' => ['centerpieces', 'bouquets'],
        ])->assertSessionHasNoErrors();

        $token = Str::random(40);
        TemporaryGuestBooking::where('guest_email', 'samantha.cruz@example.com')->firstOrFail()
            ->update(['claim_token_hash' => hash('sha256', $token)]);

        $this->get(route('guest.bookings.show', $token))
            ->assertOk()
            ->assertSeeInOrder(['Materials per area', 'What the AI suggests for each area you asked us to decorate', 'Table centerpieces', '96 stem', 'White Roses', '8 tables × 12 stems = 96 stems', 'Bouquets', DecorAreaBreakdown::MISSING_MESSAGE])
            ->assertDontSee('% of your quote');
    }

    public function test_booking_forms_and_the_guest_review_step_include_the_live_breakdown(): void
    {
        $this->get(route('guest.booking.create'))->assertOk()
            ->assertSee('id="guest-area-breakdown"', false)
            ->assertSee('id="review-area-breakdown"', false)
            ->assertSee("window.RafloraAreaBreakdown?.render('review-area-breakdown', combinedAreaBreakdown(completedImages));", false)
            ->assertSee(DecorAreaBreakdown::MISSING_MESSAGE);
        $this->actingAs($this->clientUser())->get(route('bookings.create'))->assertOk()->assertSee('id="client-area-breakdown"', false);
    }

    // ------------------------------------------------------------------ Helpers

    private function row(string $name, ?float $quantity, ?float $unitCost, ?string $decorArea, mixed $basis = null, string $category = 'flower', string $area = 'tables'): array
    {
        return array_filter([
            'item_name' => $name,
            'category' => $category,
            'quantity' => $quantity,
            'unit_type' => $category === 'flower' ? 'stem' : 'piece',
            'unit_cost_php' => $unitCost,
            'area' => $area,
            'decor_area' => $decorArea,
            'quantity_basis' => $basis,
            'is_detected' => false,
            'is_recommendation' => true,
        ], fn ($value) => $value !== null);
    }

    private function clientUser(): User
    {
        $user = User::create([
            'name' => 'Area Client',
            'email' => 'area-client@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
        Client::firstOrCreate(['email' => $user->email], ['id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Test Address']);

        return $user;
    }

    private function tempImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'area') . '.png';
        file_put_contents($path, $this->pngBytes());

        return $path;
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

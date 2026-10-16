<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\AiEstimateService;
use App\Services\BookingWorkflowService;
use App\Support\InspirationPhotos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end verification covering:
 * - Guest tracking page (multiple photos, area associations, area filtering, annotations, secure image delivery)
 * - Guest claim to client booking (data preservation, design view merging, 8-stage progress tracker, pricing separation)
 * - Admin AI estimate pricing (markup separation, fixed and percentage labour, audit log, authorization)
 */
class VerificationJourneyEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const BRIEF = ['decor_areas' => ['entrance', 'stage']];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    // =========================================================================
    // A. Guest Tracking Page Verification
    // =========================================================================

    public function test_guest_tracking_displays_all_photos_areas_and_material_breakdown(): void
    {
        Storage::disk('local')->put('bookings/inspiration-images/photo1.jpg', $this->fakePng());
        Storage::disk('local')->put('bookings/inspiration-images/photo2.jpg', $this->fakePng());

        $analysis = InspirationPhotos::analysisData([
            $this->photoPayload('photo1.jpg', ['stage'], [
                $this->materialRow('White Roses', 80, 85, 'stage', true),
                $this->materialRow('Baby Breath', 20, 120, 'stage', true),
            ]),
            $this->photoPayload('photo2.jpg', ['entrance'], [
                $this->materialRow('Pink Carnation', 40, 45, 'entrance', true),
                $this->materialRow('Floral Foam', 6, 50, 'shared', false, 'supply'),
            ]),
        ], self::BRIEF);

        $token = 'guest-token-tracking-test';
        $guest = TemporaryGuestBooking::create([
            'guest_name' => 'Sarah Connor',
            'guest_email' => 'sarah@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '100 Metro Ave, Manila',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue' => 'Manila Grand Hotel',
            'booking_type' => 'custom_ai',
            'design_brief' => self::BRIEF,
            'analysis_data' => $analysis,
            'inspiration_image_path' => 'bookings/inspiration-images/photo1.jpg',
            'claim_token_hash' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $token]));
        $response->assertOk();
        $html = $response->getContent();

        // 1. Heading and identity
        $this->assertStringContainsString('Booking Request Received', $html);
        $this->assertStringContainsString('REF-GUEST-TO', $html);
        $this->assertStringContainsString('NO PAYMENT IS REQUIRED AT THIS STAGE', $html);

        // 2. Multi-photo gallery & areas
        $this->assertStringContainsString('Photo <span data-gallery-current>1</span> of 2', $html);
        $this->assertStringContainsString('data-gallery-area="stage"', $html);
        $this->assertStringContainsString('data-gallery-area="entrance"', $html);
        $this->assertStringContainsString('Inspiration photo 1 of 2 — Stage or backdrop', $html);

        // 3. Annotation viewer integration
        $this->assertStringContainsString('id="raflora-annotation-viewer"', $html);
        $this->assertStringContainsString('data-image-lightbox-trigger', $html);

        // 4. Per-area AI estimate ranges & disclaimers (±15% rule)
        $this->assertStringContainsString('AI estimate per area', $html);
        $this->assertStringContainsString('Indicative · not an official quotation', $html);
        $this->assertStringContainsString('Stage or backdrop', $html);
        $this->assertStringContainsString('Entrance or arch', $html);
        $this->assertStringContainsString('Shared Materials', $html);

        // 5. Official quotation separation
        $this->assertStringContainsString('Price provided in your official quotation', $html);
        $this->assertStringNotContainsString('unit_cost_php', $html);
        $this->assertStringNotContainsString('estimated_unit_cost', $html);

        // 6. Secure image route verification
        $img0 = $this->get(route('guest.bookings.image', ['token' => $token, 'imageIndex' => 0]));
        $img0->assertOk();
        $img1 = $this->get(route('guest.bookings.image', ['token' => $token, 'imageIndex' => 1]));
        $img1->assertOk();

        // 7. Unauthorized image access rejected
        $unauth = $this->get(route('guest.bookings.image', ['token' => 'tampered-token', 'imageIndex' => 0]));
        $unauth->assertNotFound();
    }

    // =========================================================================
    // B. Guest Claim → Client Booking Workflow Verification
    // =========================================================================

    public function test_guest_claim_preserves_data_merges_design_views_and_renders_eight_stage_progress(): void
    {
        Storage::disk('local')->put('bookings/inspiration-images/view1.jpg', $this->fakePng());
        Storage::disk('local')->put('bookings/inspiration-images/view2.jpg', $this->fakePng());
        Storage::disk('local')->put('bookings/inspiration-images/arch.jpg', $this->fakePng());

        // Inventory item for White Roses
        InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 200,
            'min_stock' => 10,
            'unit_cost' => 50.00,
            'unit' => 'stem',
        ]);

        // Two views of Stage (White Roses 100 vs 60 stem) + one Entrance photo (Arch Greenery 10 stem)
        $analysis = InspirationPhotos::analysisData([
            $this->photoPayload('view1.jpg', ['stage'], [
                $this->materialRow('White Roses', 100, 45, 'stage', true),
            ]),
            $this->photoPayload('view2.jpg', ['stage'], [
                $this->materialRow('White Roses', 60, 45, 'stage', true),
            ]),
            $this->photoPayload('arch.jpg', ['entrance'], [
                $this->materialRow('Arch Greenery', 10, 80, 'entrance', true, 'foliage'),
            ]),
        ], self::BRIEF);

        $token = 'claim-workflow-test-token';
        $temp = TemporaryGuestBooking::create([
            'guest_name' => 'John Wick',
            'guest_email' => 'john.wick@example.com',
            'guest_phone' => '09189998877',
            'guest_address' => 'Continental Manila',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(60)->toDateString(),
            'event_time' => '17:00',
            'end_time' => '23:00',
            'venue' => 'Grand Ballroom',
            'booking_type' => 'custom_ai',
            'design_brief' => self::BRIEF,
            'analysis_data' => $analysis,
            'inspiration_image_path' => 'bookings/inspiration-images/view1.jpg',
            'claim_token_hash' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $user = User::factory()->create([
            'name' => 'John Wick',
            'email' => 'john.wick@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        // 1. Submit Claim
        $claimResponse = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        $claimResponse->assertRedirect(route('bookings'));

        // 2. Verify Database State
        $temp->refresh();
        $this->assertNotNull($temp->claimed_at);
        $this->assertNotNull($temp->client_id);

        $booking = Booking::where('guest_email', 'john.wick@example.com')->firstOrFail();
        $this->assertSame($user->client->id, $booking->client_id);
        $this->assertSame('pending', $booking->status);
        $this->assertSame(Booking::DEFAULT_QUOTATION_MARKUP, (float) $booking->multiplier);

        // Verify quantities were NOT multiplied (White Roses: max 100, not 160)
        $rosesItem = BookingItem::where('booking_id', $booking->id)->where('item_name', 'White Roses')->sole();
        $this->assertSame(100.0, (float) $rosesItem->quantity);

        // Verify conflict flag is preserved on the analysis data
        $rosesAnalysis = collect($booking->ai_analysis_data['suggested_materials'])->firstWhere('item_name', 'White Roses');
        $this->assertTrue($rosesAnalysis['needs_review']);
        $this->assertTrue($rosesAnalysis['view_conflict']);
        $this->assertStringContainsString('photo 1: 100 stem vs photo 2: 60 stem', $rosesAnalysis['review_message']);

        // 3. Verify Client Booking Page
        $clientPageResponse = $this->actingAs($user)->get(route('bookings.show', ['booking' => $booking->id]));
        $clientPageResponse->assertOk();
        $html = $clientPageResponse->getContent();

        // 8-stage progress tracker: Stage 3 of 8 (Raflora Review)
        $this->assertStringContainsString('Stage 3 of 8 · Raflora Review', $html);
        $this->assertStringNotContainsString('data-workflow-phase="staff"', $html);

        // Photo gallery & area breakdown
        $this->assertStringContainsString('Photo <span data-gallery-current>1</span> of 3', $html);
        $this->assertStringContainsString('AI estimate per area', $html);
        $this->assertStringContainsString('Indicative · not an official quotation', $html);
        $this->assertStringContainsString('Not yet issued', $html);
        $this->assertStringContainsString('Raflora shares the price in your official quotation.', $html);

        // Claim does not create fake payments or issued quotations
        $this->assertSame(0, $booking->payments()->count());
        $this->assertSame(0, $booking->quotations()->count());
    }

    // =========================================================================
    // C. Admin AI Estimate Pricing Settings Verification
    // =========================================================================

    public function test_admin_settings_updates_ai_markup_and_labour_with_audit_trail_and_authorization(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

        // 1. Staff and Client forbidden from updating settings
        $this->actingAs($staff)->post(route('admin.settings.update'), ['ai_estimate_markup' => '4.0'])->assertForbidden();
        $this->actingAs($client)->post(route('admin.settings.update'), ['ai_estimate_markup' => '4.0'])->assertForbidden();

        // 2. Admin valid update (Fixed Labour)
        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'ai_estimate_markup' => '3.50',
            'ai_labour_mode' => 'fixed',
            'ai_labour_value' => '1500.00',
            'change_reason' => 'Quarterly cost calibration',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(3.50, Setting::aiEstimateMarkup());
        $this->assertSame(['mode' => 'fixed', 'value' => 1500.00], Setting::aiLabourAllowance());

        $messages = AuditLog::where('action', 'setting_updated')->get()->map(fn ($l) => (string) (((array) $l->details)['message'] ?? ''));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'AI estimate markup updated') && str_contains($m, '3.5×')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'labour & setup updated') && str_contains($m, '₱1,500.00 fixed')));

        // 3. Admin valid update (Percentage Labour)
        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'ai_estimate_markup' => '2.50',
            'ai_labour_mode' => 'percentage',
            'ai_labour_value' => '15.00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2.50, Setting::aiEstimateMarkup());
        $this->assertSame(['mode' => 'percentage', 'value' => 15.00], Setting::aiLabourAllowance());

        // 4. Invalid boundaries rejected
        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'ai_estimate_markup' => '0.50', // below min 1.0
        ])->assertSessionHasErrors('ai_estimate_markup');

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'ai_estimate_markup' => '12.00', // above max 10.0
        ])->assertSessionHasErrors('ai_estimate_markup');

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'ai_labour_mode' => 'percentage',
            'ai_labour_value' => '120.00', // above max 100%
        ])->assertSessionHasErrors('ai_labour_value');

        // Settings did not change after invalid attempts
        $this->assertSame(2.50, Setting::aiEstimateMarkup());
        $this->assertSame(['mode' => 'percentage', 'value' => 15.00], Setting::aiLabourAllowance());
    }

    // =========================================================================
    // D. Helper methods
    // =========================================================================

    private function fakePng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function materialRow(string $name, float $qty, float $price, string $area, bool $isDetected = true, string $category = 'flower'): array
    {
        return [
            'item_name' => $name,
            'category' => $category,
            'quantity' => $qty,
            'estimated_quantity' => $qty,
            'unit_type' => $category === 'flower' ? 'stem' : 'pcs',
            'unit_cost_php' => $price,
            'decor_area' => $area,
            'area' => $area,
            'is_detected' => $isDetected,
            'confidence' => 0.95,
            'boxes' => [
                ['ymin' => 100, 'xmin' => 100, 'ymax' => 500, 'xmax' => 500, 'possible' => false],
            ],
            'visual_location' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.4, 'height' => 0.4],
        ];
    }

    private function photoPayload(string $file, array $areas, array $materials): array
    {
        return [
            'original_filename' => $file,
            'temp_path' => 'bookings/inspiration-images/' . $file,
            'photo_areas' => $areas,
            'analysis' => [
                'summary' => 'Arrangement for ' . implode(', ', $areas),
                'suggested_materials' => $materials,
            ],
        ];
    }
}

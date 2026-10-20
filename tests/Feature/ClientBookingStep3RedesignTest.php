<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientBookingStep3RedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Storage::fake('local');
        Mail::fake();
    }

    private function createClientUser(): array
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
            'name' => 'Corazon Aquino',
            'mobile_number' => '09171234567',
            'address' => 'Malacañang Palace, Manila',
        ]);

        $client = Client::firstOrCreate(
            ['email' => $user->email],
            [
                'user_id' => $user->id,
                'full_name' => $user->name,
                'phone' => '09171234567',
                'address' => 'Malacañang Palace, Manila',
            ]
        );

        return [$user, $client];
    }

    private function createPackage(): Package
    {
        return Package::create([
            'title' => 'Curated Wedding Package',
            'description' => 'A wonderful set of floral decor',
            'price' => 15000.00,
            'included_items' => ['Stage Backdrop', 'Bridal Bouquet'],
            'is_active' => true,
        ]);
    }

    public function test_client_booking_step_3_renders_compact_summary_cards_and_modal(): void
    {
        [$user, $client] = $this->createClientUser();
        $this->createPackage();

        $response = $this->actingAs($user)->get(route('bookings.create'));
        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Step 3 Header & non-binding notice
        $this->assertStringContainsString('id="booking-step-3"', $html);
        $this->assertStringContainsString('Review &amp; Submit', $html);
        $this->assertStringContainsString('Booking Request Submission Notice', $html);
        $this->assertStringContainsString('NO PAYMENT IS REQUIRED AT THIS STAGE', $html);
        $this->assertStringContainsString('Submit Booking Request', $html);

        // 2. Compact 2-column review cards
        $this->assertStringContainsString('id="client-review-card-event"', $html);
        $this->assertStringContainsString('id="client-review-card-method"', $html);
        $this->assertStringContainsString('id="client-review-card-images"', $html);
        $this->assertStringContainsString('id="client-review-ai-materials-section"', $html);
        $this->assertStringContainsString('id="client-review-card-contact"', $html);
        $this->assertStringContainsString('id="client-review-card-summary"', $html);

        // 3. Authenticated contact information rendered
        $this->assertStringContainsString('Corazon Aquino', $html);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString('09171234567', $html);
        $this->assertStringContainsString('Malacañang Palace, Manila', $html);
        $this->assertStringContainsString('Authenticated', $html);

        // 4. Mobile accordion summary elements & toggle function
        $this->assertStringContainsString('id="client-review-event-mobile-summary"', $html);
        $this->assertStringContainsString('id="client-review-method-mobile-summary"', $html);
        $this->assertStringContainsString('id="client-review-media-mobile-summary"', $html);
        $this->assertStringContainsString('id="client-review-contact-mobile-summary"', $html);
        $this->assertStringContainsString('toggleClientReviewCard', $html);

        // 5. Area Details Modal elements & Accessibility
        $this->assertStringContainsString('id="booking-area-details-modal"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="area-modal-title"', $html);
        $this->assertStringContainsString('aria-describedby="area-modal-desc"', $html);
        $this->assertStringContainsString('id="area-modal-title"', $html);
        $this->assertStringContainsString('id="area-modal-desc"', $html);
        $this->assertStringContainsString('id="area-modal-note-display"', $html);
        $this->assertStringContainsString('id="area-modal-note-editor"', $html);
        $this->assertStringContainsString('id="area-modal-note-input"', $html);
        $this->assertStringContainsString('id="area-modal-edit-note-btn"', $html);
        $this->assertStringContainsString('id="area-modal-save-note-btn"', $html);
        $this->assertStringContainsString('id="area-modal-cancel-note-btn"', $html);
        $this->assertStringContainsString('id="area-modal-estimate-price"', $html);
        $this->assertStringContainsString('id="area-modal-partial-badge"', $html);
        $this->assertStringContainsString('id="area-modal-table-body"', $html);
        $this->assertStringContainsString('id="area-modal-cards-list"', $html);
        $this->assertStringContainsString('id="area-modal-toggle-more-btn"', $html);

        // 6. Modal JavaScript functions & interoperability handles
        $this->assertStringContainsString('openAreaDetailsModal', $html);
        $this->assertStringContainsString('closeAreaDetailsModal', $html);
        $this->assertStringContainsString('syncModalNoteUI', $html);
        $this->assertStringContainsString('renderModalMaterials', $html);
        $this->assertStringContainsString('RafloraStageBackdropModal', $html);

        // 7. Quotation vs Estimate Disclaimers
        $this->assertStringContainsString('Seasonal Context', $html);
        $this->assertStringContainsString('Business Truth', $html);
        $this->assertStringContainsString('AI detection ≠ procurement feasibility ≠ inventory availability ≠ official quotation', $html);
        $this->assertStringContainsString('INDICATIVE - NOT AN OFFICIAL QUOTATION', $html);
        $this->assertStringContainsString('Official pricing confirmed in Raflora quotation', $html);
    }

    public function test_client_booking_submission_persists_area_notes_in_design_brief(): void
    {
        Http::fake();

        [$user, $client] = $this->createClientUser();

        Storage::disk('local')->put('bookings/temp-analysis/client-stage.jpg', 'fake-image-bytes');

        $session = [
            'booking_analysis_payloads' => [
                'tok-client-stage' => [
                    'analysis' => [
                        'suggested_materials' => [
                            [
                                'item_name' => 'White Hydrangeas',
                                'category' => 'flower',
                                'unit_type' => 'stem',
                                'quantity' => 40,
                                'unit_cost_php' => 80,
                                'decor_area' => 'stage',
                                'is_detected' => true,
                            ],
                        ],
                        'overall_assessment' => 'Client stage arrangement',
                    ],
                    'temp_path' => 'bookings/temp-analysis/client-stage.jpg',
                    'original_filename' => 'client-stage.jpg',
                    'photo_areas' => ['stage', 'entrance'],
                    'image_hash' => null,
                    'raw_response' => null,
                ],
            ],
        ];

        $stageNote = 'Please make sure backdrop arches have reinforced metal stands for outdoor breeze.';
        $entranceNote = 'Welcome board surrounded by blush garden roses and eucalyptus.';

        $response = $this->actingAs($user)->withSession($session)->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(60)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue' => 'Taal Vista Hotel, Tagaytay City',
            'venue_setting' => 'outdoor',
            'analysis_tokens' => json_encode(['tok-client-stage']),
            'decor_areas' => ['stage', 'entrance'],
            'decor_area_notes' => [
                'stage' => $stageNote,
                'entrance' => $entranceNote,
            ],
            'theme' => 'rustic',
            'guest_count' => 120,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $booking = Booking::where('client_id', $client->id)->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertSame('Taal Vista Hotel, Tagaytay City', $booking->venue);
        $this->assertIsArray($booking->design_brief);
        $this->assertSame($stageNote, $booking->design_brief['decor_area_notes']['stage'] ?? null);
        $this->assertSame($entranceNote, $booking->design_brief['decor_area_notes']['entrance'] ?? null);
    }
}

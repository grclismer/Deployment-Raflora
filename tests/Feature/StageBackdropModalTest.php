<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Support\DecorAreaBreakdown;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Stage or Backdrop Modal test suite:
 * Verifies real Raflora data contracts, complete/incomplete/unavailable estimate states,
 * absence of fabricated prices, client-specific note handling with 500-char limits,
 * escaping, accessibility attributes, and guest/client booking integration.
 */
class StageBackdropModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Storage::fake('local');
        Mail::fake();
    }

    public function test_stage_modal_renders_real_materials_and_hides_internal_costs(): void
    {
        $materials = [
            [
                'id' => 101,
                'item_name' => 'White Phalaenopsis Orchids',
                'name' => 'White Phalaenopsis Orchids',
                'category' => 'Fresh Flowers',
                'quantity' => 24,
                'unit_type' => 'stems',
                'decor_area' => 'stage',
                'is_detected' => true,
                'quantity_basis' => '4 clusters × 6 stems across upper arch',
                'needs_review' => false,
                'cost_price' => 150.00, // internal wholesale cost
                'unit_price' => 350.00, // internal unit price
                'markup' => 3.79,       // internal markup
            ],
            [
                'id' => 102,
                'item_name' => 'Eucalyptus Garlands',
                'name' => 'Eucalyptus Garlands',
                'category' => 'Foliage',
                'quantity' => 6,
                'unit_type' => 'meters',
                'decor_area' => 'stage',
                'is_detected' => false,
                'quantity_basis' => 'Draped along backdrop frame perimeter',
                'needs_review' => true,
                'cost_price' => 80.00,
                'unit_price' => 200.00,
            ],
            [
                'id' => 103,
                'item_name' => 'Entrance Welcome Blooms',
                'name' => 'Entrance Welcome Blooms',
                'category' => 'Fresh Flowers',
                'quantity' => 10,
                'unit_type' => 'stems',
                'decor_area' => 'entrance',
            ],
        ];

        $html = view('components.stage-backdrop-modal', [
            'materials' => $materials,
            'brief' => [
                'decor_areas' => ['stage', 'entrance'],
                'decor_area_notes' => ['stage' => 'Keep center clear for bridal couple entrance.'],
            ],
            'estimate' => [
                'groups' => [
                    [
                        'key' => 'stage',
                        'status' => 'complete',
                        'display' => '₱8,500 – ₱11,500',
                        'note' => 'Indicative range based on 2 stage materials.',
                    ],
                ],
                'labour_included' => false,
            ],
        ])->render();

        // 1. Stage area title and focal badge
        $this->assertStringContainsString('Stage or backdrop', $html);
        $this->assertStringContainsString('Focal Area', $html);

        // 2. Stage materials rendered
        $this->assertStringContainsString('White Phalaenopsis Orchids', $html);
        $this->assertStringContainsString('24', $html);
        $this->assertStringContainsString('stems', $html);
        $this->assertStringContainsString('Detected', $html);
        $this->assertStringContainsString('4 clusters × 6 stems across upper arch', $html);

        $this->assertStringContainsString('Eucalyptus Garlands', $html);
        $this->assertStringContainsString('6', $html);
        $this->assertStringContainsString('meters', $html);
        $this->assertStringContainsString('Suggested', $html);
        $this->assertStringContainsString('Flagged for Raflora review', $html);

        // 3. Entrance material is NOT rendered in stage modal
        $this->assertStringNotContainsString('Entrance Welcome Blooms', $html);

        // 4. Strict privacy: Internal wholesale costs, markups, or unit costs MUST NEVER appear
        $this->assertStringNotContainsString('cost_price', $html);
        $this->assertStringNotContainsString('unit_price', $html);
        $this->assertStringNotContainsString('150.00', $html);
        $this->assertStringNotContainsString('3.79', $html);
    }

    public function test_stage_modal_handles_complete_indicative_estimate_state(): void
    {
        $materials = [
            [
                'id' => 1,
                'item_name' => 'White Roses',
                'quantity' => 50,
                'decor_area' => 'stage',
            ],
        ];

        $html = view('components.stage-backdrop-modal', [
            'materials' => $materials,
            'estimate' => [
                'groups' => [
                    [
                        'key' => 'stage',
                        'status' => 'complete',
                        'display' => '₱12,000 – ₱14,500',
                        'note' => 'Benchmark price range for stage flowers.',
                    ],
                ],
                'labour_included' => true,
            ],
        ])->render();

        $this->assertStringContainsString('₱12,000 – ₱14,500', $html);
        $this->assertStringContainsString('Indicative · ±15%', $html);
        $this->assertStringContainsString('Includes florist labour &amp; setup', $html);
        $this->assertStringContainsString('Benchmark price range for stage flowers.', $html);
        $this->assertStringNotContainsString('Partial Estimate', $html);
        $this->assertStringNotContainsString('Price pending florist quotation', $html);
    }

    public function test_stage_modal_handles_incomplete_partial_estimate_state(): void
    {
        $materials = [
            [
                'id' => 1,
                'item_name' => 'Custom Arch Structure',
                'quantity' => 1,
                'decor_area' => 'stage',
            ],
        ];

        $html = view('components.stage-backdrop-modal', [
            'materials' => $materials,
            'estimate' => [
                'groups' => [
                    [
                        'key' => 'stage',
                        'status' => 'incomplete',
                        'display' => '₱4,500 – ₱5,500',
                        'note' => 'Price could only be estimated for some items.',
                    ],
                ],
                'labour_included' => false,
            ],
        ])->render();

        $this->assertStringContainsString('₱4,500 – ₱5,500', $html);
        $this->assertStringContainsString('Partial Estimate', $html);
        $this->assertStringContainsString('Labour &amp; setup not included', $html);
        $this->assertStringContainsString('Price could only be estimated for some items.', $html);
    }

    public function test_stage_modal_handles_unavailable_estimate_state_without_fake_prices(): void
    {
        $materials = [
            [
                'id' => 1,
                'item_name' => 'Bespoke Neon Monogram',
                'quantity' => 1,
                'decor_area' => 'stage',
            ],
        ];

        $html = view('components.stage-backdrop-modal', [
            'materials' => $materials,
            'estimate' => [
                'groups' => [
                    [
                        'key' => 'stage',
                        'status' => 'unavailable',
                        'display' => null,
                        'note' => null,
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Price pending florist quotation', $html);
        $this->assertStringContainsString('The AI could not benchmark prices for these materials.', $html);
        $this->assertStringNotContainsString('Partial Estimate', $html);

        // Verify absence of fabricated numbers
        $this->assertStringNotContainsString('₱0', $html);
        $this->assertStringNotContainsString('₱25,000', $html);
        $this->assertStringNotContainsString('₱50,000', $html);
    }

    public function test_stage_modal_handles_missing_materials_state(): void
    {
        // No materials for stage
        $materials = [
            [
                'id' => 1,
                'item_name' => 'Table Runner',
                'quantity' => 10,
                'decor_area' => 'centerpieces',
            ],
        ];

        $html = view('components.stage-backdrop-modal', [
            'materials' => $materials,
            'brief' => [
                'decor_areas' => ['stage'],
            ],
        ])->render();

        $this->assertStringContainsString(DecorAreaBreakdown::MISSING_MESSAGE, $html);
        $this->assertStringContainsString('data-missing-notice', $html);
    }

    public function test_stage_modal_escapes_client_note_and_enforces_maxlength(): void
    {
        $maliciousNote = '<script>alert("xss")</script> & "arch" note with \'quotes\'';

        $html = view('components.stage-backdrop-modal', [
            'materials' => [],
            'clientNote' => $maliciousNote,
        ])->render();

        // 1. Must be HTML-escaped
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString(e($maliciousNote), $html);

        // 2. Textarea max length 500
        $this->assertStringContainsString('maxlength="500"', $html);
        $this->assertStringContainsString('data-note-textarea', $html);
        $this->assertStringContainsString('data-note-char-count', $html);
    }

    public function test_stage_modal_accessibility_and_dom_attributes(): void
    {
        $html = view('components.stage-backdrop-modal', [
            'id' => 'custom-stage-modal',
            'materials' => [],
        ])->render();

        // Dialog accessibility attributes
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="custom-stage-modal-title"', $html);
        $this->assertStringContainsString('aria-describedby="custom-stage-modal-desc"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('data-stage-backdrop-modal', $html);
        $this->assertStringContainsString('data-action="close-modal"', $html);

        // Script handles
        $this->assertStringContainsString('window.RafloraStageBackdropModal', $html);
        $this->assertStringContainsString('e.key === \'Escape\'', $html);
    }

    public function test_guest_booking_submission_persists_stage_client_note(): void
    {
        Http::fake();

        $stageNote = 'Keep backdrop colors ivory and sage green, no dark burgundy.';

        Storage::disk('local')->put('bookings/inspiration-images/stage.jpg', 'fake-image-bytes');

        $session = [
            'booking_analysis_payloads' => [
                'tok-guest-stage' => [
                    'analysis' => [
                        'suggested_materials' => [
                            [
                                'item_name' => 'White Roses',
                                'category' => 'flower',
                                'unit_type' => 'stem',
                                'quantity' => 100,
                                'unit_cost_php' => 35,
                                'decor_area' => 'stage',
                                'is_detected' => true,
                            ],
                        ],
                        'overall_assessment' => 'Stage floral backdrop',
                    ],
                    'temp_path' => 'bookings/inspiration-images/stage.jpg',
                    'original_filename' => 'stage.jpg',
                    'photo_areas' => ['stage'],
                    'image_hash' => null,
                    'raw_response' => null,
                ],
            ],
        ];

        $response = $this->withSession($session)->post(route('guest.booking.store'), [
            'guest_name' => 'Maria Clara',
            'guest_email' => 'maria@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Rizal St, Manila',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '16:00',
            'end_time' => '21:00',
            'venue_city' => 'Tagaytay City',
            'venue_specific' => 'Grand Pavilion Tagaytay',
            'venue' => 'Grand Pavilion Tagaytay, Tagaytay City',
            'venue_setting' => 'indoor',
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['tok-guest-stage']),
            'decor_areas' => ['stage'],
            'decor_area_notes' => [
                'stage' => $stageNote,
            ],
            'theme' => 'rustic',
            'guest_count' => 100,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $tempBooking = TemporaryGuestBooking::where('guest_email', 'maria@example.com')->first();
        $this->assertNotNull($tempBooking);
        $this->assertIsArray($tempBooking->design_brief);
        $this->assertSame($stageNote, $tempBooking->design_brief['decor_area_notes']['stage'] ?? null);
    }

    public function test_authenticated_client_booking_submission_persists_stage_client_note(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create([
            'user_id' => $user->id,
            'full_name' => 'Juan Dela Cruz',
            'email' => $user->email,
            'phone' => '09181234567',
            'address' => '78 Ayala Ave, Makati',
        ]);

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

        $stageNote = 'Stage backdrop width is 12ft, request tall pedestal arrangements on both sides.';

        $response = $this->actingAs($user)->withSession($session)->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'event_time' => '17:00',
            'end_time' => '22:00',
            'venue' => 'Manila Diamond Hotel, Manila',
            'venue_setting' => 'indoor',
            'analysis_tokens' => json_encode(['tok-client-stage']),
            'decor_areas' => ['stage', 'entrance'],
            'decor_area_notes' => [
                'stage' => $stageNote,
                'entrance' => 'Arch with twinkling fairy lights',
            ],
            'theme' => 'elegant',
            'guest_count' => 150,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $booking = Booking::where('client_id', $client->id)->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertIsArray($booking->design_brief);
        $this->assertSame($stageNote, $booking->design_brief['decor_area_notes']['stage'] ?? null);
        $this->assertSame('Arch with twinkling fairy lights', $booking->design_brief['decor_area_notes']['entrance'] ?? null);
    }

    public function test_inspiration_photos_component_includes_area_details_dialog_and_stage_contracts(): void
    {
        $html = view('components.inspiration-photos', [
            'prefix' => 'guest',
            'analyzeUrl' => route('guest.bookings.analyze-temp-image'),
        ])->render();

        // 1. Area details dialog structure
        $this->assertStringContainsString('data-dialog="areas"', $html);
        $this->assertStringContainsString('id="area-details-dialog"', $html);
        $this->assertStringContainsString('data-area-tabs', $html);
        $this->assertStringContainsString('data-area-body', $html);
        $this->assertStringContainsString('data-action="open-areas"', $html);

        // 2. Client note dialog structure & limits
        $this->assertStringContainsString('data-dialog="note"', $html);
        $this->assertStringContainsString('id="area-note-dialog"', $html);
        $this->assertStringContainsString('id="area-note-text"', $html);
        $this->assertStringContainsString('maxlength="500"', $html);
        $this->assertStringContainsString('data-action="save-area-note"', $html);

        // 3. JS area navigation logic (supporting data-area targeting and state restore)
        $this->assertStringContainsString('trigger.dataset.area', $html);
        $this->assertStringContainsString('noteOpenedFromAreas', $html);
        $this->assertStringContainsString('Price pending florist quotation', $html);
        $this->assertStringContainsString('Partial', $html);
    }
}


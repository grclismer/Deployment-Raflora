<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Item;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\GeminiVisionService;
use App\Support\EventDesignBrief;
use App\Support\InspirationPhotos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Step2CompactionAndAreaPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function createFakePng(string $filename = 'inspiration.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        return UploadedFile::fake()->createWithContent($filename, $png);
    }

    public function test_guest_step2_renders_compact_gallery_pagination_area_selector_and_estimate_summary(): void
    {
        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // Compact gallery elements
        $this->assertStringContainsString('id="uploaded-images-section"', $html);
        $this->assertStringContainsString('id="uploaded-images-grid"', $html);
        $this->assertStringContainsString('id="uploaded-count-badge"', $html);

        // Paginated inspiration image analysis workspace controls
        $this->assertStringContainsString('id="img-nav-current"', $html);
        $this->assertStringContainsString('id="img-nav-total"', $html);
        $this->assertStringContainsString('id="btn-img-prev"', $html);
        $this->assertStringContainsString('id="btn-img-next"', $html);

        // Focused selected-image workspace and area selector
        $this->assertStringContainsString('id="ai-material-preview-card"', $html);
        $this->assertStringContainsString('id="viewer-photo-area-selector"', $html);
        $this->assertStringContainsString('id="viewer-photo-area-chips"', $html);
        $this->assertStringContainsString('id="viewer-photo-areas"', $html);

        // Material navigation in workspace
        $this->assertStringContainsString('id="mat-btn-prev"', $html);
        $this->assertStringContainsString('id="mat-btn-next"', $html);
        $this->assertStringContainsString('id="mat-current-idx"', $html);
        $this->assertStringContainsString('id="mat-total-count"', $html);
        $this->assertStringContainsString('id="mat-quick-list"', $html);

        // Compact Step 2: one shared component, gallery pages, a summary and detail dialogs
        $this->assertStringContainsString('id="guest-inspiration-photos"', $html);
        $this->assertStringContainsString('data-gallery-pager', $html);
        $this->assertStringContainsString('id="guest-step2-summary"', $html);
        $this->assertStringContainsString('id="photo-analysis-dialog"', $html);
        $this->assertStringContainsString('id="area-details-dialog"', $html);
        $this->assertStringContainsString('id="price-breakdown-dialog"', $html);
        $this->assertStringContainsString('id="design-preferences-modal"', $html);
        $this->assertStringContainsString('id="guest-ai-estimate"', $html);

        // Scalable decoration-area selector and per-area notes in design brief
        $this->assertStringContainsString('data-decor-area-section', $html);
        $this->assertStringContainsString('data-selected-chips', $html);
        $this->assertStringContainsString('data-add-area-btn', $html);
        $this->assertStringContainsString('data-area-search', $html);
        $this->assertStringContainsString('data-area-note-editor', $html);
        $this->assertStringContainsString('data-note-textarea', $html);
        $this->assertStringContainsString('data-hidden-area-inputs', $html);
    }

    public function test_client_step2_renders_compact_gallery_and_estimate_summary(): void
    {
        $user = User::factory()->create();
        Client::create([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);

        $response = $this->actingAs($user)->get(route('bookings.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // The same Step 2 component as the guest form, then a review step before submitting
        $this->assertStringContainsString('id="client-inspiration-photos"', $html);
        $this->assertStringContainsString('id="uploaded-images-grid"', $html);
        $this->assertStringContainsString('data-gallery-pager', $html);
        $this->assertStringContainsString('id="photo-analysis-dialog"', $html);
        $this->assertStringContainsString('id="client-ai-estimate"', $html);
        $this->assertStringContainsString('id="booking-step-3"', $html);
        $this->assertStringContainsString('id="client-review-ai-estimate"', $html);

        // Scalable decoration-area selector in client view
        $this->assertStringContainsString('data-decor-area-section', $html);
        $this->assertStringContainsString('data-selected-chips', $html);
        $this->assertStringContainsString('data-add-area-btn', $html);
        $this->assertStringContainsString('data-area-note-editor', $html);
    }

    public function test_per_area_notes_validation_and_sanitization(): void
    {
        // 1. Valid notes for picked areas
        $input = [
            'decor_areas' => ['stage', 'centerpieces'],
            'decor_area_notes' => [
                'stage' => 'Prefer white flowers and soft blush accents.',
                'centerpieces' => 'Keep arrangements low so guests can see each other.',
                'entrance' => 'Should be ignored because entrance was not selected.',
            ],
        ];

        $brief = EventDesignBrief::fromInput($input);
        $this->assertNotNull($brief);
        $this->assertCount(2, $brief['decor_area_notes']);
        $this->assertEquals('Prefer white flowers and soft blush accents.', $brief['decor_area_notes']['stage']);
        $this->assertEquals('Keep arrangements low so guests can see each other.', $brief['decor_area_notes']['centerpieces']);
        $this->assertArrayNotHasKey('entrance', $brief['decor_area_notes']);

        // 2. Note exceeding max 500 characters
        $invalidInput = [
            'decor_areas' => ['stage'],
            'decor_area_notes' => [
                'stage' => str_repeat('A', 501),
            ],
        ];

        $validator = validator($invalidInput, EventDesignBrief::rules(), EventDesignBrief::messages());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('decor_area_notes.stage', $validator->errors()->toArray());
    }

    public function test_guest_booking_submission_stores_decor_area_notes(): void
    {
        Storage::fake('local');

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')->andReturn(['is_valid' => true]);
            $mock->shouldReceive('analyzeImageFromPath')->andReturn([
                'analysis' => [
                    'suggested_materials' => [
                        [
                            'item_name' => 'White Rose',
                            'category' => 'flower',
                            'quantity' => 24,
                            'estimated_quantity' => 24,
                            'unit_type' => 'stem',
                            'is_detected' => true,
                            'decor_area' => 'stage',
                        ],
                    ],
                    'overall_assessment' => 'Test arrangement',
                ],
            ]);
        });

        $sessionToken = Str::uuid()->toString();

        $context = [
            'event_type' => 'wedding',
            'event_date' => Carbon::tomorrow()->format('Y-m-d'),
            'event_time' => '10:00',
            'end_time' => '15:00',
            'venue' => 'Grand Ballroom, Taguig',
            'venue_setting' => 'indoor',
            'scale' => 'Tables: 10. Guest Count (Pax): 100.',
            'special_requests' => null,
            'design_brief' => [
                'decor_areas' => ['stage'],
                'decor_area_notes' => [
                    'stage' => 'White roses only with eucalyptus greenery.',
                ],
            ],
        ];

        $contextHash = GeminiVisionService::analysisContextHash($context);

        Storage::disk('local')->put('temp/inspiration.png', 'fake-image-bytes');

        $payload = [
            'token' => $sessionToken,
            'original_filename' => 'inspiration.png',
            'temp_path' => 'temp/inspiration.png',
            'nonce' => 'test-nonce',
            'photo_areas' => ['stage'],
            'has_context' => true,
            'context_hash' => $contextHash,
            'analysis' => [
                'suggested_materials' => [
                    [
                        'item_name' => 'White Rose',
                        'category' => 'flower',
                        'quantity' => 24,
                        'estimated_quantity' => 24,
                        'unit_type' => 'stem',
                        'is_detected' => true,
                        'decor_area' => 'stage',
                    ],
                ],
            ],
        ];

        $postData = [
            'guest_name' => 'Maria Santos',
            'guest_email' => 'maria@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Taguig City',
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => Carbon::tomorrow()->format('Y-m-d'),
            'event_time' => '10:00',
            'end_time' => '15:00',
            'venue_city' => 'Taguig',
            'venue_specific' => 'Grand Ballroom',
            'venue' => 'Grand Ballroom, Taguig',
            'venue_setting' => 'indoor',
            'table_count' => 10,
            'guest_count' => 100,
            'analysis_tokens' => json_encode([$sessionToken]),
            'decor_areas' => ['stage'],
            'decor_area_notes' => [
                'stage' => 'White roses only with eucalyptus greenery.',
            ],
        ];

        $response = $this->withSession([
            "booking_analysis_payloads.{$sessionToken}" => $payload,
        ])->post(route('guest.booking.store'), $postData);

        $response->assertStatus(302);

        $guestBooking = TemporaryGuestBooking::where('guest_email', 'maria@example.com')->first();
        $this->assertNotNull($guestBooking);
        $this->assertIsArray($guestBooking->design_brief);
        $this->assertEquals(['stage'], $guestBooking->design_brief['decor_areas']);
        $this->assertEquals('White roses only with eucalyptus greenery.', $guestBooking->design_brief['decor_area_notes']['stage']);
    }

    public function test_client_booking_submission_stores_decor_area_notes(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Client::create([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => '09171234567',
            'address' => 'Taguig City',
        ]);

        $sessionToken = Str::uuid()->toString();

        $context = [
            'event_type' => 'wedding',
            'event_date' => Carbon::tomorrow()->format('Y-m-d'),
            'event_time' => '10:00',
            'end_time' => '15:00',
            'venue' => 'Shangri-La The Fort, BGC',
            'venue_setting' => 'indoor',
            'scale' => 'Tables: 8. Guest Count (Pax): 80.',
            'special_requests' => null,
            'design_brief' => [
                'decor_areas' => ['stage', 'centerpieces'],
                'decor_area_notes' => [
                    'stage' => 'Romantic arch with blush blooms.',
                    'centerpieces' => 'Low compote vessels.',
                ],
            ],
        ];

        $contextHash = GeminiVisionService::analysisContextHash($context);

        Storage::disk('local')->put('temp/client_inspiration.png', 'fake-image-bytes');

        $payload = [
            'token' => $sessionToken,
            'original_filename' => 'client_inspiration.png',
            'temp_path' => 'temp/client_inspiration.png',
            'nonce' => 'client-nonce',
            'photo_areas' => ['stage', 'centerpieces'],
            'has_context' => true,
            'context_hash' => $contextHash,
            'analysis' => [
                'suggested_materials' => [
                    [
                        'item_name' => 'Pink Rose',
                        'category' => 'flower',
                        'quantity' => 40,
                        'estimated_quantity' => 40,
                        'unit_type' => 'stem',
                        'is_detected' => true,
                        'decor_area' => 'stage',
                    ],
                ],
            ],
        ];

        $postData = [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => Carbon::tomorrow()->format('Y-m-d'),
            'event_time' => '10:00',
            'end_time' => '15:00',
            'venue' => 'Shangri-La The Fort, BGC',
            'venue_setting' => 'indoor',
            'table_count' => 8,
            'guest_count' => 80,
            'analysis_tokens' => json_encode([$sessionToken]),
            'decor_areas' => ['stage', 'centerpieces'],
            'decor_area_notes' => [
                'stage' => 'Romantic arch with blush blooms.',
                'centerpieces' => 'Low compote vessels.',
            ],
        ];

        $response = $this->actingAs($user)->withSession([
            "booking_analysis_payloads.{$sessionToken}" => $payload,
        ])->post(route('bookings.store'), $postData);

        $response->assertStatus(302);

        $booking = Booking::where('client_id', $user->id)->first();
        $this->assertNotNull($booking);
        $this->assertIsArray($booking->design_brief);
        $this->assertEquals('Romantic arch with blush blooms.', $booking->design_brief['decor_area_notes']['stage']);
        $this->assertEquals('Low compote vessels.', $booking->design_brief['decor_area_notes']['centerpieces']);
    }

    public function test_ai_estimate_endpoint_returns_combined_total_and_per_area_ranges(): void
    {
        $sessionToken = Str::uuid()->toString();

        $payload = [
            'token' => $sessionToken,
            'original_filename' => 'stage.png',
            'temp_path' => 'temp/stage.png',
            'photo_areas' => ['stage'],
            'analysis' => [
                'suggested_materials' => [
                    [
                        'item_name' => 'White Hydrangea',
                        'category' => 'flower',
                        'quantity' => 20,
                        'estimated_quantity' => 20,
                        'unit_type' => 'stem',
                        'unit_cost_php' => 150.00,
                        'is_detected' => true,
                        'decor_area' => 'stage',
                    ],
                ],
            ],
        ];

        $response = $this->withSession([
            "booking_analysis_payloads.{$sessionToken}" => $payload,
        ])->postJson(route('bookings.analysis-estimate'), [
            'analysis_tokens' => [$sessionToken],
            'decor_areas' => ['stage'],
            'decor_area_notes' => [
                'stage' => 'Keep lush and voluminous.',
            ],
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('estimate', $json);
        $this->assertArrayHasKey('groups', $json['estimate']);
        $this->assertArrayHasKey('combined', $json['estimate']);

        // Stage group range check
        $stageGroup = collect($json['estimate']['groups'])->firstWhere('key', 'stage');
        $this->assertNotNull($stageGroup);
        $this->assertEquals('complete', $stageGroup['status']);
        $this->assertStringContainsString('₱', $stageGroup['display']);

        // Combined range check
        $combined = $json['estimate']['combined'];
        $this->assertEquals('complete', $combined['status']);
        $this->assertStringContainsString('₱', $combined['display']);
        $this->assertStringContainsString('Combined preliminary AI estimate', $combined['note']);
        $this->assertStringNotContainsString('₱0', $combined['display']);
    }

    public function test_multiple_photos_for_same_area_take_max_quantities_without_double_counting(): void
    {
        $photos = [
            [
                'id' => 1,
                'photo_areas' => ['stage'],
                'suggested_materials' => [
                    ['item_name' => 'White Rose', 'quantity' => 20, 'estimated_quantity' => 20, 'unit_type' => 'stem', 'decor_area' => 'stage'],
                ],
            ],
            [
                'id' => 2,
                'photo_areas' => ['stage'],
                'suggested_materials' => [
                    ['item_name' => 'White Rose', 'quantity' => 35, 'estimated_quantity' => 35, 'unit_type' => 'stem', 'decor_area' => 'stage'],
                ],
            ],
        ];

        $combined = InspirationPhotos::combine($photos);

        // Should take maximum (35), not sum (55)
        $this->assertCount(1, $combined);
        $this->assertEquals(35, $combined[0]['quantity']);
    }

    public function test_incomplete_estimates_do_not_produce_false_zero_total(): void
    {
        $sessionToken = Str::uuid()->toString();

        // Material has no matching item in inventory, so unit_cost_php is null
        $payload = [
            'token' => $sessionToken,
            'original_filename' => 'unpriced.png',
            'temp_path' => 'temp/unpriced.png',
            'photo_areas' => ['entrance'],
            'analysis' => [
                'suggested_materials' => [
                    [
                        'item_name' => 'Rare Exotic Blossom Unpriced',
                        'category' => 'flower',
                        'quantity' => 10,
                        'estimated_quantity' => 10,
                        'unit_type' => 'stem',
                        'decor_area' => 'entrance',
                        'unit_cost_php' => null,
                    ],
                ],
            ],
        ];

        $response = $this->withSession([
            "booking_analysis_payloads.{$sessionToken}" => $payload,
        ])->postJson(route('bookings.analysis-estimate'), [
            'analysis_tokens' => [$sessionToken],
            'decor_areas' => ['entrance'],
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $group = collect($json['estimate']['groups'])->firstWhere('key', 'entrance');
        $this->assertNotNull($group);
        $this->assertEquals('unavailable', $group['status']);
        $this->assertNull($group['display']);

        $combined = $json['estimate']['combined'];
        $this->assertEquals('unavailable', $combined['status']);
        $this->assertNull($combined['display']);
        $this->assertStringNotContainsString('₱0', (string) $combined['display']);
    }
}

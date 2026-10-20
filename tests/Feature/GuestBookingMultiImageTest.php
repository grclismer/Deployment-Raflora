<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\TemporaryGuestBooking;
use App\Services\GeminiVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GuestBookingMultiImageTest extends TestCase
{
    use RefreshDatabase;

    private function createTestPackage(): Package
    {
        return Package::create([
            'title' => 'Curated Grand Floral Package',
            'description' => 'Elegant arrangements for celebrations',
            'price' => 12500.00,
            'included_items' => ['Bridal Bouquet', 'Table Centerpieces', 'Stage Floral Arch'],
            'is_active' => true,
        ]);
    }

    private function createFakePng(string $filename = 'test.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        return UploadedFile::fake()->createWithContent($filename, $png);
    }

    public function test_guest_booking_create_view_renders_multi_image_and_3_column_analysis_components(): void
    {
        $package = $this->createTestPackage();

        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Upload Tips Modal
        $this->assertStringContainsString('id="upload-tips-modal"', $html);
        $this->assertStringContainsString('Upload Tips', $html);

        // 2. Uploaded Images Grid & Per-Image State
        $this->assertStringContainsString('id="uploaded-images-section"', $html);
        $this->assertStringContainsString('id="uploaded-images-grid"', $html);
        $this->assertStringContainsString('id="uploaded-count-badge"', $html);

        // 3. Truthful Indeterminate Progress Tracking Card
        $this->assertStringContainsString('id="ai-progress-card"', $html);
        $this->assertStringContainsString('id="progress-card-title"', $html);
        $this->assertStringContainsString('id="progress-img-thumb"', $html);
        $this->assertStringContainsString('id="progress-indeterminate-bar"', $html);
        $this->assertStringContainsString('Analyzing your inspiration image. Gemini is identifying floral materials and preparing suggestions. This may take a moment.', $html);
        $this->assertStringNotContainsString('id="progress-step-1"', $html);
        $this->assertStringNotContainsString('Identifying floral materials...', $html);

        // 4. 3-Column AI Analysis Viewer
        $this->assertStringContainsString('id="ai-material-preview-card"', $html);
        // The photo number shows once (beside the arrows); the header shows the photo's areas.
        $this->assertStringNotContainsString('id="viewer-img-title"', $html);
        $this->assertStringContainsString('id="viewer-photo-areas"', $html);
        $this->assertStringContainsString('id="viewer-enlarge-btn"', $html);
        $this->assertStringContainsString('id="raflora-annotation-viewer"', $html);
        $this->assertStringContainsString('id="img-nav-current"', $html);
        $this->assertStringContainsString('id="img-nav-total"', $html);
        $this->assertStringContainsString('id="btn-img-prev"', $html);
        $this->assertStringContainsString('id="btn-img-next"', $html);

        // Column 1: Image stage, Original vs AI Annotated tabs, summary
        $this->assertStringContainsString('id="tab-img-original"', $html);
        $this->assertStringContainsString('id="tab-img-annotated"', $html);
        $this->assertStringContainsString('id="viewer-main-img"', $html);
        $this->assertStringContainsString('id="viewer-markers-container"', $html);
        $this->assertStringContainsString('id="viewer-summary-text"', $html);
        $this->assertStringContainsString('id="viewer-badge-mats"', $html);
        $this->assertStringContainsString('id="viewer-badge-style"', $html);
        $this->assertStringContainsString('id="viewer-badge-conf"', $html);

        // Column 2: Material details, reference image with fallback, badges, seasonal & alternative boxes
        $this->assertStringContainsString('id="mat-btn-prev"', $html);
        $this->assertStringContainsString('id="mat-btn-next"', $html);
        $this->assertStringContainsString('id="mat-ref-img"', $html);
        $this->assertStringContainsString('id="mat-ref-fallback"', $html);
        $this->assertStringContainsString('Reference image unavailable', $html);
        $this->assertStringContainsString('id="mat-source-badge"', $html);
        $this->assertStringContainsString('id="mat-category-badge"', $html);
        $this->assertStringContainsString('id="mat-name"', $html);
        $this->assertStringContainsString('id="mat-qty"', $html);
        $this->assertStringContainsString('id="mat-unit"', $html);
        $this->assertStringContainsString('id="mat-area"', $html);
        $this->assertStringContainsString('id="mat-seasonal-box"', $html);
        $this->assertStringContainsString('id="mat-alt-box"', $html);

        // No AI price or estimate: prices come only with the official quotation
        $this->assertStringNotContainsString('id="mat-unit-cost"', $html);
        $this->assertStringNotContainsString('id="mat-grand-total"', $html);
        $this->assertStringNotContainsString('AI-Assisted Initial Estimate', $html);
        $this->assertStringContainsString('shares the price in your official quotation', $html);

        // Column 3: All materials quick jump list
        $this->assertStringContainsString('id="mat-quick-list"', $html);
        $this->assertStringContainsString('id="mat-quick-count"', $html);

        // 5. Review Page Multi-Image summary
        $this->assertStringContainsString('id="review-multi-images-row"', $html);
        $this->assertStringContainsString('id="review-multi-thumbnails"', $html);
    }

    public function test_multiple_images_can_be_analyzed_independently(): void
    {
        Storage::fake('local');

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')
                ->andReturn(['is_valid' => true]);

            // First image analysis result
            $mock->shouldReceive('analyzeImageFromPath')
                ->once()
                ->andReturn([
                    'analysis' => [
                        'suggested_materials' => [
                            [
                                'item_name' => 'White Ecuadorian Rose',
                                'category' => 'flower',
                                'quantity' => 24,
                                'estimated_quantity' => 24,
                                'unit_type' => 'stem',
                                'unit_cost_php' => 85.00,
                                'is_detected' => true,
                                'confidence' => 0.96,
                                'area' => 'centerpiece',
                            ],
                        ],
                        'pricing_summary' => [
                            'estimated_total_cost' => 2040.00,
                            'estimated_grand_total_php' => 2040.00,
                        ],
                        'visual_analysis' => [
                            'detected_arrangement' => ['style' => 'Centerpiece'],
                            'composition_confidence' => 0.95,
                        ],
                        'image_valid' => true,
                        'is_usable' => true,
                    ],
                ]);

            // Second image analysis result
            $mock->shouldReceive('analyzeImageFromPath')
                ->once()
                ->andReturn([
                    'analysis' => [
                        'suggested_materials' => [
                            [
                                'item_name' => 'Blue Hydrangea',
                                'category' => 'flower',
                                'quantity' => 12,
                                'estimated_quantity' => 12,
                                'unit_type' => 'stem',
                                'unit_cost_php' => 150.00,
                                'is_detected' => true,
                                'confidence' => 0.92,
                                'area' => 'arch',
                            ],
                        ],
                        'pricing_summary' => [
                            'estimated_total_cost' => 1800.00,
                            'estimated_grand_total_php' => 1800.00,
                        ],
                        'visual_analysis' => [
                            'detected_arrangement' => ['style' => 'Arch Setup'],
                            'composition_confidence' => 0.91,
                        ],
                        'image_valid' => true,
                        'is_usable' => true,
                    ],
                ]);
        });

        // Analyze Image 1
        $file1 = $this->createFakePng('centerpiece.png');
        $res1 = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $file1,
            'event_type' => 'wedding',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'event_time' => '14:00',
            'end_time' => '20:00',
            'venue_city' => 'TAGAYTAY',
            'venue_specific' => 'Hillside Garden',
            'venue' => 'Hillside Garden, TAGAYTAY',
        ]);

        $res1->assertStatus(200);
        $res1->assertJson(['success' => true]);
        $data1 = $res1->json();
        $this->assertNotEmpty($data1['analysis_token']);
        $this->assertNotEmpty($data1['analysis_temp_path']);
        $this->assertEquals('White Ecuadorian Rose', $data1['analysis']['suggested_materials'][0]['item_name']);

        // Analyze Image 2
        $file2 = $this->createFakePng('arch.png');
        $res2 = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $file2,
            'event_type' => 'wedding',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'event_time' => '14:00',
            'end_time' => '20:00',
            'venue_city' => 'TAGAYTAY',
            'venue_specific' => 'Hillside Garden',
            'venue' => 'Hillside Garden, TAGAYTAY',
        ]);

        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);
        $data2 = $res2->json();
        $this->assertNotEmpty($data2['analysis_token']);
        $this->assertNotEquals($data1['analysis_token'], $data2['analysis_token']);
        $this->assertEquals('Blue Hydrangea', $data2['analysis']['suggested_materials'][0]['item_name']);
    }

    public function test_guest_booking_stores_multiple_images_and_preserves_independent_materials(): void
    {
        Storage::fake('local');

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')
                ->andReturn(['is_valid' => true]);

            $mock->shouldReceive('analyzeImageFromPath')
                ->andReturn([
                    'analysis' => [
                        'suggested_materials' => [
                            ['item_name' => 'Rose', 'quantity' => 10, 'estimated_quantity' => 10, 'estimated_unit_cost_php' => 50, 'unit_cost_php' => 50],
                        ],
                        'pricing_summary' => ['estimated_grand_total_php' => 500],
                        'image_valid' => true,
                        'is_usable' => true,
                    ],
                ]);
        });

        // Upload and analyze two images
        $file1 = $this->createFakePng('img1.png');
        $res1 = $this->postJson(route('guest.bookings.analyze-temp-image'), ['inspiration_image' => $file1]);
        $token1 = $res1->json('analysis_token');
        $payload1 = session("booking_analysis_payloads.{$token1}");

        $file2 = $this->createFakePng('img2.png');
        $res2 = $this->postJson(route('guest.bookings.analyze-temp-image'), ['inspiration_image' => $file2]);
        $token2 = $res2->json('analysis_token');
        $payload2 = session("booking_analysis_payloads.{$token2}");

        // Submit guest booking with multiple analysis tokens
        $submitData = [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token1, $token2]),
            'analysis_token' => $token1, // backward-compat fallback
            'event_type' => 'wedding',
            'event_date' => date('Y-m-d', strtotime('+40 days')),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'MANILA',
            'venue_specific' => 'Hotel Ballroom',
            'venue' => 'Hotel Ballroom, MANILA',
            'guest_name' => 'Maria Santos',
            'guest_email' => 'maria@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Rizal Ave, Manila',
        ];

        $postRes = $this->withSession([
            "booking_analysis_payloads.{$token1}" => $payload1,
            "booking_analysis_payloads.{$token2}" => $payload2,
        ])->post(route('guest.booking.store'), $submitData);

        $postRes->assertStatus(302);
        
        $tempBooking = TemporaryGuestBooking::where('guest_email', 'maria@example.com')->first();
        $this->assertNotNull($tempBooking);

        // Check analysis_data contains images array with 2 images
        $this->assertIsArray($tempBooking->analysis_data);
        $this->assertArrayHasKey('images', $tempBooking->analysis_data);
        $this->assertCount(2, $tempBooking->analysis_data['images']);

        // Check primary inspiration image set
        $this->assertNotNull($tempBooking->inspiration_image);
        Storage::disk('local')->assertExists($tempBooking->inspiration_image);

        // Check top-level suggested_materials aggregated for backward compatibility
        $this->assertArrayHasKey('suggested_materials', $tempBooking->analysis_data);
        $this->assertNotEmpty($tempBooking->analysis_data['suggested_materials']);

        // Check secure image viewing route for both images using the raw claim token
        $redirectUrl = $postRes->headers->get('Location');
        $rawToken = basename(parse_url($redirectUrl, PHP_URL_PATH));
        $this->assertNotEmpty($rawToken);

        $img0Res = $this->get(route('guest.bookings.image', [
            'token' => $rawToken,
            'imageIndex' => 0,
        ]));
        $img0Res->assertStatus(200);

        $img1Res = $this->get(route('guest.bookings.image', [
            'token' => $rawToken,
            'imageIndex' => 1,
        ]));
        $img1Res->assertStatus(200);

        // Unauthorized access without token rejected
        $unauthRes = $this->get(route('guest.bookings.image', [
            'token' => 'invalid-token',
            'imageIndex' => 0,
        ]));
        $unauthRes->assertStatus(404);
    }

    public function test_single_image_guest_booking_maintains_exact_backward_compatibility(): void
    {
        Storage::fake('local');

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')
                ->once()
                ->andReturn(['is_valid' => true]);

            $mock->shouldReceive('analyzeImageFromPath')
                ->once()
                ->andReturn([
                    'analysis' => [
                        'suggested_materials' => [
                            ['item_name' => 'Sunflower', 'quantity' => 15, 'estimated_quantity' => 15, 'estimated_unit_cost_php' => 60, 'unit_cost_php' => 60],
                        ],
                        'pricing_summary' => ['estimated_grand_total_php' => 900],
                        'image_valid' => true,
                        'is_usable' => true,
                    ],
                ]);
        });

        $file = $this->createFakePng('single_sunflower.png');
        $res = $this->postJson(route('guest.bookings.analyze-temp-image'), ['inspiration_image' => $file]);
        $token = $res->json('analysis_token');
        $payload = session("booking_analysis_payloads.{$token}");

        // Submit with legacy single token parameter
        $submitData = [
            'booking_type' => 'custom_ai',
            'analysis_token' => $token,
            'event_type' => 'birthday',
            'event_date' => date('Y-m-d', strtotime('+20 days')),
            'event_time' => '10:00',
            'end_time' => '16:00',
            'venue_city' => 'QUEZON CITY',
            'venue_specific' => 'Events Pavilion',
            'venue' => 'Events Pavilion, QUEZON CITY',
            'guest_name' => 'Juan Dela Cruz',
            'guest_email' => 'juan@example.com',
            'guest_phone' => '09187654321',
            'guest_address' => '45 Katipunan Ave, QC',
        ];

        $postRes = $this->withSession([
            "booking_analysis_payloads.{$token}" => $payload,
        ])->post(route('guest.booking.store'), $submitData);
        $postRes->assertStatus(302);

        $tempBooking = TemporaryGuestBooking::where('guest_email', 'juan@example.com')->first();
        $this->assertNotNull($tempBooking);
        $this->assertNotNull($tempBooking->inspiration_image);
        Storage::disk('local')->assertExists($tempBooking->inspiration_image);
        $this->assertEquals('Sunflower', $tempBooking->analysis_data['suggested_materials'][0]['item_name']);
    }

    public function test_guest_tracking_view_renders_multi_image_navigation_and_disclaimers(): void
    {
        Storage::fake('local');

        $path1 = 'bookings/inspiration-images/test_img1.jpg';
        $path2 = 'bookings/inspiration-images/test_img2.jpg';
        Storage::disk('local')->put($path1, 'fake-img-1');
        Storage::disk('local')->put($path2, 'fake-img-2');

        $token = 'test-guest-token-12345';
        $booking = TemporaryGuestBooking::create([
            'guest_name' => 'Elena Cruz',
            'guest_email' => 'elena@example.com',
            'guest_phone' => '09191112233',
            'guest_address' => 'Makati City',
            'event_type' => 'wedding',
            'event_date' => date('Y-m-d', strtotime('+35 days')),
            'event_time' => '13:00',
            'end_time' => '19:00',
            'venue' => 'The Peninsula, MAKATI',
            'booking_type' => 'custom_ai',
            'inspiration_image' => $path1,
            'analysis_data' => [
                'suggested_materials' => [
                    ['item_name' => 'Peonies', 'quantity' => 20, 'estimated_unit_cost_php' => 120, 'is_detected' => true],
                    ['item_name' => 'Eucalyptus', 'quantity' => 10, 'estimated_unit_cost_php' => 40, 'is_detected' => true],
                ],
                'pricing_summary' => ['estimated_grand_total_php' => 2800.00],
                'images' => [
                    [
                        'image_path' => $path1,
                        'original_filename' => 'peonies_bouquet.jpg',
                        'suggested_materials' => [
                            ['item_name' => 'Peonies', 'quantity' => 20, 'estimated_unit_cost_php' => 120, 'is_detected' => true],
                        ],
                    ],
                    [
                        'image_path' => $path2,
                        'original_filename' => 'eucalyptus_table.jpg',
                        'suggested_materials' => [
                            ['item_name' => 'Eucalyptus', 'quantity' => 10, 'estimated_unit_cost_php' => 40, 'is_detected' => true],
                        ],
                    ],
                ],
            ],
            'claim_token_hash' => hash('sha256', $token),
            'guest_access_token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        $res = $this->get(route('guest.bookings.show', ['token' => $token]));
        $res->assertStatus(200);

        $html = $res->getContent();

        // Multi-photo gallery: every photo, one counter, thumbnails and the enlarged viewer
        $this->assertStringContainsString('Photo <span data-gallery-current>1</span> of 2', $html);
        $this->assertSame(2, substr_count($html, 'data-gallery-thumb="'));
        $this->assertStringContainsString('id="raflora-annotation-viewer"', $html);

        // One indicative overall AI range (no areas picked), distinct from the official quotation:
        // ₱2,800 of AI materials × 3 → ₱7,140 – ₱9,660
        $this->assertStringContainsString('Indicative · not an official quotation', $html);
        $this->assertStringContainsString('₱7,140 – ₱9,660', $html);

        // No AI estimate before a quotation: neither the ₱2,800 total nor per-item AI costs reach the page
        $this->assertStringNotContainsString('AI-Assisted Initial Estimate', $html);
        $this->assertStringContainsString('Price provided in your official quotation', $html);
        $this->assertStringNotContainsString('2,800', $html);
        $this->assertStringNotContainsString('estimated_unit_cost_php', $html);
        $this->assertStringContainsString('Peonies', $html);
        $this->assertStringContainsString('Eucalyptus', $html);
    }

    public function test_guest_tracking_view_renders_image_level_traceability_and_material_tabs(): void
    {
        Storage::fake('local');

        $path1 = 'bookings/inspiration-images/img_a.jpg';
        $path2 = 'bookings/inspiration-images/img_b.jpg';
        Storage::disk('local')->put($path1, 'fake-bytes-a');
        Storage::disk('local')->put($path2, 'fake-bytes-b');

        $token = 'traceability-test-token';
        $booking = TemporaryGuestBooking::create([
            'guest_name' => 'Clara Oswald',
            'guest_email' => 'clara@example.com',
            'guest_phone' => '09170001122',
            'guest_address' => 'Taguig City',
            'event_type' => 'wedding',
            'event_date' => date('Y-m-d', strtotime('+45 days')),
            'event_time' => '14:00',
            'end_time' => '20:00',
            'venue' => 'BGC Taguig',
            'booking_type' => 'custom_ai',
            'inspiration_image' => $path1,
            'analysis_data' => [
                'suggested_materials' => [
                    ['item_name' => 'White Hydrangea', 'quantity' => 15, 'estimated_unit_cost_php' => 100, 'image_id' => 1],
                    ['item_name' => 'Pink Carnation', 'quantity' => 30, 'estimated_unit_cost_php' => 45, 'image_id' => 2],
                ],
                'pricing_summary' => ['estimated_grand_total_php' => 2850.00],
                'images' => [
                    [
                        'image_path' => $path1,
                        'original_filename' => 'stage_hydrangea.jpg',
                        'suggested_materials' => [
                            ['item_name' => 'White Hydrangea', 'quantity' => 15, 'estimated_unit_cost_php' => 100, 'image_id' => 1],
                        ],
                    ],
                    [
                        'image_path' => $path2,
                        'original_filename' => 'table_carnation.jpg',
                        'suggested_materials' => [
                            ['item_name' => 'Pink Carnation', 'quantity' => 30, 'estimated_unit_cost_php' => 45, 'image_id' => 2],
                        ],
                    ],
                ],
            ],
            'claim_token_hash' => hash('sha256', $token),
            'guest_access_token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        $res = $this->get(route('guest.bookings.show', ['token' => $token]));
        $res->assertStatus(200);
        $html = $res->getContent();

        // 1. Material tabs for per-image filtering
        $this->assertStringContainsString('id="tracking-material-tabs"', $html);
        $this->assertStringContainsString('tracking-mat-tab', $html);
        $this->assertStringContainsString('All Materials', $html);
        $this->assertStringContainsString('Photo 1 (1)', $html);
        $this->assertStringContainsString('Photo 2 (1)', $html);

        // 2. Photo-level badges on individual materials
        $this->assertStringContainsString('Inspiration Photo 1', $html);
        $this->assertStringContainsString('Inspiration Photo 2', $html);

        // 3. Visual identity: Custom AI, not package
        $this->assertStringContainsString('Your Design Request', $html);
        $this->assertStringContainsString('Smart AI Custom Design', $html);
        // The photo count shows once, in the gallery.
        $this->assertStringContainsString('Photo <span data-gallery-current>1</span> of 2', $html);
        $this->assertStringNotContainsString('2 inspiration images', $html);
    }

    public function test_curated_package_booking_displays_package_identity_and_catalog_price(): void
    {
        $package = $this->createTestPackage();

        $token = 'package-booking-token';
        $booking = TemporaryGuestBooking::create([
            'guest_name' => 'Carlos Mendoza',
            'guest_email' => 'carlos@example.com',
            'guest_phone' => '09189998877',
            'guest_address' => 'Pasig City',
            'event_type' => 'corporate',
            'event_date' => date('Y-m-d', strtotime('+50 days')),
            'event_time' => '09:00',
            'end_time' => '17:00',
            'venue' => 'Ortigas Center, Pasig',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'claim_token_hash' => hash('sha256', $token),
            'guest_access_token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        $res = $this->get(route('guest.bookings.show', ['token' => $token]));
        $res->assertStatus(200);
        $html = $res->getContent();

        // Curated package visual identity
        $this->assertStringContainsString('Selected Package', $html);
        $this->assertStringContainsString('Curated Package', $html);
        $this->assertStringContainsString('Curated Grand Floral Package', $html);
        $this->assertStringContainsString('Catalog Package Price', $html);
    }
}

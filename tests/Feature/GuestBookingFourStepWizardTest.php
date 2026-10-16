<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\TemporaryGuestBooking;
use App\Services\GeminiVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GuestBookingFourStepWizardTest extends TestCase
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

    public function test_guest_booking_create_view_renders_exact_four_step_wizard_structure(): void
    {
        $package = $this->createTestPackage();

        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Form has novalidate attribute to replace browser-native validation with Raflora UX
        $this->assertStringContainsString('id="guest-booking-form"', $html);
        $this->assertStringContainsString('novalidate', $html);

        // 2. Exact four steps in progress bar
        $this->assertStringContainsString('Step 1', $html);
        $this->assertStringContainsString('Event Details', $html);
        $this->assertStringContainsString('Step 2', $html);
        $this->assertStringContainsString('Booking Method', $html);
        $this->assertStringContainsString('Step 3', $html);
        $this->assertStringContainsString('Contact Info', $html);
        $this->assertStringContainsString('Step 4', $html);
        $this->assertStringContainsString('Review & Submit', $html);

        // 3. Step containers exist
        $this->assertStringContainsString('id="guest-step-1"', $html);
        $this->assertStringContainsString('id="guest-step-2"', $html);
        $this->assertStringContainsString('id="guest-step-3"', $html);
        $this->assertStringContainsString('id="guest-step-4"', $html);

        // 4. Step 1 inputs: Event Details
        $this->assertStringContainsString('id="event_type"', $html);
        $this->assertStringContainsString('id="other_event_type"', $html);
        $this->assertStringContainsString('id="event_date"', $html);
        $this->assertStringContainsString('id="event_time"', $html);
        $this->assertStringContainsString('id="end_time"', $html);
        $this->assertStringContainsString('id="venue_city"', $html);
        $this->assertStringContainsString('id="venue_specific"', $html);
        $this->assertStringContainsString('id="guest_count"', $html);
        $this->assertStringContainsString('id="table_count"', $html);
        $this->assertStringContainsString('id="special_requests"', $html);

        // 5. Step 2 inputs: Booking Method & Compact Material Navigator
        $this->assertStringContainsString('id="guest-card-ai"', $html);
        $this->assertStringContainsString('id="guest-card-preset"', $html);
        $this->assertStringContainsString('id="dropzone_container"', $html);
        $this->assertStringContainsString('id="inspiration_image_input"', $html);
        $this->assertStringContainsString('id="ai-material-preview-card"', $html);
        $this->assertStringContainsString('id="mat-current-idx"', $html);
        $this->assertStringContainsString('id="mat-total-count"', $html);
        $this->assertStringContainsString('id="mat-btn-prev"', $html);
        $this->assertStringContainsString('id="mat-btn-next"', $html);

        // 6. Step 3 inputs: Contact Information
        $this->assertStringContainsString('id="guest_name"', $html);
        $this->assertStringContainsString('id="guest_email"', $html);
        $this->assertStringContainsString('id="guest_phone"', $html);
        $this->assertStringContainsString('id="guest_address"', $html);

        // 7. Step 4 Review displays and disclaimers
        $this->assertStringContainsString('id="review-event-type"', $html);
        $this->assertStringContainsString('id="review-event-date"', $html);
        $this->assertStringContainsString('id="review-event-time"', $html);
        $this->assertStringContainsString('id="review-venue-city"', $html);
        $this->assertStringContainsString('id="review-venue-specific"', $html);
        $this->assertStringContainsString('id="review-method-title"', $html);
        $this->assertStringContainsString('id="review-contact-name"', $html);
        $this->assertStringContainsString('id="review-contact-email"', $html);
        $this->assertStringContainsString('id="review-contact-phone"', $html);
        $this->assertStringContainsString('id="review-contact-address"', $html);
        $this->assertStringContainsString('id="review-notes"', $html);
        $this->assertStringContainsString('id="review-ai-materials-section"', $html);
        $this->assertStringContainsString('id="review-materials-list"', $html);
        $this->assertStringNotContainsString('AI-Assisted Initial Estimate', $html);
        $this->assertStringNotContainsString('id="review-estimate-val"', $html);
        $this->assertStringContainsString('In your official quotation', $html);
        $this->assertStringContainsString('NO PAYMENT IS REQUIRED AT THIS STAGE', $html);

        // 8. Fixed bug: special_requests is referenced instead of special_instructions
        $this->assertStringContainsString("document.getElementById('special_requests')", $html);
        $this->assertStringNotContainsString("document.getElementById('special_instructions')", $html);

        // 9. Verified: no hardcoded 'wedding' in AJAX context
        $this->assertStringNotContainsString("formData.append('event_type', 'wedding')", $html);
        $this->assertStringContainsString("formData.append('event_type', document.getElementById('event_type')?.value", $html);
        $this->assertStringContainsString("formData.append('event_date', document.getElementById('event_date')?.value", $html);
    }

    public function test_ajax_temp_image_analysis_receives_and_validates_step1_context(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        // Mock GeminiVisionService to verify it receives actual context
        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')
                ->once()
                ->andReturn(['is_valid' => true]);

            $mock->shouldReceive('analyzeImageFromPath')
                ->once()
                ->with(
                    Mockery::type('string'),
                    Mockery::on(fn($notes) => str_contains($notes, 'Pastel roses')),
                    Mockery::on(fn($type) => $type === 'Debut'),
                    Mockery::on(fn($start) => $start === '18:00'),
                    Mockery::on(fn($venue) => str_contains($venue, 'Seda Hotel')),
                    Mockery::on(fn($scale) => str_contains($scale, 'Tables: 15') && str_contains($scale, '150')),
                    Mockery::on(fn($date) => $date === '2026-12-15'),
                    Mockery::on(fn($end) => $end === '22:00'),
                    Mockery::on(fn($brief) => $brief === ['photo_usage' => 'style', 'decor_areas' => ['centerpieces'], 'budget_range' => '30k_60k']),
                    Mockery::on(fn($setting) => $setting === 'outdoor')
                )
                ->andReturn([
                    'model_used' => 'gemini-2.5-flash',
                    'analysis' => [
                        'suggested_materials' => [
                            [
                                'item_name' => 'Pastel Pink Rose',
                                'category' => 'flower',
                                'quantity' => 100,
                                'estimated_quantity' => 100,
                                'unit_type' => 'stems',
                                'unit_cost_php' => 45.0,
                                'area' => 'Centerpiece',
                                'is_detected' => true,
                                'is_recommendation' => false,
                                'confidence' => 0.95,
                                'suggested_alternative' => 'Spray Rose',
                                'alternative_reason' => 'More readily accessible if local supply fluctuates',
                                'seasonality_status' => 'seasonal',
                                'seasonal_notes' => 'Generally available during December event period (subject to Raflora florist validation).',
                            ],
                        ],
                        'pricing_summary' => [
                            'estimated_total_cost' => 4500.0,
                        ],
                        'image_valid' => true,
                        'is_usable' => true,
                        'detected_regions' => 1,
                    ],
                ]);
        });

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('inspiration.png', $png);

        $response = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
            'event_type' => 'Debut',
            'event_date' => '2026-12-15',
            'event_time' => '18:00',
            'end_time' => '22:00',
            'venue' => 'Seda Hotel BGC, Taguig City',
            'venue_city' => 'Taguig City',
            'venue_specific' => 'Seda Hotel BGC',
            'guest_count' => 150,
            'table_count' => 15,
            'special_requests' => 'Pastel roses theme',
            'venue_setting' => 'outdoor',
            'photo_usage' => 'style',
            'decor_areas' => ['centerpieces'],
            // With areas picked, every photo says which of them it is for.
            'photo_areas' => ['centerpieces'],
            'budget_range' => '30k_60k',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $json = $response->json();
        $this->assertNotEmpty($json['analysis_token']);
        $this->assertNotEmpty($json['analysis_data']);
        $this->assertSame('Pastel Pink Rose', $json['analysis']['suggested_materials'][0]['item_name']);
        $this->assertSame('Spray Rose', $json['analysis']['suggested_materials'][0]['suggested_alternative']);
    }

    public function test_guest_booking_tracking_page_renders_actual_ai_analysis_materials_and_disclaimers(): void
    {
        $booking = Booking::create([
            'guest_name' => 'Carla Reyes',
            'guest_email' => 'carla.reyes@example.com',
            'guest_phone' => '09181234567',
            'guest_address' => '45 Emerald Ave, Pasig City',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '20:00',
            'venue' => 'Glass Garden, Pasig City',
            'status' => 'pending',
            'guest_access_token' => 'guest-test-token-4step',
            'inspiration_image' => 'images/inspiration_test.jpg',
        ]);

        $materials = [
            [
                'item_name' => 'White Hydrangea',
                'category' => 'flower',
                'quantity' => 20,
                'estimated_quantity' => 20,
                'unit_type' => 'stems',
                'unit_cost_php' => 120.0,
                'area' => 'Arch',
                'is_detected' => true,
                'is_recommendation' => false,
                'confidence' => 0.92,
                'suggested_alternative' => 'White Chrysanthemum',
                'alternative_reason' => 'High heat resistance for afternoon ceremonies',
                'seasonality_status' => 'seasonal',
                'seasonal_notes' => 'Seasonal availability peak in cool weather (subject to supplier confirmation).',
            ],
            [
                'item_name' => 'Eucalyptus Foliage',
                'category' => 'foliage',
                'quantity' => 15,
                'estimated_quantity' => 15,
                'unit_type' => 'bunches',
                'unit_cost_php' => 90.0,
                'area' => 'Table',
                'is_detected' => false,
                'is_recommendation' => true,
                'confidence' => 0.88,
            ],
        ];

        $html = view('guest.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => $materials,
            'totalCost' => 3750.00,
            'token' => 'guest-test-token-4step',
        ])->render();

        // 1. Both materials rendered
        $this->assertStringContainsString('White Hydrangea', $html);
        $this->assertStringContainsString('Eucalyptus Foliage', $html);

        // 2. Quantities and units rendered
        $this->assertStringContainsString('20 stems', $html);
        $this->assertStringContainsString('15 bunches', $html);

        // 3. Status badges rendered
        $this->assertStringContainsString('AI Detected', $html);
        $this->assertStringContainsString('AI Recommendation', $html);

        // 4. Suggested alternative rendered with rationale
        $this->assertStringContainsString('White Chrysanthemum', $html);
        $this->assertStringContainsString('High heat resistance for afternoon ceremonies', $html);
        $this->assertStringContainsString('AI-Suggested Alternative (Subject to Raflora Validation)', $html);

        // 5. Seasonal context rendered with non-authoritative disclaimer
        $this->assertStringContainsString('Seasonal Context:', $html);
        $this->assertStringContainsString('Flower seasonality is decision support only and requires Raflora florist validation', $html);

        // 6. No AI estimate: the price comes only with the official quotation
        $this->assertStringNotContainsString('AI-Assisted Initial Estimate', $html);
        $this->assertStringNotContainsString('3,750.00', $html);
        $this->assertStringContainsString('The price appears here once your official quotation is issued.', $html);

        // 7. Guest and client workflow stages are rendered; Staff operational stages are not shown to guests
        $this->assertStringContainsString('Request Submitted', $html);
        $this->assertStringContainsString('Awaiting Claim', $html);
        $this->assertStringContainsString('Raflora Review', $html);
        $this->assertStringContainsString('Material Preparation / Validation', $html);
        $this->assertStringNotContainsString('Inventory Reconciliation', $html);
        $this->assertStringNotContainsString('Request Expires', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\GeminiVisionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Step2StaleContextAndTruthfulProgressTest extends TestCase
{
    use RefreshDatabase;

    private function createFakePng(string $filename = 'test_inspiration.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        return UploadedFile::fake()->createWithContent($filename, $png);
    }

    public function test_guest_booking_create_view_renders_truthful_loading_and_no_fake_timers(): void
    {
        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // Truthful Indeterminate Progress UI
        $this->assertStringContainsString('id="ai-progress-card"', $html);
        $this->assertStringContainsString('id="progress-card-title"', $html);
        $this->assertStringContainsString('id="progress-img-thumb"', $html);
        $this->assertStringContainsString('id="progress-indeterminate-bar"', $html);
        $this->assertStringContainsString('Analyzing your inspiration image. Gemini is identifying floral materials and preparing suggestions. This may take a moment.', $html);

        // Verification that fake timer steps and simulated stage timers are completely removed
        $this->assertStringNotContainsString('id="progress-step-1"', $html);
        $this->assertStringNotContainsString('id="progress-step-2"', $html);
        $this->assertStringNotContainsString('id="progress-step-3"', $html);
        $this->assertStringNotContainsString('id="progress-step-4"', $html);
        $this->assertStringNotContainsString('setTruthfulProgressStep', $html);
        $this->assertStringNotContainsString('Identifying floral materials...', $html);

        // AbortController and context change handling functions
        $this->assertStringContainsString('activeAiAbortController', $html);
        $this->assertStringContainsString('cancelActiveAnalysis', $html);
        $this->assertStringContainsString('getEventContextFingerprint', $html);
        $this->assertStringContainsString('handleEventContextChanged', $html);
    }

    public function test_client_booking_create_view_renders_truthful_progress_and_abort_controller(): void
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

        // Truthful loading feedback (the same indeterminate progress card as the guest form)
        $this->assertStringContainsString('id="ai-progress-card"', $html);
        $this->assertStringContainsString('id="progress-indeterminate-bar"', $html);
        $this->assertStringContainsString('Analyzing your inspiration image. Gemini is identifying floral materials and preparing suggestions. This may take a moment.', $html);
        $this->assertStringNotContainsString('setTruthfulProgressStep', $html);

        // AbortController (shared component) and context fingerprinting
        $this->assertStringContainsString('activeAiAbortController', $html);
        $this->assertStringContainsString('cancelActiveClientAnalysis', $html);
        $this->assertStringContainsString('getClientContextFingerprint', $html);
        $this->assertStringContainsString('handleClientEventContextChanged', $html);
    }

    public function test_guest_booking_submission_rejects_stale_analysis_tokens_when_event_context_modified(): void
    {
        Storage::fake('local');

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')->andReturn(['is_valid' => true]);
            $mock->shouldReceive('analyzeImageFromPath')->andReturn([
                'analysis' => [
                    'suggested_materials' => [
                        ['item_name' => 'White Rose', 'quantity' => 50, 'estimated_quantity' => 50, 'unit_cost_php' => 50],
                    ],
                    'pricing_summary' => ['estimated_grand_total_php' => 2500],
                    'image_valid' => true,
                    'is_usable' => true,
                ],
            ]);
        });

        $eventDate = Carbon::now()->addDays(30)->toDateString();

        // 1. Client analyzes photo in Step 2 with initial event context
        $file = $this->createFakePng('wedding_inspiration.png');
        $analyzeRes = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'event_time' => '14:00',
            'end_time' => '20:00',
            'venue' => 'Grand Hotel, Manila',
            'venue_city' => 'Manila',
            'venue_specific' => 'Grand Hotel',
            'guest_count' => 150,
            'table_count' => 15,
        ]);

        $analyzeRes->assertStatus(200);
        $token = $analyzeRes->json('analysis_token');
        $payload = session("booking_analysis_payloads.{$token}");
        $this->assertNotNull($payload);
        $this->assertTrue($payload['has_context']);
        $this->assertNotEmpty($payload['context_hash']);

        // 2. User changes Step 1 details (e.g., changes event_type to birthday, scale to 50 pax) and submits stale token
        $submitData = [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
            'analysis_token' => $token,
            'event_type' => 'birthday', // CHANGED FROM WEDDING!
            'event_date' => Carbon::now()->addDays(15)->toDateString(), // CHANGED DATE!
            'event_time' => '10:00',
            'end_time' => '16:00',
            'venue_city' => 'Quezon City',
            'venue_specific' => 'Events Pavilion',
            'venue' => 'Events Pavilion, Quezon City',
            'guest_count' => 50,
            'table_count' => 5,
            'guest_name' => 'Maria Santos',
            'guest_email' => 'maria.stale@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Rizal Ave, Manila',
        ];

        $postRes = $this->withSession([
            "booking_analysis_payloads.{$token}" => $payload,
        ])->post(route('guest.booking.store'), $submitData);

        // Submitting with modified context is rejected and redirected back with analysis_error
        $postRes->assertStatus(302);
        $postRes->assertSessionHas('analysis_error', 'Your event details were modified after your photos were analyzed. Please re-analyze your inspiration photos before submitting.');

        // Verify no booking was persisted
        $this->assertNull(TemporaryGuestBooking::where('guest_email', 'maria.stale@example.com')->first());
    }

    public function test_guest_booking_submission_succeeds_when_context_matches_even_if_unrelated_contact_info_changes(): void
    {
        Storage::fake('local');

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')->andReturn(['is_valid' => true]);
            $mock->shouldReceive('analyzeImageFromPath')->andReturn([
                'analysis' => [
                    'suggested_materials' => [
                        ['item_name' => 'White Rose', 'quantity' => 50, 'estimated_quantity' => 50, 'unit_cost_php' => 50],
                    ],
                    'pricing_summary' => ['estimated_grand_total_php' => 2500],
                    'image_valid' => true,
                    'is_usable' => true,
                ],
            ]);
        });

        $eventDate = Carbon::now()->addDays(30)->toDateString();

        $file = $this->createFakePng('wedding_bouquet.png');
        $analyzeRes = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'event_time' => '14:00',
            'end_time' => '20:00',
            'venue' => 'Grand Hotel, Manila',
            'guest_count' => 150,
            'table_count' => 15,
        ]);

        $analyzeRes->assertStatus(200);
        $token = $analyzeRes->json('analysis_token');
        $payload = session("booking_analysis_payloads.{$token}");

        // Submitting with exact matching event details, but non-context contact info (name, email, phone)
        $submitData = [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
            'analysis_token' => $token,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'event_time' => '14:00',
            'end_time' => '20:00',
            'venue_city' => 'Manila',
            'venue_specific' => 'Grand Hotel',
            'venue' => 'Grand Hotel, Manila',
            'guest_count' => 150,
            'table_count' => 15,
            'guest_name' => 'Updated Contact Name',
            'guest_email' => 'valid.contact@example.com',
            'guest_phone' => '09998887766',
            'guest_address' => '99 Ayala Ave, Makati',
        ];

        $postRes = $this->withSession([
            "booking_analysis_payloads.{$token}" => $payload,
        ])->post(route('guest.booking.store'), $submitData);

        $postRes->assertStatus(302);
        $postRes->assertSessionMissing('analysis_error');

        $booking = TemporaryGuestBooking::where('guest_email', 'valid.contact@example.com')->first();
        $this->assertNotNull($booking);
        $this->assertEquals('wedding', $booking->event_type);
    }

    public function test_client_booking_submission_rejects_stale_analysis_tokens_when_event_context_modified(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Client::create([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => '09171234567',
            'address' => 'Makati City',
        ]);

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')->andReturn(['is_valid' => true]);
            $mock->shouldReceive('analyzeImageFromPath')->andReturn([
                'analysis' => [
                    'suggested_materials' => [
                        ['item_name' => 'Tulip', 'quantity' => 30, 'estimated_quantity' => 30, 'unit_cost_php' => 80],
                    ],
                    'pricing_summary' => ['estimated_grand_total_php' => 2400],
                    'image_valid' => true,
                    'is_usable' => true,
                ],
            ]);
        });

        $eventDate = Carbon::now()->addDays(45)->toDateString();

        // 1. Analyze with initial event context
        $file = $this->createFakePng('client_floral.png');
        $analyzeRes = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
            'event_type' => 'corporate',
            'event_date' => $eventDate,
            'event_time' => '09:00',
            'end_time' => '17:00',
            'venue' => 'BGC Convention Center',
            'guest_count' => 300,
            'table_count' => 30,
        ]);

        $analyzeRes->assertStatus(200);
        $token = $analyzeRes->json('analysis_token');
        $payload = session("booking_analysis_payloads.{$token}");
        $this->assertNotNull($payload);
        $this->assertTrue($payload['has_context']);

        // 2. Submit booking request with modified event context (e.g. event_type changed to birthday)
        $postRes = $this->actingAs($user)->withSession([
            "booking_analysis_payloads.{$token}" => $payload,
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
            'event_type' => 'birthday', // CHANGED
            'event_date' => Carbon::now()->addDays(20)->toDateString(), // CHANGED
            'event_time' => '18:00',
            'end_time' => '22:00',
            'venue' => 'QC Resto',
            'guest_count' => 40,
            'table_count' => 4,
        ]);

        $postRes->assertStatus(302);
        $postRes->assertSessionHas('analysis_error', 'Your event details were modified after photo analysis. Please re-analyze your inspiration photos before submitting.');

        // Booking was not created
        $this->assertEquals(0, Booking::where('event_type', 'birthday')->count());
    }

    public function test_client_booking_submission_succeeds_when_context_matches(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Client::create([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => '09171234567',
            'address' => 'Makati City',
        ]);

        $this->mock(GeminiVisionService::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('validateImage')->andReturn(['is_valid' => true]);
            $mock->shouldReceive('analyzeImageFromPath')->andReturn([
                'analysis' => [
                    'suggested_materials' => [
                        ['item_name' => 'Tulip', 'quantity' => 30, 'estimated_quantity' => 30, 'unit_cost_php' => 80],
                    ],
                    'pricing_summary' => ['estimated_grand_total_php' => 2400],
                    'image_valid' => true,
                    'is_usable' => true,
                ],
            ]);
        });

        $eventDate = Carbon::now()->addDays(45)->toDateString();

        $file = $this->createFakePng('client_matched.png');
        $analyzeRes = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
            'event_type' => 'corporate',
            'event_date' => $eventDate,
            'event_time' => '09:00',
            'end_time' => '17:00',
            'venue' => 'BGC Convention Center',
            'guest_count' => 300,
            'table_count' => 30,
        ]);

        $analyzeRes->assertStatus(200);
        $token = $analyzeRes->json('analysis_token');
        $payload = session("booking_analysis_payloads.{$token}");

        $postRes = $this->actingAs($user)->withSession([
            "booking_analysis_payloads.{$token}" => $payload,
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
            'event_type' => 'corporate',
            'event_date' => $eventDate,
            'event_time' => '09:00',
            'end_time' => '17:00',
            'venue' => 'BGC Convention Center',
            'guest_count' => 300,
            'table_count' => 30,
        ]);

        $postRes->assertStatus(302);
        $postRes->assertSessionMissing('analysis_error');

        $booking = Booking::where('event_type', 'corporate')->first();
        $this->assertNotNull($booking);
        $postRes->assertRedirect(route('bookings.show', ['booking' => $booking->id]));
    }
}

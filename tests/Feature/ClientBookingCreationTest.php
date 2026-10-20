<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Package;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Carbon\Carbon;
use App\Services\GeminiVisionService;
use Mockery;

class ClientBookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preset_client_booking_stores_package_id_and_event_size_without_concatenation()
    {
        $user = User::factory()->create();
        $client = Client::where('email', $user->email)->first();
        if (!$client) {
            $client = Client::create([
                'id' => $user->id,
                'email' => $user->email,
                'full_name' => $user->name,
                'phone' => '09171234567',
                'address' => 'Test Address',
            ]);
        }
        
        $package = Package::create([
            'title' => 'Test Package XYZ',
            'price' => 5000.00,
            'is_active' => true,
        ]);

        $eventDate = Carbon::now()->addDays(14)->toDateString();
        
        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Manila',
            'guest_count' => 150,
            'special_requests' => 'Please use red ribbons',
        ]);

        $booking = Booking::first();
        
        $this->assertNotNull($booking);
        $response->assertRedirect(route('bookings.show', ['booking' => $booking->id]));
        
        $this->assertEquals($package->id, $booking->package_id);
        $this->assertEquals(150, $booking->event_size);
        $this->assertEquals('wedding', $booking->event_type);
        $this->assertEquals('Please use red ribbons', $booking->special_requests);
        $this->assertStringNotContainsString('Selected Package:', $booking->special_requests);
        
        // Assert relation resolves
        $this->assertEquals($package->id, $booking->package->id);
    }

    public function test_custom_ai_client_booking_stores_event_size()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $client = Client::where('email', $user->email)->first();
        if (!$client) {
            $client = Client::create([
                'id' => $user->id,
                'email' => $user->email,
                'full_name' => $user->name,
                'phone' => '09171234567',
                'address' => 'Test Address',
            ]);
        }
        
        $image = UploadedFile::fake()->create('inspiration.jpg', 100);
        $eventDate = Carbon::now()->addDays(14)->toDateString();
        
        $analysisToken = \Illuminate\Support\Str::random(40);
        $response = $this->actingAs($user)->withSession([
            "booking_analysis_payloads.{$analysisToken}" => [
                'image_hash' => sha1_file($image->getRealPath()),
                'temp_path' => 'bookings/inspiration-images/inspiration.jpg',
                'analysis' => [
                    'theme' => 'Yellow theme',
                    'suggested_materials' => [
                        [
                            'item_name' => 'Yellow Tulip',
                            'category' => 'flower',
                            'unit_type' => 'stem',
                            'quantity' => 20,
                            'unit_cost_php' => 100,
                        ]
                    ]
                ],
                'raw_response' => 'mocked response',
            ]
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'birthday',
            'event_date' => $eventDate,
            'venue' => 'Quezon City',
            'guest_count' => 50,
            'special_requests' => 'Yellow theme',
            'analysis_token' => $analysisToken,
            'inspiration_image' => $image,
        ]);

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertEquals(50, $booking->event_size);
        $this->assertNull($booking->package_id);
        $this->assertEquals('Yellow theme', $booking->special_requests);
    }
}

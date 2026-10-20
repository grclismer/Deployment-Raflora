<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Booking;
use Illuminate\Support\Str;

class Phase3_9BInspirationImageSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        Storage::fake('local');
    }

    private function createUser($role = 'client')
    {
        return User::create([
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test' . Str::random(5) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function createClient($user)
    {
        return Client::create([
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '1234567890',
            'address' => 'Test Address',
        ]);
    }

    private function createBooking($overrides = [])
    {
        return Booking::create(array_merge([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Secret Garden',
            'theme' => 'Rustic',
            'budget' => 50000,
            'status' => 'pending',
        ], $overrides));
    }

    /**
     * @test
     */
    public function new_inspiration_upload_uses_private_storage()
    {
        $clientUser = $this->createUser('client');
        $this->createClient($clientUser);

        $image = UploadedFile::fake()->create('inspiration.jpg', 100, 'image/jpeg');
        $analysisToken = \Illuminate\Support\Str::random(40);

        $response = $this->actingAs($clientUser)->withSession([
            "booking_analysis_payloads.{$analysisToken}" => [
                'image_hash' => sha1_file($image->getRealPath()),
                'temp_path' => 'bookings/inspiration-images/inspiration.jpg',
                'analysis' => [
                    'theme' => 'Rustic',
                    'suggested_materials' => [
                        [
                            'item_name' => 'Rose',
                            'category' => 'flower',
                            'unit_type' => 'stem',
                            'quantity' => 20,
                            'unit_cost_php' => 100,
                        ]
                    ]
                ],
                'raw_response' => 'mocked',
            ]
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Secret Garden',
            'inspiration_image' => $image,
            'theme' => 'rustic', // option key from App\Support\EventDesignBrief::THEMES
            'budget' => 50000,
            'analysis_token' => $analysisToken,
        ]);
        $response->assertSessionHasNoErrors();
        $booking = Booking::first();
        
        $this->assertNotNull($booking->inspiration_image);
        Storage::disk('local')->assertExists($booking->inspiration_image);
        Storage::disk('public')->assertMissing($booking->inspiration_image);
    }

    /**
     * @test
     */
    public function authorized_users_can_retrieve_the_image()
    {
        $clientUser = $this->createUser('client');
        $client = $this->createClient($clientUser);

        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $booking = $this->createBooking([
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'inspiration_image' => 'bookings/inspiration-images/test_image.jpg',
        ]);

        Storage::disk('local')->put('bookings/inspiration-images/test_image.jpg', 'dummy image content');

        // Admin
        $this->actingAs($admin)->get(route('secure.inspiration.show', $booking->id))
            ->assertOk();

        // Owning Client
        $this->actingAs($clientUser)->get(route('secure.inspiration.show', $booking->id))
            ->assertOk();

        // Assigned Staff
        $this->actingAs($staff)->get(route('secure.inspiration.show', $booking->id))
            ->assertOk();
    }

    /**
     * @test
     */
    public function unauthorized_users_cannot_retrieve_the_image()
    {
        $clientUser1 = $this->createUser('client');
        $client1 = $this->createClient($clientUser1);

        $clientUser2 = $this->createUser('client');
        $client2 = $this->createClient($clientUser2);

        $booking = $this->createBooking([
            'client_id' => $client1->id,
            'inspiration_image' => 'bookings/inspiration-images/test_image.jpg',
        ]);

        Storage::disk('local')->put('bookings/inspiration-images/test_image.jpg', 'dummy image content');

        // Other client
        $this->actingAs($clientUser2)->get(route('secure.inspiration.show', $booking->id))
            ->assertForbidden();
            
        // Unauthenticated without token
        $this->get(route('secure.inspiration.show', $booking->id))
            ->assertForbidden();
    }

    /**
     * @test
     */
    public function unassigned_staff_cannot_retrieve_the_image()
    {
        $clientUser = $this->createUser('client');
        $client = $this->createClient($clientUser);

        $assignedStaff = $this->createUser('staff');
        $unassignedStaff = $this->createUser('staff');

        $booking = $this->createBooking([
            'client_id' => $client->id,
            'staff_id' => $assignedStaff->id,
            'inspiration_image' => 'bookings/inspiration-images/test_image.jpg',
        ]);

        Storage::disk('local')->put('bookings/inspiration-images/test_image.jpg', 'dummy content');

        $this->actingAs($unassignedStaff)->get(route('secure.inspiration.show', $booking->id))
            ->assertForbidden();
    }

    /**
     * @test
     */
    public function guest_booking_can_retrieve_image_with_token()
    {
        $booking = $this->createBooking([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
            'guest_access_token' => 'guest-secret-token',
            'inspiration_image' => 'bookings/inspiration-images/test_image.jpg',
        ]);

        Storage::disk('local')->put('bookings/inspiration-images/test_image.jpg', 'dummy content');

        $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => 'wrong-token']))
            ->assertForbidden();

        $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => 'guest-secret-token']))
            ->assertOk();
    }
    
    /**
     * @test
     */
    public function missing_files_are_handled_without_application_failure()
    {
        $admin = $this->createUser('admin');
        
        $booking = $this->createBooking([
            'inspiration_image' => 'bookings/inspiration-images/missing.jpg',
        ]);
        
        // File does not exist on disk
        $this->actingAs($admin)->get(route('secure.inspiration.show', $booking->id))
            ->assertNotFound();
    }
}

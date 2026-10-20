<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPaymentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_booking_view_shows_payment_form_for_approved_status(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'full_name' => 'Test Client',
            'email' => $user->email,
            'phone' => '09170000000',
            'address' => 'Test address',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDay(),
            'venue' => 'Test Venue',
            'status' => 'admin_approved',
            'final_quoted_price' => 5000,
            'total_quoted' => 5000,
        ]);

        $response = $this->actingAs($user)->get(route('bookings.show', ['booking' => $booking->id]));

        $response->assertOk();
        $response->assertSee('Payment Method');
        $response->assertSee('Reference Number');
    }

    public function test_guest_booking_view_shows_payment_form_for_admin_approved_status(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09170000001',
            'guest_address' => 'Guest address',
            'guest_access_token' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'birthday',
            'event_date' => now()->addDay(),
            'venue' => 'Guest Venue',
            'status' => 'admin_approved',
            'final_quoted_price' => 3500,
            'total_quoted' => 3500,
        ]);

        $response = $this->get(route('guest.booking.show', ['token' => $booking->guest_access_token]));

        $response->assertOk();
        $response->assertSee('Quotation Approved by Admin');
        $response->assertSee('Log In to Submit Downpayment');
        $response->assertDontSee('Payment Method');
        $response->assertDontSee('Reference Number');
        $response->assertSee('Create a free account');
    }

    public function test_client_booking_status_endpoint_returns_only_owned_booking_status(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'full_name' => 'Status Client',
            'email' => $user->email,
            'phone' => '09170000005',
            'address' => 'Status address',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDay(),
            'venue' => 'Status Venue',
            'status' => 'event_in_progress',
        ]);

        $this->actingAs($user)
            ->getJson(route('bookings.status', ['booking' => $booking->id]))
            ->assertOk()
            ->assertJsonPath('status', 'event_in_progress');

        $otherBooking = Booking::create([
            'client_id' => Client::create([
                'full_name' => 'Other Client',
                'email' => 'other-status@example.com',
                'phone' => '09170000006',
            ])->id,
            'event_type' => 'birthday',
            'event_date' => now()->addDay(),
            'venue' => 'Other Venue',
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->getJson(route('bookings.status', ['booking' => $otherBooking->id]))
            ->assertForbidden();
    }

    public function test_guest_booking_status_endpoint_returns_latest_booking_timestamp(): void
    {
        $tempBooking = new \App\Models\TemporaryGuestBooking([
            'guest_name' => 'Guest User',
            'guest_email' => 'guest-status@example.com',
            'guest_phone' => '09170000002',
            'guest_address' => 'Test Address',
            'event_type' => 'birthday',
            'event_date' => now()->addDay(),
            'venue' => 'Guest Venue',
            'expires_at' => now()->addHours(24),
        ]);
        $token = $tempBooking->generateToken();
        $tempBooking->save();

        $response = $this->getJson(route('guest.bookings.status', ['token' => $token]));

        $response->assertOk()
            ->assertJson([
                'status' => 'pending',
                'updated_at' => $tempBooking->updated_at->toISOString(),
            ]);
    }

    public function test_guest_booking_status_endpoint_rejects_invalid_token(): void
    {
        $this->getJson(route('guest.bookings.status', ['token' => 'invalid-token']))
            ->assertNotFound();
    }

    public function test_guest_booking_view_uses_current_status_instead_of_old_payment_message(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest-current-status@example.com',
            'guest_phone' => '09170000003',
            'guest_access_token' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'birthday',
            'event_date' => now()->addDay(),
            'venue' => 'Guest Venue',
            'status' => 'downpayment_received',
            'final_quoted_price' => 3500,
            'total_quoted' => 3500,
        ]);

        $response = $this->get(route('guest.booking.show', ['token' => $booking->guest_access_token]));

        $response->assertOk();
        $response->assertDontSee('DOWNPAYMENT VERIFIED');
        $response->assertDontSee('PAYMENT REFERENCE SUBMITTED');
        $response->assertSee('Create a free account');
    }

    public function test_completed_guest_booking_shows_pending_balance_instead_of_full_payment(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest-balance@example.com',
            'guest_phone' => '09170000004',
            'guest_access_token' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'birthday',
            'event_date' => now()->addDay(),
            'venue' => 'Guest Venue',
            'status' => 'event_completed',
            'final_quoted_price' => 4950,
            'total_quoted' => 4950,
        ]);

        $booking->payments()->create([
            'amount' => 4950,
            'amount_paid' => 2475,
            'status' => 'verified',
            'payment_type' => 'gcash',
            'reference_number' => 'TEST-REF-4950',
        ]);

        $response = $this->get(route('guest.booking.show', ['token' => $booking->guest_access_token]));

        $response->assertOk();
        $response->assertDontSee('EVENT COMPLETED (BALANCE PENDING)');
        $response->assertDontSee('EVENT COMPLETED &amp; FULLY PAID.', false);
        $response->assertSee('Create a free account');
    }
}

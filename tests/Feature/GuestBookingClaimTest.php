<?php

namespace Tests\Feature;

use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Carbon\Carbon;

class GuestBookingClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_claim_booking()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->get(route('client.claim-guest-booking.show', ['token' => $token]));
        
        $response->assertRedirect('/login');
    }

    public function test_client_cannot_claim_booking_with_mismatched_email()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $user = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $response = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        
        $response->assertStatus(403);
    }

    public function test_client_can_claim_booking_with_matching_email()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'guest_count' => 150,
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        
        $response->assertRedirect(route('bookings'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'john@example.com',
            'status' => 'pending',
            'event_size' => 150,
            'client_id' => \App\Models\Client::where('email', 'john@example.com')->first()->id,
        ]);

        $this->assertDatabaseHas('temporary_guest_bookings', [
            'id' => $temp->id,
            'client_id' => \App\Models\Client::where('email', 'john@example.com')->first()->id,
        ]);
        
        $this->assertNotNull($temp->fresh()->claimed_at);
    }

    public function test_client_cannot_access_get_claim_with_mismatched_email()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $user = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));
        
        $response->assertStatus(403);
    }

    public function test_client_cannot_access_get_claim_if_unverified()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));
        
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_successful_claim_preserves_package_id()
    {
        $package = \App\Models\Package::create([
            'title' => 'Test Package',
            'description' => 'Test Package Description',
            'price' => 10000.00,
            'is_active' => true,
        ]);

        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        
        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'john@example.com',
            'package_id' => $package->id,
        ]);
    }

    public function test_registration_with_guest_token_redirects_to_claim_after_verification()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->post(route('register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'mobile_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'guest_token' => $token,
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $this->assertEquals(route('client.claim-guest-booking.show', ['token' => $token]), session('url.intended'));

        $user = auth()->user();

        $record = \App\Models\EmailVerification::where('user_id', $user->id)
            ->where('purpose', 'email_verification')
            ->latest('id')
            ->first();
            
        $knownOtp = '123456';
        $record->update(['otp_hash' => hash('sha256', $knownOtp)]);

        $verifyResponse = $this->actingAs($user)->post(route('verification.verify'), [
            'otp' => $knownOtp,
        ]);

        $verifyResponse->assertRedirect(route('client.claim-guest-booking.show', ['token' => $token]));
    }
}

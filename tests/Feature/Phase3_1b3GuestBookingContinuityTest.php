<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase3_1b3GuestBookingContinuityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Journey A: Guest with a quotation registers, verifies email OTP, and reaches claim flow.
     */
    public function test_journey_a_guest_with_quotation_registers_and_reaches_claim_destination(): void
    {
        $token = Str::random(40);
        $tempBooking = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'Alice Journey',
            'guest_email' => 'alice@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Floral Ave',
            'booking_type' => 'preset',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Grand Floral Chapel',
            'guest_count' => 100,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        // 1. Guest views temporary booking analysis
        $viewResponse = $this->get(route('guest.bookings.show', ['token' => $token]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Claim &amp; Manage Your Booking Request', false);
        // The visible "Log In & Claim" button carries the "Already have an account?" context for screen readers.
        $viewResponse->assertSeeText('Already have an account? Log In & Claim');
        $viewResponse->assertSee(route('register', ['guest_token' => $token, 'email' => 'alice@example.com']));

        // 2. Guest opens registration page with guest_token
        $regPageResponse = $this->get(route('register', ['guest_token' => $token, 'email' => 'alice@example.com']));
        $regPageResponse->assertOk();
        $regPageResponse->assertSee($token);

        // 3. Guest registers with matching email and guest_token
        $registerResponse = $this->post(route('register.attempt'), [
            'first_name' => 'Alice',
            'last_name' => 'Journey',
            'email' => 'alice@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'mobile_number' => '09171234567',
            'terms' => '1',
            'guest_token' => $token,
        ]);

        $registerResponse->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->hasVerifiedEmail());

        // 4. Retrieve OTP and submit verification
        $otp = app(OtpService::class)->generate($user, 'email_verification');
        $verifyResponse = $this->post(route('verification.verify'), [
            'otp' => $otp,
        ]);

        // Intended URL must redirect to claim booking show page
        $verifyResponse->assertRedirect(route('client.claim-guest-booking.show', ['token' => $token]));
        $user = $user->fresh();
        $this->assertTrue($user->hasVerifiedEmail());

        // 5. Complete claim
        $claimResponse = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        $claimResponse->assertRedirect(route('bookings'));
        $claimResponse->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'alice@example.com',
            'client_id' => Client::where('email', 'alice@example.com')->first()->id,
        ]);
        $this->assertNotNull($tempBooking->fresh()->claimed_at);
    }

    /**
     * Journey B: Existing account holder logs in with guest_token and redirects to claim.
     */
    public function test_journey_b_existing_account_holder_logs_in_and_redirects_to_claim(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'returning@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $token = Str::random(40);
        $tempBooking = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'Returning User',
            'guest_email' => 'returning@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '456 Rose St',
            'booking_type' => 'preset',
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Sunset Terrace',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        // 1. Guest clicks log in with guest token
        $loginPageResponse = $this->get(route('login', ['guest_token' => $token, 'email' => 'returning@example.com']));
        $loginPageResponse->assertOk();
        $loginPageResponse->assertSee($token);
        $loginPageResponse->assertSee('returning@example.com');

        // 2. Submits login credentials
        $loginResponse = $this->post(route('login.attempt'), [
            'email' => 'returning@example.com',
            'password' => 'Secret123!',
            'guest_token' => $token,
        ]);

        // Redirected directly to the claim page
        $loginResponse->assertRedirect(route('client.claim-guest-booking.show', ['token' => $token]));
        $this->assertAuthenticatedAs($existingUser);

        // 3. User claims the booking
        $claimResponse = $this->actingAs($existingUser)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        $claimResponse->assertRedirect(route('bookings'));
        $claimResponse->assertSessionHas('success');

        $this->assertNotNull($tempBooking->fresh()->claimed_at);
    }

    /**
     * Journey C: Invalid or expired token handling.
     */
    public function test_journey_c_invalid_and_expired_tokens_are_rejected(): void
    {
        // 1. Invalid token returns 404
        $invalidResponse = $this->get(route('guest.bookings.show', ['token' => 'invalid-token-12345']));
        $invalidResponse->assertStatus(404);

        $clientUser = User::factory()->create([
            'email' => 'client@example.com',
            'email_verified_at' => now(),
        ]);

        $invalidClaimResponse = $this->actingAs($clientUser)->get(route('client.claim-guest-booking.show', ['token' => 'invalid-token-12345']));
        $invalidClaimResponse->assertStatus(404);

        // 2. Expired token returns 403
        $expiredToken = Str::random(40);
        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $expiredToken),
            'guest_name' => 'Expired Guest',
            'guest_email' => 'client@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '789 Garden Way',
            'booking_type' => 'preset',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Heritage Villa',
            'expires_at' => Carbon::now()->subHour(), // Expired
        ]);

        $expiredShowResponse = $this->get(route('guest.bookings.show', ['token' => $expiredToken]));
        $expiredShowResponse->assertStatus(403);

        $expiredClaimResponse = $this->actingAs($clientUser)->get(route('client.claim-guest-booking.show', ['token' => $expiredToken]));
        $expiredClaimResponse->assertStatus(403);
    }

    /**
     * Journey D: Email mismatch prevents claiming by unauthorized account.
     */
    public function test_journey_d_email_mismatch_prevents_claiming(): void
    {
        $token = Str::random(40);
        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'Original Guest',
            'guest_email' => 'original@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '100 Bloom Ave',
            'booking_type' => 'preset',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Pavilion',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $wrongUser = User::factory()->create([
            'email' => 'intruder@example.com',
            'email_verified_at' => now(),
        ]);

        $showResponse = $this->actingAs($wrongUser)->get(route('client.claim-guest-booking.show', ['token' => $token]));
        $showResponse->assertStatus(403);

        $claimResponse = $this->actingAs($wrongUser)->post(route('client.claim-guest-booking.claim', ['token' => $token]));
        $claimResponse->assertStatus(403);
    }

    /**
     * Journey E: Payment boundary — unclaimed guest cannot access or submit payment.
     */
    public function test_journey_e_payment_boundary_enforced_for_unclaimed_guest(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Approved Guest',
            'guest_email' => 'approved.boundary@example.com',
            'guest_phone' => '09171234567',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Emerald Hall',
            'status' => 'admin_approved',
            'final_quoted_price' => 45000.00,
        ]);

        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 45000.00,
            'downpayment_percentage' => 50.0,
            'valid_until' => Carbon::now()->addDays(7),
            'issued_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertOk();

        // 1. Must see approved status and claim explanation
        $response->assertSee('Quotation Approved by Admin');
        $response->assertSee('Log In to Submit Downpayment');
        $response->assertSee('Payment Boundary &amp; Account Claim Required', false);

        // 2. Must not see payment input fields
        $response->assertDontSee('Payment Method');
        $response->assertDontSee('Reference Number');
        $response->assertDontSee('Submit Payment Reference');

        // 3. Direct POST to payment reference endpoint is rejected
        $postResponse = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-UNCLAIMED-12345',
        ]);

        $postResponse->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $postResponse->assertSessionHas('error');
    }

    /**
     * Journey F: Status clarity across lifecycle states.
     */
    public function test_journey_f_guest_view_presents_clear_status_for_all_states(): void
    {
        // 1. Initial pending state
        $tokenPending = Str::random(40);
        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $tokenPending),
            'guest_name' => 'Pending Guest',
            'guest_email' => 'pending@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Pending St',
            'booking_type' => 'preset',
            'event_type' => 'corporate',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Convention Hall',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $respPending = $this->get(route('guest.bookings.show', ['token' => $tokenPending]));
        $respPending->assertOk();
        $respPending->assertSee('Booking Request Received');
        $respPending->assertSee('Claim &amp; Manage Your Booking Request', false);

        // 2. Cancelled status
        $guestTokenCancelled = (string) Str::uuid();
        Booking::create([
            'guest_name' => 'Cancelled Guest',
            'guest_email' => 'cancelled@example.com',
            'guest_phone' => '09171234567',
            'guest_access_token' => $guestTokenCancelled,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Hotel',
            'status' => 'cancelled',
            'final_quoted_price' => 50000.00,
        ]);

        $respCancelled = $this->get(route('guest.booking.analysis', ['token' => $guestTokenCancelled]));
        $respCancelled->assertOk();
        $respCancelled->assertSee('Booking Cancelled');
        $respCancelled->assertSee('This booking request is no longer active.');

        // 3. Declined status
        $guestTokenDeclined = (string) Str::uuid();
        Booking::create([
            'guest_name' => 'Declined Guest',
            'guest_email' => 'declined@example.com',
            'guest_phone' => '09171234567',
            'guest_access_token' => $guestTokenDeclined,
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Sunset Deck',
            'status' => 'declined',
            'final_quoted_price' => 20000.00,
        ]);

        $respDeclined = $this->get(route('guest.booking.analysis', ['token' => $guestTokenDeclined]));
        $respDeclined->assertOk();
        $respDeclined->assertSee('Booking Declined');
        $respDeclined->assertSee('This booking request is no longer active.');
    }

    /**
     * Cross-flow token continuity between login and registration views.
     */
    public function test_guest_token_continuity_between_login_and_register_links(): void
    {
        $token = 'test-guest-token-abc123';

        // Login page includes register link with token and email
        $loginResponse = $this->get(route('login', ['guest_token' => $token, 'email' => 'guest@example.com']));
        $loginResponse->assertOk();
        $loginResponse->assertSee(route('register', ['guest_token' => $token, 'email' => 'guest@example.com']));

        // Register page includes login link with token and email
        $registerResponse = $this->get(route('register', ['guest_token' => $token, 'email' => 'guest@example.com']));
        $registerResponse->assertOk();
        $registerResponse->assertSee(route('login', ['guest_token' => $token, 'email' => 'guest@example.com']));
    }
}

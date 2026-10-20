<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseOneSecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // SEC-01: Guest Analysis IDOR
    // ==========================================

    public function test_raw_booking_id_cannot_access_guest_analysis(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
            'guest_access_token' => (string) Str::uuid(),
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5),
            'venue' => 'Grand Plaza',
            'status' => 'pending',
            'total_quoted' => 10000,
        ]);

        // Attempting to access using the raw numeric ID must fail with 404
        // because the route now expects the secure UUID token
        $response = $this->get('/guest/booking/' . $booking->id);
        $response->assertNotFound();

        $responseAnalysis = $this->get('/guest/booking/analysis/' . $booking->id);
        $responseAnalysis->assertNotFound();
    }

    public function test_invalid_token_returns_404_for_guest_analysis(): void
    {
        $response = $this->get('/guest/booking/invalid-nonexistent-token-12345');
        $response->assertNotFound();

        $responseAnalysis = $this->get('/guest/booking/analysis/invalid-nonexistent-token-12345');
        $responseAnalysis->assertNotFound();
    }

    public function test_valid_guest_token_accesses_guest_analysis_page(): void
    {
        $token = (string) Str::uuid();
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
            'guest_access_token' => $token,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5),
            'venue' => 'Grand Plaza',
            'status' => 'pending',
            'total_quoted' => 10000,
        ]);

        $response = $this->get(route('guest.booking.show', ['token' => $token]));
        $response->assertOk();
        $response->assertViewIs('guest.booking-analysis');
        $response->assertViewHas('token', $token);
    }

    public function test_registered_client_booking_cannot_be_accessed_via_guest_analysis(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $client = Client::create([
            'full_name' => 'Registered Client',
            'email' => $user->email,
            'phone' => '09170000000',
            'address' => 'Client Address',
        ]);

        $token = (string) Str::uuid();
        $booking = Booking::create([
            'client_id' => $client->id,
            'guest_access_token' => $token,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(3),
            'venue' => 'Private Club',
            'status' => 'pending',
            'total_quoted' => 8000,
        ]);

        // Attempting to access a client booking via guest analysis route must return 403
        $response = $this->get(route('guest.booking.show', ['token' => $token]));
        $response->assertForbidden();
    }

    // ==========================================
    // SEC-02: Guest Payment Token Bypass
    // ==========================================

    public function test_guest_payment_submission_aborts_403_when_token_is_missing(): void
    {
        $booking = $this->createApprovedGuestBooking();

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'REF-MISSING-TOKEN',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-MISSING-TOKEN',
        ]);
    }

    public function test_guest_payment_submission_aborts_403_when_token_is_empty(): void
    {
        $booking = $this->createApprovedGuestBooking();

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_access_token' => '',
            'guest_token' => '',
            'reference_number' => 'REF-EMPTY-TOKEN',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-EMPTY-TOKEN',
        ]);
    }

    public function test_guest_payment_submission_aborts_403_when_token_is_incorrect(): void
    {
        $booking = $this->createApprovedGuestBooking();

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_access_token' => 'incorrect-fake-token',
            'reference_number' => 'REF-INCORRECT-TOKEN',
            'payment_type' => 'bank_transfer',
            'payment_option' => 'full_payment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-INCORRECT-TOKEN',
        ]);
    }

    public function test_guest_payment_submission_is_blocked_for_unclaimed_guest(): void
    {
        $booking = $this->createApprovedGuestBooking();

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_access_token' => $booking->guest_access_token,
            'reference_number' => 'REF-VALID-TOKEN-123',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $booking->guest_access_token]));
        $response->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');
        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-VALID-TOKEN-123',
        ]);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    // ==========================================
    // SEC-03: Missing Rate Limiting on AI Endpoints
    // ==========================================

    public function test_guest_analyze_temp_image_is_throttled_at_five_requests_per_minute(): void
    {
        // Five, like the client route: a guest may analyze up to InspirationPhotos::MAX_PHOTOS photos in a row.
        Storage::fake('public');
        $file = $this->validImageUpload();

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('guest.bookings.analyze-temp-image'), [
                'inspiration_image' => $file,
            ]);
            $this->assertNotSame(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 6th request must be throttled
        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
        ]);
        $response->assertStatus(429);
    }

    public function test_client_analyze_temp_image_is_throttled_at_five_requests_per_minute(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $file = $this->validImageUpload();

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($user)->post(route('bookings.analyze-temp-image'), [
                'inspiration_image' => $file,
            ]);
            $this->assertNotSame(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 6th request must be throttled
        $response = $this->actingAs($user)->post(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
        ]);
        $response->assertStatus(429);
    }

    public function test_diagnostic_test_gemini_endpoint_returns_404_in_testing_environment(): void
    {
        // When not in local environment, /test-gemini must return 404
        $response = $this->get('/test-gemini');
        $response->assertNotFound();
    }

    // ==========================================
    // SEC-04: Payment Reference Race Condition
    // ==========================================

    public function test_unclaimed_guest_payment_submission_cannot_create_pending_payment(): void
    {
        $booking = $this->createApprovedGuestBooking();

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_access_token' => $booking->guest_access_token,
            'reference_number' => 'REF-UNCLAIMED-001',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $booking->guest_access_token]));
        $response->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');

        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-UNCLAIMED-001',
        ]);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    public function test_client_payment_submission_rejects_second_pending_payment(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $client = Client::create([
            'full_name' => 'Client Pay',
            'email' => $user->email,
            'phone' => '09170000000',
            'address' => 'Manila',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10),
            'venue' => 'Manila Hotel',
            'status' => 'admin_approved',
            'final_quoted_price' => 20000,
            'total_quoted' => 20000,
        ]);

        // First submission creates the pending payment
        $response1 = $this->actingAs($user)->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'CLIENT-PAY-001',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $response1->assertSessionHas('success');

        // Reset status in DB to admin_approved to simulate concurrent request race
        Booking::where('id', $booking->id)->update(['status' => 'admin_approved']);

        // Second submission while pending must be rejected by the locked transaction check
        $response2 = $this->actingAs($user)->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'CLIENT-PAY-002',
            'payment_type' => 'bank_transfer',
            'payment_option' => 'full_payment',
        ]);
        $response2->assertSessionHas('error', 'A payment reference is already awaiting Admin verification.');

        $this->assertSame(1, Payment::where('booking_id', $booking->id)->where('status', 'pending')->count());
    }

    public function test_rejected_payment_can_be_resubmitted_for_new_pending_payment(): void
    {
        [$user, $booking] = $this->createApprovedClientBooking();

        // Simulate an earlier payment that was rejected by admin
        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 2500,
            'payment_option' => 'downpayment',
            'amount_paid' => 0.00,
            'remaining_balance' => 2500,
            'payment_type' => 'gcash',
            'reference_number' => 'REF-OLD-REJECTED',
            'status' => 'rejected',
        ]);

        // Client should be able to submit a new reference
        $response = $this->actingAs($user)->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'REF-NEW-RESUBMITTED',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->where('status', 'pending')->count());
        $this->assertSame(2, Payment::where('booking_id', $booking->id)->count());
    }

    public function test_payment_creation_and_booking_status_update_occur_atomically(): void
    {
        [$user, $booking] = $this->createApprovedClientBooking();

        $response = $this->actingAs($user)->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'REF-ATOMIC-001',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-ATOMIC-001',
            'status' => 'pending',
        ]);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    private function createApprovedClientBooking(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $client = \App\Models\Client::create([
            'full_name' => 'Client Tester',
            'email' => $user->email,
            'phone' => '09170000000',
            'address' => 'Manila',
        ]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(7),
            'venue' => 'Manila Bay',
            'status' => 'admin_approved',
            'final_quoted_price' => 5000,
            'total_quoted' => 5000,
        ]);
        return [$user, $booking];
    }

    private function createApprovedGuestBooking(): Booking
    {
        return Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest Tester',
            'guest_email' => 'guest.tester@example.com',
            'guest_phone' => '09170000000',
            'guest_access_token' => (string) Str::uuid(),
            'event_type' => 'wedding',
            'event_date' => now()->addDays(7),
            'venue' => 'Manila Bay',
            'status' => 'admin_approved',
            'final_quoted_price' => 5000,
            'total_quoted' => 5000,
        ]);
    }

    private function validImageUpload(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('inspiration.png', $png);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2b6GuestBoundaryEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 1. Guest inquiry submission remains functional.
     */
    public function test_01_guest_inquiry_submission_creates_temporary_booking_with_token(): void
    {
        $package = Package::create([
            'title' => 'Inquiry Test Package',
            'category' => 'wedding',
            'description' => 'Test package for guest inquiry',
            'price' => 25000.00,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $response = $this->post(route('guest.booking.store'), [
            'guest_name' => 'Maria Inquiry',
            'guest_email' => 'maria.inquiry@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Sampaguita St, Manila',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '14:00',
            'end_time' => '18:00',
            'venue_city' => 'Manila',
            'venue_specific' => 'Manila Cathedral',
            'venue' => 'Manila Cathedral, Manila',
            'guest_count' => 150,
        ]);

        $tempBooking = TemporaryGuestBooking::where('guest_email', 'maria.inquiry@example.com')->first();
        $this->assertNotNull($tempBooking);
        $this->assertNotNull($tempBooking->claim_token_hash);
        $response->assertRedirect();
    }

    /**
     * 2. Guest initial assessment remains accessible.
     */
    public function test_02_guest_initial_assessment_remains_accessible(): void
    {
        $guestToken = (string) Str::uuid();
        Booking::create([
            'guest_name' => 'Assessment Guest',
            'guest_email' => 'assessment@example.com',
            'guest_phone' => '09171234568',
            'guest_access_token' => $guestToken,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Sky Lounge',
            'status' => 'pending',
            'total_quoted' => 15000.00,
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertOk();
        $response->assertSee('Birthday Proposal');
    }

    /**
     * 3. Claim instructions are displayed.
     */
    public function test_03_claim_instructions_are_displayed_in_analysis(): void
    {
        $guestToken = (string) Str::uuid();
        Booking::create([
            'guest_name' => 'Claimable Guest',
            'guest_email' => 'claimable@example.com',
            'guest_phone' => '09171234569',
            'guest_access_token' => $guestToken,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Sunset Pavillion',
            'status' => 'quotation_sent',
            'final_quoted_price' => 30000.00,
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertOk();
        $response->assertSee('Claim &amp; Manage Your Booking Request', false);
        $response->assertSee('Create a free account &amp; Claim', false);
        $response->assertSee('Log In / Register to Review &amp; Accept Quotation', false);
    }

    /**
     * 4. Unclaimed guest quotation acceptance is blocked.
     */
    public function test_04_unclaimed_guest_quotation_acceptance_is_blocked(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Unclaimed Quotation Guest',
            'guest_email' => 'unclaimed.quote@example.com',
            'guest_phone' => '09171234570',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Garden Oasis',
            'status' => 'quotation_sent',
            'final_quoted_price' => 35000.00,
            'price_valid_until' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 35000.00,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        $response = $this->post(route('guest.bookings.accept', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertSessionHas('error', 'Official quotations cannot be accepted by an unclaimed guest. Please log in or create an account to claim your booking first.');

        $booking->refresh();
        $quotation->refresh();
        $this->assertSame('quotation_sent', $booking->status);
        $this->assertSame(Quotation::STATUS_ISSUED, $quotation->status);
    }

    /**
     * 5. Unclaimed guest payment submission is blocked.
     */
    public function test_05_unclaimed_guest_payment_submission_is_blocked(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Unclaimed Pay Guest',
            'guest_email' => 'unclaimed.pay@example.com',
            'guest_phone' => '09171234571',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'admin_approved',
            'final_quoted_price' => 40000.00,
        ]);

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-UNCLAIMED-999',
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');

        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-UNCLAIMED-999']);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    /**
     * 6. Blocked requests do not create payments or mutate booking/quotation statuses.
     */
    public function test_06_blocked_requests_do_not_create_payments_or_mutate_status(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Zero Mutation Guest',
            'guest_email' => 'zero.mutation@example.com',
            'guest_phone' => '09171234572',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Rooftop Hall',
            'status' => 'admin_approved',
            'final_quoted_price' => 20000.00,
        ]);

        // Attempt quotation acceptance
        $this->post(route('guest.bookings.accept', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
        ]);

        // Attempt payment submission
        $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'payment_type' => 'bank_transfer',
            'payment_option' => 'full_payment',
            'reference_number' => 'REF-ZERO-MUTATION',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    /**
     * 7. Canonical guest claim and conversion still work.
     */
    public function test_07_canonical_guest_claim_and_conversion_succeeds(): void
    {
        $tempBooking = new TemporaryGuestBooking();
        $tempBooking->fill([
            'guest_name' => 'Claimable Maria',
            'guest_email' => 'maria.claim@example.com',
            'guest_phone' => '09171234573',
            'guest_address' => 'Quezon City',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'venue' => 'Fernwood Gardens',
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        $rawToken = $tempBooking->generateToken();
        $tempBooking->save();

        $clientUser = User::factory()->create([
            'email' => 'maria.claim@example.com',
            'email_verified_at' => now(),
            'role' => 'client',
        ]);

        $response = $this->actingAs($clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));
        $response->assertRedirect(route('bookings'));

        $convertedBooking = Booking::where('guest_email', 'maria.claim@example.com')->first();
        $this->assertNotNull($convertedBooking);
        $this->assertNotNull($convertedBooking->client_id);
        $this->assertSame($clientUser->email, $convertedBooking->client->email);
    }

    /**
     * 8. Registered client can accept an official quotation.
     */
    public function test_08_registered_client_can_accept_official_quotation(): void
    {
        $clientUser = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $client = Client::create([
            'id' => $clientUser->id,
            'full_name' => 'Accepting Client',
            'email' => $clientUser->email,
            'phone' => '09171234574',
            'address' => 'Makati City',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Peninsula',
            'status' => 'quotation_sent',
            'final_quoted_price' => 50000.00,
            'total_quoted' => 50000.00,
            'price_valid_until' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 50000.00,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($clientUser)->post(route('bookings.accept', ['booking' => $booking->id]));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $quotation->refresh();
        $this->assertSame('approved', $booking->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $quotation->status);
    }

    /**
     * 9. Registered client can submit payment after admin approval.
     */
    public function test_09_registered_client_can_submit_payment_after_admin_approval(): void
    {
        $clientUser = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $client = Client::create([
            'id' => $clientUser->id,
            'full_name' => 'Paying Client',
            'email' => $clientUser->email,
            'phone' => '09171234575',
            'address' => 'BGC Taguig',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Shangri-La at the Fort',
            'status' => 'admin_approved',
            'final_quoted_price' => 60000.00,
            'total_quoted' => 60000.00,
        ]);

        $response = $this->actingAs($clientUser)->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'CLIENT-GCASH-12345',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('payment_submitted', $booking->status);

        $payment = Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('CLIENT-GCASH-12345', $payment->reference_number);
    }

    /**
     * 10. Cross-client access remains denied.
     */
    public function test_10_cross_client_access_remains_denied(): void
    {
        $clientUserA = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $clientA = Client::create([
            'id' => $clientUserA->id,
            'full_name' => 'Client A',
            'email' => $clientUserA->email,
            'phone' => '09171234576',
            'address' => 'Pasig City',
        ]);

        $clientUserB = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        Client::create([
            'id' => $clientUserB->id,
            'full_name' => 'Client B',
            'email' => $clientUserB->email,
            'phone' => '09171234577',
            'address' => 'Mandaluyong City',
        ]);

        $bookingA = Booking::create([
            'client_id' => $clientA->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Oasis Manila',
            'status' => 'admin_approved',
            'final_quoted_price' => 25000.00,
            'total_quoted' => 25000.00,
        ]);

        // Client B tries to submit payment for Client A's booking
        $response = $this->actingAs($clientUserB)->post(route('bookings.payment.reference', ['booking' => $bookingA->id]), [
            'reference_number' => 'UNAUTHORIZED-PAY',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('payments', ['reference_number' => 'UNAUTHORIZED-PAY']);
    }

    /**
     * 11. Claimed guest tokens cannot expose registered booking information.
     */
    public function test_11_claimed_guest_tokens_cannot_expose_registered_booking_information(): void
    {
        $clientUser = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $client = Client::create([
            'id' => $clientUser->id,
            'full_name' => 'Claimed Owner',
            'email' => $clientUser->email,
            'phone' => '09171234578',
            'address' => 'Alabang',
        ]);

        $token = (string) Str::uuid();
        $booking = Booking::create([
            'client_id' => $client->id,
            'guest_name' => 'Converted Maria',
            'guest_email' => $clientUser->email,
            'guest_access_token' => $token,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Crimson Hotel',
            'status' => 'admin_approved',
            'final_quoted_price' => 50000.00,
            'total_quoted' => 50000.00,
        ]);

        // Attempting to access guest analysis for a registered-client booking must return 403 Forbidden
        $response = $this->get(route('guest.booking.analysis', ['token' => $token]));
        $response->assertForbidden();

        // Attempting to submit guest payment for a registered-client booking must return 403 Forbidden
        $payResponse = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $token,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'CLAIMED-TOKEN-PAY',
        ]);
        $payResponse->assertForbidden();
    }

    /**
     * 12. Existing booking-specific communication remains functional.
     */
    public function test_12_existing_booking_communication_remains_functional(): void
    {
        $clientUser = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $client = Client::create([
            'id' => $clientUser->id,
            'full_name' => 'Messaging Client',
            'email' => $clientUser->email,
            'phone' => '09171234579',
            'address' => 'San Juan City',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Club Filipino',
            'status' => 'quotation_sent',
            'final_quoted_price' => 45000.00,
            'total_quoted' => 45000.00,
        ]);

        $presentation = Presentation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'file_name' => 'proposal.pdf',
            'file_path' => 'proposals/proposal.pdf',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($clientUser)->post(route('bookings.proposals.feedback', [
            'booking' => $booking->id,
            'presentation' => $presentation->id,
        ]), [
            'approval_status' => 'needs_revision',
            'feedback_text' => 'Please add more white roses on the centerpieces.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $booking->id,
            'sender_type' => 'client',
            'sender_id' => $client->id,
            'message' => 'Proposal v1 feedback: Please add more white roses on the centerpieces.',
        ]);
    }
}

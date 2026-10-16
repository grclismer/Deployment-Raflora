<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestBookingClaimAndExpirationUxTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Active temporary guest request displays all required UX elements,
     * separate 6-stage progress, claim action, expiration countdown, and preserves email privacy.
     */
    public function test_active_guest_request_displays_corrected_claim_and_expiration_ux(): void
    {
        $rawToken = Str::random(40);
        $expiresAt = Carbon::now()->addHours(18)->addMinutes(42);

        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Maria Clara',
            'guest_email' => 'maria.clara.secret@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Intramuros, Manila',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'venue' => 'San Agustin Church',
            'expires_at' => $expiresAt,
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertOk();

        // 1. Header & Request identity (the claim actions live once, in the claim panel)
        $response->assertSee('Guest Request');
        $response->assertSee('Booking Request Received');
        $response->assertSee('Track My Booking');
        $response->assertSee('your booking request has been received.');
        $response->assertSee('Raflora will review your event details and, where applicable, analyze your inspiration image before preparing your quotation.');
        $response->assertSee('NO PAYMENT IS REQUIRED AT THIS STAGE');

        // 2. Guest Request Journey: the guest and client stages only (no Staff operational stages);
        //    Raflora Review only starts in the Client Workflow after the request is claimed.
        $response->assertSee('Guest Request Journey');
        $response->assertSee('Request Submitted');
        $response->assertSee('Awaiting Claim');
        $response->assertSee('Stage 2 of 8 · Awaiting Claim');
        $response->assertSee('Raflora Review');
        $response->assertSee('data-workflow-stage="awaiting_claim" data-workflow-state="current"', false);
        $response->assertSee('data-workflow-stage="raflora_review" data-workflow-state="upcoming"', false);
        $response->assertDontSee('data-workflow-phase="staff"', false);
        $response->assertDontSee('Request Expires');
        $response->assertDontSee('Booking Confirmed');
        $response->assertDontSee('Payment / Downpayment');

        // 3. One claim panel: its heading, the countdown, and the claim actions
        $response->assertSee('Claim &amp; Manage Your Booking Request', false);
        $response->assertSee('CLAIM REQUIRED BEFORE EXPIRATION');
        $response->assertSee('Request Active');
        $response->assertSee('remaining');
        $response->assertSee('Claim before');
        $response->assertSee($expiresAt->format('F j, Y'));
        $response->assertSee('Create Account &amp; Claim', false);
        $response->assertSeeText('Already have an account? Log In & Claim');
        $response->assertSee('Create an account or log in to claim this request before it expires. You can still track this request as a guest until the expiration time.');

        // 4. Repeated status, claim-status, countdown and "what happens next" blocks are gone:
        //    the journey above is the one current status.
        foreach (['Current Status', 'Claim Status', 'Time Remaining', 'Request Status', 'What Raflora Is Doing', 'What You Should Expect Next', 'What Happens Next?', 'CLAIM YOUR REQUEST', 'Track Your Booking Easily'] as $removed) {
            $response->assertDontSee($removed);
        }
        $this->assertSame(1, substr_count($response->getContent(), 'id="expirationCountdownText"'));

        // 5. Privacy: Full email is never exposed in the public view text
        $response->assertDontSee('maria.clara.secret@example.com');
    }

    /**
     * Test 2: Expiring-soon state (<= 4 hours remaining).
     */
    public function test_expiring_soon_state_displays_claim_soon_badge(): void
    {
        $rawToken = Str::random(40);
        $expiresAt = Carbon::now()->addHours(2)->addMinutes(15);

        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Hurry Guest',
            'guest_email' => 'hurry@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '456 Manila St',
            'booking_type' => 'custom',
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => $expiresAt,
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertOk();
        $response->assertSee('Claim Soon');
        // The one countdown, in the claim panel.
        $response->assertSee('2h 1', false);
        $response->assertSee('m remaining');
    }

    /**
     * Test 3: Expired temporary guest request returns 403, shows expired state, and removes claim CTA.
     */
    public function test_expired_guest_request_displays_expired_state_and_removes_claim_cta(): void
    {
        $rawToken = Str::random(40);
        $expiredAt = Carbon::now()->subHours(2);

        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Late Guest',
            'guest_email' => 'late@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '789 Makati Ave',
            'booking_type' => 'custom',
            'event_type' => 'anniversary',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Makati Shangri-La',
            'expires_at' => $expiredAt,
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertStatus(403);
        $response->assertSee('Guest Request Expired');
        $response->assertSee('This temporary guest request was not claimed before its expiration time.');
        $response->assertSee('Expired Unclaimed');
        $response->assertSee('No recovery action exists for expired temporary requests.');
        $response->assertSee('Start New Booking Request');

        // Claim CTAs must NOT be shown
        $response->assertDontSee('Create Account &amp; Claim', false);
        $response->assertDontSee('Log In &amp; Claim');
    }

    /**
     * Test 4: Unclaimed guest cannot make payment.
     */
    public function test_unclaimed_guest_payment_is_blocked(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Blocked Guest',
            'guest_email' => 'blocked@example.com',
            'guest_phone' => '09171234567',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'admin_approved',
            'final_quoted_price' => 50000.00,
        ]);

        $postResponse = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'GCASH-123456789',
        ]);

        $postResponse->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $postResponse->assertSessionHas('error');
    }

    /**
     * Test 5: Status polling returns expired status when request expires.
     */
    public function test_status_endpoint_returns_expired_when_booking_expires(): void
    {
        $rawToken = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Polling Guest',
            'guest_email' => 'polling@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '100 Polling St',
            'booking_type' => 'custom',
            'event_type' => 'debut',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->get(route('guest.bookings.status', ['token' => $rawToken]));

        $response->assertOk();
        $response->assertJson([
            'status' => 'expired',
            'is_expired' => true,
        ]);
    }
}

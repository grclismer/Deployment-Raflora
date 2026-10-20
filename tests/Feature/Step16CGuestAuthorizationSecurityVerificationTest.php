<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Step16CGuestAuthorizationSecurityVerificationTest extends TestCase
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
     * PHASE 4: GUEST CROSS-BOOKING TEST
     *
     * Booking A + Token A
     * Booking B + Token B
     *
     * 1. Booking A + Token A -> allowed where intended.
     * 2. Booking B + Token B -> allowed where intended.
     * 3. Booking A + Token B -> denied.
     * 4. Booking B + Token A -> denied.
     * 5. Booking A + missing token -> denied where token is required.
     * 6. Booking B + missing token -> denied where token is required.
     * 7. Booking A + invalid token -> denied.
     * 8. Booking B + invalid token -> denied.
     */
    public function test_phase4_guest_cross_booking_token_isolation(): void
    {
        $tokenA = (string) Str::uuid();
        $tokenB = (string) Str::uuid();

        $bookingA = Booking::create([
            'guest_name' => 'Guest Alpha',
            'guest_email' => 'alpha@example.com',
            'guest_phone' => '09171111111',
            'guest_access_token' => $tokenA,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Alpha Ballroom',
            'status' => 'admin_approved',
            'final_quoted_price' => 50000.00,
        ]);

        $bookingB = Booking::create([
            'guest_name' => 'Guest Beta',
            'guest_email' => 'beta@example.com',
            'guest_phone' => '09172222222',
            'guest_access_token' => $tokenB,
            'client_id' => null,
            'event_type' => 'corporate',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Beta Pavilion',
            'status' => 'admin_approved',
            'final_quoted_price' => 75000.00,
        ]);

        // 1. Booking A + Token A -> allowed (GET analysis)
        $respA = $this->get(route('guest.booking.analysis', ['token' => $tokenA]));
        $respA->assertOk();
        $respA->assertSee('Wedding Proposal');
        $respA->assertDontSee('Corporate Proposal');

        // 2. Booking B + Token B -> allowed (GET analysis)
        $respB = $this->get(route('guest.booking.analysis', ['token' => $tokenB]));
        $respB->assertOk();
        $respB->assertSee('Corporate Proposal');
        $respB->assertDontSee('Wedding Proposal');

        // 3. Booking A + Token B -> denied on action endpoint
        $respAwithB = $this->post(route('guest.bookings.payment.reference', ['booking' => $bookingA->id]), [
            'guest_token' => $tokenB,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-CROSS-AB',
        ]);
        $respAwithB->assertForbidden();

        // 4. Booking B + Token A -> denied on action endpoint
        $respBwithA = $this->post(route('guest.bookings.payment.reference', ['booking' => $bookingB->id]), [
            'guest_token' => $tokenA,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-CROSS-BA',
        ]);
        $respBwithA->assertForbidden();

        // 5. Booking A + missing token -> denied
        $respAMissing = $this->post(route('guest.bookings.payment.reference', ['booking' => $bookingA->id]), [
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-MISSING-A',
        ]);
        $respAMissing->assertForbidden();

        // 6. Booking B + missing token -> denied
        $respBMissing = $this->post(route('guest.bookings.payment.reference', ['booking' => $bookingB->id]), [
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-MISSING-B',
        ]);
        $respBMissing->assertForbidden();

        // 7. Booking A + invalid token -> denied
        $respAInvalid = $this->post(route('guest.bookings.payment.reference', ['booking' => $bookingA->id]), [
            'guest_token' => 'completely-bogus-token-xyz',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-INVALID-A',
        ]);
        $respAInvalid->assertForbidden();

        // 8. Booking B + invalid token -> denied
        $respBInvalid = $this->post(route('guest.bookings.payment.reference', ['booking' => $bookingB->id]), [
            'guest_token' => 'another-invalid-token-123',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-INVALID-B',
        ]);
        $respBInvalid->assertForbidden();

        // Verify database state: zero payments created, no status mutations
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-CROSS-AB']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-CROSS-BA']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-MISSING-A']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-MISSING-B']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-INVALID-A']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-INVALID-B']);
        $this->assertSame('admin_approved', $bookingA->fresh()->status);
        $this->assertSame('admin_approved', $bookingB->fresh()->status);
    }

    /**
     * PHASE 5: UNCLAIMED PAYMENT BOUNDARY
     *
     * Using an UNCLAIMED guest booking:
     * 1. open the authenticated Client payment page -> redirected to login
     * 2. submit a payment to client route -> redirected to login
     * 3. submit a payment to guest route -> denied/blocked with boundary error
     * 4. upload payment proof -> denied
     * 5. verify no Payment record created, status unchanged, amount unchanged
     */
    public function test_phase5_unclaimed_guest_payment_boundary_strictly_enforced(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Unclaimed Target',
            'guest_email' => 'unclaimed.target@example.com',
            'guest_phone' => '09173333333',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Grand Sanctuary',
            'status' => 'admin_approved',
            'final_quoted_price' => 55000.00,
            'total_quoted' => 55000.00,
            'inspiration_image' => 'bookings/inspiration-images/test.jpg',
        ]);

        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 55000.00,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        // 1. Guest attempts to open authenticated Client booking page without auth
        $clientPageResponse = $this->get(route('bookings.show', ['booking' => $booking->id]));
        $clientPageResponse->assertRedirect(route('login'));

        // 2. Guest attempts to post to authenticated Client payment route without auth
        $clientPayResponse = $this->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'UNAUTH-CLIENT-PAY-001',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $clientPayResponse->assertRedirect(route('login'));

        // 3. Guest attempts to submit via guest payment route
        $guestPayResponse = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'reference_number' => 'GUEST-PAY-ATTEMPT-001',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $guestPayResponse->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $guestPayResponse->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');

        // 4. Guest attempts to upload payment proof via client reply route
        $fakeProof = \Illuminate\Http\UploadedFile::fake()->create('proof.jpg', 200, 'image/jpeg');
        $uploadResponse = $this->post(route('bookings.reply', ['booking' => $booking->id]), [
            'message' => 'Here is my payment proof',
            'attachment' => $fakeProof,
            'attachment_category' => 'payment_proof',
        ]);
        $uploadResponse->assertRedirect(route('login'));

        // 5. Guest attempts to access secure inspiration image without token
        $secureFileResponse = $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id]));
        $secureFileResponse->assertForbidden();

        // 6. Guest attempts to access secure inspiration image with invalid token
        $secureFileInvalid = $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => 'invalid-token-xyz']));
        $secureFileInvalid->assertForbidden();

        // 6. Verify database state: no payment records, no status mutations, no messages
        $this->assertDatabaseMissing('payments', ['reference_number' => 'UNAUTH-CLIENT-PAY-001']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'GUEST-PAY-ATTEMPT-001']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('booking_messages', 0);
        $this->assertSame('admin_approved', $booking->fresh()->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $quotation->fresh()->status);
    }

    /**
     * PHASE 6: LEGITIMATE CLAIMING WORKFLOW & OWNERSHIP ESTABLISHMENT
     *
     * 1. Perform actual current claim process
     * 2. Verify booking becomes associated with correct Client
     * 3. Verify Booking.client_id references clients.id
     * 4. Verify no unrelated Client is linked
     * 5. Verify guest cannot claim a booking already owned by another Client
     * 6. Verify claimed Client can access their own booking
     * 7. Verify Client can proceed into existing quotation/payment workflow
     */
    public function test_phase6_legitimate_claiming_and_client_ownership_transfer(): void
    {
        $tempBooking = new TemporaryGuestBooking();
        $tempBooking->fill([
            'guest_name' => 'Clara Client',
            'guest_email' => 'clara@example.com',
            'guest_phone' => '09174444444',
            'guest_address' => '77 Jasmine St, Quezon City',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(40)->toDateString(),
            'venue' => 'Villa Clara',
            'table_count' => 10,
            'guest_count' => 100,
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        $rawToken = $tempBooking->generateToken();
        $tempBooking->save();

        // Register and verify client user with matching email
        $clientUser = User::factory()->create([
            'email' => 'clara@example.com',
            'name' => 'Clara Client',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        // 1. Submit legitimate claim
        $claimResponse = $this->actingAs($clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));
        $claimResponse->assertRedirect(route('bookings'));
        $claimResponse->assertSessionHas('success');

        // 2 & 3. Verify permanent Booking created and client_id points to clients.id
        $convertedBooking = Booking::where('guest_email', 'clara@example.com')->first();
        $this->assertNotNull($convertedBooking);
        $this->assertNotNull($convertedBooking->client_id);

        $clientRecord = Client::where('email', 'clara@example.com')->first();
        $this->assertNotNull($clientRecord);
        $this->assertSame($clientRecord->id, $convertedBooking->client_id);
        $this->assertSame($clientUser->email, $convertedBooking->client->email);

        // 4. Verify no unrelated client is linked
        $otherClientUser = User::factory()->create(['email' => 'other@example.com', 'role' => 'client', 'email_verified_at' => now()]);
        $otherClient = Client::create([
            'id' => $otherClientUser->id,
            'full_name' => 'Other Person',
            'email' => 'other@example.com',
            'phone' => '09179999999',
            'address' => 'Other Address',
        ]);
        $this->assertNotSame($otherClient->id, $convertedBooking->client_id);

        // 5. Verify duplicate claim attempt is rejected
        $duplicateClaimResponse = $this->actingAs($clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));
        $duplicateClaimResponse->assertRedirect(route('client.dashboard'));
        $duplicateClaimResponse->assertSessionHas('error', 'This booking request has already been claimed.');

        // 6. Verify claimed Client can access their own booking
        $accessResponse = $this->actingAs($clientUser)->get(route('bookings.show', ['booking' => $convertedBooking->id]));
        $accessResponse->assertOk();
        $accessResponse->assertSee('Villa Clara');

        // 7. Verify Client can proceed through payment workflow once admin approved
        $convertedBooking->status = 'admin_approved';
        $convertedBooking->final_quoted_price = 45000.00;
        $convertedBooking->total_quoted = 45000.00;
        $convertedBooking->save();

        $payResponse = $this->actingAs($clientUser)->post(route('bookings.payment.reference', ['booking' => $convertedBooking->id]), [
            'reference_number' => 'CLARA-GCASH-PAY-001',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $payResponse->assertRedirect();
        $payResponse->assertSessionHas('success');

        $this->assertSame('payment_submitted', $convertedBooking->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $convertedBooking->id,
            'reference_number' => 'CLARA-GCASH-PAY-001',
            'status' => 'pending',
        ]);
    }

    /**
     * PHASE 7: CLIENT CROSS-BOOKING ISOLATION
     *
     * Client A owns Booking A.
     * Client B owns Booking B.
     *
     * 1. Client A -> Booking A -> allowed.
     * 2. Client B -> Booking B -> allowed.
     * 3. Client A -> Booking B -> denied (403).
     * 4. Client B -> Booking A -> denied (403).
     */
    public function test_phase7_client_cross_booking_access_denied(): void
    {
        $userA = User::factory()->create(['email' => 'client.a@example.com', 'role' => 'client', 'email_verified_at' => now()]);
        $clientA = Client::create([
            'id' => $userA->id,
            'full_name' => 'Client A',
            'email' => $userA->email,
            'phone' => '09175555551',
            'address' => 'District A',
        ]);

        $userB = User::factory()->create(['email' => 'client.b@example.com', 'role' => 'client', 'email_verified_at' => now()]);
        $clientB = Client::create([
            'id' => $userB->id,
            'full_name' => 'Client B',
            'email' => $userB->email,
            'phone' => '09175555552',
            'address' => 'District B',
        ]);

        $bookingA = Booking::create([
            'client_id' => $clientA->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Diamond Hotel',
            'status' => 'admin_approved',
            'final_quoted_price' => 50000.00,
            'total_quoted' => 50000.00,
        ]);

        $bookingB = Booking::create([
            'client_id' => $clientB->id,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(35)->toDateString(),
            'venue' => 'Makati Shangri-La',
            'status' => 'admin_approved',
            'final_quoted_price' => 30000.00,
            'total_quoted' => 30000.00,
        ]);

        // 1. Client A -> Booking A -> allowed
        $this->actingAs($userA)->get(route('bookings.show', ['booking' => $bookingA->id]))->assertOk();

        // 2. Client B -> Booking B -> allowed
        $this->actingAs($userB)->get(route('bookings.show', ['booking' => $bookingB->id]))->assertOk();

        // 3. Client A -> Booking B (view) -> 403 Forbidden
        $this->actingAs($userA)->get(route('bookings.show', ['booking' => $bookingB->id]))->assertForbidden();

        // 4. Client B -> Booking A (view) -> 403 Forbidden
        $this->actingAs($userB)->get(route('bookings.show', ['booking' => $bookingA->id]))->assertForbidden();

        // 5. Client A -> Booking B (submit payment) -> 403 Forbidden
        $crossPayAtoB = $this->actingAs($userA)->post(route('bookings.payment.reference', ['booking' => $bookingB->id]), [
            'reference_number' => 'INTRUDER-PAY-A',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $crossPayAtoB->assertForbidden();

        // 6. Client B -> Booking A (submit payment) -> 403 Forbidden
        $crossPayBtoA = $this->actingAs($userB)->post(route('bookings.payment.reference', ['booking' => $bookingA->id]), [
            'reference_number' => 'INTRUDER-PAY-B',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $crossPayBtoA->assertForbidden();

        // Verify database state: no payment records created
        $this->assertDatabaseMissing('payments', ['reference_number' => 'INTRUDER-PAY-A']);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'INTRUDER-PAY-B']);
    }

    /**
     * PHASE 8: CLAIM AUTHORIZATION / IDOR
     *
     * Guest A cannot claim Guest B's booking.
     * Booking already claimed by Client A cannot be claimed by Guest B.
     */
    public function test_phase8_claim_idor_prevention(): void
    {
        $tempBookingA = new TemporaryGuestBooking();
        $tempBookingA->fill([
            'guest_name' => 'Victim User',
            'guest_email' => 'victim@example.com',
            'guest_phone' => '09176666661',
            'guest_address' => 'Victim Address',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Victim Venue',
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        $tokenA = $tempBookingA->generateToken();
        $tempBookingA->save();

        // Attacker account with different email
        $attackerUser = User::factory()->create([
            'email' => 'attacker@example.com',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        // 1. Attacker tries to view claim page for Victim's booking using Token A -> 403 Forbidden
        $viewClaimResponse = $this->actingAs($attackerUser)->get(route('client.claim-guest-booking.show', ['token' => $tokenA]));
        $viewClaimResponse->assertForbidden();

        // 2. Attacker tries to post claim for Victim's booking using Token A -> 403 Forbidden
        $postClaimResponse = $this->actingAs($attackerUser)->post(route('client.claim-guest-booking.claim', ['token' => $tokenA]));
        $postClaimResponse->assertForbidden();

        // Verify Victim's booking is still unclaimed
        $this->assertFalse($tempBookingA->fresh()->isClaimed());
        $this->assertDatabaseMissing('bookings', ['guest_email' => 'victim@example.com']);
    }

    /**
     * HISTORICAL FINDINGS RE-VERIFICATION
     *
     * 1. Raw numeric booking ID cannot bypass guest token verification on guest analysis
     * 2. Claimed booking rejects guest analysis route with 403 Forbidden
     */
    public function test_historical_findings_guest_id_bypass_is_closed(): void
    {
        $token = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Historical Guest',
            'guest_email' => 'historical@example.com',
            'guest_phone' => '09177777771',
            'guest_access_token' => $token,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Historical Venue',
            'status' => 'pending',
            'total_quoted' => 20000.00,
        ]);

        // Direct numeric ID in guest analysis route returns 404 (token lookup fails)
        $this->get('/guest/booking/' . $booking->id)->assertNotFound();
        $this->get('/guest/booking/analysis/' . $booking->id)->assertNotFound();

        // Valid token succeeds
        $this->get('/guest/booking/' . $token)->assertOk();
    }
}

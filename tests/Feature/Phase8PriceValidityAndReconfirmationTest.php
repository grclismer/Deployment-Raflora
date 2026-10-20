<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase8PriceValidityAndReconfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Client $clientProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_phase8@example.com',
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'client_phase8@example.com',
        ]);

        $this->clientProfile = Client::create([
            'user_id' => $this->clientUser->id,
            'full_name' => 'Phase 8 Client',
            'email' => $this->clientUser->email,
            'phone' => '09170000088',
            'address' => '888 Floral Way, Manila',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createConfirmedBooking(array $attributes = []): Booking
    {
        $booking = Booking::create(array_merge([
            'client_id' => $this->clientProfile->id,
            'status' => 'pending',
            'event_type' => 'Wedding',
            'event_date' => Carbon::parse('2026-11-20')->toDateString(),
            'venue' => 'Manila Grand Pavilion',
            'guest_count' => 150,
            'multiplier' => 2.5,
            'labor_method' => 'fixed',
            'labor_rate' => 1500,
            'final_quoted_price' => 10000,
            'total_quoted' => 10000,
        ], $attributes));

        $item = InventoryItem::create([
            'name' => 'Ecuadorian White Roses',
            'unit_cost' => 50,
            'quantity' => 200,
            'category' => 'flowers',
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 20,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => false,
            'confirmed_at' => Carbon::now(),
        ]);

        return $booking;
    }

    /**
     * Requirement 1: Initial quotation issuance synchronizes booking and quotation validity dates.
     */
    public function test_initial_quotation_issuance_synchronizes_booking_and_quotation_validity_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $booking = $this->createConfirmedBooking();

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $booking->refresh();
        $this->assertSame('quotation_sent', $booking->status);
        $this->assertSame('2026-10-08', $booking->price_valid_until->toDateString());

        $quotation = Quotation::where('booking_id', $booking->id)->where('status', Quotation::STATUS_ISSUED)->first();
        $this->assertNotNull($quotation);
        $this->assertSame(1, $quotation->version);
        $this->assertSame('2026-10-08', $quotation->valid_until->toDateString());

        // Verify client view displays the synchronized date
        $clientResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', ['booking' => $booking->id]));
        $clientResponse->assertOk();
        $clientResponse->assertSee('Valid until Oct 8, 2026');
        $clientResponse->assertDontSee('Quotation Expired');
    }

    /**
     * Requirement 2 & 5: Re-issuance after expiration creates new version with fresh validity date (GAP-01 fix).
     */
    public function test_reissuance_after_expiration_creates_new_version_with_refreshed_validity_date(): void
    {
        // Issue initial quotation on 2026-09-10 (valid until 2026-09-17)
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));
        $booking = $this->createConfirmedBooking();

        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        $v1 = Quotation::where('booking_id', $booking->id)->where('version', 1)->first();
        $this->assertSame('2026-09-17', $v1->valid_until->toDateString());

        // Create an alert as if the scheduled task ran after expiration
        AdminAlert::create([
            'type' => 'quotation_expired',
            'booking_id' => $booking->id,
            'title' => 'Quotation Expired',
            'message' => 'The quotation has expired.',
            'is_read' => false,
        ]);

        // Advance time to 2026-09-25 (quotation is expired)
        Carbon::setTestNow(Carbon::parse('2026-09-25 14:00:00'));

        // Client cannot accept expired quotation
        $acceptResponse = $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));
        $acceptResponse->assertRedirect();
        $acceptResponse->assertSessionHas('error', 'This quotation has expired due to floral price volatility. Please wait for the admin to re-issue an updated quotation.');

        // Admin re-issues quotation on 2026-09-25
        $reissueResponse = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'quotation_sent',
            'action' => 'reissue_quotation',
        ]);

        $reissueResponse->assertRedirect();
        $reissueResponse->assertSessionMissing('error');

        $booking->refresh();
        $v1->refresh();

        // V1 must be superseded
        $this->assertSame(Quotation::STATUS_SUPERSEDED, $v1->status);

        // V2 must be issued with fresh 7-day validity window (2026-09-25 + 7 days = 2026-10-02)
        $v2 = Quotation::where('booking_id', $booking->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertSame(Quotation::STATUS_ISSUED, $v2->status);
        $this->assertSame('2026-10-02', $v2->valid_until->toDateString());
        $this->assertSame('2026-10-02', $booking->price_valid_until->toDateString());

        // Alert must be dismissed
        $this->assertTrue(AdminAlert::where('booking_id', $booking->id)->where('type', 'quotation_expired')->first()->is_read);

        // Client must now be able to accept V2 without being blocked by stale expiration!
        $acceptSuccess = $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));
        $acceptSuccess->assertRedirect(route('bookings.analysis', ['booking' => $booking->id]));
        $acceptSuccess->assertSessionHas('success');

        $booking->refresh();
        $v2->refresh();
        $this->assertSame('approved', $booking->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $v2->status);
    }

    /**
     * Requirement 3 & GAP-02: Quotation remains acceptable throughout the final valid calendar day (end-of-day precision).
     */
    public function test_quotation_remains_acceptable_during_the_final_valid_day(): void
    {
        // Issued on 2026-10-01 -> valid through 2026-10-08
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $booking = $this->createConfirmedBooking();

        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        // Advance to 2026-10-08 17:45:00 (late afternoon on the final valid day)
        Carbon::setTestNow(Carbon::parse('2026-10-08 17:45:00'));

        // Client views analysis page: must not show expired banner
        $viewResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', ['booking' => $booking->id]));
        $viewResponse->assertOk();
        $viewResponse->assertDontSee('Quotation Expired');
        $viewResponse->assertSee('Valid until Oct 8, 2026');

        // Client accepts on the final day: must succeed
        $acceptResponse = $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));
        $acceptResponse->assertRedirect();
        $acceptResponse->assertSessionHas('success');

        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, Quotation::where('booking_id', $booking->id)->first()->status);
    }

    /**
     * Requirement 4: Acceptance is rejected immediately after the expiration day ends (at midnight transition).
     */
    public function test_acceptance_is_rejected_after_the_expiration_day_ends(): void
    {
        // Issued on 2026-10-01 -> valid through 2026-10-08
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $booking = $this->createConfirmedBooking();

        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        // Advance to 2026-10-09 00:00:01 (one second past midnight after expiration day)
        Carbon::setTestNow(Carbon::parse('2026-10-09 00:00:01'));

        // Client view shows expired
        $viewResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', ['booking' => $booking->id]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Quotation Expired');

        // Client acceptance is rejected
        $acceptResponse = $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));
        $acceptResponse->assertRedirect();
        $acceptResponse->assertSessionHas('error', 'This quotation has expired due to floral price volatility. Please wait for the admin to re-issue an updated quotation.');

        $this->assertSame('quotation_sent', $booking->fresh()->status);
    }

    /**
     * Requirement 6 & 8: Payment reference binds to the accepted quotation version.
     */
    public function test_payment_reference_binds_to_accepted_quotation_version(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $booking = $this->createConfirmedBooking();

        // Admin issues quotation
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        $quotation = Quotation::where('booking_id', $booking->id)->first();

        // Client accepts
        $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));

        // Admin final approves
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'approved',
            'action' => 'accept',
        ]);

        $booking->refresh();
        $this->assertSame('admin_approved', $booking->status);

        // Client submits payment reference
        $paymentResponse = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'GCASH-PH8-12345678',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $paymentResponse->assertRedirect();
        $paymentResponse->assertSessionHas('success');

        $payment = Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame($quotation->id, $payment->quotation_id);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    /**
     * Requirement 7: Cannot re-issue over an already accepted quotation.
     */
    public function test_cannot_reissue_over_an_already_accepted_quotation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $booking = $this->createConfirmedBooking();

        // Admin issues quotation
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        // Client accepts
        $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));

        $this->assertSame('approved', $booking->fresh()->status);

        // Admin attempts to re-issue over the accepted quotation
        $reissueResponse = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'approved',
            'action' => 'reissue_quotation',
        ]);

        $reissueResponse->assertRedirect();
        $reissueResponse->assertSessionHas('error');

        // Only 1 quotation must exist, and it must remain accepted
        $this->assertSame(1, Quotation::where('booking_id', $booking->id)->count());
        $this->assertSame(Quotation::STATUS_ACCEPTED, Quotation::where('booking_id', $booking->id)->first()->status);
    }

    /**
     * Requirement 9: Guest user cannot accept quotations directly or bypass inquiry boundaries.
     */
    public function test_guest_user_cannot_accept_quotations_directly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $token = (string) Str::uuid();

        $guestBooking = Booking::create([
            'guest_name' => 'Guest Inquirer',
            'guest_email' => 'guest_inq@example.com',
            'guest_phone' => '09191112233',
            'guest_access_token' => $token,
            'status' => 'quotation_sent',
            'event_type' => 'Birthday',
            'event_date' => '2026-11-15',
            'venue' => 'Garden Terrace',
            'final_quoted_price' => 8000,
            'total_quoted' => 8000,
            'price_valid_until' => '2026-10-08',
        ]);

        Quotation::create([
            'booking_id' => $guestBooking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 8000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => '2026-10-08',
        ]);

        // Guest views analysis page: sees validity date and prompt to register
        $response = $this->get(route('guest.booking.analysis', ['token' => $token]));
        $response->assertOk();
        $response->assertSee('Valid until Oct 8, 2026');
        $response->assertSee('Log In / Register to Review &amp; Accept Quotation', false);

        // Direct post to registered client accept endpoint without auth must redirect to login
        $acceptResponse = $this->post(route('bookings.accept', $guestBooking));
        $acceptResponse->assertRedirect(route('login'));
    }
}

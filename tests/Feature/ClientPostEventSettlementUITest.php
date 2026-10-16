<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPostEventSettlementUITest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Client $client;
    private User $otherUser;
    private Client $otherClient;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'client@raflora.test',
        ]);

        $this->client = Client::create([
            'user_id' => $this->clientUser->id,
            'full_name' => 'PostEvent Client',
            'email' => $this->clientUser->email,
            'phone' => '09171234567',
            'address' => '456 Rose Lane',
        ]);

        $this->otherUser = User::factory()->create([
            'role' => 'client',
            'email' => 'other@raflora.test',
        ]);

        $this->otherClient = Client::create([
            'user_id' => $this->otherUser->id,
            'full_name' => 'Other Client',
            'email' => $this->otherUser->email,
            'phone' => '09179998877',
            'address' => '789 Lily Street',
        ]);
    }

    private function createBooking(float $price, string $status = 'confirmed'): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5),
            'venue' => 'Sunset Floral Garden',
            'final_quoted_price' => $price,
            'total_quoted' => $price,
            'status' => $status,
        ]);

        // Every status used here follows the client's acceptance of an issued quotation,
        // which is where the client's price comes from.
        Quotation::create([
            'booking_id' => $booking->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => $price,
        ]);

        return $booking;
    }

    private function addVerifiedPayment(Booking $booking, float $amount, string $option = 'downpayment'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => $amount,
            'remaining_balance' => max(0.0, $booking->total_obligation - $amount),
            'status' => $option === 'full_payment' ? 'fully_paid' : 'downpayment_received',
            'payment_option' => $option,
            'payment_type' => 'gcash',
            'reference_number' => 'VERIFIED-' . rand(10000, 99999),
            'verified_at' => now(),
        ]);
    }

    private function addPendingPayment(Booking $booking, float $amount, string $option = 'full_payment'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => 0.0,
            'remaining_balance' => $booking->remaining_balance,
            'status' => 'pending',
            'payment_option' => $option,
            'payment_type' => 'gcash',
            'reference_number' => 'PENDING-' . rand(10000, 99999),
        ]);
    }

    private function addDamageCharge(Booking $booking, float $amount, string $decision = 'charge'): ReturnItem
    {
        $item = InventoryItem::create([
            'name' => 'Crystal Centerpiece',
            'category' => 'Decor',
            'current_stock' => 10,
            'unit_cost' => 500,
        ]);

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 1,
            'quoted_unit_price' => 500,
            'confirmed_at' => now(),
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Completed',
            'total_damage_charge' => $amount,
        ]);

        return ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $item->id,
            'booking_item_id' => $bookingItem->id,
            'quantity_dispatched' => 1,
            'quantity_returned' => 0,
            'quantity_damaged' => 1,
            'condition' => 'damaged',
            'damage_charge' => $amount,
            'charge_decision' => $decision,
        ]);
    }

    // =========================================================================
    // 1. event_completed with balance displays balance and permitted payment form
    // =========================================================================

    public function test_event_completed_with_outstanding_balance_displays_balance_and_payment_form(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        $this->assertSame(5000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Displays remaining balance callout
        $response->assertSee('Balance: ₱5,000.00');

        // Status narrative explains event concluded and final payment is due
        $response->assertSee('final payment remains outstanding');

        // Payment form is exposed with correct route
        $response->assertSee(route('bookings.payment.reference', ['booking' => $booking->id]));
        $response->assertSee('Final Balance Settlement (₱5,000.00)');
        $response->assertSee('Submit Payment Reference');
    }

    // =========================================================================
    // 2. event_completed with zero balance does not offer another payment submission
    // =========================================================================

    public function test_event_completed_with_zero_balance_does_not_offer_payment_submission(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 10000, 'full_payment');

        $this->assertSame(0.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Does NOT see balance badge
        $response->assertDontSee('Balance: ₱');

        // Status narrative is congratulatory / concluded without balance prompt
        $response->assertSee('Your event has concluded. Thank you for choosing Raflora Enterprises!');
        $response->assertDontSee('final payment remains outstanding');

        // Payment form is suppressed
        $response->assertDontSee(route('bookings.payment.reference', ['booking' => $booking->id]));
        $response->assertDontSee('Submit Payment Reference');
    }

    // =========================================================================
    // 3. pending_return communicates reconciliation in progress and shows balance
    // =========================================================================

    public function test_pending_return_communicates_reconciliation_pending_without_claiming_fully_paid(): void
    {
        $booking = $this->createBooking(10000, 'pending_return');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Header and status message reflect return in progress
        $response->assertSee('Material Return Pending');
        $response->assertSee('Material return reconciliation is currently in progress.');

        // Must NOT falsely state "Fully Paid"
        $response->assertDontSee('Fully Paid');
        $response->assertDontSee('Your booking is fully paid and confirmed.');

        // Shows remaining balance badge
        $response->assertSee('Balance: ₱5,000.00');

        // Does NOT prematurely expose payment submission while returns are being audited
        $response->assertDontSee('Submit Payment Reference');
    }

    public function test_pending_return_direct_payment_submission_is_rejected(): void
    {
        $booking = $this->createBooking(10000, 'pending_return');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'DIRECT-RECON-ATTEMPT-123',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHas('error', 'Payment reference cannot be submitted while material return reconciliation is in progress.');

        // Booking status remains pending_return and no payment record was created
        $this->assertSame('pending_return', $booking->fresh()->status);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'DIRECT-RECON-ATTEMPT-123']);
    }

    public function test_downpayment_received_premature_payment_submission_is_rejected(): void
    {
        $booking = $this->createBooking(10000, 'downpayment_received');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        // UI suppresses the payment form while in downpayment_received
        $uiResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $uiResponse->assertOk();
        $uiResponse->assertDontSee('Submit Payment Reference');

        // Direct submission is rejected by the controller
        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'PREMATURE-FINAL-PAYMENT-456',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHas('error', 'Payment reference cannot be submitted at this time.');
        $this->assertSame('downpayment_received', $booking->fresh()->status);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'PREMATURE-FINAL-PAYMENT-456']);
    }

    public function test_admin_approved_zero_balance_submission_is_rejected(): void
    {
        $booking = $this->createBooking(0, 'admin_approved');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'ZERO-BAL-REF-789',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('admin_approved', $booking->fresh()->status);
        $this->assertDatabaseMissing('payments', ['reference_number' => 'ZERO-BAL-REF-789']);
    }

    // =========================================================================
    // 4. pending_resolution does not falsely claim the booking is fully paid
    // =========================================================================

    public function test_pending_resolution_communicates_review_pending_without_claiming_fully_paid(): void
    {
        $booking = $this->createBooking(10000, 'pending_resolution');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->addDamageCharge($booking, 1500, 'pending');

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Header and status message reflect pending resolution
        $response->assertSee('Pending Resolution');
        $response->assertSee('Additional charges or adjustments are currently awaiting Admin review.');

        // Must NOT claim "Fully Paid"
        $response->assertDontSee('Your booking is fully paid and confirmed.');

        // Displays remaining balance
        $response->assertSee('Balance: ₱5,000.00');
    }

    // =========================================================================
    // 5. Pending payment reference displays an awaiting-verification state
    // =========================================================================

    public function test_pending_payment_reference_displays_awaiting_verification_state(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->addPendingPayment($booking, 5000, 'full_payment');

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Explains payment reference has been submitted and is under verification
        $response->assertSee('Your payment reference has been submitted. Raflora Administration is currently verifying your payment.');

        // Suppresses duplicate submission form while a payment is pending verification
        $response->assertDontSee('Submit Payment Reference');
    }

    // =========================================================================
    // 6. Rejected payment displays accurate next action
    // =========================================================================

    public function test_rejected_payment_displays_accurate_settlement_guidance(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        // Rejected payment
        $rejected = $this->addPendingPayment($booking, 5000, 'full_payment');
        $rejected->update(['status' => 'rejected', 'reference_number' => 'REJ-12345']);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Rejection alert is visible
        $response->assertSee('Previous Payment Submission Not Verified');
        $response->assertSee('REJ-12345');
        $response->assertSee('submit a corrected reference below to complete your final settlement');

        // Payment form is accessible so client can resubmit
        $response->assertSee('Submit Payment Reference');
    }

    // =========================================================================
    // 7. Post-event payment wording identifies final settlement
    // =========================================================================

    public function test_post_event_payment_wording_identifies_final_settlement(): void
    {
        $booking = $this->createBooking(12000, 'event_completed');
        $this->addVerifiedPayment($booking, 6000, 'downpayment');

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Payment option dropdown shows Final Balance Settlement
        $response->assertSee('Final Balance Settlement (₱6,000.00)');
        // Does NOT show "50% Downpayment" option in this state
        $response->assertDontSee('50% Downpayment');
    }

    // =========================================================================
    // 8. Pre-event downpayment behavior remains unchanged
    // =========================================================================

    public function test_pre_event_downpayment_dropdown_options_remain_intact(): void
    {
        $booking = $this->createBooking(10000, 'admin_approved');

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Shows both Downpayment and Full Payment options
        $response->assertSee('50% Downpayment');
        $response->assertSee('Full Payment (100%)');
        $response->assertSee('Please submit your payment reference below to secure your booking.');
    }

    public function test_pre_event_rejected_payment_displays_secure_booking_guidance(): void
    {
        $booking = $this->createBooking(10000, 'admin_approved');
        $rejected = $this->addPendingPayment($booking, 5000, 'downpayment');
        $rejected->update(['status' => 'rejected', 'reference_number' => 'REJ-PRE-111']);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        $response->assertSee('Previous Payment Submission Not Verified');
        $response->assertSee('submit a corrected reference below to secure your booking');
    }

    // =========================================================================
    // 9. Client ownership and payment authorization remain enforced
    // =========================================================================

    public function test_unauthorized_client_cannot_access_or_submit_payment(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        // Another client attempts to view the booking analysis
        $response = $this->actingAs($this->otherUser)->get(route('bookings.analysis', $booking));
        $response->assertForbidden();

        // Another client attempts to post payment reference
        $postResponse = $this->actingAs($this->otherUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'THEFT-ATTEMPT-01',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);
        $postResponse->assertForbidden();
    }

    // =========================================================================
    // 10. Submission works and updates status
    // =========================================================================

    public function test_client_submitting_final_settlement_reference_transitions_to_payment_submitted(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-FINAL-SETTLE-888',
            'payment_type' => 'bank_transfer',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $booking = $booking->fresh();
        $this->assertSame('event_completed', $booking->status);

        $pendingPayment = $booking->payments()->where('reference_number', 'CLIENT-FINAL-SETTLE-888')->first();
        $this->assertNotNull($pendingPayment);
        $this->assertSame('pending', $pendingPayment->status);
        $this->assertSame(5000.0, (float) $pendingPayment->amount);
    }
}

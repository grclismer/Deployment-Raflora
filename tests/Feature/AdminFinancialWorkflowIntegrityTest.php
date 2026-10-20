<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinancialWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $clientUser;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@raflora.test',
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'client@raflora.test',
        ]);

        $this->client = Client::create([
            'user_id' => $this->clientUser->id,
            'full_name' => 'Integrity Client',
            'email' => 'client@raflora.test',
            'phone' => '09170001122',
            'address' => '789 Blossom Ave',
        ]);
    }

    private function createBooking(float $price, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10),
            'venue' => 'Sunflower Pavillion',
            'final_quoted_price' => $price,
            'total_quoted' => $price,
            'status' => $status,
        ]);
    }

    private function addVerifiedPayment(Booking $booking, float $amount, string $option = 'downpayment', string $type = 'gcash'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => $amount,
            'remaining_balance' => max(0.0, $booking->total_obligation - $amount),
            'status' => $option === 'full_payment' ? 'fully_paid' : 'downpayment_received',
            'payment_option' => $option,
            'payment_type' => $type,
            'reference_number' => 'VERIFIED-' . rand(10000, 99999),
            'verified_by' => $this->adminUser->id,
            'verified_at' => now(),
        ]);
    }

    private function addPendingPayment(Booking $booking, float $amount, string $option = 'downpayment', string $type = 'gcash'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => 0.0,
            'remaining_balance' => $booking->remaining_balance,
            'status' => 'pending',
            'payment_option' => $option,
            'payment_type' => $type,
            'reference_number' => 'PENDING-' . rand(10000, 99999),
        ]);
    }

    private function setupReturnWithItem(Booking $booking, string $returnStatus = 'Completed', float $damageCharge = 0.0, string $decision = 'none'): array
    {
        $item = InventoryItem::create([
            'name' => 'Vintage Arch',
            'category' => 'Hardware',
            'current_stock' => 5,
            'unit_cost' => 1500,
            'is_perishable' => false,
        ]);

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 1,
            'quoted_unit_price' => 1500,
            'confirmed_at' => now(),
        ]);

        // Record dispatch transaction so hasDispatchedReusableMaterials() returns true
        InventoryTransaction::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'transaction_type' => 'dispatch',
            'quantity_change' => -1,
            'created_by' => $this->adminUser->id,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => $returnStatus,
            'total_damage_charge' => $damageCharge,
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $item->id,
            'booking_item_id' => $bookingItem->id,
            'quantity_dispatched' => 1,
            'quantity_returned' => $damageCharge > 0 ? 0 : 1,
            'quantity_damaged' => $damageCharge > 0 ? 1 : 0,
            'condition' => $damageCharge > 0 ? 'damaged' : 'good',
            'damage_charge' => $damageCharge,
            'charge_decision' => $decision,
        ]);

        return [$return, $returnItem, $item];
    }

    // =========================================================================
    // F-FIN-01: Post-Return Settlement Continuity
    // =========================================================================

    public function test_return_assessment_completed_with_zero_balance_transitions_to_completed(): void
    {
        $booking = $this->createBooking(10000, 'pending_return');
        $this->addVerifiedPayment($booking, 10000, 'full_payment');

        [$return, $returnItem] = $this->setupReturnWithItem($booking, 'Pending');

        $response = $this->actingAs($this->adminUser)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 1,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                    'notes' => 'All good',
                ]
            ],
            'notes' => 'Audit complete, zero balance.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_return_assessment_completed_with_outstanding_balance_transitions_to_event_completed_not_deadlocked(): void
    {
        $booking = $this->createBooking(10000, 'pending_return');
        // Only 5000 paid, leaving 5000 balance
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        [$return, $returnItem] = $this->setupReturnWithItem($booking, 'Pending');

        $response = $this->actingAs($this->adminUser)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 1,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                    'notes' => 'Hardware returned intact',
                ]
            ],
            'notes' => 'Audit complete, balance remains.',
        ]);

        $response->assertSessionHasNoErrors();
        // Crucial F-FIN-01: MUST transition to event_completed, NOT remain deadlocked in pending_return
        $this->assertSame('event_completed', $booking->fresh()->status);
        $this->assertSame(5000.0, (float) $booking->fresh()->remaining_balance);
    }

    public function test_admin_can_access_final_payment_action_after_return_in_event_completed(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('Log Final Payment');
    }

    public function test_client_can_submit_payment_reference_in_event_completed(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-FINAL-12345',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertSame('event_completed', $booking->fresh()->status);
        $this->assertTrue($booking->payments()->where('reference_number', 'CLIENT-FINAL-12345')->where('status', 'pending')->exists());
    }

    public function test_payment_verification_after_completed_return_settles_booking_to_completed(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        $pendingPayment = $this->addPendingPayment($booking, 5000, 'full_payment');

        $response = $this->actingAs($this->adminUser)->post(route('admin.payments.verify', $pendingPayment->id));
        $response->assertSessionHasNoErrors();

        $booking = $booking->fresh();
        $this->assertSame(0.0, (float) $booking->remaining_balance);
        $this->assertSame('completed', $booking->status);
    }

    // =========================================================================
    // F-FIN-02: Preserve Lifecycle on Payment Rejection
    // =========================================================================

    public function test_initial_downpayment_rejection_reverts_booking_to_admin_approved(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $pendingPayment = $this->addPendingPayment($booking, 5000, 'downpayment');

        $response = $this->actingAs($this->adminUser)->post(route('admin.payments.reject', $pendingPayment->id));
        $response->assertSessionHasNoErrors();

        $this->assertSame('rejected', $pendingPayment->fresh()->status);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    public function test_post_event_payment_rejection_does_not_regress_to_admin_approved(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        $pendingPayment = $this->addPendingPayment($booking, 5000, 'full_payment');

        $response = $this->actingAs($this->adminUser)->post(route('admin.payments.reject', $pendingPayment->id));
        $response->assertSessionHasNoErrors();

        $this->assertSame('rejected', $pendingPayment->fresh()->status);
        // Crucial F-FIN-02: MUST revert to event_completed, NEVER to admin_approved!
        $this->assertNotSame('admin_approved', $booking->fresh()->status);
        $this->assertSame('event_completed', $booking->fresh()->status);
    }

    public function test_post_event_rejection_with_pending_damage_adjudication_reverts_to_pending_resolution(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        // Return has damaged item with charge_decision = 'pending'
        $this->setupReturnWithItem($booking, 'Completed', 1000.0, 'pending');

        $pendingPayment = $this->addPendingPayment($booking, 6000, 'full_payment');

        $response = $this->actingAs($this->adminUser)->post(route('admin.payments.reject', $pendingPayment->id));
        $response->assertSessionHasNoErrors();

        $this->assertSame('rejected', $pendingPayment->fresh()->status);
        $this->assertSame('pending_resolution', $booking->fresh()->status);
    }

    public function test_client_can_submit_replacement_payment_reference_after_rejection(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        // First payment rejected
        $badPayment = $this->addPendingPayment($booking, 5000, 'full_payment');
        $this->actingAs($this->adminUser)->post(route('admin.payments.reject', $badPayment->id));

        $this->assertSame('event_completed', $booking->fresh()->status);

        // Client submits replacement payment
        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'REPLACEMENT-REF-999',
            'payment_type' => 'bank_transfer',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('event_completed', $booking->fresh()->status);
        $this->assertTrue($booking->payments()->where('reference_number', 'REPLACEMENT-REF-999')->where('status', 'pending')->exists());
    }

    // =========================================================================
    // F-FIN-03: Admin Financial Visibility on Booking Detail
    // =========================================================================

    public function test_admin_booking_detail_displays_authoritative_financial_summary_card(): void
    {
        $booking = $this->createBooking(12000, 'event_completed');
        $this->addVerifiedPayment($booking, 6000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed', 1500.0, 'charge');

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings.show', $booking));
        $response->assertOk();

        // Financial Summary Card elements
        $response->assertSee('Financial Summary &amp; Settlement', false);
        $response->assertSee('Base Quote');
        $response->assertSee('12,000.00');
        $response->assertSee('Damage / Loss');
        $response->assertSee('1,500.00');
        $response->assertSee('Total Obligation');
        $response->assertSee('13,500.00');
        $response->assertSee('Verified Paid');
        $response->assertSee('6,000.00');
        $response->assertSee('Remaining Balance');
        $response->assertSee('7,500.00');
        $response->assertSee('Balance Pending');
    }

    public function test_admin_booking_detail_renders_log_final_payment_action_and_modal(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings.show', $booking));
        $response->assertOk();

        $response->assertSee('Log Final Payment');
        $response->assertSee('booking-show-final-payment-modal');
        $response->assertSee(route('admin.bookings.final_payment', ['booking' => $booking->id]));
    }

    // =========================================================================
    // F-FIN-04: Guard Premature Completion
    // =========================================================================

    public function test_booking_update_blocks_completion_when_outstanding_balance_remains(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');
        $this->setupReturnWithItem($booking, 'Completed');

        $response = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('outstanding balance', session('error'));
        $this->assertNotSame('completed', $booking->fresh()->status);
        $this->assertSame('event_completed', $booking->fresh()->status);
    }

    public function test_booking_update_blocks_completion_when_material_returns_are_incomplete(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 10000, 'full_payment'); // Zero balance
        // Return is NOT completed (status = 'Pending')
        $this->setupReturnWithItem($booking, 'Pending');

        $response = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('return audit has not been completed', session('error'));
        $this->assertNotSame('completed', $booking->fresh()->status);
    }

    public function test_booking_update_blocks_completion_when_damage_charges_are_pending_adjudication(): void
    {
        $booking = $this->createBooking(10000, 'pending_resolution');
        $this->addVerifiedPayment($booking, 10000, 'full_payment');
        $this->setupReturnWithItem($booking, 'Completed', 1000.0, 'pending');

        $response = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('unresolved damage charges', session('error'));
        $this->assertNotSame('completed', $booking->fresh()->status);
    }

    public function test_booking_update_blocks_completion_from_pre_event_statuses(): void
    {
        $booking = $this->createBooking(10000, 'admin_approved');
        $this->addVerifiedPayment($booking, 10000, 'full_payment');

        $response = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('event has not concluded', session('error'));
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    public function test_booking_update_allows_legitimate_completion_when_all_conditions_satisfied(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 10000, 'full_payment');
        $this->setupReturnWithItem($booking, 'Completed');

        $this->assertSame(0.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => 'completed',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
    }

    // =========================================================================
    // F-FIN-05: Preserve Distinct Payment Events
    // =========================================================================

    public function test_final_payment_creates_distinct_payment_record_leaving_downpayment_intact(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $downpayment = $this->addVerifiedPayment($booking, 5000, 'downpayment', 'gcash');
        $downpaymentRef = $downpayment->reference_number;
        $this->setupReturnWithItem($booking, 'Completed');

        $this->assertCount(1, $booking->payments);

        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', ['booking' => $booking->id]), [
            'amount_received' => 5000,
            'payment_type' => 'cash',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $booking = $booking->fresh();
        $payments = $booking->payments()->orderBy('id')->get();

        // Crucial F-FIN-05: MUST have 2 distinct payment records!
        $this->assertCount(2, $payments);

        // Record 1: Original downpayment is preserved
        $p1 = $payments[0];
        $this->assertSame($downpayment->id, $p1->id);
        $this->assertSame(5000.0, (float) $p1->amount);
        $this->assertSame(5000.0, (float) $p1->amount_paid);
        $this->assertSame('downpayment', $p1->payment_option);
        $this->assertSame('gcash', $p1->payment_type);
        $this->assertSame($downpaymentRef, $p1->reference_number);

        // Record 2: Distinct final settlement payment
        $p2 = $payments[1];
        $this->assertNotSame($p1->id, $p2->id);
        $this->assertSame(5000.0, (float) $p2->amount_paid);
        $this->assertSame('cash', $p2->payment_type);
        $this->assertSame('fully_paid', $p2->status);
        $this->assertStringStartsWith('admin-final-', $p2->reference_number);

        // Booking totals
        $this->assertSame(10000.0, (float) $booking->total_paid);
        $this->assertSame(0.0, (float) $booking->remaining_balance);
        $this->assertSame('completed', $booking->status);
    }

    public function test_partial_final_payment_records_distinct_payment_and_preserves_remaining_balance(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment', 'gcash');
        $this->setupReturnWithItem($booking, 'Completed');

        // Admin logs partial payment of 2000
        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', ['booking' => $booking->id]), [
            'amount_received' => 2000,
            'payment_type' => 'cash',
        ]);

        $response->assertSessionHasNoErrors();

        $booking = $booking->fresh();
        $this->assertCount(2, $booking->payments);
        $this->assertSame(7000.0, (float) $booking->total_paid);
        $this->assertSame(3000.0, (float) $booking->remaining_balance);
        $this->assertSame('event_completed', $booking->status);
    }

    public function test_legacy_log_final_payment_action_creates_distinct_payment_record(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $downpayment = $this->addVerifiedPayment($booking, 5000, 'downpayment', 'gcash');
        $this->setupReturnWithItem($booking, 'Completed');

        $response = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => $booking->status,
            'action' => 'log_final_payment',
        ]);

        $response->assertSessionHasNoErrors();

        $booking = $booking->fresh();
        $this->assertCount(2, $booking->payments);

        // Downpayment intact
        $this->assertSame(5000.0, (float) $downpayment->fresh()->amount_paid);
        $this->assertSame('downpayment_received', $downpayment->fresh()->status);

        // Distinct final payment created
        $finalPayment = $booking->payments()->where('id', '!=', $downpayment->id)->first();
        $this->assertNotNull($finalPayment);
        $this->assertSame(5000.0, (float) $finalPayment->amount_paid);
        $this->assertSame('fully_paid', $finalPayment->status);

        $this->assertSame(10000.0, (float) $booking->total_paid);
        $this->assertSame(0.0, (float) $booking->remaining_balance);
        $this->assertSame('completed', $booking->status);
    }

    public function test_verified_totals_exclude_pending_and_rejected_payments(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        // Add pending payment of 2000
        $this->addPendingPayment($booking, 2000, 'full_payment');

        // Add rejected payment of 3000
        $rejectedPayment = $this->addPendingPayment($booking, 3000, 'full_payment');
        $rejectedPayment->update(['status' => 'rejected']);

        $booking = $booking->fresh();

        // Total paid must strictly count ONLY verified payments
        $this->assertSame(5000.0, (float) $booking->total_paid);
        $this->assertSame(5000.0, (float) $booking->remaining_balance);
    }

    public function test_unauthorized_user_cannot_log_final_payment(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 5000, 'downpayment');

        // Client attempts to log final payment via Admin endpoint
        $response = $this->actingAs($this->clientUser)->post(route('admin.bookings.final_payment', ['booking' => $booking->id]), [
            'amount_received' => 5000,
            'payment_type' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_client_cannot_submit_final_payment_reference_when_balance_is_zero(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addVerifiedPayment($booking, 10000, 'full_payment');
        $this->setupReturnWithItem($booking, 'Completed');

        $this->assertSame(0.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-UNNECESSARY-PAYMENT',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('No outstanding balance', session('error'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinancialAndPaymentAlignmentTest extends TestCase
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
            'full_name' => 'Test Client',
            'email' => 'client@raflora.test',
            'phone' => '09171234567',
            'address' => '123 Flower Way',
        ]);
    }

    private function createBooking(float $price, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14),
            'venue' => 'Grand Floral Ballroom',
            'final_quoted_price' => $price,
            'total_quoted' => $price,
            'status' => $status,
        ]);
    }

    private function addPayment(Booking $booking, float $amount, string $status, string $option = 'downpayment', float $amountPaid = null): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => $amountPaid ?? ($status === 'pending' || $status === 'rejected' ? 0.0 : $amount),
            'remaining_balance' => max(0.0, $amount - ($amountPaid ?? ($status === 'pending' || $status === 'rejected' ? 0.0 : $amount))),
            'status' => $status,
            'payment_option' => $option,
            'payment_type' => 'gcash',
            'reference_number' => 'REF-' . rand(10000, 99999),
        ]);
    }

    private function addDamageCharge(Booking $booking, float $amount, string $decision = 'charge'): ReturnItem
    {
        $inventoryItem = InventoryItem::create([
            'name' => 'Crystal Vase',
            'category' => 'Decor',
            'current_stock' => 10,
            'unit_cost' => 500,
        ]);

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 2,
            'price' => 500,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Completed',
            'total_damage_charge' => $amount,
        ]);

        return ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $inventoryItem->id,
            'booking_item_id' => $bookingItem->id,
            'quantity_dispatched' => 2,
            'quantity_returned' => 1,
            'quantity_damaged' => 1,
            'condition' => 'damaged',
            'damage_charge' => $amount,
            'charge_decision' => $decision,
        ]);
    }

    // ==========================================
    // F-ADM-01: Correct Paid Amount Aggregation
    // ==========================================

    public function test_admin_booking_view_excludes_pending_payments_from_paid_amount(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addPayment($booking, 5000, 'pending', 'downpayment', 5000); // Has amount_paid filled in row

        $this->assertSame(0.0, (float) $booking->total_paid);
        $this->assertSame(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Paid:</span>', false);
        $response->assertSee('<span class="font-medium text-emerald-600">₱0.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱10,000.00</span>', false);
        $response->assertDontSee('<span class="font-medium text-emerald-600">₱5,000.00</span>', false);
    }

    public function test_admin_booking_view_excludes_rejected_payments_from_paid_amount(): void
    {
        $booking = $this->createBooking(10000, 'admin_approved');
        $this->addPayment($booking, 5000, 'rejected', 'downpayment', 5000);

        $this->assertSame(0.0, (float) $booking->total_paid);
        $this->assertSame(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Paid:</span>', false);
        $response->assertSee('<span class="font-medium text-emerald-600">₱0.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱10,000.00</span>', false);
        $response->assertDontSee('<span class="font-medium text-emerald-600">₱5,000.00</span>', false);
    }

    public function test_admin_booking_view_includes_accepted_downpayment(): void
    {
        $booking = $this->createBooking(10000, 'downpayment_received');
        $this->addPayment($booking, 5000, 'downpayment_received', 'downpayment', 5000);

        $this->assertSame(5000.0, (float) $booking->total_paid);
        $this->assertSame(5000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Paid:</span>', false);
        $response->assertSee('<span class="font-medium text-emerald-600">₱5,000.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱5,000.00</span>', false);
    }

    public function test_admin_booking_view_includes_accepted_final_payment(): void
    {
        $booking = $this->createBooking(10000, 'completed');
        $this->addPayment($booking, 10000, 'fully_paid', 'full_payment', 10000);

        $this->assertSame(10000.0, (float) $booking->total_paid);
        $this->assertSame(0.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Paid:</span>', false);
        $response->assertSee('<span class="font-medium text-emerald-600">₱10,000.00</span>', false);
        $response->assertDontSee('<span class="text-slate-500">Bal:</span>', false);
    }

    public function test_admin_booking_view_aggregates_multiple_accepted_payments_correctly(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addPayment($booking, 5000, 'downpayment_received', 'downpayment', 5000);
        $this->addPayment($booking, 3000, 'downpayment_received', 'downpayment', 3000);
        $this->addPayment($booking, 2000, 'pending', 'full_payment', 2000); // Pending must not be counted

        $this->assertSame(8000.0, (float) $booking->total_paid);
        $this->assertSame(2000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Paid:</span>', false);
        $response->assertSee('<span class="font-medium text-emerald-600">₱8,000.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱2,000.00</span>', false);
    }

    // ========================================================
    // F-ADM-02: Correct Total Obligation and Remaining Balance
    // ========================================================

    public function test_admin_booking_view_obligation_without_damage_charges(): void
    {
        $booking = $this->createBooking(10000, 'confirmed');

        $this->assertSame(10000.0, (float) $booking->total_obligation);
        $this->assertSame(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Quote:</span>', false);
        $response->assertSee('<span class="font-semibold text-slate-800">₱10,000.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱10,000.00</span>', false);
    }

    public function test_admin_booking_view_obligation_includes_damage_charges_exactly_once(): void
    {
        $booking = $this->createBooking(10000, 'pending_resolution');
        $this->addDamageCharge($booking, 2500, 'charge');

        $booking->refresh();
        $this->assertSame(12500.0, (float) $booking->total_obligation);
        $this->assertSame(12500.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Obligation:</span>', false);
        $response->assertSee('<span class="font-semibold text-slate-800">₱12,500.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱12,500.00</span>', false);
    }

    public function test_admin_booking_view_waived_or_pending_damage_charges_do_not_inflate_obligation(): void
    {
        $booking = $this->createBooking(10000, 'pending_resolution');
        $this->addDamageCharge($booking, 2500, 'waived');

        $booking->refresh();
        $this->assertSame(10000.0, (float) $booking->total_obligation);
        $this->assertSame(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('<span class="text-slate-500">Quote:</span>', false);
        $response->assertSee('<span class="font-semibold text-slate-800">₱10,000.00</span>', false);
        $response->assertSee('<span class="text-slate-500">Bal:</span>', false);
        $response->assertSee('<span class="font-bold text-rose-600">₱10,000.00</span>', false);
    }

    // ==========================================
    // F-ADM-03: Correct Final-Payment Modal
    // ==========================================

    public function test_final_payment_modal_displays_authoritative_obligation_paid_and_remaining_balance(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addPayment($booking, 5000, 'downpayment_received', 'downpayment', 5000);
        $this->addDamageCharge($booking, 2000, 'charge');

        $booking->refresh();
        $this->assertSame(12000.0, (float) $booking->total_obligation);
        $this->assertSame(5000.0, (float) $booking->total_paid);
        $this->assertSame(7000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();

        // Modal displays authoritative amounts
        $response->assertSee('Total Obligation</span><span class="font-semibold">₱12,000.00</span>', false);
        $response->assertSee('Already Paid</span><span class="font-semibold">₱5,000.00</span>', false);
        $response->assertSee('Remaining Balance</span><span class="font-semibold">₱7,000.00</span>', false);

        // Prepopulates amount_received with true remaining balance
        $response->assertSee('name="amount_received" value="7000.00"', false);
    }

    public function test_final_payment_modal_does_not_present_zero_balance_when_payment_is_merely_pending(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addPayment($booking, 10000, 'pending', 'full_payment', 10000);

        $booking->refresh();
        $this->assertSame(0.0, (float) $booking->total_paid);
        $this->assertSame(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();

        $response->assertSee('Total Obligation</span><span class="font-semibold">₱10,000.00</span>', false);
        $response->assertSee('Already Paid</span><span class="font-semibold">₱0.00</span>', false);
        $response->assertSee('Remaining Balance</span><span class="font-semibold">₱10,000.00</span>', false);
        $response->assertSee('name="amount_received" value="10000.00"', false);
    }

    // ==========================================
    // F-ADM-04: Align Final-Payment Controller
    // ==========================================

    public function test_final_payment_controller_uses_authoritative_total_obligation_including_damage_charges(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addPayment($booking, 5000, 'downpayment_received', 'downpayment', 5000);
        $this->addDamageCharge($booking, 3000, 'charge'); // Total obligation: 13000

        $booking->refresh();
        $this->assertSame(13000.0, (float) $booking->total_obligation);
        $this->assertSame(8000.0, (float) $booking->remaining_balance);

        // Paying only the quote balance (5000) does NOT fully pay the booking
        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', $booking), [
            'amount_received' => 5000,
            'payment_type' => 'cash',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame(10000.0, (float) $booking->total_paid);
        $this->assertSame(3000.0, (float) $booking->remaining_balance);
        $this->assertNotSame('completed', $booking->status);

        $latestPayment = $booking->payments()->latest('id')->first();
        $this->assertSame(3000.0, (float) $latestPayment->remaining_balance);
        $this->assertSame('downpayment_received', $latestPayment->status);

        // Pay remaining 3000
        $response2 = $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', $booking), [
            'amount_received' => 3000,
            'payment_type' => 'cash',
        ]);

        $response2->assertRedirect();
        $booking->refresh();
        $this->assertSame(13000.0, (float) $booking->total_paid);
        $this->assertSame(0.0, (float) $booking->remaining_balance);
        $this->assertSame('completed', $booking->status);
    }

    public function test_final_payment_controller_cannot_be_bypassed_by_rejected_payments(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addPayment($booking, 5000, 'rejected', 'downpayment', 5000);

        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', $booking), [
            'amount_received' => 5000,
            'payment_type' => 'bank_transfer',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        // 5000 paid out of 10000 obligation
        $this->assertSame(5000.0, (float) $booking->total_paid);
        $this->assertSame(5000.0, (float) $booking->remaining_balance);
        $this->assertNotSame('completed', $booking->status);
    }

    public function test_final_payment_controller_transitions_to_pending_resolution_when_return_items_await_adjudication(): void
    {
        $booking = $this->createBooking(10000, 'event_completed');
        $this->addPayment($booking, 5000, 'downpayment_received', 'downpayment', 5000);

        // Add return item awaiting adjudication (charge_decision = pending)
        $inventoryItem = InventoryItem::create([
            'name' => 'Gold Arch',
            'category' => 'Decor',
            'current_stock' => 5,
            'unit_cost' => 1500,
        ]);

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1,
            'price' => 1500,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending',
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $inventoryItem->id,
            'booking_item_id' => $bookingItem->id,
            'quantity_dispatched' => 1,
            'quantity_returned' => 1,
            'quantity_damaged' => 1,
            'condition' => 'damaged',
            'damage_charge' => 1500,
            'charge_decision' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', $booking), [
            'amount_received' => 5000,
            'payment_type' => 'cash',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame('pending_resolution', $booking->status);
    }

    public function test_both_final_payment_pathways_behave_consistently(): void
    {
        // Pathway A: finalPayment endpoint
        $bookingA = $this->createBooking(10000, 'event_completed');
        $this->addPayment($bookingA, 5000, 'downpayment_received', 'downpayment', 5000);
        $this->addDamageCharge($bookingA, 1000, 'charge'); // 11000 obligation

        $this->actingAs($this->adminUser)->post(route('admin.bookings.final_payment', $bookingA), [
            'amount_received' => 6000,
            'payment_type' => 'cash',
        ]);

        $bookingA->refresh();
        $this->assertSame(11000.0, (float) $bookingA->total_paid);
        $this->assertSame(0.0, (float) $bookingA->remaining_balance);
        $this->assertSame('completed', $bookingA->status);

        // Pathway B: update action = log_final_payment
        $bookingB = $this->createBooking(10000, 'event_completed');
        $this->addPayment($bookingB, 5000, 'downpayment_received', 'downpayment', 5000);
        $this->addDamageCharge($bookingB, 1000, 'charge'); // 11000 obligation

        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $bookingB), [
            'event_type' => $bookingB->event_type,
            'event_date' => $bookingB->event_date->format('Y-m-d'),
            'venue' => $bookingB->venue,
            'status' => $bookingB->status,
            'action' => 'log_final_payment',
        ]);

        $bookingB->refresh();
        $this->assertSame(11000.0, (float) $bookingB->total_paid);
        $this->assertSame(0.0, (float) $bookingB->remaining_balance);
        $this->assertSame('completed', $bookingB->status);
    }

    // ==========================================
    // F-ADM-05: Correct Verify Payment Visibility
    // ==========================================

    public function test_verify_payment_button_hidden_for_payment_pending(): void
    {
        $booking = $this->createBooking(10000, 'payment_pending');
        $this->addPayment($booking, 5000, 'pending');

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertDontSee('Verify payment for booking ' . $booking->id);
    }

    public function test_verify_payment_button_visible_for_payment_submitted_with_pending_payment(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addPayment($booking, 5000, 'pending');

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('Verify payment for booking ' . $booking->id);
    }

    public function test_verify_payment_button_visible_for_pending_resolution_with_pending_payment(): void
    {
        $booking = $this->createBooking(10000, 'pending_resolution');
        $this->addPayment($booking, 2000, 'pending');

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertSee('Verify payment for booking ' . $booking->id);
    }

    public function test_verify_payment_button_hidden_if_payment_is_already_verified(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $this->addPayment($booking, 5000, 'fully_paid', 'full_payment', 5000);

        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings'));
        $response->assertOk();
        $response->assertDontSee('Verify payment for booking ' . $booking->id);
    }

    public function test_unauthorized_user_cannot_verify_payment(): void
    {
        $booking = $this->createBooking(10000, 'payment_submitted');
        $payment = $this->addPayment($booking, 5000, 'pending');

        // Client user cannot verify payment
        $response = $this->actingAs($this->clientUser)->post(route('admin.payments.verify', $payment), [
            'amount_received' => 5000,
        ]);
        $response->assertForbidden();

        // Guest user cannot verify payment
        $this->post(route('admin.payments.verify', $payment), [
            'amount_received' => 5000,
        ])->assertForbidden();
    }
}

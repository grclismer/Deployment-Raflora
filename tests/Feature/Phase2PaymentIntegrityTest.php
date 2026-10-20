<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\AssetReturn;
use App\Models\ReturnItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2PaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createBooking($price)
    {
        return Booking::create([
            'final_quoted_price' => $price,
            'status' => 'payment_pending',
        ]);
    }

    private function addPayment(Booking $booking, $amount, $status, $option = 'downpayment')
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount_paid' => $amount,
            'amount' => $amount,
            'status' => $status,
            'payment_option' => $option,
            'payment_type' => 'bank_transfer',
            'reference_number' => 'REF' . rand(1000, 9999)
        ]);
    }

    private function addDamageCharge(Booking $booking, $amount, $decision = 'charge')
    {
        $inventoryItem = \App\Models\InventoryItem::create([
            'name' => 'Dummy',
            'category' => 'Dummy Category',
            'current_stock' => 10,
            'unit_cost' => 100
        ]);
        
        $bookingItem = \App\Models\BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1,
            'price' => 100
        ]);

        $return = AssetReturn::create(['booking_id' => $booking->id, 'status' => 'pending']);
        ReturnItem::create([
            'return_id' => $return->id,
            'damage_charge' => $amount,
            'charge_decision' => $decision,
            'condition' => 'damaged',
            'inventory_item_id' => $inventoryItem->id,
            'booking_item_id' => $bookingItem->id,
            'quantity_dispatched' => 1,
            'quantity_returned' => 1,
        ]);
        $return->update(['total_damage_charge' => $amount]);
    }

    /** @test */
    public function verified_downpayment_reduces_balance()
    {
        $booking = $this->createBooking(1000);
        $this->addPayment($booking, 300, 'downpayment_received');
        $this->assertEquals(700, $booking->remaining_balance);
    }

    /** @test */
    public function fully_paid_payment_reduces_balance_correctly()
    {
        $booking = $this->createBooking(1000);
        $this->addPayment($booking, 1000, 'fully_paid', 'full_payment');
        $this->assertEquals(0, $booking->remaining_balance);
    }

    /** @test */
    public function rejected_payment_does_not_reduce_balance()
    {
        $booking = $this->createBooking(1000);
        $this->addPayment($booking, 300, 'rejected');
        $this->assertEquals(1000, $booking->remaining_balance);
    }

    /** @test */
    public function pending_payment_does_not_reduce_balance()
    {
        $booking = $this->createBooking(1000);
        $this->addPayment($booking, 300, 'pending');
        $this->assertEquals(1000, $booking->remaining_balance);
    }

    /** @test */
    public function approved_post_event_charge_increases_total_obligation()
    {
        $booking = $this->createBooking(1000);
        $this->addDamageCharge($booking, 200, 'charge');
        $this->assertEquals(1200, $booking->total_obligation);
        $this->assertEquals(1200, $booking->remaining_balance);
    }

    /** @test */
    public function approved_post_event_charge_plus_verified_payment_reduces_combined_obligation()
    {
        $booking = $this->createBooking(1000);
        $this->addPayment($booking, 1000, 'fully_paid', 'full_payment');
        
        $this->addDamageCharge($booking, 250, 'charge');
        $this->assertEquals(1250, $booking->total_obligation);
        $this->assertEquals(250, $booking->remaining_balance);
        
        $this->addPayment($booking, 250, 'fully_paid');
        $this->assertEquals(0, $booking->fresh()->remaining_balance);
    }

    /** @test */
    public function multiple_verified_payments_reduce_balance()
    {
        $booking = $this->createBooking(1000);
        $this->addPayment($booking, 200, 'downpayment_received');
        $this->addPayment($booking, 300, 'downpayment_received');
        $this->addPayment($booking, 500, 'fully_paid');
        $this->assertEquals(0, $booking->remaining_balance);
    }

    /** @test */
    public function payment_snapshot_reflects_correct_balance_after_verification()
    {
        $booking = $this->createBooking(1000);
        $booking->update(['status' => 'payment_submitted']);
        $this->addDamageCharge($booking, 500, 'charge');
        
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 1000,
            'status' => 'pending',
            'payment_option' => 'full_payment',
            'payment_type' => 'bank_transfer',
            'reference_number' => 'REF12345'
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment->id), [
            'amount_received' => 1000
        ]);
        
        if (session('error')) {
            dump(session('error'));
        }
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $payment->refresh();
        $this->assertEquals('fully_paid', $payment->status);
        $this->assertEquals(500, $payment->remaining_balance);
    }

    /** @test */
    public function no_charge_return_does_not_increase_obligation()
    {
        $booking = $this->createBooking(1000);
        $this->addDamageCharge($booking, 500, 'no_charge');
        $this->assertEquals(1000, $booking->total_obligation);
        $this->assertEquals(1000, $booking->remaining_balance);
    }

    /** @test */
    public function pending_charge_decision_does_not_increase_obligation()
    {
        $booking = $this->createBooking(1000);
        $this->addDamageCharge($booking, 500, 'pending');
        $this->assertEquals(1000, $booking->total_obligation);
        $this->assertEquals(1000, $booking->remaining_balance);
    }
}

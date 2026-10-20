<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Booking;
use App\Models\User;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase3PostEventPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
        $this->clientUser = $this->user('client');
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => uniqid($role . '-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now()
        ]);
    }

    private function createBooking($status = 'admin_approved', $price = 1000)
    {
        $client = Client::create([
            'full_name' => 'Client Name',
            'email' => $this->clientUser->email,
            'phone' => '09170000000',
            'address' => 'Address'
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Venue',
            'final_quoted_price' => $price,
            'total_quoted' => $price,
            'status' => $status,
        ]);
        
        $booking->quotations()->create([
            'version' => 1,
            'final_quoted_price' => $price,
            'status' => 'accepted'
        ]);
        
        return $booking;
    }

    private function addPayment(Booking $booking, $amount, $status, $option = 'full_payment')
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount_paid' => $amount,
            'amount' => $amount,
            'status' => $status,
            'payment_option' => $option,
            'payment_type' => 'gcash',
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
        
        $assetReturn = \App\Models\AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Completed',
        ]);
        
        \App\Models\ReturnItem::create([
            'return_id' => $assetReturn->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_returned' => 1,
            'condition' => 'damaged',
            'charge_decision' => $decision,
            'damage_charge' => $amount
        ]);
        
        return $assetReturn;
    }

    public function test_admin_approved_client_payment_submission_still_succeeds()
    {
        $booking = $this->createBooking('admin_approved');
        
        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking->id), [
            'reference_number' => 'REF123',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment'
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($booking->payments()->where('reference_number', 'REF123')->exists());
    }

    public function test_pending_resolution_with_approved_charge_and_positive_balance_allows_submission()
    {
        $booking = $this->createBooking('pending_resolution');
        
        // simulate initial payment
        $this->addPayment($booking, 1000, 'fully_paid');
        
        // simulate post-event charge
        $this->addDamageCharge($booking, 500, 'charge');
        $booking->refresh();

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking->id), [
            'reference_number' => 'REFPOST123',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment'
        ]);

        $response->assertSessionHasNoErrors();
        
        $payment = $booking->payments()->where('reference_number', 'REFPOST123')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(500, $payment->amount);
    }

    public function test_pending_resolution_with_zero_balance_rejects_submission()
    {
        $booking = $this->createBooking('pending_resolution');
        
        // simulate initial payment
        $this->addPayment($booking, 1000, 'fully_paid');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking->id), [
            'reference_number' => 'REFPOST0',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment'
        ]);

        $response->assertSessionHas('error', 'No outstanding balance requires payment.');
    }

    public function test_pending_resolution_with_no_charge_return_does_not_create_payable_balance()
    {
        $booking = $this->createBooking('pending_resolution');
        
        // simulate initial payment
        $this->addPayment($booking, 1000, 'fully_paid');
        
        // simulate no charge return
        $this->addDamageCharge($booking, 500, 'no_charge');
        $booking->refresh();

        $response = $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking->id), [
            'reference_number' => 'REFPOSTNOCHARGE',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment'
        ]);

        $response->assertSessionHas('error', 'No outstanding balance requires payment.');
    }

    public function test_client_cannot_submit_payment_for_another_clients_booking()
    {
        $client2User = $this->user('client');
        $booking = $this->createBooking('admin_approved');

        $response = $this->actingAs($client2User)->post(route('bookings.payment.reference', $booking->id), [
            'reference_number' => 'REFOTHER',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment'
        ]);

        $response->assertStatus(403);
    }
    
    public function test_admin_verification_of_post_event_payment_completes_booking_if_return_done()
    {
        $booking = $this->createBooking('pending_resolution');
        
        $this->addPayment($booking, 1000, 'fully_paid');
        $this->addDamageCharge($booking, 500, 'charge');
        $booking->refresh();
        
        // Client submits post-event payment
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 500,
            'payment_option' => 'full_payment',
            'remaining_balance' => 0,
            'payment_type' => 'gcash',
            'reference_number' => 'REF_VERIFY',
            'status' => 'pending'
        ]);
        
        // Admin verifies
        $response = $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment->id));
        
        $response->assertSessionHasNoErrors();
        
        $booking->refresh();
        $payment->refresh();
        
        $this->assertEquals('completed', $booking->status, 'Booking should automatically complete once balance is 0 and return is done.');
        $this->assertEquals('fully_paid', $payment->status);
        $this->assertEquals(0, $booking->remaining_balance);
    }
}

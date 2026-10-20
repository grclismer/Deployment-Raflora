<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3_1b8ClientDataAndStatusAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Client $client;

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
            'full_name' => 'Jane Client',
            'email' => 'client@raflora.test',
            'phone' => '09171234567',
        ]);
    }

    private function createBooking(float $price, string $status = 'confirmed'): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14),
            'final_quoted_price' => $price,
            'total_quoted' => $price,
            'status' => $status,
        ]);

        // Every status used here comes after Admin issued a quotation (accepted from 'approved' on),
        // which is where the client's price comes from.
        Quotation::create([
            'booking_id' => $booking->id,
            'status' => $status === 'quotation_sent' ? Quotation::STATUS_ISSUED : Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => $price,
        ]);

        return $booking;
    }

    private function addPayment(Booking $booking, float $amount, string $status, string $option = 'downpayment'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => $amount,
            'status' => $status,
            'payment_option' => $option,
            'payment_type' => 'bank_transfer',
            'reference_number' => 'REF-' . rand(10000, 99999),
        ]);
    }

    private function addDamageCharge(Booking $booking, float $amount, string $decision = 'charge'): ReturnItem
    {
        $inventoryItem = InventoryItem::create([
            'name' => 'Crystal Centerpiece Vase',
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

    /**
     * F-01: Booking without damage charges displays quote as total obligation.
     */
    public function test_booking_without_damage_charges_displays_quote_accurately(): void
    {
        $booking = $this->createBooking(10000, 'quotation_sent');

        $this->assertEquals(10000.0, (float) $booking->total_obligation);
        $this->assertEquals(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Displays Quotation label and exact quoted amount
        $response->assertSee('Quotation');
        $response->assertSee('₱10,000.00');

        // Does not show damage charges note
        $response->assertDontSee('Damage/Loss Charges');
    }

    /**
     * F-01: Booking with damage charges includes charge exactly once in total obligation.
     */
    public function test_booking_with_damage_charges_includes_charge_exactly_once(): void
    {
        $booking = $this->createBooking(10000, 'pending_resolution');
        $this->addDamageCharge($booking, 2500, 'charge');

        $booking->refresh();

        // 10000 quote + 2500 damage charge = 12500 total obligation
        $this->assertEquals(12500.0, (float) $booking->total_obligation);
        $this->assertEquals(12500.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Displays Total Obligation header and combined obligation
        $response->assertSee('Total Obligation');
        $response->assertSee('₱12,500.00');

        // Explicit breakdown note indicates damage charges portion exactly once
        $response->assertSee('Includes ₱2,500.00 Damage/Loss Charges');

        // Balance badge reflects remaining obligation
        $response->assertSee('Balance: ₱12,500.00');
    }

    /**
     * F-01: Waived or pending damage decisions do not increase total obligation.
     */
    public function test_waived_or_pending_damage_charges_do_not_inflate_total_obligation(): void
    {
        $booking = $this->createBooking(10000, 'pending_return');
        $this->addDamageCharge($booking, 3000, 'no_charge');
        $this->addDamageCharge($booking, 2000, 'pending');

        $booking->refresh();

        $this->assertEquals(10000.0, (float) $booking->total_obligation);
        $this->assertEquals(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();
        $response->assertSee('₱10,000.00');
        $response->assertDontSee('Includes ₱');
    }

    /**
     * F-02: Verified downpayment aggregates in total_paid and reduces balance.
     */
    public function test_verified_downpayment_aggregates_and_reduces_balance(): void
    {
        $booking = $this->createBooking(10000, 'downpayment_received');
        $this->addPayment($booking, 5000, 'downpayment_received', 'downpayment');

        $booking->refresh();

        $this->assertEquals(5000.0, (float) $booking->total_paid);
        $this->assertEquals(5000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();
        $response->assertSee('Paid: ₱5,000.00');
        $response->assertSee('Balance: ₱5,000.00');
    }

    /**
     * F-02: Verified full payment reduces balance to zero.
     */
    public function test_verified_full_payment_reduces_balance_to_zero(): void
    {
        $booking = $this->createBooking(10000, 'confirmed');
        $this->addPayment($booking, 10000, 'fully_paid', 'full_payment');

        $booking->refresh();

        $this->assertEquals(10000.0, (float) $booking->total_paid);
        $this->assertEquals(0.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();
        $response->assertSee('Paid: ₱10,000.00');
        $response->assertDontSee('Balance: ₱');
    }

    /**
     * F-02: Pending and rejected payments are strictly excluded from client paid aggregation.
     */
    public function test_unverified_payments_do_not_inflate_paid_amount_or_reduce_balance(): void
    {
        $booking = $this->createBooking(10000, 'admin_approved');

        // Add unverified submissions
        $this->addPayment($booking, 5000, 'pending', 'downpayment');
        $this->addPayment($booking, 5000, 'rejected', 'downpayment');

        $booking->refresh();

        // Both unverified payments must be excluded
        $this->assertEquals(0.0, (float) $booking->total_paid);
        $this->assertEquals(10000.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();

        // Must not claim any amount is paid
        $response->assertDontSee('Paid: ₱');
        $response->assertDontSee('Paid: ₱5,000.00');
        $response->assertDontSee('Paid: ₱10,000.00');
    }

    /**
     * F-02: Multiple verified payments aggregate accurately across payments.
     */
    public function test_multiple_verified_payments_aggregate_accurately(): void
    {
        $booking = $this->createBooking(12000, 'confirmed');

        $this->addPayment($booking, 3000, 'downpayment_received');
        $this->addPayment($booking, 4000, 'downpayment_received');
        $this->addPayment($booking, 5000, 'fully_paid');

        // And an unverified submission which should not be counted
        $this->addPayment($booking, 2000, 'pending');

        $booking->refresh();

        $this->assertEquals(12000.0, (float) $booking->total_paid);
        $this->assertEquals(0.0, (float) $booking->remaining_balance);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $response->assertOk();
        $response->assertSee('Paid: ₱12,000.00');
    }

    /**
     * F-04: Client-facing label for 'approved' status displays 'Quotation Accepted'.
     */
    public function test_approved_status_label_displays_quotation_accepted_across_client_portal(): void
    {
        $booking = $this->createBooking(10000, 'approved');

        // 1. Model client_status_label accessor
        $this->assertSame('Quotation Accepted', $booking->client_status_label);

        // 2. Client Dashboard
        $dashboardResponse = $this->actingAs($this->clientUser)->get(route('client.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Quotation Accepted');

        // 3. Client Bookings Index
        $bookingsResponse = $this->actingAs($this->clientUser)->get(route('bookings'));
        $bookingsResponse->assertOk();
        $bookingsResponse->assertSee('Quotation Accepted');

        // 4. Client Booking Analysis Details
        $analysisResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $booking));
        $analysisResponse->assertOk();
        $analysisResponse->assertSee('Quotation Accepted');

        // 5. Client Booking Updates
        $updatesResponse = $this->actingAs($this->clientUser)->get(route('client.booking.updates', $booking));
        $updatesResponse->assertOk();
        $updatesResponse->assertSee('Quotation Accepted');
    }

    /**
     * Preservation: Admin approval boundary remains enforced for payment submission.
     */
    public function test_admin_approval_boundary_enforced_between_approved_and_admin_approved(): void
    {
        // When status is 'approved' (Quotation Accepted by client)
        $approvedBooking = $this->createBooking(10000, 'approved');
        $approvedResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $approvedBooking));
        $approvedResponse->assertOk();
        $approvedResponse->assertDontSee('Submit Payment Reference');
        $approvedResponse->assertSee('Raflora Administration must complete the final booking approval before payment submission becomes available.');

        // When status transitions to 'admin_approved'
        $adminApprovedBooking = $this->createBooking(10000, 'admin_approved');
        $adminApprovedResponse = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $adminApprovedBooking));
        $adminApprovedResponse->assertOk();
        $adminApprovedResponse->assertSee('Submit Payment Reference');
    }

    /**
     * Preservation: Unauthorized clients cannot view another client's booking analysis.
     */
    public function test_unauthorized_client_cannot_access_other_client_booking_analysis(): void
    {
        $otherUser = User::factory()->create(['role' => 'client']);
        $booking = $this->createBooking(10000, 'confirmed');

        $response = $this->actingAs($otherUser)->get(route('bookings.analysis', $booking));
        $response->assertStatus(403);
    }
}

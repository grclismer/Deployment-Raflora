<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Step16DBookingConflictAndAvailabilityVerificationTest extends TestCase
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
     * TEST 1: MULTI-BOOKING ON SAME DATE IS SUPPORTED WHEN INVENTORY SUFFICES
     *
     * Raflora explicitly supports multiple bookings on the same calendar day
     * (TASK.md Section 3: "Keep multi-booking support for the same date").
     * Two bookings with different or sufficient resources can both be confirmed.
     */
    public function test_multi_booking_on_same_date_allowed_when_inventory_sufficient(): void
    {
        $eventDate = Carbon::now()->addDays(20)->toDateString();

        $itemA = InventoryItem::create([
            'name' => 'Arch A',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 5,
            'min_stock' => 1,
            'unit_cost' => 500.00,
            'unit' => 'piece',
        ]);

        $itemB = InventoryItem::create([
            'name' => 'Arch B',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 5,
            'min_stock' => 1,
            'unit_cost' => 600.00,
            'unit' => 'piece',
        ]);

        // Booking 1: Uses Item A
        $booking1 = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Manila Cathedral',
            'status' => 'pending',
            'total_quoted' => 30000.00,
        ]);
        $booking1->inventoryItems()->attach($itemA->id, [
            'quantity' => 2,
            'quoted_unit_price' => 500.00,
            'confirmed_at' => now(),
        ]);

        // Booking 2: Uses Item B (same date, different venue)
        $booking2 = Booking::create([
            'event_type' => 'birthday',
            'event_date' => $eventDate,
            'venue' => 'Greenbelt Lounge',
            'status' => 'pending',
            'total_quoted' => 15000.00,
        ]);
        $booking2->inventoryItems()->attach($itemB->id, [
            'quantity' => 2,
            'quoted_unit_price' => 600.00,
            'confirmed_at' => now(),
        ]);

        // Admin confirms Booking 1
        $this->recordVerifiedPayment($booking1);
        $resp1 = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking1), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Manila Cathedral',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);
        $resp1->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $booking1->fresh()->status);

        // Admin confirms Booking 2 on the same date
        $this->recordVerifiedPayment($booking2);
        $resp2 = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking2), [
            '_method' => 'PUT',
            'event_type' => 'birthday',
            'event_date' => $eventDate,
            'venue' => 'Greenbelt Lounge',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);
        $resp2->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $booking2->fresh()->status);
    }

    /**
     * TEST 2: SAME-DATE BOOKING CONFLICT IS BLOCKED WHEN REUSABLE INVENTORY IS EXCEEDED
     *
     * If Booking 1 confirms 4 of 5 units of a non-perishable prop on Date X,
     * Booking 2 demanding 2 units on Date X is REJECTED by validateBookingInventoryAvailability.
     */
    public function test_same_date_booking_conflict_blocked_when_reusable_inventory_exceeded(): void
    {
        $eventDate = Carbon::now()->addDays(25)->toDateString();

        $goldPedestal = InventoryItem::create([
            'name' => 'Gold Pedestal',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 5,
            'min_stock' => 1,
            'unit_cost' => 250.00,
            'unit' => 'piece',
        ]);

        // Booking 1: Confirmed with 4 units
        $booking1 = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Palace Hotel',
            'status' => 'downpayment_received',
            'total_quoted' => 20000.00,
        ]);
        $booking1->inventoryItems()->attach($goldPedestal->id, [
            'quantity' => 4,
            'quoted_unit_price' => 250.00,
            'confirmed_at' => now(),
        ]);

        // Booking 2: Requires 2 units on the SAME DATE
        $booking2 = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Seaside Pavilion',
            'status' => 'pending',
            'total_quoted' => 18000.00,
        ]);
        $booking2->inventoryItems()->attach($goldPedestal->id, [
            'quantity' => 2,
            'quoted_unit_price' => 250.00,
            'confirmed_at' => now(),
        ]);

        // Admin attempts to transition Booking 2 to downpayment_received
        $this->recordVerifiedPayment($booking2);
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking2), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Seaside Pavilion',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        // Must be rejected with stock overlap error
        $response->assertSessionHas('error', 'This booking would exceed the available stock for the selected event date.');
        $this->assertSame('pending', $booking2->fresh()->status);
    }

    /**
     * TEST 3: CANCELLED OR DECLINED BOOKINGS DO NOT BLOCK SAME-DATE BOOKINGS
     */
    public function test_cancelled_or_declined_booking_does_not_block_same_date_booking(): void
    {
        $eventDate = Carbon::now()->addDays(15)->toDateString();

        $mirrorTable = InventoryItem::create([
            'name' => 'Mirror Table',
            'category' => 'furniture',
            'is_perishable' => false,
            'current_stock' => 3,
            'min_stock' => 1,
            'unit_cost' => 800.00,
            'unit' => 'piece',
        ]);

        // Booking 1 was CANCELLED
        $cancelledBooking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Old Venue',
            'status' => 'cancelled',
            'total_quoted' => 15000.00,
        ]);
        $cancelledBooking->inventoryItems()->attach($mirrorTable->id, [
            'quantity' => 3,
            'quoted_unit_price' => 800.00,
            'confirmed_at' => now(),
        ]);

        // Booking 2 on the SAME DATE requests 2 units
        $activeBooking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'New Venue',
            'status' => 'pending',
            'total_quoted' => 15000.00,
        ]);
        $activeBooking->inventoryItems()->attach($mirrorTable->id, [
            'quantity' => 2,
            'quoted_unit_price' => 800.00,
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($activeBooking);
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $activeBooking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'New Venue',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        $response->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $activeBooking->fresh()->status);
    }

    /**
     * TEST 4: UNCONFIRMED INQUIRIES DO NOT PREMATURELY BLOCK OTHER BOOKINGS
     */
    public function test_unconfirmed_inquiries_do_not_block_same_date_booking(): void
    {
        $eventDate = Carbon::now()->addDays(18)->toDateString();

        $candleStand = InventoryItem::create([
            'name' => 'Candle Stand',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 4,
            'min_stock' => 1,
            'unit_cost' => 150.00,
            'unit' => 'piece',
        ]);

        // Booking 1 is still in pending inquiry
        $pendingBooking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Venue A',
            'status' => 'pending',
            'total_quoted' => 10000.00,
        ]);
        $pendingBooking->inventoryItems()->attach($candleStand->id, [
            'quantity' => 4,
            'quoted_unit_price' => 150.00,
            'confirmed_at' => now(),
        ]);

        // Booking 2 is ready to confirm with 3 units
        $readyBooking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Venue B',
            'status' => 'pending',
            'total_quoted' => 10000.00,
        ]);
        $readyBooking->inventoryItems()->attach($candleStand->id, [
            'quantity' => 3,
            'quoted_unit_price' => 150.00,
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($readyBooking);
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $readyBooking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Venue B',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        $response->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $readyBooking->fresh()->status);
    }

    /**
     * TEST 5: PERISHABLE FRESH FLOWERS ARE PROCURED AND DO NOT ACT AS OVERLAP LOCKS
     */
    public function test_perishable_flowers_do_not_act_as_date_overlap_locks(): void
    {
        $eventDate = Carbon::now()->addDays(12)->toDateString();

        $freshRoses = InventoryItem::create([
            'name' => 'White Ecuadorian Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 0, // Currently 0 in warehouse (procured per event)
            'min_stock' => 0,
            'unit_cost' => 80.00,
            'unit' => 'stem',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'St. John Church',
            'status' => 'pending',
            'total_quoted' => 20000.00,
        ]);
        $booking->inventoryItems()->attach($freshRoses->id, [
            'quantity' => 100,
            'quoted_unit_price' => 80.00,
            'confirmed_at' => now(),
        ]);

        // Even though current_stock is 0, perishable flowers do not trigger date-overlap lock
        $this->recordVerifiedPayment($booking);
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'St. John Church',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        $response->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $booking->fresh()->status);
    }

    /**
     * TEST 6: DIFFERENT DATES DO NOT CONFLICT ON DATE-SPECIFIC DEMAND
     */
    public function test_different_dates_do_not_conflict_for_date_specific_demand(): void
    {
        $dateA = Carbon::now()->addDays(10)->toDateString();
        $dateB = Carbon::now()->addDays(20)->toDateString();

        $arch = InventoryItem::create([
            'name' => 'Circle Arch',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 0,
            'unit_cost' => 1000.00,
            'unit' => 'piece',
        ]);

        // Booking 1 uses the arch on Date A
        $booking1 = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $dateA,
            'venue' => 'Venue 1',
            'status' => 'downpayment_received',
            'total_quoted' => 15000.00,
        ]);
        $booking1->inventoryItems()->attach($arch->id, [
            'quantity' => 1,
            'quoted_unit_price' => 1000.00,
            'confirmed_at' => now(),
        ]);

        // Booking 2 uses the arch on Date B (different date, returned in between)
        $booking2 = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $dateB,
            'venue' => 'Venue 2',
            'status' => 'pending',
            'total_quoted' => 15000.00,
        ]);
        $booking2->inventoryItems()->attach($arch->id, [
            'quantity' => 1,
            'quoted_unit_price' => 1000.00,
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($booking2);
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking2), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $dateB,
            'venue' => 'Venue 2',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        $response->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $booking2->fresh()->status);
    }

    /**
     * TEST 7: INVENTORY RESERVATION AT PREPARATION PERIOD ENFORCES ATOMIC RESERVATION
     */
    public function test_inventory_reservation_at_preparation_period_locks_and_prevents_overbooking(): void
    {
        $eventDate = Carbon::now()->addDays(2)->toDateString();
        $prepDate = Carbon::now()->subDay()->toDateString(); // Preparation started yesterday

        $crystalChandelier = InventoryItem::create([
            'name' => 'Crystal Chandelier',
            'category' => 'lighting',
            'is_perishable' => false,
            'current_stock' => 2,
            'min_stock' => 0,
            'unit_cost' => 1500.00,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'preparation_start_date' => $prepDate,
            'preparation_status' => 'in_preparation',
            'venue' => 'Grand Opera House',
            'status' => 'pending',
            'total_quoted' => 30000.00,
        ]);
        $booking->inventoryItems()->attach($crystalChandelier->id, [
            'quantity' => 2,
            'quoted_unit_price' => 1500.00,
            'confirmed_at' => now(),
        ]);

        // Transition to downpayment_received with preparation active -> reserves inventory
        $this->recordVerifiedPayment($booking);
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'preparation_start_date' => $prepDate,
            'venue' => 'Grand Opera House',
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        $crystalChandelier->refresh();
        $this->assertSame(2.0, (float) $crystalChandelier->reserved_stock);
        $this->assertSame(0.0, (float) $crystalChandelier->net_available);
    }

    /**
     * TEST 8: PAYMENT VERIFICATION IS IDEMPOTENT AND BLOCKS DUPLICATE CONFIRMATION
     */
    public function test_payment_verification_is_idempotent_and_prevents_duplicate_confirmation(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Diamond Hotel',
            'status' => 'payment_submitted',
            'total_quoted' => 40000.00,
            'final_quoted_price' => 40000.00,
        ]);

        $payment = \App\Models\Payment::create([
            'booking_id' => $booking->id,
            'amount' => 20000.00,
            'amount_paid' => 0.00,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => 'REF-VERIFY-LOCK-01',
            'status' => 'pending',
        ]);

        // First verification succeeds
        $resp1 = $this->actingAs($this->admin)->post(route('admin.payments.verify', ['payment' => $payment->id]), [
            'amount_received' => 20000.00,
        ]);
        $resp1->assertSessionHas('success');
        $this->assertSame('downpayment_received', $booking->fresh()->status);
        $this->assertSame('downpayment_received', $payment->fresh()->status);

        // Second verification attempt on the same payment fails cleanly
        $resp2 = $this->actingAs($this->admin)->post(route('admin.payments.verify', ['payment' => $payment->id]), [
            'amount_received' => 20000.00,
        ]);
        $resp2->assertSessionHas('error', 'Payment verification requires a submitted payment.');
    }

    /**
     * TEST 9: PAYMENT VERIFICATION BLOCKS CONFIRMATION IF PREPARATION STOCK INSUFFICIENT
     */
    public function test_payment_verification_blocks_confirmation_if_preparation_stock_insufficient(): void
    {
        $prepDate = Carbon::now()->subDay()->toDateString(); // Preparation already started

        $item = InventoryItem::create([
            'name' => 'Rustic Bench',
            'category' => 'furniture',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 0,
            'unit_cost' => 400.00,
            'unit' => 'piece',
        ]);

        // Prior booking holds the only available unit
        $otherBooking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(2)->toDateString(),
            'status' => 'downpayment_received',
            'total_quoted' => 10000.00,
        ]);
        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $otherBooking->id,
            'quantity_change' => -1.0,
            'transaction_type' => 'booking_lock',
            'reason' => 'Existing lock for other booking',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(2)->toDateString(),
            'preparation_start_date' => $prepDate,
            'preparation_status' => 'in_preparation',
            'venue' => 'Rustic Barn',
            'status' => 'payment_submitted',
            'total_quoted' => 20000.00,
            'final_quoted_price' => 20000.00,
        ]);
        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 1, // Requires 1, but 0 available
            'quoted_unit_price' => 400.00,
            'confirmed_at' => now(),
        ]);

        $payment = \App\Models\Payment::create([
            'booking_id' => $booking->id,
            'amount' => 10000.00,
            'amount_paid' => 0.00,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => 'REF-BENCH-SHORTAGE',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.verify', ['payment' => $payment->id]), [
            'amount_received' => 10000.00,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Rustic Bench', session('error'));
        // Booking must remain in payment_submitted, NOT confirmed
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    /**
     * TEST 10: SAME CLIENT RAPID DUPLICATE SUBMISSION IS DEDUPLICATED BY CACHE AND TIMESTAMP
     *
     * Venue and Event Date in BookingController are used for same-client rapid double-click
     * deduplication within a 10-second window, preventing accidental duplicate bookings.
     */
    public function test_same_client_rapid_duplicate_booking_is_deduplicated(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email' => 'client.dedup@example.com',
            'email_verified_at' => now(),
        ]);
        $client = Client::create([
            'user_id' => $user->id,
            'full_name' => 'Dedup Client',
            'email' => $user->email,
            'phone' => '09123456789',
        ]);

        $eventDate = Carbon::now()->addDays(30)->toDateString();
        $venue = 'Grand Ballroom A';

        // Existing booking created 3 seconds ago
        $existingBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => $venue,
            'status' => 'pending',
            'created_at' => now()->subSeconds(3),
        ]);

        $cacheKey = 'client_recent_booking_' . md5($client->id . '|' . $eventDate . '|' . $venue);
        Cache::put($cacheKey, $existingBooking->id, 10);

        // Client attempts duplicate submission
        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => $venue,
        ]);

        $response->assertRedirect(route('bookings.show', ['booking' => $existingBooking->id]));
        $response->assertSessionHas('info', 'Your booking request has already been submitted.');
    }
}


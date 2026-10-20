<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\InventoryDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartialDispatchReservationReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Client $clientRecord;
    protected InventoryItem $item;
    protected InventoryDispatchService $dispatchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->clientUser = User::factory()->create(['role' => 'client']);

        $this->clientRecord = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);

        $this->item = InventoryItem::create([
            'name' => 'Gold Candelabra',
            'category' => 'prop',
            'is_perishable' => false,
            'current_stock' => 20,
            'unit_cost' => 500,
            'unit' => 'piece',
            'min_stock' => 2,
        ]);

        $this->dispatchService = new InventoryDispatchService();
    }

    protected function createConfirmedBookingWithLock(int $lockQuantity = 10): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Grand Ballroom',
            'status' => 'event_in_progress',
            'confirmed_at' => now(),
            'total_quoted' => 5000,
            'remaining_balance' => 0,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'quantity' => $lockQuantity,
            'quoted_unit_price' => 500,
            'confirmed_at' => now(),
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $this->item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$lockQuantity,
            'transaction_type' => 'booking_lock',
            'reason' => 'Reservation lock for booking #' . $booking->id,
            'performed_by' => $this->admin->id,
        ]);

        return $booking;
    }

    /**
     * Test 1 — Partial dispatch followed by event completion
     *
     * Initial stock: 20
     * Reservation: 10
     * Dispatch: 7
     * Complete event:
     * - Assert a release of exactly 3 units
     * - Assert reserved stock becomes 0
     * - Assert physical stock remains 13
     * - Assert net available becomes 13
     */
    public function test_partial_dispatch_followed_by_event_completion_releases_unfulfilled_reservation()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Before dispatch: current_stock = 20, reserved = 10, available = 10
        $this->assertEquals(20, $this->item->fresh()->current_stock);
        $this->assertEquals(10, $this->item->fresh()->reserved_stock);
        $this->assertEquals(10, $this->item->fresh()->net_available);

        // Dispatch 7 units
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 7],
        ], $this->admin->id, 'Partial dispatch 7 of 10');

        // After dispatch: physical stock = 13, reserved = 3 (undispatched gap), available = 10
        $this->assertEquals(13, $this->item->fresh()->current_stock);
        $this->assertEquals(3, $this->item->fresh()->reserved_stock);
        $this->assertEquals(10, $this->item->fresh()->net_available);

        // Mark event completed via admin update route
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 1. Assert release of exactly 3 units was recorded
        $releaseTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->where('transaction_type', 'booking_release')
            ->first();

        $this->assertNotNull($releaseTx, 'A booking_release transaction must be created for the undispatched units.');
        $this->assertEquals(3.0, (float) $releaseTx->quantity_change);

        // 2. Assert reserved stock becomes 0
        $this->assertEquals(0.0, (float) $this->item->fresh()->reserved_stock);

        // 3. Assert physical stock remains 13 (no physical stock was created or altered by the release)
        $this->assertEquals(13.0, (float) $this->item->fresh()->current_stock);

        // 4. Assert net available becomes 13 (current_stock 13 - reserved_stock 0)
        $this->assertEquals(13.0, (float) $this->item->fresh()->net_available);
    }

    /**
     * Test 2 — Return completion after partial dispatch
     *
     * Continue from partial dispatch:
     * - Complete the return audit for the 7 dispatched units as Good
     * - Assert physical stock is restored to 20
     * - Assert reserved stock remains 0
     * - Assert net available equals 20
     * - Assert no duplicate reservation release occurs
     */
    public function test_return_completion_after_partial_dispatch_restores_stock_without_duplicate_release()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 7 of 10
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 7],
        ], $this->admin->id, 'Partial dispatch 7 of 10');

        // Complete event
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $return = $booking->returns()->first();
        $this->assertNotNull($return, 'Return tracking record must exist after event completion.');

        $returnItem = $return->returnItems()->where('inventory_item_id', $this->item->id)->first();
        $this->assertNotNull($returnItem, 'Return item record must exist for dispatched item.');

        // Complete the return audit for the 7 dispatched items as Good
        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 7,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
            'notes' => 'All 7 dispatched items returned in good condition',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 1. Assert physical stock is restored correctly to 20 (13 + 7 returned)
        $this->assertEquals(20.0, (float) $this->item->fresh()->current_stock);

        // 2. Assert reserved stock remains 0
        $this->assertEquals(0.0, (float) $this->item->fresh()->reserved_stock);

        // 3. Assert net available equals 20
        $this->assertEquals(20.0, (float) $this->item->fresh()->net_available);

        // 4. Assert exactly one booking_release transaction exists (no duplicate release)
        $releaseCount = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->where('transaction_type', 'booking_release')
            ->count();

        $this->assertEquals(1, $releaseCount, 'Exactly one release transaction must exist; return completion must be idempotent.');
    }

    /**
     * Test 3 — Idempotency
     *
     * Attempt release again after the booking's remaining reservation has been released:
     * - Assert no duplicate release transaction is created
     * - Assert inventory values remain unchanged
     */
    public function test_release_unfulfilled_reservations_is_fully_idempotent()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 7 of 10
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 7],
        ], $this->admin->id, 'Partial dispatch');

        // First release call
        $firstRelease = $this->dispatchService->releaseUnfulfilledReservations($booking, $this->admin->id);
        $this->assertCount(1, $firstRelease);
        $this->assertEquals(3.0, (float) $firstRelease[0]->quantity_change);

        $stockAfterFirst = $this->item->fresh()->current_stock;
        $reservedAfterFirst = $this->item->fresh()->reserved_stock;
        $availableAfterFirst = $this->item->fresh()->net_available;

        // Second release call
        $secondRelease = $this->dispatchService->releaseUnfulfilledReservations($booking, $this->admin->id);
        $this->assertEmpty($secondRelease, 'Second release invocation must create zero new transactions.');

        // Third release call
        $thirdRelease = $this->dispatchService->releaseUnfulfilledReservations($booking, $this->admin->id);
        $this->assertEmpty($thirdRelease, 'Third release invocation must create zero new transactions.');

        // Assert inventory numbers are unchanged
        $this->assertEquals($stockAfterFirst, $this->item->fresh()->current_stock);
        $this->assertEquals($reservedAfterFirst, $this->item->fresh()->reserved_stock);
        $this->assertEquals($availableAfterFirst, $this->item->fresh()->net_available);
    }

    /**
     * Test 4 — Cancellation compatibility
     *
     * - Confirm cancellation with zero dispatch still releases the full reservation
     * - Confirm cancellation after partial dispatch releases only the unfulfilled balance
     */
    public function test_cancellation_releases_full_or_partial_reservation_correctly()
    {
        // Case A: Cancellation with zero dispatch
        $bookingZero = $this->createConfirmedBookingWithLock(10);
        $this->assertEquals(10, $this->item->fresh()->reserved_stock);

        $this->actingAs($this->admin)->put(route('admin.bookings.update', $bookingZero), [
            'event_type' => $bookingZero->event_type,
            'event_date' => $bookingZero->event_date->toDateString(),
            'venue' => $bookingZero->venue,
            'status' => 'cancelled',
            'action' => 'save',
        ]);

        $this->assertEquals('cancelled', $bookingZero->fresh()->status);
        $this->assertEquals(0, $this->item->fresh()->reserved_stock);
        $this->assertEquals(20, $this->item->fresh()->current_stock);
        $this->assertEquals(20, $this->item->fresh()->net_available);

        // Assert 10 units released
        $releaseTxZero = InventoryTransaction::where('booking_id', $bookingZero->id)
            ->where('transaction_type', 'booking_release')
            ->first();
        $this->assertEquals(10.0, (float) $releaseTxZero->quantity_change);

        // Case B: Cancellation after partial dispatch (e.g. 7 dispatched, event cancelled on site)
        $bookingPartial = $this->createConfirmedBookingWithLock(10);
        $this->dispatchService->dispatchItems($bookingPartial, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 7],
        ], $this->admin->id, 'Partial dispatch 7');

        $this->actingAs($this->admin)->put(route('admin.bookings.update', $bookingPartial), [
            'event_type' => $bookingPartial->event_type,
            'event_date' => $bookingPartial->event_date->toDateString(),
            'venue' => $bookingPartial->venue,
            'status' => 'cancelled',
            'action' => 'save',
        ]);

        $this->assertEquals('cancelled', $bookingPartial->fresh()->status);
        // Only 3 undispatched units released
        $releaseTxPartial = InventoryTransaction::where('booking_id', $bookingPartial->id)
            ->where('transaction_type', 'booking_release')
            ->first();
        $this->assertEquals(3.0, (float) $releaseTxPartial->quantity_change);

        // Reserved stock drops to 0, physical stock remains 13 (7 are with client/venue), available is 13
        $this->assertEquals(0.0, (float) $this->item->fresh()->reserved_stock);
        $this->assertEquals(13.0, (float) $this->item->fresh()->current_stock);
        $this->assertEquals(13.0, (float) $this->item->fresh()->net_available);
    }

    /**
     * Test 5 — Cannot dispatch on completed or cancelled bookings
     */
    public function test_cannot_dispatch_on_completed_or_cancelled_bookings()
    {
        $booking = $this->createConfirmedBookingWithLock(10);
        $booking->update(['status' => 'event_completed']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Booking #{$booking->id} cannot dispatch items while it is Event Completed.");

        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 5],
        ], $this->admin->id, 'Late dispatch attempt');
    }
}

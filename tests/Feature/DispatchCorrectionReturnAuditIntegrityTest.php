<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ReturnItem;
use App\Models\User;
use App\Services\InventoryDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchCorrectionReturnAuditIntegrityTest extends TestCase
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
            'name' => 'Crystal Vase',
            'category' => 'prop',
            'is_perishable' => false,
            'current_stock' => 50,
            'unit_cost' => 200,
            'unit' => 'piece',
            'min_stock' => 5,
        ]);

        $this->dispatchService = new InventoryDispatchService();
    }

    protected function createConfirmedBookingWithLock(int $lockQuantity = 10): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Grand Hall',
            'status' => 'event_in_progress',
            'confirmed_at' => now(),
            'total_quoted' => 2000,
            'remaining_balance' => 0,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'quantity' => $lockQuantity,
            'quoted_unit_price' => 200,
            'confirmed_at' => now(),
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $this->item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$lockQuantity,
            'transaction_type' => 'booking_lock',
            'reason' => 'Booking lock #' . $booking->id,
            'performed_by' => $this->admin->id,
        ]);

        return $booking;
    }

    /**
     * Test 1 — Valid dispatch correction before return reconciliation behaves correctly.
     */
    public function test_valid_dispatch_correction_before_return_reconciliation_behaves_correctly()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 8 units
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 8],
        ], $this->admin->id, 'Dispatch 8 units');

        $this->assertEquals(42.0, (float) $this->item->fresh()->current_stock); // 50 - 8

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // Correct dispatch by 2 units before return audit
        $this->dispatchService->correctDispatch($dispatchTx, 2.0, $this->admin->id, 'Dispatched 2 too many');

        // Physical stock restored by 2
        $this->assertEquals(44.0, (float) $this->item->fresh()->current_stock);

        // Net dispatch across transactions is now 6
        $netDispatch = abs((float) InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])
            ->sum('quantity_change'));
        $this->assertEquals(6.0, $netDispatch);

        // Correction transaction exists
        $correctionTx = InventoryTransaction::where('reference_transaction_id', $dispatchTx->id)
            ->where('transaction_type', 'dispatch_correction')
            ->first();
        $this->assertNotNull($correctionTx);
        $this->assertEquals(2.0, (float) $correctionTx->quantity_change);
    }

    /**
     * Test 2 — Correction after completed return audit cannot double-restore stock.
     */
    public function test_dispatch_correction_after_completed_return_audit_is_rejected_preventing_double_restoration()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 8 units
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 8],
        ], $this->admin->id, 'Dispatch 8 units');

        // Mark event completed
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $return = $booking->returns()->first();
        $returnItem = $return->returnItems()->where('inventory_item_id', $this->item->id)->first();

        // Complete return audit with all 8 returned as Good
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 8,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
            'notes' => 'All 8 returned in good condition',
        ]);

        // Physical stock is fully restored to 50
        $this->assertEquals(50.0, (float) $this->item->fresh()->current_stock);
        $this->assertEquals('Completed', $return->fresh()->status);

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // Attempt dispatch correction after completed return audit
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Cannot correct dispatch: return audit for booking #{$booking->id} has already been completed.");

        $this->dispatchService->correctDispatch($dispatchTx, 2.0, $this->admin->id, 'Attempt late correction');

        // Ensure physical stock remained 50 (never reached if exception thrown)
        $this->assertEquals(50.0, (float) $this->item->fresh()->current_stock);
    }

    /**
     * Test 3 — Correction is bounded by partial return audit reconciliation.
     */
    public function test_dispatch_correction_bounded_by_partial_return_audit_reconciliation()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 8 units
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 8],
        ], $this->admin->id, 'Dispatch 8 units');

        // Initialize return record
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $return = $booking->returns()->first();
        $returnItem = $return->returnItems()->where('inventory_item_id', $this->item->id)->first();

        // Partially return 6 units as Good (2 units remain unaccounted)
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 6,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
            'notes' => 'Partial return of 6 units',
        ]);

        // Physical stock: 42 + 6 = 48
        $this->assertEquals(48.0, (float) $this->item->fresh()->current_stock);
        $this->assertEquals('Partially Returned', $return->fresh()->status);

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // Attempt to correct 3 units (only 2 are unaccounted: 8 dispatched - 6 returned = 2)
        try {
            $this->dispatchService->correctDispatch($dispatchTx, 3.0, $this->admin->id, 'Attempt over-correction beyond unaccounted');
            $this->fail('Expected LogicException when correcting beyond unaccounted return balance.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('exceeds maximum allowed by return audit', $e->getMessage());
        }

        // Correcting exactly 2 units (the unaccounted portion) succeeds
        $this->dispatchService->correctDispatch($dispatchTx, 2.0, $this->admin->id, 'Correct the 2 undispatched units');

        // Physical stock restored by 2: 48 + 2 = 50 (back to 100% initial stock)
        $this->assertEquals(50.0, (float) $this->item->fresh()->current_stock);

        // Net dispatch is now 6, which exactly matches the 6 returned Good units
        $netDispatch = abs((float) InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])
            ->sum('quantity_change'));
        $this->assertEquals(6.0, $netDispatch);
    }

    /**
     * Test 4 — Repeated correction attempts do not duplicate stock restoration.
     */
    public function test_repeated_dispatch_corrections_do_not_duplicate_stock_restoration()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 8 units (stock: 50 -> 42)
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 8],
        ], $this->admin->id, 'Dispatch 8 units');

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $this->item->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // First correction: 2 units (stock: 42 + 2 = 44)
        $this->dispatchService->correctDispatch($dispatchTx, 2.0, $this->admin->id, 'Correction 1');
        $this->assertEquals(44.0, (float) $this->item->fresh()->current_stock);

        // Second correction: 3 units (stock: 44 + 3 = 47)
        $this->dispatchService->correctDispatch($dispatchTx, 3.0, $this->admin->id, 'Correction 2');
        $this->assertEquals(47.0, (float) $this->item->fresh()->current_stock);

        // Total corrected so far: 5 of 8. Remaining dispatch is 3.
        // Attempt third correction of 4 units (exceeds remaining 3)
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('exceeds maximum allowed');

        $this->dispatchService->correctDispatch($dispatchTx, 4.0, $this->admin->id, 'Excess correction');
    }

    /**
     * Test 5 — Invalid correction quantities are rejected.
     */
    public function test_invalid_correction_quantities_are_rejected()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 5],
        ], $this->admin->id, 'Dispatch 5');

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // Zero quantity
        try {
            $this->dispatchService->correctDispatch($dispatchTx, 0.0, $this->admin->id, 'Zero');
            $this->fail('Expected exception for zero correction quantity.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Correction quantity must be positive', $e->getMessage());
        }

        // Negative quantity
        try {
            $this->dispatchService->correctDispatch($dispatchTx, -2.0, $this->admin->id, 'Negative');
            $this->fail('Expected exception for negative correction quantity.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Correction quantity must be positive', $e->getMessage());
        }
    }

    /**
     * Test 6 — Controller handles rejected dispatch correction gracefully.
     */
    public function test_controller_handles_rejected_dispatch_correction_gracefully()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 5],
        ], $this->admin->id, 'Dispatch 5');

        // Complete event and return
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $return = $booking->returns()->first();
        $returnItem = $return->returnItems()->first();

        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 5,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
            'notes' => 'All returned',
        ]);

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // Admin attempts dispatch correction via controller route
        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch.correct', $dispatchTx), [
            'quantity' => 2,
            'reason' => 'Late admin correction',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Correction failed', session('error'));
        $this->assertStringContainsString('return audit for booking', session('error'));
    }

    /**
     * Test 7 — Dispatch correction after event completion clears reservation ledger atomically.
     */
    public function test_dispatch_correction_after_event_completion_clears_reservation_ledger()
    {
        $booking = $this->createConfirmedBookingWithLock(10);

        // Dispatch 8 of 10
        $this->dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $this->item->id, 'quantity' => 8],
        ], $this->admin->id, 'Dispatch 8');

        // Complete event: releases the 2 undispatched units (reserved_stock becomes 0)
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $this->assertEquals(0.0, (float) $this->item->fresh()->reserved_stock);
        $this->assertEquals(42.0, (float) $this->item->fresh()->current_stock);

        $dispatchTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('transaction_type', 'dispatch')
            ->first();

        // Admin corrects dispatch by 1 unit (while return audit is still pending)
        $this->dispatchService->correctDispatch($dispatchTx, 1.0, $this->admin->id, 'Correction after event completion');

        // Physical stock increased by 1: 42 + 1 = 43
        $this->assertEquals(43.0, (float) $this->item->fresh()->current_stock);

        // Reserved stock must remain 0 because the newly restored undispatched unit was released
        $this->assertEquals(0.0, (float) $this->item->fresh()->reserved_stock);

        // Net available: 43 - 0 = 43
        $this->assertEquals(43.0, (float) $this->item->fresh()->net_available);
    }
}

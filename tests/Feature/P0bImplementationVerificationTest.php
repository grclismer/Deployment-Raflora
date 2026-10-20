<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class P0bImplementationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $unassignedStaff;
    private Booking $booking;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->unassignedStaff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        
        $this->booking = Booking::create([
            'staff_id' => $this->staff->id,
            'status' => 'confirmed',
            'confirmed_at' => Carbon::now()->subDay(),
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(5)->toDateString(),
            'guest_email' => 'test@example.com',
        ]);

        $this->item = InventoryItem::create([
            'name' => 'Chair',
            'category' => 'furniture',
            'is_perishable' => false,
            'current_stock' => 100,
            'unit' => 'piece',
            'unit_cost' => 10,
        ]);

        BookingItem::create([
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $this->item->id,
            'quantity' => 10,
            'unit_price' => 100,
        ]);

        // Create booking lock
        InventoryTransaction::create([
            'inventory_item_id' => $this->item->id,
            'booking_id' => $this->booking->id,
            'transaction_type' => 'booking_lock',
            'quantity_change' => -10,
            'user_id' => $this->admin->id,
            'notes' => 'Lock 10',
        ]);
    }

    public function test_full_dispatch()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [
                ['inventory_item_id' => $this->item->id, 'quantity' => 10],
            ],
            'reason' => 'Full dispatch',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inventory_transactions', [
            'booking_id' => $this->booking->id,
            'transaction_type' => 'dispatch',
            'quantity_change' => -10,
        ]);
        $this->assertEquals(90, $this->item->fresh()->current_stock);
    }

    public function test_partial_dispatch()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [
                ['inventory_item_id' => $this->item->id, 'quantity' => 4],
            ],
            'reason' => 'Partial dispatch',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inventory_transactions', [
            'booking_id' => $this->booking->id,
            'transaction_type' => 'dispatch',
            'quantity_change' => -4,
        ]);
        $this->assertEquals(96, $this->item->fresh()->current_stock);
    }

    public function test_multiple_partial_dispatches()
    {
        $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 3]],
            'reason' => 'First partial',
        ]);
        
        $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 2]],
            'reason' => 'Second partial',
        ]);

        $this->assertEquals(95, $this->item->fresh()->current_stock);
    }

    public function test_dispatch_exceeding_outstanding_quantity()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 12]],
            'reason' => 'Exceeding dispatch',
        ]);

        $response->assertSessionHas('error');
        $this->assertMatchesRegularExpression('/only 10( \S+)? are reserved for this booking/', session('error'));
        $this->assertEquals(100, $this->item->fresh()->current_stock);
    }

    public function test_insufficient_physical_stock()
    {
        $this->item->update(['current_stock' => 5]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 8]],
            'reason' => 'Insufficient stock',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('in stock. Receive stock in Inventory Management, or dispatch fewer.', session('error'));
        $this->assertEquals(5, $this->item->fresh()->current_stock);
    }

    public function test_unauthorized_staff_dispatch()
    {
        // Try via admin route using staff
        $response = $this->actingAs($this->staff)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 5]],
            'reason' => 'Unauthorized route',
        ]);
        $response->assertForbidden();
    }

    public function test_staff_dispatch_for_unassigned_booking()
    {
        $response = $this->actingAs($this->unassignedStaff)->post(route('staff.events.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 5]],
            'reason' => 'Unassigned staff',
        ]);

        // EventController throws 404 (firstOrFail) for unassigned booking
        $response->assertNotFound();
    }

    public function test_staff_dispatch_for_assigned_booking()
    {
        $response = $this->actingAs($this->staff)->post(route('staff.events.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 5]],
            'reason' => 'Assigned staff',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(95, $this->item->fresh()->current_stock);
    }

    public function test_valid_correction()
    {
        $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 4]],
            'reason' => 'Dispatch',
        ]);

        $txn = InventoryTransaction::where('transaction_type', 'dispatch')->first();

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch.correct', $txn), [
            'quantity' => 1,
            'reason' => 'Correction',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(97, $this->item->fresh()->current_stock); // 100 - 4 + 1
        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_type' => 'dispatch_correction',
            'quantity_change' => 1,
            'reference_transaction_id' => $txn->id,
        ]);
    }

    public function test_correction_exceeding_original_quantity()
    {
        $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 4]],
            'reason' => 'Dispatch',
        ]);

        $txn = InventoryTransaction::where('transaction_type', 'dispatch')->first();

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch.correct', $txn), [
            'quantity' => 5, // Exceeds 4
            'reason' => 'Correction',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('exceeds maximum allowed correction', session('error'));
        $this->assertEquals(96, $this->item->fresh()->current_stock); 
    }

    public function test_null_confirmation_timestamp_rejection()
    {
        $this->booking->update(['confirmed_at' => null]); // Legacy/UNCLEAR status

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 2]],
            'reason' => 'Dispatch legacy',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('UNCLEAR (null)', session('error'));
    }

    public function test_end_to_end_verification_scenario()
    {
        // - A confirmed booking requires 10 units. (Already set up, Outstanding = 10)
        // - Staff dispatches 4 units.
        $this->actingAs($this->staff)->post(route('staff.events.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 4]],
            'reason' => 'First dispatch',
        ]);

        // - Physical stock decreases by exactly 4. (100 -> 96)
        $this->assertEquals(96, $this->item->fresh()->current_stock);

        // - Outstanding quantity becomes 6.
        // Formula: lock (10) - release (0) - net_dispatch (4) = 6
        $netDispatch = abs(InventoryTransaction::where('inventory_item_id', $this->item->id)->where('booking_id', $this->booking->id)->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])->sum('quantity_change'));
        $outstanding = 10 - 0 - $netDispatch;
        $this->assertEquals(6, $outstanding);

        // - Staff dispatches another 3 units.
        $this->actingAs($this->staff)->post(route('staff.events.dispatch', $this->booking), [
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 3]],
            'reason' => 'Second dispatch',
        ]);

        // - Physical stock decreases by another 3. (96 -> 93)
        $this->assertEquals(93, $this->item->fresh()->current_stock);

        // - Outstanding quantity becomes 3.
        $netDispatch = abs(InventoryTransaction::where('inventory_item_id', $this->item->id)->where('booking_id', $this->booking->id)->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])->sum('quantity_change'));
        $outstanding = 10 - 0 - $netDispatch;
        $this->assertEquals(3, $outstanding);

        // - Admin performs a valid correction of 1 unit on the FIRST dispatch.
        $firstDispatch = InventoryTransaction::where('transaction_type', 'dispatch')->orderBy('id', 'asc')->first();
        $this->actingAs($this->admin)->post(route('admin.bookings.dispatch.correct', $firstDispatch), [
            'quantity' => 1,
            'reason' => 'Correct first dispatch',
        ]);

        // - Physical stock increases by 1. (93 -> 94)
        $this->assertEquals(94, $this->item->fresh()->current_stock);

        // - Outstanding quantity increases by 1 (back to 4).
        $netDispatch = abs(InventoryTransaction::where('inventory_item_id', $this->item->id)->where('booking_id', $this->booking->id)->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])->sum('quantity_change'));
        $outstanding = 10 - 0 - $netDispatch;
        $this->assertEquals(4, $outstanding);

        // - Original dispatch records remain unchanged. (First should still be -4)
        $this->assertEquals(-4, $firstDispatch->fresh()->quantity_change);

        // - All operations appear in transaction history.
        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_type' => 'dispatch',
            'quantity_change' => -4,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_type' => 'dispatch',
            'quantity_change' => -3,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_type' => 'dispatch_correction',
            'quantity_change' => 1,
            'reference_transaction_id' => $firstDispatch->id,
        ]);
    }
}

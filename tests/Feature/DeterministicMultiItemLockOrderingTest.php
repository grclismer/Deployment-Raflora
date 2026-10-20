<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use App\Services\InventoryDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterministicMultiItemLockOrderingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private Client $clientRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->client = User::create([
            'name' => 'Client User',
            'email' => 'client-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);

        $this->clientRecord = Client::create([
            'email' => $this->client->email,
            'full_name' => $this->client->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
            'user_id' => $this->client->id,
        ]);
    }

    public function test_multi_item_return_audit_in_ascending_order_updates_stock_and_transactions_correctly(): void
    {
        [$booking, $return, $items] = $this->createReturnFixtureWithMultipleItems(3);

        // Submit in ascending order of returnItem IDs
        $payload = ['items' => []];
        foreach ($return->returnItems()->orderBy('id', 'asc')->get() as $returnItem) {
            $payload['items'][$returnItem->id] = [
                'quantity_good' => 2,
                'quantity_damaged' => 0,
                'quantity_lost' => 0,
                'condition' => 'good',
            ];
        }

        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.return-tracking'));

        foreach ($items as $item) {
            // Initial stock was 10, dispatched was 2 (current was 8). Returning 2 good brings stock back to 10.
            $this->assertEquals(10, $item->fresh()->current_stock);

            $this->assertDatabaseHas('inventory_transactions', [
                'inventory_item_id' => $item->id,
                'booking_id' => $booking->id,
                'transaction_type' => 'return',
                'quantity_change' => 2,
            ]);
        }

        $this->assertEquals('Completed', $return->fresh()->status);
    }

    public function test_multi_item_return_audit_in_reverse_order_produces_identical_results_to_ascending_order(): void
    {
        [$booking, $return, $items] = $this->createReturnFixtureWithMultipleItems(3);

        // Submit in descending (reversed) order of returnItem IDs
        $payload = ['items' => []];
        foreach ($return->returnItems()->orderBy('id', 'desc')->get() as $returnItem) {
            $payload['items'][$returnItem->id] = [
                'quantity_good' => 2,
                'quantity_damaged' => 0,
                'quantity_lost' => 0,
                'condition' => 'good',
            ];
        }

        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.return-tracking'));

        foreach ($items as $item) {
            $this->assertEquals(10, $item->fresh()->current_stock);

            $this->assertDatabaseHas('inventory_transactions', [
                'inventory_item_id' => $item->id,
                'booking_id' => $booking->id,
                'transaction_type' => 'return',
                'quantity_change' => 2,
            ]);
        }

        $this->assertEquals('Completed', $return->fresh()->status);
    }

    public function test_multi_item_shortage_resolution_in_ascending_order_restocks_and_creates_procurement_transactions(): void
    {
        $booking = $this->createBooking();
        $item1 = $this->createInventoryItem('Item Alpha', 5);
        $item2 = $this->createInventoryItem('Item Beta', 5);
        $item3 = $this->createInventoryItem('Item Gamma', 5);

        $alert = AdminAlert::create([
            'booking_id' => $booking->id,
            'type' => 'shortage',
            'title' => 'Shortage Alert',
            'message' => 'Multiple items short',
            'is_read' => false,
        ]);

        // Submit sorted ascending by item ID
        $sortedIds = collect([$item1->id, $item2->id, $item3->id])->sort()->values()->toArray();
        $stockPayload = [];
        foreach ($sortedIds as $id) {
            $stockPayload[$id] = 10;
        }

        $response = $this->actingAs($this->admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => $stockPayload,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.notifications'));

        $this->assertEquals(15, $item1->fresh()->current_stock);
        $this->assertEquals(15, $item2->fresh()->current_stock);
        $this->assertEquals(15, $item3->fresh()->current_stock);

        foreach ([$item1, $item2, $item3] as $item) {
            $this->assertDatabaseHas('inventory_transactions', [
                'inventory_item_id' => $item->id,
                'booking_id' => $booking->id,
                'transaction_type' => 'procurement',
                'quantity_change' => 10,
            ]);
        }

        $this->assertTrue((bool) $alert->fresh()->is_read);
    }

    public function test_multi_item_shortage_resolution_in_reverse_order_produces_identical_results(): void
    {
        $booking = $this->createBooking();
        $item1 = $this->createInventoryItem('Item Alpha', 5);
        $item2 = $this->createInventoryItem('Item Beta', 5);
        $item3 = $this->createInventoryItem('Item Gamma', 5);

        $alert = AdminAlert::create([
            'booking_id' => $booking->id,
            'type' => 'shortage',
            'title' => 'Shortage Alert',
            'message' => 'Multiple items short',
            'is_read' => false,
        ]);

        // Submit in reverse order of item IDs
        $reverseIds = collect([$item1->id, $item2->id, $item3->id])->sortDesc()->values()->toArray();
        $stockPayload = [];
        foreach ($reverseIds as $id) {
            $stockPayload[$id] = 10;
        }

        $response = $this->actingAs($this->admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => $stockPayload,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.notifications'));

        $this->assertEquals(15, $item1->fresh()->current_stock);
        $this->assertEquals(15, $item2->fresh()->current_stock);
        $this->assertEquals(15, $item3->fresh()->current_stock);

        foreach ([$item1, $item2, $item3] as $item) {
            $this->assertDatabaseHas('inventory_transactions', [
                'inventory_item_id' => $item->id,
                'booking_id' => $booking->id,
                'transaction_type' => 'procurement',
                'quantity_change' => 10,
            ]);
        }

        $this->assertTrue((bool) $alert->fresh()->is_read);
    }

    public function test_multi_item_shortage_resolution_idempotency_safeguard_works_regardless_of_submission_order(): void
    {
        $booking = $this->createBooking();
        $item1 = $this->createInventoryItem('Item Alpha', 5);
        $item2 = $this->createInventoryItem('Item Beta', 5);

        $minId = min($item1->id, $item2->id);
        $maxId = max($item1->id, $item2->id);

        // 1st request: submitted in ascending order
        $this->actingAs($this->admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => [
                $minId => 5,
                $maxId => 8,
            ],
        ]);

        $this->assertEquals(10, InventoryItem::find($minId)->current_stock);
        $this->assertEquals(13, InventoryItem::find($maxId)->current_stock);
        $this->assertEquals(2, InventoryTransaction::where('booking_id', $booking->id)->count());

        // 2nd request: submitted in reverse order within 10 seconds (duplicate)
        $this->actingAs($this->admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => [
                $maxId => 8,
                $minId => 5,
            ],
        ]);

        // Stocks and transaction count must remain unchanged
        $this->assertEquals(10, InventoryItem::find($minId)->current_stock);
        $this->assertEquals(13, InventoryItem::find($maxId)->current_stock);
        $this->assertEquals(2, InventoryTransaction::where('booking_id', $booking->id)->count());
    }

    public function test_multi_item_return_audit_rolls_back_atomically_if_downward_correction_exceeds_available_stock(): void
    {
        [$booking, $return, $items] = $this->createReturnFixtureWithMultipleItems(2);
        $returnItems = $return->returnItems()->orderBy('id', 'asc')->get();

        // Artificially deplete stock of item 2 to 0
        $items[1]->update(['current_stock' => 0]);

        // First establish item 2 as having 2 good returned previously — a prior credit is
        // always recorded in the inventory ledger by the return audit.
        $returnItems[1]->update(['quantity_good' => 2, 'condition' => 'good']);
        InventoryTransaction::create([
            'inventory_item_id' => $items[1]->id,
            'booking_id' => $booking->id,
            'quantity_change' => 2,
            'transaction_type' => 'return',
            'reason' => 'Return tracking update: good',
            'performed_by' => $this->admin->id,
        ]);

        // Now attempt an update: item 1 adds good stock (+2), but item 2 reduces good stock from 2 to 0 (stockDiff = -2),
        // but item 2 currently has 0 stock, so downward correction cannot proceed without negative stock.
        $payload = [
            'items' => [
                $returnItems[0]->id => [
                    'quantity_good' => 2,
                    'condition' => 'good',
                ],
                $returnItems[1]->id => [
                    'quantity_good' => 0,
                    'condition' => 'pending',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), $payload);

        // Should return with error session message
        $response->assertSessionHas('error');

        // Atomicity check: Item 1 should NOT have its stock increased because the entire transaction rolled back
        $this->assertEquals(8, $items[0]->fresh()->current_stock);
        $this->assertEquals(0, $items[1]->fresh()->current_stock);

        // No new return transaction should be saved (only the prior credit remains)
        $this->assertEquals(1, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());
    }

    public function test_authorization_enforced_for_both_endpoints(): void
    {
        $booking = $this->createBooking();
        $item = $this->createInventoryItem('Arch Stand', 10);
        [$bookingWithReturn, $return] = $this->createReturnFixtureWithMultipleItems(1);

        // Client cannot access shortage resolution
        $response1 = $this->actingAs($this->client)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'stock' => [$item->id => 5],
        ]);
        $response1->assertForbidden();

        // Client cannot access return tracking update
        $returnItem = $return->returnItems()->first();
        $response2 = $this->actingAs($this->client)->put(route('admin.return-tracking.update', $return), [
            'items' => [$returnItem->id => ['quantity_good' => 1]],
        ]);
        $response2->assertForbidden();
    }

    public function test_f02_partial_dispatch_reservation_release_preserved_on_return_completion(): void
    {
        $booking = $this->createBooking();
        $booking->update(['status' => 'pending_return', 'confirmed_at' => now()->subDays(5)]);

        // 2 items reserved
        $item1 = $this->createInventoryItem('Arch Stand', 10);
        $item2 = $this->createInventoryItem('Flower Urn', 10);

        // Item 1: reserved 5, dispatched 2
        InventoryTransaction::create([
            'inventory_item_id' => $item1->id,
            'booking_id' => $booking->id,
            'quantity_change' => -5,
            'transaction_type' => 'booking_lock',
            'reason' => 'Booking reservation',
            'performed_by' => $this->admin->id,
        ]);
        InventoryTransaction::create([
            'inventory_item_id' => $item1->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'dispatch',
            'reason' => 'Staff Dispatch',
            'performed_by' => $this->admin->id,
        ]);

        // Item 2: reserved 4, dispatched 0
        InventoryTransaction::create([
            'inventory_item_id' => $item2->id,
            'booking_id' => $booking->id,
            'quantity_change' => -4,
            'transaction_type' => 'booking_lock',
            'reason' => 'Booking reservation',
            'performed_by' => $this->admin->id,
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $item1->id,
            'item_name' => $item1->name,
            'quantity' => 5,
            'quoted_unit_price' => 100,
            'confirmed_at' => now(),
        ]);
        $booking->bookingItems()->create([
            'inventory_item_id' => $item2->id,
            'item_name' => $item2->name,
            'quantity' => 4,
            'quoted_unit_price' => 100,
            'confirmed_at' => now(),
        ]);

        // Manage returns: only dispatched item1 exists as ReturnItem
        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $return = $booking->fresh()->returns()->first();
        $returnItem1 = $return->returnItems()->first();

        // Mark fully returned good
        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem1->id => [
                    'quantity_good' => 2,
                    'condition' => 'good',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Completed', $return->fresh()->status);
        $this->assertEquals('completed', $booking->fresh()->status);

        // Verify F-02: unfulfilled reservations released for both items
        // Item 1: locked 5 - dispatched 2 = 3 unfulfilled released
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item1->id,
            'booking_id' => $booking->id,
            'transaction_type' => 'booking_release',
            'quantity_change' => 3,
        ]);

        // Item 2: locked 4 - dispatched 0 = 4 unfulfilled released
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item2->id,
            'booking_id' => $booking->id,
            'transaction_type' => 'booking_release',
            'quantity_change' => 4,
        ]);
    }

    public function test_f03_dispatch_correction_guard_preserved_after_return_audit(): void
    {
        $booking = $this->createBooking();
        $booking->update(['status' => 'pending_return', 'confirmed_at' => now()->subDays(5)]);
        $item = $this->createInventoryItem('Lantern Frame', 10);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -5,
            'transaction_type' => 'booking_lock',
            'performed_by' => $this->admin->id,
        ]);
        $dispatchTx = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -5,
            'transaction_type' => 'dispatch',
            'performed_by' => $this->admin->id,
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 5,
            'confirmed_at' => now(),
        ]);

        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        // Complete return audit
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 5,
                    'condition' => 'good',
                ],
            ],
        ]);

        $this->assertEquals('Completed', $return->fresh()->status);

        // Attempting dispatch correction now must fail due to F-03 guard
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot correct dispatch: booking');

        app(InventoryDispatchService::class)->correctDispatch(
            $dispatchTx,
            2.0,
            $this->admin->id,
            'Attempted correction after return audit'
        );
    }

    private function createBooking(): Booking
    {
        return Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'pending',
            'total_quoted' => 0,
        ]);
    }

    private function createInventoryItem(string $name, float $stock = 10): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => $stock,
            'unit_cost' => 100,
            'min_stock' => 1,
            'unit' => 'piece',
        ]);
    }

    private function createReturnFixtureWithMultipleItems(int $count = 3): array
    {
        $booking = $this->createBooking();
        $booking->update(['status' => 'pending_return', 'confirmed_at' => now()->subDays(5)]);

        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $item = $this->createInventoryItem("Material Item #{$i}", 8); // current stock 8 (since 2 dispatched from 10)
            $items[] = $item;

            $booking->bookingItems()->create([
                'inventory_item_id' => $item->id,
                'item_name' => $item->name,
                'quantity' => 2,
                'quoted_unit_price' => 100,
                'confirmed_at' => now(),
            ]);

            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'booking_id' => $booking->id,
                'quantity_change' => -2,
                'transaction_type' => 'dispatch',
                'reason' => 'Admin Dispatch',
                'performed_by' => $this->admin->id,
            ]);
        }

        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $return = $booking->fresh()->returns()->first();

        return [$booking->fresh(), $return, $items];
    }
}

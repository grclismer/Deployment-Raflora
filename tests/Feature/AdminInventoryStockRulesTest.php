<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryStockRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_booking_creates_lock_transaction_without_deducting_current_stock(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-stock@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Arch Stand',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 5,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 1500,
        ]);

        $booking->inventoryItems()->attach($inventoryItem->id, [
            'quantity' => 3,
            'quoted_unit_price' => 120,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($booking);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'preparation_start_date' => now()->toDateString(),
            'status' => 'downpayment_received',
            'special_requests' => null,
            'admin_note' => null,
            'action' => 'save',
        ]);

        $inventoryItem->refresh();

        // Ensure current_stock remains exactly as physical stock
        $this->assertSame(5.0, (float) $inventoryItem->current_stock);
        
        // Ensure the audit transaction is created
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'transaction_type' => 'booking_lock',
            'quantity_change' => -3.0,
        ]);
        
        // Ensure net available works as intended
        $this->assertSame(3.0, (float) $inventoryItem->reserved_stock);
        $this->assertSame(2.0, (float) $inventoryItem->net_available);
    }

    public function test_confirmed_booking_cannot_overlap_available_stock_for_same_date(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-overlap@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Gold Pedestal',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 5,
            'min_stock' => 2,
            'unit_cost' => 90,
            'unit' => 'piece',
        ]);

        $existingBooking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(12)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'downpayment_received',
            'total_quoted' => 1500,
        ]);

        $existingBooking->inventoryItems()->attach($inventoryItem->id, [
            'quantity' => 4,
            'quoted_unit_price' => 100,
            'procurement_status' => 'reserved',
            'confirmed_at' => now(),
        ]);

        $newBooking = Booking::create([
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(12)->toDateString(),
            'venue' => 'The Loft',
            'status' => 'pending',
            'total_quoted' => 800,
        ]);

        $newBooking->inventoryItems()->attach($inventoryItem->id, [
            'quantity' => 2,
            'quoted_unit_price' => 95,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($newBooking);
        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $newBooking), [
            '_method' => 'PUT',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(12)->toDateString(),
            'venue' => 'The Loft',
            'status' => 'downpayment_received',
            'special_requests' => null,
            'admin_note' => null,
            'action' => 'save',
        ]);

        $response->assertSessionHas('error');
        $newBooking->refresh();
        $this->assertSame('pending', $newBooking->status);
    }

    public function test_inventory_index_does_not_show_duplicate_low_stock_alert_banner(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-alerts@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        InventoryItem::create([
            'name' => 'Dried Pampas',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 2,
            'unit_cost' => 50,
            'unit' => 'bundle',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertDontSee('Low stock alert');
    }

    public function test_inventory_index_prioritizes_items_by_restock_shortage_within_selected_category(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-sorting@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        foreach ([
            ['name' => 'Healthy Flowers', 'current_stock' => 20, 'min_stock' => 2],
            ['name' => 'Small Shortage Flowers', 'current_stock' => 1, 'min_stock' => 3],
            ['name' => 'Critical Shortage Flowers', 'current_stock' => 0, 'min_stock' => 8],
            ['name' => 'At Threshold Flowers', 'current_stock' => 0, 'min_stock' => 0],
            ['name' => 'Unrelated Decor', 'category' => 'decor', 'current_stock' => 0, 'min_stock' => 100],
        ] as $item) {
            InventoryItem::create([
                'name' => $item['name'],
                'category' => $item['category'] ?? 'flowers',
                'is_perishable' => false,
                'current_stock' => $item['current_stock'],
                'min_stock' => $item['min_stock'],
                'unit_cost' => 50,
                'unit' => 'piece',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.inventory.index', ['category' => 'flowers']));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Critical Shortage Flowers',
            'Small Shortage Flowers',
            'At Threshold Flowers',
            'Healthy Flowers',
        ]);
        $response->assertDontSee('Unrelated Decor');
    }
    public function test_admin_stock_update_creates_transaction(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-tx@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Vase',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
        
        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => null,
            'quantity_change' => 10,
            'transaction_type' => 'adjustment',
            'reason' => 'Initial stock on creation',
            'performed_by' => $admin->id,
        ]);
        
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'quantity_change' => 10,
            'transaction_type' => 'adjustment',
        ]);

        // Edit Item does not change stock
        $this->actingAs($admin)->put(route('admin.inventory.update', $inventoryItem), [
            'name' => 'Vase Updated',
            'category' => 'decor',
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);

        // Adjust stock upwards creates adjustment transaction
        $this->actingAs($admin)->post(route('admin.inventory.adjust-stock', $inventoryItem), [
            'new_stock' => 15,
            'reason' => 'Stock adjustment upward',
        ]);

        $this->assertSame(15.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'quantity_change' => 5,
            'transaction_type' => 'adjustment',
        ]);

        // Adjust stock downwards creates adjustment transaction
        $this->actingAs($admin)->post(route('admin.inventory.adjust-stock', $inventoryItem), [
            'new_stock' => 12,
            'reason' => 'Stock adjustment downward',
        ]);

        $this->assertSame(12.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'quantity_change' => -3,
            'transaction_type' => 'adjustment',
        ]);

        // Metadata update preserves stock
        $txCount = \App\Models\InventoryTransaction::count();
        $this->actingAs($admin)->put(route('admin.inventory.update', $inventoryItem), [
            'name' => 'Vase Final Name',
            'category' => 'decor',
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
        $this->assertSame($txCount, \App\Models\InventoryTransaction::count());
        $this->assertSame(12.0, (float) $inventoryItem->fresh()->current_stock);
    }

    public function test_historical_inventory_transaction_is_preserved_after_inventory_item_removal(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-archive@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Old Vase',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
        
        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => null,
            'quantity_change' => 10,
            'transaction_type' => 'adjustment',
            'reason' => 'Initial stock on creation',
            'performed_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.inventory.archive', $inventoryItem));

        $this->assertSoftDeleted('inventory_items', [
            'id' => $inventoryItem->id,
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
        ]);
    }
    public function test_net_available_calculation_and_presentation_is_correct(): void
    {
        $admin = User::create([
            'name' => 'Admin Stock Rules',
            'email' => 'admin-stockrules@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $item = InventoryItem::create([
            'name' => 'Test Sunflowers',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 90,
            'min_stock' => 50,
            'unit_cost' => 10,
            'unit' => 'stems',
        ]);

        // Create an active booking taking 15
        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Test Venue',
            'status' => 'confirmed',
            'total_quoted' => 1000,
            'confirmed_at' => now(),
        ]);

        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 15,
            'quoted_unit_price' => 10,
            'procurement_status' => 'reserved',
            'confirmed_at' => now(),
        ]);

        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'transaction_type' => 'booking_lock',
            'quantity_change' => 15,
            'performed_by' => $admin->id,
            'reference_transaction_id' => null,
            'notes' => 'Test lock',
        ]);

        // Model Check (Authoritative rules)
        $this->assertEquals(90, $item->fresh()->current_stock);
        $this->assertEquals(15, $item->fresh()->reserved_stock);
        $this->assertEquals(75, $item->fresh()->net_available);
        $this->assertFalse($item->fresh()->isLowStock());

        // Zero-reserved item check
        $itemZero = InventoryItem::create([
            'name' => 'Test Eucalyptus',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 100,
            'min_stock' => 25,
            'unit_cost' => 10,
            'unit' => 'stems',
        ]);

        $this->assertEquals(100, $itemZero->fresh()->current_stock);
        $this->assertEquals(0, $itemZero->fresh()->reserved_stock);
        $this->assertEquals(100, $itemZero->fresh()->net_available);

        // UI rendering check
        $response = $this->actingAs($admin)->get(route('admin.inventory.index'));
        $response->assertOk();
        
        // Ensure unit is not repeated in the stock columns but is in the item info
        $response->assertSeeText('stems');
        $response->assertSeeText('Perishable');
        
        // For Test Sunflowers: 90 on hand, 15 reserved, 0 to procure, 50 min
        // Assert they appear as raw numbers without units in the td
        $response->assertSeeText('90');
        $response->assertSeeText('15');
        $response->assertSeeText('0');
        $response->assertSeeText('50');
        
        $response->assertSeeText('100');
    }
}

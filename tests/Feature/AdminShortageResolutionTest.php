<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShortageResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_inline_shortage_resolution_increases_stock_and_creates_transaction()
    {
        $admin = $this->user('admin');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10); // current stock 10

        $response = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(15, $inventoryItem->fresh()->current_stock);
        
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'quantity_change' => 5,
            'transaction_type' => 'procurement',
            'reason' => 'Admin inline restock',
            'performed_by' => $admin->id,
        ]);
        
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_valid_booking_shortage_resolution_creates_transaction_and_idempotency_safeguard()
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);

        $alert = AdminAlert::create([
            'booking_id' => $booking->id,
            'type' => 'shortage',
            'title' => 'Shortage',
            'message' => 'Shortage',
            'is_read' => false,
        ]);

        // 1st request
        $response = $this->actingAs($admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => [
                $inventoryItem->id => 10,
            ]
        ]);

        $response->assertRedirect(route('admin.notifications'));
        $this->assertEquals(20, $inventoryItem->fresh()->current_stock);
        $this->assertTrue((bool) $alert->fresh()->is_read);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => 10,
            'transaction_type' => 'procurement',
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);

        // 2nd request (duplicate submission within 10 seconds)
        $response2 = $this->actingAs($admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => [
                $inventoryItem->id => 10,
            ]
        ]);

        // Should NOT error, but simply ignore the duplicate silently and redirect
        $response2->assertRedirect(route('admin.notifications'));
        $response2->assertSessionHasNoErrors();
        
        // Stock should remain 20 (not incremented again)
        $this->assertEquals(20, $inventoryItem->fresh()->current_stock);
        // Only 1 transaction still exists for this item/booking/quantity combo
        $this->assertDatabaseCount('inventory_transactions', 1);

        // 3rd request (legitimate subsequent shortage resolution outside window or different quantity)
        // Advance time to bypass 10-second window
        $this->travel(11)->seconds();

        $response3 = $this->actingAs($admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => [
                $inventoryItem->id => 5, // New shortage restock
            ]
        ]);

        $response3->assertRedirect(route('admin.notifications'));
        $this->assertEquals(25, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_read_alert_with_unresolved_shortage_can_still_be_resolved()
    {
        $admin = $this->user('admin');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);
        $booking = $this->booking();

        // Create an already read alert
        $alert = \App\Models\AdminAlert::create([
            'booking_id' => $booking->id,
            'alert_type' => 'booking_shortage',
            'type' => 'booking_shortage',
            'title' => 'Shortage',
            'message' => 'Shortage',
            'is_read' => true,
        ]);

        // Submit resolution
        $response = $this->actingAs($admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'action' => 'restock_and_resolve',
            'stock' => [
                $inventoryItem->id => 15,
            ]
        ]);

        $response->assertRedirect(route('admin.notifications'));
        $this->assertEquals(25, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_unauthorized_user_cannot_resolve_shortages()
    {
        $staff = $this->user('staff');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);

        // The inline route in web.php uses `auth` and `admin` middleware
        $response = $this->actingAs($staff)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
        ]);

        $response->assertForbidden(); 
        $this->assertEquals(10, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_invalid_quantity_does_not_change_stock_or_create_transaction()
    {
        $admin = $this->user('admin');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);

        $response = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => -5,
        ]);

        $response->assertSessionHasErrors('additional_stock');
        $this->assertEquals(10, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_rapid_repeated_inline_shortage_resolution_does_not_create_duplicate_procurement()
    {
        $admin = $this->user('admin');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);

        // 1st request
        $response1 = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
        ]);

        $response1->assertSessionHasNoErrors();
        $this->assertEquals(15, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 1);

        // 2nd request (rapid duplicate within 10 seconds)
        $response2 = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
        ]);

        $response2->assertSessionHasNoErrors();
        // Current stock must NOT increase again
        $this->assertEquals(15, $inventoryItem->fresh()->current_stock);
        // Only 1 transaction exists
        $this->assertDatabaseCount('inventory_transactions', 1);

        // 3rd request (subsequent legitimate restock after 10-second window)
        $this->travel(11)->seconds();

        $response3 = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
        ]);

        $response3->assertSessionHasNoErrors();
        $this->assertEquals(20, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_inline_shortage_resolution_with_alert_id_marks_alert_as_read()
    {
        $admin = $this->user('admin');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);

        $alert = AdminAlert::create([
            'type' => 'daily_shortage',
            'title' => 'Shortage Alert',
            'message' => 'Real Vase is low',
            'inventory_item_id' => $inventoryItem->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
            'alert_id' => $alert->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(15, $inventoryItem->fresh()->current_stock);
        $this->assertTrue((bool) $alert->fresh()->is_read);
    }

    public function test_transaction_atomicity_rolls_back_stock_if_transaction_fails()
    {
        $admin = $this->user('admin');
        $inventoryItem = $this->inventoryItem('Real Vase', 10, 10);

        // Simulate database exception during InventoryTransaction creation
        InventoryTransaction::creating(function () {
            throw new \RuntimeException('Simulated database error during transaction');
        });

        $response = $this->actingAs($admin)->post(route('admin.notifications.resolve-shortage', $inventoryItem), [
            'additional_stock' => 5,
        ]);

        $response->assertSessionHas('error');
        // Stock must remain unchanged due to rollback
        $this->assertEquals(10, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    private function booking(): Booking
    {
        return Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 0,
        ]);
    }

    private function inventoryItem(string $name = 'White Roses', float $unitCost = 50, float $stock = 10): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => $stock,
            'unit_cost' => $unitCost,
            'min_stock' => 1,
            'unit' => 'stem',
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
        ]);
    }
}

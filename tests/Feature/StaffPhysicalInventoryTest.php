<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPhysicalInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_staff_can_record_physical_stock_and_transaction_actor(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin');
        $booking = $this->booking($staff->id);
        $inventoryItem = InventoryItem::create([
            'name' => 'Rental Stand',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 3,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);

        // Staff records observed count
        $this->actingAs($staff)
            ->put(route('staff.events.inventory.update', [$booking, $inventoryItem]), ['observed_stock' => 7.5])
            ->assertRedirect();

        // Under Decision A, global current_stock is NOT directly modified by staff
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(3.0, (float) $bookingItem->fresh()->quantity);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 0);

        // An AdminAlert is created for Admin review
        $alert = \App\Models\AdminAlert::where('type', 'physical_count_variance')
            ->where('booking_id', $booking->id)
            ->where('inventory_item_id', $inventoryItem->id)
            ->first();
        $this->assertNotNull($alert);
        $this->assertFalse($alert->is_read);

        // Staff cannot approve adjustment
        $this->actingAs($staff)
            ->post(route('admin.inventory.adjustments.approve', $alert))
            ->assertForbidden();

        // Admin approves adjustment
        $this->actingAs($admin)
            ->post(route('admin.inventory.adjustments.approve', $alert))
            ->assertRedirect();

        // Stock is now updated and transaction is created with Admin as actor
        $this->assertSame(7.5, (float) $inventoryItem->fresh()->current_stock);
        $this->assertTrue($alert->fresh()->is_read);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2.5,
            'transaction_type' => 'adjustment',
            'performed_by' => $admin->id,
        ]);
    }

    public function test_staff_cannot_record_stock_for_another_staff_members_event(): void
    {
        $staffA = $this->user('staff', 'stock-a@example.com');
        $staffB = $this->user('staff', 'stock-b@example.com');
        $booking = $this->booking($staffA->id);
        $inventoryItem = $this->inventoryItem();
        $this->confirmedBookingItem($booking, $inventoryItem);

        $this->actingAs($staffB)
            ->put(route('staff.events.inventory.update', [$booking, $inventoryItem]), ['observed_stock' => 4])
            ->assertNotFound();

        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_invalid_physical_stock_is_rejected_without_mutation(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $inventoryItem = $this->inventoryItem();
        $this->confirmedBookingItem($booking, $inventoryItem);

        $this->actingAs($staff)
            ->put(route('staff.events.inventory.update', [$booking, $inventoryItem]), ['observed_stock' => -1])
            ->assertSessionHasErrors('observed_stock');

        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_unconfirmed_material_cannot_be_counted(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $inventoryItem = $this->inventoryItem();
        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 1,
            'procurement_status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->put(route('staff.events.inventory.update', [$booking, $inventoryItem]), ['observed_stock' => 4])
            ->assertNotFound();
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' User',
            'email' => $email ?? ($role . '-' . uniqid() . '@example.com'),
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function booking(int $staffId): Booking
    {
        return Booking::create([
            'staff_id' => $staffId,
            'event_type' => 'Staff Event',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Raflora Event Venue',
            'status' => 'confirmed',
        ]);
    }

    private function inventoryItem(): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'Rental Stand',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
    }

    private function confirmedBookingItem(Booking $booking, InventoryItem $inventoryItem): BookingItem
    {
        return BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 3,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMaterialReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_staff_can_record_returned_quantity_without_reconciling_inventory(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $inventoryItem = $this->inventoryItem();
        $this->confirmedBookingItem($booking, $inventoryItem, 4);

        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $returnRecord = AssetReturn::where('booking_id', $booking->id)->firstOrFail();
        $returnItem = $returnRecord->returnItems()->firstOrFail();

        $this->actingAs($staff)
            ->put(route('staff.events.return.update', $booking), [
                'items' => [
                    $returnItem->id => [
                        'quantity_returned' => 3,
                        'notes' => 'Three units returned from venue.',
                    ],
                ],
                'notes' => 'Return submitted for Admin review.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('return_items', [
            'id' => $returnItem->id,
            'quantity_returned' => 3,
            'notes' => 'Three units returned from venue.',
        ]);
        $this->assertDatabaseHas('returns', [
            'id' => $returnRecord->id,
            'status' => 'Partially Returned',
            'notes' => 'Return submitted for Admin review.',
        ]);
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_staff_cannot_submit_a_return_for_another_staff_members_event(): void
    {
        $staffA = $this->user('staff', 'return-a@example.com');
        $staffB = $this->user('staff', 'return-b@example.com');
        $booking = $this->booking($staffA->id);
        $inventoryItem = $this->inventoryItem();
        $this->confirmedBookingItem($booking, $inventoryItem, 2);

        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $this->actingAs($staffA)->get(route('staff.events.show', $booking));
        $returnRecord = AssetReturn::where('booking_id', $booking->id)->firstOrFail();
        $returnItem = $returnRecord->returnItems()->firstOrFail();

        $this->actingAs($staffB)
            ->put(route('staff.events.return.update', $booking), [
                'items' => [$returnItem->id => ['quantity_returned' => 1]],
            ])
            ->assertNotFound();
    }

    public function test_return_quantity_cannot_exceed_dispatched_quantity(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $inventoryItem = $this->inventoryItem();
        $this->confirmedBookingItem($booking, $inventoryItem, 2);

        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $returnItem = ReturnItem::whereHas('assetReturn', fn ($query) => $query->where('booking_id', $booking->id))->firstOrFail();

        $this->actingAs($staff)
            ->put(route('staff.events.return.update', $booking), [
                'items' => [$returnItem->id => ['quantity_returned' => 3]],
            ])
            ->assertSessionHasErrors('items.' . $returnItem->id . '.quantity_returned');

        $this->assertSame(0.0, (float) $returnItem->fresh()->quantity_returned);
    }

    public function test_perishable_materials_are_not_added_to_staff_return_records(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $perishable = $this->inventoryItem(true);
        $this->confirmedBookingItem($booking, $perishable, 2);

        $this->actingAs($staff)->get(route('staff.events.show', $booking));

        $this->assertDatabaseCount('returns', 0);
        $this->assertDatabaseCount('return_items', 0);
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
            'status' => 'event_completed',
        ]);
    }

    private function inventoryItem(bool $perishable = false): InventoryItem
    {
        return InventoryItem::create([
            'name' => $perishable ? 'Fresh Flower' : 'Rental Stand',
            'category' => $perishable ? 'flowers' : 'hardware',
            'is_perishable' => $perishable,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
    }

    private function confirmedBookingItem(Booking $booking, InventoryItem $inventoryItem, int $quantity): BookingItem
    {
        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => $quantity,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);

        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$quantity,
            'transaction_type' => 'dispatch',
            'reason' => 'Staff Dispatch',
            'performed_by' => 1,
        ]);

        return $bookingItem;
    }
}

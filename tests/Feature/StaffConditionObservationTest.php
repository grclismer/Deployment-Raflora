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

class StaffConditionObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_damaged_observation_with_evidence(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $staff = $this->user('staff');
        [$booking, $inventoryItem, $returnItem] = $this->returnFixture($staff->id, 2);

        $this->actingAs($staff)->put(route('staff.events.return.update', $booking), [
            'items' => [$returnItem->id => ['quantity_returned' => 2]],
        ]);

        $this->actingAs($staff)
            ->put(route('staff.events.return.condition', $booking), [
                'items' => [$returnItem->id => [
                    'condition' => 'damaged',
                    'notes' => 'Scratches observed on frame.',
                    'evidence' => [\Illuminate\Http\UploadedFile::fake()->create('damage.jpg', 10, 'image/jpeg')]
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('return_items', [
            'id' => $returnItem->id,
            'condition' => 'damaged',
            'damage_charge' => 0,
            'notes' => 'Scratches observed on frame.',
        ]);
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame('event_completed', $booking->fresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_damaged_observation_requires_a_returned_quantity(): void
    {
        $staff = $this->user('staff');
        [$booking, , $returnItem] = $this->returnFixture($staff->id, 0);

        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($staff)
            ->put(route('staff.events.return.condition', $booking), [
                'items' => [$returnItem->id => [
                    'condition' => 'damaged',
                    'evidence' => [\Illuminate\Http\UploadedFile::fake()->create('damage.jpg', 10, 'image/jpeg')]
                ]],
            ])
            ->assertSessionHasErrors('items.' . $returnItem->id . '.condition');

        $this->assertSame('pending', $returnItem->fresh()->condition);
    }

    public function test_staff_cannot_record_condition_for_another_staff_members_return(): void
    {
        $staffA = $this->user('staff', 'condition-a@example.com');
        $staffB = $this->user('staff', 'condition-b@example.com');
        [$booking, , $returnItem] = $this->returnFixture($staffA->id, 1);

        $this->actingAs($staffB)
            ->put(route('staff.events.return.condition', $booking), [
                'items' => [$returnItem->id => ['condition' => 'good']],
            ])
            ->assertNotFound();

        $this->assertSame('pending', $returnItem->fresh()->condition);
    }

    public function test_lost_observation_can_be_recorded_without_returned_quantity(): void
    {
        $staff = $this->user('staff');
        [$booking, , $returnItem] = $this->returnFixture($staff->id, 0);

        $this->actingAs($staff)
            ->put(route('staff.events.return.condition', $booking), [
                'items' => [$returnItem->id => ['condition' => 'lost', 'notes' => 'Not found at venue.']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('return_items', [
            'id' => $returnItem->id,
            'condition' => 'lost',
            'notes' => 'Not found at venue.',
        ]);
    }

    private function returnFixture(int $staffId, int $returnedQuantity): array
    {
        $booking = Booking::create([
            'staff_id' => $staffId,
            'event_type' => 'Staff Event',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Raflora Event Venue',
            'status' => 'event_completed',
        ]);
        $inventoryItem = InventoryItem::create([
            'name' => 'Rental Stand',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 2,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);
        $return = AssetReturn::create(['booking_id' => $booking->id, 'status' => 'Pending', 'total_damage_charge' => 0]);
        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_returned' => $returnedQuantity,
            'condition' => 'pending',
            'damage_charge' => 0,
        ]);

        return [$booking, $inventoryItem, $returnItem];
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
}

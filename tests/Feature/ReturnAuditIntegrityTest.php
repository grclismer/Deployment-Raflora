<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnAuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_returnable_material_initializes_with_authoritative_dispatched_quantity(): void
    {
        [$admin, $booking, $inventoryItem] = $this->fixture();
        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 3,
            'quoted_unit_price' => 100,
            'is_ai_suggested' => true,
            'confirmed_at' => now(),
        ]);
        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -3,
            'transaction_type' => 'dispatch',
            'reason' => 'Admin Dispatch',
            'performed_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.return-tracking.start', $booking));
        $response = $this->actingAs($admin)->get(route('admin.return-tracking.manage', $booking));

        $response->assertOk();
        $returnItem = $booking->fresh()->returns()->first()->returnItems()->first();
        $this->assertNotNull($returnItem);
        $this->assertSame($inventoryItem->id, $returnItem->inventory_item_id);
    }

    public function test_unconfirmed_ai_material_is_not_initialized_as_return_item(): void
    {
        [$admin, $booking, $inventoryItem] = $this->fixture();
        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 3,
            'quoted_unit_price' => 100,
            'is_ai_suggested' => true,
            'confirmed_at' => null,
        ]);

        $this->actingAs($admin)->get(route('admin.return-tracking'))->assertOk();
        $this->assertSame(0, $booking->fresh()->returns()->count());
    }

    public function test_over_return_is_rejected_and_not_persisted(): void
    {
        [$admin, $booking, $inventoryItem, $return] = $this->returnFixture(3);
        $returnItem = $return->returnItems()->first();

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 4,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0.0, (float) $returnItem->fresh()->quantity_returned);
    }

    public function test_good_return_restores_only_valid_quantity_and_repeated_update_does_not_double_restore(): void
    {
        [$admin, $booking, $inventoryItem, $return] = $this->returnFixture(3);
        $returnItem = $return->returnItems()->first();

        $payload = [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 2,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), $payload);
        $this->assertSame(2.0, (float) $inventoryItem->fresh()->current_stock);
        $transaction = InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->first();
        $this->assertNotNull($transaction);
        $this->assertSame($inventoryItem->id, $transaction->inventory_item_id);
        $this->assertSame(2.0, (float) $transaction->quantity_change);
        $this->assertSame('return', $transaction->transaction_type);
        $this->assertSame('Return tracking update: good', $transaction->reason);
        $this->assertSame($admin->id, $transaction->performed_by);

        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), $payload);
        $this->assertSame(2.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(1, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());
    }

    public function test_damaged_and_lost_returns_do_not_restore_good_stock(): void
    {
        [$admin, $booking, $inventoryItem, $return] = $this->returnFixture(3);
        $returnItem = $return->returnItems()->first();

        foreach (['damaged', 'lost'] as $condition) {
            $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
                'items' => [
                    $returnItem->id => [
                        'quantity_returned' => 3,
                        'condition' => $condition,
                        'damage_charge' => 25,
                    ],
                ],
            ]);
        }

        $this->assertSame(0.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(0, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());
    }

    public function test_return_item_from_another_return_cannot_be_manipulated(): void
    {
        [$admin, $booking, $inventoryItem, $return] = $this->returnFixture(3);
        [$otherAdmin, $otherBooking, $otherInventory, $otherReturn] = $this->returnFixture(2);
        $otherReturnItem = $otherReturn->returnItems()->first();

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $otherReturnItem->id => [
                    'quantity_returned' => 1,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(0.0, (float) $otherReturnItem->fresh()->quantity_returned);
    }

    private function fixture(): array
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('return-admin-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'event_completed',
        ]);
        $inventoryItem = InventoryItem::create([
            'name' => 'Rental Frame',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 0,
            'min_stock' => 0,
            'unit_cost' => 100,
            'unit' => 'piece',
        ]);

        return [$admin, $booking, $inventoryItem];
    }

    private function returnFixture(float $quantity): array
    {
        [$admin, $booking, $inventoryItem] = $this->fixture();
        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => $quantity,
            'quoted_unit_price' => 100,
            'is_ai_suggested' => true,
            'confirmed_at' => now(),
        ]);
        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$quantity,
            'transaction_type' => 'dispatch',
            'reason' => 'Admin Dispatch',
            'performed_by' => $admin->id,
        ]);
        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $returnModel = $booking->fresh()->returns()->first();

        return [$admin, $booking->fresh(), $inventoryItem, $returnModel];
    }
}

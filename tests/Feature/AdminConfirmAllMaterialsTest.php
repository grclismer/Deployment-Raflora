<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfirmAllMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_all_confirms_every_linked_ai_material_and_leaves_unmatched_ones(): void
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        $roses = $this->inventoryItem('White Roses');
        $foam = $this->inventoryItem('Floral Foam Blocks');
        $first = $booking->bookingItems()->create(['inventory_item_id' => $roses->id, 'item_name' => $roses->name, 'quantity' => 12, 'quoted_unit_price' => 80, 'is_ai_suggested' => true, 'procurement_status' => 'pending']);
        $second = $booking->bookingItems()->create(['inventory_item_id' => $foam->id, 'item_name' => $foam->name, 'quantity' => 3, 'quoted_unit_price' => 85, 'is_ai_suggested' => true, 'procurement_status' => 'pending']);
        $unmatched = $booking->bookingItems()->create(['item_name' => 'Butterfly Accents', 'quantity' => 5, 'quoted_unit_price' => 0, 'is_ai_suggested' => true, 'procurement_status' => 'pending']);

        $this->actingAs($admin)
            ->from(route('admin.bookings.show', $booking))
            ->post(route('admin.bookings.items.confirm-all', $booking))
            ->assertRedirect(route('admin.bookings.show', $booking))
            ->assertSessionHas('success', '2 materials confirmed for this booking. 1 unmatched suggestion still needs linking or promoting.');

        $this->assertNotNull($first->fresh()->confirmed_at);
        $this->assertNotNull($second->fresh()->confirmed_at);
        $this->assertNull($unmatched->fresh()->confirmed_at);
        // Each item goes through the same steps as the single "Confirm Material" button.
        $this->assertSame(12.0, (float) $booking->fresh()->inventoryItems()->whereKey($roses->id)->first()->pivot->quantity);
        $this->assertTrue($booking->fresh()->inventoryItems()->whereKey($foam->id)->exists());
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'material_confirmed')->where('entity_id', $booking->id)->count());
    }

    public function test_confirm_all_with_nothing_ready_changes_nothing(): void
    {
        $booking = $this->booking();
        $unmatched = $booking->bookingItems()->create(['item_name' => 'Butterfly Accents', 'quantity' => 5, 'is_ai_suggested' => true]);

        $this->actingAs($this->user('admin'))
            ->post(route('admin.bookings.items.confirm-all', $booking))
            ->assertSessionHas('error', 'There are no linked materials waiting for confirmation.');

        $this->assertNull($unmatched->fresh()->confirmed_at);
    }

    public function test_non_admins_cannot_confirm_all(): void
    {
        $booking = $this->booking();
        $item = $booking->bookingItems()->create(['inventory_item_id' => $this->inventoryItem()->id, 'item_name' => 'White Roses', 'quantity' => 1, 'is_ai_suggested' => true]);

        foreach (['staff', 'client'] as $role) {
            $this->actingAs($this->user($role))
                ->post(route('admin.bookings.items.confirm-all', $booking))
                ->assertForbidden();
        }

        $this->assertNull($item->fresh()->confirmed_at);
    }

    public function test_quotation_tab_shows_confirm_all_button_with_ready_count(): void
    {
        $booking = $this->booking();
        $booking->bookingItems()->create(['inventory_item_id' => $this->inventoryItem('White Roses')->id, 'item_name' => 'White Roses', 'quantity' => 2, 'quoted_unit_price' => 0, 'is_ai_suggested' => true]);
        $booking->bookingItems()->create(['inventory_item_id' => $this->inventoryItem('Pink Rose')->id, 'item_name' => 'Pink Rose', 'quantity' => 2, 'quoted_unit_price' => 85, 'is_ai_suggested' => true]);

        $this->actingAs($this->user('admin'))
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('id="confirmAllMaterialsForm"', false)
            ->assertSee('Confirm all linked (2)')
            ->assertSee('1 of them still have a ₱0.00 unit cost.');
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

    private function inventoryItem(string $name = 'White Roses'): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => 'props', 'is_perishable' => false, 'current_stock' => 50, 'unit_cost' => 50, 'min_stock' => 1, 'unit' => 'piece']);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}

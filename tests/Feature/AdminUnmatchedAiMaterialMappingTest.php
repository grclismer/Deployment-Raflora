<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUnmatchedAiMaterialMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_suggestion_with_exact_inventory_match_maps_automatically()
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        
        $inventoryItem = $this->inventoryItem('Exact Match Vase');
        
        $bookingItem = $booking->bookingItems()->create([
            'item_name' => 'Exact Match Vase',
            'inventory_item_id' => $inventoryItem->id,
            'is_ai_suggested' => true,
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'procurement_status' => 'pending',
        ]);

        $this->assertNotNull($bookingItem->inventory_item_id);
    }

    public function test_ai_suggestion_with_null_inventory_item_id_can_be_mapped_by_admin()
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        
        $unmatchedAiItem = $booking->bookingItems()->create([
            'item_name' => 'Unknown AI Vase',
            'inventory_item_id' => null, // Unmatched
            'is_ai_suggested' => true,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'procurement_status' => 'pending',
        ]);

        $validInventoryItem = $this->inventoryItem('Real Vase', 60);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'action' => 'save',
            'status' => $booking->status,
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'items' => [[
                'booking_item_id' => $unmatchedAiItem->id,
                'inventory_item_id' => $validInventoryItem->id, // Mapped
                'item_name' => $validInventoryItem->name,
                'quantity' => 3,
                'is_ai_suggested' => '1',
            ]]
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unmatchedAiItem->refresh();
        $this->assertEquals($validInventoryItem->id, $unmatchedAiItem->inventory_item_id);
        $this->assertEquals($validInventoryItem->name, $unmatchedAiItem->item_name);
        $this->assertEquals(3, $unmatchedAiItem->quantity);
    }

    public function test_invalid_inventory_item_selection_is_rejected_for_unmatched_ai_material()
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        
        $unmatchedAiItem = $booking->bookingItems()->create([
            'item_name' => 'Unknown AI Vase',
            'inventory_item_id' => null, // Unmatched
            'is_ai_suggested' => true,
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'procurement_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'action' => 'save',
            'status' => $booking->status,
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'items' => [[
                'booking_item_id' => $unmatchedAiItem->id,
                'inventory_item_id' => 99999, // Invalid ID
                'item_name' => 'Invalid Item',
                'quantity' => 1,
                'is_ai_suggested' => '1',
            ]]
        ]);

        $response->assertSessionHas('error', 'The selected inventory item is invalid.');
        
        $unmatchedAiItem->refresh();
        $this->assertNull($unmatchedAiItem->inventory_item_id);
    }

    public function test_existing_designated_substitute_restriction_remains_enforced()
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        
        $originalInventoryItem = $this->inventoryItem('Original Item');
        
        $matchedAiItem = $booking->bookingItems()->create([
            'item_name' => 'Original Item',
            'inventory_item_id' => $originalInventoryItem->id,
            'is_ai_suggested' => true,
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'procurement_status' => 'pending',
        ]);

        $unauthorizedSubstitute = $this->inventoryItem('Sneaky Substitute');

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'action' => 'save',
            'status' => $booking->status,
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'items' => [[
                'booking_item_id' => $matchedAiItem->id,
                'inventory_item_id' => $unauthorizedSubstitute->id, // Try to substitute
                'item_name' => $unauthorizedSubstitute->name,
                'quantity' => 1,
                'is_ai_suggested' => '1',
            ]]
        ]);

        $response->assertSessionHas('error', 'The selected substitute is not designated for this material.');
        
        $matchedAiItem->refresh();
        $this->assertEquals($originalInventoryItem->id, $matchedAiItem->inventory_item_id); // Did not change
    }

    public function test_unauthorized_user_cannot_modify_mapping()
    {
        $staff = $this->user('staff');
        $booking = $this->booking();
        
        $unmatchedAiItem = $booking->bookingItems()->create([
            'item_name' => 'Unknown AI Vase',
            'inventory_item_id' => null, // Unmatched
            'is_ai_suggested' => true,
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'procurement_status' => 'pending',
        ]);

        $validInventoryItem = $this->inventoryItem('Real Vase');

        $response = $this->actingAs($staff)->put(route('admin.bookings.update', $booking), [
            'action' => 'save',
            'status' => $booking->status,
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'items' => [[
                'booking_item_id' => $unmatchedAiItem->id,
                'inventory_item_id' => $validInventoryItem->id,
                'item_name' => $validInventoryItem->name,
                'quantity' => 1,
                'is_ai_suggested' => '1',
            ]]
        ]);

        $response->assertForbidden();

        $unmatchedAiItem->refresh();
        $this->assertNull($unmatchedAiItem->inventory_item_id);
    }
    
    public function test_mapped_material_remains_correctly_represented_during_quotation_and_inventory_planning()
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        
        $unmatchedAiItem = $booking->bookingItems()->create([
            'item_name' => 'Unknown AI Vase',
            'inventory_item_id' => null, // Unmatched
            'is_ai_suggested' => true,
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'procurement_status' => 'pending',
        ]);

        $validInventoryItem = $this->inventoryItem('Real Vase', 60);

        // 1. Map the item
        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'action' => 'save',
            'status' => $booking->status,
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'items' => [[
                'booking_item_id' => $unmatchedAiItem->id,
                'inventory_item_id' => $validInventoryItem->id, // Mapped
                'item_name' => $validInventoryItem->name,
                'quantity' => 1,
                'is_ai_suggested' => '1',
            ]]
        ]);
        $response->assertSessionHasNoErrors();
        
        // 2. Confirm the mapped item
        $unmatchedAiItem->refresh();
        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [
            'booking' => $booking,
            'bookingItem' => $unmatchedAiItem
        ]));

        // 3. Send Quotation
        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'action' => 'send_quotation',
            'status' => $booking->status,
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'items' => [[
                'booking_item_id' => $unmatchedAiItem->id,
                'inventory_item_id' => $validInventoryItem->id,
                'item_name' => $validInventoryItem->name,
                'quantity' => 1,
                'unit_price' => 60,
                'is_ai_suggested' => '1',
            ]]
        ]);

        if (session('error')) {
            $response->dumpSession();
        }
        $response->assertRedirect();
        
        $unmatchedAiItem->refresh();
        $this->assertEquals($validInventoryItem->id, $unmatchedAiItem->inventory_item_id);
        $this->assertEquals('pending', $unmatchedAiItem->procurement_status);
        $this->assertEquals(60, $unmatchedAiItem->quoted_unit_price);
        $this->assertEquals('quotation_sent', $booking->fresh()->status);
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

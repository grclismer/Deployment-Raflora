<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingReviewAiSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_booking_review_displays_unmatched_ai_suggestions_with_action_buttons(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-ai-review@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => null,
            'item_name' => 'Mystery Orchid',
            'quantity' => 4,
            'quoted_unit_price' => 120,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending',
            'notes' => 'AI suggested',
        ]);

        InventoryItem::create([
            'name' => 'Orchid',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'stem',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Unmatched / AI Suggestion');
        $response->assertSee('Link to Existing Inventory');
        $response->assertSee('Promote to Catalog');
    }

    public function test_admin_booking_item_quantity_and_unit_price_changes_persist(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-item-persist@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $bookingItem = $booking->bookingItems()->create([
            'inventory_item_id' => null,
            'item_name' => 'Mystery Orchid',
            'quantity' => 4,
            'quoted_unit_price' => 120,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending',
            'notes' => 'AI suggested',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Please adjust the orchid quantity.',
            'action' => 'send_note',
            'items' => [
                [
                    'booking_item_id' => $bookingItem->id,
                    'item_name' => 'Mystery Orchid',
                    'quantity' => 2,
                    'unit_price' => '95.00',
                ],
            ],
        ]);

        $response->assertRedirect();
        $bookingItem->refresh();

        $this->assertSame(2.0, (float) $bookingItem->quantity);
        $this->assertSame(95.00, (float) $bookingItem->quoted_unit_price);
    }

    public function test_admin_booking_unlinked_ai_suggestion_removal_persists_in_analysis(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-unlinked-ai@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $booking->aiAnalyses()->create([
            'raw_gemini_response' => json_encode(['test' => true]),
            'suggested_materials' => [
                [
                    'item_name' => 'Wet Floral Foam',
                    'estimated_quantity' => 2,
                    'estimated_unit_cost_php' => 40,
                ],
            ],
            'analyzed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Removing foam item.',
            'action' => 'send_note',
            'items' => [
                [
                    'item_name' => 'Wet Floral Foam',
                    'quantity' => 2,
                    'unit_price' => '40.00',
                    'remove' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $latestAnalysis = $booking->aiAnalyses()->latest('analyzed_at')->first();

        $this->assertNotNull($latestAnalysis);
        $this->assertSame([], $latestAnalysis->suggested_materials);
    }

    public function test_admin_ai_material_forms_include_booking_item_id_for_resolution(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-ai-form-contract@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $bookingItem = $booking->bookingItems()->create([
            'inventory_item_id' => null,
            'item_name' => 'Mystery Orchid',
            'quantity' => 4,
            'quoted_unit_price' => 120,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending',
            'notes' => 'AI suggested',
        ]);

        InventoryItem::create([
            'name' => 'Orchid',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 100,
            'unit' => 'stem',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('name="booking_item_id"', false);
        $response->assertSee('name="link_inventory_item_id"', false);
        $response->assertSee('name="catalog_name"', false);
        $this->assertNotNull($bookingItem->fresh());
    }
}

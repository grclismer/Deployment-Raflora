<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCompletionPrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $client;
    protected Booking $booking;
    protected InventoryItem $inventoryItem;
    protected BookingItem $bookingItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->client = User::factory()->create(['role' => 'client']);
        
        $clientRecord = Client::create([
            'email' => $this->client->email,
            'full_name' => $this->client->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);

        $this->inventoryItem = InventoryItem::create([
            'name' => 'Table Centerpiece',
            'category' => 'prop',
            'is_perishable' => false,
            'current_stock' => 50,
            'unit_cost' => 100,
            'unit' => 'piece',
            'min_stock' => 5,
        ]);

        $this->booking = Booking::create([
            'client_id' => $clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Main Hall',
            'status' => 'event_in_progress',
            'confirmed_at' => now(),
            'total_quoted' => 1000,
            'remaining_balance' => 0,
        ]);

        $this->bookingItem = BookingItem::create([
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'item_name' => 'Table Centerpiece',
            'quantity' => 10,
            'quoted_unit_price' => 100,
            'confirmed_at' => now(),
        ]);
        
        // Setup initial lock transaction to simulate booking confirmation
        InventoryTransaction::create([
            'inventory_item_id' => $this->inventoryItem->id,
            'booking_id' => $this->booking->id,
            'quantity_change' => -10,
            'transaction_type' => 'booking_lock',
            'performed_by' => $this->admin->id,
            'reason' => 'Booking lock',
        ]);
    }

    public function test_event_completion_without_dispatch_is_prevented()
    {
        // Missing dispatch entirely
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_completed',
            'action' => 'save',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot transition to completion state: no materials have been dispatched.');
        $this->assertEquals('event_in_progress', $this->booking->fresh()->status);
        
        // Also test with mark_event_completed action
        $responseAction = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);
        
        $responseAction->assertRedirect();
        $responseAction->assertSessionHas('error', 'Cannot mark event completed: no materials have been dispatched.');
        $this->assertEquals('event_in_progress', $this->booking->fresh()->status);
    }

    public function test_event_completion_with_valid_dispatch_succeeds()
    {
        // Full dispatch
        $dispatchService = new \App\Services\InventoryDispatchService();
        $dispatchService->dispatchItems($this->booking, [
            ['inventory_item_id' => $this->inventoryItem->id, 'quantity' => 10]
        ], $this->admin->id, 'Full dispatch');

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        // With remaining balance > 0, it transitions to event_completed
        $this->assertEquals('event_completed', $this->booking->fresh()->status);
    }
    
    public function test_event_completion_with_partial_dispatch_succeeds()
    {
        // Partial dispatch (e.g. 2 out of 10)
        $dispatchService = new \App\Services\InventoryDispatchService();
        $dispatchService->dispatchItems($this->booking, [
            ['inventory_item_id' => $this->inventoryItem->id, 'quantity' => 2]
        ], $this->admin->id, 'Partial dispatch');

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_completed',
            'action' => 'save',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals('event_completed', $this->booking->fresh()->status);
    }
    
    public function test_event_completion_with_no_required_materials_succeeds()
    {
        // Remove all required materials
        $this->bookingItem->delete();
        
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals('event_completed', $this->booking->fresh()->status);
    }
    
    public function test_unauthorized_users_cannot_trigger_completion()
    {
        // Full dispatch
        $dispatchService = new \App\Services\InventoryDispatchService();
        $dispatchService->dispatchItems($this->booking, [
            ['inventory_item_id' => $this->inventoryItem->id, 'quantity' => 10]
        ], $this->admin->id, 'Full dispatch');

        // Client attempting to update
        $responseClient = $this->actingAs($this->client)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_completed',
            'action' => 'save',
        ]);
        
        $responseClient->assertForbidden();
        
        // Staff attempting to use admin update
        $responseStaff = $this->actingAs($this->staff)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_completed',
            'action' => 'save',
        ]);
        
        $this->assertTrue($responseStaff->isForbidden() || $responseStaff->isRedirect());
        
        $this->assertEquals('event_in_progress', $this->booking->fresh()->status);
    }
    
    public function test_completion_does_not_prematurely_mark_return_reconciliation_complete()
    {
        // Set outstanding balance so it stops at event_completed
        $this->booking->update(['remaining_balance' => 500]);
        
        $dispatchService = new \App\Services\InventoryDispatchService();
        $dispatchService->dispatchItems($this->booking, [
            ['inventory_item_id' => $this->inventoryItem->id, 'quantity' => 10]
        ], $this->admin->id, 'Full dispatch');

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_completed',
        ]);

        $response->assertRedirect();
        
        $booking = $this->booking->fresh();
        $this->assertEquals('event_completed', $booking->status);
        
        // Test log_final_payment transition to verify it doesn't skip pending_resolution
        $booking->returns()->create(['status' => 'Pending']); // Dummy return
        $returnItem = $booking->returns()->first()->returnItems()->create([
            'booking_item_id' => $this->bookingItem->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity_returned' => 10,
            'condition' => 'damaged',
            'charge_decision' => 'pending',
        ]);
        
        $paymentResponse = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => $this->booking->event_type,
            'event_date' => $this->booking->event_date->toDateString(),
            'venue' => $this->booking->venue,
            'status' => $booking->status,
            'action' => 'log_final_payment',
        ]);
        
        $paymentResponse->assertRedirect();
        $this->assertEquals('pending_resolution', $this->booking->fresh()->status);
    }
}

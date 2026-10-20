<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Package;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Services\QuotationIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class PackageBookingQuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_booking_items_are_confirmed_and_can_be_quoted()
    {
        $user = User::factory()->create();
        $client = Client::create([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);
        
        $package = Package::create([
            'title' => 'Test Package XYZ',
            'price' => 5000.00,
            'is_active' => true,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Rose',
            'category' => 'floral',
            'current_stock' => 100,
            'unit_cost' => 50.00,
            'min_stock' => 10,
            'unit' => 'stem',
        ]);

        $package->inventoryItems()->attach($inventoryItem->id, ['quantity' => 10]);

        $eventDate = Carbon::now()->addDays(14)->toDateString();
        
        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Manila',
            'guest_count' => 150,
        ]);

        $booking = Booking::with('bookingItems')->first();
        $this->assertNotNull($booking);
        $this->assertCount(1, $booking->bookingItems);
        
        $item = $booking->bookingItems->first();
        $this->assertFalse((bool)$item->is_ai_suggested);
        $this->assertNotNull($item->confirmed_at);
        $this->assertEquals($inventoryItem->id, $item->inventory_item_id);

        // Test quotation issuance
        $booking->update(['status' => 'pending']);
        $booking->final_quoted_price = 5000.00;
        $booking->save();
        
        $admin = User::factory()->create(['role' => 'admin']);
        
        $service = app(QuotationIssuanceService::class);
        $quotation = $service->issue($booking, $admin->id);
        
        $this->assertNotNull($quotation);
        $this->assertEquals(5000.00, $quotation->final_quoted_price);

    }
}

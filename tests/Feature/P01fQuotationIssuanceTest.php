<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P01fQuotationIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin()
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
    }

    private function createBooking()
    {
        return Booking::create([
            'event_type' => 'wedding',
            'event_date' => '2026-12-01',
            'status' => 'pending',
            'downpayment_amount' => 0,
            'multiplier' => 2,
            'labor_method' => 'fixed',
            'labor_rate' => 50,
        ]);
    }

    public function test_valid_booking_can_issue_quotation()
    {
        $admin = $this->createAdmin();
        $booking = $this->createBooking();
        
        $item = InventoryItem::create(['name' => 'Rose', 'unit_cost' => 10, 'quantity' => 100, 'category' => 'flowers']);
        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => 'Rose',
            'quantity' => 10,
            'quoted_unit_price' => 10,
            'is_ai_suggested' => false,
            'confirmed_at' => now(),
        ]);

        $service = app(QuotationIssuanceService::class);
        $quotation = $service->issue($booking, $admin->id);

        $this->assertDatabaseHas('quotations', [
            'booking_id' => $booking->id,
            'version' => 1,
            'status' => 'issued',
            'final_quoted_price' => 250, // 10*10 = 100 * 2 = 200 + 50 = 250
        ]);

        $this->assertEquals(1, $quotation->version);
        $this->assertIsArray($quotation->items_snapshot);
        $this->assertCount(1, $quotation->items_snapshot);
        $this->assertEquals(10, $quotation->items_snapshot[0]['quoted_unit_price']);
    }

    public function test_ai_unreviewed_material_cannot_be_issued()
    {
        $admin = $this->createAdmin();
        $booking = $this->createBooking();
        
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'Unknown',
            'quantity' => 10,
            'ai_recommended_price' => 50,
            'quoted_unit_price' => 0, // Unreviewed AI price
            'is_ai_suggested' => true,
            'confirmed_at' => null,
        ]);

        $service = app(QuotationIssuanceService::class);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot issue a quotation: no confirmed materials exist for this booking.');
        
        $service->issue($booking, $admin->id);
    }

    public function test_immutability_of_issued_quotation()
    {
        $admin = $this->createAdmin();
        $booking = $this->createBooking();
        
        $item = InventoryItem::create(['name' => 'Rose', 'unit_cost' => 10, 'quantity' => 100, 'category' => 'flowers']);
        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => 'Rose',
            'quantity' => 10,
            'quoted_unit_price' => 10,
            'is_ai_suggested' => false,
            'confirmed_at' => now(),
        ]);

        $service = app(QuotationIssuanceService::class);
        $quotation = $service->issue($booking, $admin->id);

        $this->assertEquals(250, $quotation->final_quoted_price); // 100 * 2 = 200 + 50

        // Mutate booking
        $bookingItem->update(['quoted_unit_price' => 20]);
        $booking->update(['multiplier' => 4]);

        $quotation->refresh();
        $this->assertEquals(250, $quotation->final_quoted_price);
        $this->assertEquals(10, $quotation->items_snapshot[0]['quoted_unit_price']);
    }

    public function test_reissue_creates_new_version_and_supersedes_old()
    {
        $admin = $this->createAdmin();
        $booking = $this->createBooking();
        
        $item = InventoryItem::create(['name' => 'Rose', 'unit_cost' => 10, 'quantity' => 100, 'category' => 'flowers']);
        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => 'Rose',
            'quantity' => 10,
            'quoted_unit_price' => 10,
            'is_ai_suggested' => false,
            'confirmed_at' => now(),
        ]);

        $service = app(QuotationIssuanceService::class);
        $v1 = $service->issue($booking, $admin->id);

        $this->assertEquals(1, $v1->version);
        $this->assertEquals('issued', $v1->status);

        // Reissue
        $v2 = $service->issue($booking, $admin->id);

        $v1->refresh();
        $this->assertEquals('superseded', $v1->status);
        
        $this->assertEquals(2, $v2->version);
        $this->assertEquals('issued', $v2->status);
    }
}

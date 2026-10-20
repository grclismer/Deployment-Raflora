<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Quotation;
use App\Models\Payment;
use App\Models\InventoryItem;

class P01dFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function createBooking()
    {
        return Booking::create([
            'event_type' => 'wedding',
            'event_date' => '2026-12-01',
            'status' => 'pending',
            'downpayment_amount' => 0,
        ]);
    }

    private function createInventoryItem()
    {
        return InventoryItem::create([
            'item_name' => 'Rose',
            'category' => 'floral',
            'unit_cost' => 10,
        ]);
    }

    public function test_booking_can_store_labor_fields()
    {
        $booking = $this->createBooking();
        $booking->update([
            'labor_method' => 'percentage',
            'labor_rate' => 15.5,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'labor_method' => 'percentage',
            'labor_rate' => 15.5,
        ]);
        
        $this->assertEquals('percentage', $booking->labor_method);
        $this->assertEquals(15.5, $booking->labor_rate);
    }

    public function test_booking_item_can_store_ai_recommended_price()
    {
        $booking = $this->createBooking();
        $inventoryItem = $this->createInventoryItem();

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => 'Rose',
            'quantity' => 10,
            'quoted_unit_price' => 50,
            'ai_recommended_price' => 55.50,
            'is_ai_suggested' => true,
        ]);

        $this->assertDatabaseHas('booking_items', [
            'id' => $bookingItem->id,
            'ai_recommended_price' => 55.50,
        ]);

        $this->assertEquals(55.50, $bookingItem->ai_recommended_price);
    }

    public function test_quotation_stores_new_p01d_fields_and_json_snapshot()
    {
        $booking = $this->createBooking();
        $snapshot = [
            ['name' => 'Item 1', 'qty' => 2, 'price' => 100],
            ['name' => 'Item 2', 'qty' => 1, 'price' => 50],
        ];

        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'raw_materials_sum' => 250.00,
            'multiplier' => 2.5,
            'labor_method' => 'fixed',
            'labor_rate' => 100.00,
            'labor_amount' => 100.00,
            'final_quoted_price' => 725.00,
            'items_snapshot' => $snapshot,
        ]);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'version' => 1,
            'raw_materials_sum' => 250.00,
            'multiplier' => 2.5,
            'labor_method' => 'fixed',
            'final_quoted_price' => 725.00,
        ]);

        $this->assertIsArray($quotation->items_snapshot);
        $this->assertCount(2, $quotation->items_snapshot);
        $this->assertEquals('Item 1', $quotation->items_snapshot[0]['name']);
        
        $this->assertEquals(1, $quotation->version);
    }

    public function test_payment_can_reference_quotation()
    {
        $booking = $this->createBooking();
        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'quotation_id' => $quotation->id,
            'amount' => 500,
            'payment_type' => 'downpayment',
            'payment_option' => 'gcash',
            'reference_number' => 'REF-12345',
            'amount_paid' => 500,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'quotation_id' => $quotation->id,
        ]);

        $this->assertTrue($payment->quotation->is($quotation));
    }
}

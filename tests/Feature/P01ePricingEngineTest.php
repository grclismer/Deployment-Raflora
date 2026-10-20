<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Services\QuotationPricingService;

class P01ePricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private QuotationPricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new QuotationPricingService();
    }

    private function createBooking()
    {
        return Booking::create([
            'event_type' => 'wedding',
            'event_date' => '2026-12-01',
            'status' => 'pending',
            'downpayment_amount' => 0,
        ]);
    }

    public function test_single_material_subtotal()
    {
        $booking = $this->createBooking();
        BookingItem::create([
            'booking_id' => $booking->id,
            'quantity' => 2,
            'quoted_unit_price' => 50, // 100 total
            'item_name' => 'Rose',
        ]);

        $this->pricingService->calculateTotals($booking);
        $this->assertEquals(100.0, $booking->raw_materials_sum);
    }

    public function test_multiple_material_subtotal_with_multiplier()
    {
        $booking = $this->createBooking();
        $booking->update(['multiplier' => 2.0]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'item_name' => 'Rose',
        ]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'quantity' => 1,
            'quoted_unit_price' => 200,
            'item_name' => 'Vase',
        ]);

        $this->pricingService->calculateTotals($booking);
        
        $this->assertEquals(300.0, $booking->raw_materials_sum);
        $this->assertEquals(600.0, $booking->final_quoted_price); // 300 * 2.0
    }

    public function test_labor_percentage_calculation_applies_correctly()
    {
        $booking = $this->createBooking();
        $booking->update([
            'multiplier' => 2.0,
            'labor_method' => 'percentage',
            'labor_rate' => 10,
        ]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'quantity' => 1,
            'quoted_unit_price' => 1000,
            'item_name' => 'Setup',
        ]);

        $this->pricingService->calculateTotals($booking);

        $this->assertEquals(1000.0, $booking->raw_materials_sum);
        // (1000 * 2.0) + (1000 * 10%) = 2000 + 100 = 2100
        $this->assertEquals(2100.0, $booking->final_quoted_price);
    }

    public function test_labor_fixed_calculation_applies_correctly()
    {
        $booking = $this->createBooking();
        $booking->update([
            'multiplier' => 3.0,
            'labor_method' => 'fixed',
            'labor_rate' => 500,
        ]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'quantity' => 1,
            'quoted_unit_price' => 1000,
            'item_name' => 'Setup',
        ]);

        $this->pricingService->calculateTotals($booking);

        $this->assertEquals(1000.0, $booking->raw_materials_sum);
        // (1000 * 3.0) + 500 = 3500
        $this->assertEquals(3500.0, $booking->final_quoted_price);
    }


}

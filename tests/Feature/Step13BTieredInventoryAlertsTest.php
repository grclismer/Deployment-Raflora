<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\AdminAlert;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Step13BTieredInventoryAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure starting clean
        AdminAlert::truncate();
        Booking::query()->delete();
        InventoryItem::query()->delete();
    }

    private function createScenario(string $status, ?int $daysUntilEvent, int $stock, int $required, bool $isRead = false): Booking
    {
        $eventDate = $daysUntilEvent !== null ? Carbon::now()->addDays($daysUntilEvent)->startOfDay() : null;

        $booking = Booking::create([
            'client_id' => null, // Just mock minimum fields
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'status' => $status,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '123456789',
            'total_quoted' => 1000,
            'downpayment' => 500,
            'final_quoted_price' => 1000
        ]);

        $item = InventoryItem::create([
            'name' => 'Test Item',
            'type' => 'Vase',
            'color' => 'White',
            'current_stock' => $stock,
            'reserved_stock' => 0,
            'min_stock' => 1,
            'is_perishable' => false,
            'cost' => 10,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'quantity' => $required,
            'confirmed_at' => now(),
            'item_type' => 'inventory'
        ]);

        if ($isRead) {
            AdminAlert::create([
                'type' => 'inventory_shortage',
                'booking_id' => $booking->id,
                'inventory_item_id' => $item->id,
                'title' => 'Read Alert',
                'message' => 'Test',
                'is_read' => true,
            ]);
        }

        return $booking;
    }

    public function test_28_days_shortage_creates_weekly_alert()
    {
        $this->createScenario('confirmed', 28, 5, 10);
        Artisan::call('inventory:check-tiered-shortages');

        $this->assertDatabaseHas('admin_alerts', ['booking_id' => Booking::first()->id, 'type' => 'inventory_shortage']);
    }

    public function test_27_days_shortage_no_alert()
    {
        $this->createScenario('confirmed', 27, 5, 10);
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertDatabaseMissing('admin_alerts', ['booking_id' => Booking::first()->id]);
    }

    public function test_21_14_7_3_1_days_create_alerts()
    {
        $days = [21, 14, 7, 3, 1];
        foreach ($days as $day) {
            Booking::query()->delete();
            AdminAlert::truncate();
            $this->createScenario('confirmed', $day, 5, 10);
            Artisan::call('inventory:check-tiered-shortages');
            $this->assertDatabaseHas('admin_alerts', ['booking_id' => Booking::first()->id]);
        }
    }

    public function test_event_day_no_pre_event_alert()
    {
        $this->createScenario('confirmed', 0, 5, 10);
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertDatabaseMissing('admin_alerts', ['booking_id' => Booking::first()->id]);
    }

    public function test_sufficient_stock_no_alert()
    {
        $this->createScenario('confirmed', 28, 15, 10);
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertDatabaseMissing('admin_alerts', ['booking_id' => Booking::first()->id]);
    }

    public function test_ineligible_statuses_no_alert()
    {
        $statuses = ['pending', 'quotation_sent', 'cancelled', 'completed'];
        foreach ($statuses as $status) {
            $this->createScenario($status, 28, 5, 10);
        }
        
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertEquals(0, AdminAlert::count());
    }

    public function test_duplicate_unread_alert_is_prevented()
    {
        $this->createScenario('confirmed', 28, 5, 10);
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertEquals(1, AdminAlert::count());
        
        // Run again
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertEquals(1, AdminAlert::count()); // Still 1
    }

    public function test_read_alert_allows_new_alert()
    {
        $this->createScenario('confirmed', 28, 5, 10, true);
        $this->assertEquals(1, AdminAlert::where('is_read', true)->count());
        
        Artisan::call('inventory:check-tiered-shortages');
        
        $this->assertEquals(1, AdminAlert::where('is_read', false)->count());
        $this->assertEquals(2, AdminAlert::count());
    }

    public function test_null_event_date_ignored()
    {
        $this->createScenario('confirmed', null, 5, 10);
        Artisan::call('inventory:check-tiered-shortages');
        $this->assertEquals(0, AdminAlert::count());
    }
}

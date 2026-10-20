<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\InventoryDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin audit F7: Reserved Stock (held, from the ledger) and Booked Demand (upcoming need) stay
 * distinct and are labelled as such; Dashboard and Reports share one on-hand low-stock rule;
 * the dispatch service reads holds through the shared ledger rule; and the shortage panel does
 * not count already-dispatched units as still missing.
 */
class InventoryDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_stock_and_booked_demand_stay_distinct(): void
    {
        $roses = $this->item('White Roses', 10, true, 0);
        $arch = $this->item('Gold Arch', 5, false, 0);
        $booking = $this->booking('confirmed');
        $this->line($booking, $roses, 4);
        $this->line($booking, $arch, 2);
        $this->ledger($arch, $booking, 'booking_lock', -2);

        // Held: only the reusable arch is ever held. Demand: both lines count.
        $this->assertEquals(0.0, (float) $roses->fresh()->reserved_stock);
        $this->assertEquals(2.0, (float) $arch->fresh()->reserved_stock);
        $this->assertEquals(4.0, (float) InventoryItem::withBookedDemand()->find($roses->id)->reserved_stock);
        $this->assertEquals(2.0, (float) InventoryItem::withBookedDemand()->find($arch->id)->reserved_stock);
    }

    public function test_inventory_page_labels_the_demand_figure_as_booked_demand(): void
    {
        $roses = $this->item('White Roses', 10, true, 0);
        $this->line($this->booking('confirmed'), $roses, 4);

        $page = $this->admin()->get(route('admin.inventory.index'))->assertOk();
        $page->assertSee('BOOKED DEMAND');
        $page->assertSee('Quantity needed by upcoming confirmed booking lines (not yet held or dispatched).');
        $page->assertDontSee('>RESERVED</th>', false);
        $page->assertDontSee('Quantity committed to valid event bookings.');
    }

    public function test_dashboard_and_reports_share_the_on_hand_low_stock_rule(): void
    {
        $this->item('Low Ribbon', 2, false, 5);       // on hand below minimum
        $this->item('Empty Vase', 0, false, 1);       // out of stock
        $planned = $this->item('Gold Arch', 10, false, 5);
        $this->line($this->booking('confirmed'), $planned, 8); // low only after booked demand

        $belowMinimum = InventoryItem::belowMinimum()->count();
        $this->assertSame(2, $belowMinimum);

        $this->admin()->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('inventoryStatus', fn ($s) => $s['low_stock'] + $s['out_of_stock'] === $belowMinimum)
            ->assertViewHas('lowStockItems', fn ($items) => $items->count() === $belowMinimum);
        $this->admin()->get(route('admin.reports'))->assertOk()
            ->assertViewHas('stockAlerts', $belowMinimum)
            ->assertViewHas('lowStockItems', fn ($items) => $items->count() === $belowMinimum);

        // The Inventory page is a planning view: it also flags the arch, low after booked demand.
        $this->admin()->get(route('admin.inventory.index'))->assertOk()
            ->assertViewHas('inventoryItems', fn ($items) => collect($items->items())->firstWhere('id', $planned->id)->warning_level === 'low');
    }

    public function test_dispatch_and_release_use_the_ledger_hold(): void
    {
        $arch = $this->item('Gold Arch', 10, false, 0);
        $booking = $this->booking('confirmed');
        $this->ledger($arch, $booking, 'booking_lock', -3);
        $service = app(InventoryDispatchService::class);

        $service->dispatchItems($booking, [['inventory_item_id' => $arch->id, 'quantity' => 2]], $this->adminUser()->id, 'Load truck');
        $this->assertEquals(1.0, $arch->fresh()->reservedForBooking($booking->id));
        $this->assertEquals(8.0, (float) $arch->fresh()->current_stock);

        try {
            $service->dispatchItems($booking, [['inventory_item_id' => $arch->id, 'quantity' => 2]], $this->adminUser()->id, 'Too many');
            $this->fail('Dispatching more than is still held must be refused.');
        } catch (\LogicException $e) {
            $this->assertMatchesRegularExpression('/only 1( \S+)? is reserved for this booking/', $e->getMessage());
        }

        $released = $service->releaseUnfulfilledReservations($booking, $this->adminUser()->id, 'Event over');
        $this->assertCount(1, $released);
        $this->assertEquals(1.0, (float) $released[0]->quantity_change);
        $this->assertEquals(0.0, $arch->fresh()->reservedForBooking($booking->id));
        $this->assertEquals(0.0, (float) $arch->fresh()->reserved_stock);
    }

    public function test_shortage_panel_does_not_count_dispatched_units_as_missing(): void
    {
        $arch = $this->item('Gold Arch', 5, false, 0);
        $booking = $this->booking('confirmed');
        $this->line($booking, $arch, 4);
        $this->ledger($arch, $booking, 'booking_lock', -4);
        $this->ledger($arch, $booking, 'dispatch', -3);
        $arch->decrement('current_stock', 3); // 2 on hand, 1 still held here, 3 already at the venue

        $this->admin()->get(route('admin.notifications'))->assertOk()
            ->assertViewHas('alerts', fn ($alerts) => $alerts->firstWhere('id', 'booking-shortage-' . $booking->id) === null);

        // A real shortage is still reported, net of what was dispatched.
        $other = $this->booking('confirmed');
        $this->ledger($arch, $other, 'booking_lock', -2); // the 2 on hand are now held elsewhere... over-committed
        $this->admin()->get(route('admin.notifications'))->assertOk()
            ->assertViewHas('alerts', function ($alerts) use ($booking) {
                $alert = $alerts->firstWhere('id', 'booking-shortage-' . $booking->id);
                $row = $alert ? collect($alert->items)->firstWhere('name', 'Gold Arch') : null;

                return $row !== null && (float) $row['required'] === 1.0 && (float) $row['shortfall'] === 1.0;
            });
    }

    private function item(string $name, float $stock, bool $perishable, float $minimum): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => $perishable ? 'flowers' : 'props', 'is_perishable' => $perishable,
            'current_stock' => $stock, 'unit_cost' => 100, 'min_stock' => $minimum, 'unit' => 'piece']);
    }

    private function booking(string $status): Booking
    {
        return Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(10)->toDateString(), 'venue' => 'Hall',
            'status' => $status, 'final_quoted_price' => 18000, 'total_quoted' => 18000, 'confirmed_at' => now()]);
    }

    private function line(Booking $booking, InventoryItem $item, float $quantity): BookingItem
    {
        return BookingItem::create(['booking_id' => $booking->id, 'inventory_item_id' => $item->id, 'item_name' => $item->name,
            'quantity' => $quantity, 'quoted_unit_price' => 100, 'confirmed_at' => now()]);
    }

    private function ledger(InventoryItem $item, Booking $booking, string $type, float $change): void
    {
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $booking->id, 'quantity_change' => $change,
            'transaction_type' => $type, 'reason' => 'test', 'performed_by' => $this->adminUser()->id]);
    }

    private function adminUser(): User
    {
        return User::firstOrCreate(['email' => 'admin-definitions@example.com'], ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin']);
    }

    private function admin(): static
    {
        return $this->actingAs($this->adminUser());
    }
}

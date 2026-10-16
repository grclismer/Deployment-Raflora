<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin audit F7–F9: reservations are counted from one ledger rule wherever they are read;
 * free text and look-alike names never create or silently pick catalog items; and viewing Admin
 * pages never links, creates or changes booking materials.
 */
class InventoryConsistencyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // F7 — One per-booking ledger rule: locks − releases − dispatches, never below zero.
    public function test_reservation_rule_is_shared_and_does_not_double_count_dispatch(): void
    {
        $arch = $this->item('Gold Arch', 10, false);
        $a = $this->booking('confirmed');
        $b = $this->booking('confirmed');
        $this->ledger($arch, $a, 'booking_lock', -5);
        $this->ledger($arch, $a, 'booking_release', 1);
        $this->ledger($arch, $a, 'dispatch', -2);
        $arch->decrement('current_stock', 2);
        $this->ledger($arch, $b, 'booking_lock', -3);

        $arch = $arch->fresh();
        $this->assertEquals(2.0, $arch->reservedForBooking($a->id)); // 5 − 1 − 2 still held, 2 already out
        $this->assertEquals(3.0, $arch->reservedForBooking($b->id));
        $this->assertEquals(5.0, (float) $arch->reserved_stock);
        $this->assertEquals(8.0, (float) $arch->current_stock);
        $this->assertEquals(3.0, $arch->net_available);
    }

    // F7 — The shortage alert counts other bookings' holds instead of subtracting this booking's need.
    public function test_booking_shortage_counts_stock_held_by_other_bookings(): void
    {
        $arch = $this->item('Gold Arch', 3, false);
        $holder = $this->booking('confirmed');
        $this->line($holder, $arch, 2);
        $this->ledger($arch, $holder, 'booking_lock', -2);

        $needer = $this->booking('admin_approved');
        $this->line($needer, $arch, 2);

        $this->admin()->get(route('admin.notifications'))->assertOk()
            ->assertViewHas('alerts', function ($alerts) use ($needer) {
                $alert = $alerts->firstWhere('id', 'booking-shortage-' . $needer->id);
                $row = $alert ? collect($alert->items)->firstWhere('name', 'Gold Arch') : null;

                // 3 on hand − 2 held for the other booking = 1 available against 2 needed.
                return $row !== null && (float) $row['reserved_stock'] === 2.0 && (float) $row['net_available'] === 1.0 && (float) $row['shortfall'] === 1.0;
            });
    }

    // F7 — The Inventory page's "Reserved" is booked demand, from one named definition.
    public function test_inventory_page_uses_the_shared_booked_demand_definition(): void
    {
        $roses = $this->item('Pink Roses', 10, true);
        $this->line($this->booking('confirmed'), $roses, 4);
        $this->line($this->booking('pending'), $roses, 25, ['is_ai_suggested' => true, 'confirmed_at' => null]);
        $this->line($this->booking('cancelled'), $roses, 7);

        $expected = (float) InventoryItem::withBookedDemand()->find($roses->id)->reserved_stock;
        $this->assertEquals(4.0, $expected);

        $this->admin()->get(route('admin.inventory.index'))->assertOk()
            ->assertViewHas('inventoryItems', fn ($items) => (float) collect($items->items())->firstWhere('id', $roses->id)->reserved_stock === $expected);
    }

    // F8 — A free-text quotation line stays an unlinked booking line; the catalog is unchanged.
    public function test_free_text_quotation_line_does_not_create_a_catalog_item(): void
    {
        $booking = $this->booking('pending');
        $roses = $this->line($booking, $this->item('White Roses', 50, true), 10, ['quoted_unit_price' => 600]);
        $catalog = InventoryItem::count();

        $this->admin()->put(route('admin.bookings.update', $booking), $this->form($booking, [
            'action' => 'send_quotation',
            'items' => [
                ['booking_item_id' => $roses->id, 'item_name' => 'White Roses', 'quantity' => 10, 'unit_price' => 600],
                ['item_name' => 'Hand-painted Welcome Sign', 'quantity' => 1, 'unit_price' => 800, 'is_ai_suggested' => '1'],
            ],
        ]))->assertSessionMissing('error');

        $this->assertSame($catalog, InventoryItem::count());
        $line = $booking->bookingItems()->where('item_name', 'Hand-painted Welcome Sign')->sole();
        $this->assertNull($line->inventory_item_id);
        $this->assertNull($line->confirmed_at);
        $this->assertNotSame('unmatched', $line->procurement_status); // not marked "removed from quotation"
        $this->assertSame(['White Roses'], collect($booking->fresh()->activeQuotation->items_snapshot)->pluck('item_name')->all());
    }

    // F8 — Promoting a line that does not exist on the booking creates nothing.
    public function test_promote_without_a_matching_booking_line_creates_nothing(): void
    {
        $booking = $this->booking('pending');
        $catalog = InventoryItem::count();

        $this->admin()->post(route('admin.bookings.ai.promote', $booking), [
            'booking_item_id' => 999999, 'catalog_name' => 'Ghost Item', 'quantity' => 4, 'unit_price' => 50,
        ])->assertSessionHas('error');

        $this->assertSame($catalog, InventoryItem::count());
        $this->assertSame(0, $booking->bookingItems()->count());
    }

    // F8 — A promote that would duplicate an existing catalog name is refused; a new name still works.
    public function test_promote_refuses_duplicate_names_and_still_creates_new_items(): void
    {
        $booking = $this->booking('pending');
        $this->item('Gold Arch', 2, false);
        $line = $this->line($booking, null, 1, ['item_name' => 'golden arch frame', 'is_ai_suggested' => true, 'confirmed_at' => null]);

        $this->admin()->post(route('admin.bookings.ai.promote', $booking), ['booking_item_id' => $line->id, 'catalog_name' => '  gold ARCH '])
            ->assertSessionHas('error');
        $this->assertSame(1, InventoryItem::where('name', 'like', '%arch%')->count());
        $this->assertNull($line->fresh()->inventory_item_id);

        $this->admin()->post(route('admin.bookings.ai.promote', $booking), ['booking_item_id' => $line->id, 'catalog_name' => 'Golden Arch Frame', 'is_perishable' => 0])
            ->assertSessionHas('success');
        $created = InventoryItem::where('name', 'Golden Arch Frame')->sole();
        $this->assertSame($created->id, $line->fresh()->inventory_item_id);
        $this->assertEquals(0.0, (float) $created->current_stock);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post(route('admin.bookings.ai.promote', $booking), ['booking_item_id' => $line->id, 'catalog_name' => 'Staff Item'])
            ->assertForbidden();
        $this->assertSame(0, InventoryItem::where('name', 'Staff Item')->count());
    }

    // F8 — Sending the quotation never picks one of two look-alike catalog items for an AI line.
    public function test_send_does_not_link_an_ai_line_to_an_ambiguous_name(): void
    {
        $booking = $this->booking('pending');
        $this->item('Gold Arch', 2, false);
        $this->item('Gold Arch', 5, false); // older duplicate
        $this->line($booking, $this->item('White Roses', 50, true), 10, ['quoted_unit_price' => 600]);
        $ai = $this->line($booking, null, 1, ['item_name' => 'Gold Arch', 'is_ai_suggested' => true, 'confirmed_at' => null]);

        $this->admin()->put(route('admin.bookings.update', $booking), $this->form($booking, ['action' => 'send_quotation']))
            ->assertSessionHas('error', 'Confirm every material requirement before sending the quotation.');

        $this->assertNull($ai->fresh()->inventory_item_id);
        $this->assertSame(0, $booking->quotations()->count());
    }

    // F9 — Viewing the booking page, edit page and Notifications writes nothing, however often.
    public function test_admin_booking_and_notification_pages_do_not_write(): void
    {
        $booking = $this->booking('pending');
        $this->item('White Roses', 50, true);
        $line = $this->line($booking, null, 10, ['item_name' => 'White Roses', 'is_ai_suggested' => true, 'confirmed_at' => null]);
        $this->adminUser(); // created before listening, so only the page requests are observed

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) && !preg_match('/\b(sessions|cache)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        foreach ([route('admin.bookings.show', $booking), route('admin.bookings.edit', $booking), route('admin.notifications')] as $url) {
            $this->admin()->get($url)->assertOk();
            $this->admin()->get($url)->assertOk();
        }

        $this->assertSame([], $writes);
        $this->assertNull($line->fresh()->inventory_item_id);
    }

    // F9 — Linking stays available as Admin's explicit action.
    public function test_explicit_link_action_still_links(): void
    {
        $booking = $this->booking('pending');
        $roses = $this->item('White Roses', 50, true);
        $line = $this->line($booking, null, 10, ['item_name' => 'white roses', 'is_ai_suggested' => true, 'confirmed_at' => null]);

        $this->admin()->post(route('admin.bookings.ai.link', $booking), ['booking_item_id' => $line->id, 'link_inventory_item_id' => $roses->id])
            ->assertSessionHas('success');

        $this->assertSame($roses->id, $line->fresh()->inventory_item_id);
    }

    private function item(string $name, float $stock, bool $perishable): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => $perishable ? 'flowers' : 'props', 'is_perishable' => $perishable,
            'current_stock' => $stock, 'unit_cost' => 100, 'min_stock' => 0, 'unit' => 'piece']);
    }

    private function booking(string $status): Booking
    {
        return Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(10)->toDateString(), 'venue' => 'Hall',
            'status' => $status, 'multiplier' => 3, 'final_quoted_price' => 18000, 'total_quoted' => 18000, 'confirmed_at' => now()]);
    }

    private function line(Booking $booking, ?InventoryItem $item, float $quantity, array $attributes = []): BookingItem
    {
        return BookingItem::create(array_merge(['booking_id' => $booking->id, 'inventory_item_id' => $item?->id, 'item_name' => $item?->name,
            'quantity' => $quantity, 'quoted_unit_price' => 100, 'is_ai_suggested' => false, 'confirmed_at' => now()], $attributes));
    }

    private function ledger(InventoryItem $item, Booking $booking, string $type, float $change): void
    {
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $booking->id, 'quantity_change' => $change,
            'transaction_type' => $type, 'reason' => 'test', 'performed_by' => $this->adminUser()->id]);
    }

    private function form(Booking $booking, array $fields): array
    {
        return array_merge(['event_type' => $booking->event_type, 'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue, 'status' => $booking->status], $fields);
    }

    private function adminUser(): User
    {
        return User::firstOrCreate(['email' => 'admin-inventory@example.com'], ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin']);
    }

    private function admin(): static
    {
        return $this->actingAs($this->adminUser());
    }
}

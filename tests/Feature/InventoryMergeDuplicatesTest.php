<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Package;
use App\Models\ReturnItem;
use App\Models\User;
use App\Services\InventoryMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Names with the same words in a different order are the same item, and duplicate items are
 * merged into the original: bookings, stock and history move over, nothing is lost.
 */
class InventoryMergeDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = Client::create(['full_name' => 'Test Client', 'email' => 'client@example.com', 'phone' => '09170000000']);
    }

    public function test_word_order_does_not_make_a_new_item(): void
    {
        $arch = $this->item('Entrance Arch Floral Arrangement', 5, 0);

        $this->assertSame(InventoryItem::wordKey('Entrance Floral Arch Arrangement'), InventoryItem::wordKey('entrance arch floral arrangement'));
        $this->assertSame($arch->id, InventoryItem::catalogMatches('Entrance Floral Arch Arrangement')['exact']?->id);
        $this->assertSame($arch->id, InventoryItem::catalogMatches('Floral Arch Arrangements Entrance')['similar']?->id);
        $this->assertNull(InventoryItem::catalogMatches('Entrance Arch Arrangement')['exact'], 'Different words stay different items.');
        $this->assertNull(InventoryItem::catalogMatches('Entrance Arch Arrangement')['similar']);

        $this->actingAs($this->admin)
            ->post(route('admin.inventory.store'), [
                'name' => 'Entrance Floral Arch Arrangement', 'category' => 'Props', 'unit' => 'piece', 'unit_cost' => 0,
                'min_stock' => 0, 'item_type' => 'non_perishable', 'received_date' => now()->toDateString(),
                'usable_life_value' => 365, 'usable_life_unit' => 'days',
            ])
            ->assertSessionHasErrors('name');
        $this->assertStringContainsString('"Entrance Arch Floral Arrangement" is already in inventory', session('errors')->first('name'));
        $this->assertSame(1, InventoryItem::withTrashed()->count());
    }

    public function test_merge_moves_every_record_to_the_original_and_adds_the_stock(): void
    {
        $keep = $this->item('Red Rose', 250, 80);
        $duplicate = $this->item('Red Roses', 180, 15);
        $other = $this->item('White Rose', 100, 18);

        // Booking A uses only the duplicate; booking B lists both and holds stock on both.
        $bookingA = $this->booking();
        $lineA = $this->line($bookingA, $duplicate, 12, 20);
        $bookingB = $this->booking();
        $keepLine = $this->line($bookingB, $keep, 10, 80);
        $dupLine = $this->line($bookingB, $duplicate, 5, 20);
        $this->ledger($bookingB, $keep, 'booking_lock', -10);
        $this->ledger($bookingB, $duplicate, 'booking_lock', -5);
        $this->ledger($bookingA, $duplicate, 'booking_lock', -12);

        $return = AssetReturn::create(['booking_id' => $bookingB->id, 'status' => 'Partially Returned', 'total_damage_charge' => 100, 'approval_status' => 'pending']);
        $keepReturn = $this->returnLine($return, $keep, ['quantity_returned' => 10, 'quantity_good' => 9, 'quantity_damaged' => 1, 'condition' => 'mixed', 'charge_decision' => 'charge', 'damage_charge' => 100]);
        $this->returnLine($return, $duplicate, ['quantity_returned' => 4, 'quantity_good' => 4, 'quantity_lost' => 1, 'condition' => 'lost', 'charge_decision' => 'pending']);

        $package = Package::create(['title' => 'Garden', 'category' => 'wedding', 'price' => 1000]);
        DB::table('inventory_item_package')->insert([
            ['package_id' => $package->id, 'inventory_item_id' => $keep->id, 'quantity' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['package_id' => $package->id, 'inventory_item_id' => $duplicate->id, 'quantity' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('inventory_item_substitutes')->insert([
            ['item_id' => $duplicate->id, 'substitute_id' => $other->id],
            ['item_id' => $keep->id, 'substitute_id' => $other->id],
            ['item_id' => $duplicate->id, 'substitute_id' => $keep->id],
        ]);
        $analysis = AiAnalysisResult::create(['booking_id' => $bookingA->id, 'suggested_materials' => [['item_name' => 'Red Roses', 'inventory_item_id' => $duplicate->id], ['item_name' => 'White Rose', 'inventory_item_id' => $other->id]], 'analyzed_at' => now()]);

        $report = app(InventoryMergeService::class)->merge($keep, $duplicate, $this->admin->id);

        // The original keeps its name and cost and gets both stocks; the duplicate is archived.
        $keep->refresh();
        $this->assertSame(['Red Rose', 80.0, 430.0], [$keep->name, (float) $keep->unit_cost, (float) $keep->current_stock]);
        $duplicate = InventoryItem::withTrashed()->find($duplicate->id);
        $this->assertTrue($duplicate->trashed());
        $this->assertSame(0.0, (float) $duplicate->current_stock);
        $this->assertStringContainsString("Merged into #{$keep->id} Red Rose", $duplicate->description);

        // Booking A's line now points at the original; booking B has one combined line, same total.
        $this->assertSame($keep->id, (int) $lineA->fresh()->inventory_item_id);
        $this->assertNull($dupLine->fresh());
        $keepLine->refresh();
        $this->assertSame([15.0, 60.0], [(float) $keepLine->quantity, (float) $keepLine->quoted_unit_price]);
        $this->assertSame(1, BookingItem::where('booking_id', $bookingB->id)->count());

        // Stock held for each booking is unchanged, now on the original.
        $this->assertSame(15.0, $keep->reservedForBooking($bookingB->id));
        $this->assertSame(12.0, $keep->reservedForBooking($bookingA->id));
        $this->assertSame(0, InventoryTransaction::where('inventory_item_id', $duplicate->id)->count());

        // The return audit has one line with both counts; the open decision stays pending.
        $this->assertSame(1, ReturnItem::where('return_id', $return->id)->count());
        $keepReturn->refresh();
        $this->assertSame([14.0, 13.0, 1.0, 1.0, 100.0], [(float) $keepReturn->quantity_returned, (float) $keepReturn->quantity_good, (float) $keepReturn->quantity_damaged, (float) $keepReturn->quantity_lost, (float) $keepReturn->damage_charge]);
        $this->assertSame(['mixed', 'pending'], [$keepReturn->condition, $keepReturn->charge_decision]);

        // Package quantities added; substitutes moved without self-links or repeats; AI rows relinked.
        $this->assertSame([10.0], DB::table('inventory_item_package')->where('package_id', $package->id)->pluck('quantity')->map(fn ($q) => (float) $q)->all());
        $this->assertSame([[$keep->id, $other->id]], DB::table('inventory_item_substitutes')->get()->map(fn ($row) => [(int) $row->item_id, (int) $row->substitute_id])->all());
        $this->assertSame([$keep->id, $other->id], array_column($analysis->fresh()->suggested_materials, 'inventory_item_id'));

        $this->assertSame(2, $report['booking_lines']);
        $this->assertTrue(AuditLog::where('action', 'inventory_items_merged')->where('entity_id', $keep->id)->exists());
    }

    public function test_command_previews_then_merges_every_duplicate_into_the_lowest_id(): void
    {
        $keepArch = $this->item('Entrance Floral Arch Arrangement', 5000, 0);
        $keepRose = $this->item('Pink Rose', 500, 85);
        $dupArch = $this->item('Entrance Arch Floral Arrangement', 500, 0);
        $dupRose = $this->item('Pink Roses', 500, 16);
        $this->item('Entrance Arch', 3, 0);

        $this->artisan('inventory:merge-duplicates', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();
        $this->assertSame(5, InventoryItem::count());

        $this->artisan('inventory:merge-duplicates')
            ->expectsOutputToContain('2 duplicate item(s) merged.')
            ->assertSuccessful();

        $this->assertSame(3, InventoryItem::count());
        $this->assertSame(5500.0, (float) $keepArch->fresh()->current_stock);
        $this->assertSame([1000.0, 85.0], [(float) $keepRose->fresh()->current_stock, (float) $keepRose->fresh()->unit_cost]);
        $this->assertTrue($dupArch->fresh()->trashed());
        $this->assertTrue($dupRose->fresh()->trashed());

        $this->artisan('inventory:merge-duplicates')->expectsOutputToContain('No duplicate inventory items found.')->assertSuccessful();
    }

    public function test_a_return_line_merged_into_a_perishable_original_can_still_be_saved(): void
    {
        // The original is a fresh flower; the copy was added as a reusable item and went through a return audit.
        $keep = $this->item('Rose Orange', 150, 100);
        $duplicate = $this->item('Orange Rose', 500, 0);
        $duplicate->update(['is_perishable' => false]);
        $booking = $this->booking();
        $booking->update(['status' => 'pending_return']);
        $this->ledger($booking, $duplicate, 'dispatch', -180);
        $return = AssetReturn::create(['booking_id' => $booking->id, 'status' => 'Pending', 'total_damage_charge' => 0, 'approval_status' => 'not_required']);
        $line = $this->returnLine($return, $duplicate, []);

        app(InventoryMergeService::class)->merge($keep, $duplicate);

        $this->actingAs($this->admin)
            ->get(route('admin.return-tracking.manage', $booking))
            ->assertOk()
            ->assertSee('data-dispatched="180"', false);

        $this->actingAs($this->admin)
            ->put(route('admin.return-tracking.update', $return), [
                'items' => [$line->id => ['inventory_item_id' => $keep->id, 'quantity_good' => 170, 'quantity_damaged' => 10, 'quantity_lost' => 0, 'charge_decision' => 'no_charge', 'damage_charge' => 0]],
                'notes' => 'Inspected',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame([170.0, 10.0, 'no_charge'], [(float) $line->fresh()->quantity_good, (float) $line->fresh()->quantity_damaged, $line->fresh()->charge_decision]);
    }

    private function item(string $name, float $stock, float $cost): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => 'Flowers', 'is_perishable' => true, 'current_stock' => $stock, 'unit_cost' => $cost, 'min_stock' => 0, 'unit' => 'stems']);
    }

    private function booking(): Booking
    {
        return Booking::create(['client_id' => $this->client->id, 'status' => 'confirmed', 'event_type' => 'wedding', 'event_date' => now()->addDays(20)->toDateString(), 'venue' => 'Grand Hall']);
    }

    private function line(Booking $booking, InventoryItem $item, float $quantity, float $price): BookingItem
    {
        return BookingItem::create(['booking_id' => $booking->id, 'inventory_item_id' => $item->id, 'item_name' => $item->name, 'quantity' => $quantity, 'quoted_unit_price' => $price, 'is_ai_suggested' => false, 'procurement_status' => 'pending']);
    }

    private function ledger(Booking $booking, InventoryItem $item, string $type, float $change): void
    {
        InventoryTransaction::create(['booking_id' => $booking->id, 'inventory_item_id' => $item->id, 'transaction_type' => $type, 'quantity_change' => $change]);
    }

    private function returnLine(AssetReturn $return, InventoryItem $item, array $values): ReturnItem
    {
        return ReturnItem::create(array_merge(['return_id' => $return->id, 'inventory_item_id' => $item->id, 'quantity_returned' => 0, 'quantity_good' => 0, 'quantity_damaged' => 0, 'quantity_lost' => 0, 'condition' => 'pending', 'damage_charge' => 0, 'charge_decision' => 'pending'], $values));
    }
}

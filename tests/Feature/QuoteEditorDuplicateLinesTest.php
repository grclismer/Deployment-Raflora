<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quote editor lists every saved material line (two lines can share a name, e.g. the same
 * material suggested for two photos), so each one can be priced before issuing; and a refused
 * Approve & Send keeps the values Admin typed.
 */
class QuoteEditorDuplicateLinesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Booking $booking;
    private BookingItem $first;
    private BookingItem $second;
    private BookingItem $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => 'client@example.com', 'phone' => '09170000000']);
        $this->booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'pending',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Grand Hall',
        ]);
        $arch = InventoryItem::create(['name' => 'Entrance Floral Arch Arrangement', 'category' => 'props', 'is_perishable' => false, 'current_stock' => 500, 'unit_cost' => 0, 'min_stock' => 0, 'unit' => 'piece']);
        $rose = InventoryItem::create(['name' => 'White Rose', 'category' => 'flowers', 'is_perishable' => true, 'current_stock' => 500, 'unit_cost' => 80, 'min_stock' => 0, 'unit' => 'stem']);
        $this->first = $this->line($arch, 120);
        $this->second = $this->line($arch, 120);
        $this->other = $this->line($rose, 250);
    }

    public function test_both_lines_with_the_same_name_are_listed_and_the_second_is_flagged(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.bookings.show', $this->booking))->assertOk()->getContent();

        $this->assertStringContainsString('name="items[0][booking_item_id]" value="' . $this->first->id . '"', $html);
        $this->assertStringContainsString('name="items[1][booking_item_id]" value="' . $this->second->id . '"', $html);
        $this->assertSame(1, substr_count($html, '>Listed twice</span>'));
    }

    public function test_pricing_both_duplicate_lines_lets_the_quotation_go_out(): void
    {
        $this->sendQuotation([
            [$this->first, 120, '450'],
            [$this->second, 120, '450'],
            [$this->other, 250, '80'],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame('quotation_sent', $this->booking->fresh()->status);
        $this->assertSame(450.0, (float) $this->second->fresh()->quoted_unit_price);
    }

    public function test_a_refused_send_keeps_what_admin_typed(): void
    {
        // The second arch line is left at ₱0, so issuing is refused and nothing is saved...
        $this->sendQuotation([
            [$this->first, 130, '450'],
            [$this->second, 120, '0'],
            [$this->other, 260, '85.50'],
        ])->assertSessionHas('error');

        $this->assertSame('pending', $this->booking->fresh()->status);
        $this->assertSame(0.0, (float) $this->first->fresh()->quoted_unit_price);

        // ...but the editor refills the typed values, so only the ₱0 line needs attention.
        $html = $this->actingAs($this->admin)->get(route('admin.bookings.show', $this->booking))->getContent();
        $this->assertMatchesRegularExpression('/id="input_qty_0"[^>]*value="130"/', $html);
        $this->assertMatchesRegularExpression('/id="input_price_0"[^>]*value="450.00"/', $html);
        $this->assertMatchesRegularExpression('/id="input_price_2"[^>]*value="85.50"/', $html);
        $this->assertMatchesRegularExpression('/id="input_qty_2"[^>]*value="260"/', $html);
    }

    private function sendQuotation(array $rows)
    {
        $items = [];
        foreach ($rows as [$line, $qty, $price]) {
            $items[] = [
                'booking_item_id' => $line->id,
                'inventory_item_id' => $line->inventory_item_id,
                'item_name' => $line->item_name,
                'is_ai_suggested' => '1',
                'quantity' => $qty,
                'unit_price' => $price,
            ];
        }

        return $this->actingAs($this->admin)
            ->from(route('admin.bookings.show', $this->booking))
            ->put(route('admin.bookings.update', $this->booking), [
                'event_type' => 'wedding',
                'event_date' => $this->booking->event_date->toDateString(),
                'venue' => 'Grand Hall',
                'status' => 'pending',
                'action' => 'send_quotation',
                'multiplier' => 3,
                'items' => $items,
            ]);
    }

    private function line(InventoryItem $item, float $quantity): BookingItem
    {
        return BookingItem::create([
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'quoted_unit_price' => 0,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);
    }
}

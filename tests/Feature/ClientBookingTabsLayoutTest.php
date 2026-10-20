<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client booking page keeps its supporting information in tabs (one panel at a time), folds the
 * progress steps, lists the quotation compactly and does not repeat what the header already shows.
 */
class ClientBookingTabsLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporting_information_is_tabbed_compact_and_not_repeated(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $user->email, 'phone' => '09170000000', 'user_id' => $user->id]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'quotation_sent',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Grand Hall',
            'event_size' => 120,
            'table_count' => 12,
            'special_requests' => 'Pastel roses only',
            'final_quoted_price' => 9000,
        ]);
        $items = [
            ['item_name' => 'Entrance Arch', 'quantity' => 1, 'quoted_unit_price' => 100],
            ['item_name' => 'entrance arch', 'quantity' => 2, 'quoted_unit_price' => 100],
        ];
        foreach (range(1, 11) as $n) {
            $items[] = ['item_name' => "Material {$n}", 'quantity' => $n, 'quoted_unit_price' => 10];
        }
        Quotation::create(['booking_id' => $booking->id, 'status' => Quotation::STATUS_ISSUED, 'version' => 1, 'multiplier' => 3, 'final_quoted_price' => 9000, 'valid_until' => now()->addDays(7)->toDateString(), 'items_snapshot' => $items]);

        $html = $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('role="tablist" aria-label="Booking information"', false)
            ->assertSee('id="booking-tab-quotation"', false)
            ->assertSee('id="booking-tab-details"', false)
            ->assertSee('Special Requests')
            ->assertSee('Pastel roses only')
            ->assertSee('data-workflow-steps', false)
            ->assertSee('Show all steps')
            ->assertSee('Show all 12 items')
            ->assertSee('id="quoteItemSearch"', false)
            // The header already shows the event basics: no second Event Details card.
            ->assertDontSee('id="event-details-heading"', false)
            ->getContent();

        // Same name and price: one line, quantities added (1 + 2 at ₱300).
        $this->assertSame(1, substr_count($html, '>Entrance Arch</span>'));
        $this->assertStringContainsString('3 × ₱300.00', $html);
        $this->assertSame(12, substr_count($html, 'data-quote-row data-name='));
        // Only the first 10 lines show until "Show all".
        $this->assertSame(2, preg_match_all('/class="[^"]*\bhidden\b[^"]*" data-quote-row/', $html));
        $this->assertSame(1, substr_count($html, '>120 guests<'), 'Guests appear once, in the header.');
    }
}

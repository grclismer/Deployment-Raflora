<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\GuestBookingController;
use App\Models\Booking;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AI suggestions link to a catalog item only on an exact name (ignoring case and punctuation);
 * a partial name such as "Tulip" inside "Handmade Pink Velvet Tulip" stays unmatched for Admin.
 */
class AiSuggestionInventoryMatchingTest extends TestCase
{
    use RefreshDatabase;

    public static function controllers(): array
    {
        return [
            'client booking' => [BookingController::class],
            'guest booking' => [GuestBookingController::class],
        ];
    }

    #[DataProvider('controllers')]
    public function test_partial_names_are_not_linked_to_a_different_catalog_item(string $controller): void
    {
        $tulip = $this->inventoryItem('Tulip', perishable: true);
        $this->inventoryItem('White Satin Ribbon');
        $booking = $this->booking();

        $this->persist($controller, $booking, [
            ['item_name' => 'Handmade Pink Velvet Tulip', 'quantity' => 3, 'unit_cost_php' => 50],
            ['item_name' => 'Pink and White Satin Ribbon', 'quantity' => 2, 'unit_cost_php' => 50],
        ]);

        $items = $booking->bookingItems()->get()->keyBy('item_name');
        $this->assertNull($items['Handmade Pink Velvet Tulip']->inventory_item_id);
        $this->assertNull($items['Pink and White Satin Ribbon']->inventory_item_id);
        $this->assertFalse($booking->bookingItems()->where('inventory_item_id', $tulip->id)->exists());
    }

    #[DataProvider('controllers')]
    public function test_exact_names_still_link_ignoring_case_and_punctuation(string $controller): void
    {
        $tulip = $this->inventoryItem('Tulip', perishable: true);
        $ribbon = $this->inventoryItem('White Satin-Ribbon');
        $booking = $this->booking();

        $this->persist($controller, $booking, [
            ['item_name' => 'tulip', 'quantity' => 3, 'unit_cost_php' => 50],
            ['item_name' => 'WHITE SATIN RIBBON', 'quantity' => 2, 'unit_cost_php' => 50],
        ]);

        $this->assertSame($tulip->id, (int) $booking->bookingItems()->where('item_name', 'tulip')->value('inventory_item_id'));
        $this->assertSame($ribbon->id, (int) $booking->bookingItems()->where('item_name', 'WHITE SATIN RIBBON')->value('inventory_item_id'));
    }

    #[DataProvider('controllers')]
    public function test_all_capital_or_ambiguous_names_never_link_by_accident(string $controller): void
    {
        $this->inventoryItem('LED');
        $this->inventoryItem('Gold Vase');
        $this->inventoryItem('gold vase!');
        $booking = $this->booking();

        $this->persist($controller, $booking, [
            ['item_name' => 'Fairy Lights', 'quantity' => 1, 'unit_cost_php' => 50],
            ['item_name' => 'Gold Vase', 'quantity' => 1, 'unit_cost_php' => 50],
        ]);

        $this->assertSame(0, $booking->bookingItems()->whereNotNull('inventory_item_id')->count());
    }

    private function persist(string $controller, Booking $booking, array $materials): void
    {
        $method = new \ReflectionMethod($controller, 'persistAiSuggestedMaterials');
        $method->invoke(app($controller), $booking, $materials);
    }

    private function inventoryItem(string $name, bool $perishable = false): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => 'props', 'is_perishable' => $perishable, 'current_stock' => 50, 'unit_cost' => 10, 'min_stock' => 1, 'unit' => 'piece']);
    }

    private function booking(): Booking
    {
        return Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(20)->toDateString(), 'venue' => 'Hall', 'status' => 'pending']);
    }
}

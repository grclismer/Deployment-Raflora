<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCsvExportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Raflora Admin']);
    }

    public function test_shortage_csv_lists_only_this_bookings_shortage_items(): void
    {
        $booking = $this->shortageBooking();
        $other = $this->booking();
        $other->bookingItems()->create(['item_name' => 'Other Booking Item', 'quantity' => 4]);

        $response = $this->actingAs($this->admin)->get(route('admin.notifications.booking-shortages.export', $booking));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('booking_' . $booking->id . '_shortage_report_', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($csv, 3))));

        $this->assertContains(['Booking No.', '#' . $booking->id], $rows);
        $this->assertContains(['Client', 'Ana Santos'], $rows);
        $this->assertContains(['Prepared By', 'Raflora Admin'], $rows);
        $this->assertContains(['Shortage Items', '2', 'Items to Canvass', '1', 'Items to Procure', '1'], $rows);
        $this->assertContains(['1', 'Artificial Sunflower', 'Flower', '10', '4', '6', 'piece', 'Normal', 'Bloom Supply · Liza · 09170000000', 'To procure'], $rows);
        $this->assertContains(['2', 'Butterfly Accents', 'Not in inventory', '5', '0', '5', '', 'High', '', 'To canvass'], $rows);
        $this->assertStringNotContainsString('Floral Foam', $csv, 'Fully available materials are left out.');
        $this->assertStringNotContainsString('Other Booking Item', $csv);
    }

    public function test_printable_report_shows_the_logo_and_the_shortage_rows(): void
    {
        $booking = $this->shortageBooking();

        $this->actingAs($this->admin)
            ->get(route('admin.notifications.booking-shortages.report', $booking))
            ->assertOk()
            ->assertSee('assets/images/raflora-logo-nobackground.png', false)
            ->assertSee('Booking Shortage Report')
            ->assertSee('#' . $booking->id)
            ->assertSee('Artificial Sunflower')
            ->assertSee('Butterfly Accents')
            ->assertDontSee('Floral Foam')
            ->assertSee('Print / Save as PDF');
    }

    public function test_a_booking_without_shortages_has_nothing_to_export(): void
    {
        $booking = $this->booking();
        $foam = $this->inventoryItem('Floral Foam', 'Supply', 50);
        $booking->bookingItems()->create(['inventory_item_id' => $foam->id, 'item_name' => 'Floral Foam', 'quantity' => 2]);

        $this->actingAs($this->admin)
            ->from(route('admin.notifications'))
            ->get(route('admin.notifications.booking-shortages.export', $booking))
            ->assertRedirect(route('admin.notifications'))
            ->assertSessionHas('info');
    }

    public function test_only_admin_can_export_shortages_or_reports(): void
    {
        $booking = $this->shortageBooking();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.notifications.booking-shortages.export', $booking))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.notifications.booking-shortages.report', $booking))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.reports.export'))->assertForbidden();
    }

    public function test_shortage_popup_offers_export_and_print(): void
    {
        $booking = $this->shortageBooking();

        $this->actingAs($this->admin)
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee(route('admin.notifications.booking-shortages.export', ['booking' => $booking->id]), false)
            ->assertSee(route('admin.notifications.booking-shortages.report', ['booking' => $booking->id]), false);
    }

    public function test_reports_page_swaps_the_search_for_an_export_that_downloads_the_report(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee(route('admin.reports.export'), false)
            ->assertDontSee('id="adminGlobalSearch"', false);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export'));
        $response->assertOk();
        $csv = $response->streamedContent();
        foreach (['Key performance indicators', 'Six-month trend', 'Booking status distribution', 'Top requested materials', 'Low stock items', 'Recent activity'] as $section) {
            $this->assertStringContainsString($section, $csv);
        }

        // Other pages keep the booking search.
        $this->actingAs($this->admin)->get(route('admin.notifications'))->assertSee('id="adminGlobalSearch"', false);
    }

    private function shortageBooking(): Booking
    {
        $booking = $this->booking();
        $sunflower = $this->inventoryItem('Artificial Sunflower', 'Flower', 4, ['supplier_name' => 'Bloom Supply', 'supplier_contact_person' => 'Liza', 'supplier_contact_number' => '09170000000']);
        $foam = $this->inventoryItem('Floral Foam', 'Supply', 50);
        $booking->bookingItems()->create(['inventory_item_id' => $sunflower->id, 'item_name' => 'Artificial Sunflower', 'quantity' => 10]);
        $booking->bookingItems()->create(['inventory_item_id' => $foam->id, 'item_name' => 'Floral Foam', 'quantity' => 2]);
        $booking->bookingItems()->create(['item_name' => 'Butterfly Accents', 'quantity' => 5]);

        return $booking;
    }

    private function booking(): Booking
    {
        $client = Client::create(['full_name' => 'Ana Santos', 'email' => 'ana-' . uniqid() . '@example.com', 'phone' => '09170000001']);

        return Booking::create([
            'client_id' => $client->id,
            'status' => 'pending',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Grand Hall',
        ]);
    }

    private function inventoryItem(string $name, string $category, float $stock, array $extra = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name' => $name,
            'category' => $category,
            'is_perishable' => false,
            'current_stock' => $stock,
            'unit_cost' => 10,
            'min_stock' => 0,
            'unit' => 'piece',
        ], $extra));
    }
}

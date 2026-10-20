<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client Records lists five clients per page, and no Admin page has the header booking search.
 */
class AdminClientRecordsPagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_records_show_five_clients_per_page_and_paging_keeps_the_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 7) as $n) {
            Client::create(['full_name' => sprintf('Client %02d', $n), 'email' => "client{$n}@example.com", 'phone' => '0917000000' . $n]);
        }

        $first = $this->actingAs($admin)->get(route('admin.client-records'))->assertOk();
        $this->assertSame(5, $first->viewData('clients')->count());
        $this->assertSame(7, $first->viewData('clients')->total());
        $first->assertSee('Client 05')->assertDontSee('Client 06')
            ->assertDontSee('id="adminGlobalSearch"', false);

        $second = $this->actingAs($admin)->get(route('admin.client-records', ['page' => 2]))->assertOk();
        $second->assertSee('Client 06')->assertSee('Client 07')->assertDontSee('Client 01');

        // A search spanning pages keeps its term in the page links.
        $this->actingAs($admin)->get(route('admin.client-records', ['search' => 'Client']))
            ->assertOk()
            ->assertSee('search=Client&amp;page=2', false);
    }

    public function test_return_tracking_shows_five_returns_per_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['full_name' => 'Return Client', 'email' => 'return@example.com', 'phone' => '09170000000']);
        foreach (range(1, 7) as $n) {
            $booking = \App\Models\Booking::create(['client_id' => $client->id, 'status' => 'pending_return', 'event_type' => 'wedding', 'event_date' => now()->subDays($n)->toDateString(), 'venue' => 'Hall']);
            \App\Models\AssetReturn::create(['booking_id' => $booking->id, 'status' => 'Pending', 'total_damage_charge' => 0, 'approval_status' => 'not_required']);
        }

        $first = $this->actingAs($admin)->get(route('admin.return-tracking'))->assertOk();
        $this->assertSame(5, $first->viewData('returns')->count());
        $this->assertSame(7, $first->viewData('returns')->total());

        $this->assertSame(2, $this->actingAs($admin)->get(route('admin.return-tracking', ['page' => 2]))->viewData('returns')->count());
        $this->actingAs($admin)->get(route('admin.return-tracking', ['status' => 'Pending']))
            ->assertSee('status=Pending&amp;page=2', false);
    }

    public function test_no_admin_page_has_the_header_booking_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['admin.notifications', 'admin.inventory.index', 'admin.client-records', 'admin.return-tracking', 'admin.gallery', 'admin.packages.index', 'admin.reports', 'admin.settings'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertDontSee('id="adminGlobalSearch"', false);
        }
    }
}

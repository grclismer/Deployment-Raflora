<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffWorkspaceComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $otherStaff;
    private User $clientUser;
    private Booking $booking;
    private InventoryItem $rose;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->makeUser('staff', 'staff.workspace@example.com');
        $this->otherStaff = $this->makeUser('staff', 'other.staff@example.com');
        $this->clientUser = $this->makeUser('client', 'client.workspace@example.com');

        $client = Client::create([
            'full_name' => 'Ana & John Reyes',
            'email' => 'client.workspace@example.com',
            'phone' => '09171234567',
        ]);

        $this->rose = InventoryItem::create([
            'name' => 'Red Rose',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 100,
            'unit_cost' => 25,
            'unit' => 'stems',
            'min_stock' => 10,
        ]);

        $this->booking = Booking::create([
            'client_id' => $client->id,
            'staff_id' => $this->staff->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(2)->toDateString(),
            'event_time' => '10:00:00',
            'venue' => 'The Emerald Ballroom',
            'status' => 'confirmed',
            'confirmed_at' => now()->subDay(),
        ]);

        BookingItem::create([
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $this->rose->id,
            'item_name' => 'Red Rose',
            'quantity' => 20,
            'quoted_unit_price' => 30,
            'confirmed_at' => now()->subDay(),
            'procurement_status' => 'ready',
        ]);
    }

    public function test_staff_can_view_all_workspace_modules(): void
    {
        $modules = [
            route('staff.dashboard'),
            route('staff.tasks'),
            route('staff.assignments'),
            route('staff.work-orders'),
            route('staff.dispatch'),
            route('staff.returns'),
            route('staff.checklist'),
            route('staff.messages'),
            route('staff.activity'),
            route('staff.calendar'),
            route('staff.guide'),
            route('staff.search'),
            route('staff.requests'),
        ];

        foreach ($modules as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
    }

    public function test_client_is_forbidden_from_all_workspace_modules(): void
    {
        $modules = [
            route('staff.dashboard'),
            route('staff.tasks'),
            route('staff.assignments'),
            route('staff.work-orders'),
            route('staff.dispatch'),
            route('staff.returns'),
            route('staff.checklist'),
            route('staff.messages'),
            route('staff.activity'),
            route('staff.calendar'),
            route('staff.guide'),
            route('staff.search'),
            route('staff.requests'),
        ];

        foreach ($modules as $url) {
            $this->actingAs($this->clientUser)->get($url)->assertForbidden();
        }
    }

    public function test_staff_dashboard_shows_accurate_metrics_and_assigned_event(): void
    {
        $response = $this->actingAs($this->staff)->get(route('staff.dashboard'));

        $response->assertOk()
            ->assertSee('Operational dashboard')
            ->assertSee('Assigned Events')
            ->assertSee('My Tasks')
            ->assertSee('Items to Prepare')
            ->assertSee('Dispatch Task')
            ->assertSee('Ana & John Reyes')
            ->assertSee('The Emerald Ballroom')
            ->assertSee('Preparation & Reservation')
            ->assertSee('Review event details and venue access');
    }

    public function test_staff_can_filter_tasks_by_view_and_type(): void
    {
        $this->actingAs($this->staff)
            ->get(route('staff.tasks', ['view' => 'today']))
            ->assertOk();

        $this->actingAs($this->staff)
            ->get(route('staff.tasks', ['type' => 'checklist']))
            ->assertOk()
            ->assertSee('Review event details and venue access');
    }

    public function test_staff_can_view_work_orders_for_assigned_events(): void
    {
        $response = $this->actingAs($this->staff)->get(route('staff.work-orders'));

        $response->assertOk()
            ->assertSee('WO-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('Ana & John Reyes')
            ->assertSee('Preparation Tasks')
            ->assertSee('Material Requirements')
            ->assertSee('Red Rose');
    }

    public function test_staff_can_search_events_and_tasks(): void
    {
        $response = $this->actingAs($this->staff)->get(route('staff.search', ['q' => 'Emerald']));

        $response->assertOk()
            ->assertSee('The Emerald Ballroom')
            ->assertSee('Ana & John Reyes');
    }

    public function test_staff_can_submit_inventory_request_without_mutating_stock(): void
    {
        $initialStock = $this->rose->current_stock;

        $response = $this->actingAs($this->staff)->post(route('staff.requests.inventory'), [
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $this->rose->id,
            'quantity' => 15,
            'note' => 'Extra table centerpieces requested by coordinator',
        ]);

        $response->assertRedirect(route('staff.requests'))
            ->assertSessionHas('success');

        $this->assertSame($initialStock, $this->rose->fresh()->current_stock);

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'inventory_request',
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $this->rose->id,
            'is_read' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->staff->id,
            'action' => 'inventory_requested',
            'module' => 'inventory',
        ]);
    }

    public function test_staff_can_report_operational_issue_to_admin(): void
    {
        $response = $this->actingAs($this->staff)->post(route('staff.requests.issue'), [
            'booking_id' => $this->booking->id,
            'category' => 'venue',
            'description' => 'Ballroom freight elevator under maintenance, loading delayed by 45 mins.',
        ]);

        $response->assertRedirect(route('staff.requests'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'staff_issue',
            'booking_id' => $this->booking->id,
            'is_read' => false,
        ]);
    }

    public function test_staff_cannot_submit_requests_for_unassigned_events(): void
    {
        $response = $this->actingAs($this->otherStaff)->post(route('staff.requests.inventory'), [
            'booking_id' => $this->booking->id,
            'inventory_item_id' => $this->rose->id,
            'quantity' => 5,
        ]);

        $response->assertNotFound();
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}

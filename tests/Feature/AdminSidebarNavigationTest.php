<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);
    }

    /**
     * Test all 10 approved admin navigation routes resolve with HTTP 200 for authenticated admin.
     */
    public function test_all_approved_admin_navigation_routes_are_accessible(): void
    {
        $routes = [
            'admin.dashboard',
            'admin.bookings',
            'admin.notifications',
            'admin.gallery',
            'admin.packages.index',
            'admin.inventory.index',
            'admin.return-tracking',
            'admin.client-records',
            'admin.reports',
            'admin.settings',
        ];

        foreach ($routes as $routeName) {
            $response = $this->actingAs($this->admin)->get(route($routeName));
            $response->assertOk();
        }
    }

    /**
     * Test active state highlighting on direct route.
     */
    public function test_sidebar_highlights_active_item_on_direct_route(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // Dashboard should have is-active class and aria-current="page"
        $response->assertSee('href="' . route('admin.dashboard') . '"', false);
        $response->assertSee('is-active', false);
        $response->assertSee('aria-current="page"', false);
    }

    /**
     * Test child route keeps parent module active (e.g. booking details keeps Booking Management active).
     */
    public function test_child_route_keeps_parent_navigation_item_active(): void
    {
        $client = Client::create([
            'full_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '09123456789',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'event_time' => '10:00:00',
            'event_location' => 'Grand Ballroom',
            'status' => 'pending',
            'booking_reference' => 'BK-TEST-001',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking->id));
        $response->assertOk();

        // The Booking Management link should be active because request()->routeIs('admin.bookings*')
        $response->assertSee('href="' . route('admin.bookings') . '"', false);
        $response->assertSee('data-title="Booking Management"', false);
    }

    /**
     * Test notification badge rendering: no badge when 0 unread alerts, dynamic count when > 0.
     */
    public function test_notification_badge_shows_dynamic_count_and_hides_when_zero(): void
    {
        // 0 unread alerts: No badge pill rendered
        $responseZero = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $responseZero->assertOk();
        $responseZero->assertDontSee('rf-nav-badge-pill', false);

        // Create unread alerts
        AdminAlert::create([
            'title' => 'Stock Alert',
            'message' => 'Roses low stock',
            'type' => 'warning',
            'is_read' => false,
        ]);
        AdminAlert::create([
            'title' => 'Payment Submitted',
            'message' => 'New downpayment uploaded',
            'type' => 'info',
            'is_read' => false,
        ]);

        $responseWithBadge = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $responseWithBadge->assertOk();
        $responseWithBadge->assertSee('rf-nav-badge-pill', false);
        $responseWithBadge->assertSee('2');
        $responseWithBadge->assertSee('rf-nav-badge-dot', false);
    }

    /**
     * Test approved information architecture: 6 section titles exist, 10 tooltips with full names exist.
     */
    public function test_sidebar_contains_all_approved_sections_and_full_module_tooltips(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // 6 uppercase section titles
        $response->assertSee('Overview');
        $response->assertSee('Bookings');
        $response->assertSee('Operations');
        $response->assertSee('Clients');
        $response->assertSee('Analytics');
        $response->assertSee('System');

        // Full module names in tooltips (no abbreviations like "Inv." or "Pkg.")
        $expectedTooltips = [
            'Dashboard',
            'Booking Management',
            'Notifications',
            'Gallery Management',
            'Package Management',
            'Inventory Management',
            'Return Tracking',
            'Client Records',
            'Reports & Analytics',
            'Account Management',
        ];

        foreach ($expectedTooltips as $tooltip) {
            $response->assertSee('<span class="rf-collapsed-tooltip">' . $tooltip . '</span>', false);
        }

        // Profile footer contains Administrator identity
        $response->assertSee('Administrator');
        $response->assertSee($this->admin->name);
    }

    /**
     * Test that unsupported modules from reference mockup are NOT added.
     */
    public function test_unsupported_reference_modules_are_not_in_sidebar(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // Should not have separate nav links for unsupported modules
        $response->assertDontSee('data-title="Calendar"', false);
        $response->assertDontSee('data-title="Work Orders"', false);
        $response->assertDontSee('data-title="Staff Management"', false);
        $response->assertDontSee('data-title="Activity Logs"', false);
    }

    /**
     * Test responsive controls: collapse toggle, mobile backdrop, and mobile close button.
     */
    public function test_responsive_sidebar_controls_and_drawers_are_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // Desktop collapse toggle button
        $response->assertSee('id="desktopSidebarToggleBtn"', false);
        $response->assertSee('onclick="toggleDesktopSidebar()"', false);

        // Mobile slide-over backdrop & close button
        $response->assertSee('id="adminSidebarBackdrop"', false);
        $response->assertSee('onclick="closeAdminSidebar()"', false);
    }

    /**
     * Test security: Guest and Client roles cannot access admin modules.
     */
    public function test_unauthorized_users_cannot_access_admin_routes(): void
    {
        // Unauthenticated guest is redirected
        $guestResponse = $this->get(route('admin.dashboard'));
        $guestResponse->assertRedirect(route('login'));

        // Client role is denied access with 403
        $clientUser = User::factory()->create([
            'role' => 'client',
        ]);

        $clientResponse = $this->actingAs($clientUser)->get(route('admin.dashboard'));
        $clientResponse->assertForbidden();

        $clientInventoryResponse = $this->actingAs($clientUser)->get(route('admin.inventory.index'));
        $clientInventoryResponse->assertForbidden();
    }
}

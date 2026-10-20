<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_renders_mobile_navigation_toggle_with_accessible_attributes(): void
    {
        $staff = $this->user('staff');

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();

        // Mobile toggle button
        $response->assertSee('id="mobileStaffToggle"', false);
        $response->assertSee('aria-controls="staffSidebar"', false);
        $response->assertSee('aria-expanded="false"', false);
        $response->assertSee('aria-label="Open Staff navigation"', false);

        // Sidebar off-canvas container
        $response->assertSee('id="staffSidebar"', false);
        $response->assertSee('aria-label="Staff navigation sidebar"', false);
        $response->assertSee('-translate-x-full', false);
        $response->assertSee('lg:translate-x-0', false);

        // Close button inside off-canvas sidebar
        $response->assertSee('id="closeStaffSidebarBtn"', false);
        $response->assertSee('aria-label="Close Staff navigation"', false);

        // Backdrop element
        $response->assertSee('id="staffSidebarBackdrop"', false);
    }

    public function test_staff_dashboard_preserves_existing_navigation_destinations(): void
    {
        $staff = $this->user('staff');

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();

        // Existing navigation destination: Workspace
        $response->assertSee(route('staff.dashboard'));
        $response->assertSee('Workspace');

        // Existing navigation destination: Log Out
        $response->assertSee(route('logout'));
        $response->assertSee('Log Out');

        // Verify no admin-specific routes are present
        $response->assertDontSee(route('admin.dashboard'));
        $response->assertDontSee(route('admin.inventory.index'));
        $response->assertDontSee(route('admin.reports'));
    }

    public function test_staff_event_show_renders_mobile_navigation_toggle_and_drawer(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));

        $response->assertOk();

        // Mobile toggle and sidebar elements present on event detail view
        $response->assertSee('id="mobileStaffToggle"', false);
        $response->assertSee('aria-controls="staffSidebar"', false);
        $response->assertSee('aria-label="Open Staff navigation"', false);
        $response->assertSee('id="staffSidebar"', false);
        $response->assertSee('id="closeStaffSidebarBtn"', false);
        $response->assertSee('id="staffSidebarBackdrop"', false);
    }

    public function test_guest_and_client_cannot_access_staff_views_or_navigation(): void
    {
        // Unauthenticated guest redirected to login
        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('login'));

        // Client forbidden from staff dashboard
        $client = $this->user('client');
        $this->actingAs($client)
            ->get(route('staff.dashboard'))
            ->assertForbidden();
    }

    public function test_phase_3_1b_5a_alerts_remain_rendered_alongside_mobile_navigation(): void
    {
        $staff = $this->user('staff');

        $response = $this->actingAs($staff)
            ->withSession([
                'success' => 'Checklist verified with responsive navigation active.',
                'warning' => 'Material return pending verification.',
            ])
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Checklist verified with responsive navigation active.');
        $response->assertSee('Material return pending verification.');
        $response->assertSee('id="mobileStaffToggle"', false);
        $response->assertSee('id="staffSidebar"', false);
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' User',
            'email' => $email ?? ($role . '-' . uniqid() . '@example.com'),
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function booking(int $staffId): Booking
    {
        return Booking::create([
            'staff_id' => $staffId,
            'event_type' => 'Staff Mobile Nav Event',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Skyline Pavilion',
            'status' => 'confirmed',
        ]);
    }
}

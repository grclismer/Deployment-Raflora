<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3_1bClientNavigationAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createClientUser(): User
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        Client::create([
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '09171234567',
            'address' => '123 Test Street, Manila',
        ]);

        return $user;
    }

    public function test_guest_desktop_navbar_shows_booking_link_and_no_client_links(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('BOOKING');
        $response->assertSee(route('booking.start'));
        $response->assertDontSee('MY BOOKINGS');
        $response->assertSee('Log In');
    }

    public function test_guest_mobile_panel_shows_booking_link(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="mobileMenuPanel"', false);
        $response->assertSee(route('booking.start'));
    }

    public function test_authenticated_client_desktop_navbar_shows_book_event_and_my_bookings(): void
    {
        $clientUser = $this->createClientUser();

        $response = $this->actingAs($clientUser)->get(route('home'));

        $response->assertOk();
        $response->assertSee('BOOK EVENT');
        $response->assertSee(route('booking.start'));
        $response->assertSee('MY BOOKINGS');
        $response->assertSee(route('bookings'));
        $response->assertDontSee('Log In');
    }

    public function test_authenticated_client_mobile_panel_shows_book_event_my_bookings_and_dashboard(): void
    {
        $clientUser = $this->createClientUser();

        $response = $this->actingAs($clientUser)->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="mobileMenuPanel"', false);
        $response->assertSee(route('booking.start'));
        $response->assertSee(route('bookings'));
        $response->assertSee(route('client.dashboard'));
    }

    public function test_avatar_dropdown_for_client_contains_organized_links(): void
    {
        $clientUser = $this->createClientUser();

        $response = $this->actingAs($clientUser)->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('bookings'));
        $response->assertSee(route('client.dashboard'));
        $response->assertSee(route('booking-history'));
        $response->assertSee(route('account-settings'));
        $response->assertSee(route('logout'));
        $response->assertSee('Log Out');
    }

    public function test_admin_and_staff_navbar_do_not_expose_client_booking_links(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $responseAdmin = $this->actingAs($admin)->get(route('home'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('ADMIN DASHBOARD');
        $responseAdmin->assertSee(route('admin.dashboard'));
        $responseAdmin->assertDontSee('MY BOOKINGS');

        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $responseStaff = $this->actingAs($staff)->get(route('home'));
        $responseStaff->assertOk();
        $responseStaff->assertSee('STAFF DASHBOARD');
        $responseStaff->assertSee(route('staff.dashboard'));
        $responseStaff->assertDontSee('MY BOOKINGS');
    }

    public function test_client_dashboard_displays_prominent_book_new_event_cta_and_view_all_link(): void
    {
        $clientUser = $this->createClientUser();

        $response = $this->actingAs($clientUser)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertSee('Book New Event');
        $response->assertSee(route('booking.start'));
        $response->assertSee('View all');
        $response->assertSee(route('bookings'));
    }

    public function test_client_can_access_booking_start_and_redirects_to_bookings_create(): void
    {
        $clientUser = $this->createClientUser();

        $response = $this->actingAs($clientUser)->get(route('booking.start'));

        $response->assertRedirect(route('bookings.create'));
    }

    public function test_client_can_access_bookings_index(): void
    {
        $clientUser = $this->createClientUser();

        $response = $this->actingAs($clientUser)->get(route('bookings'));

        $response->assertOk();
        $response->assertSee('My Bookings');
    }
}

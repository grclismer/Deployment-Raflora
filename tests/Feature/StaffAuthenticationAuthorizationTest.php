<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAuthenticationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_reaches_admin_dashboard(): void
    {
        $admin    = $this->user('admin');
        $rawToken = $this->makeTrustedDevice($admin);

        $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_login_reaches_staff_landing(): void
    {
        $staff    = $this->user('staff');
        $rawToken = $this->makeTrustedDevice($staff);

        $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $staff->email,
                'password' => 'password',
            ])->assertRedirect(route('staff.dashboard'));
    }

    public function test_staff_dashboard_shows_operational_empty_state_without_admin_navigation(): void
    {
        $response = $this->actingAs($this->user('staff'))->get(route('staff.dashboard'));

        $response->assertOk()
            ->assertSee('Operational dashboard')
            ->assertSee('No active assigned events')
            ->assertSee('Assignments are managed by Admin')
            ->assertDontSee('Reports &amp; Analytics')
            ->assertDontSee('Inventory Management');
    }

    public function test_client_login_reaches_client_dashboard(): void
    {
        $client = $this->user('client');

        $this->post(route('login.attempt'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('client.dashboard'));
    }

    public function test_client_login_ignores_staff_intended_url(): void
    {
        $client = $this->user('client');

        $this->withSession(['url.intended' => route('staff.dashboard')])
            ->post(route('login.attempt'), [
                'email' => $client->email,
                'password' => 'password',
            ])->assertRedirect(route('client.dashboard'));
    }

    public function test_staff_login_ignores_admin_intended_url(): void
    {
        $staff    = $this->user('staff');
        $rawToken = $this->makeTrustedDevice($staff);

        $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.attempt'), [
                'email'    => $staff->email,
                'password' => 'password',
            ])->assertRedirect(route('staff.dashboard'));
    }

    public function test_admin_login_honors_admin_intended_url(): void
    {
        $admin    = $this->user('admin');
        $rawToken = $this->makeTrustedDevice($admin);

        $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->withSession(['url.intended' => route('admin.bookings')])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ])->assertRedirect(route('admin.bookings'));
    }

    public function test_logout_clears_stale_intended_url_before_next_login(): void
    {
        $client = $this->user('client');

        $this->actingAs($client)
            ->withSession(['url.intended' => route('staff.dashboard')])
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->post(route('login.attempt'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('client.dashboard'));
    }

    public function test_staff_can_access_staff_landing_but_not_admin_routes(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.bookings'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.quotations'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.reports'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings'))->assertForbidden();
    }

    public function test_client_cannot_access_staff_or_admin_routes(): void
    {
        $client = $this->user('client');

        $this->actingAs($client)->get(route('staff.dashboard'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_guests_are_redirected_from_staff_and_admin_routes(): void
    {
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_access_admin_routes_and_staff_landing_for_supervision(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('staff.dashboard'))->assertOk();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'name'              => ucfirst($role) . ' User',
            'email'             => $role . '@example.com',
            'password'          => 'password',
            'role'              => $role,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Create a valid trusted-device record for a user and return the raw token.
     * This allows login tests to proceed directly to the dashboard (bypassing OTP)
     * when we are testing role-based routing, not the device-verification flow itself.
     */
    private function makeTrustedDevice(User $user): string
    {
        $service  = new TrustedDeviceService();
        $rawToken = $service->generateToken();

        TrustedDevice::create([
            'user_id'           => $user->id,
            'device_token_hash' => $service->hashToken($rawToken),
            'device_name'       => 'Test Browser',
            'ip_address'        => '127.0.0.1',
            'user_agent'        => 'PHPUnit',
            'expires_at'        => $service->trustExpiresAt(),
        ]);

        return $rawToken;
    }
}

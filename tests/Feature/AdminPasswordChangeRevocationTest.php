<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A successful password change in Account Settings signs out the Admin's other sessions and
 * revokes their trusted devices, as OTP reset and recovery already do; the current session
 * stays signed in. A rejected change touches nothing. Runs on the database session driver
 * so real session rows are created, read and removed.
 */
class AdminPasswordChangeRevocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->forgetSessionState();

        $this->admin = User::factory()->create([
            'role' => 'admin', 'is_bootstrap' => false, 'email_verified_at' => now(),
            'password' => Hash::make('AdminPassword123!'), 'email' => 'admin@raflora.com',
        ]);
        $this->otherUser = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
    }

    /** A real stored session that authenticates $user when its id is sent as the session cookie. */
    private function storedSession(User $user): string
    {
        $id = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.5', 'user_agent' => 'Other Browser',
            'payload' => base64_encode(serialize(['_token' => Str::random(40), Auth::guard('web')->getName() => $user->id])),
            'last_activity' => now()->timestamp,
        ]);

        return $id;
    }

    private function device(User $user, string $token): TrustedDevice
    {
        return TrustedDevice::create([
            'user_id' => $user->id, 'device_token_hash' => hash('sha256', $token), 'device_name' => 'Device ' . $token,
            'ip_address' => '10.0.0.5', 'user_agent' => 'Mozilla/5.0', 'last_used_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
    }

    /** A fresh request using only the given session cookie (no actingAs), to see whether it is still signed in. */
    private function openSettingsWithSession(string $sessionId)
    {
        $this->forgetSessionState();

        return $this->withCookie(config('session.cookie'), $sessionId)->get(route('admin.settings'));
    }

    /** Each simulated browser starts from a fresh guard and session store, as a separate request would. */
    private function forgetSessionState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('redirect');   // the redirector keeps the store it was built with
        $this->defaultCookies = [];
    }

    private function changePassword(array $input)
    {
        $this->forgetSessionState();

        return $this->actingAs($this->admin)
            ->withSession([Auth::guard('web')->getName() => $this->admin->id])
            ->from(route('admin.settings'))
            ->post(route('admin.settings.password.update'), $input);
    }

    public function test_successful_change_signs_out_other_sessions_and_revokes_trusted_devices(): void
    {
        $otherSession = $this->storedSession($this->admin);
        $foreignSession = $this->storedSession($this->otherUser);
        $laptop = $this->device($this->admin, 'admin-laptop');
        $phone = $this->device($this->admin, 'admin-phone');
        $foreignDevice = $this->device($this->otherUser, 'staff-desktop');

        // The other session really is signed in beforehand.
        $this->openSettingsWithSession($otherSession)->assertOk();

        $this->changePassword(['current_password' => 'AdminPassword123!', 'password' => 'BrandNewPassword888!', 'password_confirmation' => 'BrandNewPassword888!'])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success', 'Your password has been updated. Your other sessions were signed out and your trusted devices were revoked.');

        $this->assertTrue(Hash::check('BrandNewPassword888!', $this->admin->fresh()->password));

        // Other session row gone; the current session is the only one left for the Admin, and still works.
        $this->assertDatabaseMissing('sessions', ['id' => $otherSession]);
        $remaining = DB::table('sessions')->where('user_id', $this->admin->id)->pluck('id');
        $this->assertCount(1, $remaining);
        $this->openSettingsWithSession($remaining->first())->assertOk();

        // The other browser's cookie no longer signs anyone in, including on a later visit.
        $this->assertNotSame(200, $this->openSettingsWithSession($otherSession)->getStatusCode());
        $this->assertNotSame(200, $this->openSettingsWithSession($otherSession)->getStatusCode());

        // Trusted devices: all of the Admin's revoked and no longer usable.
        foreach ([$laptop, $phone] as $device) {
            $this->assertNotNull($device->fresh()->revoked_at);
            $this->assertNull(app(TrustedDeviceService::class)->findUsableForUser($this->admin, 'admin-' . ($device->is($laptop) ? 'laptop' : 'phone')));
        }

        // Another user's session and device are untouched.
        $this->assertDatabaseHas('sessions', ['id' => $foreignSession]);
        $this->assertNull($foreignDevice->fresh()->revoked_at);

        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->admin->id, 'action' => 'password_updated']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->admin->id, 'action' => 'sessions_revoked']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->admin->id, 'action' => 'trusted_devices_revoked']);
    }

    public function test_backward_compatible_route_applies_the_same_revocation(): void
    {
        $otherSession = $this->storedSession($this->admin);
        $device = $this->device($this->admin, 'admin-laptop');

        $this->actingAs($this->admin)->post(route('admin.account.password'), [
            'current_password' => 'AdminPassword123!', 'password' => 'LegacyRoutePassword999!', 'password_confirmation' => 'LegacyRoutePassword999!',
        ])->assertRedirect(route('admin.settings'));

        $this->assertDatabaseMissing('sessions', ['id' => $otherSession]);
        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_rejected_changes_leave_password_sessions_and_devices_untouched(): void
    {
        $otherSession = $this->storedSession($this->admin);
        $device = $this->device($this->admin, 'admin-laptop');

        $attempts = [
            'wrong current password' => [['current_password' => 'Wrong123!', 'password' => 'BrandNewPassword888!', 'password_confirmation' => 'BrandNewPassword888!'], 'current_password'],
            'confirmation mismatch' => [['current_password' => 'AdminPassword123!', 'password' => 'BrandNewPassword888!', 'password_confirmation' => 'Different888!'], 'password'],
            'too short' => [['current_password' => 'AdminPassword123!', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
        ];

        foreach ($attempts as $label => [$input, $field]) {
            $this->changePassword($input)->assertSessionHasErrors($field)->assertSessionMissing('_old_input.password');

            $this->assertTrue(Hash::check('AdminPassword123!', $this->admin->fresh()->password), $label);
            $this->assertDatabaseHas('sessions', ['id' => $otherSession]);
            $this->assertNull($device->fresh()->revoked_at, $label);
        }

        $this->openSettingsWithSession($otherSession)->assertOk();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'trusted_devices_revoked']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sessions_revoked']);
    }

    public function test_staff_cannot_change_the_admin_password_or_trigger_revocation(): void
    {
        $adminSession = $this->storedSession($this->admin);
        $device = $this->device($this->admin, 'admin-laptop');
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now(), 'password' => Hash::make('StaffPassword123!')]);

        foreach (['admin.settings.password.update', 'admin.account.password'] as $route) {
            $this->actingAs($staff)->post(route($route), [
                'current_password' => 'StaffPassword123!', 'password' => 'BrandNewPassword888!', 'password_confirmation' => 'BrandNewPassword888!',
            ])->assertForbidden();
        }

        $this->assertTrue(Hash::check('StaffPassword123!', $staff->fresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => $adminSession]);
        $this->assertNull($device->fresh()->revoked_at);
    }
}

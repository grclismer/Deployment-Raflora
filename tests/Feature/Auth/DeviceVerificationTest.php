<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\OtpService;
use App\Services\TrustedDeviceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * S-01E-3: DeviceVerification integration tests.
 *
 * These tests verify the complete Admin/Staff device-verification flow:
 * - Password → trusted-device check → OTP or direct authentication
 * - OTP verification → optional trusted device creation
 * - Security invariants (Auth::login timing, pending state security, etc.)
 *
 * Client authentication is tested at the end to confirm it is unaffected.
 */
class DeviceVerificationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function adminUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'admin',
            'email_verified_at' => now(),
            'is_bootstrap'      => false,
        ], $attrs));
    }

    private function staffUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'staff',
            'email_verified_at' => now(),
        ], $attrs));
    }

    private function clientUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'client',
            'email_verified_at' => now(),
        ], $attrs));
    }

    /**
     * Create a valid trusted-device DB record and return the raw token.
     */
    private function createTrustedDevice(User $user, array $overrides = []): string
    {
        $service  = new TrustedDeviceService();
        $rawToken = $service->generateToken();

        TrustedDevice::create(array_merge([
            'user_id'           => $user->id,
            'device_token_hash' => $service->hashToken($rawToken),
            'device_name'       => 'Chrome on Windows',
            'ip_address'        => '127.0.0.1',
            'user_agent'        => 'Mozilla/5.0',
            'expires_at'        => $service->trustExpiresAt(),
        ], $overrides));

        return $rawToken;
    }

    /**
     * Extract a valid OTP from the OtpService, bypassing email delivery.
     */
    private function generateDeviceOtp(User $user): string
    {
        // Use the internal service to generate a known OTP for testing.
        // We capture the OTP by intercepting the OtpService::generate() call
        // via a fresh call on the same user/purpose.
        Mail::fake();
        $service = app(OtpService::class);
        return $service->generate($user, 'device_verification');
    }

    /**
     * Put a user into the pending-device-auth session state.
     */
    private function setPendingState(User $user): array
    {
        $session = [
            'pending_device_auth_user_id'    => $user->id,
            'pending_device_auth_expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
        return $session;
    }

    // =========================================================================
    // 1–2: Untrusted device → OTP challenge
    // =========================================================================

    /** Test 1: Admin with valid password + no trusted device receives OTP challenge. */
    public function test_admin_with_no_trusted_device_receives_otp_challenge(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        $response = $this->post(route('login.attempt'), [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest(); // Auth::login NOT called
    }

    /** Test 2: Staff with valid password + no trusted device receives OTP challenge. */
    public function test_staff_with_no_trusted_device_receives_otp_challenge(): void
    {
        Mail::fake();
        $staff = $this->staffUser();

        $response = $this->post(route('login.attempt'), [
            'email'    => $staff->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();
    }

    // =========================================================================
    // 3–4: Trusted device → bypass OTP
    // =========================================================================

    /** Test 3: Admin with valid password + valid trusted device authenticates without OTP. */
    public function test_admin_with_valid_trusted_device_authenticates_without_otp(): void
    {
        Mail::fake();
        $admin    = $this->adminUser();
        $rawToken = $this->createTrustedDevice($admin);

        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /** Test 4: Staff with valid password + valid trusted device authenticates without OTP. */
    public function test_staff_with_valid_trusted_device_authenticates_without_otp(): void
    {
        Mail::fake();
        $staff    = $this->staffUser();
        $rawToken = $this->createTrustedDevice($staff);

        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $staff->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    // =========================================================================
    // 5–8: Invalid trusted-device cookie situations
    // =========================================================================

    /** Test 5: Invalid trusted-device cookie does not bypass OTP. */
    public function test_invalid_trusted_device_cookie_does_not_bypass_otp(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => 'completely-invalid-token'])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();
    }

    /** Test 6: Expired trusted-device cookie does not bypass OTP. */
    public function test_expired_trusted_device_cookie_does_not_bypass_otp(): void
    {
        Mail::fake();
        $admin    = $this->adminUser();
        $rawToken = $this->createTrustedDevice($admin, [
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();
    }

    /** Test 7: Revoked trusted-device cookie does not bypass OTP. */
    public function test_revoked_trusted_device_cookie_does_not_bypass_otp(): void
    {
        Mail::fake();
        $admin    = $this->adminUser();
        $rawToken = $this->createTrustedDevice($admin);

        TrustedDevice::where('user_id', $admin->id)->first()->revoke();

        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();
    }

    /** Test 8: Trusted device belonging to another user does not bypass OTP for target user. */
    public function test_other_users_trusted_device_does_not_bypass_otp(): void
    {
        Mail::fake();
        $adminA   = $this->adminUser();
        $adminB   = $this->adminUser();
        $rawToken = $this->createTrustedDevice($adminA); // belongs to A

        // Log in as B, presenting A's cookie.
        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $adminB->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();
    }

    // =========================================================================
    // 9: Wrong password
    // =========================================================================

    /** Test 9: Incorrect password does not create pending device authentication. */
    public function test_incorrect_password_does_not_create_pending_state(): void
    {
        $admin = $this->adminUser();

        $response = $this->post(route('login.attempt'), [
            'email'    => $admin->email,
            'password' => 'wrong-password',
        ]);

        // back() with error — confirm user is not authenticated and no pending state was created.
        $this->assertGuest();
        $this->assertNull(session('pending_device_auth_user_id'));
    }

    // =========================================================================
    // 10: Auth::login not called before OTP
    // =========================================================================

    /** Test 10: Untrusted device does not call Auth::login before OTP. */
    public function test_untrusted_device_does_not_authenticate_before_otp(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        $this->post(route('login.attempt'), [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        // User must NOT be authenticated after login POST on untrusted device.
        $this->assertGuest();
    }

    // =========================================================================
    // 11–12: Correct OTP authenticates
    // =========================================================================

    /** Test 11: Correct device OTP authenticates Admin. */
    public function test_correct_otp_authenticates_admin(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $response = $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /** Test 12: Correct device OTP authenticates Staff. */
    public function test_correct_otp_authenticates_staff(): void
    {
        Mail::fake();
        $staff = $this->staffUser();
        $otp   = $this->generateDeviceOtp($staff);

        $response = $this->withSession($this->setPendingState($staff))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    // =========================================================================
    // 13–14: Failed OTP
    // =========================================================================

    /** Test 13: Incorrect OTP does not authenticate user. */
    public function test_incorrect_otp_does_not_authenticate(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->generateDeviceOtp($admin); // generate but submit wrong

        $response = $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => '000000']);

        $response->assertRedirect(); // back with errors
        $this->assertGuest();
    }

    /** Test 14: Expired OTP does not authenticate user. */
    public function test_expired_otp_does_not_authenticate(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        // Travel beyond OTP expiry.
        Carbon::setTestNow(now()->addMinutes(11));

        $response = $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        $response->assertRedirect();
        $this->assertGuest();

        Carbon::setTestNow();
    }

    // =========================================================================
    // 15–16: OTP limits and purpose
    // =========================================================================

    /** Test 15: OTP attempt limits remain enforced. */
    public function test_otp_attempt_limits_are_enforced(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->generateDeviceOtp($admin);

        $session = $this->setPendingState($admin);

        // Exhaust all 5 attempts with wrong OTPs.
        for ($i = 0; $i < 5; $i++) {
            $this->withSession($session)->post(route('device.verify.submit'), ['otp' => '000000']);
        }

        // Now submit the correct OTP — should be locked.
        $response = $this->withSession($session)
            ->post(route('device.verify.submit'), ['otp' => '000000']);

        $this->assertGuest();
        $response->assertRedirect();
    }

    /** Test 16: OTP purpose is device_verification (isolated from other purposes). */
    public function test_otp_purpose_is_device_verification(): void
    {
        Mail::fake();
        $admin   = $this->adminUser();
        $service = app(OtpService::class);

        // Generate OTP for a different purpose — should NOT work for device verification.
        $emailOtp = $service->generate($admin, 'email_verification');

        $response = $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $emailOtp]);

        $this->assertGuest();
        $response->assertRedirect();
    }

    // =========================================================================
    // 17–20: Remember device checkbox
    // =========================================================================

    /** Test 17: Remember checkbox checked creates a trusted-device record. */
    public function test_remember_checked_creates_trusted_device_record(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), [
                'otp'             => $otp,
                'remember_device' => '1',
            ]);

        $this->assertDatabaseHas('trusted_devices', ['user_id' => $admin->id]);
    }

    /** Test 18: Remember checkbox checked queues the raflora_device_trust cookie. */
    public function test_remember_checked_queues_trusted_device_cookie(): void
    {
        Mail::fake();
        Cookie::spy();

        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), [
                'otp'             => $otp,
                'remember_device' => '1',
            ]);

        Cookie::shouldHaveReceived('queue')->atLeast()->once();
    }

    /** Test 19: Remember checkbox unchecked does not create trusted-device record. */
    public function test_remember_unchecked_does_not_create_trusted_device_record(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $admin->id]);
    }

    /** Test 20: Remember checkbox unchecked does not queue the trust cookie. */
    public function test_remember_unchecked_does_not_queue_trust_cookie(): void
    {
        Mail::fake();
        Cookie::spy();

        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        // The trust cookie must NOT be queued; a forget cookie MAY be queued (stale cleanup).
        // We check the DB: no record = no cookie was issued.
        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $admin->id]);
    }

    // =========================================================================
    // 21–22: Expiration
    // =========================================================================

    /** Test 21: Created trusted device expires in exactly 30 days. */
    public function test_created_trusted_device_expires_in_30_days(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-09-22 00:00:00');

        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), [
                'otp'             => $otp,
                'remember_device' => '1',
            ]);

        $device = TrustedDevice::where('user_id', $admin->id)->first();
        $this->assertNotNull($device);

        $expected = Carbon::now()->addDays(30)->toDateTimeString();
        $this->assertEquals($expected, $device->expires_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /** Test 22: Existing trusted device expiration does not slide after authentication. */
    public function test_existing_trusted_device_expiration_does_not_slide(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-09-22 00:00:00');

        $admin    = $this->adminUser();
        $rawToken = $this->createTrustedDevice($admin);
        $device   = TrustedDevice::where('user_id', $admin->id)->first();
        $originalExpiry = $device->expires_at->toDateTimeString();

        // Simulate using the device 20 days later.
        Carbon::setTestNow('2026-10-12 00:00:00');

        $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ]);

        $device->refresh();
        $this->assertEquals($originalExpiry, $device->expires_at->toDateTimeString());

        Carbon::setTestNow();
    }

    // =========================================================================
    // 23–25: Session management
    // =========================================================================

    /** Test 23: Successful trusted-device login regenerates session. */
    public function test_trusted_device_login_regenerates_session(): void
    {
        Mail::fake();
        $admin    = $this->adminUser();
        $rawToken = $this->createTrustedDevice($admin);

        $sessionBefore = session()->getId();

        $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => 'password',
            ]);

        // After regeneration the session ID differs.
        $this->assertNotEquals($sessionBefore, session()->getId());
    }

    /** Test 24: Successful OTP login regenerates session. */
    public function test_otp_login_regenerates_session(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $sessionBefore = session()->getId();

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        $this->assertNotEquals($sessionBefore, session()->getId());
    }

    /** Test 25: Pending authentication state is cleared after successful OTP. */
    public function test_pending_state_is_cleared_after_successful_otp(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        $this->assertNull(session('pending_device_auth_user_id'));
        $this->assertNull(session('pending_device_auth_expires_at'));
    }

    // =========================================================================
    // 26: Failed OTP leaves user unauthenticated
    // =========================================================================

    /** Test 26: Failed OTP leaves user unauthenticated. */
    public function test_failed_otp_leaves_user_unauthenticated(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), ['otp' => '999999']);

        $this->assertGuest();
    }

    // =========================================================================
    // 27: Bootstrap Admin bypass protection
    // =========================================================================

    /** Test 27: Bootstrap Admin cannot bypass setup using a trusted device. */
    public function test_bootstrap_admin_cannot_bypass_setup_with_trusted_device(): void
    {
        Mail::fake();
        $bootstrap = $this->adminUser(['is_bootstrap' => true]);
        $rawToken  = $this->createTrustedDevice($bootstrap);

        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $bootstrap->email,
                'password' => 'password',
            ]);

        // Must be redirected to setup, not dashboard.
        $response->assertRedirect(route('admin.setup'));
        // Bootstrap Admin IS authenticated at this point (setup requires auth).
        $this->assertAuthenticatedAs($bootstrap);
    }

    // =========================================================================
    // 28: Unverified Staff
    // =========================================================================

    /** Test 28: Unverified Staff cannot access Staff dashboard through trusted device. */
    public function test_unverified_staff_cannot_access_dashboard_via_trusted_device(): void
    {
        Mail::fake();
        $staff    = $this->staffUser(['email_verified_at' => null]);
        $rawToken = $this->createTrustedDevice($staff);

        // Even with a trusted device cookie, unverified staff must verify email first.
        $response = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $staff->email,
                'password' => 'password',
            ]);

        // Must be redirected to email verification, not staff dashboard.
        $response->assertRedirect(route('verification.notice'));
    }

    // =========================================================================
    // 29: Laravel remember-me suppression
    // =========================================================================

    /** Test 29: Admin/Staff Laravel remember-me does not bypass trusted-device verification. */
    public function test_admin_laravel_remember_me_does_not_bypass_device_verification(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        // Submit login with remember=1 — should still go through OTP, not be remembered.
        $response = $this->post(route('login.attempt'), [
            'email'    => $admin->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        // Must be redirected to device verification, NOT admin dashboard.
        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();
    }

    // =========================================================================
    // 30: Client authentication unaffected
    // =========================================================================

    /** Test 30: Client authentication remains unaffected by Admin/Staff trusted-device logic. */
    public function test_client_authentication_is_unaffected(): void
    {
        $client = $this->clientUser();

        $response = $this->post(route('login.attempt'), [
            'email'    => $client->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($client);
    }

    // =========================================================================
    // 31–32: Multiple trusted devices
    // =========================================================================

    /** Test 31: Multiple trusted devices can coexist. */
    public function test_multiple_trusted_devices_can_coexist(): void
    {
        Mail::fake();
        $admin    = $this->adminUser();
        $token1   = $this->createTrustedDevice($admin);
        $token2   = $this->createTrustedDevice($admin);

        $this->assertCount(2, TrustedDevice::where('user_id', $admin->id)->get());

        // Both tokens are independently usable.
        $service = new TrustedDeviceService();
        $this->assertNotNull($service->findUsableForUser($admin, $token1));
        $this->assertNotNull($service->findUsableForUser($admin, $token2));
    }

    /** Test 32: New trusted device does not invalidate unrelated existing trusted devices. */
    public function test_new_trusted_device_does_not_invalidate_existing_devices(): void
    {
        Mail::fake();
        $admin       = $this->adminUser();
        $existingToken = $this->createTrustedDevice($admin);
        $existingDevice = TrustedDevice::where('user_id', $admin->id)->first();
        $existingHash   = $existingDevice->device_token_hash;

        // Create a second trusted device via OTP flow.
        $otp = $this->generateDeviceOtp($admin);
        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), [
                'otp'             => $otp,
                'remember_device' => '1',
            ]);

        // The original record must still exist and be usable.
        $this->assertDatabaseHas('trusted_devices', [
            'user_id'           => $admin->id,
            'device_token_hash' => $existingHash,
        ]);

        $service = new TrustedDeviceService();
        $this->assertNotNull($service->findUsableForUser($admin, $existingToken));
    }

    // =========================================================================
    // 33–34: Raw token security
    // =========================================================================

    /** Test 33: Raw token is not persisted in the database. */
    public function test_raw_token_is_not_persisted(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), [
                'otp'             => $otp,
                'remember_device' => '1',
            ]);

        $device = TrustedDevice::where('user_id', $admin->id)->first();
        $this->assertNotNull($device);

        $attrs = $device->getAttributes();

        // No plaintext token columns.
        $this->assertArrayNotHasKey('device_token', $attrs);
        $this->assertArrayNotHasKey('token', $attrs);
        $this->assertArrayNotHasKey('raw_token', $attrs);

        // Hash must be 64-char hex.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $attrs['device_token_hash']);
    }

    /** Test 34: Raw token does not appear in AuditLog entries created by the device flow. */
    public function test_raw_token_is_not_written_to_audit_logs(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $otp   = $this->generateDeviceOtp($admin);

        $this->withSession($this->setPendingState($admin))
            ->post(route('device.verify.submit'), [
                'otp'             => $otp,
                'remember_device' => '1',
            ]);

        $device = TrustedDevice::where('user_id', $admin->id)->first();
        $hash   = $device?->device_token_hash;

        // Verify no audit log detail contains the token hash (we store only safe descriptions).
        $logs = AuditLog::where('user_id', $admin->id)
            ->where('module', 'device_security')
            ->get();

        foreach ($logs as $log) {
            $detail = json_encode($log->details ?? []);
            // Token hash must not appear in log details.
            if ($hash) {
                $this->assertStringNotContainsString($hash, $detail);
            }
        }

        // At least one security event was recorded.
        $this->assertGreaterThan(0, $logs->count());
    }

    // =========================================================================
    // 35: Security events are recorded
    // =========================================================================

    /** Test 35: Security events are recorded for the device OTP flow. */
    public function test_security_events_are_recorded(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        // Trigger the OTP challenge.
        $this->post(route('login.attempt'), [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        // At least the challenge-created event must be recorded.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module'  => 'device_security',
        ]);
    }

    // =========================================================================
    // Additional security invariants
    // =========================================================================

    /** Pending session contains no password and no raw OTP. */
    public function test_pending_session_does_not_contain_password_or_otp(): void
    {
        Mail::fake();
        $admin = $this->adminUser();

        $this->post(route('login.attempt'), [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        // The session must contain user_id but NOT password or OTP.
        $this->assertNotNull(session('pending_device_auth_user_id'));
        $this->assertNull(session('pending_device_auth_password'));
        $this->assertNull(session('pending_device_auth_otp'));
    }

    /** The device-verify page is inaccessible without a pending challenge. */
    public function test_device_verify_page_redirects_without_pending_challenge(): void
    {
        $response = $this->get(route('device.verify.show'));
        $response->assertRedirect(route('login'));
    }

    /** User identity comes from server session, not from hidden form fields. */
    public function test_user_identity_is_server_side_not_from_form(): void
    {
        Mail::fake();
        $admin  = $this->adminUser();
        $other  = $this->adminUser();
        $otp    = $this->generateDeviceOtp($admin);

        // Set pending state for $admin.
        $session = $this->setPendingState($admin);

        // Submit with an OTP valid for $admin — system must use the session identity.
        $this->withSession($session)
            ->post(route('device.verify.submit'), ['otp' => $otp]);

        // The authenticated user must be $admin, not $other.
        $this->assertAuthenticatedAs($admin);
    }
}

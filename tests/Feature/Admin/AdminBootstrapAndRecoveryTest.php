<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRecoveryCode;
use App\Models\AuditLog;
use App\Models\EmailVerification;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBootstrapAndRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /* =========================================================================
     * SECTION 1: DEFAULT ADMIN SEEDING & BOOTSTRAP GATE (Scenarios 1-4)
     * ========================================================================= */

    public function test_1_and_2_default_admin_seeds_with_is_bootstrap_true_and_unverified_email(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_bootstrap);
        $this->assertTrue($admin->isBootstrapAdmin());
        $this->assertNull($admin->email_verified_at);
        $this->assertEquals('admin@raflora.com', $admin->email);
    }

    public function test_3_default_admin_login_redirects_to_admin_setup(): void
    {
        $this->seed(AdminSeeder::class);

        $response = $this->post(route('login.attempt'), [
            'email' => 'admin@raflora.com',
            'password' => 'Admin@12345',
        ]);

        $response->assertRedirect(route('admin.setup'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isBootstrapAdmin());
    }

    public function test_4_and_51_direct_dashboard_and_operations_access_blocked_for_bootstrap_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        // Attempt direct access to admin dashboard
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.setup'));

        // Attempt direct access to bookings
        $response2 = $this->actingAs($admin)->get(route('admin.bookings'));
        $response2->assertRedirect(route('admin.setup'));

        // Attempt direct access to staff dashboard
        $response3 = $this->actingAs($admin)->get(route('staff.dashboard'));
        $response3->assertRedirect(route('admin.setup'));
    }

    /* =========================================================================
     * SECTION 2: BOOTSTRAP ACTIVE EMAIL & OTP (Scenarios 5-12)
     * ========================================================================= */

    public function test_5_and_6_active_email_is_required_and_must_be_valid(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        // Empty email
        $response = $this->actingAs($admin)->post(route('admin.setup.email'), [
            'email' => '',
        ]);
        $response->assertSessionHasErrors('email');

        // Invalid email format
        $response = $this->actingAs($admin)->post(route('admin.setup.email'), [
            'email' => 'not-an-email',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_7_duplicate_email_is_rejected_in_bootstrap(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        User::factory()->create([
            'email' => 'client.existing@example.com',
            'role' => 'client',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.setup.email'), [
            'email' => 'client.existing@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_8_bootstrap_otp_is_generated_for_admin_bootstrap_purpose(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.setup.email'), [
            'email' => 'active.admin@raflora.com',
        ]);

        $response->assertRedirect(route('admin.setup'));
        $response->assertSessionHas('status');

        $this->assertEquals('active.admin@raflora.com', session('admin_bootstrap_email'));

        $record = EmailVerification::where('user_id', $admin->id)
            ->where('purpose', 'admin_bootstrap')
            ->first();

        $this->assertNotNull($record);
        $this->assertFalse($record->isVerified());
        $this->assertEquals(64, strlen($record->otp_hash)); // SHA-256 hashed
    }

    public function test_9_incorrect_bootstrap_otp_fails(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)->withSession(['admin_bootstrap_email' => 'active.admin@raflora.com']);

        // Generate OTP
        app(\App\Services\OtpService::class)->generate($admin, 'admin_bootstrap');

        $response = $this->post(route('admin.setup.otp'), [
            'otp' => '000000',
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertFalse(session('admin_bootstrap_email_verified', false));
    }

    public function test_10_expired_bootstrap_otp_fails(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)->withSession(['admin_bootstrap_email' => 'active.admin@raflora.com']);

        // Generate OTP and manually expire it
        app(\App\Services\OtpService::class)->generate($admin, 'admin_bootstrap');
        EmailVerification::where('user_id', $admin->id)
            ->where('purpose', 'admin_bootstrap')
            ->update(['expires_at' => now()->subMinutes(5)]);

        $response = $this->post(route('admin.setup.otp'), [
            'otp' => '123456',
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertFalse(session('admin_bootstrap_email_verified', false));
    }

    public function test_11_bootstrap_otp_attempt_limits_work(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)->withSession(['admin_bootstrap_email' => 'active.admin@raflora.com']);
        app(\App\Services\OtpService::class)->generate($admin, 'admin_bootstrap');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.setup.otp'), ['otp' => '999999']);
        }

        // 6th attempt should be blocked due to maximum attempts exceeded
        $response = $this->post(route('admin.setup.otp'), ['otp' => '999999']);
        $response->assertSessionHasErrors('otp');

        $record = EmailVerification::where('user_id', $admin->id)
            ->where('purpose', 'admin_bootstrap')
            ->first();
        $this->assertGreaterThanOrEqual(5, $record->attempts);
    }

    public function test_12_correct_otp_verifies_email_and_marks_account_verified(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        $newEmail = 'active.admin@raflora.com';

        $this->actingAs($admin)->withSession(['admin_bootstrap_email' => $newEmail]);
        $code = app(\App\Services\OtpService::class)->generate($admin, 'admin_bootstrap');

        $response = $this->post(route('admin.setup.otp'), [
            'otp' => $code,
        ]);

        $response->assertRedirect(route('admin.setup'));
        $this->assertTrue(session('admin_bootstrap_email_verified'));

        $admin->refresh();
        $this->assertEquals($newEmail, $admin->email);
        $this->assertNotNull($admin->email_verified_at);
    }

    /* =========================================================================
     * SECTION 3: MANDATORY PASSWORD REPLACEMENT & RECOVERY CODE (Scenarios 13-21)
     * ========================================================================= */

    public function test_13_password_replacement_is_mandatory_and_requires_verified_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        // Trying to post password without verifying email first
        $response = $this->actingAs($admin)->post(route('admin.setup.password'), [
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect(route('admin.setup'));
        $response->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_bootstrap);
    }

    public function test_14_and_15_weak_password_and_mismatched_confirmation_fail(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->withSession([
            'admin_bootstrap_email' => 'verified@raflora.com',
            'admin_bootstrap_email_verified' => true,
        ]);

        // Weak password (< 8 chars)
        $response = $this->post(route('admin.setup.password'), [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);
        $response->assertSessionHasErrors('password');

        // Mismatched confirmation
        $response = $this->post(route('admin.setup.password'), [
            'password' => 'ValidPass123!',
            'password_confirmation' => 'MismatchPass456!',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_16_original_default_password_is_rejected(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('Admin@12345'),
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->withSession([
            'admin_bootstrap_email' => 'verified@raflora.com',
            'admin_bootstrap_email_verified' => true,
        ]);

        $response = $this->post(route('admin.setup.password'), [
            'password' => 'Admin@12345',
            'password_confirmation' => 'Admin@12345',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->is_bootstrap);
    }

    public function test_17_to_21_valid_password_succeeds_generates_recovery_code_clears_bootstrap_and_grants_access(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('Admin@12345'),
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->withSession([
            'admin_bootstrap_email' => 'verified@raflora.com',
            'admin_bootstrap_email_verified' => true,
        ]);

        $newPassword = 'BrandNewAdminPassword2026!';
        $response = $this->post(route('admin.setup.password'), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect(route('admin.setup'));
        $response->assertSessionHas('status');

        // 18. Recovery code is flashed to session for single display
        $plainCode = session('admin_recovery_code');
        $this->assertNotEmpty($plainCode);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $plainCode);

        // 19. Recovery code is stored as SHA-256 hash in database, NOT plaintext
        $recoveryRecord = AdminRecoveryCode::where('user_id', $admin->id)->first();
        $this->assertNotNull($recoveryRecord);
        $this->assertFalse($recoveryRecord->is_used);
        $this->assertNotEquals($plainCode, $recoveryRecord->code_hash);
        $this->assertEquals(hash('sha256', strtoupper(str_replace('-', '', $plainCode))), $recoveryRecord->code_hash);

        // Crucial hardening requirement: is_bootstrap remains TRUE before explicit acknowledgment
        $admin->refresh();
        $this->assertTrue($admin->is_bootstrap);
        $this->assertTrue($admin->isBootstrapAdmin());

        // Attempting normal dashboard access before acknowledgment redirects back to setup
        $blockedResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $blockedResponse->assertRedirect(route('admin.setup'));

        // Explicit acknowledgment completes setup
        $ackResponse = $this->actingAs($admin)->post(route('admin.setup.acknowledge'));
        $ackResponse->assertRedirect(route('admin.dashboard'));

        // 20. is_bootstrap becomes false only AFTER acknowledgment
        $admin->refresh();
        $this->assertFalse($admin->is_bootstrap);
        $this->assertFalse($admin->isBootstrapAdmin());

        // Password actually updated
        $this->assertTrue(Hash::check($newPassword, $admin->password));

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin_bootstrap_completed',
        ]);

        // 21. Normal admin access becomes available
        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
    }

    /* =========================================================================
     * SECTION 4: ADMIN PASSWORD RESET (Scenarios 22-30)
     * ========================================================================= */

    public function test_22_and_23_forgot_password_is_rate_limited_and_does_not_expose_account_existence(): void
    {
        $admin = User::factory()->create([
            'email' => 'realadmin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        // Request with non-existent email
        $nonExistentResponse = $this->post(route('admin.password.send'), [
            'email' => 'doesnotexist@example.com',
        ]);
        $nonExistentResponse->assertRedirect(route('admin.password.otp.show'));
        $nonExistentResponse->assertSessionHas('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');

        // Request with real admin email yields identical response structure
        $realAdminResponse = $this->post(route('admin.password.send'), [
            'email' => 'realadmin@raflora.com',
        ]);
        $realAdminResponse->assertRedirect(route('admin.password.otp.show'));
        $realAdminResponse->assertSessionHas('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');

        // Rate limiting test: sending multiple requests triggers throttle
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password.send'), ['email' => 'throttle@raflora.com']);
        }
        $throttled = $this->post(route('admin.password.send'), ['email' => 'throttle@raflora.com']);
        $throttled->assertStatus(429);
    }

    public function test_24_password_reset_otp_is_used_with_purpose_password_reset(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.pw@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $this->post(route('admin.password.send'), ['email' => $admin->email]);

        $otpRecord = EmailVerification::where('user_id', $admin->id)
            ->where('purpose', 'password_reset')
            ->first();

        $this->assertNotNull($otpRecord);
        $this->assertFalse($otpRecord->isVerified());
    }

    public function test_25_and_26_incorrect_and_expired_password_reset_otp_fail(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.pw@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $this->withSession(['admin_password_reset_email' => $admin->email]);
        app(\App\Services\OtpService::class)->generate($admin, 'password_reset');

        // Incorrect OTP
        $response = $this->post(route('admin.password.otp.verify'), ['otp' => '000000']);
        $response->assertSessionHasErrors('otp');

        // Expire OTP
        EmailVerification::where('user_id', $admin->id)
            ->where('purpose', 'password_reset')
            ->update(['expires_at' => now()->subMinutes(10)]);

        $response2 = $this->post(route('admin.password.otp.verify'), ['otp' => '123456']);
        $response2->assertSessionHasErrors('otp');
    }

    public function test_27_to_30_valid_otp_allows_reset_rejects_reuse_invalidates_sessions_and_requires_fresh_login(): void
    {
        $oldPassword = 'OldAdminPassword123!';
        $admin = User::factory()->create([
            'email' => 'admin.pw@raflora.com',
            'password' => Hash::make($oldPassword),
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        // Insert mock active session in database
        DB::table('sessions')->insert([
            'id' => 'mock-admin-session-id',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
        $this->assertEquals(1, DB::table('sessions')->where('user_id', $admin->id)->count());

        $this->withSession(['admin_password_reset_email' => $admin->email]);
        $code = app(\App\Services\OtpService::class)->generate($admin, 'password_reset');

        // Verify OTP
        $verifyResponse = $this->post(route('admin.password.otp.verify'), ['otp' => $code]);
        $verifyResponse->assertRedirect(route('admin.password.new.show'));
        $this->assertTrue(session('admin_password_reset_verified'));

        // Current password reuse is rejected
        $reuseResponse = $this->post(route('admin.password.new.submit'), [
            'password' => $oldPassword,
            'password_confirmation' => $oldPassword,
        ]);
        $reuseResponse->assertSessionHasErrors('password');

        // Submit new valid password
        $newPassword = 'BrandNewPassword999!';
        $resetResponse = $this->post(route('admin.password.new.submit'), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $resetResponse->assertRedirect(route('login'));
        $resetResponse->assertSessionHas('success');

        // 29. Successful reset invalidates sessions
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $admin->id)->count());
        $this->assertGuest();

        // 30. Fresh login works (admin now has a trusted device from this test run)
        $service  = new TrustedDeviceService();
        $rawToken = $service->generateToken();
        TrustedDevice::create([
            'user_id'           => $admin->id,
            'device_token_hash' => $service->hashToken($rawToken),
            'device_name'       => 'Test',
            'ip_address'        => '127.0.0.1',
            'user_agent'        => 'PHPUnit',
            'expires_at'        => $service->trustExpiresAt(),
        ]);

        $loginResponse = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $admin->email,
                'password' => $newPassword,
            ]);
        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /* =========================================================================
     * SECTION 5: EMERGENCY ACCOUNT RECOVERY (Scenarios 31-40)
     * ========================================================================= */

    public function test_31_to_34_emergency_recovery_requires_code_and_limits_attempts(): void
    {
        $admin = User::factory()->create([
            'email' => 'lost.email@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $plainCode = 'A1B2-C3D4-E5F6-G7H8';
        $normalized = 'A1B2C3D4E5F6G7H8';
        AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', $normalized),
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);

        // 31 & 32. Invalid recovery code fails
        $failResponse = $this->post(route('admin.recovery.code'), [
            'recovery_code' => 'WRONG-CODE-1234-56',
        ]);
        $failResponse->assertSessionHasErrors('recovery_code');

        // 33. Attempt limit (5 attempts)
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('admin.recovery.code'), ['recovery_code' => 'WRONG-CODE-1234-56']);
        }
        $lockedResponse = $this->post(route('admin.recovery.code'), ['recovery_code' => 'WRONG-CODE-1234-56']);
        $lockedResponse->assertSessionHasErrors('recovery_code');

        // Fresh recovery code to test successful verification
        $adminCode2 = AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', 'VALIDRECOVERY123'),
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);

        // 34. Correct recovery code permits recovery
        $successResponse = $this->post(route('admin.recovery.code'), [
            'recovery_code' => 'VALIDRECOVERY123',
        ]);
        $successResponse->assertRedirect(route('admin.recovery.show'));
        $this->assertTrue(session('admin_recovery_code_verified'));
        $this->assertEquals($adminCode2->id, session('admin_recovery_code_id'));
    }

    public function test_35_to_40_emergency_recovery_flow_replaces_email_password_invalidates_code_and_revokes_sessions(): void
    {
        $oldPassword = 'OldPassword123!';
        $admin = User::factory()->create([
            'email' => 'old.inaccessible@raflora.com',
            'password' => Hash::make($oldPassword),
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $rawRecoveryCode = 'AAAA-BBBB-CCCC-DDDD';
        $codeRecord = AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', 'AAAABBBBCCCCDDDD'),
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);

        // Verify recovery code
        $this->post(route('admin.recovery.code'), ['recovery_code' => $rawRecoveryCode]);

        // 35. Submit new operational email
        $newEmail = 'new.recovered@raflora.com';
        $emailResponse = $this->post(route('admin.recovery.email'), ['email' => $newEmail]);
        $emailResponse->assertRedirect(route('admin.recovery.show'));
        $this->assertEquals($newEmail, session('admin_recovery_email'));

        // 36. Verify OTP sent to new email using dedicated purpose admin_recovery
        $otpCode = app(\App\Services\OtpService::class)->generate($admin, 'admin_recovery');
        $otpResponse = $this->post(route('admin.recovery.otp'), ['otp' => $otpCode]);
        $otpResponse->assertRedirect(route('admin.recovery.show'));
        $this->assertTrue(session('admin_recovery_email_verified'));

        // Mock existing sessions
        DB::table('sessions')->insert([
            'id' => 'emergency-mock-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        // Submit new password
        $newPassword = 'BrandNewRecoveryPassword123!';
        $pwResponse = $this->post(route('admin.recovery.password'), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $pwResponse->assertRedirect(route('admin.recovery.show'));

        // 37. Old recovery code cannot be reused (marked used)
        $codeRecord->refresh();
        $this->assertTrue($codeRecord->is_used);
        $this->assertNotNull($codeRecord->used_at);

        // 38. New replacement recovery code is generated and shown once
        $replacementCode = session('admin_replacement_recovery_code');
        $this->assertNotEmpty($replacementCode);
        $this->assertNotEquals($rawRecoveryCode, $replacementCode);

        // Replacement code is stored as SHA-256
        $newCodeRecord = AdminRecoveryCode::where('user_id', $admin->id)
            ->where('is_used', false)
            ->first();
        $this->assertNotNull($newCodeRecord);

        // 39. Existing sessions are revoked
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $admin->id)->count());

        // Account updated
        $admin->refresh();
        $this->assertEquals($newEmail, $admin->email);
        $this->assertTrue(Hash::check($newPassword, $admin->password));
        $this->assertNotNull($admin->email_verified_at);

        // 40. Fresh login works (admin now has a trusted device)
        $service  = new TrustedDeviceService();
        $rawToken = $service->generateToken();
        TrustedDevice::create([
            'user_id'           => $admin->id,
            'device_token_hash' => $service->hashToken($rawToken),
            'device_name'       => 'Test',
            'ip_address'        => '127.0.0.1',
            'user_agent'        => 'PHPUnit',
            'expires_at'        => $service->trustExpiresAt(),
        ]);

        $loginResponse = $this->withCookies([TrustedDeviceService::COOKIE_NAME => $rawToken])
            ->post(route('login.attempt'), [
                'email'    => $newEmail,
                'password' => $newPassword,
            ]);
        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /* =========================================================================
     * SECTION 6: AUTHENTICATED ADMIN EMAIL CHANGE (Scenarios 41-47)
     * ========================================================================= */

    public function test_41_to_47_authenticated_admin_email_change_flow(): void
    {
        $password = 'AdminPassword123!';
        $admin = User::factory()->create([
            'email' => 'current.admin@raflora.com',
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_bootstrap' => false,
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'email' => 'taken@raflora.com',
            'role' => 'staff',
        ]);

        // 41 & 42. Duplicate email rejected & password required
        $dupResponse = $this->actingAs($admin)->post(route('admin.email-change.submit'), [
            'current_password' => $password,
            'email' => 'taken@raflora.com',
        ]);
        $dupResponse->assertSessionHasErrors('email');

        // Wrong password rejected
        $wrongPw = $this->actingAs($admin)->post(route('admin.email-change.submit'), [
            'current_password' => 'WrongPassword!',
            'email' => 'new.valid@raflora.com',
        ]);
        $wrongPw->assertSessionHasErrors('current_password');

        // 43. Submit valid new email
        $newEmail = 'brandnew.admin@raflora.com';
        $submitResponse = $this->actingAs($admin)->post(route('admin.email-change.submit'), [
            'current_password' => $password,
            'email' => $newEmail,
        ]);
        $submitResponse->assertRedirect(route('admin.email-change.show'));
        $this->assertEquals($newEmail, session('admin_email_change_pending'));

        // 44. Incorrect OTP fails
        $failOtp = $this->actingAs($admin)->post(route('admin.email-change.verify'), [
            'otp' => '000000',
        ]);
        $failOtp->assertSessionHasErrors('otp');

        // 45 & 46. Correct OTP updates email and keeps verified
        $otp = app(\App\Services\OtpService::class)->generate($admin, 'admin_email_change');
        $verifyResponse = $this->actingAs($admin)->post(route('admin.email-change.verify'), [
            'otp' => $otp,
        ]);
        $verifyResponse->assertRedirect(route('admin.settings'));
        $verifyResponse->assertSessionHas('success');

        $admin->refresh();
        $this->assertEquals($newEmail, $admin->email);
        $this->assertNotNull($admin->email_verified_at);

        // 47. Security event recorded in audit logs
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin_email_changed',
            'module' => 'admin_security',
        ]);
    }

    /* =========================================================================
     * SECTION 7: AUTHORIZATION BOUNDARIES (Scenarios 48-50)
     * ========================================================================= */

    public function test_48_and_49_client_and_staff_cannot_access_admin_setup(): void
    {
        $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        // Client gets 403
        $this->actingAs($client)->get(route('admin.setup'))->assertForbidden();

        // Staff gets 403
        $this->actingAs($staff)->get(route('admin.setup'))->assertForbidden();
    }

    public function test_50_normal_admin_cannot_be_trapped_in_bootstrap_and_redirects_to_dashboard(): void
    {
        $normalAdmin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'email_verified_at' => now(),
        ]);

        // Visiting setup should redirect to admin dashboard
        $response = $this->actingAs($normalAdmin)->get(route('admin.setup'));
        $response->assertRedirect(route('admin.dashboard'));
    }

    /* =========================================================================
     * SECTION 8: HARDENING & REGRESSION TESTS (Requirements 1-8)
     * ========================================================================= */

    /**
     * Requirement 3 & 7A: Recovery code replacement invalidates all previous unused codes.
     */
    public function test_recovery_code_replacement_invalidates_previous_codes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'email_verified_at' => now(),
        ]);

        $codeA = 'AAAA-1111-BBBB-2222';
        $normalizedA = 'AAAA1111BBBB2222';
        $recordA = AdminRecoveryCode::issueForUser($admin->id, hash('sha256', $normalizedA));

        $this->assertFalse($recordA->is_used);
        $this->assertEquals(1, AdminRecoveryCode::where('user_id', $admin->id)->where('is_used', false)->count());

        // Issue replacement code B
        $codeB = 'CCCC-3333-DDDD-4444';
        $normalizedB = 'CCCC3333DDDD4444';
        $recordB = AdminRecoveryCode::issueForUser($admin->id, hash('sha256', $normalizedB));

        // Code A is now invalidated
        $recordA->refresh();
        $this->assertTrue($recordA->is_used);
        $this->assertNotNull($recordA->used_at);

        // Code B is the only active unused code
        $recordB->refresh();
        $this->assertFalse($recordB->is_used);
        $this->assertEquals(1, AdminRecoveryCode::where('user_id', $admin->id)->where('is_used', false)->count());

        // Attempting recovery with Code A is rejected
        $responseA = $this->post(route('admin.recovery.code'), ['recovery_code' => $codeA]);
        $responseA->assertSessionHasErrors('recovery_code');

        // Recovery with Code B succeeds
        $responseB = $this->post(route('admin.recovery.code'), ['recovery_code' => $codeB]);
        $responseB->assertRedirect(route('admin.recovery.show'));
        $this->assertTrue(session('admin_recovery_code_verified'));
    }

    /**
     * Requirement 7B: Used recovery code cannot be reused.
     */
    public function test_consumed_recovery_code_cannot_be_reused(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'email_verified_at' => now(),
        ]);

        $code = 'USED-RECO-VERY-CODE';
        $normalized = 'USEDRECOVERYCODE';
        $record = AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', $normalized),
            'is_used' => true,
            'used_at' => now()->subHour(),
            'attempts' => 1,
            'generated_at' => now()->subDay(),
        ]);

        $response = $this->post(route('admin.recovery.code'), ['recovery_code' => $code]);
        $response->assertSessionHasErrors('recovery_code');
        $this->assertFalse(session('admin_recovery_code_verified', false));
    }

    /**
     * Requirement 6 & 7C: Emergency recovery cannot be bypassed by OTP alone.
     */
    public function test_emergency_recovery_cannot_be_bypassed_by_otp_alone(): void
    {
        $admin = User::factory()->create([
            'email' => 'victim.admin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        // Attacker has an OTP for their own email, but NO verified recovery code session
        $this->withSession([
            'admin_recovery_email' => 'attacker@raflora.com',
            'admin_recovery_email_verified' => true,
            // 'admin_recovery_code_verified' is intentionally absent!
        ]);

        $response = $this->post(route('admin.recovery.password'), [
            'password' => 'AttackerPassword123!',
            'password_confirmation' => 'AttackerPassword123!',
        ]);

        // Must be rejected and redirected back to recovery initial step
        $response->assertRedirect(route('admin.recovery.show'));
        $response->assertSessionHasErrors();

        // Admin email and password remain unchanged
        $admin->refresh();
        $this->assertEquals('victim.admin@raflora.com', $admin->email);
    }

    /**
     * Requirement 6 & 7D: Emergency recovery cannot be bypassed by recovery code alone.
     */
    public function test_emergency_recovery_cannot_be_bypassed_by_recovery_code_alone(): void
    {
        $admin = User::factory()->create([
            'email' => 'legit.admin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $codeRecord = AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', 'VALIDCODE1234567'),
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);

        // Recovery code is verified in session, but OTP verification was NOT completed
        $this->withSession([
            'admin_recovery_code_verified' => true,
            'admin_recovery_code_id' => $codeRecord->id,
            // 'admin_recovery_email_verified' is intentionally absent!
        ]);

        $response = $this->post(route('admin.recovery.password'), [
            'password' => 'NewPassword12345!',
            'password_confirmation' => 'NewPassword12345!',
        ]);

        // Must be rejected and redirected back
        $response->assertRedirect(route('admin.recovery.show'));
        $response->assertSessionHasErrors();

        // Recovery code must NOT be marked as used
        $codeRecord->refresh();
        $this->assertFalse($codeRecord->is_used);
    }

    /**
     * Requirement 6 & 7E: Emergency recovery requires a valid new password satisfying password policy.
     */
    public function test_emergency_recovery_requires_valid_new_password_satisfying_policy(): void
    {
        $admin = User::factory()->create([
            'email' => 'legit.admin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $codeRecord = AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', 'VALIDCODE1234567'),
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);

        $this->withSession([
            'admin_recovery_code_verified' => true,
            'admin_recovery_code_id' => $codeRecord->id,
            'admin_recovery_email' => 'new.email@raflora.com',
            'admin_recovery_email_verified' => true,
        ]);

        // 1. Weak password fails
        $weakResponse = $this->post(route('admin.recovery.password'), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
        $weakResponse->assertSessionHasErrors('password');

        // 2. Mismatched confirmation fails
        $mismatchResponse = $this->post(route('admin.recovery.password'), [
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);
        $mismatchResponse->assertSessionHasErrors('password');

        // Recovery code remains unused
        $codeRecord->refresh();
        $this->assertFalse($codeRecord->is_used);
    }

    /**
     * Requirement 1 & 7F: Emergency recovery OTP uses dedicated purpose admin_recovery and is isolated.
     */
    public function test_emergency_recovery_otp_purpose_is_isolated_from_bootstrap_otp(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@raflora.com',
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $codeRecord = AdminRecoveryCode::create([
            'user_id' => $admin->id,
            'code_hash' => hash('sha256', 'RECOVERYCODE1234'),
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);

        $this->withSession([
            'admin_recovery_code_verified' => true,
            'admin_recovery_code_id' => $codeRecord->id,
            'admin_recovery_email' => 'target.admin@raflora.com',
        ]);

        // Generate OTP with purpose 'admin_bootstrap'
        $bootstrapOtp = app(\App\Services\OtpService::class)->generate($admin, 'admin_bootstrap');

        // Attempt to verify at admin.recovery.otp with the bootstrap OTP -> must fail
        $failResponse = $this->post(route('admin.recovery.otp'), [
            'otp' => $bootstrapOtp,
        ]);
        $failResponse->assertSessionHasErrors('otp');
        $this->assertFalse(session('admin_recovery_email_verified', false));

        // Now generate OTP with dedicated purpose 'admin_recovery'
        $recoveryOtp = app(\App\Services\OtpService::class)->generate($admin, 'admin_recovery');

        // Verification at admin.recovery.otp succeeds
        $successResponse = $this->post(route('admin.recovery.otp'), [
            'otp' => $recoveryOtp,
        ]);
        $successResponse->assertRedirect(route('admin.recovery.show'));
        $this->assertTrue(session('admin_recovery_email_verified'));
    }

    /**
     * Requirement 5 & 7G: Bootstrap setup cannot complete before explicit recovery code acknowledgment.
     */
    public function test_bootstrap_setup_remains_incomplete_without_explicit_recovery_acknowledgment(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('Admin@12345'),
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->withSession([
            'admin_bootstrap_email' => 'admin.new@raflora.com',
            'admin_bootstrap_email_verified' => true,
        ]);

        // Submit new password
        $this->post(route('admin.setup.password'), [
            'password' => 'NewAdminPassword2026!',
            'password_confirmation' => 'NewAdminPassword2026!',
        ]);

        // Verify recovery code was generated in DB
        $this->assertEquals(1, AdminRecoveryCode::where('user_id', $admin->id)->where('is_used', false)->count());

        // Account is still in bootstrap state!
        $admin->refresh();
        $this->assertTrue($admin->is_bootstrap);
        $this->assertTrue($admin->isBootstrapAdmin());

        // Direct access to admin panel is blocked
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.setup'));

        // Simulate closing the browser session (session cleared)
        $this->flushSession();

        // Re-authenticate: user is still bootstrap admin
        $this->actingAs($admin)->get(route('admin.setup'))->assertOk();

        // Reissuing fresh recovery code works
        $reissueResponse = $this->actingAs($admin)->post(route('admin.setup.reissue-code'));
        $reissueResponse->assertRedirect(route('admin.setup'));
        $this->assertNotEmpty(session('admin_recovery_code'));

        // Account is still bootstrap admin
        $admin->refresh();
        $this->assertTrue($admin->is_bootstrap);

        // Explicit acknowledgment completes the setup
        $ackResponse = $this->actingAs($admin)->post(route('admin.setup.acknowledge'));
        $ackResponse->assertRedirect(route('admin.dashboard'));

        // Now setup is complete
        $admin->refresh();
        $this->assertFalse($admin->is_bootstrap);
        $this->assertFalse($admin->isBootstrapAdmin());

        // Normal dashboard access is granted
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    /**
     * Requirement 8 & 7H: StaffMiddleware closes demonstrated access path for bootstrap Admin.
     */
    public function test_bootstrap_admin_cannot_access_staff_routes_via_staff_middleware(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => true,
            'email_verified_at' => null,
        ]);

        // An admin role normally passes StaffMiddleware because Admin can supervise staff.
        // But StaffMiddleware explicitly checks isBootstrapAdmin() to close the operational access path.
        $response = $this->actingAs($admin)->get(route('staff.dashboard'));
        $response->assertRedirect(route('admin.setup'));
        $response->assertSessionHas('info', 'Initial administrative setup is required before accessing operations.');
    }
}

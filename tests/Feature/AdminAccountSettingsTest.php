<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'password' => Hash::make('AdminPassword123!'),
            'name' => 'Maria Santos',
            'email' => 'admin.maria@raflora.com',
            'mobile_number' => '09171234567',
            'address' => 'Quezon City, Metro Manila',
            'email_verified_at' => now(),
        ]);
    }

    private function createFakeImage(string $filename = 'avatar.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        return UploadedFile::fake()->createWithContent($filename, $png);
    }

    /**
     * 1. Admin settings page loads successfully.
     */
    public function test_admin_settings_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('Account Settings');
        $response->assertSee('Manage your account information, security, and administrative settings.');
        $response->assertSee('Profile');
        $response->assertSee('Security');
        $response->assertSee('Administration');
        $response->assertSee('Account Overview');
        $response->assertSee('Security Summary');
    }

    /**
     * 2. Admin can update name, mobile number, and address.
     */
    public function test_admin_can_update_profile_name_mobile_number_and_address(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => 'Maria Teresa Santos',
            'mobile_number' => '09189876543',
            'address' => 'Makati City, Metro Manila',
        ]);

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHas('success');

        $this->admin->refresh();
        $this->assertEquals('Maria Teresa Santos', $this->admin->name);
        $this->assertEquals('09189876543', $this->admin->mobile_number);
        $this->assertEquals('Makati City, Metro Manila', $this->admin->address);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'profile_updated',
        ]);
    }

    /**
     * 3. Admin can upload a valid profile image.
     */
    public function test_admin_can_upload_valid_profile_image(): void
    {
        Storage::fake('public');

        $file = $this->createFakeImage('maria.png');

        $response = $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => $this->admin->name,
            'profile_image' => $file,
        ]);

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHas('success');

        $this->admin->refresh();
        $this->assertNotNull($this->admin->profile_image);
        Storage::disk('public')->assertExists($this->admin->profile_image);
    }

    /**
     * 4. Invalid profile image is rejected.
     */
    public function test_invalid_profile_image_is_rejected(): void
    {
        Storage::fake('public');

        $invalidFile = UploadedFile::fake()->create('script.sh', 500);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => $this->admin->name,
            'profile_image' => $invalidFile,
        ]);

        $response->assertSessionHasErrors('profile_image');
        $this->admin->refresh();
        $this->assertNull($this->admin->profile_image);
    }

    /**
     * 5. Existing profile image is replaced safely, and can be removed.
     */
    public function test_existing_profile_image_is_replaced_safely(): void
    {
        Storage::fake('public');

        $firstFile = $this->createFakeImage('first.png');
        $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => $this->admin->name,
            'profile_image' => $firstFile,
        ]);

        $this->admin->refresh();
        $firstPath = $this->admin->profile_image;
        Storage::disk('public')->assertExists($firstPath);

        // Replace with new image
        $secondFile = $this->createFakeImage('second.png');
        $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => $this->admin->name,
            'profile_image' => $secondFile,
        ]);

        $this->admin->refresh();
        $secondPath = $this->admin->profile_image;

        $this->assertNotEquals($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        // Remove profile image
        $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => $this->admin->name,
            'remove_profile_image' => '1',
        ]);

        $this->admin->refresh();
        $this->assertNull($this->admin->profile_image);
        Storage::disk('public')->assertMissing($secondPath);
    }

    /**
     * 6. Profile form cannot change role.
     */
    public function test_profile_form_cannot_change_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => 'Maria Hacked',
            'role' => 'client',
        ]);

        $this->admin->refresh();
        $this->assertEquals('admin', $this->admin->role);
    }

    /**
     * 7. Profile form cannot directly change email.
     */
    public function test_profile_form_cannot_directly_change_email(): void
    {
        $originalEmail = $this->admin->email;

        $this->actingAs($this->admin)->post(route('admin.settings.profile.update'), [
            'name' => 'Maria Santos',
            'email' => 'new.unverified@example.com',
        ]);

        $this->admin->refresh();
        $this->assertEquals($originalEmail, $this->admin->email);
    }

    /**
     * 8. Change Email action still reaches the existing OTP workflow.
     */
    public function test_change_email_action_reaches_existing_otp_workflow(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.email-change.show'));

        $response->assertOk();
        $response->assertSee('Change Administrator Email');
    }

    /**
     * 9. Admin can update password using correct current password.
     */
    public function test_admin_can_update_password_using_correct_current_password(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.password.update'), [
            'current_password' => 'AdminPassword123!',
            'password' => 'BrandNewPassword888!',
            'password_confirmation' => 'BrandNewPassword888!',
        ]);

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHas('success');

        $this->admin->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword888!', $this->admin->password));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'password_updated',
        ]);
    }

    /**
     * 10. Incorrect current password is rejected.
     */
    public function test_incorrect_current_password_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.password.update'), [
            'current_password' => 'IncorrectCurrentPassword!',
            'password' => 'BrandNewPassword888!',
            'password_confirmation' => 'BrandNewPassword888!',
        ]);

        $response->assertSessionHasErrors('current_password');

        $this->admin->refresh();
        $this->assertTrue(Hash::check('AdminPassword123!', $this->admin->password));
    }

    /**
     * Backward-compatible route admin.account.password works.
     */
    public function test_backward_compatible_account_password_route_works(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.account.password'), [
            'current_password' => 'AdminPassword123!',
            'password' => 'LegacyRoutePassword999!',
            'password_confirmation' => 'LegacyRoutePassword999!',
        ]);

        $response->assertRedirect(route('admin.settings'));
        $this->admin->refresh();
        $this->assertTrue(Hash::check('LegacyRoutePassword999!', $this->admin->password));
    }

    /**
     * 11. Trusted devices display only the current Admin's records.
     */
    public function test_trusted_devices_display_only_current_admins_records(): void
    {
        $otherAdmin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $myDevice = TrustedDevice::create([
            'user_id' => $this->admin->id,
            'device_token_hash' => hash('sha256', 'my_secret_token_1'),
            'device_name' => 'Maria MacBook Pro',
            'ip_address' => '192.168.1.10',
            'user_agent' => 'Mozilla/5.0 Mac',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $otherDevice = TrustedDevice::create([
            'user_id' => $otherAdmin->id,
            'device_token_hash' => hash('sha256', 'other_secret_token_2'),
            'device_name' => 'Other Admin Alienware',
            'ip_address' => '192.168.1.99',
            'user_agent' => 'Mozilla/5.0 Windows',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('Maria MacBook Pro');
        $response->assertDontSee('Other Admin Alienware');
    }

    /**
     * 12 & 13. Trusted device can be revoked and becomes unusable.
     */
    public function test_trusted_device_can_be_revoked_and_becomes_unusable(): void
    {
        $device = TrustedDevice::create([
            'user_id' => $this->admin->id,
            'device_token_hash' => hash('sha256', 'my_token_to_revoke'),
            'device_name' => 'Maria Chrome Windows',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Chrome Windows',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($device->isUsable());

        $response = $this->actingAs($this->admin)->post(route('admin.settings.trusted-devices.revoke'), [
            'device_id' => $device->id,
        ]);

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHas('success');

        $device->refresh();
        $this->assertNotNull($device->revoked_at);
        $this->assertFalse($device->isUsable());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'trusted_device_revoked',
        ]);
    }

    /**
     * 14. Expired trusted device is shown as expired and is not treated as active.
     */
    public function test_expired_trusted_device_is_shown_as_expired(): void
    {
        $device = TrustedDevice::create([
            'user_id' => $this->admin->id,
            'device_token_hash' => hash('sha256', 'expired_token'),
            'device_name' => 'Old Laptop',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Old Chrome',
            'last_used_at' => now()->subDays(40),
            'expires_at' => now()->subDays(10),
        ]);

        $this->assertFalse($device->isUsable());

        $response = $this->actingAs($this->admin)->get(route('admin.settings'));
        $response->assertOk();
        $response->assertSee('Old Laptop');
        $response->assertSee('Expired');
    }

    /**
     * 15. Raw device token/hash is never rendered.
     */
    public function test_raw_device_token_hash_is_never_rendered(): void
    {
        $secretHash = hash('sha256', 'super_secret_raw_token_xyz');

        TrustedDevice::create([
            'user_id' => $this->admin->id,
            'device_token_hash' => $secretHash,
            'device_name' => 'Secret Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Chrome',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('Secret Test Device');
        $response->assertDontSee($secretHash);
    }

    /**
     * 16 & 17. Active sessions display only the authenticated Admin's sessions and current session is identified.
     */
    public function test_active_sessions_display_only_authenticated_admin_sessions(): void
    {
        $otherUser = User::factory()->create([
            'role' => 'staff',
        ]);

        DB::table('sessions')->insert([
            'id' => 'admin_session_id_1',
            'user_id' => $this->admin->id,
            'ip_address' => '192.168.1.55',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0',
            'payload' => 'dummy_payload',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => 'other_user_session_id_2',
            'user_id' => $otherUser->id,
            'ip_address' => '10.0.0.99',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15 Safari/604.1',
            'payload' => 'dummy_payload',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('192.168.1.55');
        $response->assertDontSee('10.0.0.99');
    }

    /**
     * 18 & 19. Sign Out All Other Sessions preserves current session and removes other records.
     */
    public function test_sign_out_all_other_sessions_preserves_current_session_and_removes_others(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin', 'is_bootstrap' => false]);

        // Create 2 sessions for current admin
        DB::table('sessions')->insert([
            'id' => 'current_session_alpha',
            'user_id' => $this->admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Chrome',
            'payload' => 'payload1',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => 'old_session_beta',
            'user_id' => $this->admin->id,
            'ip_address' => '192.168.1.200',
            'user_agent' => 'Firefox',
            'payload' => 'payload2',
            'last_activity' => now()->subHours(2)->timestamp,
        ]);

        // Session for other admin
        DB::table('sessions')->insert([
            'id' => 'other_admin_session_gamma',
            'user_id' => $otherAdmin->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Safari',
            'payload' => 'payload3',
            'last_activity' => now()->timestamp,
        ]);

        // Start request with session id 'current_session_alpha'
        $response = $this->actingAs($this->admin)
            ->withSession(['_token' => 'dummy'])
            ->withCookies([])
            ->post(route('admin.settings.sessions.revoke-others'));

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHas('success');

        // Other session for current admin was removed
        $this->assertDatabaseMissing('sessions', [
            'id' => 'old_session_beta',
        ]);

        // Other admin session was NOT touched
        $this->assertDatabaseHas('sessions', [
            'id' => 'other_admin_session_gamma',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'sessions_revoked',
        ]);
    }

    /**
     * 20. Non-admin users cannot execute Admin-only session or trusted-device management actions.
     */
    public function test_non_admin_users_cannot_execute_admin_session_or_trusted_device_actions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('admin.settings.sessions.revoke-others'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.settings.trusted-devices.revoke'), ['device_id' => 1])
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('admin.settings'))
            ->assertForbidden();
    }

    /**
     * 21. Team account creation still works.
     */
    public function test_team_account_creation_still_works(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.account.accounts.store'), [
            'name' => 'Staff Sarah',
            'email' => 'sarah.staff@raflora.com',
            'role' => 'staff',
            'password' => 'StaffSecret123!',
            'password_confirmation' => 'StaffSecret123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'sarah.staff@raflora.com',
            'role' => 'staff',
            'name' => 'Staff Sarah',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'team_account_created',
        ]);
    }

    /**
     * 22. Business configuration update still works.
     */
    public function test_business_configuration_update_still_works(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'downpayment_percentage' => '35.0',
            'long_term_booking_threshold_days' => '90',
            'price_reconfirmation_threshold_days' => '30',
            'change_reason' => 'Annual adjustment',
            'confirmed' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(35.0, (float) Setting::getSetting('downpayment_percentage'));
    }

    /**
     * 23. Audit trail still loads.
     */
    public function test_audit_trail_still_loads(): void
    {
        AuditLog::record(
            $this->admin->id,
            'security_check',
            'Routine security check executed.',
            'admin_security'
        );

        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('Routine security check executed.');
    }

    /**
     * 24. Tab navigation defaults to profile and activates selected tab via query param.
     */
    public function test_settings_page_tab_navigation_and_query_parameter_selection(): void
    {
        // Default page load -> profile tab active
        $defaultResponse = $this->actingAs($this->admin)->get(route('admin.settings'));
        $defaultResponse->assertOk();
        $defaultResponse->assertSee('id="tab-pane-profile"', false);
        $defaultResponse->assertSee('aria-controls="tab-pane-profile"', false);

        // Security tab query param
        $securityResponse = $this->actingAs($this->admin)->get(route('admin.settings', ['tab' => 'security']));
        $securityResponse->assertOk();
        $securityResponse->assertSee('aria-controls="tab-pane-security"', false);

        // Administration tab query param
        $adminResponse = $this->actingAs($this->admin)->get(route('admin.settings', ['tab' => 'administration']));
        $adminResponse->assertOk();
        $adminResponse->assertSee('aria-controls="tab-pane-administration"', false);
    }

    /**
     * 25. Settings page does not display unsupported tabs or redundant header buttons.
     */
    public function test_settings_page_does_not_display_unsupported_tabs_or_duplicate_header_buttons(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertOk();
        // Tab list must only contain the 3 approved tabs
        $response->assertSee('id="tab-btn-profile"', false);
        $response->assertSee('id="tab-btn-security"', false);
        $response->assertSee('id="tab-btn-administration"', false);
        $response->assertDontSee('id="tab-btn-notifications"', false);
        $response->assertDontSee('id="tab-btn-appearance"', false);
        $response->assertDontSee('id="tab-btn-system-preferences"', false);
        $response->assertDontSee('id="tab-btn-api"', false);
        $response->assertDontSee('System Preferences');
        $response->assertDontSee('API & Integrations');
        $response->assertDontSee('Google Authenticator');
        $response->assertDontSee('Two-Factor Authentication');
    }
}



<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Add Account (Account Settings > Administration) keeps its own validation errors and entered
 * values, so a failed attempt reopens the dialog on Administration and never fills the Admin's
 * own profile or password forms. Team Accounts shows only what account data supports.
 */
class AdminAccountCreationFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'password' => Hash::make('AdminPassword123!'),
            'name' => 'Maria Santos',
            'email' => 'admin.maria@raflora.com',
            'email_verified_at' => now(),
        ]);
        User::factory()->create(['role' => 'staff', 'name' => 'Existing Staff', 'email' => 'taken@raflora.com']);
    }

    private function addAccount(array $overrides = [])
    {
        return $this->actingAs($this->admin)->from(route('admin.settings'))
            ->post(route('admin.account.accounts.store'), array_merge([
                'name' => 'Applicant Name',
                'email' => 'applicant@raflora.com',
                'role' => 'staff',
                'password' => 'StaffSecret123!',
                'password_confirmation' => 'StaffSecret123!',
            ], $overrides));
    }

    private function settingsPage(): string
    {
        return $this->actingAs($this->admin)->get(route('admin.settings'))->assertOk()->getContent();
    }

    private function pane(string $html, string $tab): string
    {
        preg_match('/<div id="tab-pane-' . $tab . '"[^>]*>/', $html, $m);

        return $m[0] ?? '';
    }

    private function inputTag(string $html, string $id): string
    {
        preg_match('/<input[^>]*id="' . $id . '"[^>]*>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_failed_add_account_never_fills_the_admin_profile_name(): void
    {
        $this->addAccount(['email' => 'taken@raflora.com'])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors(['email'], null, 'createAccount')
            ->assertSessionDoesntHaveErrors(['name', 'email']);

        $html = $this->settingsPage();

        $this->assertStringContainsString('value="Maria Santos"', $this->inputTag($html, 'profile_name'));
        $this->assertStringNotContainsString('Applicant Name', $this->inputTag($html, 'profile_name'));
        $this->assertSame('Maria Santos', $this->admin->fresh()->name);
        $this->assertDatabaseMissing('users', ['name' => 'Applicant Name']);
    }

    public function test_failed_add_account_reopens_the_dialog_on_administration_with_entered_values(): void
    {
        $this->addAccount(['email' => 'taken@raflora.com']);
        $html = $this->settingsPage();

        $this->assertStringNotContainsString('hidden', $this->pane($html, 'administration'));
        $this->assertStringContainsString('hidden', $this->pane($html, 'profile'));
        $this->assertStringContainsString('data-open-on-load="true"', $html);
        $this->assertStringContainsString('value="Applicant Name"', $this->inputTag($html, 'account_name'));
        $this->assertStringContainsString('value="taken@raflora.com"', $this->inputTag($html, 'account_email'));
        $this->assertMatchesRegularExpression('/id="account_email_error"[^>]*>\s*The email has already been taken\./', $html);
        $this->assertStringContainsString('aria-invalid="true"', $this->inputTag($html, 'account_email'));
    }

    public function test_add_account_password_mismatch_opens_administration_not_security(): void
    {
        $this->addAccount(['password_confirmation' => 'Different123!'])
            ->assertSessionHasErrors(['password'], null, 'createAccount')
            ->assertSessionDoesntHaveErrors(['password']);

        $html = $this->settingsPage();

        $this->assertStringNotContainsString('hidden', $this->pane($html, 'administration'));
        $this->assertStringContainsString('hidden', $this->pane($html, 'security'));
        $this->assertMatchesRegularExpression('/id="account_password_error"[^>]*>\s*The password field confirmation does not match\./', $html);
        // The page-level summary is for the default bag; it must not present this as the Admin's own password change.
        $this->assertStringNotContainsString('Please correct the following errors:', $html);
        $this->assertStringContainsString('value="applicant@raflora.com"', $this->inputTag($html, 'account_email'));
        $this->assertDatabaseMissing('users', ['email' => 'applicant@raflora.com']);
    }

    public function test_add_account_passwords_are_never_refilled(): void
    {
        $this->addAccount(['email' => 'taken@raflora.com']);
        $html = $this->settingsPage();

        $this->assertStringNotContainsString('StaffSecret123!', $html);
        $this->assertStringNotContainsString('value=', $this->inputTag($html, 'temp_password'));
        $this->assertStringNotContainsString('value=', $this->inputTag($html, 'temp_password_confirmation'));
    }

    public function test_add_account_still_refuses_the_admin_role_inside_its_own_form(): void
    {
        $this->addAccount(['role' => 'admin'])->assertSessionHasErrors(['role'], null, 'createAccount');

        $this->assertDatabaseMissing('users', ['email' => 'applicant@raflora.com']);
        $this->assertStringContainsString('data-open-on-load="true"', $this->settingsPage());
    }

    public function test_successful_add_account_closes_the_dialog_and_creates_staff(): void
    {
        $this->addAccount()->assertRedirect(route('admin.settings'))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'applicant@raflora.com', 'role' => 'staff', 'email_verified_at' => null]);
        $html = $this->settingsPage();
        $this->assertStringNotContainsString('hidden', $this->pane($html, 'administration'));
        $this->assertStringNotContainsString('data-open-on-load="true"', $html);
    }

    public function test_profile_and_password_validation_still_use_their_own_forms(): void
    {
        // A failed profile save still refills the profile form.
        $this->actingAs($this->admin)->from(route('admin.settings'))
            ->post(route('admin.settings.profile.update'), ['name' => 'Renamed Admin', 'mobile_number' => str_repeat('9', 25)])
            ->assertSessionHasErrors(['mobile_number']);
        $html = $this->settingsPage();
        $this->assertStringContainsString('value="Renamed Admin"', $this->inputTag($html, 'profile_name'));
        $this->assertStringNotContainsString('data-open-on-load="true"', $html);
        $this->assertStringNotContainsString('Renamed Admin', $this->inputTag($html, 'account_name'));

        // A failed password change still opens Security with its own message.
        $this->actingAs($this->admin)->from(route('admin.settings'))
            ->post(route('admin.settings.password.update'), ['current_password' => 'wrong', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])
            ->assertSessionHasErrors(['current_password']);
        $html = $this->settingsPage();
        $this->assertStringNotContainsString('hidden', $this->pane($html, 'security'));
        $this->assertStringContainsString('The provided current password is incorrect.', $html);
        $this->assertStringNotContainsString('data-open-on-load="true"', $html);
    }

    public function test_team_accounts_status_follows_email_verification(): void
    {
        User::factory()->create(['role' => 'staff', 'name' => 'Pending Staff', 'email' => 'pending@raflora.com', 'email_verified_at' => null]);
        User::factory()->create(['role' => 'staff', 'name' => 'Verified Staff', 'email' => 'verified@raflora.com', 'email_verified_at' => now()]);

        $html = $this->actingAs($this->admin)->get(route('admin.settings', ['tab' => 'administration']))->getContent();
        $row = fn (string $email) => preg_match('/' . preg_quote($email, '/') . '.*?<\/tr>/s', $html, $m) ? $m[0] : '';

        $this->assertStringContainsString('Email verified', $row('verified@raflora.com'));
        $this->assertStringContainsString('Awaiting first sign-in verification', $row('pending@raflora.com'));
        $this->assertStringNotContainsString('Active', $row('pending@raflora.com'));
        $this->assertStringNotContainsString('Active', $row('verified@raflora.com'));
    }

    public function test_staff_still_cannot_create_accounts_or_open_settings(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        $this->actingAs($staff)->post(route('admin.account.accounts.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@raflora.com', 'role' => 'staff',
            'password' => 'StaffSecret123!', 'password_confirmation' => 'StaffSecret123!',
        ])->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings'))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@raflora.com']);
    }
}

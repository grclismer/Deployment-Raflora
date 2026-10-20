<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthenticationHygieneTest extends TestCase
{
    use RefreshDatabase;

    /* =========================================================================
     * CLIENT REGISTRATION TESTS
     * ========================================================================= */

    public function test_client_can_register_without_providing_a_username(): void
    {
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@example.com',
            'mobile_number' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', [
            'email' => 'maria.santos@example.com',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'mobile_number' => '09171234567',
            'username' => null,
            'role' => 'client',
        ]);
    }

    public function test_username_is_not_stored_or_used_if_submitted(): void
    {
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Juan',
            'last_name' => 'Luna',
            'username' => 'ignored_user_handle',
            'email' => 'juan.luna@example.com',
            'mobile_number' => '09181234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', [
            'email' => 'juan.luna@example.com',
            'username' => null,
        ]);
        $this->assertDatabaseMissing('users', [
            'username' => 'ignored_user_handle',
        ]);
    }

    public function test_registration_accepts_valid_11_numeric_digit_mobile_number(): void
    {
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Clara',
            'last_name' => 'Reyes',
            'email' => 'clara.reyes@example.com',
            'mobile_number' => '09191234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'clara.reyes@example.com',
            'mobile_number' => '09191234567',
        ]);
    }

    public function test_registration_rejects_mobile_number_with_fewer_than_11_digits(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Short',
            'last_name' => 'Number',
            'email' => 'short@example.com',
            'mobile_number' => '0917123456', // 10 digits
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['mobile_number']);
        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    public function test_registration_rejects_mobile_number_with_more_than_11_digits(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Long',
            'last_name' => 'Number',
            'email' => 'long@example.com',
            'mobile_number' => '091712345678', // 12 digits
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['mobile_number']);
        $this->assertDatabaseMissing('users', ['email' => 'long@example.com']);
    }

    public function test_registration_rejects_mobile_number_with_letters(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Letter',
            'last_name' => 'Number',
            'email' => 'letter@example.com',
            'mobile_number' => '0917123456a',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['mobile_number']);
        $this->assertDatabaseMissing('users', ['email' => 'letter@example.com']);
    }

    public function test_registration_rejects_mobile_number_with_symbols_or_spaces(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Symbol',
            'last_name' => 'Number',
            'email' => 'symbol@example.com',
            'mobile_number' => '0917-1234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['mobile_number']);
        $this->assertDatabaseMissing('users', ['email' => 'symbol@example.com']);
    }

    public function test_registration_rejects_mobile_number_with_mixed_letters_and_digits(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Mixed',
            'last_name' => 'Number',
            'email' => 'mixed@example.com',
            'mobile_number' => '0917ABC0001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['mobile_number']);
        $this->assertDatabaseMissing('users', ['email' => 'mixed@example.com']);
    }

    public function test_registration_rejects_empty_mobile_number(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Empty',
            'last_name' => 'Mobile',
            'email' => 'empty@example.com',
            'mobile_number' => '',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['mobile_number']);
        $this->assertDatabaseMissing('users', ['email' => 'empty@example.com']);
    }

    public function test_registration_preserves_safe_fields_and_never_preserves_passwords(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'SafeFirst',
            'last_name' => 'SafeLast',
            'email' => 'invalid-email-format',
            'mobile_number' => '09171112233',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'MismatchPass999!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email', 'password']);

        // Safe fields preserved
        $this->assertEquals('SafeFirst', session('_old_input.first_name'));
        $this->assertEquals('SafeLast', session('_old_input.last_name'));
        $this->assertEquals('09171112233', session('_old_input.mobile_number'));

        // Sensitive fields NOT preserved
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    /* =========================================================================
     * LOGIN & ENUMERATION TESTS
     * ========================================================================= */

    public function test_user_can_login_with_email_and_valid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'valid.client@example.com',
            'password' => Hash::make('SecretPassword123'),
            'role' => 'client',
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'valid.client@example.com',
            'password' => 'SecretPassword123',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'username' => 'legacyuser',
            'email' => 'legacy@example.com',
            'password' => Hash::make('SecretPassword123'),
            'role' => 'client',
        ]);

        // Attempting with username under the email parameter succeeds
        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'legacyuser',
            'password' => 'SecretPassword123',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_login_does_not_reveal_whether_account_exists(): void
    {
        // 1. Existing user with wrong password
        User::factory()->create([
            'email' => 'exists@example.com',
            'password' => Hash::make('CorrectPassword123'),
        ]);

        $response1 = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'exists@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response1->assertRedirect(route('login'));
        $response1->assertSessionHas('error', 'Email/username or password is incorrect.');

        // 2. Non-existent user
        $response2 = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'doesnotexist@example.com',
            'password' => 'SomePassword123',
        ]);

        $response2->assertRedirect(route('login'));
        $response2->assertSessionHas('error', 'Email/username or password is incorrect.');

        // Both responses yield identical generic error messages
        $this->assertEquals(session('error'), 'Email/username or password is incorrect.');
    }

    public function test_login_is_throttled_after_consecutive_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), [
                'email' => 'throttle@example.com',
                'password' => 'WrongPassword123',
            ]);
        }

        // 6th attempt should be blocked by rate limiter (HTTP 429)
        $response = $this->post(route('login.attempt'), [
            'email' => 'throttle@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response->assertStatus(429);
    }

    /* =========================================================================
     * PASSWORD RESET ENUMERATION TESTS
     * ========================================================================= */

    public function test_forgot_password_returns_generic_response_for_nonexistent_email(): void
    {
        $response = $this->from(route('forgot-password'))->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);

        $response->assertRedirect(route('forgot-password'));
        $response->assertSessionHas('status', 'If your email address is registered, you will receive a password reset link shortly.');
        $response->assertSessionHasNoErrors();
    }

    public function test_reset_password_page_does_not_leak_user_name(): void
    {
        $user = User::factory()->create([
            'email' => 'secretname@example.com',
            'first_name' => 'TopSecret',
            'last_name' => 'Identity',
            'name' => 'TopSecret Identity',
        ]);

        $response = $this->get(route('password.reset', [
            'token' => 'dummy-token',
            'email' => 'secretname@example.com',
        ]));

        $response->assertOk();
        $response->assertDontSee('TopSecret');
        $response->assertDontSee('Identity');
        $response->assertDontSee('Reset password for');
    }

    /* =========================================================================
     * GOOGLE OAUTH SECURITY TESTS
     * ========================================================================= */

    public function test_google_oauth_cannot_authenticate_admin_account(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@raflora.com',
            'role' => 'admin',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-admin-id-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('admin@raflora.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Raflora Admin');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    public function test_google_oauth_cannot_authenticate_staff_account(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff@raflora.com',
            'role' => 'staff',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-staff-id-456');
        $socialiteUser->shouldReceive('getEmail')->andReturn('staff@raflora.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Raflora Staff');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
        $this->assertNull($staff->fresh()->google_id);
    }

    public function test_google_oauth_authenticates_client_account_successfully(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-client-id-789');
        $socialiteUser->shouldReceive('getEmail')->andReturn('client.google@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Juan Dela Cruz');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticated();

        $clientUser = User::where('email', 'client.google@example.com')->first();
        $this->assertNotNull($clientUser);
        $this->assertEquals('client', $clientUser->role);
        $this->assertEquals('Juan', $clientUser->first_name);
        $this->assertEquals('Dela Cruz', $clientUser->last_name);
        $this->assertEquals('google-client-id-789', $clientUser->google_id);
    }

    /* =========================================================================
     * AUTHORIZATION BOUNDARY REGRESSION TESTS
     * ========================================================================= */

    public function test_staff_and_client_remain_unable_to_access_admin_routes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.dashboard'))->assertForbidden();
    }
}

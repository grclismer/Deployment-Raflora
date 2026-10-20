<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class EmailVerificationOtpTest extends TestCase
{
    use RefreshDatabase;

    /* =========================================================================
     * 1 & 2. REGISTRATION & OTP GENERATION
     * ========================================================================= */

    public function test_client_registration_creates_an_unverified_account_and_redirects_to_verification(): void
    {
        Mail::fake();

        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Alice',
            'last_name' => 'Gupta',
            'email' => 'alice.gupta@example.com',
            'mobile_number' => '09171112233',
            'address' => '456 Mango Ave, Cebu',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'alice.gupta@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());

        // OTP verification record created
        $verification = EmailVerification::where('user_id', $user->id)
            ->where('purpose', 'email_verification')
            ->first();

        $this->assertNotNull($verification);
        $this->assertNotNull($verification->otp_hash);
        $this->assertEquals(64, strlen($verification->otp_hash)); // SHA-256 length
        $this->assertEquals(0, $verification->attempts);
        $this->assertNull($verification->verified_at);

        // Verification email dispatched
        Mail::assertSent(EmailVerificationOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_verification_otp_is_stored_as_sha256_hash_and_never_in_plaintext(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');

        $this->assertEquals(6, strlen($otp));
        $this->assertTrue(ctype_digit($otp));

        $record = EmailVerification::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($record);

        // Database stores the hash, not the plaintext OTP
        $this->assertEquals(hash('sha256', $otp), $record->otp_hash);
        $this->assertNotEquals($otp, $record->otp_hash);
    }

    /* =========================================================================
     * 3 & 4. OTP VERIFICATION SUCCESS & FAILURE
     * ========================================================================= */

    public function test_valid_otp_verifies_the_email_and_redirects_to_client_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');

        $response = $this->actingAs($user)
            ->post(route('verification.verify'), [
                'otp' => $otp,
            ]);

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());

        $record = EmailVerification::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($record->verified_at);
    }

    public function test_invalid_otp_is_rejected_and_increments_attempts(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), [
                'otp' => '000000', // Invalid OTP
            ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors(['otp']);

        $user->refresh();
        $this->assertNull($user->email_verified_at);

        $record = EmailVerification::where('user_id', $user->id)->latest()->first();
        $this->assertEquals(1, $record->attempts);
        $this->assertNull($record->verified_at);
    }

    /* =========================================================================
     * 5 & 6. EXPIRATION & REUSE PREVENTION
     * ========================================================================= */

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');

        // Artificially expire the OTP
        EmailVerification::where('user_id', $user->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), [
                'otp' => $otp,
            ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors(['otp']);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_otp_cannot_be_reused_after_successful_verification(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');

        // First verification succeeds
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $otp]);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Reset user verification to test re-use attempt against same record
        $user->update(['email_verified_at' => null]);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), ['otp' => $otp]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors(['otp']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_generic_otp_service_validates_otp_without_modifying_email_verified_at(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'device_verification');

        $result = $otpService->verify($user, $otp, 'device_verification');

        $this->assertTrue($result['success']);
        $this->assertSame('device_verification', $result['purpose']);
        $this->assertNotNull($result['verification']->verified_at);

        // Crucial architectural assertion: Generic OTP validation does NOT mutate email_verified_at
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /* =========================================================================
     * 7 & 8. ATTEMPT LIMITS & RESEND COOLDOWN
     * ========================================================================= */

    public function test_otp_verification_locks_after_maximum_attempts(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $correctOtp = $otpService->generate($user, 'email_verification');

        // Submit 5 incorrect attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)->post(route('verification.verify'), [
                'otp' => '111111',
            ]);
        }

        $record = EmailVerification::where('user_id', $user->id)->latest()->first();
        $this->assertEquals(5, $record->attempts);

        // Now attempt with the correct OTP - must be rejected due to attempt lockout
        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), [
                'otp' => $correctOtp,
            ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors(['otp']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_otp_resend_is_throttled_by_cooldown_period(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otpService->generate($user, 'email_verification');

        // Attempt immediate resend (cooldown is 60s)
        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.resend'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors(['resend']);

        Mail::assertNothingSent();
    }

    public function test_otp_can_be_resent_after_cooldown_expires(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $otpService = app(OtpService::class);
        $otpService->generate($user, 'email_verification');

        // Fast forward time past 60 seconds cooldown
        EmailVerification::where('user_id', $user->id)->update([
            'last_resend_at' => now()->subSeconds(65),
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.resend'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status');

        Mail::assertSent(EmailVerificationOtpMail::class);
    }

    /* =========================================================================
     * 9, 10, & 11. CENTRALIZED VERIFICATION GATE
     * ========================================================================= */

    public function test_unverified_client_cannot_access_protected_client_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_client_can_access_verification_notice_page(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('Verify Your Email');
    }

    public function test_verified_client_can_access_protected_client_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
    }

    public function test_verified_client_accessing_verification_notice_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertRedirect(route('client.dashboard'));
    }

    /* =========================================================================
     * 12, 13, & 14. AUTHENTICATION & HYGIENE INTEGRATION
     * ========================================================================= */

    public function test_duplicate_email_registration_remains_rejected(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Dup',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'mobile_number' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_unverified_client_logging_in_is_redirected_to_verification_gate(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
    }

    /* =========================================================================
     * 15 & 16. GOOGLE OAUTH INTERACTION & ADMIN/STAFF HARDENING
     * ========================================================================= */

    public function test_google_client_registration_marks_email_verified(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-client-uid-999');
        $socialiteUser->shouldReceive('getName')->andReturn('Google Client User');
        $socialiteUser->shouldReceive('getEmail')->andReturn('googleclient@example.com');

        $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('client.dashboard'));

        $user = User::where('email', 'googleclient@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_google_oauth_continues_to_strictly_block_admin_and_staff(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.target@raflora.com',
            'role' => 'admin',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-admin-hack-id');
        $socialiteUser->shouldReceive('getEmail')->andReturn('admin.target@raflora.com');

        $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_password_is_never_returned_through_old_input_on_validation_failure(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'mobile_number' => '09171234567',
            'password' => 'SecretPassword123!',
            'password_confirmation' => 'SecretPassword123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertFalse(session()->hasOldInput('password_confirmation'));
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertTrue(session()->hasOldInput('first_name'));
    }
}

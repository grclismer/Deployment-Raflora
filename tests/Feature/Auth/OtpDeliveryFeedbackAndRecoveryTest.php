<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PhpMailerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class OtpDeliveryFeedbackAndRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.feedback@raflora.com',
            'password' => Hash::make('AdminPass123!'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'is_bootstrap' => false,
        ]);

        $this->client = User::factory()->create([
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client.feedback@example.com',
            'password' => Hash::make('ClientPass123!'),
            'role' => 'client',
            'email_verified_at' => null,
        ]);
    }

    /**
     * Requirement 7.1: Successful initial OTP dispatch delivers expected success feedback.
     */
    public function test_successful_client_registration_dispatch_provides_success_feedback(): void
    {
        Mail::fake();

        $response = $this->post(route('register.attempt'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.register@example.com',
            'mobile_number' => '09171234567',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('success', 'Account created! Please enter the 6-digit verification code sent to your email.');
        $response->assertSessionMissing('warning');
    }

    /**
     * Requirement 7.1: Successful untrusted device challenge dispatch provides status feedback.
     */
    public function test_successful_device_challenge_dispatch_provides_status_feedback(): void
    {
        Mail::fake();

        $response = $this->post(route('login.attempt'), [
            'email' => $this->admin->email,
            'password' => 'AdminPass123!',
        ]);

        $response->assertRedirect(route('device.verify.show'));
        $response->assertSessionHas('status', 'A 6-digit verification code has been sent to your registered email.');
        $this->assertGuest();
    }

    /**
     * Requirement 7.2: Failed initial OTP dispatch provides truthful failure feedback without false claims.
     */
    public function test_failed_client_registration_dispatch_provides_truthful_failure_feedback(): void
    {
        $mockOtpService = Mockery::mock(OtpService::class)->makePartial();
        $mockOtpService->shouldReceive('sendOtp')->andReturn(false);
        $this->app->instance(OtpService::class, $mockOtpService);

        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.fail@example.com',
            'mobile_number' => '09179876543',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionMissing('success');
        $response->assertSessionHas('warning');
        $response->assertSessionHasErrors(['otp']);
    }

    /**
     * Requirement 7.2: Failed untrusted device challenge dispatch warns user and does not claim email was sent.
     */
    public function test_failed_device_challenge_dispatch_provides_error_and_does_not_claim_email_sent(): void
    {
        $mockOtpService = Mockery::mock(OtpService::class)->makePartial();
        $mockOtpService->shouldReceive('sendOtp')->andReturn(false);
        $this->app->instance(OtpService::class, $mockOtpService);

        $response = $this->post(route('login.attempt'), [
            'email' => $this->admin->email,
            'password' => 'AdminPass123!',
        ]);

        $response->assertRedirect(route('device.verify.show'));
        $response->assertSessionMissing('status');
        $response->assertSessionHasErrors(['otp']);
        $this->assertGuest();
    }

    /**
     * Requirement 7.3: Successful resend on device verification returns success status.
     */
    public function test_successful_device_verification_resend_provides_status_feedback(): void
    {
        Mail::fake();

        $session = [
            'pending_device_auth_user_id' => $this->admin->id,
            'pending_device_auth_expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];

        $response = $this->withSession($session)
            ->from(route('device.verify.show'))
            ->post(route('device.verify.resend'));

        $response->assertRedirect(route('device.verify.show'));
        $response->assertSessionHas('status', 'A new verification code has been sent to your email.');
        $response->assertSessionDoesntHaveErrors();
    }

    /**
     * Requirement 7.3: Successful resend on email verification returns success status.
     */
    public function test_successful_email_verification_resend_provides_status_feedback(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->client)
            ->from(route('verification.notice'))
            ->post(route('verification.resend'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status', 'A new verification code has been sent to your email.');
        $response->assertSessionDoesntHaveErrors();
    }

    /**
     * Requirement 7.4: Failed resend on device verification provides error feedback and avoids false status.
     */
    public function test_failed_device_verification_resend_provides_error_feedback(): void
    {
        $mockOtpService = Mockery::mock(OtpService::class)->makePartial();
        $mockOtpService->shouldReceive('sendOtp')->andReturn(false);
        $this->app->instance(OtpService::class, $mockOtpService);

        $session = [
            'pending_device_auth_user_id' => $this->admin->id,
            'pending_device_auth_expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];

        $response = $this->withSession($session)
            ->from(route('device.verify.show'))
            ->post(route('device.verify.resend'));

        $response->assertRedirect(route('device.verify.show'));
        $response->assertSessionMissing('status');
        $response->assertSessionHasErrors(['resend']);
    }

    /**
     * Requirement 7.4: Failed resend on email verification provides error feedback and avoids false status.
     */
    public function test_failed_email_verification_resend_provides_error_feedback(): void
    {
        $mockOtpService = Mockery::mock(OtpService::class)->makePartial();
        $mockOtpService->shouldReceive('sendOtp')->andReturn(false);
        $this->app->instance(OtpService::class, $mockOtpService);

        $response = $this->actingAs($this->client)
            ->from(route('verification.notice'))
            ->post(route('verification.resend'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionMissing('status');
        $response->assertSessionHasErrors(['resend']);
    }

    /**
     * Requirement 7.5: Device verification remains incomplete after delivery failure.
     */
    public function test_device_verification_remains_incomplete_and_unauthenticated_after_delivery_failure(): void
    {
        $mockOtpService = Mockery::mock(OtpService::class)->makePartial();
        $mockOtpService->shouldReceive('sendOtp')->andReturn(false);
        $this->app->instance(OtpService::class, $mockOtpService);

        $response = $this->post(route('login.attempt'), [
            'email' => $this->admin->email,
            'password' => 'AdminPass123!',
        ]);

        $response->assertRedirect(route('device.verify.show'));
        $this->assertGuest();

        // Protected dashboard routes remain inaccessible
        $dashboardResponse = $this->get(route('admin.dashboard'));
        $dashboardResponse->assertRedirect(route('login'));
    }

    /**
     * Requirement 7.6: Password recovery maintains account-enumeration-safe responses across existing/non-existent accounts.
     */
    public function test_password_recovery_maintains_account_enumeration_safe_responses(): void
    {
        Mail::fake();

        // 1. Non-existent account
        $nonExistentResponse = $this->post(route('admin.password.send'), [
            'email' => 'unknown.account@raflora.com',
        ]);
        $nonExistentResponse->assertRedirect(route('admin.password.otp.show'));
        $nonExistentResponse->assertSessionHas('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');

        // 2. Real admin account with successful dispatch
        $realAdminResponse = $this->post(route('admin.password.send'), [
            'email' => $this->admin->email,
        ]);
        $realAdminResponse->assertRedirect(route('admin.password.otp.show'));
        $realAdminResponse->assertSessionHas('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');
    }

    /**
     * Requirement 7.6: Password recovery delivery failure maintains account-enumeration safety and records audit.
     */
    public function test_password_recovery_delivery_failure_maintains_enumeration_safety_and_audits(): void
    {
        $mockOtpService = Mockery::mock(OtpService::class)->makePartial();
        $mockOtpService->shouldReceive('sendOtp')->andReturn(false);
        $this->app->instance(OtpService::class, $mockOtpService);

        $failedDeliveryResponse = $this->post(route('admin.password.send'), [
            'email' => $this->admin->email,
        ]);

        // The response must be identical to preserve account enumeration protection
        $failedDeliveryResponse->assertRedirect(route('admin.password.otp.show'));
        $failedDeliveryResponse->assertSessionHas('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');
        $failedDeliveryResponse->assertSessionMissing('error');

        // Audit log recorded the internal failure without leaking account existence to user
        $auditLog = AuditLog::where('user_id', $this->admin->id)
            ->where('action', 'admin_password_reset_delivery_failed')
            ->first();

        $this->assertNotNull($auditLog);
    }

    /**
     * Requirement 7.7: Cooldown enforcement and OTP state consistency.
     */
    public function test_cooldown_is_enforced_on_resend_and_failed_delivery_expires_otp(): void
    {
        Mail::fake();
        $realOtpService = app(OtpService::class);

        $session = [
            'pending_device_auth_user_id' => $this->admin->id,
            'pending_device_auth_expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];

        // First resend succeeds
        $res1 = $this->withSession($session)->from(route('device.verify.show'))->post(route('device.verify.resend'));
        $res1->assertSessionHas('status');

        // Immediate subsequent resend is rejected by cooldown
        $res2 = $this->withSession($session)->from(route('device.verify.show'))->post(route('device.verify.resend'));
        $res2->assertSessionHasErrors(['resend']);
        $this->assertStringContainsString('wait', strtolower(session('errors')->first('resend')));

        // Now test failed delivery marks OTP expired in database
        config(['mail.use_phpmailer' => true]);
        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendVerificationOtp')->andReturn(false);
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')->andThrow(new \RuntimeException('Connection failed'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        $otp = $realOtpService->generate($this->admin, 'device_verification');
        $sendResult = $realOtpService->sendOtp($this->admin, $otp, 10, 'device_verification');
        $this->assertFalse($sendResult);

        // Record must be expired
        $record = EmailVerification::where('user_id', $this->admin->id)
            ->where('otp_hash', hash('sha256', $otp))
            ->first();
        $this->assertNotNull($record);
        $this->assertTrue($record->isExpired());

        // Verifying with this undelivered OTP is rejected
        $verifyResult = $realOtpService->verify($this->admin, $otp, 'device_verification');
        $this->assertFalse($verifyResult['success']);
        $this->assertStringContainsString('expired', strtolower($verifyResult['message']));
    }

    /**
     * Requirement: Client password reset link dispatches via PHPMailer when enabled.
     */
    public function test_client_password_reset_link_uses_phpmailer_when_enabled(): void
    {
        config(['mail.use_phpmailer' => true]);

        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendPasswordReset')
            ->once()
            ->with($this->client->email, $this->client->first_name, Mockery::type('string'))
            ->andReturn(true);
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        Mail::fake();

        $response = $this->post(route('password.email'), [
            'email' => $this->client->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If your email address is registered, you will receive a password reset link shortly.');
        Mail::assertNothingSent();
    }

    /**
     * Requirement: Client password reset link falls back to Laravel Mail broker when PHPMailer fails.
     */
    public function test_client_password_reset_link_falls_back_to_laravel_mail_when_phpmailer_fails(): void
    {
        config(['mail.use_phpmailer' => true]);

        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendPasswordReset')
            ->once()
            ->andReturn(false);
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        Mail::fake();

        $response = $this->post(route('password.email'), [
            'email' => $this->client->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If your email address is registered, you will receive a password reset link shortly.');
        
        // Laravel's password broker fallback sends notification/mail via Laravel Mail
        // Notification is sent to the client
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->client->email,
        ]);
    }

    /**
     * Requirement: SMTP configuration and credentials resolve correctly via Laravel config repository.
     */
    public function test_smtp_configuration_resolves_via_config_repository(): void
    {
        $this->assertEquals(env('MAIL_HOST', 'smtp.gmail.com'), config('mail.mailers.smtp.host'));
        $this->assertEquals((int) env('MAIL_PORT', 587), (int) config('mail.mailers.smtp.port'));
        $this->assertEquals(env('MAIL_USERNAME'), config('mail.mailers.smtp.username'));
        $this->assertNotEmpty(config('mail.mailers.smtp.password'));
        $this->assertNotEmpty(config('mail.mailers.smtp.password_reset'));
        $this->assertEquals(config('mail.mailers.smtp.password'), config('mail.mailers.smtp.password_reset'));
    }
}


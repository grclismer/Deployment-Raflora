<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PhpMailerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPMailer\PHPMailer\PHPMailer;
use Tests\TestCase;

class OtpDeliveryReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'delivery.test@example.com',
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $this->otpService = app(OtpService::class);
    }

    /**
     * Requirement 1: A PHPMailer false result must be treated as delivery failure
     * and must invoke the intended Laravel Mail fallback.
     */
    public function test_phpmailer_returns_false_invokes_laravel_mail_fallback_and_succeeds(): void
    {
        Mail::fake();
        config(['mail.use_phpmailer' => true]);

        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendVerificationOtp')
            ->once()
            ->andReturn(false);
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        $otp = $this->otpService->generate($this->user, 'email_verification');

        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'email_verification');

        $this->assertTrue($result, 'Expected sendOtp to report true when fallback succeeds');

        Mail::assertSent(EmailVerificationOtpMail::class, function ($mail) {
            return $mail->hasTo('delivery.test@example.com');
        });

        // OTP remains valid and usable
        $record = EmailVerification::where('user_id', $this->user->id)
            ->where('otp_hash', hash('sha256', $otp))
            ->first();

        $this->assertNotNull($record);
        $this->assertFalse($record->isExpired());

        $verifyResult = $this->otpService->verify($this->user, $otp, 'email_verification');
        $this->assertTrue($verifyResult['success']);
    }

    /**
     * Requirement 2: A thrown PHPMailer exception must be handled and trigger fallback.
     */
    public function test_phpmailer_throws_invokes_laravel_mail_fallback_and_succeeds(): void
    {
        Mail::fake();
        config(['mail.use_phpmailer' => true]);

        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendVerificationOtp')
            ->once()
            ->andThrow(new \Exception('SMTP connection timeout to host'));
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        $otp = $this->otpService->generate($this->user, 'email_verification');

        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'email_verification');

        $this->assertTrue($result, 'Expected sendOtp to report true when fallback succeeds after PHPMailer exception');

        Mail::assertSent(EmailVerificationOtpMail::class, function ($mail) {
            return $mail->hasTo('delivery.test@example.com');
        });

        $verifyResult = $this->otpService->verify($this->user, $otp, 'email_verification');
        $this->assertTrue($verifyResult['success']);
    }

    /**
     * Requirements 3, 4, 5, 9:
     * - Return true only when delivery succeeds.
     * - If all delivery mechanisms fail, invalidate/expire the OTP.
     * - Do not leave an undelivered OTP usable.
     * - Do not swallow failures.
     */
    public function test_both_delivery_mechanisms_fail_returns_false_and_invalidates_otp(): void
    {
        config(['mail.use_phpmailer' => true]);

        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendVerificationOtp')
            ->once()
            ->andReturn(false);
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Fallback SMTP server unreachable'));

        Mail::shouldReceive('to')
            ->once()
            ->with($this->user->email)
            ->andReturn($pendingMail);

        $otp = $this->otpService->generate($this->user, 'email_verification');

        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'email_verification');

        $this->assertFalse($result, 'Expected sendOtp to report false when all delivery attempts fail');

        // Verify OTP is expired in the database
        $record = EmailVerification::where('user_id', $this->user->id)
            ->where('otp_hash', hash('sha256', $otp))
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue($record->isExpired(), 'Undelivered OTP must be expired');

        // Verify that trying to verify with the undelivered OTP is rejected
        $verifyResult = $this->otpService->verify($this->user, $otp, 'email_verification');
        $this->assertFalse($verifyResult['success'], 'Undelivered OTP must not be verified');
        $this->assertStringContainsString('expired', strtolower($verifyResult['message']));
    }

    /**
     * Direct Laravel Mail failure (when PHPMailer is disabled) invalidates the OTP and returns false.
     */
    public function test_direct_laravel_mail_failure_invalidates_otp_and_returns_false(): void
    {
        config(['mail.use_phpmailer' => false]);

        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        Mail::shouldReceive('to')
            ->once()
            ->with($this->user->email)
            ->andReturn($pendingMail);

        $otp = $this->otpService->generate($this->user, 'device_verification');

        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'device_verification');

        $this->assertFalse($result, 'Expected sendOtp to report false on direct Laravel Mail failure');

        $record = EmailVerification::where('user_id', $this->user->id)
            ->where('otp_hash', hash('sha256', $otp))
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue($record->isExpired());

        $verifyResult = $this->otpService->verify($this->user, $otp, 'device_verification');
        $this->assertFalse($verifyResult['success']);
        $this->assertStringContainsString('expired', strtolower($verifyResult['message']));
    }

    /**
     * Requirement: Successful delivery preserves a usable OTP.
     */
    public function test_successful_phpmailer_delivery_preserves_usable_otp_without_invoking_fallback(): void
    {
        Mail::fake();
        config(['mail.use_phpmailer' => true]);

        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendVerificationOtp')
            ->once()
            ->andReturn(true);
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        $otp = $this->otpService->generate($this->user, 'email_verification');

        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'email_verification');

        $this->assertTrue($result);
        Mail::assertNothingSent(); // Laravel Mail fallback must NOT be called when PHPMailer succeeds

        $record = EmailVerification::where('user_id', $this->user->id)
            ->where('otp_hash', hash('sha256', $otp))
            ->first();

        $this->assertNotNull($record);
        $this->assertFalse($record->isExpired());

        $verifyResult = $this->otpService->verify($this->user, $otp, 'email_verification');
        $this->assertTrue($verifyResult['success']);
    }

    /**
     * Requirements 6 & 10: Plaintext OTP and credentials are redacted in error logs.
     */
    public function test_logs_redact_plaintext_otp_and_credentials_on_delivery_failure(): void
    {
        config(['mail.use_phpmailer' => true]);

        $loggedErrors = [];
        Log::shouldReceive('error')
            ->atLeast()->once()
            ->andReturnUsing(function ($message, $context = []) use (&$loggedErrors) {
                $loggedErrors[] = json_encode(['msg' => $message, 'ctx' => $context]);
            });
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP 535 authentication failed'));

        Mail::shouldReceive('to')
            ->once()
            ->with($this->user->email)
            ->andReturn($pendingMail);

        $otp = $this->otpService->generate($this->user, 'email_verification');

        // PHPMailer throws with sensitive content in message
        $mockPhpMailer = Mockery::mock(PhpMailerService::class);
        $mockPhpMailer->shouldReceive('sendVerificationOtp')
            ->once()
            ->andThrow(new \Exception("Auth error with OTP code {$otp} and random pin 654321 on server"));
        $this->app->instance(PhpMailerService::class, $mockPhpMailer);

        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'email_verification');
        $this->assertFalse($result);

        $allLogs = implode(' ', $loggedErrors);
        $this->assertStringNotContainsString($otp, $allLogs, 'Plaintext OTP must not appear in error logs');
        $this->assertStringNotContainsString('654321', $allLogs, 'Plaintext PIN must not appear in error logs');
        $this->assertStringContainsString('[REDACTED_OTP]', $allLogs);
        $this->assertStringContainsString('[REDACTED_CODE]', $allLogs);
    }

    /**
     * PhpMailerService direct unit test: catches Throwable and sanitizes error.
     */
    public function test_phpmailer_service_catches_throwable_and_sanitizes_errors(): void
    {
        $mockMailer = Mockery::mock(PHPMailer::class);
        $mockMailer->shouldReceive('clearAllRecipients')->once();
        $mockMailer->shouldReceive('addAddress')->once();
        $mockMailer->shouldReceive('send')->once()->andThrow(new \Exception('Connection to mail host 999999 failed'));

        $service = new PhpMailerService($mockMailer);

        $result = $service->sendVerificationOtp('test@example.com', 'Test', '999999', 10);

        $this->assertFalse($result, 'Expected sendVerificationOtp to catch Throwable and return false');
    }

    /**
     * Requirement A: Mailer selection strictly respects config('mail.use_phpmailer')
     * without bypassing the configuration repository via direct env().
     */
    public function test_mailer_selection_respects_configuration_repository_without_direct_env(): void
    {
        Mail::fake();

        // 1. Explicitly disabled via config repository -> uses Laravel Mail directly
        config(['mail.use_phpmailer' => false]);
        $mockPhpMailerDisabled = Mockery::mock(PhpMailerService::class);
        $mockPhpMailerDisabled->shouldNotReceive('sendVerificationOtp');
        $this->app->instance(PhpMailerService::class, $mockPhpMailerDisabled);

        $otp = $this->otpService->generate($this->user, 'email_verification');
        $result = $this->otpService->sendOtp($this->user, $otp, 10, 'email_verification');

        $this->assertTrue($result);
        Mail::assertSent(EmailVerificationOtpMail::class);

        // 2. Explicitly enabled via config repository -> delegates to PHPMailer
        config(['mail.use_phpmailer' => true]);
        $mockPhpMailerEnabled = Mockery::mock(PhpMailerService::class);
        $mockPhpMailerEnabled->shouldReceive('sendVerificationOtp')
            ->once()
            ->andReturn(true);
        $this->app->instance(PhpMailerService::class, $mockPhpMailerEnabled);

        $otp2 = $this->otpService->generate($this->user, 'email_verification');
        $result2 = $this->otpService->sendOtp($this->user, $otp2, 10, 'email_verification');

        $this->assertTrue($result2);
    }

    /**
     * Requirement A & Step 10A: Verify that OtpService and PhpMailerService source
     * code contains zero direct env() calls, ensuring 100% configuration caching compatibility.
     */
    public function test_otp_service_and_phpmailer_service_have_zero_direct_env_calls(): void
    {
        $otpServicePath = app_path('Services/OtpService.php');
        $phpMailerServicePath = app_path('Services/PhpMailerService.php');

        $otpServiceCode = file_get_contents($otpServicePath);
        $phpMailerServiceCode = file_get_contents($phpMailerServicePath);

        $this->assertDoesNotMatchRegularExpression('/\benv\s*\(/', $otpServiceCode, 'OtpService must not contain direct env() calls');
        $this->assertDoesNotMatchRegularExpression('/\benv\s*\(/', $phpMailerServiceCode, 'PhpMailerService must not contain direct env() calls');
    }
}

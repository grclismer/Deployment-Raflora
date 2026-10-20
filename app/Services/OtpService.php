<?php

namespace App\Services;

use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const DEFAULT_EXPIRY_MINUTES = 10;
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate a new cryptographically secure numeric OTP, hash it, store it,
     * invalidate prior pending OTPs, and return the plaintext OTP for delivery.
     * The plaintext OTP is NEVER logged or persisted.
     */
    public function generate(
        User $user,
        string $purpose = 'email_verification',
        int $expireMinutes = self::DEFAULT_EXPIRY_MINUTES
    ): string {
        // Invalidate previous unverified OTPs for this user and purpose
        EmailVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update([
                'expires_at' => now(),
            ]);

        // Generate 6-digit numeric OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = hash('sha256', $otp);

        EmailVerification::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_hash' => $otpHash,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes($expireMinutes),
            'attempts' => 0,
            'last_resend_at' => now(),
        ]);

        return $otp;
    }

    /**
     * Verify a submitted OTP against the latest active verification record for user and purpose.
     */
    public function verify(
        User $user,
        string $submittedOtp,
        string $purpose = 'email_verification',
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS
    ): array {
        $submittedOtp = trim($submittedOtp);

        $record = EmailVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $record) {
            return [
                'success' => false,
                'message' => 'No active verification code found. Please request a new code.',
            ];
        }

        if ($record->isExpired()) {
            return [
                'success' => false,
                'message' => 'The verification code has expired. Please request a new code.',
            ];
        }

        if ($record->hasExceededAttempts($maxAttempts)) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. This code is locked. Please request a new code.',
            ];
        }

        // Increment attempt count
        $record->increment('attempts');

        // Constant-time hash verification
        $submittedHash = hash('sha256', $submittedOtp);
        if (hash_equals($record->otp_hash, $submittedHash)) {
            $record->update(['verified_at' => now()]);

            // Invalidate any other leftover unverified records for this purpose
            EmailVerification::where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->where('id', '!=', $record->id)
                ->update(['expires_at' => now()]);

            return [
                'success' => true,
                'message' => 'Verification code verified successfully.',
                'verification' => $record,
                'purpose' => $purpose,
                'user' => $user,
            ];
        }

        // Failed attempt calculation
        $remaining = max(0, $maxAttempts - $record->attempts);
        if ($remaining === 0) {
            return [
                'success' => false,
                'message' => 'Invalid verification code. Maximum attempts reached. Please request a new code.',
            ];
        }

        return [
            'success' => false,
            'message' => "Invalid verification code. You have {$remaining} attempt(s) remaining.",
        ];
    }

    /**
     * Check if a new OTP can be resent based on cooldown period.
     */
    public function canResend(
        User $user,
        string $purpose = 'email_verification',
        int $cooldownSeconds = self::DEFAULT_RESEND_COOLDOWN_SECONDS
    ): array {
        $record = EmailVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $record || ! $record->last_resend_at) {
            return ['allowed' => true, 'seconds_remaining' => 0];
        }

        $cooldownExpiresAt = $record->last_resend_at->copy()->addSeconds($cooldownSeconds);

        if (now()->lessThan($cooldownExpiresAt)) {
            $secondsRemaining = (int) ceil(now()->diffInSeconds($cooldownExpiresAt, true));
            return [
                'allowed' => false,
                'seconds_remaining' => max(1, $secondsRemaining),
            ];
        }

        return ['allowed' => true, 'seconds_remaining' => 0];
    }

    /**
     * Send the OTP code to the user's email via PHPMailer or Laravel Mail fallback.
     *
     * In the event of delivery failure:
     * - A PHPMailer false or exception triggers Laravel Mail fallback.
     * - If all delivery mechanisms fail, the unverified OTP record is invalidated/expired
     *   so an undelivered OTP cannot remain usable.
     * - Logs are sanitized to avoid leaking OTPs or credentials.
     * - Returns true only if delivery succeeds; false otherwise.
     */
    public function sendOtp(
        User $user,
        string $otp,
        int $expireMinutes = self::DEFAULT_EXPIRY_MINUTES,
        ?string $purpose = null
    ): bool {
        $usePhpMailer = (bool) config('mail.use_phpmailer', false);

        if ($usePhpMailer) {
            $phpMailerSuccess = false;

            try {
                $phpMailerService = app(PhpMailerService::class);
                $phpMailerSuccess = (bool) $phpMailerService->sendVerificationOtp(
                    $user->email,
                    $user->first_name ?? $user->name ?? 'Client',
                    $otp,
                    $expireMinutes
                );
            } catch (\Throwable $e) {
                Log::error('PHPMailer failed to send OTP verification email, falling back to Laravel Mail', [
                    'user_id' => $user->id,
                    'error' => $this->sanitizeLogMessage($e->getMessage(), $otp),
                ]);
                $phpMailerSuccess = false;
            }

            if ($phpMailerSuccess) {
                return true;
            }

            Log::warning('PHPMailer returned false for OTP delivery, invoking Laravel Mail fallback', [
                'user_id' => $user->id,
            ]);
        }

        // Primary delivery (when PHPMailer is disabled) OR fallback delivery (when PHPMailer failed)
        try {
            Mail::to($user->email)->send(
                new EmailVerificationOtpMail(
                    $otp,
                    $user->first_name ?? $user->name ?? 'Valued Client',
                    $expireMinutes
                )
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Laravel Mail delivery failed for OTP verification', [
                'user_id' => $user->id,
                'error' => $this->sanitizeLogMessage($e->getMessage(), $otp),
            ]);

            // All delivery mechanisms failed: invalidate/expire the OTP so it cannot be used
            $this->invalidateOtp($user, $otp, $purpose);

            return false;
        }
    }

    /**
     * Invalidate an unverified OTP record by expiring it immediately.
     * Prevents an undelivered or failed OTP from remaining usable.
     */
    public function invalidateOtp(User $user, string $otp, ?string $purpose = null): void
    {
        $otpHash = hash('sha256', trim($otp));

        $query = EmailVerification::where('user_id', $user->id)
            ->where('otp_hash', $otpHash)
            ->whereNull('verified_at');

        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }

        $query->update([
            'expires_at' => now()->subMinute(),
        ]);

        Log::warning('Unverified OTP invalidated due to email delivery failure', [
            'user_id' => $user->id,
            'purpose' => $purpose,
        ]);
    }

    /**
     * Sanitize log messages to prevent leaking OTPs, credentials, or sensitive tokens.
     */
    protected function sanitizeLogMessage(string $message, ?string $otp = null): string
    {
        if ($otp !== null && $otp !== '') {
            $message = str_replace($otp, '[REDACTED_OTP]', $message);
        }

        $message = preg_replace('/\b\d{6}\b/', '[REDACTED_CODE]', $message);

        $smtpPass = config('mail.mailers.smtp.password_reset', config('mail.mailers.smtp.password'));
        if (! empty($smtpPass)) {
            $message = str_replace($smtpPass, '[REDACTED_PASSWORD]', $message);
        }

        return $message;
    }
}

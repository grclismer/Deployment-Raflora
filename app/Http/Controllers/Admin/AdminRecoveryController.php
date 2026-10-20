<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecoveryCode;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminRecoveryController extends Controller
{
    /**
     * Dedicated OTP purpose for emergency admin recovery.
     */
    public const OTP_PURPOSE = 'admin_recovery';

    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Show the emergency recovery screen.
     */
    public function show(Request $request): View
    {
        return $this->showRecovery($request);
    }

    public function showRecovery(Request $request): View
    {
        $authorizedCodeId = $request->session()->get('admin_recovery_authorized_code_id') ?? $request->session()->get('admin_recovery_code_id');
        $replacementCode = $request->session()->get('admin_replacement_recovery_code');
        $email = $request->session()->get('admin_recovery_email');
        $emailVerified = $request->session()->get('admin_recovery_email_verified', false);

        $step = 'code';
        if ($replacementCode) {
            $step = 'replacement_code';
        } elseif ($emailVerified) {
            $step = 'password';
        } elseif ($email) {
            $step = 'otp';
        } elseif ($authorizedCodeId) {
            $step = 'email';
        }

        $admin = User::where('role', 'admin')->first();
        $cooldownSeconds = 0;
        if ($admin) {
            $cooldown = $this->otpService->canResend($admin, self::OTP_PURPOSE);
            $cooldownSeconds = $cooldown['seconds_remaining'] ?? 0;
        }

        return view('admin.recovery.index', [
            'step' => $step,
            'email' => $email,
            'replacementCode' => $replacementCode,
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    /**
     * Factor 1: Verify the emergency recovery code.
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recovery_code' => ['required', 'string'],
        ], [
            'recovery_code.required' => 'Please enter your emergency recovery code.',
        ]);

        $cleanCode = strtoupper(str_replace(['-', ' '], '', trim($validated['recovery_code'])));
        $submittedHash = hash('sha256', $cleanCode);

        // Raflora has exactly one primary administrator
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            return back()->withErrors(['recovery_code' => 'No administrative account found.']);
        }

        $record = AdminRecoveryCode::where('user_id', $admin->id)
            ->where('is_used', false)
            ->latest('id')
            ->first();

        if (! $record || ! $record->isUsable(5)) {
            $this->recordSecurityEvent($admin, 'admin_recovery_failed', 'Emergency recovery attempt rejected: no usable recovery code or maximum attempts reached.');
            return back()->withErrors(['recovery_code' => 'Invalid recovery code or maximum attempts reached.']);
        }

        if (! hash_equals($record->code_hash, $submittedHash)) {
            $record->increment('attempts');
            $remaining = max(0, 5 - $record->attempts);
            $this->recordSecurityEvent($admin, 'admin_recovery_failed', "Invalid recovery code submitted. {$remaining} attempts remaining.");

            if ($remaining === 0) {
                return back()->withErrors(['recovery_code' => 'Invalid recovery code. Maximum attempts reached. Code is now locked.']);
            }

            return back()->withErrors(['recovery_code' => "Invalid recovery code. You have {$remaining} attempt(s) remaining."]);
        }

        // Recovery code factor satisfied - authorize session for this specific recovery code record
        $request->session()->put('admin_recovery_code_verified', true);
        $request->session()->put('admin_recovery_authorized_code_id', $record->id);
        $request->session()->put('admin_recovery_code_id', $record->id);
        $this->recordSecurityEvent($admin, 'admin_recovery_started', "Emergency recovery code #{$record->id} validated successfully.");

        return redirect()->route('admin.recovery.show');
    }

    /**
     * Factor 2: Submit new active operational email during emergency recovery.
     */
    public function submitEmail(Request $request): RedirectResponse
    {
        if (! $request->session()->get('admin_recovery_code_verified')) {
            return redirect()->route('admin.recovery.show')
                ->withErrors(['recovery_code' => 'Please verify your recovery code first.']);
        }

        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $admin->id],
        ], [
            'email.required' => 'A new active email address is required.',
            'email.unique' => 'This email address is already in use by another account.',
        ]);

        $newEmail = strtolower(trim($validated['email']));
        $request->session()->put('admin_recovery_email', $newEmail);
        $request->session()->put('admin_recovery_email_verified', false);

        // Generate and dispatch OTP using dedicated purpose admin_recovery
        $otp = $this->otpService->generate($admin, self::OTP_PURPOSE);
        $userForDispatch = clone $admin;
        $userForDispatch->email = $newEmail;
        $sent = $this->otpService->sendOtp($userForDispatch, $otp, 10, self::OTP_PURPOSE);

        if (! $sent) {
            return redirect()->route('admin.recovery.show')
                ->withErrors(['otp' => 'Failed to deliver verification code to your new email. Please request a new code.']);
        }

        return redirect()->route('admin.recovery.show')
            ->with('status', 'A 6-digit verification code has been sent to your new email.');
    }

    /**
     * Factor 2: Verify OTP sent to the new email using dedicated purpose admin_recovery.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        if (! $request->session()->get('admin_recovery_code_verified')) {
            return redirect()->route('admin.recovery.show')
                ->withErrors(['recovery_code' => 'Please verify your recovery code first.']);
        }

        $admin = User::where('role', 'admin')->first();
        $email = $request->session()->get('admin_recovery_email');
        if (! $admin || ! $email) {
            return redirect()->route('admin.recovery.show');
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must be exactly 6 digits.',
        ]);

        // Strictly verify using dedicated admin_recovery OTP purpose
        $result = $this->otpService->verify($admin, $validated['otp'], self::OTP_PURPOSE);
        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['message']]);
        }

        // Mark OTP factor verified
        $request->session()->put('admin_recovery_email_verified', true);

        return redirect()->route('admin.recovery.show')
            ->with('status', 'New email verified! Please set a new secure password to finalize recovery.');
    }

    /**
     * Resend recovery email OTP using dedicated purpose admin_recovery.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $admin = User::where('role', 'admin')->first();
        $email = $request->session()->get('admin_recovery_email');
        if (! $admin || ! $email) {
            return redirect()->route('admin.recovery.show');
        }

        $cooldown = $this->otpService->canResend($admin, self::OTP_PURPOSE);
        if (! $cooldown['allowed']) {
            return back()->withErrors(['resend' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting a new code."]);
        }

        $otp = $this->otpService->generate($admin, self::OTP_PURPOSE);
        $userForDispatch = clone $admin;
        $userForDispatch->email = $email;
        $sent = $this->otpService->sendOtp($userForDispatch, $otp, 10, self::OTP_PURPOSE);

        if (! $sent) {
            return back()->withErrors(['resend' => 'Failed to deliver verification code to your email. Please try again.']);
        }

        return back()->with('status', 'A new verification code has been sent.');
    }

    /**
     * Factor 3: Set new password and finalize emergency recovery.
     * Requires ALL factors:
     * - Factor 1: Valid recovery code authorized in session
     * - Factor 2: New operational email verified via dedicated admin_recovery OTP
     * - Factor 3: Valid new password satisfying policy and differing from current password
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        // Enforce Factor 1 and Factor 2 presence
        $codeVerified = $request->session()->get('admin_recovery_code_verified', false);
        $emailVerified = $request->session()->get('admin_recovery_email_verified', false);
        $codeId = $request->session()->get('admin_recovery_authorized_code_id') ?? $request->session()->get('admin_recovery_code_id');
        $newEmail = $request->session()->get('admin_recovery_email');

        if (! $codeVerified || ! $emailVerified || ! $codeId || ! $newEmail) {
            return redirect()->route('admin.recovery.show')
                ->withErrors(['recovery_code' => 'All recovery factors (recovery code and verified new email) must be completed before resetting password.']);
        }

        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            return redirect()->route('login');
        }

        $recoveryRecord = AdminRecoveryCode::where('id', $codeId)
            ->where('user_id', $admin->id)
            ->where('is_used', false)
            ->first();

        if (! $recoveryRecord) {
            return redirect()->route('admin.recovery.show')
                ->withErrors(['recovery_code' => 'The recovery code has already been used or is no longer valid.']);
        }

        // Validate Factor 3 (New Password)
        $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'password.required' => 'A new password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters with letters and numbers.',
        ]);

        $newPassword = $request->input('password');

        if (Hash::check($newPassword, $admin->password)) {
            return back()->withErrors([
                'password' => 'The new password cannot be the same as your current password.',
            ]);
        }

        // 1. Consume the old recovery code
        $recoveryRecord->markUsed();
        $this->recordSecurityEvent($admin, 'admin_recovery_code_used', "Emergency recovery code #{$recoveryRecord->id} consumed.");

        // 2. Update Admin email and password
        $oldEmail = $admin->email;
        $admin->email = $newEmail;
        $admin->email_verified_at = now();
        $admin->password = Hash::make($newPassword);
        $admin->is_bootstrap = false;
        $admin->save();

        // 3. Generate replacement recovery code and ensure ONLY ONE unused recovery code exists
        $replacementCode = AdminBootstrapController::generateRecoveryCode();
        AdminRecoveryCode::issueForUser($admin->id, $replacementCode['hash']);
        $this->recordSecurityEvent($admin, 'admin_recovery_code_regenerated', 'Replacement emergency recovery code generated.');

        // 4. Invalidate all active sessions and trusted devices for this admin in database
        try {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
            $this->recordSecurityEvent($admin, 'admin_sessions_revoked', 'All existing administrative sessions were terminated after emergency recovery.');

            DB::table('trusted_devices')->where('user_id', $admin->id)->update(['revoked_at' => now()]);
            $this->recordSecurityEvent($admin, 'admin_trusted_devices_revoked', 'All existing trusted devices were revoked after emergency recovery.');
        } catch (\Throwable $e) {
        }

        $this->recordSecurityEvent($admin, 'admin_recovery_completed', "Emergency recovery completed. Email changed from {$oldEmail} to {$admin->email}.");

        // Clear session recovery flags and flash replacement code
        $request->session()->forget([
            'admin_recovery_code_verified',
            'admin_recovery_authorized_code_id',
            'admin_recovery_code_id',
            'admin_recovery_email',
            'admin_recovery_email_verified',
        ]);
        $request->session()->flash('admin_replacement_recovery_code', $replacementCode['formatted']);

        return redirect()->route('admin.recovery.show');
    }

    /**
     * Complete recovery flow and redirect to login.
     */
    public function finishRecovery(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_replacement_recovery_code');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Emergency account recovery complete. Please log in with your new credentials.');
    }

    /**
     * Record sensitive security events in AuditLog.
     */
    protected function recordSecurityEvent(User $user, string $action, ?string $details = null): void
    {
        try {
            AuditLog::record(
                $user->id,
                $action,
                $details,
                'admin_security',
                null,
                request()->ip(),
                request()->userAgent()
            );
        } catch (\Throwable $e) {
            \Log::warning("Failed to record security event: {$action} - " . $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminPasswordResetController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Show the admin forgot password form.
     */
    public function showForgot(): View
    {
        return view('admin.password-reset-forgot');
    }

    /**
     * Send password_reset OTP if the user is an admin.
     * Generic response prevents account enumeration.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ]);

        $email = $request->input('email');
        $admin = User::where('email', $email)->where('role', 'admin')->first();

        if ($admin) {
            $cooldown = $this->otpService->canResend($admin, 'password_reset');
            if ($cooldown['allowed']) {
                $otp = $this->otpService->generate($admin, 'password_reset');
                $sent = $this->otpService->sendOtp($admin, $otp, 10, 'password_reset');
                if (! $sent) {
                    AuditLog::record(
                        $admin->id,
                        'admin_password_reset_delivery_failed',
                        'Password reset OTP delivery failed for admin account',
                        'admin_security',
                        ['email_masked' => $this->maskEmail($email)],
                        $request->ip(),
                        $request->userAgent()
                    );
                }
            }

            AuditLog::record(
                $admin->id,
                'admin_password_reset_requested',
                'Password reset OTP requested for admin account',
                'admin_security',
                ['email_masked' => $this->maskEmail($email)],
                $request->ip(),
                $request->userAgent()
            );
        }

        $request->session()->put('admin_password_reset_email', $email);

        return redirect()->route('admin.password.otp.show')
            ->with('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');
    }

    /**
     * Show the OTP input form for admin password reset.
     */
    public function showOtpForm(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('admin_password_reset_email');
        if (! $email) {
            return redirect()->route('admin.password.forgot');
        }

        $admin = User::where('email', $email)->where('role', 'admin')->first();
        $cooldownSeconds = 0;
        if ($admin) {
            $cooldown = $this->otpService->canResend($admin, 'password_reset');
            $cooldownSeconds = $cooldown['seconds_remaining'] ?? 0;
        }

        return view('admin.password-reset-otp', [
            'email' => $email,
            'maskedEmail' => $this->maskEmail($email),
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    /**
     * Verify the 6-digit password_reset OTP.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $email = $request->session()->get('admin_password_reset_email');
        if (! $email) {
            return redirect()->route('admin.password.forgot');
        }

        $admin = User::where('email', $email)->where('role', 'admin')->first();
        if (! $admin) {
            return back()->withErrors(['otp' => 'Invalid or expired verification code.']);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must be exactly 6 digits.',
        ]);

        $result = $this->otpService->verify($admin, $validated['otp'], 'password_reset');

        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['message']]);
        }

        $request->session()->put('admin_password_reset_verified', true);

        return redirect()->route('admin.password.new.show');
    }

    /**
     * Resend password_reset OTP.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $email = $request->session()->get('admin_password_reset_email');
        if (! $email) {
            return redirect()->route('forgot-password');
        }

        $admin = User::where('email', $email)->where('role', 'admin')->first();
        if (! $admin) {
            return back()->with('status', 'A new password reset code has been sent to your email.');
        }

        $cooldown = $this->otpService->canResend($admin, 'password_reset');
        if (! $cooldown['allowed']) {
            return back()->withErrors([
                'resend' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting a new code.",
            ]);
        }

        $otp = $this->otpService->generate($admin, 'password_reset');
        $sent = $this->otpService->sendOtp($admin, $otp, 10, 'password_reset');

        if (! $sent) {
            AuditLog::record(
                $admin->id,
                'admin_password_reset_delivery_failed',
                'Password reset OTP delivery failed for admin account on resend',
                'admin_security',
                ['email_masked' => $this->maskEmail($email)],
                $request->ip(),
                $request->userAgent()
            );
        }

        return back()->with('status', 'A new password reset code has been sent to your email.');
    }

    /**
     * Show the new password form.
     */
    public function showNewPasswordForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('admin_password_reset_verified', false)) {
            return redirect()->route('forgot-password');
        }

        return view('admin.password-reset-new');
    }

    /**
     * Reset the admin's password, invalidate sessions, and require fresh login.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        if (! $request->session()->get('admin_password_reset_verified', false)) {
            return redirect()->route('forgot-password');
        }

        $email = $request->session()->get('admin_password_reset_email');
        $admin = User::where('email', $email)->where('role', 'admin')->first();

        if (! $admin) {
            return redirect()->route('forgot-password');
        }

        $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'password.required' => 'New password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters with letters and numbers.',
        ]);

        $newPassword = $request->input('password');

        if (Hash::check($newPassword, $admin->password)) {
            return back()->withErrors(['password' => 'The new password cannot be the same as your current password.']);
        }

        // Update password
        $admin->password = Hash::make($newPassword);
        $admin->save();

        // Terminate existing sessions in database
        try {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        } catch (\Throwable $e) {
            // In case session driver is file or array
        }

        $this->recordSecurityEvent($admin, 'admin_password_reset_completed', 'Admin password was reset via OTP.');
        $this->recordSecurityEvent($admin, 'admin_sessions_revoked', 'All sessions revoked after admin password reset.');

        $request->session()->forget(['admin_password_reset_email', 'admin_password_reset_verified']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Password reset successfully! Please log in with your new password.');
    }

    /**
     * Record sensitive security events in AuditLog.
     */
    protected function recordSecurityEvent(User $user, string $action, ?string $details = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'module' => 'admin_security',
                'details' => $details,
                'entity_type' => User::class,
                'entity_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            \Log::warning("Failed to record security event: {$action} - " . $e->getMessage());
        }
    }

    /**
     * Mask an email address for display.
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', min(5, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}

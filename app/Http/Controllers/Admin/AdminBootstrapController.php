<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecoveryCode;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminBootstrapController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Display the initial bootstrap setup wizard.
     */
    public function showSetup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // Determine current step based on session state, verification, and code acknowledgment
        $step = 'email';
        $pendingEmail = $request->session()->get('admin_bootstrap_pending_email') ?? $request->session()->get('admin_bootstrap_email');
        $emailVerified = $request->session()->get('admin_bootstrap_email_verified', false) || $user->hasVerifiedEmail();
        $hasRecoveryFlash = $request->session()->has('admin_recovery_code') || $request->session()->has('admin_recovery_code_plaintext');
        $hasUnusedCodeInDb = AdminRecoveryCode::where('user_id', $user->id)->where('is_used', false)->exists();

        if ($hasRecoveryFlash) {
            $step = 'recovery_code';
        } elseif ($emailVerified && $hasUnusedCodeInDb) {
            // Password was replaced and code was issued, but session flash closed before acknowledgment
            $step = 'reissue';
        } elseif ($emailVerified) {
            $step = 'password';
        } elseif ($pendingEmail) {
            $step = 'otp';
        }

        $recoveryCode = $request->session()->get('admin_recovery_code') ?? $request->session()->get('admin_recovery_code_plaintext');
        $cooldown = $this->otpService->canResend($user, 'admin_bootstrap');

        return view('admin.setup.index', [
            'step' => $step,
            'email' => $pendingEmail ?? $user->email,
            'pendingEmail' => $pendingEmail,
            'maskedEmail' => $pendingEmail ? $this->maskEmail($pendingEmail) : null,
            'recoveryCode' => $recoveryCode,
            'cooldownSeconds' => $cooldown['seconds_remaining'] ?? 0,
        ]);
    }

    /**
     * Submit an active email address and dispatch bootstrap OTP.
     */
    public function submitEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
        ], [
            'email.required' => 'An active email address is required for administrator security.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already in use by another account.',
        ]);

        $newEmail = strtolower(trim($validated['email']));

        // Temporarily store the email on user or in session for OTP generation
        $user->email = $newEmail;
        $user->save();

        $request->session()->put('admin_bootstrap_pending_email', $newEmail);
        $request->session()->put('admin_bootstrap_email', $newEmail);
        $request->session()->put('admin_bootstrap_email_verified', false);

        $otp = $this->otpService->generate($user, 'admin_bootstrap');
        $sent = $this->otpService->sendOtp($user, $otp, 10, 'admin_bootstrap');

        $this->recordSecurityEvent($user, 'admin_bootstrap_started', "Active email submitted: {$newEmail}");

        if (! $sent) {
            return redirect()->route('admin.setup')
                ->withErrors(['otp' => 'Failed to deliver verification code to your active email. Please request a new code.']);
        }

        return redirect()->route('admin.setup')
            ->with('status', 'A 6-digit verification code has been sent to your active email.');
    }

    /**
     * Verify the active email OTP.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must be exactly 6 digits.',
        ]);

        $result = $this->otpService->verify($user, $validated['otp'], 'admin_bootstrap');

        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['message']]);
        }

        $pendingEmail = $request->session()->get('admin_bootstrap_pending_email') ?? $request->session()->get('admin_bootstrap_email') ?? $user->email;
        $user->email = $pendingEmail;
        $user->email_verified_at = now();
        $user->save();

        $request->session()->put('admin_bootstrap_email_verified', true);

        $this->recordSecurityEvent($user, 'admin_bootstrap_email_verified', "Email {$pendingEmail} successfully verified via OTP.");

        return redirect()->route('admin.setup')
            ->with('status', 'Email verified successfully. Please set a new secure administrative password.');
    }

    /**
     * Resend the bootstrap OTP to the active email.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $cooldown = $this->otpService->canResend($user, 'admin_bootstrap');

        if (! $cooldown['allowed']) {
            return back()->withErrors([
                'resend' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting a new code.",
            ]);
        }

        $otp = $this->otpService->generate($user, 'admin_bootstrap');
        $sent = $this->otpService->sendOtp($user, $otp, 10, 'admin_bootstrap');

        if (! $sent) {
            return back()->withErrors([
                'resend' => 'Failed to deliver verification code to your email. Please try again.',
            ]);
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    /**
     * Perform mandatory password replacement and generate emergency recovery code.
     * Crucial: setup is NOT yet complete until the Admin explicitly confirms saving the code.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $request->session()->get('admin_bootstrap_email_verified', false) && ! $user->hasVerifiedEmail()) {
            return redirect()->route('admin.setup')
                ->with('error', 'You must verify your active email address before changing the password.');
        }

        $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'password.required' => 'A new administrative password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters and contain letters and numbers.',
        ]);

        $newPassword = $request->input('password');

        // Disallow reusing the current/initial bootstrap password
        if (Hash::check($newPassword, $user->password)) {
            return back()->withErrors([
                'password' => 'The new password cannot be the same as your initial temporary password.',
            ]);
        }

        // Update password
        $user->password = Hash::make($newPassword);
        $user->save();

        // Generate emergency recovery code and issue it (invalidating all prior unused codes)
        $codeData = self::generateRecoveryCode();
        AdminRecoveryCode::issueForUser($user->id, $codeData['hash']);

        // Put plaintext recovery code in session flash for single display
        $request->session()->flash('admin_recovery_code_plaintext', $codeData['formatted']);
        $request->session()->flash('admin_recovery_code', $codeData['formatted']);
        $request->session()->put('admin_recovery_code_generated', true);

        $this->recordSecurityEvent($user, 'admin_recovery_code_regenerated', 'Initial emergency recovery code generated.');

        return redirect()->route('admin.setup')
            ->with('status', 'Password updated! Please save your emergency recovery code below to complete setup.');
    }

    /**
     * Explicitly acknowledge that the emergency recovery code has been saved.
     * Completes Admin bootstrap setup and transitions to normal Admin access.
     */
    public function acknowledgeRecoveryCode(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $hasUnusedCode = AdminRecoveryCode::where('user_id', $user->id)
            ->where('is_used', false)
            ->exists();

        if (! $hasUnusedCode) {
            return redirect()->route('admin.setup')
                ->with('error', 'A recovery code has not been generated. Please generate one to proceed.');
        }

        // Complete bootstrap setup
        $user->is_bootstrap = false;
        $user->save();

        // Forget all bootstrap-specific session state
        $request->session()->forget([
            'admin_bootstrap_pending_email',
            'admin_bootstrap_email',
            'admin_bootstrap_email_verified',
            'admin_recovery_code_generated',
            'admin_recovery_code',
            'admin_recovery_code_plaintext',
        ]);

        $request->session()->regenerate();

        $this->recordSecurityEvent($user, 'admin_bootstrap_completed', 'Initial Admin bootstrap setup acknowledged and completed.');

        return redirect()->route('admin.dashboard')
            ->with('success', 'Administrator setup completed! Welcome to Raflora Enterprises.');
    }

    /**
     * Reissue a recovery code if the browser session closed before the initial code was acknowledged.
     */
    public function reissueRecoveryCode(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isBootstrapAdmin() || ! $user->hasVerifiedEmail()) {
            return redirect()->route('admin.setup');
        }

        $codeData = self::generateRecoveryCode();
        AdminRecoveryCode::issueForUser($user->id, $codeData['hash']);

        $request->session()->flash('admin_recovery_code_plaintext', $codeData['formatted']);
        $request->session()->flash('admin_recovery_code', $codeData['formatted']);
        $request->session()->put('admin_recovery_code_generated', true);

        $this->recordSecurityEvent($user, 'admin_recovery_code_regenerated', 'Emergency recovery code reissued prior to setup confirmation.');

        return redirect()->route('admin.setup')
            ->with('status', 'A new emergency recovery code has been generated. Please save it immediately to complete setup.');
    }

    /**
     * Alias for showSetup.
     */
    public function show(Request $request): View|RedirectResponse
    {
        return $this->showSetup($request);
    }

    /**
     * Generate a 16-character emergency recovery code formatted in 4 blocks (XXXX-XXXX-XXXX-XXXX).
     * 16 uppercase alphanumeric characters uniformly chosen from 36 characters (0-9, A-Z)
     * provide approximately 83 bits of cryptographic entropy (16 * log2(36) ≈ 82.74 bits).
     */
    public static function generateRecoveryCode(): array
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $raw = '';
        for ($i = 0; $i < 16; $i++) {
            $raw .= $chars[random_int(0, 35)];
        }

        $formatted = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4) . '-' . substr($raw, 12, 4);
        $clean = str_replace('-', '', $formatted);
        $hash = hash('sha256', $clean);

        return [
            'formatted' => $formatted,
            'clean' => $clean,
            'hash' => $hash,
        ];
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

    /**
     * Mask an email address for display (e.g. ad***@raflora.com).
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(1, $len - 2));
        }

        return $maskedName . '@' . $domain;
    }
}

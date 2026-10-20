<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminEmailChangeController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Show the email change interface.
     */
    public function show(Request $request): View
    {
        return $this->showForm($request);
    }

    public function showForm(Request $request): View
    {
        $pendingEmail = $request->session()->get('admin_pending_email_change');
        $cooldown = $this->otpService->canResend($request->user(), 'admin_email_change');

        return view('admin.email-change', [
            'admin' => $request->user(),
            'pendingEmail' => $pendingEmail,
            'maskedEmail' => $pendingEmail ? $this->maskEmail($pendingEmail) : null,
            'cooldownSeconds' => $cooldown['seconds_remaining'] ?? 0,
        ]);
    }

    /**
     * Verify password, validate new email, and send OTP to the new email.
     */
    public function submitNewEmail(Request $request): RedirectResponse
    {
        return $this->sendOtp($request);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $emailField = $request->has('email') ? 'email' : 'new_email';

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            $emailField => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ], [
            'current_password.required' => 'Current password is required to request an email change.',
            $emailField . '.required' => 'A valid new email address is required.',
            $emailField . '.unique' => 'This email address is already registered to another account.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The provided password does not match your current password.'])->withInput();
        }

        $newEmail = strtolower(trim($validated[$emailField]));

        if ($newEmail === strtolower($user->email)) {
            return back()->withErrors(['new_email' => 'The new email address must be different from your current email.'])->withInput();
        }

        // Store pending email in session
        $request->session()->put('admin_pending_email_change', $newEmail);
        $request->session()->put('admin_email_change_pending', $newEmail);

        // Generate and send OTP for purpose admin_email_change
        $otp = $this->otpService->generate($user, 'admin_email_change');
        
        // Dispatch to the proposed new email address
        $userForDispatch = clone $user;
        $userForDispatch->email = $newEmail;
        $sent = $this->otpService->sendOtp($userForDispatch, $otp, 10, 'admin_email_change');

        $this->recordSecurityEvent($user, 'admin_email_change_requested', "Email change requested from {$user->email} to {$newEmail}.");

        if (! $sent) {
            return redirect()->route('admin.email-change.show')
                ->withErrors(['otp' => 'Failed to deliver verification code to your new email. Please try again.']);
        }

        return redirect()->route('admin.email-change.show')
            ->with('status', 'A 6-digit verification code has been sent to your new email address.');
    }

    /**
     * Verify the OTP and update the administrator's email.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pendingEmail = $request->session()->get('admin_pending_email_change') ?? $request->session()->get('admin_email_change_pending');

        if (! $pendingEmail) {
            return redirect()->route('admin.email-change.show')
                ->withErrors(['error' => 'No pending email change found. Please submit your request again.']);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must be exactly 6 digits.',
        ]);

        $result = $this->otpService->verify($user, $validated['otp'], 'admin_email_change');

        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['message']]);
        }

        $oldEmail = $user->email;
        $user->email = $pendingEmail;
        $user->email_verified_at = now();
        $user->save();

        $request->session()->forget(['admin_pending_email_change', 'admin_email_change_pending']);

        $this->recordSecurityEvent($user, 'admin_email_changed', "Admin email changed from {$oldEmail} to {$user->email}.");

        return redirect()->route('admin.settings')
            ->with('success', 'Email address successfully updated and verified.');
    }

    /**
     * Resend verification code to the proposed new email.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pendingEmail = $request->session()->get('admin_pending_email_change');

        if (! $pendingEmail) {
            return redirect()->route('admin.email-change.show');
        }

        $cooldown = $this->otpService->canResend($user, 'admin_email_change');
        if (! $cooldown['allowed']) {
            return back()->withErrors([
                'resend' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting a new code.",
            ]);
        }

        $otp = $this->otpService->generate($user, 'admin_email_change');
        $userForDispatch = clone $user;
        $userForDispatch->email = $pendingEmail;
        $sent = $this->otpService->sendOtp($userForDispatch, $otp, 10, 'admin_email_change');

        if (! $sent) {
            return back()->withErrors([
                'resend' => 'Failed to deliver verification code to your new email. Please try again.',
            ]);
        }

        return back()->with('status', 'A new verification code has been sent to your new email.');
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

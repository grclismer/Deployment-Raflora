<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Display the email verification prompt.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return $this->redirectBasedOnRole($user);
        }

        $maskedEmail = $this->maskEmail($user->email ?? '');

        // If user has no active OTP yet, generate one
        $cooldown = $this->otpService->canResend($user, 'email_verification');

        return view('auth.verify-email', [
            'email' => $maskedEmail,
            'cooldownSeconds' => $cooldown['seconds_remaining'] ?? 0,
        ]);
    }

    /**
     * Handle the OTP verification submission.
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return $this->redirectBasedOnRole($user);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must be exactly 6 digits.',
        ]);

        $result = $this->otpService->verify($user, $validated['otp'], 'email_verification');

        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['message']]);
        }

        // Calling workflow explicitly transitions the user account verification state
        $user->markEmailAsVerified();

        return redirect()->intended(route('client.dashboard'))
            ->with('status', 'Email verified successfully! Welcome to Raflora.');
    }

    /**
     * Resend a verification OTP.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return $this->redirectBasedOnRole($user);
        }

        $cooldown = $this->otpService->canResend($user, 'email_verification');
        if (! $cooldown['allowed']) {
            return back()->withErrors([
                'resend' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting a new code.",
            ]);
        }

        $otp = $this->otpService->generate($user, 'email_verification');
        $sent = $this->otpService->sendOtp($user, $otp, 10, 'email_verification');

        if (! $sent) {
            return back()->withErrors([
                'resend' => 'Failed to deliver verification code to your email. Please try again in a few moments.',
            ]);
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    /**
     * Mask an email address for privacy (e.g., j***n@example.com).
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

    /**
     * Redirect to the appropriate dashboard based on user role.
     */
    protected function redirectBasedOnRole($user): RedirectResponse
    {
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'staff') {
            return redirect()->route('staff.dashboard');
        }

        return redirect()->route('client.dashboard');
    }
}

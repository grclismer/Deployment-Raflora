<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Services\OtpService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Services\PhpMailerService;

class AuthController extends Controller
{
    /**
     * Show the login page.
     */
    public function showLogin(Request $request): View
    {
        $guestToken = $request->query('guest_token');
        if ($guestToken) {
            $request->session()->put('url.intended', route('client.claim-guest-booking.show', ['token' => $guestToken]));
        }

        return view('auth.login', [
            'guestToken' => $guestToken,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Handle login request with email and password.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $credentials = [
            'password' => $validated['password'],
        ];

        if ($guestToken = $request->input('guest_token')) {
            $request->session()->put('url.intended', route('client.claim-guest-booking.show', ['token' => $guestToken]));
        }

        // ----------------------------------------------------------------
        // Find the candidate user by email or username first (without Auth::attempt)
        // so we can apply role-specific logic before creating a session.
        // ----------------------------------------------------------------
        $candidate = User::where('email', $validated['email'])
            ->orWhere('username', $validated['email'])
            ->first();

        // -----------------------------------------------------------------
        // Admin and Staff: apply the device-trust / OTP flow.
        //
        // SECURITY:
        // - We MUST verify the password manually before any trusted-device
        //   or OTP logic to prevent bypass via crafted cookies.
        // - Laravel's remember-me mechanism is SUPPRESSED for Admin/Staff.
        //   The approved persistent-authentication path is the trusted-device
        //   cookie, chosen on the device-verification screen, not here.
        // -----------------------------------------------------------------
        if ($candidate && in_array($candidate->role, ['admin', 'staff'], true)) {
            // Verify password without logging in (no Auth::attempt yet).
            if (! Hash::check($validated['password'], $candidate->password)) {
                // Generic failure — same message as a missing account.
                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'Email/username or password is incorrect.');
            }

            // Password is correct. Proceed through the security chain.
            return $this->handleAdminStaffLogin($candidate, $request);
        }

        // -----------------------------------------------------------------
        // Client login: unchanged behavior.
        // -----------------------------------------------------------------
        $credentials['email'] = $candidate ? $candidate->email : $validated['email'];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // If client email is not yet verified, redirect to verification gate.
            if ($user->role === 'client' && ! $user->hasVerifiedEmail()) {
                $otpService = app(OtpService::class);
                $cooldown = $otpService->canResend($user, 'email_verification');
                $sent = true;
                if ($cooldown['allowed']) {
                    $otp = $otpService->generate($user, 'email_verification');
                    $sent = $otpService->sendOtp($user, $otp, 10, 'email_verification');
                }

                if (! $sent) {
                    return redirect()->route('verification.notice')
                        ->with('info', 'Please verify your email address to access your client dashboard.')
                        ->withErrors(['otp' => 'We were unable to deliver your verification code. Please click resend to try again.']);
                }

                return redirect()->route('verification.notice')
                    ->with('info', 'Please verify your email address to access your client dashboard.');
            }

            $redirectRoute = match ($user->role) {
                'admin'  => route('admin.dashboard'),
                'staff'  => route('staff.dashboard'),
                default  => route('client.dashboard'),
            };

            return redirect()->to($this->roleAwareIntendedUrl($request, $user, $redirectRoute))
                ->with('success', 'Welcome back, ' . ($user->first_name ?? $user->name) . '!');
        }

        return back()
            ->withInput($request->only('email'))
            ->with('error', 'Email/username or password is incorrect.');
    }

    /**
     * Handle the Admin/Staff login after the password has been verified.
     *
     * SECURITY ORDER:
     *  1. Bootstrap Admin → redirect to setup without any OTP/device check.
     *  2. Staff without verified email → redirect to email verification.
     *  3. Check trusted-device cookie.
     *     a. Valid trusted device → authenticate immediately, regenerate session.
     *     b. No/invalid trusted device → generate device OTP, store pending state,
     *        redirect to device-verification page (Auth::login NOT called yet).
     *
     * The $user parameter has already had their password verified by the caller.
     * Auth::attempt() and Auth::login() are NEVER called before this method
     * completes the appropriate checks.
     */
    private function handleAdminStaffLogin(User $user, Request $request): RedirectResponse
    {
        // ── 1. Bootstrap Admin must complete setup first ────────────────────────
        // Trusted-device and OTP are irrelevant here; redirect immediately.
        if ($user->isBootstrapAdmin()) {
            // Authenticate so that the session middleware can recognise the user.
            Auth::login($user, false);   // No remember-me for bootstrap
            $request->session()->regenerate();

            return redirect()->route('admin.setup')
                ->with('info', 'Welcome to Raflora. Please complete your administrator account setup.');
        }

        // ── 2. Unverified Staff must verify email before normal access ──────────
        // Device trust cannot bypass email verification.
        if ($user->role === 'staff' && ! $user->hasVerifiedEmail()) {
            // Log the user in temporarily so EmailVerificationController routes work.
            Auth::login($user, false);
            $request->session()->regenerate();

            $otpService = app(OtpService::class);
            $cooldown   = $otpService->canResend($user, 'email_verification');
            $sent = true;
            if ($cooldown['allowed']) {
                $otp = $otpService->generate($user, 'email_verification');
                $sent = $otpService->sendOtp($user, $otp, 10, 'email_verification');
            }

            if (! $sent) {
                return redirect()->route('verification.notice')
                    ->with('info', 'Please verify your email address to access your staff dashboard.')
                    ->withErrors(['otp' => 'We were unable to deliver your verification code. Please click resend to try again.']);
            }

            return redirect()->route('verification.notice')
                ->with('info', 'Please verify your email address to access your staff dashboard.');
        }

        // ── 3. Trusted-device check ─────────────────────────────────────────────
        $deviceService = app(TrustedDeviceService::class);
        $rawToken      = $deviceService->getTokenFromCookie();

        if ($rawToken !== null) {
            $trustedDevice = $deviceService->findUsableForUser($user, $rawToken);

            if ($trustedDevice !== null) {
                // Valid trusted device — authenticate without OTP.
                $trustedDevice->recordUsage($request->ip()); // updates last_used_at only

                Auth::login($user, false); // Never pass remember=true for Admin/Staff
                $request->session()->regenerate();

                $this->recordLoginSecurityEvent(
                    $user,
                    'trusted_device_login',
                    'Admin/Staff authenticated via trusted device (OTP skipped).',
                    $request
                );

                return redirect()->to($this->roleAwareIntendedUrl(
                    $request,
                    $user,
                    match ($user->role) {
                        'admin' => route('admin.dashboard'),
                        'staff' => route('staff.dashboard'),
                        default => route('client.dashboard'),
                    }
                ))->with('success', 'Welcome back, ' . ($user->first_name ?? $user->name) . '!');
            }

            // Token present but invalid/expired/revoked — clear the stale cookie.
            $deviceService->clearTrustedDeviceCookie();
        }

        // ── 4. No valid trusted device — issue OTP challenge ────────────────────
        // Auth::login() is NOT called here.
        $otpService = app(OtpService::class);
        $cooldown   = $otpService->canResend($user, 'device_verification');
        $sent = true;
        if ($cooldown['allowed']) {
            $otp = $otpService->generate($user, 'device_verification');
            $sent = $otpService->sendOtp($user, $otp, 10, 'device_verification');
        }

        // Store pending state on server side only. Do NOT store the password.
        // We pass the remember-me intent so it survives the OTP transition.
        $deviceVerificationController = app(DeviceVerificationController::class);
        $deviceVerificationController->storePendingState($request, $user->id, $request->boolean('remember'));

        $this->recordLoginSecurityEvent(
            $user,
            'device_otp_challenge_created',
            'Admin/Staff device OTP challenge issued (untrusted device).',
            $request
        );

        if (! $sent) {
            return redirect()->route('device.verify.show')
                ->withErrors(['otp' => 'We were unable to deliver your verification code to your email. Please click resend to try again.']);
        }

        return redirect()->route('device.verify.show')
            ->with('status', 'A 6-digit verification code has been sent to your registered email.');
    }

    /**
     * Record a security audit event for login-related actions.
     */
    private function recordLoginSecurityEvent(
        User    $user,
        string  $action,
        ?string $details,
        Request $request
    ): void {
        try {
            AuditLog::record(
                $user->id,
                $action,
                $details,
                'device_security',
                null,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $e) {
            \Log::warning("Failed to record login security event: {$action} - " . $e->getMessage());
        }
    }

    private function roleAwareIntendedUrl(Request $request, User $user, string $fallback): string
    {
        $intended = $request->session()->pull('url.intended');

        if (!is_string($intended) || $intended === '') {
            return $fallback;
        }

        $parsed = parse_url($intended);
        if ($parsed === false || (isset($parsed['host']) && $parsed['host'] !== $request->getHost())) {
            return $fallback;
        }

        $path = $parsed['path'] ?? '';
        $allowedPrefixes = match ($user->role) {
            'admin' => ['/admin/', '/staff/'],
            'staff' => ['/staff/'],
            default => ['/client/', '/claim-booking/'],
        };

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $path . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
            }
        }

        return $fallback;
    }

    /**
     * Show the registration page.
     */
    public function showRegister(Request $request): View
    {
        $nameParts = explode(' ', $request->query('name', ''));
        $firstName = $request->query('first_name') ?? $nameParts[0] ?? null;
        $lastName = $request->query('last_name') ?? (count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null);

        return view('auth.register', [
            'email' => $request->query('email'),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'guestToken' => $request->query('guest_token'),
        ]);
    }

    /**
     * Handle registration request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        // Get validated data
        $validated = $request->validated();
        $guestToken = $request->input('guest_token');

        // Create new user
        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'mobile_number' => $validated['mobile_number'] ?? null,
            'role' => 'client', // New registrations default to client role
        ]);

        Client::firstOrCreate(
            ['email' => $user->email],
            [
                'full_name' => $user->name,
                'phone' => $user->mobile_number,
            ]
        );

        if ($guestToken) {
            // Do not auto-claim. Set the intended URL to the claim page, which they will hit after email verification.
            $request->session()->put('url.intended', route('client.claim-guest-booking.show', ['token' => $guestToken]));
        }

        // Automatically log in the newly registered user
        Auth::login($user);

        // Regenerate session for security
        $request->session()->regenerate();

        // Generate and dispatch verification OTP
        $otpService = app(\App\Services\OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');
        $sent = $otpService->sendOtp($user, $otp, 10, 'email_verification');

        if (! $sent) {
            return redirect()->route('verification.notice')
                ->with('warning', 'Account created! However, we could not send your verification code. Please request a new code.')
                ->withErrors(['otp' => 'We were unable to deliver your verification code. Please click resend to try again.']);
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Account created! Please enter the 6-digit verification code sent to your email.');
    }

    /**
     * Show the forgot password page.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send the password reset link to the provided email address.
     */
    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ]);

        $user = User::where('email', $request->input('email'))->first();
        if (! $user) {
            // Generic message to prevent account enumeration
            return back()->with('status', 'If your email address is registered, you will receive a password reset link shortly.');
        }

        // Admin password recovery uses dedicated OTP flow
        if ($user->role === 'admin') {
            $otpService = app(\App\Services\OtpService::class);
            $cooldown = $otpService->canResend($user, 'password_reset');
            if ($cooldown['allowed']) {
                $otp = $otpService->generate($user, 'password_reset');
                $sent = $otpService->sendOtp($user, $otp, 10, 'password_reset');
                if (! $sent) {
                    \App\Models\AuditLog::record(
                        $user->id,
                        'admin_password_reset_delivery_failed',
                        'Password reset OTP delivery failed for admin account',
                        'admin_security',
                        ['email_masked' => substr($user->email, 0, 2) . '***@' . explode('@', $user->email)[1]],
                        $request->ip(),
                        $request->userAgent()
                    );
                }
            }

            \App\Models\AuditLog::record(
                $user->id,
                'admin_password_reset_requested',
                'Password reset OTP requested for admin account',
                'admin_security',
                ['email_masked' => substr($user->email, 0, 2) . '***@' . explode('@', $user->email)[1]],
                $request->ip(),
                $request->userAgent()
            );

            $request->session()->put('admin_password_reset_email', $user->email);

            return redirect()->route('admin.password.otp.show')
                ->with('status', 'If your email is registered as an administrator, a 6-digit verification code has been sent.');
        }

        // If configured to use PHPMailer, create the token and send via PHPMailer
        if (config('mail.use_phpmailer', false)) {
            // Create and store the password reset token manually so we can send a custom HTML email.
            $token = Password::broker()->createToken($user);
            $resetUrl = url('/reset-password/' . $token . '?email=' . urlencode($user->email));

            // Send polished HTML email via PHPMailer
            try {
                $mailer = app(PhpMailerService::class);
                $sent = $mailer->sendPasswordReset($user->email, $user->first_name ?? $user->name, $resetUrl);
                if (! $sent) {
                    \Log::warning('PHPMailer reported failure sending reset email to ' . $user->email . ', invoking Laravel Mail fallback');
                    Password::sendResetLink($request->only('email'));
                    return back()->with('status', 'If your email address is registered, you will receive a password reset link shortly.');
                }
            } catch (\Throwable $e) {
                \Log::error('Error sending PHPMailer reset: ' . $e->getMessage() . ', invoking Laravel Mail fallback');
                Password::sendResetLink($request->only('email'));
                return back()->with('status', 'If your email address is registered, you will receive a password reset link shortly.');
            }

            return back()->with('status', 'If your email address is registered, you will receive a password reset link shortly.');
        }

        // Primary delivery (when PHPMailer is disabled) via Laravel's broker
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If your email address is registered, you will receive a password reset link shortly.');
    }

    /**
     * Show the password reset page.
     */
    public function showResetPasswordForm(Request $request, ?string $token = null): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No account was found with that email address.',
            'password.required' => 'New password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password has been reset. You can now log in.')
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * Handle logout request.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();

        // Invalidate session
        request()->session()->invalidate();

        // Regenerate CSRF token
        request()->session()->regenerateToken();

        return redirect('/')->with('success', 'Logged out successfully.');
    }
}

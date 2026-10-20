<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\OtpService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Handles the device-verification second-factor challenge for Admin and Staff.
 *
 * This controller is reached ONLY after a user has supplied a correct password
 * in AuthController::login(). It manages the OTP challenge, optional trusted-device
 * creation, and final authenticated session establishment.
 *
 * SECURITY INVARIANTS (must be maintained in every method):
 * - Auth::login() is NEVER called before OTP is verified.
 * - The user identity comes exclusively from the server-side pending session.
 * - No user-supplied value from the form identifies which user is being verified.
 * - Raw device token is never stored or logged.
 * - Trust record is created only after OTP success and only if user selected "Remember".
 * - Cookie is queued only after successful DB::transaction commit.
 */
class DeviceVerificationController extends Controller
{
    public function __construct(
        private readonly OtpService         $otpService,
        private readonly TrustedDeviceService $trustedDeviceService,
    ) {}

    // =========================================================================
    // Pending session helpers
    // =========================================================================

    /** Session key that stores the user_id pending device verification. */
    private const SESSION_USER_ID    = 'pending_device_auth_user_id';
    /** Session key for the ISO-8601 expiration of the pending challenge. */
    private const SESSION_EXPIRES_AT = 'pending_device_auth_expires_at';
    /** Session key for whether the user wants to remember this device. */
    private const SESSION_REMEMBER   = 'pending_device_auth_remember';

    /**
     * Store the minimal pending-authentication state in the session.
     *
     * SECURITY:
     * - Only the user_id is stored — the identity is server-controlled.
     * - Password is NEVER stored in the session.
     * - Raw OTP is NEVER stored in the session.
     * - The challenge expires after OTP_PENDING_TTL_MINUTES to prevent stale challenges.
     */
    private const OTP_PENDING_TTL_MINUTES = 15;

    public function storePendingState(Request $request, int $userId, bool $remember = false): void
    {
        $request->session()->put(self::SESSION_USER_ID, $userId);
        $request->session()->put(self::SESSION_REMEMBER, $remember);
        $request->session()->put(
            self::SESSION_EXPIRES_AT,
            now()->addMinutes(self::OTP_PENDING_TTL_MINUTES)->toIso8601String()
        );
    }

    /**
     * Retrieve and validate the pending challenge from the session.
     *
     * Returns the User model if the pending state is valid and unexpired.
     * Returns null if no valid pending state exists.
     *
     * SECURITY: The user is resolved by the server-side session user_id only.
     */
    private function resolvePendingUser(Request $request): ?User
    {
        $userId    = $request->session()->get(self::SESSION_USER_ID);
        $expiresAt = $request->session()->get(self::SESSION_EXPIRES_AT);

        if (! $userId || ! $expiresAt) {
            return null;
        }

        // Reject expired pending challenges.
        if (now()->isAfter($expiresAt)) {
            $this->clearPendingState($request);
            return null;
        }

        $user = User::find($userId);

        if (! $user || ! in_array($user->role, ['admin', 'staff'], true)) {
            $this->clearPendingState($request);
            return null;
        }

        return $user;
    }

    /**
     * Clear all pending-authentication session keys.
     */
    public function clearPendingState(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_USER_ID,
            self::SESSION_EXPIRES_AT,
            self::SESSION_REMEMBER,
        ]);
    }

    // =========================================================================
    // Display the device verification form
    // =========================================================================

    /**
     * Show the device verification OTP form.
     *
     * Redirects to login if there is no valid pending challenge.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->resolvePendingUser($request);

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Your session has expired. Please log in again.');
        }

        $cooldown = $this->otpService->canResend($user, 'device_verification');

        return view('auth.device-verify', [
            'cooldownSeconds' => $cooldown['seconds_remaining'] ?? 0,
        ]);
    }

    // =========================================================================
    // Handle OTP submission
    // =========================================================================

    /**
     * Verify the submitted device OTP and complete the authentication flow.
     *
     * SECURITY ORDER:
     * 1. Resolve user from server-side pending session (never from request input).
     * 2. Validate OTP via OtpService (hashed, attempt-limited, expiry-checked).
     * 3. On failure: return error, do NOT authenticate.
     * 4. On success: optionally create trusted device.
     * 5. Authenticate via Auth::login() only after OTP succeeds.
     * 6. Regenerate session.
     * 7. Clear pending state.
     * 8. Redirect to appropriate dashboard.
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = $this->resolvePendingUser($request);

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Your session has expired. Please log in again.');
        }

        $validated = $request->validate([
            'otp'            => ['required', 'string', 'digits:6'],
            'remember_device' => ['nullable', 'boolean'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits'   => 'The verification code must be exactly 6 digits.',
        ]);

        // Verify OTP via OtpService — isolated to 'device_verification' purpose.
        $result = $this->otpService->verify($user, $validated['otp'], 'device_verification');

        if (! $result['success']) {
            // OTP failed: record audit event, return generic error.
            $this->recordSecurityEvent(
                $user,
                'device_otp_failed',
                'Device verification OTP failed.',
                $request
            );

            return back()->withErrors(['otp' => $result['message']]);
        }

        // OTP succeeded. Record audit event.
        $this->recordSecurityEvent(
            $user,
            'device_otp_verified',
            'Device verification OTP verified successfully.',
            $request
        );

        // Optionally create a trusted device if the user selected "Remember" on the login form or this OTP form.
        $rememberDevice = $request->boolean('remember_device') || $request->session()->get(self::SESSION_REMEMBER, false);

        if ($rememberDevice) {
            $this->createTrustedDevice($user, $request);
        } else {
            // Clear any stale invalid cookie that may be present.
            $this->trustedDeviceService->clearTrustedDeviceCookie();
        }

        // Authenticate the user — only now, after OTP success.
        // Do NOT pass remember=true: the trusted-device cookie replaces Laravel remember-me.
        Auth::login($user, false);

        // Regenerate session to bind the authenticated identity to a fresh session ID.
        $request->session()->regenerate();

        // Clear all pending device-auth session state.
        $this->clearPendingState($request);

        return $this->redirectToDashboard($user, $request);
    }

    // =========================================================================
    // OTP resend
    // =========================================================================

    /**
     * Resend the device verification OTP.
     *
     * Subject to the existing OtpService cooldown.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->resolvePendingUser($request);

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Your session has expired. Please log in again.');
        }

        $cooldown = $this->otpService->canResend($user, 'device_verification');

        if (! $cooldown['allowed']) {
            return back()->withErrors([
                'resend' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting a new code.",
            ]);
        }

        $otp = $this->otpService->generate($user, 'device_verification');
        $sent = $this->otpService->sendOtp($user, $otp, 10, 'device_verification');

        if (! $sent) {
            return back()->withErrors([
                'resend' => 'Failed to deliver verification code to your email. Please try again in a few moments.',
            ]);
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Create a trusted-device record and queue the cookie.
     *
     * ATOMICITY: The TrustedDevice::create() is wrapped in a DB::transaction().
     * The cookie is queued only after the transaction commits successfully.
     * If the transaction fails, no cookie is issued.
     *
     * SECURITY:
     * - Raw token is generated and used only within this call frame.
     * - Only the SHA-256 hash is persisted.
     * - Raw token is NOT logged, NOT stored in session, NOT returned.
     */
    private function createTrustedDevice(User $user, Request $request): void
    {
        try {
            $rawToken  = null;
            $expiresAt = $this->trustedDeviceService->trustExpiresAt();

            DB::transaction(function () use ($user, $request, $expiresAt, &$rawToken): void {
                $rawToken = $this->trustedDeviceService->generateToken();

                TrustedDevice::create([
                    'user_id'           => $user->id,
                    'device_token_hash' => $this->trustedDeviceService->hashToken($rawToken),
                    'device_name'       => $this->parseDeviceName($request->userAgent()),
                    'ip_address'        => $request->ip(),
                    'user_agent'        => $request->userAgent(),
                    'last_used_at'      => now(),
                    'expires_at'        => $expiresAt,
                ]);
            });

            // Only reached if transaction committed — queue cookie with raw token.
            if ($rawToken !== null) {
                $this->trustedDeviceService->queueTrustedDeviceCookie($rawToken, $expiresAt);

                $this->recordSecurityEvent(
                    $user,
                    'trusted_device_created',
                    'Trusted device record created and cookie issued.',
                    $request
                );
            }
        } catch (\Throwable $e) {
            // Trusted device creation failed. Log the infrastructure failure
            // but do NOT prevent user authentication — the user already verified their OTP.
            \Log::error('Failed to create trusted device', [
                'user_id' => $user->id,
                // NEVER log $rawToken or token hash.
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Derive a human-readable device label from the User-Agent string.
     *
     * This is for display/audit only. It is NEVER used as a trust criterion.
     */
    private function parseDeviceName(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }

        // Detect browser.
        $browser = match (true) {
            str_contains($userAgent, 'Edg')     => 'Edge',
            str_contains($userAgent, 'Chrome')  => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari')  => 'Safari',
            str_contains($userAgent, 'Opera')   => 'Opera',
            default                             => 'Browser',
        };

        // Detect OS.
        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac')     => 'macOS',
            str_contains($userAgent, 'Linux')   => 'Linux',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone')  => 'iPhone',
            str_contains($userAgent, 'iPad')    => 'iPad',
            default                             => 'Device',
        };

        return "{$browser} on {$os}";
    }

    /**
     * Redirect to the appropriate role dashboard after successful authentication.
     */
    private function redirectToDashboard(User $user, Request $request): RedirectResponse
    {
        // Bootstrap Admin must still be directed to setup — device trust cannot bypass it.
        if ($user->isBootstrapAdmin()) {
            return redirect()->route('admin.setup')
                ->with('info', 'Welcome to Raflora. Please complete your administrator account setup.');
        }

        $dashboardRoute = match ($user->role) {
            'admin' => route('admin.dashboard'),
            'staff' => route('staff.dashboard'),
            default => route('client.dashboard'),
        };

        return redirect()->to($dashboardRoute)
            ->with('success', 'Welcome back, ' . ($user->first_name ?? $user->name) . '!');
    }

    /**
     * Record a security audit event using the existing AuditLog infrastructure.
     *
     * SECURITY: Never log raw tokens, OTPs, passwords, or session IDs.
     */
    private function recordSecurityEvent(
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
            \Log::warning("Failed to record device security event: {$action} - " . $e->getMessage());
        }
    }
}

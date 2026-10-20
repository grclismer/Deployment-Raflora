<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class TrustedDeviceService
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /**
     * The name of the trusted-device browser cookie.
     *
     * IMPORTANT: changing this name invalidates all existing device trust cookies
     * for all users. Only change it deliberately and with a migration plan.
     */
    public const COOKIE_NAME = 'raflora_device_trust';

    /**
     * Fixed trust duration in days.
     *
     * This is the approved S-01E requirement. It must NOT be user-configurable.
     * Expiration is always calculated as now() + TRUST_DURATION_DAYS at the
     * moment of trust creation. Subsequent device usage MUST NOT extend this.
     */
    public const TRUST_DURATION_DAYS = 30;

    // -------------------------------------------------------------------------
    // Token generation
    // -------------------------------------------------------------------------

    /**
     * Generate a cryptographically random trusted-device token.
     *
     * Uses Illuminate\Support\Str::random(), which internally calls
     * random_bytes() — a cryptographically secure random byte generator.
     * The result is 64 characters drawn from the 62-symbol alphabet [A-Za-z0-9],
     * giving approximately 380 bits of entropy — sufficient for a persistent secret.
     *
     * SECURITY RULES:
     * - The returned raw token must NEVER be stored in the database.
     * - The returned raw token must NEVER be logged.
     * - The caller is responsible for hashing it before DB persistence
     *   and delivering it to the browser via a secure cookie only.
     *
     * @return string Raw cryptographically random 64-character token.
     */
    public function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Compute the SHA-256 hex digest of a raw trusted-device token.
     *
     * This is the only value that may be stored in trusted_devices.device_token_hash.
     *
     * @param  string  $rawToken  The plaintext token — never stored.
     * @return string  64-character lowercase hexadecimal SHA-256 digest.
     */
    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    // -------------------------------------------------------------------------
    // Expiration
    // -------------------------------------------------------------------------

    /**
     * Calculate the fixed trust expiration timestamp for a new trusted device.
     *
     * The returned timestamp is always now() + TRUST_DURATION_DAYS.
     * This value must be stored in trusted_devices.expires_at exactly once,
     * at the moment of trust creation. It must NOT be recalculated or updated
     * when the device is subsequently used.
     *
     * @return \Carbon\Carbon  Immutable fixed expiration timestamp.
     */
    public function trustExpiresAt(): Carbon
    {
        return now()->addDays(self::TRUST_DURATION_DAYS);
    }

    // -------------------------------------------------------------------------
    // Cookie creation
    // -------------------------------------------------------------------------

    /**
     * Queue a trusted-device cookie onto the current response.
     *
     * The cookie is configured to match the project's session security settings:
     * - HttpOnly: true (JavaScript cannot read the token)
     * - Secure: mirrors SESSION_SECURE_COOKIE (HTTPS-only in production)
     * - SameSite: lax (consistent with session cookie policy)
     * - Path: / (available to all routes)
     * - Domain: mirrors SESSION_DOMAIN (no hardcoded domain)
     * - Expiration: exactly matches the trusted device's fixed expires_at value
     *
     * SECURITY: This method only queues the cookie. It does NOT create or
     * persist the trusted_devices database record. The caller (device-verification
     * controller in S-01E-3) is responsible for coordinating both steps.
     *
     * NOTE ON ATOMICITY: The controller must create the DB record before or in
     * the same transaction as calling this method. If the DB write fails, the
     * cookie must not be queued. The recommended pattern in S-01E-3 is:
     *   1. Wrap DB::transaction() around TrustedDevice::create().
     *   2. Queue the cookie only after the transaction commits successfully.
     *
     * @param  string  $rawToken   The plaintext token to store in the cookie.
     *                             NEVER the hash — the browser must present the
     *                             raw token to be hashed during validation.
     * @param  \Carbon\Carbon  $expiresAt  The fixed expiration from trustExpiresAt().
     */
    public function queueTrustedDeviceCookie(string $rawToken, Carbon $expiresAt): void
    {
        Cookie::queue($this->buildTrustedDeviceCookie($rawToken, $expiresAt));
    }

    /**
     * Build (but do not queue) a trusted-device Symfony Cookie object.
     *
     * Useful in tests or when the cookie must be attached to a specific response
     * object rather than the global cookie queue.
     *
     * @param  string  $rawToken
     * @param  \Carbon\Carbon  $expiresAt
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function buildTrustedDeviceCookie(string $rawToken, Carbon $expiresAt): SymfonyCookie
    {
        return new SymfonyCookie(
            name: self::COOKIE_NAME,
            value: $rawToken,
            expire: $expiresAt->timestamp,
            path: config('session.path', '/'),
            domain: config('session.domain'),
            secure: config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    // -------------------------------------------------------------------------
    // Cookie extraction
    // -------------------------------------------------------------------------

    /**
     * Extract the raw trusted-device token from the current request's cookie.
     *
     * Returns null if the cookie is absent or empty.
     *
     * SECURITY: This method ONLY returns the presented token. It does NOT
     * authenticate the user, validate the token against the database, or
     * take any action. The caller must still perform findUsableForUser().
     *
     * @return string|null  Raw token from the cookie, or null if absent.
     */
    public function getTokenFromCookie(): ?string
    {
        $value = request()->cookie(self::COOKIE_NAME);

        if (empty($value) || ! is_string($value)) {
            return null;
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Cookie clearing
    // -------------------------------------------------------------------------

    /**
     * Queue a response instruction that immediately expires the trusted-device cookie.
     *
     * Used when:
     * - A device token is found to be invalid/expired/revoked during login.
     * - A user explicitly revokes this device.
     * - A logout handler needs to clean up device trust state.
     *
     * Cookie::forget() issues a Set-Cookie header with expiration in the past,
     * which instructs the browser to remove the cookie immediately.
     */
    public function clearTrustedDeviceCookie(): void
    {
        Cookie::queue(Cookie::forget(
            name: self::COOKIE_NAME,
            path: config('session.path', '/'),
            domain: config('session.domain'),
        ));
    }

    // -------------------------------------------------------------------------
    // Trust validation / lookup
    // -------------------------------------------------------------------------

    /**
     * Find the active (usable) TrustedDevice for a specific user and presented token.
     *
     * SECURITY DESIGN:
     * - The lookup is strictly USER-BOUND. A raw token alone is never sufficient
     *   to locate a record — the user must be known first (via successful password
     *   authentication in the S-01E-3 login flow).
     * - The presented token is hashed before any database query.
     * - Database lookup matches user_id + device_token_hash + expires_at > now()
     *   + revoked_at IS NULL.
     * - A found record is further validated through TrustedDevice::isUsable(),
     *   which checks expires_at and revoked_at at the model level.
     * - IP address, User-Agent, and device_name are NEVER used as trust criteria.
     *
     * TOKEN COMPARISON NOTE:
     * The comparison is performed as an exact SHA-256 hex equality database query.
     * Because the stored value is a deterministic hash digest (not a secret itself),
     * a constant-time comparison is not strictly required at this layer. The raw
     * token was already compared as a cookie value delivered over TLS.
     *
     * @param  \App\Models\User  $user      The user whose device we are checking.
     *                                       Must be the user who supplied the correct
     *                                       password (already authenticated).
     * @param  string            $rawToken  The token extracted from the browser cookie.
     * @return \App\Models\TrustedDevice|null  The usable record, or null if none found.
     */
    public function findUsableForUser(User $user, string $rawToken): ?TrustedDevice
    {
        // Reject empty/blank tokens immediately, without touching the database.
        if (empty(trim($rawToken))) {
            return null;
        }

        $tokenHash = $this->hashToken($rawToken);

        /** @var \App\Models\TrustedDevice|null $device */
        $device = $user->trustedDevices()
            ->where('device_token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($device === null) {
            return null;
        }

        // Double-check through the model's own isUsable() to respect any
        // future changes to usability logic without needing to update this query.
        return $device->isUsable() ? $device : null;
    }
}

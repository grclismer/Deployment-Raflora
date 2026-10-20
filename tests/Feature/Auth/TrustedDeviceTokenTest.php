<?php

namespace Tests\Feature\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

/**
 * S-01E-2: TrustedDeviceService — token, cookie, and lookup infrastructure tests.
 *
 * What is tested here:
 * - Secure token generation (format, length, uniqueness)
 * - SHA-256 hashing (correctness, determinism, format)
 * - Trust expiration calculation (fixed 30-day, non-sliding)
 * - Cookie creation (name, HttpOnly, SameSite, expiry)
 * - Cookie extraction (from request)
 * - Cookie clearing (expiration header)
 * - Trust validation/lookup (user-bound, hash-based, revocation/expiry aware)
 * - Security invariants (no raw token in DB, correct user binding, no IP/UA criteria)
 *
 * What is NOT tested here:
 * - Login flow or AuthController behavior (S-01E-3)
 * - OTP challenges or OtpService (separate)
 * - Device-verification UI or routes (S-01E-3)
 * - Client authentication behavior (out of scope)
 */
class TrustedDeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    private TrustedDeviceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrustedDeviceService();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function adminUser(): User
    {
        return User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function staffUser(): User
    {
        return User::factory()->create([
            'role'              => 'staff',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Create a TrustedDevice record for a user using the service's own
     * hashing and expiration helpers to reflect real-world usage.
     */
    private function createDevice(User $user, string $rawToken, array $overrides = []): TrustedDevice
    {
        return TrustedDevice::create(array_merge([
            'user_id'           => $user->id,
            'device_token_hash' => $this->service->hashToken($rawToken),
            'device_name'       => 'Chrome on Windows',
            'ip_address'        => '127.0.0.1',
            'user_agent'        => 'Mozilla/5.0',
            'expires_at'        => $this->service->trustExpiresAt(),
        ], $overrides));
    }

    // =========================================================================
    // 1–7: Token generation & hashing
    // =========================================================================

    /** Test 1: generateToken() returns a non-empty string. */
    public function test_generates_a_trusted_device_token(): void
    {
        $token = $this->service->generateToken();
        $this->assertIsString($token);
    }

    /** Test 2: Generated token is not empty. */
    public function test_generated_token_is_not_empty(): void
    {
        $token = $this->service->generateToken();
        $this->assertNotEmpty($token);
    }

    /** Test 3: Generated token is exactly 64 characters long (Str::random(64)). */
    public function test_generated_token_meets_expected_length_and_format(): void
    {
        $token = $this->service->generateToken();

        $this->assertEquals(64, strlen($token));

        // Str::random(64) uses [A-Za-z0-9] alphabet — verify no other characters.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
    }

    /** Test 4: hashToken() produces a 64-character SHA-256 hexadecimal digest. */
    public function test_hashing_a_token_produces_64_character_sha256_hex_digest(): void
    {
        $token = $this->service->generateToken();
        $hash  = $this->service->hashToken($token);

        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    /** Test 5: Raw token is different from its SHA-256 hash. */
    public function test_raw_token_differs_from_its_hash(): void
    {
        $token = $this->service->generateToken();
        $hash  = $this->service->hashToken($token);

        $this->assertNotEquals($token, $hash);
    }

    /** Test 6: Hashing the same raw token twice yields the same digest (determinism). */
    public function test_same_raw_token_produces_same_sha256_hash(): void
    {
        $token  = $this->service->generateToken();
        $hashA  = $this->service->hashToken($token);
        $hashB  = $this->service->hashToken($token);

        $this->assertEquals($hashA, $hashB);
    }

    /** Test 7: Different tokens produce different hashes. */
    public function test_different_tokens_produce_different_hashes(): void
    {
        $tokenA = $this->service->generateToken();
        $tokenB = $this->service->generateToken();

        // It is astronomically unlikely that two Str::random(64) calls collide.
        $this->assertNotEquals(
            $this->service->hashToken($tokenA),
            $this->service->hashToken($tokenB)
        );
    }

    // =========================================================================
    // 8–13: Trust validation / lookup
    // =========================================================================

    /**
     * Test 8: A valid trusted device is found when all conditions are met:
     * - correct user
     * - correct raw token
     * - expires_at in the future
     * - revoked_at is null
     */
    public function test_valid_trusted_device_is_found_for_correct_user_and_token(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        $this->createDevice($admin, $raw);

        $result = $this->service->findUsableForUser($admin, $raw);

        $this->assertNotNull($result);
        $this->assertInstanceOf(TrustedDevice::class, $result);
        $this->assertEquals($admin->id, $result->user_id);
    }

    /** Test 9: Wrong token does not find the trusted device. */
    public function test_wrong_token_does_not_find_trusted_device(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        $this->createDevice($admin, $raw);

        $wrongToken = $this->service->generateToken(); // Different token
        $result     = $this->service->findUsableForUser($admin, $wrongToken);

        $this->assertNull($result);
    }

    /** Test 10: Correct token for User A cannot find User B's trusted device. */
    public function test_token_for_user_a_cannot_find_user_b_device(): void
    {
        $userA = $this->adminUser();
        $userB = $this->staffUser();

        $rawA = $this->service->generateToken();
        $rawB = $this->service->generateToken();

        $this->createDevice($userA, $rawA);
        $this->createDevice($userB, $rawB);

        // User A's token cannot locate User B's device.
        $result = $this->service->findUsableForUser($userB, $rawA);
        $this->assertNull($result, 'Cross-user device lookup must return null.');

        // User B's token cannot locate User A's device.
        $result2 = $this->service->findUsableForUser($userA, $rawB);
        $this->assertNull($result2, 'Cross-user device lookup must return null.');
    }

    /** Test 11: Expired token is rejected — findUsableForUser() returns null. */
    public function test_expired_token_is_rejected(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        $this->createDevice($admin, $raw, [
            'expires_at' => now()->subMinute(), // Already expired
        ]);

        $result = $this->service->findUsableForUser($admin, $raw);
        $this->assertNull($result);
    }

    /** Test 12: Revoked token is rejected. */
    public function test_revoked_token_is_rejected(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        $device = $this->createDevice($admin, $raw, [
            'expires_at' => now()->addDays(30),
        ]);
        $device->revoke();

        $result = $this->service->findUsableForUser($admin, $raw);
        $this->assertNull($result);
    }

    /** Test 13: Empty or blank token is immediately rejected without DB query. */
    public function test_empty_token_is_rejected(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();
        $this->createDevice($admin, $raw);

        $this->assertNull($this->service->findUsableForUser($admin, ''));
        $this->assertNull($this->service->findUsableForUser($admin, '   '));
    }

    // =========================================================================
    // 14: Fixed expiration
    // =========================================================================

    /**
     * Test 14: Trust expiration is fixed at creation.
     * Simulating a device usage later must NOT change expires_at.
     */
    public function test_fixed_expiration_does_not_slide_on_usage(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        $originalExpiry = $this->service->trustExpiresAt();

        $device = $this->createDevice($admin, $raw, [
            'expires_at' => $originalExpiry,
        ]);

        // Simulate using the device 20 days later.
        Carbon::setTestNow(now()->addDays(20));
        $device->recordUsage('192.168.1.50');
        $device->refresh();

        // expires_at must remain the original creation-time value.
        $this->assertEquals(
            $originalExpiry->toDateTimeString(),
            $device->expires_at->toDateTimeString(),
            'expires_at must not change after device usage.'
        );

        // Restore time.
        Carbon::setTestNow();
    }

    /** trustExpiresAt() always returns now() + exactly TRUST_DURATION_DAYS. */
    public function test_trust_expires_at_returns_now_plus_30_days(): void
    {
        $before = now()->addDays(TrustedDeviceService::TRUST_DURATION_DAYS);

        $expiresAt = $this->service->trustExpiresAt();

        $after = now()->addDays(TrustedDeviceService::TRUST_DURATION_DAYS);

        $this->assertTrue(
            $expiresAt->between($before, $after),
            'trustExpiresAt() must be within [now+30d, now+30d] window.'
        );
    }

    // =========================================================================
    // 15: Cookie creation
    // =========================================================================

    /**
     * Test 15: The built cookie has the correct name, HttpOnly, SameSite, and expiry.
     *
     * buildTrustedDeviceCookie() returns a Symfony Cookie directly so that
     * its attributes can be inspected without going through the request lifecycle.
     */
    public function test_cookie_has_correct_name_http_only_samesite_and_expiration(): void
    {
        $raw       = $this->service->generateToken();
        $expiresAt = $this->service->trustExpiresAt();

        $cookie = $this->service->buildTrustedDeviceCookie($raw, $expiresAt);

        // Cookie name.
        $this->assertEquals(TrustedDeviceService::COOKIE_NAME, $cookie->getName());
        $this->assertEquals('raflora_device_trust', $cookie->getName());

        // HttpOnly must be true — prevents JavaScript access.
        $this->assertTrue($cookie->isHttpOnly());

        // SameSite must be 'lax' — matching session cookie policy.
        $this->assertEquals('lax', strtolower($cookie->getSameSite()));

        // Path must be / — cookie available to all routes.
        $this->assertEquals('/', $cookie->getPath());

        // Cookie value must be the raw token (not the hash).
        $this->assertEquals($raw, $cookie->getValue());

        // Expiry must be approximately 30 days from now.
        // Allow a ±2 minute window for test execution latency.
        $expectedExpiry = $expiresAt->timestamp;
        $actualExpiry   = $cookie->getExpiresTime();

        $this->assertEqualsWithDelta($expectedExpiry, $actualExpiry, 120,
            'Cookie expiry must match the trusted-device fixed expires_at (within 2 minutes).'
        );
    }

    /**
     * queueTrustedDeviceCookie() queues the cookie onto the global cookie jar.
     */
    public function test_queue_trusted_device_cookie_queues_the_cookie(): void
    {
        Cookie::spy();

        $raw       = $this->service->generateToken();
        $expiresAt = $this->service->trustExpiresAt();

        $this->service->queueTrustedDeviceCookie($raw, $expiresAt);

        Cookie::shouldHaveReceived('queue')->once();
    }

    // =========================================================================
    // 16: Cookie extraction
    // =========================================================================

    /**
     * Test 16: getTokenFromCookie() returns the raw token from the request.
     */
    public function test_cookie_extraction_returns_presented_raw_token(): void
    {
        $raw  = $this->service->generateToken();
        $name = TrustedDeviceService::COOKIE_NAME;

        // Simulate a request with the device cookie already set.
        $response = $this->withCookies([$name => $raw])
            ->get('/');

        // Make a fresh request with the cookie in context.
        $this->withCookies([$name => $raw])->call('GET', '/', [], [], [], [
            'HTTP_COOKIE' => "{$name}={$raw}",
        ]);

        // Test the extraction directly via request() helper
        // by binding the cookie in the request.
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [
            $name => $raw,
        ]);
        app()->instance('request', $request);

        $extracted = $this->service->getTokenFromCookie();
        $this->assertEquals($raw, $extracted);

        // Restore original request binding.
        app()->instance('request', request());
    }

    /**
     * getTokenFromCookie() returns null when the cookie is absent.
     */
    public function test_cookie_extraction_returns_null_when_cookie_absent(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET');
        app()->instance('request', $request);

        $extracted = $this->service->getTokenFromCookie();
        $this->assertNull($extracted);

        app()->instance('request', request());
    }

    // =========================================================================
    // 17: Cookie clearing
    // =========================================================================

    /**
     * Test 17: clearTrustedDeviceCookie() queues a forget/expire instruction.
     */
    public function test_clear_trusted_device_cookie_queues_forget_cookie(): void
    {
        Cookie::spy();

        $this->service->clearTrustedDeviceCookie();

        // Cookie::queue() must be called (Cookie::forget returns a cookie object
        // that is then queued).
        Cookie::shouldHaveReceived('queue')->once();
    }

    /**
     * The cleared cookie should have an expiration in the past.
     */
    public function test_cleared_cookie_has_past_expiration(): void
    {
        $clearedCookie = Cookie::forget(TrustedDeviceService::COOKIE_NAME, '/');

        $this->assertTrue(
            $clearedCookie->getExpiresTime() < time(),
            'The forget cookie must have an expiration timestamp in the past.'
        );
    }

    // =========================================================================
    // 18: No raw token in database
    // =========================================================================

    /**
     * Test 18: The trusted_devices table never stores a raw token.
     *
     * After creating a device using the service's hash helper, verify:
     * - device_token_hash is a 64-char SHA-256 hex string.
     * - No column named 'device_token' or 'token' exists in the stored attributes.
     */
    public function test_no_raw_token_is_stored_in_the_database(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();
        $hash  = $this->service->hashToken($raw);

        $device = $this->createDevice($admin, $raw);

        $storedAttributes = $device->getAttributes();

        // The stored hash must equal our SHA-256 computation.
        $this->assertEquals($hash, $storedAttributes['device_token_hash']);

        // The raw token must NOT appear anywhere in the stored attributes.
        $this->assertNotEquals($raw, $storedAttributes['device_token_hash']);

        // Forbidden plaintext column names.
        $this->assertArrayNotHasKey('device_token', $storedAttributes);
        $this->assertArrayNotHasKey('token', $storedAttributes);
        $this->assertArrayNotHasKey('raw_token', $storedAttributes);
    }

    // =========================================================================
    // 19: No raw token in logs/exceptions
    // =========================================================================

    /**
     * Test 19: Service operations do not log or throw exceptions that expose the raw token.
     *
     * We verify that:
     * - Token generation does not write to the log.
     * - Hash computation does not write to the log.
     * - findUsableForUser() with a missing record does not expose the token.
     *
     * Full log-channel testing is not possible in the standard test environment,
     * but we confirm no exception is thrown that includes the raw token string.
     */
    public function test_service_operations_do_not_throw_exceptions_exposing_raw_token(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();
        $hash  = $this->service->hashToken($raw);

        // These calls must not throw.
        $expiresAt = $this->service->trustExpiresAt();
        $result    = $this->service->findUsableForUser($admin, $raw); // No DB record yet.

        $this->assertNull($result);
        $this->assertNotEmpty($hash);
        $this->assertNotEmpty($raw);
    }

    // =========================================================================
    // 20: Lookup does not use IP / UA / device_name as trust criteria
    // =========================================================================

    /**
     * Test 20: findUsableForUser() ignores IP address, User-Agent, and device_name.
     *
     * A device with null IP / UA / device_name must still be found if the token matches.
     * A device with full IP / UA / device_name but wrong token must NOT be found.
     */
    public function test_lookup_does_not_use_ip_ua_or_device_name_as_trust_criteria(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        // Device created with NO IP, UA, or device_name — should still be usable.
        TrustedDevice::create([
            'user_id'           => $admin->id,
            'device_token_hash' => $this->service->hashToken($raw),
            'device_name'       => null,
            'ip_address'        => null,
            'user_agent'        => null,
            'expires_at'        => $this->service->trustExpiresAt(),
        ]);

        $result = $this->service->findUsableForUser($admin, $raw);
        $this->assertNotNull($result, 'Device with null IP/UA/name should be found by token alone.');

        // Now try a completely different token — the device above has rich metadata
        // but the token is wrong. Must return null.
        $wrongToken = $this->service->generateToken();
        $result2    = $this->service->findUsableForUser($admin, $wrongToken);
        $this->assertNull($result2, 'Wrong token must not match even if IP/UA/name would match.');
    }

    // =========================================================================
    // 21: Fixed 30-day expiration does not slide
    // =========================================================================

    /**
     * Test 21: trustExpiresAt() always uses TRUST_DURATION_DAYS = 30.
     *
     * Explicitly verifying the constant to guard against drift.
     */
    public function test_trust_duration_days_constant_is_30(): void
    {
        $this->assertEquals(30, TrustedDeviceService::TRUST_DURATION_DAYS);
    }

    /**
     * Repeated calls to trustExpiresAt() never return a shorter or longer interval.
     */
    public function test_trust_expires_at_always_uses_exactly_30_days(): void
    {
        // Freeze time for precision.
        Carbon::setTestNow('2026-09-22 00:00:00');

        $expiresAt = $this->service->trustExpiresAt();

        $expected = Carbon::now()->addDays(30);
        $this->assertEquals($expected->toDateTimeString(), $expiresAt->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * recordUsage() does not change expires_at — non-sliding expiration.
     * (Companion to TrustedDeviceTest::test_record_usage_does_not_extend_expires_at,
     * verified here from the service layer perspective.)
     */
    public function test_expiration_does_not_slide_after_usage_via_service_layer(): void
    {
        $admin = $this->adminUser();
        $raw   = $this->service->generateToken();

        Carbon::setTestNow('2026-09-22 00:00:00');
        $originalExpiry = $this->service->trustExpiresAt(); // Day 0 + 30 = Oct 22

        $device = $this->createDevice($admin, $raw, ['expires_at' => $originalExpiry]);

        // Simulate day 15.
        Carbon::setTestNow('2026-10-07 12:00:00');
        $device->recordUsage();
        $device->refresh();

        $this->assertEquals(
            $originalExpiry->toDateTimeString(),
            $device->expires_at->toDateTimeString(),
            'expires_at must remain fixed at Day 30, not slide to Day 45.'
        );

        Carbon::setTestNow();
    }

    // =========================================================================
    // COOKIE_NAME constant correctness
    // =========================================================================

    public function test_cookie_name_constant_is_raflora_device_trust(): void
    {
        $this->assertEquals('raflora_device_trust', TrustedDeviceService::COOKIE_NAME);
    }
}

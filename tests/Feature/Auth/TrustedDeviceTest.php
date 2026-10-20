<?php

namespace Tests\Feature\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S-01E-1: TrustedDevice database and model foundation tests.
 *
 * These tests verify the trusted_devices table, Eloquent model conventions,
 * state helper methods, relationship integrity, and—critically—that no
 * plaintext device token is ever stored in the database schema or model.
 *
 * What is NOT tested here:
 * - Login interception / device recognition during authentication
 * - OTP challenge dispatch
 * - Cookie creation / reading
 * - Client authentication behavior (out of S-01E scope)
 */
class TrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a minimal set of valid TrustedDevice attributes for a given user.
     * The device_token_hash simulates the SHA-256 hash that the application
     * layer will produce; a raw token is NEVER stored.
     */
    private function deviceAttributes(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id'            => $user->id,
            'device_token_hash'  => hash('sha256', 'dummy-raw-token-never-stored'),
            'device_name'        => 'Chrome on Windows',
            'ip_address'         => '127.0.0.1',
            'user_agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'expires_at'         => now()->addDays(30),
        ], $overrides);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role'             => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function staffUser(): User
    {
        return User::factory()->create([
            'role'             => 'staff',
            'email_verified_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Database record creation
    // -------------------------------------------------------------------------

    public function test_trusted_devices_table_can_create_a_valid_record(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin));

        $this->assertDatabaseHas('trusted_devices', [
            'id'                => $device->id,
            'user_id'           => $admin->id,
            'device_token_hash' => hash('sha256', 'dummy-raw-token-never-stored'),
            'device_name'       => 'Chrome on Windows',
        ]);

        $this->assertNull($device->revoked_at);
        $this->assertNull($device->last_used_at);
        $this->assertNotNull($device->expires_at);
    }

    // -------------------------------------------------------------------------
    // 2. User → TrustedDevice relationship
    // -------------------------------------------------------------------------

    public function test_user_has_many_trusted_devices_relationship_works(): void
    {
        $admin = $this->adminUser();

        TrustedDevice::create($this->deviceAttributes($admin));
        TrustedDevice::create($this->deviceAttributes($admin, [
            'device_token_hash' => hash('sha256', 'second-raw-token-never-stored'),
            'device_name'       => 'Firefox on Linux',
        ]));

        $this->assertCount(2, $admin->trustedDevices);
        $this->assertInstanceOf(TrustedDevice::class, $admin->trustedDevices->first());
    }

    // -------------------------------------------------------------------------
    // 3. TrustedDevice → User relationship
    // -------------------------------------------------------------------------

    public function test_trusted_device_belongs_to_user_relationship_works(): void
    {
        $staff  = $this->staffUser();
        $device = TrustedDevice::create($this->deviceAttributes($staff));

        $this->assertInstanceOf(User::class, $device->user);
        $this->assertEquals($staff->id, $device->user->id);
        $this->assertEquals('staff', $device->user->role);
    }

    // -------------------------------------------------------------------------
    // 4. isUsable() — active device
    // -------------------------------------------------------------------------

    public function test_is_usable_returns_true_when_expires_at_is_future_and_not_revoked(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]));

        $this->assertTrue($device->isUsable());
    }

    // -------------------------------------------------------------------------
    // 5. isUsable() — expired device
    // -------------------------------------------------------------------------

    public function test_is_usable_returns_false_when_expires_at_is_in_the_past(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at' => now()->subMinute(),
            'revoked_at' => null,
        ]));

        $this->assertFalse($device->isUsable());
    }

    // -------------------------------------------------------------------------
    // 6. isUsable() — revoked device
    // -------------------------------------------------------------------------

    public function test_is_usable_returns_false_when_revoked_at_is_set(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at' => now()->addDays(30),
            'revoked_at' => now()->subHour(),
        ]));

        $this->assertFalse($device->isUsable());
    }

    // -------------------------------------------------------------------------
    // 7. isUsable() — both expired and revoked
    // -------------------------------------------------------------------------

    public function test_is_usable_returns_false_when_both_expired_and_revoked(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at' => now()->subDay(),
            'revoked_at' => now()->subDay(),
        ]));

        $this->assertFalse($device->isUsable());
    }

    // -------------------------------------------------------------------------
    // 8. recordUsage() updates last_used_at
    // -------------------------------------------------------------------------

    public function test_record_usage_updates_last_used_at(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin));

        $this->assertNull($device->last_used_at);

        $device->recordUsage('192.168.1.100');
        $device->refresh();

        $this->assertNotNull($device->last_used_at);
        $this->assertTrue($device->last_used_at->isToday());
        $this->assertEquals('192.168.1.100', $device->ip_address);
    }

    // -------------------------------------------------------------------------
    // 9. recordUsage() MUST NOT extend expires_at
    // -------------------------------------------------------------------------

    public function test_record_usage_does_not_extend_expires_at(): void
    {
        $admin      = $this->adminUser();
        $fixedExpiry = now()->addDays(30);

        $device = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at' => $fixedExpiry,
        ]));

        $device->recordUsage();
        $device->refresh();

        // expires_at must remain identical to what was set at creation.
        $this->assertEquals(
            $fixedExpiry->toDateTimeString(),
            $device->expires_at->toDateTimeString()
        );
    }

    // -------------------------------------------------------------------------
    // 10. revoke() sets revoked_at
    // -------------------------------------------------------------------------

    public function test_revoke_sets_revoked_at_and_makes_device_unusable(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at' => now()->addDays(30),
        ]));

        $this->assertTrue($device->isUsable());
        $this->assertNull($device->revoked_at);

        $device->revoke();
        $device->refresh();

        $this->assertNotNull($device->revoked_at);
        $this->assertFalse($device->isUsable());
    }

    // -------------------------------------------------------------------------
    // 11. Multiple trusted devices per user
    // -------------------------------------------------------------------------

    public function test_multiple_trusted_devices_can_belong_to_the_same_user(): void
    {
        $admin = $this->adminUser();

        $tokens = ['token-a', 'token-b', 'token-c'];

        foreach ($tokens as $raw) {
            TrustedDevice::create($this->deviceAttributes($admin, [
                'device_token_hash' => hash('sha256', $raw),
            ]));
        }

        $this->assertCount(3, $admin->trustedDevices()->get());
    }

    // -------------------------------------------------------------------------
    // 12. Cascade delete on user removal
    // -------------------------------------------------------------------------

    public function test_deleting_a_user_removes_their_trusted_devices(): void
    {
        $admin = $this->adminUser();
        TrustedDevice::create($this->deviceAttributes($admin));
        TrustedDevice::create($this->deviceAttributes($admin, [
            'device_token_hash' => hash('sha256', 'second-raw-token'),
        ]));

        $adminId = $admin->id;
        $this->assertCount(2, TrustedDevice::where('user_id', $adminId)->get());

        $admin->delete();

        $this->assertCount(0, TrustedDevice::where('user_id', $adminId)->get());
    }

    // -------------------------------------------------------------------------
    // 13. Model/DB layer allows any role (auth enforcement is at login, not model)
    // -------------------------------------------------------------------------

    public function test_trusted_device_table_is_role_agnostic_at_the_model_layer(): void
    {
        $client = User::factory()->create([
            'role'             => 'client',
            'email_verified_at' => now(),
        ]);

        // A client record CAN exist at the DB layer; the restriction that clients
        // are never challenged for device verification is enforced in the auth
        // layer (AuthController / S-01E-2), NOT in the model or migration.
        $device = TrustedDevice::create($this->deviceAttributes($client));

        $this->assertDatabaseHas('trusted_devices', ['user_id' => $client->id]);
        $this->assertEquals($client->id, $device->user->id);
    }

    // -------------------------------------------------------------------------
    // SECURITY: No plaintext token column exists in the schema or model
    // -------------------------------------------------------------------------

    public function test_trusted_device_model_does_not_define_a_plaintext_token_attribute(): void
    {
        $admin  = $this->adminUser();
        $device = TrustedDevice::create($this->deviceAttributes($admin));

        // These attribute names MUST NOT exist in the model or the DB row.
        $forbiddenAttributes = [
            'device_token',
            'raw_token',
            'trusted_token',
            'token',
        ];

        $attributes = $device->getAttributes();

        foreach ($forbiddenAttributes as $forbidden) {
            $this->assertArrayNotHasKey(
                $forbidden,
                $attributes,
                "Security violation: plaintext token column '{$forbidden}' found in trusted_devices."
            );
        }

        // Confirm the hash column exists and is the SHA-256 hex representation.
        $this->assertArrayHasKey('device_token_hash', $attributes);
        $this->assertEquals(64, strlen($attributes['device_token_hash']));
    }

    // -------------------------------------------------------------------------
    // isUsable() uses ONLY expires_at and revoked_at — not UA or IP
    // -------------------------------------------------------------------------

    public function test_is_usable_does_not_depend_on_device_name_ip_or_user_agent(): void
    {
        $admin = $this->adminUser();

        $deviceA = TrustedDevice::create($this->deviceAttributes($admin, [
            'expires_at'  => now()->addDays(30),
            'revoked_at'  => null,
            'device_name' => null,
            'ip_address'  => null,
            'user_agent'  => null,
        ]));

        $deviceB = TrustedDevice::create($this->deviceAttributes($admin, [
            'device_token_hash' => hash('sha256', 'different-token'),
            'expires_at'  => now()->subMinute(),
            'revoked_at'  => null,
            'device_name' => 'Chrome on Windows',
            'ip_address'  => '192.168.1.1',
            'user_agent'  => 'Mozilla/5.0',
        ]));

        // Device A has no UA/IP but is valid → usable.
        $this->assertTrue($deviceA->isUsable(), 'Active device with null UA/IP should still be usable.');

        // Device B has full UA/IP but is expired → NOT usable.
        $this->assertFalse($deviceB->isUsable(), 'Expired device with full UA/IP must NOT be usable.');
    }
}

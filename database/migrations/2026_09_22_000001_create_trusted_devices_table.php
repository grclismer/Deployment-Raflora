<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the `trusted_devices` table used by the Admin/Staff device-trust
     * mechanism introduced in S-01E. Each row represents a single browser/device
     * that a user has verified via email OTP and optionally asked to be remembered
     * for 30 days.
     *
     * Security notes:
     * - Only the SHA-256 hash of the device token is stored (device_token_hash).
     *   The raw token is NEVER persisted in any column.
     * - Trust expiration is FIXED at creation time (expires_at = now + 30 days).
     *   Expiration MUST NOT be extended when the device is used.
     * - Revocation is represented by revoked_at; a non-null value means the
     *   device is immediately invalid regardless of expires_at.
     * - device_name, ip_address, and user_agent are descriptive/audit fields
     *   and MUST NOT be used as proof of device trust.
     */
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table. Deleting a user removes all their
            // trusted-device records automatically.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // SHA-256 hexadecimal hash of the random device token (64 hex chars).
            // The plaintext token is NEVER stored in the database.
            $table->string('device_token_hash', 64);

            // Human-readable label derived from the User-Agent for display/audit.
            // Examples: "Chrome on Windows", "Safari on iPhone".
            // MUST NOT be used as a trust credential.
            $table->string('device_name')->nullable();

            // IPv4 or IPv6 address (max 45 characters) recorded when trust is
            // established or exercised. Stored for security audit purposes only.
            $table->string('ip_address', 45)->nullable();

            // Full User-Agent string observed when trust was created.
            // Stored for forensic audit purposes only.
            $table->text('user_agent')->nullable();

            // Timestamp of the most recent successful login using this device.
            // Updated on each trusted-device login. Does NOT extend expires_at.
            $table->timestamp('last_used_at')->nullable();

            // Fixed expiration: set once at trust creation to now() + 30 days.
            // MUST NOT be modified by usage or login.
            $table->timestamp('expires_at');

            // Explicit revocation timestamp. Non-null means this device is
            // immediately invalid, regardless of expires_at.
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // Primary lookup index: used when a browser presents a device cookie.
            // The authentication layer will hash the cookie value and query
            // (user_id, device_token_hash) to locate the record.
            $table->index(['user_id', 'device_token_hash']);

            // Index supporting active-device queries that filter on:
            // user_id + expires_at (> now) + revoked_at (IS NULL).
            $table->index(['user_id', 'expires_at', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};

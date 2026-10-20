<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $table = 'trusted_devices';

    /**
     * The attributes that are mass assignable.
     *
     * Security note: `device_token_hash` is intentionally listed here so that
     * the controller/service layer can persist it via ::create(). The raw
     * token must NEVER be stored; only the SHA-256 hash is passed in.
     *
     * `expires_at` and `revoked_at` are included because they are set at
     * creation time by the application layer, not by user-supplied input.
     */
    protected $fillable = [
        'user_id',
        'device_token_hash',
        'device_name',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user that owns this trusted device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether this trusted-device record is currently valid for
     * exempting the user from the OTP/device-verification second factor.
     *
     * A device is usable if and only if:
     *   1. It has not been explicitly revoked (revoked_at is null).
     *   2. Its fixed-expiration timestamp is still in the future.
     *
     * IMPORTANT: device_name, ip_address, and user_agent are NEVER evaluated
     * as trust criteria inside this method.
     */
    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * Explicitly revoke this trusted device, preventing any future use.
     *
     * A revoked device cannot be reactivated.
     * Sets revoked_at to the current timestamp and persists the record.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * Record that this device was used for a trusted-device login.
     *
     * Updates last_used_at to the current timestamp.
     * Optionally records the request IP address.
     *
     * CRITICAL: expires_at is intentionally NOT touched here.
     * Trust expiration is FIXED at creation and must never slide.
     */
    public function recordUsage(?string $ipAddress = null): void
    {
        $this->update(array_filter([
            'last_used_at' => now(),
            'ip_address'   => $ipAddress,
        ], fn ($value) => $value !== null));
    }
}

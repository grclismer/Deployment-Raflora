<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryGuestBooking extends Model
{
    protected $fillable = [
        'claim_token_hash',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_address',
        'booking_type',
        'package_id',
        'event_type',
        'event_date',
        'event_time',
        'end_time',
        'venue',
        'venue_latitude',
        'venue_longitude',
        'venue_setting',
        'table_count',
        'guest_count',
        'special_requests',
        'design_brief',
        'inspiration_image_path',
        'analysis_data',
        'expires_at',
        'claimed_at',
        'client_id'
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'string',
        'analysis_data' => 'json',
        'venue_latitude' => 'float',
        'venue_longitude' => 'float',
        'design_brief' => 'array',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function getInspirationImageAttribute(): ?string
    {
        return $this->inspiration_image_path;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function isClaimed(): bool
    {
        return !is_null($this->claimed_at) || !is_null($this->client_id);
    }

    /**
     * Set a new secure claim token and return the raw token string (UUID).
     */
    public function generateToken(): string
    {
        $rawToken = (string) Str::uuid();
        $this->claim_token_hash = hash('sha256', $rawToken);
        return $rawToken;
    }

    /**
     * Determine if a given raw token is valid for this request.
     */
    public function validateToken(string $rawToken): bool
    {
        return hash_equals($this->claim_token_hash ?? '', hash('sha256', $rawToken));
    }
}

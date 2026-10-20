<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    // -----------------------------------------------------------------------
    // Status constants
    // -----------------------------------------------------------------------
    /** Draft/initial state — not yet issued to client */
    public const STATUS_PENDING = 'pending';

    /** Actively issued to client — authoritative offer */
    public const STATUS_ISSUED = 'issued';

    /** Replaced by a newer quotation version */
    public const STATUS_SUPERSEDED = 'superseded';

    /** Accepted by the client — binding offer */
    public const STATUS_ACCEPTED = 'accepted';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'issued_by',
        'suggested_florals',
        'recommended_price',
        'status',
        'valid_until',
        'version',
        'raw_materials_sum',
        'multiplier',
        'labor_method',
        'labor_rate',
        'labor_amount',
        'final_quoted_price',
        'downpayment_percentage',
        'items_snapshot',
        'is_tentative',
        'reconfirmed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suggested_florals'   => 'json',
            'recommended_price'   => 'decimal:2',
            'valid_until'         => 'date',
            'version'             => 'integer',
            'raw_materials_sum'   => 'decimal:2',
            'multiplier'          => 'decimal:2',
            'labor_rate'          => 'decimal:2',
            'labor_amount'        => 'decimal:2',
            'final_quoted_price'       => 'decimal:2',
            'downpayment_percentage'   => 'decimal:2',
            'items_snapshot'           => 'json',
            'is_tentative'             => 'boolean',
            'reconfirmed_at'           => 'datetime',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * Get the booking associated with this quotation.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Get the admin user who issued this quotation.
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'id');
    }

    /**
     * Get all payments that reference this quotation.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'quotation_id', 'id');
    }
}



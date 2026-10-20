<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAlert extends Model
{
    /**
     * Alert types that carry a request Admin must approve or reject. For these, is_read records
     * the decision itself, so they are never closed by "mark read" or "dismiss all".
     */
    public const DECISION_TYPES = ['physical_count_variance'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'title',
        'message',
        'booking_id',
        'inventory_item_id',
        'is_read',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /**
     * Whether this alert waits for an approve/reject decision rather than an acknowledgement.
     */
    public function requiresDecision(): bool
    {
        return in_array($this->type, self::DECISION_TYPES, true);
    }

    /**
     * Get the booking associated with this alert.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Get the inventory item associated with this alert.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'id');
    }
}

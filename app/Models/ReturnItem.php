<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnItem extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'return_items';

    /**
     * Indicates if the model has timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'return_id',
        'inventory_item_id',
        'quantity_returned',
        'quantity_good',
        'quantity_damaged',
        'quantity_lost',
        'condition',
        'final_amount',
        'damage_charge',
        'notes',
        'charge_decision',
        'charge_reason',
        'charge_decision_by',
        'charge_decision_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_returned' => 'decimal:2',
            'quantity_good' => 'decimal:2',
            'quantity_damaged' => 'decimal:2',
            'quantity_lost' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'damage_charge' => 'decimal:2',
            'charge_decision_at' => 'datetime',
        ];
    }

    /**
     * Get the total accounted quantity across good, damaged, and lost.
     */
    public function getAccountedQuantityAttribute(): float
    {
        return (float) ($this->quantity_good ?? 0)
            + (float) ($this->quantity_damaged ?? 0)
            + (float) ($this->quantity_lost ?? 0);
    }

    /**
     * Get the return associated with this return item.
     */
    public function assetReturn(): BelongsTo
    {
        return $this->belongsTo(AssetReturn::class, 'return_id', 'id');
    }

    /**
     * Get the inventory item for this return item.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'id');
    }

    /**
     * Get the evidences associated with this return item.
     */
    public function evidences(): HasMany
    {
        return $this->hasMany(ReturnItemEvidence::class, 'return_item_id', 'id');
    }

    /**
     * Get the user who made the charge decision.
     */
    public function chargeDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charge_decision_by', 'id');
    }
}

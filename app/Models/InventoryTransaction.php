<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'inventory_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inventory_item_id',
        'inventory_stock_id',
        'booking_id',
        'reference_transaction_id',
        'quantity_change',
        'transaction_type',
        'reason',
        'performed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the inventory item associated with this transaction.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'id');
    }

    /**
     * Get the booking associated with this transaction.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Get the user who performed this transaction.
     */
    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by', 'id');
    }

    /**
     * Get the original transaction referenced by this correction transaction.
     */
    public function referenceTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_transaction_id', 'id');
    }

    /**
     * Get the stock batch associated with this transaction.
     */
    public function inventoryStock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }
}

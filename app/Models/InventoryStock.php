<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'received_date',
        'quantity_received',
        'quantity_remaining',
        'usable_life_value',
        'usable_life_unit',
        'usable_until',
        'unit_cost',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'usable_until' => 'date',
            'quantity_received' => 'decimal:2',
            'quantity_remaining' => 'decimal:2',
            'usable_life_value' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'inventory_stock_id');
    }

    public function isUsableOn(Carbon|string $date): bool
    {
        $target = Carbon::parse($date)->startOfDay();
        return $this->usable_until && $this->usable_until->gte($target) && (float) $this->quantity_remaining > 0;
    }

    public function isExpired(): bool
    {
        return $this->usable_until && $this->usable_until->lt(Carbon::today());
    }
}

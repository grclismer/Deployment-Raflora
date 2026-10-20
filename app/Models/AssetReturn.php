<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetReturn extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'returns';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'assigned_staff_id',
        'return_date',
        'status',
        'total_damage_charge',
        'inspected_by',
        'approved_by',
        'approved_at',
        'approval_status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_damage_charge' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Return reference formatted as RT-00X.
     */
    public function getReferenceAttribute(): string
    {
        return 'RT-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Human-readable label for approval status.
     */
    public function getApprovalStatusDisplayLabelAttribute(): string
    {
        return match ($this->approval_status) {
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'not_required' => 'Not Required',
            default => 'Not Required',
        };
    }

    /**
     * Get the booking associated with this return.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Get the staff assigned to process this return.
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id', 'id');
    }

    /**
     * Get the inspector assigned to assess returned items.
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by', 'id');
    }

    /**
     * Get the administrator who approved damage/loss adjudication.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Get the user who inspected this return (legacy relation).
     */
    public function inspectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by', 'id');
    }

    /**
     * Get all return items for this return.
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id', 'id');
    }

    /**
     * Get all inventory items in this return (through return_items pivot).
     */
    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(
            InventoryItem::class,
            'return_items',
            'return_id',
            'inventory_item_id'
        )->withPivot(['quantity_returned', 'quantity_good', 'quantity_damaged', 'quantity_lost', 'condition', 'final_amount', 'damage_charge', 'notes']);
    }
}

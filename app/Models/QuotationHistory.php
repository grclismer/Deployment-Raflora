<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationHistory extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'quotation_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'field_changed',
        'old_value',
        'new_value',
        'reason',
        'changed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
        ];
    }

    /**
     * Get the booking associated with this history entry.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Get the user who made this change.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }
}

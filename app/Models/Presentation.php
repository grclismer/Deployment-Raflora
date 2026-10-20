<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presentation extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'presentations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'version',
        'file_path',
        'file_name',
        'sent_at',
        'sent_by',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the booking associated with this presentation.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Get the user who sent this presentation.
     */
    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by', 'id');
    }
}

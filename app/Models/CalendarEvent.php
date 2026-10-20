<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'calendar_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'event_type',
        'start_datetime',
        'end_datetime',
        'related_booking_id',
        'is_blocked',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * Get the user who owns this calendar event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the booking related to this calendar event.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'related_booking_id', 'id');
    }

    /**
     * Get all meetings associated with this calendar event.
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'calendar_event_id', 'id');
    }
}

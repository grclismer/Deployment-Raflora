<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's request to move the event (App\Services\ScheduleChangeService).
 */
class BookingScheduleRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'booking_id',
        'booking_message_id',
        'requested_date',
        'requested_start_time',
        'requested_end_time',
        'previous_date',
        'previous_start_time',
        'previous_end_time',
        'reason',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'previous_date' => 'date',
        'decided_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** "Nov 12, 2026 · 2:00 PM – 6:00 PM" */
    public function requestedLabel(): string
    {
        return self::scheduleLabel($this->requested_date, $this->requested_start_time, $this->requested_end_time);
    }

    public function previousLabel(): string
    {
        return $this->previous_date
            ? self::scheduleLabel($this->previous_date, $this->previous_start_time, $this->previous_end_time)
            : 'Not set';
    }

    public static function scheduleLabel(Carbon $date, ?string $start, ?string $end): string
    {
        $time = fn (?string $value) => $value ? Carbon::parse($value)->format('g:i A') : null;
        $start = $time($start);
        $end = $time($end);

        return $date->format('M j, Y') . ($start ? ' · ' . $start . ($end ? ' – ' . $end : '') : ($end ? ' · until ' . $end : ''));
    }
}

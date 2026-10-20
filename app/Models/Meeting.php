<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPES = [
        'online' => 'Online meeting',
        'in_person' => 'In-person meeting',
    ];

    /** Scheduled meetings occupy a one-hour slot on Raflora's calendar. */
    public const SLOT_MINUTES = 60;

    protected $table = 'meetings';

    protected $fillable = [
        'booking_id',
        'meeting_type',
        'scheduled_datetime',
        'meeting_link',
        'address',
        'agenda',
        'status',
        'requested_by',
        'scheduled_by',
        'outcome_notes',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_datetime' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by', 'id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->meeting_type] ?? ucfirst(str_replace('_', ' ', (string) $this->meeting_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_REQUESTED => 'Requested',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_SCHEDULED], true);
    }

    /**
     * Whether a booking can still hold client ↔ Raflora meetings: claimed by a client and
     * neither stopped (cancelled / declined / rejected) nor completed.
     */
    public static function bookingAllowsMeetings(Booking $booking): bool
    {
        return !is_null($booking->client_id)
            && !in_array($booking->status, ['cancelled', 'declined', 'rejected', 'completed'], true);
    }

    /**
     * Find another scheduled meeting whose one-hour slot overlaps the given start time.
     */
    public static function conflictingScheduledMeeting(\DateTimeInterface $start, ?int $ignoreMeetingId = null): ?self
    {
        $start = \Carbon\Carbon::instance($start);

        return self::query()
            ->where('status', self::STATUS_SCHEDULED)
            ->when($ignoreMeetingId, fn ($q) => $q->whereKeyNot($ignoreMeetingId))
            ->where('scheduled_datetime', '>', $start->copy()->subMinutes(self::SLOT_MINUTES))
            ->where('scheduled_datetime', '<', $start->copy()->addMinutes(self::SLOT_MINUTES))
            ->first();
    }
}

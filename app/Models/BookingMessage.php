<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingMessage extends Model
{
    protected $fillable = [
        'booking_id',
        'sender_type',
        'sender_id',
        'message',
        'visibility',
        'related_quotation_version',
        'submission_key',
        'read_at',
        'attachment_path',
        'attachment_name',
        'attachment_category',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'related_quotation_version' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}

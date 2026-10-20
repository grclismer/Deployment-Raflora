<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'event_type',
        'details',
        'old_values',
        'new_values',
        'ip_address',
        'entity_type',
        'entity_id',
    ];

    protected $casts = [
        'details' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public static function record(
        ?int $userId,
        string $action,
        string|array|null $details = null,
        string $module = 'admin_security',
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'event_type' => $action,
            'details' => is_array($details) ? $details : ['message' => $details],
            'new_values' => $newValues,
            'ip_address' => $ipAddress ?? (app()->runningInConsole() ? '127.0.0.1' : request()->ip()),
        ]);
    }
}

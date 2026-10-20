<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRecoveryCode extends Model
{
    use HasFactory;

    protected $table = 'admin_recovery_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'is_used',
        'used_at',
        'attempts',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'generated_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(int $maxAttempts = 5): bool
    {
        return ! $this->is_used && $this->attempts < $maxAttempts;
    }

    public function markUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
        ]);
    }

    /**
     * Issue a new recovery code for user, strictly invalidating any previously unused codes.
     */
    public static function issueForUser(int $userId, string $codeHash): self
    {
        self::where('user_id', $userId)
            ->where('is_used', false)
            ->update([
                'is_used' => true,
                'used_at' => now(),
            ]);

        return self::create([
            'user_id' => $userId,
            'code_hash' => $codeHash,
            'is_used' => false,
            'attempts' => 0,
            'generated_at' => now(),
        ]);
    }
}

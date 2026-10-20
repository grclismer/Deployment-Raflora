<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysisResult extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'ai_analysis_results';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'raw_gemini_response',
        'suggested_materials',
        'analyzed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'suggested_materials' => 'json',
        'analyzed_at' => 'datetime',
    ];

    /**
     * Get the booking associated with this analysis.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }
}

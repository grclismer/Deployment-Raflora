<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItemEvidence extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'return_item_evidences';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'return_item_id',
        'file_path',
        'file_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    /**
     * Get the return item associated with the evidence.
     */
    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }

    /**
     * Get the user who uploaded the evidence.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

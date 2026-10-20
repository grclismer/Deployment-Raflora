<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    protected $fillable = [
        'title',
        'event_date',
        'event_type',
        'theme',
        'is_archived',
    ];

    public function images()
    {
        return $this->hasMany(GalleryImage::class);
    }
}

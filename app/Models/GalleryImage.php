<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    protected $fillable = [
        'gallery_id',
        'image_path',
    ];

    protected static function booted(): void
    {
        static::deleting(function (GalleryImage $image) {
            if ($image->image_path && !str_starts_with($image->image_path, 'assets/')) {
                $otherReferences = static::where('id', '!=', $image->id)
                    ->where('image_path', $image->image_path)
                    ->exists();

                if (!$otherReferences && Storage::disk('public')->exists($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }
        });
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the publicly accessible URL for the image, supporting both legacy and public disk paths.
     */
    public function getUrlAttribute(): string
    {
        if (empty($this->image_path)) {
            return asset('assets/images/background.jpg');
        }

        if (str_starts_with($this->image_path, 'assets/')) {
            return asset($this->image_path);
        }

        return asset('storage/' . ltrim($this->image_path, '/'));
    }
}

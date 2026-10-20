<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            if (empty($package->package_code)) {
                $cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $package->title);
                $words = array_filter(explode(' ', $cleaned));
                
                $code = '';
                foreach ($words as $word) {
                    if (is_numeric($word)) {
                        $code .= $word;
                    } else {
                        $code .= strtoupper(substr($word, 0, 1));
                    }
                }
                
                $baseCode = substr($code, 0, 10);
                if (empty($baseCode)) {
                    $baseCode = 'PKG';
                }
                
                $finalCode = $baseCode;
                $counter = 1;
                while (self::where('package_code', $finalCode)->exists()) {
                    $finalCode = $baseCode . $counter;
                    $counter++;
                }
                
                $package->package_code = $finalCode;
            }
        });
    }

    protected $fillable = [
        'package_code',
        'title',
        'category',
        'description',
        'price',
        'included_items',
        'image_path',
        'is_active',
        'is_archived',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'included_items' => 'array',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'inventory_item_package')
            ->withPivot('quantity')
            ->withTimestamps()
            ->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
    }

    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class, 'package_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(PackageImage::class);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $firstImage = $this->images->first();
        if ($firstImage && $firstImage->image_path) {
            return asset('storage/' . $firstImage->image_path);
        }
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        return null;
    }

    public function getAllImageUrlsAttribute(): array
    {
        $urls = $this->images->pluck('image_path')->map(fn($p) => asset('storage/' . $p))->toArray();
        if (empty($urls) && $this->image_path) {
            $urls[] = asset('storage/' . $this->image_path);
        }
        return $urls;
    }
}

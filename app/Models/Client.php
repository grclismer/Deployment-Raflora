<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /**
     * Table name override.
     */
    protected $table = 'clients';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'address',
        'notes',
    ];

    /**
     * Get all bookings for this client.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id', 'id');
    }

    /**
     * Compatibility accessor for client name.
     */
    public function getNameAttribute(): string
    {
        return (string) ($this->full_name ?? '');
    }
}

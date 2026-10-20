<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('role')) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'role_updated',
                    'module' => 'user',
                    'details' => 'User role updated to ' . $user->role,
                    'entity_type' => User::class,
                    'entity_id' => $user->id,
                ]);
            }
        });
    }
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'google_id', 'email_verified_at', 'is_bootstrap',
        'first_name', 'last_name', 'username', 'address', 'mobile_number', 'role', 'profile_image'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_bootstrap' => 'boolean',
        ];
    }

    /**
     * Determine if this user is the single default Admin awaiting bootstrap completion.
     */
    public function isBootstrapAdmin(): bool
    {
        return $this->role === 'admin' && (bool) $this->is_bootstrap;
    }

    /**
     * Get the emergency recovery codes associated with this admin.
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(AdminRecoveryCode::class);
    }

    /**
     * Get all bookings handled by this user (admin/staff).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'handled_by', 'id');
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class, 'email', 'email');
    }

    /**
     * Get all calendar events for this user.
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'user_id', 'id');
    }

    /**
     * Get all inventory transactions performed by this user.
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'performed_by', 'id');
    }

    /**
     * Get all payments recorded by this user.
     */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by', 'id');
    }

    /**
     * Get all returns inspected by this user.
     */
    public function inspectedReturns(): HasMany
    {
        return $this->hasMany(AssetReturn::class, 'inspected_by', 'id');
    }

    /**
     * Get all meetings created/managed by this user.
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'user_id', 'id');
    }

    /**
     * Get all presentations sent by this user.
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class, 'sent_by', 'id');
    }

    /**
     * Get all quotations changed by this user.
     */
    public function quotationChanges(): HasMany
    {
        return $this->hasMany(QuotationHistory::class, 'changed_by', 'id');
    }

    /**
     * Get all email verification records for this user.
     */
    public function emailVerifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    /**
     * Get all trusted devices registered for this user (Admin/Staff S-01E).
     * Client records may technically exist at the model layer, but device trust
     * is only applied to Admin and Staff during login.
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }
}

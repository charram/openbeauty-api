<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'email',
    'password',
    'phone',
    'phone_verified_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ============================================================
    // USER ROLES
    // ============================================================

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_CLINIC_ADMIN = 'clinic_admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    // ============================================================
    // CASTS
    // ============================================================

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ============================================================
    // PHONE VERIFICATION
    // ============================================================

    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null
            && $this->phone_verified_at !== null;
    }

    // ============================================================
    // USER ROLE HELPERS
    // ============================================================

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isClinicAdmin(): bool
    {
        return $this->role === self::ROLE_CLINIC_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    // ============================================================
    // CLINIC MEMBERSHIP
    // ============================================================

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    // ============================================================
    // CLINIC MANAGEMENT PERMISSION
    // ============================================================

    public function canManageClinic(Clinic $clinic): bool
    {
        if (!$this->exists || !$clinic->exists) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->isClinicAdmin()) {
            return false;
        }

        return $this->clinics()
            ->whereKey($clinic->getKey())
            ->wherePivot('is_active', true)
            ->exists();
    }
}
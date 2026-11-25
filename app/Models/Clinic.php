<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Clinic extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clinic_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}
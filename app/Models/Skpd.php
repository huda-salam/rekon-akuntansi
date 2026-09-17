<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skpd extends Model
{
    protected $table = 'skpds';

    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function officials(): HasMany
    {
        return $this->hasMany(Official::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

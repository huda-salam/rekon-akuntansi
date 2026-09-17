<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterReference extends Model
{
    protected $fillable = [
        'code',
        'description',
        'type',
        'level',
        'parent_code',
        'source_year',
        'is_active',
    ];

    protected $casts = [
        'level' => 'decimal:2',
        'source_year' => 'integer',
        'is_active' => 'boolean',
    ];
}

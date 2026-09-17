<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingYear extends Model
{
    protected $fillable = ['year', 'is_active'];

    protected $casts = [
        'year' => 'integer',
        'is_active' => 'boolean',
    ];
}

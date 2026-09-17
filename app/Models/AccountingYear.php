<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingYear extends Model
{
    protected $fillable = ['year', 'is_active'];

    protected $casts = [
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }
}

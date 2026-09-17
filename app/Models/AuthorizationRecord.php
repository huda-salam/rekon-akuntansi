<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthorizationRecord extends Model
{
    protected $table = 'authorizations';

    protected $fillable = [
        'accounting_year_id',
        'skpd_id',
        'authorization_number',
        'authorization_date',
        'type',
        'description',
        'total_amount',
        'source_payload',
    ];

    protected $casts = [
        'authorization_date' => 'date',
        'total_amount' => 'decimal:2',
        'source_payload' => 'array',
    ];

    public function accountingYear(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class);
    }

    public function skpd(): BelongsTo
    {
        return $this->belongsTo(Skpd::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(AuthorizationDetail::class, 'authorization_id');
    }
}

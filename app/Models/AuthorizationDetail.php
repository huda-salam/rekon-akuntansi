<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationDetail extends Model
{
    protected $table = 'authorization_details';

    protected $fillable = [
        'authorization_id',
        'account_code',
        'account_name',
        'description',
        'quantity',
        'unit',
        'amount',
        'source_reference',
        'source_payload',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'amount' => 'decimal:2',
        'source_payload' => 'array',
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(AuthorizationRecord::class, 'authorization_id');
    }
}

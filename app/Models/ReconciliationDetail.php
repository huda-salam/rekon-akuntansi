<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationDetail extends Model
{
    protected $fillable = [
        'reconciliation_id',
        'source_type',
        'source_id',
        'match_status',
        'source_amount',
        'matched_amount',
        'difference_amount',
        'notes',
        'match_payload',
    ];

    protected $casts = [
        'source_amount' => 'decimal:2',
        'matched_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'match_payload' => 'array',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }
}

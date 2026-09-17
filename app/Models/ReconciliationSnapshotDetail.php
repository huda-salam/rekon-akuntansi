<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSnapshotDetail extends Model
{
    protected $table = 'reconciliation_snapshot_details';

    protected $fillable = [
        'snapshot_id',
        'source_type',
        'source_reference',
        'match_status',
        'source_amount',
        'matched_amount',
        'difference_amount',
        'notes',
        'detail_payload',
    ];

    protected $casts = [
        'source_amount' => 'decimal:2',
        'matched_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'detail_payload' => 'array',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSnapshot::class, 'snapshot_id');
    }
}

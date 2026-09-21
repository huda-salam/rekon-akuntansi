<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSnapshotResult extends Model
{
    protected $fillable = [
        'snapshot_id',
        'source_result_id',
        'rule_code',
        'rule_name',
        'category',
        'skpd_id',
        'month',
        'period',
        'status',
        'expected_value',
        'actual_value',
        'variance',
        'inputs',
        'lineage',
        'explanation',
        'review',
    ];

    protected $casts = [
        'expected_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'variance' => 'decimal:2',
        'inputs' => 'array',
        'lineage' => 'array',
        'review' => 'array',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSnapshot::class, 'snapshot_id');
    }
}

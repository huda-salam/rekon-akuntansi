<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationSnapshot extends Model
{
    protected $fillable = [
        'reconciliation_id',
        'reconciliation_run_id',
        'accounting_year',
        'skpd_code',
        'skpd_name',
        'month',
        'reconciliation_type',
        'sequence',
        'period_start',
        'period_end',
        'notes',
        'finalized_at',
        'snapshot_hash',
        'snapshot_payload',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'finalized_at' => 'datetime',
        'snapshot_payload' => 'array',
    ];

    public function reconciliationRun(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ReconciliationSnapshotDetail::class, 'snapshot_id');
    }
}

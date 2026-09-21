<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'accounting_year_id',
        'initiated_by',
        'month',
        'status',
        'file_count',
        'imported_count',
        'failed_count',
        'source_record_count',
        'financial_fact_count',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function year(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class, 'accounting_year_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SourceDocument::class, 'import_batch_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    protected $fillable = [
        'accounting_year_id',
        'started_by',
        'status',
        'started_at',
        'completed_at',
        'source_document_ids',
        'parameters',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'source_document_ids' => 'array',
        'parameters' => 'array',
        'summary' => 'array',
    ];

    public function year(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class, 'accounting_year_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReconciliationResult::class);
    }
}

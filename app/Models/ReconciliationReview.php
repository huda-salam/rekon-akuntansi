<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationReview extends Model
{
    protected $fillable = [
        'reconciliation_result_id',
        'reviewed_by',
        'status',
        'note',
        'evidence',
        'reviewed_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(ReconciliationResult::class, 'reconciliation_result_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

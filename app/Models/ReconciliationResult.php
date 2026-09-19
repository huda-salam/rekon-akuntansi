<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationResult extends Model
{
    protected $fillable = [
        'reconciliation_run_id','reconciliation_rule_id','source_document_id','skpd_id',
        'period','status','expected_value','actual_value','variance','inputs','lineage','explanation',
    ];

    protected $casts = [
        'expected_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'variance' => 'decimal:2',
        'inputs' => 'array',
        'lineage' => 'array',
    ];

    public function run(): BelongsTo { return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id'); }
    public function rule(): BelongsTo { return $this->belongsTo(ReconciliationRule::class, 'reconciliation_rule_id'); }
    public function sourceDocument(): BelongsTo { return $this->belongsTo(SourceDocument::class); }
    public function skpd(): BelongsTo { return $this->belongsTo(Skpd::class); }
    public function reviews(): HasMany { return $this->hasMany(ReconciliationReview::class); }
}

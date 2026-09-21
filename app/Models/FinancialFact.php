<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class FinancialFact extends Model
{
    protected $fillable = [
        'source_document_id','source_record_id','accounting_year_id','skpd_id',
        'fact_date','period','month','source_type','transaction_type','document_number',
        'account_code','metric','value','unit','dimensions','lineage',
    ];

    protected $casts = [
        'fact_date' => 'date',
        'value' => 'decimal:2',
        'dimensions' => 'array',
        'lineage' => 'array',
    ];

    /**
     * Convenience bulk-create API for normalized facts.
     *
     * Each item is still persisted through the normal model lifecycle,
     * so casts, events and mass-assignment rules remain in effect.
     */
    public static function createMany(iterable $records): Collection
    {
        return collect($records)->map(
            fn (array $attributes): self => static::create($attributes)
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $fact): void {
            if ($fact->source_type !== null && $fact->source_type !== '') {
                return;
            }

            $fact->source_type = SourceDocument::query()
                ->whereKey($fact->source_document_id)
                ->value('document_type') ?? 'unknown';
        });
    }

    public function document(): BelongsTo { return $this->belongsTo(SourceDocument::class, 'source_document_id'); }
    public function record(): BelongsTo { return $this->belongsTo(SourceRecord::class, 'source_record_id'); }
    public function year(): BelongsTo { return $this->belongsTo(AccountingYear::class, 'accounting_year_id'); }
    public function skpd(): BelongsTo { return $this->belongsTo(Skpd::class); }
}

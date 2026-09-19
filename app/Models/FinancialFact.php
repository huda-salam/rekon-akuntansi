<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function document(): BelongsTo { return $this->belongsTo(SourceDocument::class, 'source_document_id'); }
    public function record(): BelongsTo { return $this->belongsTo(SourceRecord::class, 'source_record_id'); }
    public function year(): BelongsTo { return $this->belongsTo(AccountingYear::class, 'accounting_year_id'); }
    public function skpd(): BelongsTo { return $this->belongsTo(Skpd::class); }
}

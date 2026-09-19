<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRule extends Model
{
    protected $fillable = [
        'accounting_year_id',
        'calculation_definition_id',
        'code',
        'name',
        'category',
        'scope',
        'version',
        'status',
        'expression',
        'tolerance',
        'input_metrics',
        'metadata',
    ];

    protected $casts = [
        'tolerance' => 'decimal:2',
        'input_metrics' => 'array',
        'metadata' => 'array',
    ];

    public function year(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class, 'accounting_year_id');
    }

    public function calculationDefinition(): BelongsTo
    {
        return $this->belongsTo(CalculationDefinition::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReconciliationResult::class);
    }
}

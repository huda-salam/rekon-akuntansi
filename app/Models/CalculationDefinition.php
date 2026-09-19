<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculationDefinition extends Model
{
    protected $fillable = [
        'accounting_year_id',
        'code',
        'name',
        'version',
        'status',
        'expression',
        'input_metrics',
        'metadata',
    ];

    protected $casts = [
        'input_metrics' => 'array',
        'metadata' => 'array',
    ];

    public function year(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class, 'accounting_year_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ReconciliationRule::class);
    }
}

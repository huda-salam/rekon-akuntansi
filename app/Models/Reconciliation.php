<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reconciliation extends Model
{
    protected $fillable = [
        'accounting_year_id',
        'skpd_id',
        'status',
        'period_start',
        'period_end',
        'notes',
        'finalized_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'finalized_at' => 'datetime',
    ];

    public function accountingYear(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class);
    }

    public function skpd(): BelongsTo
    {
        return $this->belongsTo(Skpd::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ReconciliationDetail::class);
    }

    public function snapshot(): ?ReconciliationSnapshot
    {
        return $this->hasOne(ReconciliationSnapshot::class)->first();
    }

    public function beritaAcara(): ?BeritaAcara
    {
        return $this->hasOne(BeritaAcara::class)->first();
    }
}

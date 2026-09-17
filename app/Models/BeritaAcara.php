<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeritaAcara extends Model
{
    protected $table = 'berita_acaras';

    protected $fillable = [
        'reconciliation_id',
        'snapshot_id',
        'number',
        'date',
        'signatory_official_name',
        'signatory_official_nip',
        'signatory_official_position',
        'document_path',
    ];

    protected $casts = ['date' => 'date'];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSnapshot::class, 'snapshot_id');
    }
}

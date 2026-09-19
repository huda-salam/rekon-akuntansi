<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceRecord extends Model
{
    protected $fillable = ['source_document_id','source_row','sheet_name','record_type','payload'];
    protected $casts = ['payload'=>'array'];

    public function document(): BelongsTo { return $this->belongsTo(SourceDocument::class, 'source_document_id'); }
    public function facts(): HasMany { return $this->hasMany(FinancialFact::class); }
}
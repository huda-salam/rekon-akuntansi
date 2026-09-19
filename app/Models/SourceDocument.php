<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceDocument extends Model
{
    protected $fillable = [
        'accounting_year_id',
        'uploaded_by',
        'original_filename',
        'document_type',
        'source_category',
        'mime_type',
        'checksum_sha256',
        'file_path',
        'file_size',
        'row_count',
        'status',
        'metadata',
        'imported_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'imported_at' => 'datetime',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(SourceRecord::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(AccountingYear::class, 'accounting_year_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

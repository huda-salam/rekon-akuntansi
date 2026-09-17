<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Official extends Model
{
    protected $table = 'officials';

    protected $fillable = [
        'skpd_id',
        'name',
        'nip',
        'position',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function skpd(): BelongsTo
    {
        return $this->belongsTo(Skpd::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineEvidenceFile extends Model
{
    protected $fillable = [
        'evidence_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(DisciplineEvidence::class, 'evidence_id');
    }
}
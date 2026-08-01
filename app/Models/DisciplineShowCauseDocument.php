<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineShowCauseDocument extends Model
{
    protected $fillable = [
        'show_cause_id',
        'document_name',
        'document_path',
        'source_evidence_file_id',
        'source_witness_document_id',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function showCauseNotice(): BelongsTo
    {
        return $this->belongsTo(DisciplineShowCauseNotice::class, 'show_cause_id');
    }
}
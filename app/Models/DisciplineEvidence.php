<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineEvidence extends Model
{
    protected $fillable = [
        'case_id',
        'evidence_type',
        'evidence_description',
        'evidence_source',
        'chain_of_custody',
        'is_original',
        'uploaded_by',
    ];

    protected $casts = [
        'is_original' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DisciplineEvidenceFile::class, 'evidence_id');
    }
}
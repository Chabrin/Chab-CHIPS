<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineOutcomeDocument extends Model
{
    protected $fillable = [
        'outcome_id',
        'document_name',
        'document_path',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(DisciplineOutcome::class, 'outcome_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineWitnessDocument extends Model
{
    protected $fillable = [
        'witness_statement_id',
        'document_name',
        'document_path',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function witnessStatement(): BelongsTo
    {
        return $this->belongsTo(DisciplineWitnessStatement::class, 'witness_statement_id');
    }
}

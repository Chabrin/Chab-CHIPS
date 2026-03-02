<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineHearingDocument extends Model
{
    protected $fillable = [
        'hearing_id',
        'document_name',
        'document_path',
        'document_type',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function hearing(): BelongsTo
    {
        return $this->belongsTo(DisciplineHearing::class, 'hearing_id');
    }
}
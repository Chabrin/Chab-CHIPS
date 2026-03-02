<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineInvestigationNote extends Model
{
    protected $fillable = [
        'case_id',
        'note_date',
        'note_text',
        'note_type',
        'is_confidential',
        'created_by',
    ];

    protected $casts = [
        'note_date' => 'date',
        'is_confidential' => 'boolean',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

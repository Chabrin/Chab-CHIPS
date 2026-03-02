<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineCaseClosure extends Model
{
    protected $fillable = [
        'case_id',
        'closure_date',
        'closure_reason',
        'closure_notes',
        'lessons_learned',
        'policy_recommendations',
        'closed_by',
    ];

    protected $casts = [
        'closure_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'closed_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

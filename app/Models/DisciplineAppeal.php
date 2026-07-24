<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineAppeal extends Model
{
    protected $fillable = [
        'case_id',
        'outcome_id',
        'filed_by',
        'filed_at',
        'grounds',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'decision_action',
    ];

    protected $casts = [
        'filed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(DisciplineOutcome::class, 'outcome_id');
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

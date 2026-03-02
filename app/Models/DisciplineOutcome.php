<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineOutcome extends Model
{
    protected $fillable = [
        'case_id',
        'decision_date',
        'decision',
        'action_taken_id',
        'findings',
        'reasoning',
        'sanctions',
        'effective_date',
        'suspension_days',
        'appeal_allowed',
        'appeal_deadline',
        'decided_by',
        'approved_by',
    ];

    protected $casts = [
        'decision_date' => 'date',
        'effective_date' => 'date',
        'suspension_days' => 'integer',
        'appeal_allowed' => 'boolean',
        'appeal_deadline' => 'date',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function actionTaken(): BelongsTo
    {
        return $this->belongsTo(ActionTaken::class, 'action_taken_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'decided_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisciplineOutcomeDocument::class, 'outcome_id');
    }
}
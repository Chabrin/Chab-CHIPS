<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineHearingMinutes extends Model
{
    protected $fillable = [
        'hearing_id',
        'attendees',
        'start_time',
        'end_time',
        'proceedings_summary',
        'employee_statement',
        'panel_questions',
        'panel_recommendation',
        'minute_taker_id',
        'approved_by',
        'approved_at',
        'signature_attestation',
    ];

    protected $casts = [
        'attendees' => 'array',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;

    public function hearing(): BelongsTo
    {
        return $this->belongsTo(DisciplineHearing::class, 'hearing_id');
    }

    public function minuteTaker(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'minute_taker_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasOne
    {
        return $this->hasOne(DisciplineHearingDocument::class, 'hearing_id');
    }
}
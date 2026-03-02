<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineHearing extends Model
{
    protected $fillable = [
        'case_id',
        'hearing_date',
        'hearing_location',
        'hearing_type',
        'chairperson_id',
        'panel_members',
        'employee_attended',
        'employee_representative',
        'scheduled_by',
        'rescheduled_from',
        'reschedule_reason',
    ];

    protected $casts = [
        'hearing_date' => 'datetime',
        'panel_members' => 'array',
        'employee_attended' => 'boolean',
        'scheduled_at' => 'datetime',
        'rescheduled_from' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'scheduled_at';

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class, 'case_id');
    }

    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'chairperson_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(DisciplineHearingMinutes::class, 'hearing_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisciplineHearingDocument::class, 'hearing_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DisciplineCase extends Model
{
    protected $fillable = [
        'case_number',
        'staff_id',
        'position_id',
        'current_step',
        'current_status_id',
        'severity_level',
        'is_anonymous',
        'created_by',
        'date_time',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'is_anonymous' => 'boolean',
        'date_time' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(DisciplineIncidentReport::class, 'case_id');
    }

    public function registration(): HasOne
    {
        return $this->hasOne(DisciplineCaseRegistration::class, 'case_id');
    }

    public function investigators(): HasMany
    {
        return $this->hasMany(DisciplineInvestigator::class, 'case_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DisciplineEvidence::class, 'case_id');
    }

    public function witnessStatements(): HasMany
    {
        return $this->hasMany(DisciplineWitnessStatement::class, 'case_id');
    }

    public function investigationNotes(): HasMany
    {
        return $this->hasMany(DisciplineInvestigationNote::class, 'case_id');
    }

    public function showCauseNotice(): HasOne
    {
        return $this->hasOne(DisciplineShowCauseNotice::class, 'case_id');
    }

    public function employeeResponse(): HasOne
    {
        return $this->hasOne(DisciplineEmployeeResponse::class, 'case_id');
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(DisciplineHearing::class, 'case_id');
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(DisciplineOutcome::class, 'case_id');
    }

    public function trainingAssignments(): HasMany
    {
        return $this->hasMany(DisciplineTrainingAssignment::class, 'case_id');
    }

    public function historyUpdates(): HasMany
    {
        return $this->hasMany(DisciplineHistoryUpdate::class, 'case_id');
    }

    public function closure(): HasOne
    {
        return $this->hasOne(DisciplineCaseClosure::class, 'case_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(DisciplineActivityLog::class, 'case_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DisciplineNotification::class, 'case_id');
    }

    public function deadlineExtensions()
    {
        return $this->hasMany(DisciplineDeadlineExtension::class, 'case_id');
    }
}
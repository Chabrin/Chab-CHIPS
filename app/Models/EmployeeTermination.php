<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmployeeTermination extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'hr_employment_termination';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'staff_id',
        'discipline_case_id',
        'discipline_outcome_id',
        'contract_id',
        'years_served',
        'pay_per_year',
        'severerance_payment',
        'termination_reason_id',
        'documents',
        'current_status_id',
        'created_by_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'incident_date_time' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function elements()
    {
        return $this->hasMany(EmployeeTerminationElement::class, 'parent_id', 'id');
    }

    public function documentTrackers()
    {
        return $this->hasMany(EmployeeTerminationDocumentTracker::class, 'parent_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function disciplineCase()
    {
        return $this->belongsTo(DisciplineCase::class, 'discipline_case_id');
    }

    public function disciplineOutcome()
    {
        return $this->belongsTo(DisciplineOutcome::class, 'discipline_outcome_id');
    }

    public function exitInterview()
    {
        return $this->hasOne(ExitInterview::class, 'termination_id');
    }

    public function finalDues()
    {
        return $this->hasOne(FinalDues::class, 'termination_id');
    }

}

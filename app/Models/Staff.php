<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use App\Support\HireDate;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_staff';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'photo',
        'full_names',
        'employment_type',
        'gender',
        'address',
        'mobile_number',
        'email_address',
        'birth_date',
        'national_id',
        'passport',
        'bank_name',
        'pin_number',
        'hire_date',
        'department_id',
        'position_id',
        'username',
        'system_password',
        'uid',
        'permission_group_id',
        'current_status_id',
        'branch_id',
        'account_name',
        'account_number',
        'zone_id',
        'zone_area_id',
        'kins_names',
        'kins_relationship',
        'staff_number',
        'district',
        'location',
        'sub_location',
        'marital',
        'birth_place',
        'nhif_number',
        'nssf_number',
        'spouse_name',
        'citizenship',
        'personal_email',
        'disability',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'date_time' => 'datetime',
    ];

    /** Canonical employment status labels for current_status_id. */
    public const STATUSES = [1 => 'Active', 2 => 'Suspended', 3 => 'Terminated', 4 => 'On Leave'];

    /** Bootstrap badge classes matching STATUSES, keyed the same way. */
    public const STATUS_BADGE_CLASSES = [1 => 'badge-light-success', 2 => 'badge-light-warning', 3 => 'badge-light-danger', 4 => 'badge-light-info'];

    /** How the next of kin is related to the staff member, for kins_relationship. */
    public const KIN_RELATIONSHIPS = ['Spouse', 'Parent', 'Child', 'Sibling', 'Guardian', 'Relative', 'Friend', 'Other'];

    /**
     * The hire date as a date, whatever format it was stored in (a plain datetime cast threw on day-first dates).
     */
    public function getHireDateAttribute($value) {
        return HireDate::parse($value);
    }

    // Relationships
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_name');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function zoneArea()
    {
        return $this->belongsTo(ZoneArea::class, 'zone_area_id');
    }

    // Zones this staff member manages/supervises (inverse of Zone::zoneManager()/zoneSupervisor()).
    public function zonesManaged()
    {
        return $this->hasMany(Zone::class, 'zone_manager_id');
    }

    public function zonesSupervised()
    {
        return $this->hasMany(Zone::class, 'zone_supervisor_id');
    }

    public function zone_assignments()
    {
        return $this->hasMany(ZoneAssignment::class, 'staff_id');
    }

    public function leave_calendar_plans()
    {
        return $this->hasMany(LeaveCalendarPlan::class, 'employee_id');
    }

    public function contract()
    {
        return $this->hasOne(Contract::class, 'staff_id');
    }

    public function employee_attendances()
{
    return $this->hasMany(EmployeeAttendance::class, 'employee_id', 'id');
}

    // Discipline Tracker Relationships
    
    /**
     * Discipline cases where this staff is the subject
     */
    public function disciplineCases()
    {
        return $this->hasMany(DisciplineCase::class, 'staff_id');
    }

    /**
     * Incident reports submitted by this staff member
     */
    public function incidentReports()
    {
        return $this->hasMany(DisciplineIncidentReport::class, 'reported_by');
    }

    /**
     * Investigations assigned to this staff member as investigator
     */
    public function investigationsAssigned()
    {
        return $this->hasMany(DisciplineInvestigator::class, 'investigator_id');
    }

    /**
     * Investigations reassigned to this staff member
     */
    public function investigationsReassigned()
    {
        return $this->hasMany(DisciplineInvestigator::class, 'reassigned_to');
    }

    /**
     * Witness statements where this staff is the witness
     */
    public function witnessStatements()
    {
        return $this->hasMany(DisciplineWitnessStatement::class, 'witness_staff_id');
    }

    public function userLink()
    {
        return $this->hasOne(UserStaffLink::class);
    }

    public function employeeDocuments()
{
    return $this->hasMany(\App\Models\EmployeeDocument::class, 'employee_id');
}
public static function nextStaffNumber(string $employmentType = 'Permanent'): string
{
    $prefixMap = [
        'Permanent' => '',
        'Temporary' => 'T',
        'Casual'    => 'C',
        'Intern'    => 'I',
    ];

    $prefix = $prefixMap[$employmentType] ?? '';

    // Get the highest numeric part for this category
    $max = static::query()
        ->where('employment_type', $employmentType)
        ->selectRaw("MAX(NULLIF(regexp_replace(staff_number, '\\D', '', 'g'), '')::bigint) as max_no")
        ->value('max_no');

    $nextNo = ((int) $max) + 1;

    // Permanent → plain number
    if ($prefix === '') {
        return (string) $nextNo;
    }

    // Others → PREFIX-001 (3 digits with leading zeros)
    return sprintf('%s-%03d', $prefix, $nextNo);
}
}

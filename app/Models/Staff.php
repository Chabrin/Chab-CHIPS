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
        'data_notice_agreed_at',
        'data_notice_recorded_by',
        'is_helb_beneficiary',
        'helb_certificate_exception_reason',
        'helb_certificate_exception_by',
        'helb_certificate_exception_at',
        'nationality',
        'residency_status',
        'work_permit_number',
        'work_permit_expiry',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'date_time' => 'datetime',
        'data_notice_agreed_at' => 'datetime',
        'is_helb_beneficiary' => 'boolean',
        'helb_certificate_exception_at' => 'datetime',
        'work_permit_expiry' => 'date',
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
/** The number in a staff number ("CT-007" is 7, "281" is 281). */
public static function numberPart(?string $staffNumber): int
{
    return (int) preg_replace('/\D/', '', (string) $staffNumber);
}

/** A staff number as the category writes it: plain for categories with no prefix, otherwise PREFIX-001. */
public static function formatStaffNumber(string $employmentType, int $number): string
{
    // The prefix is set per category under HR Settings > Employment Categories; a code with no category gets plain numbers
    $prefix = EmploymentCategory::prefixFor($employmentType);

    return $prefix === '' ? (string) $number : sprintf('%s-%03d', $prefix, $number);
}

/** The highest number any staff member holds in this category (0 when there are none). Numbers held by drafts are not counted. */
public static function highestStaffNumber(string $employmentType): int
{
    if (static::query()->getConnection()->getDriverName() === 'pgsql') {
        $max = static::query()
            ->where('employment_type', $employmentType)
            ->selectRaw("MAX(NULLIF(regexp_replace(staff_number, '\\D', '', 'g'), '')::bigint) as max_no")
            ->value('max_no');
    } else {
        // The same answer on other databases (the tests run on SQLite), which have no regexp_replace
        $max = static::query()->where('employment_type', $employmentType)->pluck('staff_number')
            ->map(fn ($number) => static::numberPart($number))->max();
    }

    return (int) $max;
}

/**
 * The next free staff number in the category: one more than the highest held by any staff member or by any part-finished record
 * (draft), so a number held for a draft is not offered to anyone else.
 */
public static function nextStaffNumber(string $employmentType = 'Permanent'): string
{
    $highest = max(static::highestStaffNumber($employmentType), \App\Services\StaffNumbers::highestReserved($employmentType));

    return static::formatStaffNumber($employmentType, $highest + 1);
}
}

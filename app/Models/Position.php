<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_set_staff_positions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'department_id',
        'date_time',
        'position_name',
        'slug_name',
        'basic_salary',
        'salary_account_id',
        'current_status_id',
        'reports_to_id',
        'is_active',
        'role_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The system role that goes with this position (made when the position was created).
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Positions that are switched on, so can be chosen for new staff and contracts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Where this position is used, in words, for the message when it cannot be deleted. Empty when nothing uses it.
     * Staff and the records kept about them (contracts, payroll, leave, discipline) are what a position is deleted from,
     * so they all count.
     *
     * @return string[]
     */
    public function usageNotes(): array
    {
        $notes = [];
        $add = function (int $count, string $one, string $many) use (&$notes) {
            if ($count > 0) {
                $notes[] = $count === 1 ? $one : str_replace(':n', (string) $count, $many);
            }
        };

        $add(Staff::where('position_id', $this->id)->count(), '1 staff member holds it', ':n staff members hold it');
        $add(static::where('reports_to_id', $this->id)->count(), '1 other position reports to it', ':n other positions report to it');
        $add(Contract::where('position_id', $this->id)->count(), 'it is on 1 contract', 'it is on :n contracts');
        $add(PayrollSummary::where('position_id', $this->id)->count(), 'it is on 1 payroll record', 'it is on :n payroll records');
        $add(LeaveRequest::where('position_id', $this->id)->count(), 'it is on 1 leave request', 'it is on :n leave requests');
        $add(DisciplineCase::where('position_id', $this->id)->count(), 'it is on 1 discipline case', 'it is on :n discipline cases');
        $add(PayrollElementPosition::where('staff_positions_id', $this->id)->count(), 'a payroll element is set up for it', 'payroll elements are set up for it (:n)');
        $add(StaffOnboardingInvitation::where('position_id', $this->id)->count(), 'a staff onboarding names it', ':n staff onboardings name it');

        return $notes;
    }

    // Relationship to supervisor position
    public function reportsTo()
    {
        return $this->belongsTo(Position::class, 'reports_to_id');
    }

    // Relationship to staff members in this position
    public function staff()
    {
        return $this->hasMany(Staff::class, 'position_id');
    }

    // Relationship to payroll elements through mapping table
    public function payrollElements()
    {
        return $this->belongsToMany(
            PayrollElement::class,
            'sys_hr_payroll_elements_repeat_staff_positions_id',
            'staff_positions_id',
            'parent_id'
        );
    }

    // Relationship to account
    public function salaryAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'salary_account_id');
    }
    public function department()
{
    return $this->belongsTo(Department::class, 'department_id');
}
}

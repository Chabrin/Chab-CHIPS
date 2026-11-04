<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayrollSummary extends Model
{
    use HasFactory;
    protected $table = 'sys_hr_payroll_summary';
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'staff_number_id',
        'staff_id',
        'period',
        'basic_salary',
        'salary_arrears',
        'acting_allowance',
        'tax_payable',
        'nhif',
        'nssf',
        'nssf_tier1',
        'nssf_tier2',
        'social_welfare',
        'akiba_sacco_loan',
        'salary_advance',
        'absenteeism',
        'security_deposit',
        'helb_loans',
        'housing_levy',
        'other_deductions',
        'total_deductions',
        'total_earnings',
        'net_pay',
        'akiba_share_contribution',
        'current_status_id',
        'register_id',
        'prorated_deduction',
        'unpaid_leave',
        'department_id',
        'position_id',
        'worked_days',
        'guarantor_loan',
        'emmergency_loan',
        'period_id',
        'negligence',
        'payslip_elements',
        'processed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'date_time' => 'datetime',
        'period' => 'datetime',
        'basic_salary' => 'decimal:2',
        'salary_arrears' => 'decimal:2',
        'acting_allowance' => 'decimal:2',
        'tax_payable' => 'decimal:2',
        'nhif' => 'decimal:2',
        'nssf' => 'decimal:2',
        'nssf_tier1' => 'decimal:2',
        'nssf_tier2' => 'decimal:2',
        'social_welfare' => 'decimal:2',
        'akiba_sacco_loan' => 'decimal:2',
        'salary_advance' => 'decimal:2',
        'absenteeism' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'helb_loans' => 'decimal:2',
        'housing_levy' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'akiba_share_contribution' => 'decimal:2',
        'prorated_deduction' => 'decimal:2',
        'unpaid_leave' => 'decimal:2',
        'guarantor_loan' => 'decimal:2',
        'emmergency_loan' => 'decimal:2',
        'negligence' => 'decimal:2',
        'payslip_elements' => 'array',
    ];

    /**
     * Get the staff that owns the payroll summary
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the position
     */
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Get the status
     */
    public function status()
    {
        return $this->belongsTo(Status::class, 'current_status_id');
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }
}

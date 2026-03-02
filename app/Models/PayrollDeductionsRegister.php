<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollDeductionsRegister extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_payroll_deductions_register';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'payroll_period',
        'staff_id',
        'deduction_type_id',
        'amount',
        'company_amount',
        'total_amount',
        'salary',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'payroll_period' => 'datetime',
        'amount' => 'decimal:2',
        'company_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'salary' => 'decimal:2',
    ];

    // Relationships
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function deductionType()
    {
        return $this->belongsTo(PayrollElement::class, 'deduction_type_id');
    }

    // Scopes
    public function scopeForPeriod($query, $periodDate)
    {
        return $query->where('payroll_period', $periodDate);
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }
}

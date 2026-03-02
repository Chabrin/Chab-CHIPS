<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ProratedDeduction extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employee_amortized_deductions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'staff_id',
        'principal_amount',
        'interest_rate',
        'deduction_date',
        'created_by_id',
        'current_status_id',
        'monthly_deduction',
        'total_amount',
        'interest_amount',
        'repayment_months',
        'deduction_type_id',
        'element_id',
        'issued_date',
        'initial_deduction',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'deduction_date' => 'datetime',
        'issued_date' => 'datetime',
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_deduction' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'initial_deduction' => 'decimal:2',
    ];

    // Relationships
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function element()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }

    public function repeats()
    {
        return $this->hasMany(ProratedDeductionRepeat::class, 'parent_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('current_status_id', 1);
    }
}

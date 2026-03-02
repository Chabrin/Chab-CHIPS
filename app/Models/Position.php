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
        'date_time',
        'position_name',
        'basic_salary',
        'salary_account_id',
        'current_status_id',
        'reports_to_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'basic_salary' => 'decimal:2',
    ];

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
}

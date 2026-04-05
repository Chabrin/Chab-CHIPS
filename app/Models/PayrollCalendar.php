<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollCalendar extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_payroll_calendar';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'created_by',
        'current_status_id',
        'date_time',
        'payroll_month_date',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periods()
    {
        return $this->hasMany(PayrollPeriod::class, 'id', 'calendar_id');
    }

    // Helper to get formatted month
    public function getMonthFormattedAttribute()
    {
        if ($this->payroll_month_date) {
            $year = substr($this->payroll_month_date, 0, 4);
            $month = substr($this->payroll_month_date, 4, 2);
            $monthName = date('F', mktime(0, 0, 0, (int)$month, 1));
            return $monthName . ' ' . $year;
        }
        return '-';
    }
}

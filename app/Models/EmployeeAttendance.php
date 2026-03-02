<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employees_attendance';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'employee_id',
        'created_by_id',
        'month_year',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function staff()
    {
        return $this->hasOne(Staff::class, 'staff_number', 'employee_id');
    }

    public function employee_attendance_timed()
    {
        return $this->hasMany(EmployeeAttendanceTimed::class, 'parent_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }


}

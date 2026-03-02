<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceTimed extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employees_attendance_255_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'date',
        'time_in',
        'time_out',
        'hours',
        'current_status_id',
    ];

    //relationships
    public function employee_attendance()
    {
        return $this->hasMany(EmployeeAttendance::class, 'parent_id');
    }


}

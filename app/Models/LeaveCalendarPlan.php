<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class LeaveCalendarPlan extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_leave_calendar';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'employee_id',
        'created_by',
        'start_date',
        'end_date',
        'entitled_days',
        'current_status_id',
        'leave_type_id',
        'balance',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'entitled_days' => 'float',
        'balance' => 'float',
    ];

    //relationships
    public function employee()
    {
        return $this->belongsTo(Staff::class, 'employee_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function slots()
    {
        return $this->hasMany(LeaveCalendarSlot::class, 'leave_calendar_plan_id');
    }

}

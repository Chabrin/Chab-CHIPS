<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestDaysTaken extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_leave_requests_234_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'current_status_id',
        'leave_date',
        'day_type_id',
    ];

    protected $casts = [
        'leave_date' => 'datetime',
    ];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'parent_id');
    }
    
    public function dayType()
    {
        return $this->belongsTo(WorkDay::class, 'day_type_id', 'work_type_id');
    }
    
    // Helper method to get day type name
    public function getDayTypeName()
    {
        $types = [1 => 'Weekday', 2 => 'Saturday', 3 => 'Sunday'];
        return $types[$this->day_type_id] ?? 'Unknown';
    }

}

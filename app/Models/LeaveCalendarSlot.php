<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveCalendarSlot extends Model
{
    protected $table = 'sys_hr_leave_calendar_slots';

    protected $fillable = [
        'leave_calendar_plan_id',
        'start_date',
        'end_date',
        'days_count',
        'notes',
        'current_status_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_count' => 'float',
    ];

    public function plan()
    {
        return $this->belongsTo(LeaveCalendarPlan::class, 'leave_calendar_plan_id');
    }
}
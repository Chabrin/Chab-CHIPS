<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_leave_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'type_name',
        'current_status_id',
        'entitled_days',
        'salary_rate',
        'leave_type_id',
        'counts_weekends',
        'counts_public_holidays',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'counts_weekends' => 'boolean',
        'counts_public_holidays' => 'boolean',
    ];
}
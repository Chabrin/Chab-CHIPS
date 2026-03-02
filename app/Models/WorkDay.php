<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class WorkDay extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_working_days';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'week_day',
        'work_type_id',
        'current_status_id',
        'created_by',
        'date_time',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}

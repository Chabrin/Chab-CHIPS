<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/** The supervisor HR named for a staff member, kept so every later cycle starts with it filled in. */
class PerformanceSupervisorAssignment extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_supervisor_assignments';

    protected $fillable = ['staff_id', 'supervisor_staff_id', 'set_by'];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Staff::class, 'supervisor_staff_id');
    }
}

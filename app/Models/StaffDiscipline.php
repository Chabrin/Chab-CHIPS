<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class StaffDiscipline extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_staff_discipline_tracker';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'position_id',
        'staff_id',
        'incident_date_time',
        'incident_id',
        'action_taken_id',
        'description',
        'documents',
        'created_by',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'incident_date_time' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function incident()
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function actionTaken()
    {
        return $this->belongsTo(ActionTaken::class, 'action_taken_id');
    }

    public function documentTrackers()
    {
        return $this->hasMany(StaffDisciplineDocumentTracker::class, 'parent_id');
    }

}

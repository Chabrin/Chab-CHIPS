<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ZoneAssignment extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_pm_zones_assignments';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'created_by',
        'current_status_id',
        'date_time',
        'fo_ids',
        'property_id',
        'zone_id',
        'assigned_as',
        'staff_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function zone_assignment_zone()
    {
        return $this->hasOne(ZoneAssignmentZone::class, 'parent_id', 'id');
    }

    public function zone()
    {
        return $this->hasOneThrough(
            Zone::class,
            ZoneAssignmentZone::class,
            'parent_id', // Foreign key on ZoneAssignmentZone table...
            'id',        // Foreign key on Zone table...
            'id',        // Local key on ZoneAssignment table...
            'zone_id'    // Local key on ZoneAssignmentZone table...
        );
    }

    public function assignment_type()
    {
        return $this->belongsTo(ZoneAssignmentType::class, 'assigned_as');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class, 'assigned_as');
    }
}

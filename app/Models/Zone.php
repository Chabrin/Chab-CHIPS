<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_set_zones';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'estates_id',
        'current_status_id',
        'date_time',
        'zone_name',
        'zone_manager_id',
        'zone_supervisor_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function zone_assignments()
    {
        return $this->hasMany(ZoneAssignment::class, 'zone_id');
    }

    public function zone_areas()
    {
        return $this->hasMany(ZoneArea::class, 'zone_id');
    }

    public function zoneManager()
    {
        return $this->belongsTo(Staff::class, 'zone_manager_id');
    }

    public function zoneSupervisor()
    {
        return $this->belongsTo(Staff::class, 'zone_supervisor_id');
    }

    public function fieldOfficers()
    {
        return $this->hasMany(Staff::class, 'zone_id');
    }

}

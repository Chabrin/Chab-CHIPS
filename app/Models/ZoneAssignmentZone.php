<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ZoneAssignmentZone extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_pm_zones_assignments_repeat_zone_id';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'zone_id',
    ];


    //relationships
    public function zone_assignments()
    {
        return $this->hasMany(ZoneAssignment::class, 'zone_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function parent_assignment()
    {
        return $this->belongsTo(ZoneAssignment::class, 'parent_id');
    }


}

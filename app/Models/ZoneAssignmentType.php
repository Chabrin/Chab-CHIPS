<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ZoneAssignmentType extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_pm_zones_assignments_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'type_name',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function zone_assignments()
    {
        return $this->hasMany(ZoneAssignment::class, 'assigned_as');
    }

}

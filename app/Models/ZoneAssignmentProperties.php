<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ZoneAssignmentProperties extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_pm_properties';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        
        'parent_id',
        'properties_ids',
        'params',
    ];


    //relationships
    public function property()
    {
        return $this->hasMany(Property::class, 'properties_ids');
    }

    public function parent_assignment()
    {
        return $this->belongsTo(ZoneAssignment::class, 'parent_id');
    }

}

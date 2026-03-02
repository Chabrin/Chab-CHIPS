<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ZoneArea extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_set_zone_areas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'zone_id',
        'date_time',
        'area_name',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    //relationships
    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

}

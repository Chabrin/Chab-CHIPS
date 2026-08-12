<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitTopology extends Model
{
    protected $table = 'sys_pm_unit_topologies';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'topology_name',
    ];
}

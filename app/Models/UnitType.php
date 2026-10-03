<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitType extends Model
{
    protected $table = 'sys_pm_unit_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'type_name',
    ];
}

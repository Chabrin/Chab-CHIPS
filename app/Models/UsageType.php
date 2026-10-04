<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageType extends Model
{
    protected $table = 'sys_pm_usage_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'type_name',
    ];
}

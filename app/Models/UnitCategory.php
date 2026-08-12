<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitCategory extends Model
{
    protected $table = 'sys_pm_unit_categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'category_name',
    ];
}

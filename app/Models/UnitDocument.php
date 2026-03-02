<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitDocument extends Model
{
    protected $table = 'sys_pm_units_repeat_unit_uploads';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'unit_uploads',
        'params',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairType extends Model
{
    protected $table = 'sys_pm_properties_repair_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'element_name',
        'current_status_id',
        'element_id',
        'created_by_id',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function propertyElement()
    {
        return $this->belongsTo(PropertyElement::class, 'element_id');
    }

}

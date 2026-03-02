<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairRequestElement extends Model
{
    protected $table = 'sys_pm_properties_repair_types_repeat_element_id';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'element_id',
        'params',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'parent_id');
    }

    public function propertyElement()
    {
        return $this->belongsTo(PropertyElement::class, 'element_id');
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairRequestItem extends Model
{
    protected $table = 'sys_pm_properties_repairs_requests_49_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'unit_id',
        'property_element_id',
        'repair_type_id',
        'amount',
        'remarks',
        'photos_documents',
        'current_status_id',
        'status_date',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'parent_id');
    }

    public function repairType()
    {
        return $this->belongsTo(RepairType::class, 'repair_type_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function propertyElement()
    {
        return $this->belongsTo(PropertyElement::class, 'property_element_id');
    }
}

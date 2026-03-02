<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionPicture extends Model
{
    protected $table = 'sys_pm_properties_inspections_39_repeat_repeat_unit_photos';

    protected $fillable = [
        'parent_id',
        'unit_photos',
        'params',
    ];

    public function inspectionUnit()
    {
        return $this->belongsTo(InspectionUnit::class, 'parent_id');
    }
}

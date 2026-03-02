<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionUnit extends Model
{
    protected $table = 'sys_pm_properties_inspections_39_repeat';

    protected $fillable = [
        'parent_id',
        'unit_id',
        'unit_status_id',
        'remarks',
        'unit_photos',
        'previous_status_id',
        'current_status_id',
        'event_date',
    ];

    public function inspection()
    {
        return $this->belongsTo(Inspection::class, 'parent_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function pictures()
    {
        return $this->hasMany(InspectionPicture::class, 'parent_id');
    }
}

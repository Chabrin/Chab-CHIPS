<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $table = 'sys_pm_properties_inspections';

    protected $fillable = [
        'date_time',
        'visited_date_time',
        'left_date_time',
        'created_by_id',
        'zone_id',
        'property_id',
        'client_id',
        'duration',
        'user_location',
        'current_status_id',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function units()
    {
        return $this->hasMany(InspectionUnit::class, 'parent_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

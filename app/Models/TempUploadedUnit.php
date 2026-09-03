<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempUploadedUnit extends Model
{
    protected $table = 'temp_uploaded_units';

    protected $fillable = [
        'property_id',
        'unit_number',
        'unit_name',
        'usage_type',
        'unit_condition',
        'topology',
        'category',
        'rent_amount',
        'description',
        'vat_able',
        'initial_water_meter_reading',
        'pushed_to_main',
        'push_error',
        'created_by',
    ];

    protected $casts = [
        'pushed_to_main' => 'boolean',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TempBatchUnit extends Model
{
    use SoftDeletes;

    protected $table = 'temp_batch_units';

    protected $fillable = [
        'batch_id',
        'row_no',
        'unit_number',
        'unit_name',
        'unit_type',
        'usage_type',
        'topology',
        'rent_amount',
        'vat_able',
        'initial_water_reading',
        'unit_condition',
        'unit_category',
        'unit_description',
        'error',
        'submitted_at',
        'unit_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];
}

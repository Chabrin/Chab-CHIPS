<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempGeneratedInvoice extends Model
{
    protected $table = 'temp_generated_invoices';

    protected $fillable = [
        'property_id',
        'lease_id',
        'tenant_id',
        'unit_id',
        'tenant_name',
        'unit_code',
        'base_rent',
        'prorated_amount',
        'proration_days',
        'month_days',
        'returns_period',
        'date_due',
        'pushed_to_main',
        'push_error',
        'created_by',
    ];

    protected $casts = [
        'pushed_to_main' => 'boolean',
        // Calendar dates must reach the staging table without a UTC conversion.
        'returns_period' => 'date:Y-m-d',
        'date_due' => 'date:Y-m-d',
    ];
}

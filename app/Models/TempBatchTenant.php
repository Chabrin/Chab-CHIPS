<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TempBatchTenant extends Model
{
    use SoftDeletes;

    protected $table = 'temp_batch_tenants';

    protected $fillable = [
        'batch_id',
        'row_no',
        'sheet',
        'unit_number',
        'tenant_type',
        'lease_start_date',
        'mobile_no',
        'names',
        'company_name',
        'registration_number',
        'national_id',
        'passport_no',
        'gender',
        'title',
        'email',
        'kra_pin',
        'nationality',
        'other_contact_name',
        'other_contact_phone',
        'other_contact_email',
        'contact_name',
        'contact_phone',
        'contact_email',
        'nok_name',
        'nok_phone',
        'nok_email',
        'nok_id',
        'nok_relationship',
        'error',
        'submitted_at',
        'tenant_id',
        'lease_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempUploadedTenant extends Model
{
    protected $table = 'temp_uploaded_tenants';

    protected $fillable = [
        'property_id',
        'unit_code',
        'title',
        'names',
        'gender',
        'mobile_number',
        'email_address',
        'pin_number',
        'nationality',
        'national_id',
        'passport_number',
        'other_contact_name',
        'other_contact_email',
        'other_contact_phone',
        'next_of_kin_name',
        'next_of_kin_mobile',
        'next_of_kin_email',
        'next_of_kin_national_id',
        'next_of_kin_relationship',
        'pushed_to_main',
        'push_error',
        'created_by',
    ];

    protected $casts = [
        'pushed_to_main' => 'boolean',
    ];
}

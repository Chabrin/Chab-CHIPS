<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantNextOfKin extends Model
{
    protected $table = 'tenants_nextof_kin';

    protected $fillable = [
        'tenant_id',
        'kin_names',
        'mobile_number',
        'email_address',
        'relationship',
        'national_id',
        'second_name',
    ];

    public function tenant()
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }
}

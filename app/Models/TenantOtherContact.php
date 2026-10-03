<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantOtherContact extends Model
{
    protected $table = 'tenants_other_contacts';

    protected $fillable = [
        'tenant_id',
        'title',
        'names',
        'email',
        'mobile_number',
    ];

    public function tenant()
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }
}

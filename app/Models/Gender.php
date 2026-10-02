<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    protected $table = 'cb_genders';

    protected $fillable = [
        'name',
    ];

    public function tenantRecords()
    {
        return $this->hasMany(TenantRecord::class);
    }
}

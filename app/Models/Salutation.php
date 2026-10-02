<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salutation extends Model
{
    protected $table = 'cb_salutations';

    protected $fillable = [
        'name',
    ];

    public function tenantRecords()
    {
        return $this->hasMany(TenantRecord::class);
    }
}

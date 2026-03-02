<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBlacklistReason extends Model
{
    protected $table = 'sys_pm_tenants_blacklist_reasons';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'reason_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function blacklists()
    {
        return $this->hasMany(TenantBlacklist::class, 'reason_id');
    }
}

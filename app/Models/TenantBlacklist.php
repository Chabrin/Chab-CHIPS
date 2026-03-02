<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBlacklist extends Model
{
    protected $table = 'sys_pm_tenants_blacklist';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'date_time',
        'blacklist_date',
        'zone_id',
        'client_id',
        'property_id',
        'unit_id',
        'lease_id',
        'tenant_id',
        'reason_id',
        'description',
        'created_by',
        'current_balance',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    public function reason()
    {
        return $this->belongsTo(TenantBlacklistReason::class, 'reason_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}

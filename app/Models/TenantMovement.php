<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantMovement extends Model
{
    protected $table = 'sys_pm_movement_requests';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'date_time',
        'movement_date',
        'created_by',
        'zone_id',
        'client_id',
        'property_id',
        'unit_from_id',
        'lease_id',
        'unit_to_id',
        'tenant_id',
        'current_status_id',
        'current_balance',
        'reference_number',
        'new_lease_start_date',
        'new_lease_end_date',
        'sla_id',
        'new_lease_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(TenantMovementItem::class, 'parent_id', 'id');
    }

    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unitFrom()
    {
        return $this->belongsTo(Unit::class, 'unit_from_id');
    }

    public function unitTo()
    {
        return $this->belongsTo(Unit::class, 'unit_to_id');
    }

    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    public function newLease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'new_lease_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function sla()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

}

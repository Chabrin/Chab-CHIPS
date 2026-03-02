<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseAgreement extends Model
{
    protected $table = 'sys_pm_lease_agreements';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'client_id',
        'property_id',
        'unit_id',
        'tenant_id',
        'date_start',
        'date_end',
        'lease_status',
        'ref_number',
        'created_by_id',
        'sla_id',
        'zone_id',
        'current_status_id',
        'auto_renewal',
        'years',
        'initial_water_meter_reading',
        'escalation_rate',
        'escalation_frequency',
        'documents',
        'linked_lease_id'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'escalation_rate' => 'decimal:2'
    ];

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

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function invoiceableItems()
    {
        return $this->hasMany(LeaseAgreementInvoiceableItem::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(LeaseAgreementDocument::class, 'parent_id');
    }
}

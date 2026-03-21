<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantAccountTransaction extends Model
{
    protected $table = 'sys_pm_tenants_accounts_trans';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'narration',
        'pay_for',
        'dr_cr',
        'debit_amount',
        'credit_amount',
        'receipt_number',
        'payment_mode',
        'entry_ref',
        'entry_by',
        'del_status',
        'invoice_number',
        'invoice_id',
        'payment_id',
        'zone_id',
        'property_id',
        'unit_id',
        'lease_id',
        'client_id',
        'tenant_id',
        'sla_id',
        'field_officer_id',
        'zone_supervisor_id',
        'zone_manager_id',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
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

    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recovery extends Model
{
    protected $table = 'sys_pm_recoveries';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'ref_number',
        'type_id',
        'recovery_date',
        'narration',
        'created_by',
        'zone_id',
        'client_id',
        'property_id',
        'sla_id',
        'unit_id',
        'lease_id',
        'tenant_id',
        'commisionable',
        'current_status_id',
        'amount',
        'offset_invoice_id',
        'origin_id',
        'account_id',
        'distributions_allocation_id',
        'return_report_id',
        'returns_status_id',
        'returns_batch_id',
        'return_type_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'type_id');
    }

    public function sla()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    // public function invoice()
    // {
    //     return $this->belongsTo(Invoice::class, 'offset_invoice_id');
    // }

    
}

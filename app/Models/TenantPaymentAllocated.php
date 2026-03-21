<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantPaymentAllocated extends Model
{
    protected $table = 'sys_pm_tenants_payments_allocated';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'payment_date',
        'batch_id',
        'entry_id',
        'receipt_number',
        'amount',
        'narration',
        'pay_for',
        'payment_month',
        'payment_year',
        'payment_mode',
        'payment_channel',
        'security_code',
        'reference_number',
        'entry_ref',
        'trans_amount',
        'trans_type',
        'trans_remarks',
        'trans_part',
        'receipt_month',
        'receipt_year',
        'is_recovery',
        'is_in_account',
        'property_id',
        'client_id',
        'unit_id',
        'tenant_id',
        'zone_id',
        'field_officer_id',
        'zone_supervisor_id',
        'zone_manager_id',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'payment_date' => 'datetime',
        'amount' => 'decimal:4',
        'trans_amount' => 'decimal:4',
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
}

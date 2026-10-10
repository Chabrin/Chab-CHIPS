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
        'open_ended',
        'initial_water_meter_reading',
        'escalation_rate',
        'service_charges_amount',
        'escalation_frequency',
        'documents',
        'linked_lease_id',
        'contract_template_id',
        'unsigned_pdf_path',
        'signed_pdf_path',
        'contract_sign_token',
        'signer_name',
        'contract_sent_at',
        'contract_signed_at',
        'tenant_verified_at',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'open_ended' => 'boolean',
        'escalation_rate' => 'decimal:2',
        'service_charges_amount' => 'decimal:2',
        'contract_sent_at' => 'datetime',
        'contract_signed_at' => 'datetime',
        'tenant_verified_at' => 'datetime',
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

    public function contractTemplate()
    {
        return $this->belongsTo(LeaseContractTemplate::class, 'contract_template_id');
    }
}

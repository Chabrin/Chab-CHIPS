<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreement extends Model
{
    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 5;
    const STATUS_REJECTED = 41;

    protected $table = 'sys_pm_service_agreements';
    protected $primaryKey = 'id';
    public $timestamps = false; // table has no created_at/updated_at columns

    protected $fillable = [
        'date_time',
        'client_d',
        'zone_area_id',
        'property_id',
        'commission_percentage',
        'vat',
        'date_start',
        'date_end',
        'zone_id',
        'select',
        'reference_number',
        'created_by_id',
        'sla_documents',
        'landlord_payment_date',
        'current_status_id',
        'auto_renewal',
        'years',
        'vat_per',
        'flat_rate_vat',
        'move_in_inspection_required',
        'charge_registration_fee',
        'returns_combination',
        'rent_selected',
        'electricity_bill_selected',
        'electricity_bill_cost',
        'water_bill_selected',
        'water_bill_cost',
        'service_charges_selected',
        'service_charges_cost',
    ];

    protected $casts = [
        'date_time'                    => 'datetime',
        'created_at'                   => 'datetime',
        'updated_at'                   => 'datetime',
        'move_in_inspection_required'  => 'boolean',
        'charge_registration_fee'      => 'boolean',
        'rent_selected'                => 'boolean',
        'electricity_bill_selected'    => 'boolean',
        'water_bill_selected'          => 'boolean',
        'service_charges_selected'     => 'boolean',
    ];

    /**
     * Whether this service agreement has been approved.
     */
    public function isApproved(): bool {
        return (int) $this->current_status_id === self::STATUS_APPROVED;
    }

    /**
     * Limit a query to approved service agreements.
     */
    public function scopeApproved($query) {
        return $query->where('current_status_id', self::STATUS_APPROVED);
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_d');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function serviceAgreementDocuments()
    {
        return $this->hasMany(ServiceAgreementDocument::class, 'parent_id');
    }

    public function billCharges()
    {
        return $this->hasMany(ServiceAgreementBillCharge::class, 'parent_id');
    }

    public function returnTypes()
    {
        return $this->hasMany(ServiceAgreementReturnType::class, 'parent_id');
    }

    public function invoiceClients()
    {
        return $this->hasMany(ServiceAgreementInvoiceClient::class, 'parent_id');
    }

    public function moveInInspections()
    {
        return $this->hasMany(MoveInInspection::class, 'sla_id');
    }
}

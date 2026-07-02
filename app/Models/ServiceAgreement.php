<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreement extends Model
{
    protected $table = 'sys_pm_service_agreements';
    protected $primaryKey = 'id';
    public $timestamps = true;

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
    ];

    protected $casts = [
        'date_time'                    => 'datetime',
        'created_at'                   => 'datetime',
        'updated_at'                   => 'datetime',
        'move_in_inspection_required'  => 'boolean',
    ];

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

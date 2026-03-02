<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreementBillCharge extends Model
{
    protected $table = 'sys_pm_service_agreements_186_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'item_id',
        'cost_per_unit',
        'comm_percentage',
        'commissionable',
    ];

    public function serviceAgreement()
    {
        return $this->belongsTo(ServiceAgreement::class, 'parent_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(InvoiceableItem::class, 'item_id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseAgreementInvoiceableItem extends Model
{
    protected $table = 'sys_pm_lease_agreements_27_repeat';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'item_id',
        'amount',
        'cycle_id',
        'commissionable',
        'comm_per',
        'vatable',
        'prepay_client',
    ];

    public function leaseAgreement()
    {
        return $this->belongsTo(LeaseAgreement::class, 'parent_id');
    }

    public function item()
    {
        return $this->belongsTo(InvoiceableItem::class, 'item_id');
    }

}

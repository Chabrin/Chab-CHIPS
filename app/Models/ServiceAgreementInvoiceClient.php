<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreementInvoiceClient extends Model
{
    protected $table = 'sys_pm_service_agreements_24_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'item_id',
        'amount',
    ];

    public function serviceAgreement()
    {
        return $this->belongsTo(ServiceAgreement::class, 'parent_id');
    }

    public function item()
    {
        return $this->belongsTo(InvoiceableItem::class, 'item_id');
    }
}

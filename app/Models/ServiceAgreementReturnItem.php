<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreementReturnItem extends Model
{
    protected $table = 'sys_pm_service_agreements_282_repeat_repeat_return_items';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'return_items',
        'params',
    ];

    public function invoiceableItem()
    {
        return $this->belongsTo(InvoiceableItem::class, 'return_items', 'id');
    }

    public function serviceAgreementReturnType()
    {
        return $this->belongsTo(ServiceAgreementReturnType::class, 'parent_id', 'id');
    }


}

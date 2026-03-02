<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreementReturnType extends Model
{
    protected $table = 'sys_pm_service_agreements_282_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'return_type_id',
        'return_items',
    ];

    public function returnType()
    {
        return $this->belongsTo(ReturnType::class, 'return_type_id', 'id');
    }

    public function serviceAgreement()
    {
        return $this->belongsTo(ServiceAgreement::class, 'parent_id', 'id');
    }

    public function invoiceableItems()
    {
        return $this->hasMany(ServiceAgreementReturnItem::class, 'parent_id');
    }

}

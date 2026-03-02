<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAgreementDocument extends Model
{
    protected $table = 'sys_pm_service_agreements_repeat_sla_documents';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'sla_documents',
        'params',
    ];

    public function serviceAgreement()
    {
        return $this->belongsTo(ServiceAgreement::class, 'parent_id');
    }
}

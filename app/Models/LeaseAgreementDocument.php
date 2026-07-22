<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseAgreementDocument extends Model
{
     protected $table = 'sys_pm_lease_agreements_repeat_documents';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'documents',
        'params',
    ];

    public function leaseAgreement()
    {
        return $this->belongsTo(LeaseAgreement::class, 'parent_id');
    }

}

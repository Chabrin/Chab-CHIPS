<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseAgreementDocument extends Model
{
     protected $table = 'sys_pm_lease_agreements_repeat_documents';

    protected $fillable = [
        'parent_id',
        'documents',
        'parans',
    ];

    public function leaseAgreement()
    {
        return $this->belongsTo(LeaseAgreement::class, 'parent_id');
    }

}

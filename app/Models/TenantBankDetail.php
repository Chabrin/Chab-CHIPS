<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBankDetail extends Model
{
    protected $table = 'tenants_bank_details';

    protected $fillable = [
        'tenant_id',
        'bank_id',
        'branch_id',
        'account_name',
        'account_number',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'branch_id');
    }
}

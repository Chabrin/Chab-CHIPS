<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Vendor extends Model
{
    protected $table = 'vendors';

    protected $fillable = [
        'vendor_code',
        'vendor_name',
        'vendor_type',
        'client_id',
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'address_line_4',
        'bank_name',
        'bank_branch',
        'account_number',
        'current_balance',
        'last_maintained_at',
        'audit_user',
        'status',
        'created_by',
        'taxable',
    ];

    protected $casts = [
        'last_maintained_at' => 'datetime',
        'current_balance' => 'decimal:2',
    ];

    public function getEncryptedIdAttribute(): string
    {
        return Crypt::encrypt($this->id);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordAccount extends Model
{
    protected $table = 'cb_landlord_account';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'month',
        'landlord_code',
        'landlord_name',
        'property_code',
        'transaction_number',
        'entry_type_code',
        'entry_sequence',
        'entry_description',
        'entry_date',
        'debit_amount',
        'credit_amount',
        'total_amount',
        'account_status',
        'payment_ref',
        'payment_ref_type',
        'interest_rate',
        'audit_user',
        'entry_particulars',
        'pv_number',
        'gl_account',
        'vendor_id',
        'is_recovered',
        'recovery_year',
        'recovery_month',
        'return_transaction',
        'return_code',
        'client_id',
        'property_id',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}

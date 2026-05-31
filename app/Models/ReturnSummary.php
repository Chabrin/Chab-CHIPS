<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnSummary extends Model
{
    protected $table = 'cb_returns_summary';
    public $timestamps = false;

    protected $fillable = [
        'return_code',
        'landlord_code',
        'property_code',
        'year',
        'month',
        'return_description',
        'return_period',
        'return_type',
        'commission_rate',
        'total_credit',
        'commission_total',
        'vatable_total',
        'commission_amount',
        'total_debit',
        'net_amount',
        'transaction_amount',
        'net_balance',
        'prepared_by',
        'prepared_status',
        'prepared_date',
        'checked_by',
        'checked_status',
        'checked_date',
        'saved_status',
        'finalized_status',
        'finalized_by',
        'finalized_date',
        'approved_by',
        'approved_status',
        'approved_date',
        'paid_status',
        'voucher_generated',
        'voucher_number',
        'remarks',
        'remarks_2',
        'rebate_year',
        'rebate_month',
        'return_tag',
        'return_status',
        'cheque_number',
        'bank_account',
        'amount_paid',
        'amount_overdraft',
        'payment_date',
        'return_zone',
        'auth_2m',
        'auth_un',
        'overdraft_percentage',
        'return_id',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'prepared_date' => 'datetime',
        'checked_date' => 'datetime',
        'finalized_date' => 'datetime',
        'approved_date' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(ReturnClient::class, 'return_code', 'return_code');
    }

    public function scopePaid($query)
    {
        return $query->where('paid_status', 1);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('paid_status', 0);
    }
}

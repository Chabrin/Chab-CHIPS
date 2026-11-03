<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucherLine extends Model
{
    protected $table = 'cb_payment_vouchers_lines_new';

    protected $fillable = [
        'voucher_number',
        'entry_number',
        'entry_year',
        'entry_month',
        'account_number',
        'account_bank',
        'cheque_number',
        'cheque_leaf_id',
        'cheque_type',
        'voucher_type',
        'property_code',
        'dr_cr',
        'entry_id',
        'entry_description',
        'is_approved',
        'amount',
        'interest_rate',
        'gl_account',
        'transaction_number',
        'payee_name',
        'credit_account',
        'credit_month',
        'credit_year',
        'source_transaction',
        'cheque_posted',
        'cheque_open',
        'cheque_date',
        'end_date',
        'property_description',
        'unlocked_by',
        'unlocked_date',
        'unlock_reason',
        'source_account_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'cheque_date' => 'datetime',
        'end_date' => 'datetime',
        'unlocked_date' => 'datetime',
    ];

    public function header()
    {
        return $this->belongsTo(PaymentVoucherHeader::class, 'voucher_number', 'voucher_number');
    }

    public function chequeLeaf()
    {
        return $this->belongsTo(ChequeLeaf::class, 'cheque_leaf_id');
    }
}

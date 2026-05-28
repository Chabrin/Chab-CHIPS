<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentVoucherHeader extends Model
{
    protected $table = 'cb_payment_vouchers_headers_new';

    protected $fillable = [
        'voucher_sequence',
        'voucher_number',
        'year',
        'month',
        'voucher_date',
        'description',
        'vendor_id',
        'vendor_name',
        'vendor_type',
        'debit_amount',
        'credit_amount',
        'entry_count',
        'voucher_status',
        'cheque_number',
        'is_deleted',
        'prepared_by',
        'checked_status',
        'checked_by',
        'authorized_status',
        'authorized_by',
        'is_printed',
        'checked_date',
        'authorized_date',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'voucher_date' => 'datetime',
        'checked_date' => 'datetime',
        'authorized_date' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(PaymentVoucherLine::class, 'voucher_number', 'voucher_number');
    }

    public function approvalRequest()
    {
        return ApprovalRequest::where('request_type', 'voucher')
            ->where('request_id', $this->id)
            ->first();
    }

    public function getApprovalStatus(): ?string
    {
        $request = $this->approvalRequest();
        return $request?->status;
    }
}

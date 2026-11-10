<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class PaymentVoucherHeader extends Model
{
    /** Key of the database lock that makes two people creating a voucher at the same moment take turns. */
    private const SEQUENCE_LOCK_KEY = 7340011;

    protected $table = 'cb_payment_vouchers_headers_new';

    /**
     * Take the next voucher sequence number. Every place that creates a voucher header uses this, so no two
     * vouchers get the same number. It must be called inside an open transaction, and the header must be saved
     * in that same transaction: the lock is held until the transaction ends, so the next person waits and then
     * sees this voucher when they read the highest number.
     */
    public static function nextSequence(): int {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('nextSequence() must be called inside a database transaction.');
        }

        // Postgres only (production and dev). The SQLite test database has a single connection, so there is nothing to wait for.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::SEQUENCE_LOCK_KEY]);
        }

        return ((int) static::max('voucher_sequence')) + 1;
    }

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

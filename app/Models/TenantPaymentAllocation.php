<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the allocation trail: an amount of one payment applied to one invoice. This is the table the
 * allocation service writes (sys_pm_tenants_payments_invoices). The older sys_pm_tenants_payments_87_repeat table
 * is empty and nothing writes it any more.
 */
class TenantPaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'sys_pm_tenants_payments_invoices';

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount_allocated'
    ];

    protected $casts = [
        'amount_allocated' => 'decimal:2'
    ];

    public $timestamps = false;

    /**
     * Get the payment this allocation belongs to
     */
    public function payment()
    {
        return $this->belongsTo(TenantPayment::class, 'payment_id');
    }

    /**
     * Get the invoice this allocation is for
     */
    public function invoice()
    {
        return $this->belongsTo(DebtorInvoice::class, 'invoice_id');
    }
}

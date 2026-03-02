<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantPaymentInvoice extends Model
{
    protected $table = 'sys_pm_tenants_payments_invoices';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount_allocated',
        'allocation_date'
    ];

    protected $casts = [
        'amount_allocated' => 'decimal:2',
        'allocation_date' => 'datetime'
    ];

    /**
     * Get the invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(DebtorInvoice::class, 'invoice_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantPaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'sys_pm_tenants_payments_87_repeat';

    protected $fillable = [
        'parent_id',
        'invoice_id',
        'amount_allocated',
        'payment_priority',
        'allocation_id'
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
        return $this->belongsTo(TenantPayment::class, 'parent_id');
    }

    /**
     * Get the invoice this allocation is for
     */
    public function invoice()
    {
        return $this->belongsTo(DebtorInvoice::class, 'invoice_id');
    }
}

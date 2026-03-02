<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLineItem extends Model
{
    protected $table = 'sys_pm_debtors_invoices_41_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'item_id',
        'quantity',
        'amount',
        'payment_method_id',
        'current_status_id'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'amount' => 'decimal:2'
    ];

    /**
     * Get the parent invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(DebtorInvoice::class, 'parent_id');
    }
}

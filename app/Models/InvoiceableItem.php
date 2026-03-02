<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceableItem extends Model
{
    protected $table = 'sys_set_invoiceable_items';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'item_name',
        'billing_code',
        'feature',
        'payment_priority',
        'commissionable',
        'default_cycle_id',
        'current_status_id',
        'default_expense_account_id',
        'advance_payable',
        'is_bill',
        'fixed_amount',
        'amount',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(InvoiceableItemCycle::class, 'default_cycle_id');
    }

}

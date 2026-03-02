<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositAllocation extends Model
{
    protected $table = 'sys_pm_deposit_allocations';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'deposit_id',
        'invoice_id',
        'amount_allocated',
        'date_allocated',
        'narration',
        'created_by',
        'created_date'
    ];

    protected $casts = [
        'date_allocated' => 'datetime',
        'created_date' => 'datetime',
        'amount_allocated' => 'decimal:2'
    ];

    /**
     * Get the deposit that owns the allocation.
     */
    public function deposit()
    {
        return $this->belongsTo(Deposit::class, 'deposit_id');
    }

    /**
     * Get the invoice associated with the allocation.
     */
    public function invoice()
    {
        return $this->belongsTo(DebtorInvoice::class, 'invoice_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceWorkflow extends Model
{
    protected $table = 'sys_pm_debtors_invoices_60_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'action_by_id',
        'action_position_id',
        'action_id',
        'action_date_time',
        'remark'
    ];

    protected $casts = [
        'action_date_time' => 'datetime'
    ];

    /**
     * Get the parent invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(DebtorInvoice::class, 'parent_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by_id');
    }
}

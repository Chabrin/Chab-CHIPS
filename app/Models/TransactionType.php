<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    protected $table = 'sys_set_transactions_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'type_name',
        'is_recovery',
        'current_status_id',
        'workflow_status_id',
        'is_commissionable',
        'is_client_expense',
        'category_id',
        'debit_account_id',
        'credit_account_id',
        'default_amount',
        'item_code',
    ];

    public function category()
    {
        return $this->belongsTo(TaxCategory::class, 'category_id');
    }

    public function debitAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'credit_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChartAccount;
use App\Models\User;

class Tax extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_gl_taxes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'name',
        'default_type_id',
        'computation_id',
        'category_id',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'created_by_id',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'default_type_id' => 'integer',
        'computation_id' => 'integer',
        'category_id' => 'integer',
        'debit_account_id' => 'integer',
        'credit_account_id' => 'integer',
        'amount' => 'decimal:2',
        'created_by_id' => 'integer',
        'current_status_id' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function defaultType()
    {
        return $this->belongsTo(TaxDefaultType::class, 'default_type_id');
    }

    public function computation()
    {
        return $this->belongsTo(TaxComputation::class, 'computation_id');
    }

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
}

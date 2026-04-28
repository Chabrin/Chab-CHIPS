<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class CompanyBankAccount extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_set_bank_accounts';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'account_name',
        'account_number',
        'address',
        'gl_account_id',
        'bank_id',
        'branch_id',
        'account_type',
        'current_status_id',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'date_time'         => 'datetime',
        'current_status_id' => 'integer',
        'bank_id'           => 'integer',
        'branch_id'         => 'integer',
        'created_by'        => 'integer',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'branch_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chequeBooks()
    {
        return $this->hasMany(ChequeBook::class, 'bank_account_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->current_status_id) {
            1 => 'Active',
            2 => 'Inactive',
            default => 'Unknown',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ((int) $this->current_status_id) {
            1 => 'badge-light-success',
            2 => 'badge-light-danger',
            default => 'badge-light-secondary',
        };
    }
}

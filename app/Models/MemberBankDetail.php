<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MemberBankDetail extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    use HasFactory, SoftDeletes;
    protected $table = 'members_bank_details';

    protected $fillable = [
        'member_no',
        'account_no',
        'account_name',
        'account_type',
        'bank_id',
        'branch_id',
        'added_by',
        'added_on',
        'server_time',
        'updated_by',
        'deleted_by',
        'deleted_reason',
        'currency_code',
        'default_account'
    ];

    protected $casts = [
        'bank_id' => 'integer',
        'branch_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Bank for this member account
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * Branch for this member account
     */
    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'branch_id');
    }
}

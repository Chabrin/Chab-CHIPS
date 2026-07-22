<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankBranch extends Model
{
    use HasFactory;
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_set_banks_branches';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'branch_code',
        'branch_name',
        'bank_id',
        'current_status_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}

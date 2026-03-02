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

    protected $fillable = [
        'branch_code',
        'branch_name',
        'bank_id',
        'added_by',
        'added_on'
    ];

    protected $casts = [
        'added_on' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(LegacyBank::class, 'bank_id');
    }
}

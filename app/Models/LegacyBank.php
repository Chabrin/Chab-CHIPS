<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LegacyBank extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    use HasFactory;
    protected $table = 'banks';

    protected $fillable = [
        'bank_code',
        'name',
        'added_by',
        'added_on',
        'swift_bic_codes',
        'swift_code'
    ];


    protected $casts = [
        'added_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branches()
    {
        return $this->hasMany(BankBranch::class, 'bank_id');
    }
}

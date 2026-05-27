<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ClientBankDetail extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'clients_bank_details';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'bank_id',
        'branch_id',
        'account_number',
        'account_name',
        'is_default',
        'added_by',
        'added_on',
    ];

    protected $casts = [
        'added_on' => 'datetime',
        'is_default' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'branch_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }
}

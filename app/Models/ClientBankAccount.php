<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientBankAccount extends Model
{
    protected $table = 'sys_pm_clients_17_repeat';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'bank_id',
        'account_name',
        'account_number',
        'branch',
        'return_percentage',
    ];

    
    public function client()
    {
        return $this->belongsTo(Client::class, 'parent_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'branch');
    }
}
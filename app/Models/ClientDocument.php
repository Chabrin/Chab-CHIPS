<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDocument extends Model
{
    protected $table = 'sys_pm_clients_65_repeat';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'documents',
        'file_type',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'parent_id');
    }
}

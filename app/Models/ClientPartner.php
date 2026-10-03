<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPartner extends Model
{
    protected $table = 'clients_partners';

    protected $fillable = [
        'client_id',
        'name',
        'national_id',
        'kra_pin',
        'phone',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}

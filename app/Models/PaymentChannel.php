<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentChannel extends Model
{
    protected $table = 'sys_set_payment_channel';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'channel_name',
        'channel_code'
    ];
}

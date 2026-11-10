<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommSetting extends Model
{
    protected $table = 'cb_comm_settings';

    protected $guarded = [];

    protected $casts = [
        'value_json' => 'array',
    ];
}

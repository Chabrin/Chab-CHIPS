<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorSession extends Model
{
    protected $table = 'two_factor_sessions';
    protected $fillable = ['user_id', 'token', 'secret', 'expires_at'];
    protected $dates = ['expires_at'];
}

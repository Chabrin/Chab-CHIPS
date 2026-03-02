<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLogs extends Model
{

    protected $table = 'cb_error_logs';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;
    protected $fillable = [
        'error',
        'error_message',
        'stack_trace',
        'dispatch_time',
        'route',
        'resolved',
        'user_affected'
    ];

    protected $casts = [
        'dispatch_time' => 'datetime',
        'resolved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlPeriod extends Model
{
    protected $table = 'sys_gl_periods';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'period_start',
        'period_end',
        'current_status_id',
        'added_by',
        'added_on',
    ];

    protected $casts = [
        'date_time'    => 'datetime',
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'added_on'     => 'datetime',
    ];
}

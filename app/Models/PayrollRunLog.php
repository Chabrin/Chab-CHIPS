<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunLog extends Model
{
    protected $table = 'payroll_run_logs';
    public $timestamps = false;

    protected $fillable = [
        'payroll_run_id',
        'action',
        'comment',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

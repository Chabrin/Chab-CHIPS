<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlTransaction extends Model
{
    protected $table = 'sys_gl_transactions';
    public $timestamps = false;

    protected $casts = [
        'date_time'    => 'datetime',
        'journal_date' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(GlTransactionLine::class, 'parent_id');
    }
}

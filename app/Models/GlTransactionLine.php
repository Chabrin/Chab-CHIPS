<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlTransactionLine extends Model
{
    protected $table = 'sys_gl_transactions_91_repeat';
    public $timestamps = false;

    public function header()
    {
        return $this->belongsTo(GlTransaction::class, 'parent_id');
    }
}

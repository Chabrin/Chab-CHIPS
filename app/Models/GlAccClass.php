<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class GlAccClass extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_gl_acc_classes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'name',
        'balance_nature_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function groups()
    {
        return $this->hasMany(GlAccGroup::class, 'class_id');
    }
}

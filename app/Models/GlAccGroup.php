<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class GlAccGroup extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_gl_acc_groups';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'group_name',
        'class_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'class_id'  => 'integer',
    ];

    public function glClass()
    {
        return $this->belongsTo(GlAccClass::class, 'class_id');
    }

    public function categories()
    {
        return $this->hasMany(GlAccountCategory::class, 'account_group_id');
    }
}

<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class GlAccountCategory extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_gl_accounts_categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'category_name',
        'account_group_id',
    ];

    protected $casts = [
        'date_time'        => 'datetime',
        'account_group_id' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(GlAccGroup::class, 'account_group_id');
    }

    public function accounts()
    {
        return $this->hasMany(ChartAccount::class, 'category_id');
    }
}

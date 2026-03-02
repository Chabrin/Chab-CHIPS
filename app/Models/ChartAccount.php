<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ChartAccount extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_gl_chart_accounts_master';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'account_name',
        'account_code',
        'account_group_id',
        'category_id',
        'class_id',
        'current_status_id',
        'financial_statement_id',
        'group_id',
        'sub_group_id',
        'normally',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'account_group_id' => 'integer',
        'category_id' => 'integer',
        'class_id' => 'integer',
        'current_status_id' => 'integer',
        'financial_statement_id' => 'integer',
        'group_id' => 'integer',
        'sub_group_id' => 'integer',
    ];

    // Relationships
    public function payrollElements()
    {
        return $this->hasMany(PayrollElement::class, 'account_id');
    }

    public function chartGroup()
    {
        return $this->belongsTo(ChartGroup::class, 'group_id');
    }

    // Accessors
    public function getFullAccountAttribute()
    {
        return $this->account_code . ' - ' . $this->account_name;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('current_status_id', 5);
    }


}

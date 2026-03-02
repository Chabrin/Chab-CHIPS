<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ChartGroup extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_gl_chart_groups';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'financial_statement_id',
        'class_id',
    ];

    protected $casts = [
        'financial_statement_id' => 'integer',
        'class_id' => 'integer',
    ];
    
    //relationships
    public function chartAccounts()
    {
        return $this->hasMany(ChartAccount::class, 'group_id');
    }
}

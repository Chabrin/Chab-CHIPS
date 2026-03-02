<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ShifRate extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_nhif_rates';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'rate',
        'created_by_id',
        'current_status_id',
        'date_time',
        'from_date',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'from_date' => 'datetime',
    ];

    //relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

}

<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_public_holidays';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'pay_rate',
        'recurrent',
        'created_by_id',
        'current_status_id',
        'date_time',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    //relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

}

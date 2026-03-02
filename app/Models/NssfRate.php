<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class NssfRate extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_nssf_rates';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'created_by_id',
        'current_status_id',
        'date_time',
        'from_date',
        'base_id',
        'employee_perc',
        'employer_perc',
        'maximum_contribution',
        'taxable',
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

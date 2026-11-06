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
        'lower_earnings_limit',
        'upper_earnings_limit',
        'tier1_name',
        'tier2_name',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'from_date' => 'datetime',
        'employee_perc' => 'float',
        'lower_earnings_limit' => 'float',
        'upper_earnings_limit' => 'float',
    ];

    public const ACTIVE = 5;

    /** The row payroll uses on a date: the latest active one that has started and has everything the calculation needs. */
    public static function inForceOn(string $date): ?self
    {
        return static::where('current_status_id', self::ACTIVE)
            ->whereNotNull('employee_perc')
            ->whereNotNull('lower_earnings_limit')
            ->whereNotNull('upper_earnings_limit')
            ->whereDate('from_date', '<=', $date)
            ->orderByDesc('from_date')
            ->orderByDesc('id')
            ->first();
    }

    //relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

}

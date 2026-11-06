<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time payroll item (bonus, arrears, an absence deduction) entered for a single payroll period. A row
 * with no staff member applies to every staff member in that period's run.
 */
class PayrollPeriodItem extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'payroll_period_items';

    protected $fillable = [
        'period_id',
        'staff_id',
        'element_id',
        'amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * The payroll element this item is an entry of.
     */
    public function element()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }

    /**
     * The staff member the item is for, or null when it applies to everyone.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * The payroll period the item belongs to.
     */
    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }
}

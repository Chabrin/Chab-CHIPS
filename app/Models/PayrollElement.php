<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollElement extends Model
{
    public const RECURRING = 'recurring';
    public const ONE_TIME = 'one_time';
    public const ALL_STAFF = 'all';
    public const SPECIFIC_STAFF = 'specific';

    /** Calculated by the payroll run for every staff member; they never come from a contract row. */
    public const STATUTORY_KEYS = ['nssf', 'nhif', 'housing_levy', 'payee'];

    /** Output elements that are never entered against a person: salary comes from the contract itself. */
    public const SYSTEM_KEYS = ['basic_salary', 'net_salary'];

    /** Formulas an element that applies to all staff can use: Fixed, Percent of basic salary, Percent of gross payment. */
    public const AMOUNT_FORMULAS = [1, 2, 3];

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_payroll_elements';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'element_name',
        'element_key',
        'account_id',
        'element_type',
        'current_status_id',
        'created_by_id',
        'formula_id',
        'amount',
        'staff_positions_id',
        'payslip_position',
        'recurrence',
        'applies_to',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'amount' => 'decimal:4',
        'current_status_id' => 'integer',
        'created_by_id' => 'integer',
        'staff_positions_id' => 'integer',
        'payslip_position' => 'integer',
    ];

    // Relationships
    public function account()
    {
        return $this->belongsTo(ChartAccount::class, 'account_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'staff_positions_id');
    }

    // Accessors
    public function getElementTypeNameAttribute()
    {
        return match ($this->element_type) {
            '1' => 'Deduction',
            '2' => 'Earnings',
            default => 'Unknown'
        };
    }

    public function getStatusNameAttribute()
    {
        return match ($this->current_status_id) {
            5 => 'Approved',
            default => 'Pending'
        };
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    /**
     * Whether the payroll run calculates this element itself (NSSF, SHA, Housing Levy, PAYE).
     */
    public function isStatutory(): bool
    {
        return in_array(strtolower((string) $this->element_key), self::STATUTORY_KEYS, true);
    }

    /**
     * Whether this is a system element (basic salary, net salary) that is never entered against a person.
     */
    public function isSystem(): bool
    {
        return in_array(strtolower((string) $this->element_key), self::SYSTEM_KEYS, true);
    }

    /**
     * Whether this element is entered once for a payroll month instead of repeating every month.
     */
    public function isOneTime(): bool
    {
        return $this->recurrence === self::ONE_TIME;
    }

    /**
     * Whether this recurring element is applied to every staff member without being added to a contract.
     */
    public function appliesToAll(): bool
    {
        return $this->applies_to === self::ALL_STAFF;
    }

    /**
     * Whether a contract can hold this element: recurring, and not calculated by the run or a system element.
     */
    public function canBeOnContract(): bool
    {
        return !$this->isOneTime() && !$this->isStatutory() && !$this->isSystem();
    }

    /**
     * Whether this element can be entered as a one-time item for a payroll month.
     */
    public function canBeOneTimeItem(): bool
    {
        return $this->isOneTime() && !$this->isStatutory() && !$this->isSystem();
    }

    /**
     * What an all-staff element is worth for one person, from its formula: a fixed amount, or a percentage of
     * basic salary or of gross payment. Any other formula counts as a fixed amount.
     */
    public function defaultAmount(float $basic, float $gross): float
    {
        $amount = (float) $this->amount;

        return round(match ((int) $this->formula_id) {
            2 => $basic * $amount / 100,
            3 => $gross * $amount / 100,
            default => $amount,
        }, 2);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('current_status_id', 5);
    }

    public function scopeDeductions($query)
    {
        return $query->where('element_type', '1');
    }

    public function scopeAllowances($query)
    {
        return $query->where('element_type', '2');
    }
}

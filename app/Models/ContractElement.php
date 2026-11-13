<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ContractElement extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employee_contracts_279_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public const MONTHLY = 'monthly';
    public const ONCE = 'once';

    protected $fillable = [
        'parent_id',
        'element_id',
        'entry_type_id',
        'amount',
        'frequency',
        'once_month',
        'prorate',
    ];

    protected $casts = [
        'once_month' => 'date',
        'prorate' => 'boolean',
    ];

    /**
     * Whether a part month (the month the contract starts or ends) reduces this item by the days worked. When nobody has
     * chosen, the run's old rule for the kind of item applies: an allowance is reduced, a deduction is charged in full.
     */
    public function prorates(): bool
    {
        return $this->prorate ?? ((string) ($this->payrollElement?->element_type ?? $this->entry_type_id) === '2');
    }

    public function isOnce(): bool
    {
        return $this->frequency === self::ONCE;
    }

    /**
     * Whether the run for a pay period starting on this date takes the item. An every-month item always applies. A
     * once-only item applies only in the period that starts in the month it names, so it can never be taken twice.
     */
    public function appliesInPeriod(string $periodStart): bool
    {
        if (!$this->isOnce()) {
            return true;
        }

        return $this->once_month !== null && $this->once_month->format('Y-m') === substr($periodStart, 0, 7);
    }

    /**
     * The terms in a few words for the change log: "Monthly, full amount" or "Once Dec 2026, prorated".
     */
    public function termsBrief(): string
    {
        $when = $this->isOnce() ? 'Once' . ($this->once_month ? ' ' . $this->once_month->format('M Y') : '') : 'Monthly';

        return $when . ', ' . ($this->prorates() ? 'prorated' : 'full amount');
    }

    /**
     * The terms in a sentence, for screens that show them: "Every month · reduced by the days worked in a part month".
     */
    public function termsText(): string
    {
        $when = $this->isOnce() ? 'Once only' . ($this->once_month ? ' (' . $this->once_month->format('M Y') . ')' : '') : 'Every month';

        return $when . " \u{00B7} " . ($this->prorates() ? 'reduced by the days worked in a part month' : 'full amount in a part month');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'parent_id', 'id');
    }

    public function payrollElement()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id', 'id');
    }


}

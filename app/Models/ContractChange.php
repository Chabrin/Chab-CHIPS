<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record of a new contract, a salary change or a change to a recurring element on a contract: the old and new
 * value, who made it and when. It feeds the check before a payroll run and gives each salary a history.
 */
class ContractChange extends Model
{
    public const CONTRACT_CREATED = 'contract_created';
    public const BASIC_SALARY = 'basic_salary';
    public const ELEMENT_ADDED = 'element_added';
    public const ELEMENT_REMOVED = 'element_removed';
    public const ELEMENT_CHANGED = 'element_changed';

    protected $table = 'contract_changes';
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'staff_id',
        'field',
        'element_id',
        'old_value',
        'new_value',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The payroll element a change is about, for element changes.
     */
    public function element()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }

    /**
     * The staff member whose contract changed.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * The user who made the change.
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Record a new contract with its starting salary.
     */
    public static function contractCreated(Contract|EmployeeContract $contract, ?int $userId): void
    {
        self::create([
            'contract_id' => $contract->id,
            'staff_id' => $contract->staff_id,
            'field' => self::CONTRACT_CREATED,
            'new_value' => self::money($contract->basic_salary),
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a basic salary change. Nothing is recorded when the salary is the same.
     */
    public static function salary(Contract|EmployeeContract $contract, $old, $new, ?int $userId): void
    {
        if (self::money($old) === self::money($new)) {
            return;
        }

        self::create([
            'contract_id' => $contract->id,
            'staff_id' => $contract->staff_id,
            'field' => self::BASIC_SALARY,
            'old_value' => self::money($old),
            'new_value' => self::money($new),
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * Record the difference between two sets of recurring elements on a contract, each as element id => amount.
     */
    public static function elements(Contract|EmployeeContract $contract, array $old, array $new, ?int $userId): void
    {
        $rows = [];

        foreach ($new as $elementId => $amount) {
            if (!array_key_exists($elementId, $old)) {
                $rows[] = [self::ELEMENT_ADDED, $elementId, null, $amount];
            } elseif (self::money($old[$elementId]) !== self::money($amount)) {
                $rows[] = [self::ELEMENT_CHANGED, $elementId, $old[$elementId], $amount];
            }
        }

        foreach ($old as $elementId => $amount) {
            if (!array_key_exists($elementId, $new)) {
                $rows[] = [self::ELEMENT_REMOVED, $elementId, $amount, null];
            }
        }

        foreach ($rows as [$field, $elementId, $before, $after]) {
            self::create([
                'contract_id' => $contract->id,
                'staff_id' => $contract->staff_id,
                'field' => $field,
                'element_id' => $elementId,
                'old_value' => $before === null ? null : self::money($before),
                'new_value' => $after === null ? null : self::money($after),
                'changed_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * A contract's elements as element id => amount, the shape elements() compares.
     */
    public static function elementAmounts($rows): array
    {
        $amounts = [];
        foreach ($rows as $row) {
            $amounts[(int) $row->element_id] = (float) $row->amount;
        }

        return $amounts;
    }

    private static function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

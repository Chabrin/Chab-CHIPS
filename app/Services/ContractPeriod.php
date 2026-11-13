<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * The last day of a contract, from its start date and period: the day before the same date one period later.
 * 12 months from 1 January 2027 ends on 31 December 2027, and 6 months from 15 March 2027 on 14 September 2027.
 * Months and years do not overflow: 1 month from 31 January ends on 27 February (28 February, the same date one month
 * later, less a day). public/assets/js/contract-period.js works it out the same way in the browser, and both are tested
 * against tests/fixtures/contract-period-cases.json, so keep the two in step.
 */
class ContractPeriod
{
    public const UNITS = ['days', 'weeks', 'months', 'years'];

    public static function endDate(string $start, int $value, string $unit): CarbonImmutable
    {
        $begin = CarbonImmutable::parse($start)->startOfDay();

        $later = match ($unit) {
            'days' => $begin->addDays($value),
            'weeks' => $begin->addWeeks($value),
            'months' => $begin->addMonthsNoOverflow($value),
            'years' => $begin->addYearsNoOverflow($value),
            default => throw new \InvalidArgumentException("Unknown contract period unit: {$unit}"),
        };

        return $later->subDay();
    }

    /**
     * The last day of a probation period of this many months.
     */
    public static function probationEnd(string $start, int $months): CarbonImmutable
    {
        return self::endDate($start, $months, 'months');
    }
}

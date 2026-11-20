<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * The month-by-month schedule of a deduction that runs for a set period and then stops.
 *
 * HR gives the total owed and the monthly amount (or the number of months, or the month it should end by) and a start month; the other is
 * worked out. Every month takes the monthly amount, and the last month takes only what is left: KES 21,500 at KES 2,000 a month is ten
 * months of 2,000 and then 1,500 in the eleventh. All the arithmetic is in whole cents so nothing drifts.
 */
class DeductionSchedule
{
    /** The longest a deduction may run. */
    public const MAX_MONTHS = 120;

    /**
     * @param  float       $total       the total owed
     * @param  float|null  $monthly     the amount taken each month (worked out from the months when left out)
     * @param  string      $startDate   any date in the first month of deduction
     * @param  int|null    $months      the number of months wanted
     * @param  string|null $endMonth    the month it should finish by (Y-m), as an alternative to $months
     * @return array{rows: array<int, array{n: int, month: string, label: string, amount: float, balance: float}>, months: int, monthly: float,
     *               last_amount: float, end_month: string|null, problems: string[], notes: string[]}
     */
    public static function build(float $total, ?float $monthly, string $startDate, ?int $months = null, ?string $endMonth = null): array
    {
        $result = ['rows' => [], 'months' => 0, 'monthly' => 0.0, 'last_amount' => 0.0, 'end_month' => null, 'problems' => [], 'notes' => []];
        $totalCents = (int) round($total * 100);
        if ($totalCents <= 0) {
            $result['problems'][] = 'The total owed must be more than zero.';

            return $result;
        }
        try {
            $start = CarbonImmutable::parse($startDate)->startOfMonth();
        } catch (\Throwable) {
            $result['problems'][] = 'The start month is not a valid date.';

            return $result;
        }

        // How many months were asked for, if the months or the end month were given
        $wanted = $months;
        if ($wanted === null && $endMonth !== null && $endMonth !== '') {
            try {
                $end = CarbonImmutable::parse($endMonth . (strlen($endMonth) === 7 ? '-01' : ''))->startOfMonth();
                $wanted = $start->diffInMonths($end, false) + 1;
            } catch (\Throwable) {
                $result['problems'][] = 'The end month is not a valid date.';

                return $result;
            }
            if ($wanted < 1) {
                $result['problems'][] = 'The end month cannot be before the start month.';

                return $result;
            }
        }
        $wanted = $wanted !== null ? (int) $wanted : null;
        if ($wanted !== null && $wanted < 1) {
            $result['problems'][] = 'The number of months must be at least 1.';

            return $result;
        }

        $monthlyCents = $monthly !== null && $monthly > 0 ? (int) round($monthly * 100) : null;
        if ($monthlyCents === null) {
            if ($wanted === null) {
                $result['problems'][] = 'Give the monthly amount, or the number of months, or the month it should end by.';

                return $result;
            }
            // Worked out from the months, rounded up so the last month is never bigger than the others
            $monthlyCents = (int) ceil($totalCents / $wanted);
        }

        $needed = (int) ceil($totalCents / $monthlyCents);
        if ($needed > self::MAX_MONTHS) {
            // A typing slip (KES 1 a month on a large total) must not build a schedule of thousands of rows
            $result['problems'][] = 'At KES ' . number_format($monthlyCents / 100.0, 2) . ' a month it would take ' . number_format($needed) . ' months. The longest schedule is ' . self::MAX_MONTHS . ' months (10 years): raise the monthly amount.';

            return $result;
        }
        if ($wanted !== null && $wanted < $needed) {
            $minimum = ceil($totalCents / $wanted) / 100.0;
            $result['problems'][] = 'At KES ' . number_format($monthlyCents / 100.0, 2) . ' a month it takes ' . $needed . ' months, not ' . $wanted
                . '. To finish in ' . $wanted . ' the monthly amount must be at least KES ' . number_format($minimum, 2) . '.';
        } elseif ($wanted !== null && $wanted > $needed) {
            $result['notes'][] = 'At KES ' . number_format($monthlyCents / 100.0, 2) . ' a month it finishes after ' . $needed . ' months, earlier than the ' . $wanted . ' asked for.';
        }

        $balance = $totalCents;
        for ($n = 1; $n <= $needed; $n++) {
            $amount = min($monthlyCents, $balance);
            $balance -= $amount;
            $month = $start->addMonths($n - 1);
            $result['rows'][] = ['n' => $n, 'month' => $month->format('Y-m'), 'label' => $month->format('M Y'), 'amount' => $amount / 100.0, 'balance' => $balance / 100.0];
        }

        $result['months'] = $needed;
        $result['monthly'] = $monthlyCents / 100.0;
        $result['last_amount'] = $result['rows'][$needed - 1]['amount'];
        $result['end_month'] = $result['rows'][$needed - 1]['month'];

        return $result;
    }
}

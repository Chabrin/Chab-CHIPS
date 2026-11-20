<?php

namespace App\Services;

/**
 * The legal limits on what can be taken from one person's pay in a month (Employment Act, section 19):
 *
 * - All deductions together may not be more than two-thirds of the wages, so the employee keeps at least one-third.
 * - Repayment of a loan from the company may not be more than half of the wages left after the other deductions.
 *
 * It only measures and says which deductions to reduce. Whether a payroll that breaks a limit is stopped is decided where it is run.
 */
class DeductionLimits
{
    /** The kinds of scheduled deduction that are loans from the company. */
    public const COMPANY_LOAN_KINDS = ['salary_advance'];

    /**
     * @param  float  $gross       the wages the limits are counted on
     * @param  float  $otherTotal  every deduction that is not one of the scheduled ones below (tax, NSSF, SHA, levy, other items)
     * @param  array<int, array{id: mixed, name: string, kind: string, amount: float}>  $scheduled  the scheduled deductions being taken this month
     * @return array{ok: bool, gross: float, deductions: float, take_home: float, minimum_take_home: float, breaches: array<int, array{rule: string, message: string, excess: float}>, reduce: array<int, array{name: string, amount: float, reduce_by: float}>}
     */
    public static function check(float $gross, float $otherTotal, array $scheduled): array
    {
        $grossCents = (int) round($gross * 100);
        $otherCents = (int) round($otherTotal * 100);
        $scheduledCents = 0;
        $loanCents = 0;
        foreach ($scheduled as $item) {
            $cents = (int) round($item['amount'] * 100);
            $scheduledCents += $cents;
            if (in_array($item['kind'] ?? '', self::COMPANY_LOAN_KINDS, true)) {
                $loanCents += $cents;
            }
        }
        $totalCents = $otherCents + $scheduledCents;
        $maxTotal = intdiv($grossCents * 2, 3);
        $result = [
            'ok' => true, 'gross' => $grossCents / 100.0, 'deductions' => $totalCents / 100.0, 'take_home' => ($grossCents - $totalCents) / 100.0,
            'minimum_take_home' => ($grossCents - $maxTotal) / 100.0, 'breaches' => [], 'reduce' => [],
        ];

        $excess = 0;
        if ($totalCents > $maxTotal) {
            $excess = $totalCents - $maxTotal;
            $result['breaches'][] = [
                'rule' => 'two_thirds',
                'message' => 'Deductions are KES ' . number_format($totalCents / 100.0, 2) . ', more than two-thirds of the wages (KES ' . number_format($maxTotal / 100.0, 2) . '). The employee must keep at least KES ' . number_format(($grossCents - $maxTotal) / 100.0, 2) . '.',
                'excess' => $excess / 100.0,
            ];
        }

        // A loan from the company: at most half of what is left after the other deductions
        $leftBeforeLoan = $grossCents - ($totalCents - $loanCents);
        $loanCap = intdiv(max(0, $leftBeforeLoan), 2);
        $loanExcess = 0;
        if ($loanCents > $loanCap) {
            $loanExcess = $loanCents - $loanCap;
            $result['breaches'][] = [
                'rule' => 'loan_half',
                'message' => 'The company loan repayment is KES ' . number_format($loanCents / 100.0, 2) . ', more than half of the wages left after the other deductions (KES ' . number_format($loanCap / 100.0, 2) . ').',
                'excess' => $loanExcess / 100.0,
            ];
        }

        if ($result['breaches']) {
            $result['ok'] = false;
            $result['reduce'] = self::whatToReduce($scheduled, max($excess, $loanExcess), $excess >= $loanExcess);
        }

        return $result;
    }

    /**
     * Which scheduled deductions to reduce, largest first, and by how much, to take the excess away. When it is the loan rule that is broken,
     * only the company loans can be reduced.
     *
     * @return array<int, array{name: string, amount: float, reduce_by: float}>
     */
    private static function whatToReduce(array $scheduled, int $excessCents, bool $anyDeduction): array
    {
        $candidates = array_values(array_filter($scheduled, fn ($item) => $anyDeduction || in_array($item['kind'] ?? '', self::COMPANY_LOAN_KINDS, true)));
        usort($candidates, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        $reduce = [];
        foreach ($candidates as $item) {
            if ($excessCents <= 0) {
                break;
            }
            $cents = (int) round($item['amount'] * 100);
            $by = min($cents, $excessCents);
            $reduce[] = ['name' => $item['name'], 'amount' => $cents / 100.0, 'reduce_by' => $by / 100.0];
            $excessCents -= $by;
        }

        return $reduce;
    }
}

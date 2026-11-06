<?php

namespace App\Services;

class NssfCalculator
{
    public function calculate(float $grossPay): array
    {
        $grossPay = max($grossPay, 0);
        $rate = config('nssf.rate');
        $lel  = config('nssf.lower_earnings_limit');
        $uel  = config('nssf.upper_earnings_limit');

        $percent = (float) round($rate * 100, 2);
        $tier1 = round($rate * min($grossPay, $lel), 2);
        $tier2 = round($rate * max(min($grossPay, $uel) - $lel, 0), 2);

        return [
            'tiers' => [
                ['name' => config('nssf.tier1_label'), 'amount' => $tier1, 'basis' => $percent . '% of pay up to ' . number_format($lel)],
                ['name' => config('nssf.tier2_label'), 'amount' => $tier2, 'basis' => $percent . '% of pay from ' . number_format($lel) . ' up to ' . number_format($uel)],
            ],
            'total' => round($tier1 + $tier2, 2),
        ];
    }
}
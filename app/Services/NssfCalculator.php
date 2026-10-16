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

        $tier1 = round($rate * min($grossPay, $lel), 2);
        $tier2 = round($rate * max(min($grossPay, $uel) - $lel, 0), 2);

        return [
            'tiers' => [
                ['name' => config('nssf.tier1_label'), 'amount' => $tier1],
                ['name' => config('nssf.tier2_label'), 'amount' => $tier2],
            ],
            'total' => round($tier1 + $tier2, 2),
        ];
    }
}
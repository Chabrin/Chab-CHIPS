<?php

namespace App\Services;

use App\Models\NssfRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class NssfCalculator
{
    /** Figures already looked up, by date, so a payroll run asks the database once */
    private array $settingsByDate = [];
    private ?bool $ratesAreStored = null;

    /**
     * NSSF on a gross pay, using the rates in force on $onDate (today when none is given).
     */
    public function calculate(float $grossPay, $onDate = null): array
    {
        $grossPay = max($grossPay, 0);
        $settings = $this->settingsFor($onDate);
        $rate = $settings['rate'];
        $lel  = $settings['lower_earnings_limit'];
        $uel  = $settings['upper_earnings_limit'];

        $percent = (float) round($rate * 100, 2);
        $tier1 = round($rate * min($grossPay, $lel), 2);
        $tier2 = round($rate * max(min($grossPay, $uel) - $lel, 0), 2);

        return [
            'tiers' => [
                ['name' => $settings['tier1_label'], 'amount' => $tier1, 'basis' => $percent . '% of pay up to ' . number_format($lel)],
                ['name' => $settings['tier2_label'], 'amount' => $tier2, 'basis' => $percent . '% of pay from ' . number_format($lel) . ' up to ' . number_format($uel)],
            ],
            'total' => round($tier1 + $tier2, 2),
        ];
    }

    /**
     * The NSSF figures in force on a date: the latest row of HR Settings > NSSF Rates that has started, or the
     * standard figures in config/nssf.php when there is no usable row. 'source' says which one was used.
     */
    public function settingsFor($onDate = null): array
    {
        $date = Carbon::parse($onDate ?? now())->toDateString();

        return $this->settingsByDate[$date] ??= $this->lookUp($date);
    }

    private function lookUp(string $date): array
    {
        $row = $this->ratesAreStored() ? NssfRate::inForceOn($date) : null;

        if (!$row) {
            return [
                'source' => 'standard',
                'id' => null,
                'from_date' => null,
                'rate' => (float) config('nssf.rate'),
                'lower_earnings_limit' => (float) config('nssf.lower_earnings_limit'),
                'upper_earnings_limit' => (float) config('nssf.upper_earnings_limit'),
                'tier1_label' => config('nssf.tier1_label'),
                'tier2_label' => config('nssf.tier2_label'),
            ];
        }

        return [
            'source' => 'settings',
            'id' => $row->id,
            'from_date' => $row->from_date->toDateString(),
            'rate' => $row->employee_perc / 100,
            'lower_earnings_limit' => $row->lower_earnings_limit,
            'upper_earnings_limit' => $row->upper_earnings_limit,
            'tier1_label' => $row->tier1_name ?: config('nssf.tier1_label'),
            'tier2_label' => $row->tier2_name ?: config('nssf.tier2_label'),
        ];
    }

    /** False until the migration that adds the limits has run, so pay keeps working in the meantime. */
    private function ratesAreStored(): bool
    {
        return $this->ratesAreStored ??= Schema::hasColumn('sys_hr_nssf_rates', 'upper_earnings_limit');
    }
}

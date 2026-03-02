<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait TaxCalculator
{
    /**
     * Calculate progressive tax for an amount using um_tax_bands table.
     * Returns tax amount (numeric).
     */
    public function calculateTax(float $amount): float
    {
        try {
            $tax_relief_amount = DB::table('cb_system_configs')
                ->where('setting', 'tax_relief_amount')
                ->value('value');
            $minimum_taxable_icome = DB::table('cb_system_configs')
                ->where('setting', 'minimum_taxable_icome')
                ->value('value');

            $bands = DB::table('tax_bands')->orderBy('range_from')->get();

            if ($bands->isEmpty() || $amount <= $minimum_taxable_icome) {
                return 0.0;
            }

            $tax = 0.0;

            foreach ($bands as $band) {
                $from = (float) $band->range_from;
                $to = isset($band->range_to) ? (float) $band->range_to : null;
                $rate = (float) $band->tax_rate;

                if ($to === null) {
                    if ($amount > $from) {
                        $portion = $amount - $from;
                        $tax += ($portion * $rate) / 100.0;
                    }
                    break;
                }

                if ($amount > $from) {
                    $portion = min($amount, $to) - $from;
                    $tax += ($portion * $rate) / 100.0;
                }
            }

            $tax = max(0.0, $tax - $tax_relief_amount);

            return round($tax, 2);
        } catch (\Exception $e) {
            Log::error('Tax calculation failed: ' . $e->getMessage());
            return 0.0;
        }
    }
}

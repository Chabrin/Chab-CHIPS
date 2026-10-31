<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\CarbonImmutable;

class EmployeeContractExpiry
{
    public function tracking(Contract $contract): array
    {
        $today = CarbonImmutable::today('Africa/Nairobi');
        if (!$contract->date_start || !$contract->date_end) {
            return ['status' => 'missing_dates', 'label' => 'Missing dates', 'days_remaining' => null];
        }

        $start = CarbonImmutable::parse($contract->date_start)->startOfDay();
        $end = CarbonImmutable::parse($contract->date_end)->startOfDay();
        $days = (int) $today->diffInDays($end, false);
        $status = match (true) {
            $end->lessThan($start) => 'invalid_dates',
            $days < 0 => 'expired',
            $start->greaterThan($today) => 'upcoming',
            $days <= 30 => 'expiring_soon',
            default => 'active',
        };
        $labels = ['invalid_dates' => 'Invalid dates', 'expired' => 'Expired', 'upcoming' => 'Upcoming', 'expiring_soon' => 'Expiring soon', 'active' => 'Active'];

        return ['status' => $status, 'label' => $labels[$status], 'days_remaining' => $days];
    }

    /** Catch up within the current reminder window if a daily run was missed. */
    public function reminder(int $days): ?int
    {
        return match (true) {
            $days <= 0 => 0,
            $days <= 7 => 7,
            $days <= 14 => 14,
            $days <= 30 => 30,
            default => null,
        };
    }
}

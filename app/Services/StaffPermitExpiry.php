<?php

namespace App\Services;

use App\Models\Staff;
use Carbon\CarbonImmutable;

/**
 * Work permits (and Alien IDs) that are about to run out or already have, for staff who have not left. Staff who left are not listed.
 */
class StaffPermitExpiry
{
    /** How many days ahead a permit is counted as due. */
    public const WINDOW_DAYS = 60;

    /**
     * Counts for the reminder, and the people behind them.
     *
     * @return array{as_of: string, through: string, expired: int, due_14: int, due_30: int, due_60: int, staff: array<int, array>}
     */
    public function summary(): array
    {
        $today = CarbonImmutable::today('Africa/Nairobi');
        $through = $today->addDays(self::WINDOW_DAYS);

        $staff = Staff::whereNotNull('work_permit_expiry')
            ->where('current_status_id', '!=', 3)
            ->whereDate('work_permit_expiry', '<=', $through->toDateString())
            ->orderBy('work_permit_expiry')
            ->get(['id', 'full_names', 'staff_number', 'work_permit_number', 'work_permit_expiry']);

        $rows = $staff->map(function (Staff $member) use ($today) {
            $days = (int) $today->diffInDays(CarbonImmutable::parse($member->work_permit_expiry)->startOfDay(), false);

            return [
                'id' => $member->id,
                'name' => $member->full_names,
                'staff_number' => $member->staff_number,
                'permit' => $member->work_permit_number,
                'expires' => CarbonImmutable::parse($member->work_permit_expiry)->toDateString(),
                'days' => $days,
            ];
        });

        return [
            'as_of' => $today->toDateString(),
            'through' => $through->toDateString(),
            'expired' => $rows->where('days', '<', 0)->count(),
            'due_14' => $rows->filter(fn ($r) => $r['days'] >= 0 && $r['days'] <= 14)->count(),
            'due_30' => $rows->filter(fn ($r) => $r['days'] >= 0 && $r['days'] <= 30)->count(),
            'due_60' => $rows->filter(fn ($r) => $r['days'] >= 0)->count(),
            'staff' => $rows->values()->all(),
        ];
    }

    /**
     * The one line the bell shows, or null when nothing needs attention.
     */
    public function message(array $summary): ?string
    {
        $due = $summary['due_60'];
        $expired = $summary['expired'];
        if ($due === 0 && $expired === 0) {
            return null;
        }

        $parts = [];
        if ($expired > 0) {
            $parts[] = $expired . ($expired === 1 ? ' work permit has' : ' work permits have') . ' already expired';
        }
        if ($due > 0) {
            $parts[] = $due . ($due === 1 ? ' expires' : ' expire') . ' within ' . self::WINDOW_DAYS . ' days'
                . ($summary['due_14'] > 0 ? ' (' . $summary['due_14'] . ' within 14 days)' : '');
        }

        return ucfirst(implode('; ', $parts)) . '. Check Employees for the staff concerned.';
    }
}

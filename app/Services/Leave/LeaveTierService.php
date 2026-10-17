<?php

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\LeaveTypeTier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveTierService
{
    /**
     * Whether a leave type has pay tiers configured.
     *
     * @param int $leaveTypeId
     * @return bool
     */
    public function hasTiers(int $leaveTypeId): bool {
        return LeaveTypeTier::where('leave_type_id', $leaveTypeId)->exists();
    }

    /**
     * Re-tag the days for a request's staff member and leave type.
     * Never throws: a tagging problem must not block an approval.
     *
     * @param LeaveRequest $request
     * @return void
     */
    public function retagForRequest(LeaveRequest $request): void {
        try {
            $years = array_unique([
                Carbon::parse($request->start_date)->year,
                Carbon::parse($request->end_date)->year,
            ]);

            foreach ($years as $year) {
                $this->retag((int) $request->staff_id, (int) $request->leave_type_id, (int) $year);
            }
        } catch (\Throwable $e) {
            Log::error('Leave tier retag failed for request ' . $request->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Re-tag all confirmed (status 5) days for this staff/type/year in date order.
     *
     * @param int $staffId
     * @param int $leaveTypeId
     * @param int $year
     * @return void
     */
    public function retag(int $staffId, int $leaveTypeId, int $year): void {
        $tiers = LeaveTypeTier::where('leave_type_id', $leaveTypeId)->orderBy('tier_order')->get();
        if ($tiers->isEmpty()) {
            return;
        }

        $d = 'sys_hr_leave_requests_234_repeat';

        DB::transaction(function () use ($tiers, $staffId, $leaveTypeId, $year, $d) {
            $days = LeaveRequestDaysTaken::query()
                ->join('sys_hr_leave_requests as r', 'r.id', '=', "$d.parent_id")
                ->where('r.staff_id', $staffId)
                ->where('r.leave_type_id', $leaveTypeId)
                ->whereYear("$d.leave_date", $year)
                ->where("$d.current_status_id", 5)
                ->orderBy("$d.leave_date")
                ->select("$d.*")
                ->get();

            foreach ($days as $i => $day) {
                $tier = $this->tierForPosition($tiers, $i + 1);
                $day->pay_rate   = $tier->pay_rate;
                $day->tier_order = $tier->tier_order;
                $day->save();
            }
        });
    }

    /**
     * Preview how a number of new days would fall across the leave type's tiers, given the days already used this year.
     * Returns null when the leave type has no tiers.
     *
     * @param int $staffId
     * @param int $leaveTypeId
     * @param int $year
     * @param int $requestedDays
     * @return array{used_this_year: int, summary_text: string, tiers: array}|null
     */
    public function breakdown(int $staffId, int $leaveTypeId, int $year, int $requestedDays): ?array {
        $tiers = LeaveTypeTier::where('leave_type_id', $leaveTypeId)->orderBy('tier_order')->get();
        if ($tiers->isEmpty()) {
            return null;
        }

        $used = LeaveRequestDaysTaken::query()
            ->join('sys_hr_leave_requests as r', 'r.id', '=', 'sys_hr_leave_requests_234_repeat.parent_id')
            ->where('r.staff_id', $staffId)
            ->where('r.leave_type_id', $leaveTypeId)
            ->whereYear('sys_hr_leave_requests_234_repeat.leave_date', $year)
            ->where('sys_hr_leave_requests_234_repeat.current_status_id', 5)
            ->count();

        $counts = [];
        for ($i = 1; $i <= $requestedDays; $i++) {
            $tier = $this->tierForPosition($tiers, $used + $i);
            $counts[$tier->tier_order] = ($counts[$tier->tier_order] ?? 0) + 1;
        }

        $parts = [];
        $rows = [];
        foreach ($tiers as $tier) {
            if (!empty($counts[$tier->tier_order])) {
                $parts[] = $counts[$tier->tier_order] . ' day(s) at ' . $tier->label . ' (' . rtrim(rtrim((string) $tier->pay_rate, '0'), '.') . '%)';
                $rows[] = ['label' => $tier->label, 'pay_rate' => $tier->pay_rate, 'days' => $counts[$tier->tier_order]];
            }
        }

        return [
            'used_this_year' => $used,
            'summary_text' => $parts ? implode(', ', $parts) : 'No chargeable days in this range',
            'tiers' => $rows,
        ];
    }

    /**
     * The tier a given day position (1 = first day of the year) falls in.
     *
     * @param Collection $tiers
     * @param int $position
     * @return LeaveTypeTier
     */
    private function tierForPosition(Collection $tiers, int $position): LeaveTypeTier {
        $upTo = 0;
        foreach ($tiers as $tier) {
            if ($tier->days === null) {
                return $tier;
            }
            $upTo += $tier->days;
            if ($position <= $upTo) {
                return $tier;
            }
        }
        return $tiers->last();
    }
}
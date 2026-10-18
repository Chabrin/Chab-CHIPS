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
     * Re-tag all confirmed (status 5) days for this staff/type/year in date order, counting each day by its fraction.
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

            // Walk the days in date order; a day that straddles a tier boundary is paid at the blend of the tiers it covers
            $daysBefore = 0.0;
            foreach ($days as $day) {
                $fraction = (float) ($day->day_fraction ?? 1);
                $parts = $this->split($tiers, $daysBefore, max($fraction, 0.01));
                $paid = array_sum(array_map(fn($part) => $part[0]->pay_rate * $part[1], $parts));

                $day->pay_rate   = round($paid / array_sum(array_column($parts, 1)), 2);
                $day->tier_order = $parts[0][0]->tier_order;
                $day->save();

                $daysBefore += $fraction;
            }
        });
    }

    /**
     * Preview how new days would fall across the leave type's tiers, given the days already used this year.
     * Pass the size of each day (a Saturday is 0.5) when the dates are known, or a plain number of days.
     * Returns null when the leave type has no tiers.
     *
     * @param int $staffId
     * @param int $leaveTypeId
     * @param int $year
     * @param float|array $requestedDays
     * @return array{used_this_year: float, summary_text: string, tiers: array}|null
     */
    public function breakdown(int $staffId, int $leaveTypeId, int $year, float|array $requestedDays): ?array {
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
            ->sum(DB::raw('COALESCE(day_fraction, 1)'));
        $used = (float) $used;

        // A plain number of days is whole days with any remainder as a part day
        if (!is_array($requestedDays)) {
            $requestedDays = array_merge(array_fill(0, (int) floor($requestedDays), 1.0), fmod($requestedDays, 1.0) > 0.0001 ? [fmod($requestedDays, 1.0)] : []);
        }

        $counts = [];
        $position = $used;
        foreach ($requestedDays as $size) {
            foreach ($this->split($tiers, $position, (float) $size) as [$tier, $days]) {
                $counts[$tier->tier_order] = ($counts[$tier->tier_order] ?? 0) + $days;
            }
            $position += (float) $size;
        }

        $parts = [];
        $rows = [];
        foreach ($tiers as $tier) {
            if (!empty($counts[$tier->tier_order])) {
                $days = round($counts[$tier->tier_order], 2);
                $parts[] = $days . ' day(s) at ' . $tier->label . ' (' . (float) $tier->pay_rate . '%)';
                $rows[] = ['label' => $tier->label, 'pay_rate' => $tier->pay_rate, 'days' => $days];
            }
        }

        return [
            'used_this_year' => round($used, 2),
            'summary_text' => $parts ? implode(', ', $parts) : 'No chargeable days in this range',
            'tiers' => $rows,
        ];
    }

    /**
     * Split a stretch of leave starting after `$position` days across the tiers. A stretch that crosses a tier
     * boundary comes back as several parts.
     *
     * @param Collection $tiers
     * @param float $position days already used before this stretch
     * @param float $days size of the stretch
     * @return array list of [LeaveTypeTier, days]
     */
    private function split(Collection $tiers, float $position, float $days): array {
        $parts = [];

        while ($days > 0.0001) {
            [$tier, $room] = $this->tierAt($tiers, $position);
            $take = $room === null ? $days : min($days, $room);
            $parts[] = [$tier, $take];
            $position += $take;
            $days -= $take;
        }

        return $parts ?: [[$this->tierAt($tiers, $position)[0], 0.0]];
    }

    /**
     * The tier that day position `$position` (days used so far) falls in, and how many days are left in it
     * (null when the tier has no limit).
     *
     * @param Collection $tiers
     * @param float $position
     * @return array [LeaveTypeTier, float|null]
     */
    private function tierAt(Collection $tiers, float $position): array {
        $upTo = 0.0;

        foreach ($tiers as $tier) {
            if ($tier->days === null) {
                return [$tier, null];
            }

            $upTo += $tier->days;
            if ($position + 0.0001 < $upTo) {
                return [$tier, $upTo - $position];
            }
        }

        return [$tiers->last(), null];
    }
}

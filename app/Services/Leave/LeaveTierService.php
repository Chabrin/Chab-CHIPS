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
    public function hasTiers(int $leaveTypeId): bool
    {
        return LeaveTypeTier::where('leave_type_id', $leaveTypeId)->exists();
    }

    /**
     * Re-tag the days for a request's staff member and leave type.
     * Never throws: a tagging problem must not block an approval.
     */
    public function retagForRequest(LeaveRequest $request): void
    {
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

    /** Re-tag all confirmed (status 5) days for this staff/type/year in date order. */
    public function retag(int $staffId, int $leaveTypeId, int $year): void
    {
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

    private function tierForPosition(Collection $tiers, int $position): LeaveTypeTier
    {
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
<?php

namespace App\Services\Leave;

use App\Jobs\SendApprovalNotification;
use App\Models\ConfirmationLog;
use App\Models\LeaveCalendarPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    private const SECTION = 'Leave Application';
    private const STATUS_PENDING = 1;
    private const STATUS_PARTIAL = 24;
    private const STATUS_APPROVED = 5;
    private const STATUS_REJECTED = 41;

    public function __construct(private LeaveCalendarService $calendar, private LeaveTierService $tiers) {
    }

    /**
     * Approve a pending or partially approved leave request in a single step: build and confirm the days
     * taken from the leave type's counting rules, deduct the plan balance, log it and notify.
     *
     * @param LeaveRequest $request
     * @param int $approverId
     * @return array{success: bool, error?: string, used_days?: float}
     */
    public function quickApprove(LeaveRequest $request, int $approverId): array {
        if ($error = $this->actionableError($request)) {
            return ['success' => false, 'error' => $error];
        }

        $isPartialDay = $this->isPartialDay($request);
        $rules = $this->calendar->rulesFor((int) $request->leave_type_id);
        $dates = $isPartialDay
            ? collect([Carbon::parse($request->start_date)])
            : $this->calendar->countableDates($request->start_date, $request->end_date, (int) $request->leave_type_id);

        if ($dates->isEmpty()) {
            return ['success' => false, 'error' => 'The requested dates contain no chargeable leave days.'];
        }

        $usedDays = DB::transaction(function () use ($request, $approverId, $dates, $isPartialDay, $rules) {
            LeaveRequestDaysTaken::where('parent_id', $request->id)->delete();

            $usedDays = 0.0;

            foreach ($dates as $date) {
                // A partial day is charged by hours; otherwise by the day's weight (a Saturday is half a day)
                $fraction = $isPartialDay ? $this->partialDayUsedDays($request) : $this->calendar->dayWeight($date, $rules);

                LeaveRequestDaysTaken::create([
                    'parent_id' => $request->id,
                    'current_status_id' => self::STATUS_APPROVED,
                    'leave_date' => $isPartialDay ? $request->start_date : $date->format('Y-m-d') . ' 00:00:00',
                    'day_type_id' => $this->dayTypeFor($date),
                    'day_fraction' => $fraction,
                ]);

                $usedDays += $fraction;
            }

            $request->used_days = $usedDays;
            $request->current_status_id = self::STATUS_APPROVED;
            $request->save();

            $plan = $this->planFor($request);
            if ($plan) {
                $plan->balance = $plan->balance - $usedDays;
                $plan->save();
            }

            $this->log('approve', $request->id, $approverId, 2, [
                'quick_approval' => true,
                'used_days' => $usedDays,
                'is_partial_day' => $isPartialDay,
                'employee_id' => $request->staff_id,
                'leave_type_id' => $request->leave_type_id,
                'plan_id' => $plan ? $plan->id : null,
                'plan_balance_after' => $plan ? $plan->balance : null,
            ]);

            return $usedDays;
        });

        $this->tiers->retagForRequest($request);
        SendApprovalNotification::dispatch(self::SECTION, $request->id, $approverId);

        return ['success' => true, 'used_days' => $usedDays];
    }

    /**
     * Reject a pending or partially approved leave request and log it.
     *
     * @param LeaveRequest $request
     * @param int $rejecterId
     * @return array{success: bool, error?: string}
     */
    public function quickReject(LeaveRequest $request, int $rejecterId): array {
        if ($error = $this->actionableError($request)) {
            return ['success' => false, 'error' => $error];
        }

        DB::transaction(function () use ($request, $rejecterId) {
            $request->current_status_id = self::STATUS_REJECTED;
            $request->save();

            $this->log('reject', $request->id, $rejecterId, null, ['quick_approval' => true]);
        });

        return ['success' => true];
    }

    /**
     * Reason a request can't be approved or rejected right now, or null when it can.
     *
     * @param LeaveRequest $request
     * @return string|null
     */
    private function actionableError(LeaveRequest $request): ?string {
        if (!in_array((int) $request->current_status_id, [self::STATUS_PENDING, self::STATUS_PARTIAL], true)) {
            return 'This record is no longer pending and cannot be actioned.';
        }

        $staff = Staff::find($request->staff_id);
        if (!$staff) {
            return 'The referenced staff member no longer exists.';
        }

        if (in_array((int) $staff->current_status_id, [2, 3], true)) {
            return 'The referenced staff member is inactive or terminated.';
        }

        return null;
    }

    /**
     * A same-day request with custom start/end times is charged by hours rather than whole days.
     *
     * @param LeaveRequest $request
     * @return bool
     */
    private function isPartialDay(LeaveRequest $request): bool {
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $hasCustomTimes = !($start->format('H:i') === '08:00' && $end->format('H:i') === '17:00');

        return $start->isSameDay($end) && $hasCustomTimes;
    }

    /**
     * Days charged for a partial-day request (hours / 8, two decimals).
     *
     * @param LeaveRequest $request
     * @return float
     */
    private function partialDayUsedDays(LeaveRequest $request): float {
        $hours = abs(Carbon::parse($request->end_date)->diffInSeconds(Carbon::parse($request->start_date))) / 3600;

        return round($hours / 8, 2);
    }

    /**
     * Day type id used on LeaveRequestDaysTaken: 1 weekday, 2 Saturday, 3 Sunday.
     *
     * @param Carbon $date
     * @return int
     */
    private function dayTypeFor(Carbon $date): int {
        return match ($date->dayOfWeekIso) {
            6 => 2,
            7 => 3,
            default => 1,
        };
    }

    /**
     * The approved calendar plan covering the request's year for this staff member and leave type.
     *
     * @param LeaveRequest $request
     * @return LeaveCalendarPlan|null
     */
    private function planFor(LeaveRequest $request): ?LeaveCalendarPlan {
        return LeaveCalendarPlan::where('employee_id', $request->staff_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->whereYear('start_date', Carbon::parse($request->start_date)->year)
            ->where('current_status_id', self::STATUS_APPROVED)
            ->first();
    }

    /**
     * Write an entry to the approvals confirmation log.
     *
     * @param string $action
     * @param int $id
     * @param int $userId
     * @param int|null $stage
     * @param array $metadata
     * @return void
     */
    private function log(string $action, int $id, int $userId, ?int $stage, array $metadata): void {
        ConfirmationLog::create([
            'section' => self::SECTION,
            'model' => LeaveRequest::class,
            'action' => $action,
            'ids' => [$id],
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
            'approval_stage' => $stage,
            'metadata' => $metadata,
        ]);
    }
}

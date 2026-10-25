<?php

namespace App\Services\Leave;

use App\Jobs\SendApprovalNotification;
use App\Models\ConfirmationLog;
use App\Models\LeaveCalendarPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveApprovalService
{
    private const SECTION = 'Leave Application';
    private const STATUS_PENDING = 1;
    private const STATUS_PARTIAL = 24;
    private const STATUS_APPROVED = 5;
    private const STATUS_REJECTED = 41;
    private const NO_LONGER_PENDING = 'This record is no longer pending and cannot be actioned.';

    public function __construct(private LeaveCalendarService $calendar, private LeaveTierService $tiers, private LeaveApprovalRouter $router) {
    }

    /**
     * Route a newly filed (or re-filed) leave request. A request that needs a supervisor stays pending (status 1);
     * one that goes straight to HR moves to the HR stage (status 24) with the skipped stage logged; the HR
     * approver's own request is approved on the spot.
     *
     * @param LeaveRequest $request
     * @param int $actorId
     * @return array{route: string, reason: string, status: int, auto_approved: bool, error?: string}
     */
    public function submit(LeaveRequest $request, int $actorId): array {
        $requester = Staff::find($request->staff_id);
        if (!$requester) {
            return ['route' => LeaveApprovalRouter::ROUTE_HR, 'reason' => 'no_staff', 'status' => (int) $request->current_status_id, 'auto_approved' => false];
        }

        $route = $this->router->routeFor($requester);

        // Automatic approval is for the HR approver's own application only: filed by someone else, it goes to HR like any other
        if ($route['route'] === LeaveApprovalRouter::ROUTE_AUTO && User::find($actorId)?->linkedStaff()?->id !== (int) $requester->id) {
            $route = ['route' => LeaveApprovalRouter::ROUTE_HR, 'reason' => 'filed_on_behalf', 'supervisor_position_id' => null];
        }

        $outcome = ['route' => $route['route'], 'reason' => $route['reason'], 'status' => self::STATUS_PENDING, 'auto_approved' => false];

        if ($route['route'] === LeaveApprovalRouter::ROUTE_SUPERVISOR) {
            return $outcome;
        }

        $this->moveToHrStage($request, $actorId, $route['reason']);
        $outcome['status'] = self::STATUS_PARTIAL;

        if ($route['route'] === LeaveApprovalRouter::ROUTE_AUTO) {
            $result = $this->hrApprove($request, $actorId, ['auto_approved' => true, 'rule' => $route['reason']]);

            if ($result['success']) {
                $outcome['status'] = self::STATUS_APPROVED;
                $outcome['auto_approved'] = true;
                $this->notifyDirectorsOfAutoApproval($request);
            } else {
                $outcome['error'] = $result['error'];
            }
        }

        return $outcome;
    }

    /**
     * Approve a request as the given user at whichever stage it is at: the supervisor stage for a request waiting
     * on a supervisor, otherwise the HR (final) stage. Checks the user is allowed to act on this request.
     *
     * @param LeaveRequest $request
     * @param User $user
     * @return array{success: bool, error?: string, status?: int, used_days?: float}
     */
    public function approve(LeaveRequest $request, User $user): array {
        if ($denial = $this->router->denial($user, $request)) {
            return ['success' => false, 'error' => $denial['error'], 'status' => $denial['status']];
        }

        if ((int) $request->current_status_id === self::STATUS_PENDING) {
            $requester = Staff::find($request->staff_id);
            $route = $this->router->routeFor($requester);

            if ($route['route'] === LeaveApprovalRouter::ROUTE_SUPERVISOR) {
                return $this->hodApprove($request, $user->id);
            }

            // Filed before routing existed and now waiting on HR only: move it to the HR stage first
            $this->moveToHrStage($request, $user->id, $route['reason']);
        }

        return $this->hrApprove($request, $user->id);
    }

    /**
     * Reject a request as the given user. Checks the user is allowed to act on this request.
     *
     * @param LeaveRequest $request
     * @param User $user
     * @return array{success: bool, error?: string, status?: int}
     */
    public function reject(LeaveRequest $request, User $user): array {
        if ($denial = $this->router->denial($user, $request)) {
            return ['success' => false, 'error' => $denial['error'], 'status' => $denial['status']];
        }

        return $this->quickReject($request, $user->id);
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
            if (!$this->lockPending($request, [self::STATUS_PENDING, self::STATUS_PARTIAL])) {
                return null;
            }

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

        if ($usedDays === null) {
            return ['success' => false, 'error' => self::NO_LONGER_PENDING];
        }

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

        $rejected = DB::transaction(function () use ($request, $rejecterId) {
            if (!$this->lockPending($request, [self::STATUS_PENDING, self::STATUS_PARTIAL])) {
                return false;
            }

            $request->current_status_id = self::STATUS_REJECTED;
            $request->status_changed_by = $rejecterId;
            $request->save();

            $this->log('reject', $request->id, $rejecterId, null, ['quick_approval' => true]);

            return true;
        });

        return $rejected ? ['success' => true] : ['success' => false, 'error' => self::NO_LONGER_PENDING];
    }

    /**
     * Reason a request can't be approved or rejected right now, or null when it can.
     *
     * @param LeaveRequest $request
     * @return string|null
     */
    private function actionableError(LeaveRequest $request): ?string {
        if (!in_array((int) $request->current_status_id, [self::STATUS_PENDING, self::STATUS_PARTIAL], true)) {
            return self::NO_LONGER_PENDING;
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
     * Take a row lock on the request and reload it, so two approvals racing for the same request can't both
     * pass the status check. Must run inside a transaction.
     *
     * @param LeaveRequest $request
     * @param array $statuses
     * @return bool
     */
    private function lockPending(LeaveRequest $request, array $statuses): bool {
        LeaveRequest::whereKey($request->id)->lockForUpdate()->first();
        $request->refresh();

        return in_array((int) $request->current_status_id, $statuses, true);
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
     * The approved annual leave plan covering the request's year for this staff member, or null for any other leave type.
     *
     * @param LeaveRequest $request
     * @return LeaveCalendarPlan|null
     */
    private function planFor(LeaveRequest $request): ?LeaveCalendarPlan {
        // Only annual leave is planned; any old plan for another leave type is not touched
        if (!$this->calendar->isAnnualLeaveType(LeaveType::whereKey($request->leave_type_id)->value('type_name'))) {
            return null;
        }

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

    /**
     * Move a request straight to the HR stage (status 24), skipping the supervisor stage, and log why it was skipped.
     *
     * @param LeaveRequest $request
     * @param int $actorId
     * @param string $reason
     * @return void
     */
    private function moveToHrStage(LeaveRequest $request, int $actorId, string $reason): void {
        DB::transaction(function () use ($request, $actorId, $reason) {
            if (!$this->lockPending($request, [self::STATUS_PENDING])) {
                return;
            }

            $request->current_status_id = self::STATUS_PARTIAL;
            $request->status_changed_by = null;
            $request->save();

            $this->log('route', $request->id, $actorId, null, [
                'stage' => 'supervisor_skipped',
                'reason' => $reason,
                'employee_id' => $request->staff_id,
                'leave_type_id' => $request->leave_type_id,
            ]);
        });
    }

    /**
     * The days that approving this request will charge, worked out from the leave type's counting rules, for the
     * approver to review before approving.
     *
     * @param LeaveRequest $request
     * @return array{days: array, total: float, is_partial_day: bool, hours: float|null}
     */
    public function previewDays(LeaveRequest $request): array {
        $isPartialDay = $this->isPartialDay($request);
        $rules = $this->calendar->rulesFor((int) $request->leave_type_id);
        $dates = $isPartialDay
            ? collect([Carbon::parse($request->start_date)])
            : $this->calendar->countableDates($request->start_date, $request->end_date, (int) $request->leave_type_id);

        $days = $dates->map(fn (Carbon $date) => [
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->format('l'),
            'type' => $this->dayTypeFor($date),
            'day_fraction' => $isPartialDay ? $this->partialDayUsedDays($request) : $this->calendar->dayWeight($date, $rules),
        ])->values()->all();

        return [
            'days' => $days,
            'total' => round(array_sum(array_column($days, 'day_fraction')), 2),
            'is_partial_day' => $isPartialDay,
            'hours' => $isPartialDay ? round(abs(Carbon::parse($request->end_date)->diffInSeconds(Carbon::parse($request->start_date))) / 3600, 2) : null,
        ];
    }

    /**
     * Tell the directors that the HR approver's own leave was approved automatically, as an in-app notification.
     *
     * @param LeaveRequest $request
     * @return void
     */
    private function notifyDirectorsOfAutoApproval(LeaveRequest $request): void {
        try {
            $name = Staff::whereKey($request->staff_id)->value('full_names') ?? 'The HR approver';
            $start = Carbon::parse($request->start_date)->format('d M Y');
            $end = Carbon::parse($request->end_date)->format('d M Y');

            User::whereHasRole('directors')->get()->each(function (User $director) use ($request, $name, $start, $end) {
                Notification::create([
                    'type' => 'LeaveAutoApproved',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $director->id,
                    'data' => ['leave_request_id' => $request->id, 'staff_id' => $request->staff_id],
                    'title' => 'Leave approved automatically',
                    'message' => "{$name}'s leave ({$start} to {$end}) was approved automatically because they are the HR approver.",
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('Could not notify directors of an auto-approved leave: ' . $e->getMessage());
        }
    }

    /**
     * Stage 1: supervisor approval. Moves status 1 (Pending) -> 24 (Partial) and records who approved, so the same
     * person can't give the HR approval too. Does not charge leave days yet and sends no email.
     *
     * @param LeaveRequest $request
     * @param int $approverId
     * @return array{success: bool, error?: string}
     */
    public function hodApprove(LeaveRequest $request, int $approverId): array {
        if ((int) $request->current_status_id !== self::STATUS_PENDING) {
            return ['success' => false, 'error' => 'Only pending applications can be recommended by the supervisor.'];
        }

        if ($error = $this->actionableError($request)) {
            return ['success' => false, 'error' => $error];
        }

        $approved = DB::transaction(function () use ($request, $approverId) {
            if (!$this->lockPending($request, [self::STATUS_PENDING])) {
                return false;
            }

            $request->current_status_id = self::STATUS_PARTIAL;
            $request->status_changed_by = $approverId;
            $request->save();

            $this->log('partial_approve', $request->id, $approverId, 1, [
                'stage' => 'hod_partial_approval',
                'employee_id' => $request->staff_id,
                'leave_type_id' => $request->leave_type_id,
            ]);

            return true;
        });

        return $approved ? ['success' => true] : ['success' => false, 'error' => self::NO_LONGER_PENDING];
    }

    /**
     * Stage 2: HR final approval. Moves status 24 (Partial) -> 5 (Approved). Calculates countable days, creates
     * LeaveRequestDaysTaken, deducts the plan balance and logs stage 2.
     *
     * @param LeaveRequest $request
     * @param int $approverId
     * @param array $metadata extra entries for the stage-2 log (e.g. auto_approved)
     * @return array{success: bool, error?: string, used_days?: float}
     */
    public function hrApprove(LeaveRequest $request, int $approverId, array $metadata = []): array {
        if ((int) $request->current_status_id !== self::STATUS_PARTIAL) {
            return ['success' => false, 'error' => 'Application must be approved by the supervisor first.'];
        }

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

        $usedDays = DB::transaction(function () use ($request, $approverId, $dates, $isPartialDay, $rules, $metadata) {
            if (!$this->lockPending($request, [self::STATUS_PARTIAL])) {
                return null;
            }

            LeaveRequestDaysTaken::where('parent_id', $request->id)->delete();

            $usedDays = 0.0;

            foreach ($dates as $date) {
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
            $request->status_changed_by = $approverId;
            $request->save();

            $plan = $this->planFor($request);
            if ($plan) {
                $plan->balance = $plan->balance - $usedDays;
                $plan->save();
            }

            $this->log('approve', $request->id, $approverId, 2, array_merge([
                'stage' => 'hr_final_approval',
                'used_days' => $usedDays,
                'is_partial_day' => $isPartialDay,
                'employee_id' => $request->staff_id,
                'leave_type_id' => $request->leave_type_id,
                'plan_id' => $plan ? $plan->id : null,
                'plan_balance_after' => $plan ? $plan->balance : null,
            ], $metadata));

            return $usedDays;
        });

        if ($usedDays === null) {
            return ['success' => false, 'error' => self::NO_LONGER_PENDING];
        }

        $this->tiers->retagForRequest($request);
        SendApprovalNotification::dispatch(self::SECTION, $request->id, $approverId);

        return ['success' => true, 'used_days' => $usedDays];
    }
}

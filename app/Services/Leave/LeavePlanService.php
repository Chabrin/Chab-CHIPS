<?php

namespace App\Services\Leave;

use App\Models\LeaveCalendarPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * A leave plan is one staff member's allowance for one leave type in one year (entitled days and a running balance).
 * Only annual leave is planned: its plan is created the first time the person files for the year, from their hire
 * date, unless one already exists. Every other leave type is measured against the leave type's own entitled days.
 */
class LeavePlanService
{
    private const STATUS_APPROVED = 5;
    private const STATUS_PENDING = 1;
    private const STATUS_PARTIAL = 24;

    public function __construct(private LeaveCalendarService $calendar) {
    }

    /**
     * Whether this leave type has a yearly plan (annual leave only).
     *
     * @param LeaveType|null $leaveType
     * @return bool
     */
    public function isPlanned(?LeaveType $leaveType): bool {
        return $leaveType !== null && $this->calendar->isAnnualLeaveType($leaveType->type_name);
    }

    /**
     * The staff member's plan for the leave type and year, preferring an approved one when there are several.
     *
     * @param int $staffId
     * @param int $leaveTypeId
     * @param int $year
     * @return LeaveCalendarPlan|null
     */
    public function findPlan(int $staffId, int $leaveTypeId, int $year): ?LeaveCalendarPlan {
        return LeaveCalendarPlan::where('employee_id', $staffId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereYear('start_date', $year)
            ->orderByRaw('CASE WHEN current_status_id = ' . self::STATUS_APPROVED . ' THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Days already taken (approved or completed requests starting in the year).
     *
     * @param int $staffId
     * @param int $leaveTypeId
     * @param int $year
     * @return float
     */
    public function usedDays(int $staffId, int $leaveTypeId, int $year): float {
        return (float) LeaveRequest::whereYear('start_date', $year)
            ->where('leave_type_id', $leaveTypeId)
            ->where('staff_id', $staffId)
            ->whereIn('current_status_id', [5, 49])
            ->sum('days');
    }

    /**
     * Days asked for in requests still waiting for approval, which are not on the balance yet but are spoken for.
     *
     * @param int $staffId
     * @param int $leaveTypeId
     * @param int $year
     * @param int|null $ignoreRequestId a request being edited, which doesn't count against itself
     * @return float
     */
    public function reservedDays(int $staffId, int $leaveTypeId, int $year, ?int $ignoreRequestId = null): float {
        return (float) LeaveRequest::whereYear('start_date', $year)
            ->where('leave_type_id', $leaveTypeId)
            ->where('staff_id', $staffId)
            ->whereIn('current_status_id', [self::STATUS_PENDING, self::STATUS_PARTIAL])
            ->when($ignoreRequestId, fn ($query) => $query->where('id', '!=', $ignoreRequestId))
            ->sum('requested_days');
    }

    /**
     * Entitled days for the year, or null when they can't be known: annual leave is pro-rated from the hire date,
     * so it needs one; other leave types use the leave type's own entitled days.
     *
     * @param Staff $staff
     * @param LeaveType $leaveType
     * @param int $year
     * @return float|null
     */
    public function entitlementFor(Staff $staff, LeaveType $leaveType, int $year): ?float {
        if (!$this->isPlanned($leaveType)) {
            return (float) ($leaveType->entitled_days ?? 0);
        }

        $hireDate = $staff->getRawOriginal('hire_date');

        return $this->calendar->parseHireDate($hireDate) ? $this->calendar->calculateAnnualEntitlement($hireDate, $year) : null;
    }

    /**
     * The plan to measure a request against, creating the year's annual plan on first use. An existing plan is
     * always used as it is, and the creation re-checks under a lock on the staff member so two requests arriving
     * together can't both create one.
     *
     * @param Staff $staff
     * @param LeaveType $leaveType
     * @param int $year
     * @param int|null $userId
     * @return array{plan: LeaveCalendarPlan|null, created: bool, error: string|null}
     */
    public function ensurePlan(Staff $staff, LeaveType $leaveType, int $year, ?int $userId = null): array {
        $existing = $this->findPlan((int) $staff->id, (int) $leaveType->id, $year);
        if ($existing) {
            return $this->usablePlan($existing, $year, false);
        }

        $entitled = $this->entitlementFor($staff, $leaveType, $year);
        if ($entitled === null) {
            return ['plan' => null, 'created' => false, 'error' => $this->noHireDateMessage($year)];
        }

        return DB::transaction(function () use ($staff, $leaveType, $year, $userId, $entitled) {
            Staff::whereKey($staff->id)->lockForUpdate()->first();

            $existing = $this->findPlan((int) $staff->id, (int) $leaveType->id, $year);
            if ($existing) {
                return $this->usablePlan($existing, $year, false);
            }

            $plan = LeaveCalendarPlan::create([
                'employee_id' => $staff->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'entitled_days' => $entitled,
                'balance' => $entitled - $this->usedDays((int) $staff->id, (int) $leaveType->id, $year),
                'current_status_id' => self::STATUS_APPROVED,
                'created_by' => $userId,
                'date_time' => now(),
            ]);

            return ['plan' => $plan, 'created' => true, 'error' => null];
        });
    }

    /**
     * What a request can be measured against right now, without creating anything: the entitlement, the balance
     * and what is left after requests still waiting for approval. For annual leave with no plan yet this is
     * what the plan will hold when it is created.
     *
     * @param Staff $staff
     * @param LeaveType $leaveType
     * @param int $year
     * @param int|null $ignoreRequestId a request being edited
     * @return array{entitled: float|null, balance: float|null, reserved: float, available: float|null, plan: LeaveCalendarPlan|null, error: string|null}
     */
    public function availableDays(Staff $staff, LeaveType $leaveType, int $year, ?int $ignoreRequestId = null): array {
        $plan = $this->isPlanned($leaveType) ? $this->findPlan((int) $staff->id, (int) $leaveType->id, $year) : null;
        $figures = ['entitled' => null, 'balance' => null, 'reserved' => 0.0, 'available' => null, 'plan' => $plan, 'error' => null];

        if ($plan) {
            if ((int) $plan->current_status_id !== self::STATUS_APPROVED) {
                $figures['error'] = $this->notApprovedMessage($year);

                return $figures;
            }

            $figures['entitled'] = (float) $plan->entitled_days;
            $figures['balance'] = (float) $plan->balance;
        } else {
            $entitled = $this->entitlementFor($staff, $leaveType, $year);
            if ($entitled === null) {
                $figures['error'] = $this->noHireDateMessage($year);

                return $figures;
            }

            $figures['entitled'] = $entitled;
            $figures['balance'] = $entitled - $this->usedDays((int) $staff->id, (int) $leaveType->id, $year);
        }

        $figures['reserved'] = $this->reservedDays((int) $staff->id, (int) $leaveType->id, $year, $ignoreRequestId);
        $figures['available'] = max(0, $figures['balance'] - $figures['reserved']);

        return $figures;
    }

    /**
     * An existing plan is only usable once approved.
     *
     * @param LeaveCalendarPlan $plan
     * @param int $year
     * @param bool $created
     * @return array{plan: LeaveCalendarPlan|null, created: bool, error: string|null}
     */
    private function usablePlan(LeaveCalendarPlan $plan, int $year, bool $created): array {
        if ((int) $plan->current_status_id !== self::STATUS_APPROVED) {
            return ['plan' => null, 'created' => false, 'error' => $this->notApprovedMessage($year)];
        }

        return ['plan' => $plan, 'created' => $created, 'error' => null];
    }

    /**
     * Why annual leave can't be filed when the hire date is unknown.
     *
     * @param int $year
     * @return string
     */
    private function noHireDateMessage(int $year): string {
        return "There is no hire date on record, so the {$year} annual leave plan can't be created. Ask HR to add the hire date or create the plan.";
    }

    /**
     * Why annual leave can't be filed against a plan that isn't approved.
     *
     * @param int $year
     * @return string
     */
    private function notApprovedMessage(int $year): string {
        return "The {$year} annual leave plan is not approved yet. Ask HR to approve it.";
    }
}

<?php

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides who approves a leave request. The route comes from the position hierarchy
 * (sys_set_staff_positions.reports_to_id): staff at the bottom of the tree go to the holder of the position they
 * report to and then to HR; supervisors, staff with no position and staff whose supervisor can't be reached go
 * straight to HR; the HR approver's own leave is approved automatically.
 */
class LeaveApprovalRouter
{
    public const ROUTE_SUPERVISOR = 'supervisor';
    public const ROUTE_HR = 'hr';
    public const ROUTE_AUTO = 'auto';

    private const STATUS_PENDING = 1;
    private const STATUS_PARTIAL = 24;

    /** Roles that may give the HR (final) approval; the update_leave_applications permission also counts. */
    private const HR_ROLES = ['hr_manager', 'directors', 'superadministrator'];
    private const HR_PERMISSION = 'update_leave_applications';

    /** A requester holding this role is the HR approver, so their own leave is approved automatically. */
    private const AUTO_APPROVE_ROLE = 'hr_manager';

    /** @var array<int, bool> */
    private array $supervisorPositions = [];

    /** @var array<int, Collection> */
    private array $supervisorStaff = [];

    /**
     * Where a leave request from this staff member goes first, and why.
     *
     * @param Staff $requester
     * @return array{route: string, reason: string, supervisor_position_id: int|null}
     */
    public function routeFor(Staff $requester): array {
        $user = $requester->userLink?->user;
        if ($user && $user->hasRole(self::AUTO_APPROVE_ROLE)) {
            return $this->result(self::ROUTE_AUTO, 'hr_approver_own_leave');
        }

        $position = $requester->position_id ? Position::find($requester->position_id) : null;
        if (!$position) {
            return $this->result(self::ROUTE_HR, 'no_position');
        }

        if ($this->isSupervisorPosition((int) $position->id)) {
            return $this->result(self::ROUTE_HR, 'is_supervisor');
        }

        if (!$position->reports_to_id) {
            return $this->result(self::ROUTE_HR, 'no_boss_position');
        }

        if ($this->supervisorStaffIds($requester)->isEmpty()) {
            return $this->result(self::ROUTE_HR, 'no_supervisor_with_system_access');
        }

        return $this->result(self::ROUTE_SUPERVISOR, 'position_supervisor', (int) $position->reports_to_id);
    }

    /**
     * Whether other positions report to this one, which makes its holders supervisors.
     *
     * @param int $positionId
     * @return bool
     */
    public function isSupervisorPosition(int $positionId): bool {
        return $this->supervisorPositions[$positionId] ??= Position::where('reports_to_id', $positionId)->exists();
    }

    /**
     * Ids of the staff who can approve this requester's leave as supervisor: active holders of the position
     * the requester reports to who have an unblocked system user, excluding the requester.
     *
     * @param Staff $requester
     * @return Collection
     */
    public function supervisorStaffIds(Staff $requester): Collection {
        $bossPositionId = $requester->position_id ? Position::whereKey($requester->position_id)->value('reports_to_id') : null;
        if (!$bossPositionId) {
            return collect();
        }

        $holders = $this->supervisorStaff[$bossPositionId] ??= Staff::where('position_id', $bossPositionId)
            ->where('current_status_id', 1)
            ->whereHas('userLink.user', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('block')->orWhere('block', 0);
                });
            })
            ->pluck('id');

        return $holders->reject(fn ($id) => (int) $id === (int) $requester->id)->values();
    }

    /**
     * Whether the user can give the HR (final) approval.
     *
     * @param User $user
     * @return bool
     */
    public function isHrApprover(User $user): bool {
        return $user->hasRole(self::HR_ROLES) || $user->isAbleTo(self::HR_PERMISSION);
    }

    /**
     * A notification may open an application for its owner, their supervisor, or HR.
     * Viewing does not grant approval rights or bypass the current approval stage.
     */
    public function canReview(User $user, LeaveRequest $request): bool {
        if ($this->isHrApprover($user)) {
            return true;
        }
        $staffId = $user->linkedStaff()?->id;
        if (!$staffId) {
            return false;
        }
        if ((int) $staffId === (int) $request->staff_id) {
            return true;
        }
        $requester = $request->employee;
        return $requester && $this->supervisorStaffIds($requester)->contains($staffId);
    }

    /** Whether the user can approve leave at all. */
    public function isApprover(User $user): bool {
        if ($this->isHrApprover($user)) {
            return true;
        }

        $staff = $user->linkedStaff();

        return $staff && $staff->position_id && $this->isSupervisorPosition((int) $staff->position_id);
    }

    /**
     * Why the user can't approve or reject this request right now, or null when they can.
     *
     * @param User $user
     * @param LeaveRequest $request
     * @return string|null
     */
    public function canAct(User $user, LeaveRequest $request): ?string {
        return $this->denial($user, $request)['error'] ?? null;
    }

    /**
     * Why the user can't act on this request, with the HTTP status that fits: 403 when they aren't allowed to,
     * 422 when the request itself can't be actioned. Null when they can.
     *
     * @param User $user
     * @param LeaveRequest $request
     * @return array{error: string, status: int}|null
     */
    public function denial(User $user, LeaveRequest $request): ?array {
        $staff = $user->linkedStaff();
        if ($staff && (int) $staff->id === (int) $request->staff_id) {
            return ['error' => 'You cannot approve your own leave application.', 'status' => 403];
        }

        $status = (int) $request->current_status_id;

        if ($status === self::STATUS_PARTIAL) {
            if (!$this->isHrApprover($user)) {
                return ['error' => 'Only HR can give the final approval.', 'status' => 403];
            }

            if ($request->status_changed_by && (int) $request->status_changed_by === (int) $user->id) {
                return ['error' => 'The same person cannot approve both stages. A different approver is required.', 'status' => 403];
            }

            return null;
        }

        if ($status !== self::STATUS_PENDING) {
            return ['error' => 'This record is no longer pending and cannot be actioned.', 'status' => 422];
        }

        $requester = Staff::find($request->staff_id);
        if (!$requester) {
            return ['error' => 'The referenced staff member no longer exists.', 'status' => 422];
        }

        if ($this->routeFor($requester)['route'] === self::ROUTE_SUPERVISOR) {
            return $staff && $this->supervisorStaffIds($requester)->contains($staff->id)
                ? null
                : ['error' => 'This request is waiting for the requester\'s supervisor.', 'status' => 403];
        }

        return $this->isHrApprover($user) ? null : ['error' => 'This request goes straight to HR.', 'status' => 403];
    }

    /**
     * Who a pending request is really waiting on: 'supervisor', 'hr', or null once it is no longer pending.
     *
     * @param LeaveRequest $request
     * @return string|null
     */
    public function waitingOn(LeaveRequest $request): ?string {
        $status = (int) $request->current_status_id;

        if ($status === self::STATUS_PARTIAL) {
            return 'hr';
        }

        if ($status !== self::STATUS_PENDING) {
            return null;
        }

        $requester = Staff::find($request->staff_id);

        return $requester && $this->routeFor($requester)['route'] === self::ROUTE_SUPERVISOR ? 'supervisor' : 'hr';
    }

    /**
     * The pending leave requests (stage 1 and stage 2) this user is allowed to act on.
     *
     * @param User $user
     * @return Collection
     */
    public function actionableFor(User $user): Collection {
        $query = LeaveRequest::with(['employee.position', 'leaveType', 'handoverEmployee:id,full_names'])
            ->whereIn('current_status_id', [self::STATUS_PENDING, self::STATUS_PARTIAL]);

        if (!$this->isHrApprover($user)) {
            $staff = $user->linkedStaff();
            if (!$staff || !$staff->position_id) {
                return collect();
            }

            // A supervisor only sees stage-1 requests from positions that report to theirs
            $query->where('current_status_id', self::STATUS_PENDING)
                ->whereHas('employee.position', fn ($q) => $q->where('reports_to_id', $staff->position_id));
        }

        return $query->orderBy('id')->get()->filter(fn (LeaveRequest $request) => $this->canAct($user, $request) === null)->values();
    }

    /**
     * Human label for the stage a pending request is at, from the viewer's point of view.
     *
     * @param LeaveRequest $request
     * @return string
     */
    public function stageLabel(LeaveRequest $request): string {
        if ((int) $request->current_status_id === self::STATUS_PARTIAL) {
            return 'Stage 2 (HR approval)';
        }

        $requester = Staff::find($request->staff_id);
        $route = $requester ? $this->routeFor($requester) : null;

        return $route && $route['route'] === self::ROUTE_SUPERVISOR
            ? 'Stage 1 (Supervisor approval)'
            : 'HR approval (no supervisor stage)';
    }

    /**
     * Build a route result.
     *
     * @param string $route
     * @param string $reason
     * @param int|null $supervisorPositionId
     * @return array{route: string, reason: string, supervisor_position_id: int|null}
     */
    private function result(string $route, string $reason, ?int $supervisorPositionId = null): array {
        return ['route' => $route, 'reason' => $reason, 'supervisor_position_id' => $supervisorPositionId];
    }
}

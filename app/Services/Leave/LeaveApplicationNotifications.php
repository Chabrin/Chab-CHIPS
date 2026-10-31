<?php

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/** In-app alerts only; existing final-approval email delivery stays in the approval service. */
class LeaveApplicationNotifications
{
    public function __construct(private LeaveApprovalRouter $router) {}

    public function submitted(LeaveRequest $request, string $route): void
    {
        $staff = $request->employee;
        if (!$staff) {
            return;
        }

        $supervisors = $route === LeaveApprovalRouter::ROUTE_AUTO ? collect() : $this->router->supervisorStaffIds($staff);
        foreach ($this->users() as $user) {
            $linkedStaffId = $user->linkedStaff()?->id;
            if ((int) $linkedStaffId === (int) $staff->id) {
                if ((int) $request->current_status_id !== LeaveRequest::STATUS_APPROVED) {
                    $this->send($request, $user, 'LeaveApplicationReceived', 'Your leave application was submitted',
                        $this->router->waitingDescription($request) ?? 'Your application has been received.');
                }
                continue;
            }

            if ($user->roles->isNotEmpty() && $request->handover_staff_id && (int) $linkedStaffId === (int) $request->handover_staff_id
                && (int) $user->linkedStaff()?->current_status_id === 1 && trim((string) $request->handover_notes) !== '') {
                $status = (int) $request->current_status_id === LeaveRequest::STATUS_APPROVED
                    ? 'The leave has been approved.' : 'The leave is still awaiting approval.';
                $this->send($request, $user, 'LeaveHandoverAssigned', 'Relief arrangements assigned to you',
                    "{$status} Open this notification to read the notes and acknowledge receipt.",
                    route('self-service.leave-handover', $request->id));
            }

            if ($route === LeaveApprovalRouter::ROUTE_AUTO) {
                continue; // Directors already receive the existing auto-approval alert.
            }

            $isSupervisor = $linkedStaffId && $supervisors->contains($linkedStaffId);
            $isHr = $this->router->isHrApprover($user);
            if (!$isSupervisor && !$isHr) {
                continue;
            }

            $next = $route === LeaveApprovalRouter::ROUTE_SUPERVISOR
                ? ($isHr ? 'It is awaiting supervisor approval. HR may give final approval or reject it now.' : 'Please review it for supervisor approval.')
                : 'It is awaiting HR final approval under the existing leave routing rules.';

            $this->send($request, $user, 'LeaveApplicationSubmitted', 'Leave application submitted', $next);
        }
    }

    /** Only staff with a usable system account can receive an in-app handover alert. */
    public function handoverStaff(?Staff $requester = null): Collection
    {
        $users = User::with('staffLink.staff:id,full_names,current_status_id,department_id')
            ->where(fn ($query) => $query->whereNull('block')->orWhere('block', 0))
            ->whereHas('roles')
            ->get(['id', 'staff_id', 'activation'])
            ->filter(fn (User $user) => $user->activation != 0);
        $legacyIds = $users->filter(fn (User $user) => !$user->staffLink?->staff && $user->staff_id)->pluck('staff_id');
        $legacyStaff = Staff::whereIn('id', $legacyIds)->get(['id', 'full_names', 'current_status_id', 'department_id'])->keyBy('id');

        return $users->map(fn (User $user) => $user->staffLink?->staff ?? $legacyStaff->get($user->staff_id))
            ->filter(fn ($staff) => $staff && (int) $staff->current_status_id === 1)
            ->filter(fn ($staff) => !$requester || ($requester->department_id && (int) $staff->department_id === (int) $requester->department_id && (int) $staff->id !== (int) $requester->id))
            ->unique('id')->sortBy('full_names', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function readyForHr(LeaveRequest $request): void
    {
        $supervisors = $this->router->supervisorStaffIds($request->employee);
        foreach ($this->users() as $user) {
            if ((int) $user->id === (int) $request->status_changed_by) {
                continue;
            }
            $isHr = $this->router->isHrApprover($user);
            $staffId = $user->linkedStaff()?->id;
            $isRelief = app(LeaveHandoverService::class)->isRecipient($user, $request);
            if (!$isHr && (int) $staffId !== (int) $request->staff_id && !$supervisors->contains($staffId) && !$isRelief) continue;
            $this->send($request, $user, $isHr ? 'LeaveReadyForHrApproval' : 'LeaveSupervisorApproved',
                'Supervisor approved leave — pending HR',
                'Supervisor approval is complete. The leave is still pending HR final approval; it is not yet fully approved.',
                $this->reviewUrl($request, $user, $isRelief));
        }
    }

    /** Save final-stage bell alerts in the same transaction as the decision and balance change. */
    public function decided(LeaveRequest $request, int $actorId): void
    {
        $approved = (int) $request->current_status_id === LeaveRequest::STATUS_APPROVED;
        if (!$approved && (int) $request->current_status_id !== 41) return;
        $supervisors = $this->router->supervisorStaffIds($request->employee);
        foreach ($this->users() as $user) {
            $staffId = $user->linkedStaff()?->id;
            $isApplicant = (int) $staffId === (int) $request->staff_id;
            if ((int) $user->id === $actorId && !$isApplicant) continue;
            $isRelief = app(LeaveHandoverService::class)->isRecipient($user, $request);
            if (!$isApplicant && !$isRelief && !$supervisors->contains($staffId) && !$this->router->isHrApprover($user)) continue;
            $this->send($request, $user, $approved ? 'LeaveApproved' : 'LeaveRejected',
                $approved ? 'Leave fully approved' : 'Leave application rejected',
                $approved ? 'HR final approval is complete. No further supervisor approval is needed.'
                    : 'The leave application was rejected. The request is closed and these relief arrangements are not active.',
                $this->reviewUrl($request, $user, $isRelief));
        }
    }

    private function reviewUrl(LeaveRequest $request, User $user, bool $isRelief): string
    {
        return $isRelief && !$this->router->canReview($user, $request)
            ? route('self-service.leave-handover', $request->id, false)
            : route('leave-management.application-notification', $request->id, false);
    }

    public function acknowledged(LeaveRequest $request): void
    {
        $supervisors = $this->router->supervisorStaffIds($request->employee);
        foreach ($this->users() as $user) {
            $staffId = $user->linkedStaff()?->id;
            if ((int) $user->id === (int) $request->handover_acknowledged_by) continue;
            if ((int) $staffId === (int) $request->staff_id || $supervisors->contains($staffId) || $this->router->isHrApprover($user)) {
                $this->send($request, $user, 'LeaveHandoverAcknowledged', 'Relief arrangements acknowledged',
                    ($request->handoverEmployee?->full_names ?? 'The selected colleague').' acknowledged receipt of the relief notes and files. This does not approve the leave.');
            }
        }
    }

    private function users()
    {
        return User::with(['roles.permissions', 'permissions', 'staffLink.staff'])
            ->where(fn ($query) => $query->whereNull('block')->orWhere('block', 0))
            ->get()->filter(fn (User $user) => $user->activation != 0);
    }

    private function send(LeaveRequest $request, User $user, string $type, string $title, string $next, ?string $actionUrl = null): void
    {
        $revision = hash('sha256', json_encode([
            $request->date_time?->format('Y-m-d H:i:s.u'), $request->staff_id,
            $request->leave_type_id, $request->start_date, $request->end_date,
            $request->requested_days, $request->handover_staff_id, $request->handover_notes,
            $request->handover_attachments,
        ]));
        $data = ['leave_request_id' => $request->id, 'staff_id' => $request->staff_id, 'revision' => $revision];
        $name = $request->employee?->full_names ?? 'An employee';
        $dates = $request->start_date->format('d M Y') . ' to ' . $request->end_date->format('d M Y');
        $staffId = $user->linkedStaff()?->id;
        $copy = LeaveNotificationMessage::format($type, $name, $dates, $next,
            (int) $staffId === (int) $request->staff_id,
            $request->handover_staff_id && (int) $staffId === (int) $request->handover_staff_id);

        // notifications.data is a text column. Match the serialized event to avoid repeat alerts
        // when the same saved submission is processed again; edited submissions have a new revision.
        Notification::firstOrCreate([
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
        ], [
            'data' => $data,
            'title' => $copy[0] ?? $title,
            'message' => $copy[1] ?? "{$name} applied for leave ({$dates}). {$next}",
            'action_url' => $actionUrl ?? route('leave-management.application-notification', $request->id, false),
        ]);
    }
}

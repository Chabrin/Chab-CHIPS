<?php

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

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
                continue;
            }

            if ($user->roles->isNotEmpty() && $request->handover_staff_id && (int) $linkedStaffId === (int) $request->handover_staff_id
                && (int) $user->linkedStaff()?->current_status_id === 1 && trim((string) $request->handover_notes) !== '') {
                $status = (int) $request->current_status_id === LeaveRequest::STATUS_APPROVED
                    ? 'The leave has been approved.' : 'The leave is still awaiting approval.';
                $this->send($request, $user, 'LeaveHandoverAssigned', 'Work handover assigned to you',
                    "You have been selected to receive their work handover. {$status} Open this notification to read the notes.",
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
                ? ($isSupervisor ? 'Please review it for supervisor approval.' : 'It is awaiting supervisor approval before HR can give final approval.')
                : 'It is awaiting HR final approval under the existing leave routing rules.';

            $this->send($request, $user, 'LeaveApplicationSubmitted', 'Leave application submitted', $next);
        }
    }

    /** Only staff with a usable system account can receive an in-app handover alert. */
    public function handoverStaff(): Collection
    {
        $users = User::with('staffLink.staff:id,full_names,current_status_id')
            ->where(fn ($query) => $query->whereNull('block')->orWhere('block', 0))
            ->whereHas('roles')
            ->get(['id', 'staff_id', 'activation'])
            ->filter(fn (User $user) => $user->activation != 0);
        $legacyIds = $users->filter(fn (User $user) => !$user->staffLink?->staff && $user->staff_id)->pluck('staff_id');
        $legacyStaff = Staff::whereIn('id', $legacyIds)->get(['id', 'full_names', 'current_status_id'])->keyBy('id');

        return $users->map(fn (User $user) => $user->staffLink?->staff ?? $legacyStaff->get($user->staff_id))
            ->filter(fn ($staff) => $staff && (int) $staff->current_status_id === 1)
            ->unique('id')->sortBy('full_names', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function readyForHr(LeaveRequest $request): void
    {
        foreach ($this->users() as $user) {
            if ((int) $user->linkedStaff()?->id === (int) $request->staff_id
                || (int) $user->id === (int) $request->status_changed_by
                || !$this->router->isHrApprover($user)) {
                continue;
            }

            $this->send($request, $user, 'LeaveReadyForHrApproval', 'Leave ready for HR approval',
                'Supervisor approval is complete. Please review it for final approval.');
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
        ]));
        $data = ['leave_request_id' => $request->id, 'staff_id' => $request->staff_id, 'revision' => $revision];
        $name = $request->employee?->full_names ?? 'An employee';
        $dates = $request->start_date->format('d M Y') . ' to ' . $request->end_date->format('d M Y');

        // notifications.data is a text column. Match the serialized event to avoid repeat alerts
        // when the same saved submission is processed again; edited submissions have a new revision.
        Notification::firstOrCreate([
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
        ], [
            'data' => $data,
            'title' => $title,
            'message' => "{$name} applied for leave ({$dates}). {$next}",
            'action_url' => $actionUrl ?? route('leave-management.leave-applications.by-staff', Crypt::encrypt($request->id)),
        ]);
    }
}

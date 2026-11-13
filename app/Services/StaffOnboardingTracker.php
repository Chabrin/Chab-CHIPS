<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Staff;
use App\Models\StaffDraft;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

/**
 * One list of everyone being onboarded, whichever way it started: an invitation sent for the employee to fill in, or HR adding
 * the staff member directly on Add Staff (kept as a draft until saved). Each row says how far it has got, stage by stage, and
 * what to do next.
 *
 * The stages are the tabs of Add Staff, in order, and then the contract, which is added after Save Staff. A person who has been
 * saved as staff but has no contract yet is still in progress, waiting at "Add contract".
 */
class StaffOnboardingTracker
{
    public const GROUP_IN_PROGRESS = 'in_progress';
    public const GROUP_FINISHED = 'finished';
    public const GROUP_CLOSED = 'closed';

    public const CONTRACT = 'contract';

    /** The tabs that the employee fills in on the invitation form; HR does the rest on Add Staff. */
    private const EMPLOYEE_STAGES = ['personal', 'contact', 'banking', 'documents'];

    private const INVITATION_LABELS = [
        'sent' => 'Link sent',
        'submitted' => 'Awaiting review',
        'reviewed' => 'Approved, staff record not yet created',
        'approved' => 'Staff created',
        'revoked' => 'Cancelled',
        'mail_failed' => 'Email failed',
    ];

    /** @return array<string, string> stage key => what people read, in order */
    public static function stageLabels(): array
    {
        return StaffDraft::STAGES + [self::CONTRACT => 'Add contract'];
    }

    /**
     * Every onboarding as a row, newest rules applied per group by the caller.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        // The encrypted form contents are never read here: the list needs only who, when and how far
        $invitations = StaffOnboardingInvitation::query()
            ->select(['id', 'recipient_email', 'status', 'invited_by', 'staff_id', 'expires_at', 'submitted_at', 'reviewed_at', 'created_at'])
            ->latest()
            ->get();
        $drafts = StaffDraft::query()
            ->select(['id', 'created_by', 'title', 'last_tab', 'stages', 'onboarding_invitation_id', 'employment_type', 'staff_number', 'staff_id', 'completed_at', 'created_at', 'updated_at'])
            ->get();

        $byInvitation = $drafts->whereNotNull('onboarding_invitation_id')->groupBy('onboarding_invitation_id')
            ->map(fn (Collection $group) => $group->sortBy(fn (StaffDraft $draft) => [$draft->completed_at === null ? 0 : 1, -$draft->id])->first());
        $invitationIds = $invitations->pluck('id')->all();

        $staffIds = $invitations->pluck('staff_id')->merge($drafts->pluck('staff_id'))->filter()->unique()->values()->all();
        $staff = $staffIds ? Staff::whereIn('id', $staffIds)->get(['id', 'full_names', 'staff_number', 'position_id'])->keyBy('id') : collect();
        $withContract = $staffIds ? Contract::whereIn('staff_id', $staffIds)->pluck('staff_id')->flip() : collect();
        $people = $this->people($invitations->pluck('invited_by')->merge($drafts->pluck('created_by'))->filter()->unique()->values()->all());

        $rows = collect();

        foreach ($invitations as $invitation) {
            $rows->push($this->invitationRow($invitation, $byInvitation->get($invitation->id), $staff, $withContract, $people));
        }

        // Drafts and finished records of staff added directly (an invitation's own are part of its row)
        foreach ($drafts as $draft) {
            if ($draft->onboarding_invitation_id !== null && in_array((int) $draft->onboarding_invitation_id, $invitationIds, true)) {
                continue;
            }
            $rows->push($this->draftRow($draft, $staff, $withContract, $people));
        }

        return $rows->values();
    }

    /** The rows of one group, in the order that suits it: what has been open longest first, the rest newest first. */
    public static function inGroup(Collection $rows, string $group): Collection
    {
        $rows = $rows->where('group', $group);

        return ($group === self::GROUP_IN_PROGRESS ? $rows->sortBy(fn ($row) => $row['started_at']->timestamp) : $rows->sortByDesc(fn ($row) => $row['started_at']->timestamp))->values();
    }

    /** @return array<string, int> */
    public static function counts(Collection $rows): array
    {
        return [
            self::GROUP_IN_PROGRESS => $rows->where('group', self::GROUP_IN_PROGRESS)->count(),
            self::GROUP_FINISHED => $rows->where('group', self::GROUP_FINISHED)->count(),
            self::GROUP_CLOSED => $rows->where('group', self::GROUP_CLOSED)->count(),
        ];
    }

    /** @param Collection<int, Staff> $staff @param Collection<int, int> $withContract @param array<int, string> $people */
    private function invitationRow(StaffOnboardingInvitation $invitation, ?StaffDraft $draft, Collection $staff, Collection $withContract, array $people): array
    {
        $status = (string) $invitation->status;
        $person = $invitation->staff_id ? $staff->get($invitation->staff_id) : null;
        $staffSaved = $status === 'approved';

        if (in_array($status, ['revoked', 'mail_failed'], true)) {
            $group = self::GROUP_CLOSED;
        } elseif (in_array($status, ['sent', 'submitted', 'reviewed'], true)) {
            $group = self::GROUP_IN_PROGRESS;
        } else {
            $group = self::GROUP_FINISHED; // approved, and the older 'completed' invitations
        }

        // How far each Add Staff stage has got: HR's own walk through the form once a draft exists, otherwise what the status implies
        if ($status === 'approved') {
            $statuses = $draft && $draft->completed_at !== null ? $draft->stageStatuses() : StaffDraft::finishedStages(false);
        } elseif ($draft && $draft->completed_at === null) {
            $statuses = $draft->stageStatuses();
        } else {
            $statuses = [];
            foreach (array_keys(StaffDraft::STAGES) as $key) {
                $statuses[$key] = in_array($status, ['submitted', 'reviewed'], true) && in_array($key, self::EMPLOYEE_STAGES, true) ? 'complete' : 'pending';
            }
        }

        $hasContract = $person && $withContract->has($person->id);
        $stages = $this->stages($statuses, $staffSaved, $hasContract);

        // Saved as staff but still no contract: onboarding is not over, it is waiting at "Add contract"
        if ($status === 'approved' && $person && !$hasContract) {
            $group = self::GROUP_IN_PROGRESS;
        }

        $label = self::INVITATION_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
        if ($status === 'sent' && $invitation->expires_at && $invitation->expires_at->isPast()) {
            $label = 'Link expired';
        }
        if ($status === 'approved' && $group === self::GROUP_IN_PROGRESS) {
            $label = 'Staff created, no contract yet';
        }

        $row = $this->base('invitation-' . $invitation->id, 'Invitation', $invitation->created_at, $stages);
        $row['name'] = ($draft?->title) ?: ($person?->full_names) ?: $invitation->recipient_email;
        $row['email'] = $invitation->recipient_email;
        $row['started_by'] = $people[$invitation->invited_by] ?? null;
        $row['status_label'] = $label;
        $row['staff_number'] = $person?->staff_number ?: $draft?->staff_number;
        $row['expires_at'] = in_array($status, ['sent', 'submitted'], true) ? $invitation->expires_at : null;
        $row['finished_at'] = $group === self::GROUP_FINISHED ? ($draft?->completed_at ?: $invitation->reviewed_at ?: $invitation->created_at) : null;
        $row['group'] = $group;
        $row['now_at'] = match (true) {
            $status === 'sent' => 'Waiting for the employee to fill in the form',
            $status === 'submitted' => 'Waiting for HR to review what was submitted',
            $group === self::GROUP_CLOSED => '—',
            default => $this->nowAt($stages),
        };

        $row['actions'] = $this->invitationActions($invitation, $draft, $status, $person, $hasContract);

        return $row;
    }

    /** @param Collection<int, Staff> $staff @param Collection<int, int> $withContract @param array<int, string> $people */
    private function draftRow(StaffDraft $draft, Collection $staff, Collection $withContract, array $people): array
    {
        $finished = $draft->completed_at !== null;
        $person = $draft->staff_id ? $staff->get($draft->staff_id) : null;
        $hasContract = $person && $withContract->has($person->id);
        $statuses = $draft->stageStatuses();
        $stages = $this->stages($statuses, $finished, $hasContract);

        $row = $this->base('draft-' . $draft->id, 'Added by HR', $draft->created_at, $stages);
        $row['name'] = $draft->title ?: ($person?->full_names) ?: 'Untitled (nothing typed yet)';
        $row['email'] = null;
        $row['started_by'] = $people[$draft->created_by] ?? null;
        $row['staff_number'] = $person?->staff_number ?: $draft->staff_number;
        $row['expires_at'] = null;
        $row['finished_at'] = $finished && $hasContract ? $draft->completed_at : null;
        $row['group'] = $finished && $hasContract ? self::GROUP_FINISHED : self::GROUP_IN_PROGRESS;
        $row['status_label'] = !$finished ? 'Draft' : ($hasContract ? 'Staff created' : 'Staff created, no contract yet');
        $row['now_at'] = $this->nowAt($stages);
        $row['actions'] = [];

        if (!$finished) {
            $row['actions'][] = ['label' => 'Resume', 'url' => route('staff.create', ['draft' => $draft->id]), 'method' => 'get', 'style' => 'primary'];
            $row['actions'][] = ['label' => 'Discard', 'url' => route('staff.drafts.destroy', $draft->id), 'method' => 'delete', 'style' => 'outline-danger', 'confirm' => 'Discard this draft? What was saved in it, including its files, is deleted, and its staff number goes back.'];
        } elseif ($person) {
            $row['actions'] = $this->afterSaving($person, $hasContract);
        }

        return $row;
    }

    /** The fields every row has. @param \Carbon\CarbonInterface|null $startedAt @param array<int, array<string, string>> $stages */
    private function base(string $key, string $source, $startedAt, array $stages): array
    {
        return [
            'key' => $key,
            'source' => $source,
            'started_at' => $startedAt ?: now(),
            'days_open' => max(0, (int) floor(($startedAt ?: now())->diffInDays(now(), true))),
            'stages' => $stages,
        ];
    }

    /**
     * The stages of a row as a list for display: each Add Staff tab (the System User tab left out when it does not apply),
     * then the contract.
     *
     * @param array<string, string> $statuses
     * @return array<int, array{key: string, label: string, status: string}>
     */
    private function stages(array $statuses, bool $staffSaved, bool $hasContract): array
    {
        $list = [];
        foreach (StaffDraft::STAGES as $key => $label) {
            $status = $statuses[$key] ?? 'pending';
            if ($status === 'skipped') {
                continue;
            }
            $list[] = ['key' => $key, 'label' => $label, 'status' => $status];
        }
        $list[] = ['key' => self::CONTRACT, 'label' => 'Add contract', 'status' => $staffSaved && $hasContract ? 'complete' : 'pending'];

        return $list;
    }

    /** The first stage that is not done: what the person is waiting at. @param array<int, array{key: string, label: string, status: string}> $stages */
    private function nowAt(array $stages): string
    {
        foreach ($stages as $stage) {
            if ($stage['status'] !== 'complete') {
                return $stage['label'];
            }
        }

        return 'All done';
    }

    private function invitationActions(StaffOnboardingInvitation $invitation, ?StaffDraft $draft, string $status, ?Staff $person, bool $hasContract): array
    {
        $actions = [];

        if ($status === 'submitted') {
            $actions[] = ['label' => 'Review submission', 'url' => route('staff.onboarding.review', $invitation), 'method' => 'get', 'style' => 'outline-primary'];
        } elseif ($status === 'reviewed') {
            $actions[] = $draft && $draft->completed_at === null
                ? ['label' => 'Resume draft', 'url' => route('staff.create', ['draft' => $draft->id]), 'method' => 'get', 'style' => 'primary']
                : ['label' => 'Continue to Add Staff', 'url' => route('staff.create', ['onboarding' => $invitation->id]), 'method' => 'get', 'style' => 'primary'];
        } elseif ($status === 'approved' && $person) {
            $actions = $this->afterSaving($person, $hasContract);
        }

        if (in_array($status, ['sent', 'submitted', 'reviewed'], true)) {
            $actions[] = [
                'label' => $status === 'sent' ? 'Revoke' : 'Reject', 'url' => route('staff.onboarding.revoke', $invitation), 'method' => 'delete', 'style' => 'outline-danger',
                'confirm' => 'Cancel this onboarding? Any submitted details and files will be deleted.',
            ];
        }

        return $actions;
    }

    /** What to do for someone already saved as staff: add the contract, or look at them. @return array<int, array<string, string>> */
    private function afterSaving(Staff $person, bool $hasContract): array
    {
        if (!$hasContract) {
            return [['label' => 'Add contract', 'url' => route('contract.create', ['staff_id' => $person->id, 'position_id' => $person->position_id]), 'method' => 'get', 'style' => 'primary']];
        }

        return [['label' => 'View staff', 'url' => route('staff.show', Crypt::encrypt($person->id)), 'method' => 'get', 'style' => 'outline-primary']];
    }

    /** @param array<int, int> $ids @return array<int, string> user id => name */
    private function people(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        try {
            return User::whereIn('id', $ids)->pluck('name', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}

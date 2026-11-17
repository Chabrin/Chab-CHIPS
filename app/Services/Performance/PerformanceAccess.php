<?php

namespace App\Services\Performance;

use App\Models\PerformanceAppeal;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceMeetingAttendee;
use App\Models\PerformanceReview;
use App\Models\PerformanceSetting;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserStaffLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is who in a review, who it goes to, and what each person may do. It follows the leave approval logic (the position
 * a staff member reports to is their supervisor, HR is a set of roles, nobody acts on their own review), with one
 * difference HR asked for: a position that has a "reports to" always has that position's holder as supervisor, even when
 * others report to them. Only staff with no "reports to" go straight to HR.
 *
 * When several people hold the supervising position HR names one (PerformanceSupervisorAssignment); if nobody is named
 * the review goes to HR and the cycle preview says so. Whoever the viewer is, a staff member looking at their own review is
 * always treated as the subject of it, even when they are also HR.
 */
class PerformanceAccess
{
    /** The roles that give the HR (final) rating. The same three the leave approval uses; change them here, in one place. */
    public const HR_ROLES = ['hr_manager', 'directors', 'superadministrator'];

    public const ROLE_SUBJECT = 'subject';

    public const ROLE_HR = 'hr';

    public const ROLE_SUPERVISOR = 'supervisor';

    /** A committee member HR picked for a meeting or hearing about this review. They may read it; they cannot rate it. */
    public const ROLE_COMMITTEE = 'committee';

    /** @var array<int, true>|null staff ids that have a usable (unblocked) login */
    private ?array $loginStaffIds = null;

    /** @var array<int, Collection> */
    private array $holders = [];

    public function isHr(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::HR_ROLES);
    }

    public function staffOf(?User $user): ?Staff
    {
        return $user?->linkedStaff();
    }

    /** Whether the staff member has a system login that is not blocked, which is what the system treats as a user. */
    public function hasLogin(Staff $staff): bool
    {
        if ($this->loginStaffIds === null) {
            $this->loginStaffIds = $this->loadLoginStaffIds();
        }

        return isset($this->loginStaffIds[(int) $staff->id]);
    }

    private function loadLoginStaffIds(): array
    {
        $unblocked = fn ($query) => $query->where(fn ($q) => $q->whereNull('block')->orWhere('block', 0));

        $ids = [];
        foreach (UserStaffLink::whereHas('user', $unblocked)->pluck('staff_id') as $id) {
            $ids[(int) $id] = true;
        }
        foreach (User::where($unblocked)->whereNotNull('staff_id')->pluck('staff_id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /** The id of the position the staff member reports to, or null when they have none. */
    public function bossPositionId(Staff $staff): ?int
    {
        $boss = $staff->position_id ? Position::whereKey($staff->position_id)->value('reports_to_id') : null;

        return $boss ? (int) $boss : null;
    }

    /** Active holders of the position the staff member reports to, never the staff member themselves. */
    public function supervisorCandidates(Staff $staff): Collection
    {
        $boss = $this->bossPositionId($staff);
        if (! $boss) {
            return collect();
        }

        $holders = $this->holders[$boss] ??= Staff::where('position_id', $boss)->where('current_status_id', 1)->orderBy('full_names')->get();

        return $holders->reject(fn (Staff $holder) => (int) $holder->id === (int) $staff->id)->values();
    }

    /**
     * Where a staff member's review goes first, and why.
     *
     * @return array{route: string, supervisor_staff_id: int|null, reason: string}
     */
    public function route(Staff $staff, ?int $assignedSupervisorId = null): array
    {
        if (! $staff->position_id) {
            return $this->hr('no_position');
        }
        if (! $this->bossPositionId($staff)) {
            return $this->hr('no_boss_position');
        }

        $candidates = $this->supervisorCandidates($staff);
        if ($candidates->isEmpty()) {
            return $this->hr('no_holder');
        }

        $chosen = null;
        $reason = null;
        if ($assignedSupervisorId && $candidates->contains(fn (Staff $candidate) => (int) $candidate->id === $assignedSupervisorId)) {
            $chosen = $candidates->first(fn (Staff $candidate) => (int) $candidate->id === $assignedSupervisorId);
            $reason = 'named';
        } elseif ($candidates->count() === 1) {
            $chosen = $candidates->first();
            $reason = 'only_holder';
        } else {
            return $this->hr($assignedSupervisorId ? 'assignment_invalid' : 'not_named');
        }

        if (! $this->hasLogin($chosen)) {
            return $this->hr('supervisor_has_no_login');
        }

        return ['route' => PerformanceReview::ROUTE_SUPERVISOR, 'supervisor_staff_id' => (int) $chosen->id, 'reason' => $reason];
    }

    private function hr(string $reason): array
    {
        return ['route' => PerformanceReview::ROUTE_HR, 'supervisor_staff_id' => null, 'reason' => $reason];
    }

    /** What the reasons mean, in words, for the cycle preview and the review page. */
    public const REASONS = [
        'named' => 'Supervisor named by HR',
        'only_holder' => 'The only person in the supervisor position',
        'no_position' => 'No position, so HR rates',
        'no_boss_position' => 'Position has no "reports to", so HR rates',
        'no_holder' => 'Nobody active holds the supervisor position, so HR rates',
        'not_named' => 'Several people hold the supervisor position and none is named, so HR rates',
        'assignment_invalid' => 'The named supervisor no longer holds the supervisor position, so HR rates',
        'supervisor_has_no_login' => 'The supervisor has no system login, so HR rates',
        'hr_took_over' => 'HR took over the supervisor rating',
    ];

    /**
     * Every part this person plays in the review. The subject of a review plays only that part, even when they are also HR,
     * so they only ever see what is shared with the staff member. Anyone else can be HR, the named supervisor, or both (HR who is also
     * the supervisor), or nothing.
     *
     * @return string[]
     */
    public function roles(?User $user, PerformanceReview $review): array
    {
        if (! $user) {
            return [];
        }

        $staff = $this->staffOf($user);
        if ($staff && (int) $staff->id === (int) $review->staff_id) {
            return [self::ROLE_SUBJECT];
        }

        $roles = [];
        if ($this->isHr($user)) {
            $roles[] = self::ROLE_HR;
        }
        if ($staff && (int) $staff->current_status_id === 1 && $review->supervisor_staff_id && (int) $staff->id === (int) $review->supervisor_staff_id) {
            $roles[] = self::ROLE_SUPERVISOR;
        }
        if ($staff && (int) $staff->current_status_id === 1 && PerformanceMeetingAttendee::where('role', PerformanceMeetingAttendee::ROLE_COMMITTEE)
            ->where('staff_id', $staff->id)
            ->whereIn('meeting_id', PerformanceMeeting::where('review_id', $review->id)->select('id'))->exists()) {
            $roles[] = self::ROLE_COMMITTEE;
        }

        return $roles;
    }

    /** The part that decides what this person is shown: subject, then HR, then supervisor, then committee. Null when they have no part. */
    public function role(?User $user, PerformanceReview $review): ?string
    {
        $roles = $this->roles($user, $review);
        foreach ([self::ROLE_SUBJECT, self::ROLE_HR, self::ROLE_SUPERVISOR, self::ROLE_COMMITTEE] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return null;
    }

    /** The reviews this person may open: their own, the ones they supervise, and all of them for HR. */
    public function visibleTo(?User $user): Builder
    {
        $query = PerformanceReview::query();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }
        if ($this->isHr($user)) {
            return $query;
        }

        $staff = $this->staffOf($user);
        if (! $staff) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($staff) {
            $scope->where('staff_id', $staff->id);
            if ((int) $staff->current_status_id === 1) {
                $scope->orWhere('supervisor_staff_id', $staff->id)
                    ->orWhereIn('id', PerformanceMeeting::whereIn('id', PerformanceMeetingAttendee::where('role', PerformanceMeetingAttendee::ROLE_COMMITTEE)
                        ->where('staff_id', $staff->id)->select('meeting_id'))->select('review_id'));
            }
        });
    }

    // ── the joint review meeting and the appeal ───────────────────────────

    public function meetingOf(PerformanceReview $review, string $kind): ?PerformanceMeeting
    {
        return PerformanceMeeting::where('review_id', $review->id)->where('kind', $kind)->first();
    }

    public function appealOf(PerformanceReview $review): ?PerformanceAppeal
    {
        return PerformanceAppeal::where('review_id', $review->id)->first();
    }

    /** The last day the staff member can appeal on their own: the appeal period after the review was finalised. */
    public function appealDeadline(PerformanceReview $review): ?\Carbon\Carbon
    {
        return $review->finalised_at ? $review->finalised_at->copy()->addDays(PerformanceSetting::number(PerformanceSetting::APPEAL_DAYS)) : null;
    }

    public function appealWindowOpen(PerformanceReview $review): bool
    {
        $deadline = $this->appealDeadline($review);

        return $deadline !== null && now()->lessThanOrEqualTo($deadline);
    }

    /** Everyone with an HR role who can be told or invited: not blocked, activated. */
    public function hrUsers(): Collection
    {
        $ids = DB::table('cb_role_user as ru')->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('r.name', self::HR_ROLES)->pluck('ru.user_id')->unique()->all();

        return User::whereIn('id', $ids)->where(fn ($q) => $q->whereNull('block')->orWhere('block', 0))->orderBy('name')->get()
            ->filter(fn (User $user) => $user->activation != 0)->values();
    }

    /**
     * Why the person cannot do this to the review right now, or null when they can. The status goes with it: 403 when they
     * are not allowed to, 422 when the review is not at the stage for it.
     *
     * @return array{error: string, status: int}|null
     */
    public function denial(?User $user, PerformanceReview $review, string $action): ?array
    {
        $roles = $this->roles($user, $review);
        if ($roles === []) {
            return ['error' => 'You do not have access to this review.', 'status' => 403];
        }
        if ($action === 'view') {
            return null;
        }

        $is = fn (string $role) => in_array($role, $roles, true);
        $review->loadMissing('cycle');
        $open = $review->cycle && $review->cycle->isPublished();
        $status = $review->status;
        $no = fn (string $message, int $code = 403) => ['error' => $message, 'status' => $code];
        $closed = 'This review cycle is not open.';
        // The two stages are given by two different people, as in leave approval
        $ratedAsSupervisor = $is(self::ROLE_SUPERVISOR) && $review->route === PerformanceReview::ROUTE_SUPERVISOR;

        switch ($action) {
            case 'self':
                if (! $is(self::ROLE_SUBJECT)) {
                    return $no('Only the staff member can enter their own rating.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }

                return $status === PerformanceReview::STATUS_AWAITING_SELF ? null : $no('The self-rating has already been submitted.', 422);

            case 'self_on_behalf':
            case 'skip_self':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can do this on behalf of the staff member.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }

                return $status === PerformanceReview::STATUS_AWAITING_SELF ? null : $no('The self-rating has already been submitted.', 422);

            case 'supervisor':
                if (! $is(self::ROLE_SUPERVISOR)) {
                    return $no('Only the supervisor named for this review can rate it.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }

                return $status === PerformanceReview::STATUS_AWAITING_SUPERVISOR ? null : $no('This review is not waiting for the supervisor.', 422);

            case 'hr':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can give the final rating.');
                }
                if ($ratedAsSupervisor) {
                    return $no('You are this review\'s supervisor, so a different HR person gives the final rating.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }

                return $status === PerformanceReview::STATUS_AWAITING_HR ? null : $no('This review is not waiting for HR.', 422);

            case 'return':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can send a review back.');
                }
                if ($ratedAsSupervisor) {
                    return $no('You are this review\'s supervisor, so a different HR person sends it back.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }
                if ($status !== PerformanceReview::STATUS_AWAITING_HR) {
                    return $no('This review is not waiting for HR.', 422);
                }

                return $review->route === PerformanceReview::ROUTE_SUPERVISOR ? null : $no('This review has no supervisor to send it back to.', 422);

            case 'reassign':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can change the supervisor.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }
                if ($status === PerformanceReview::STATUS_FINALISED) {
                    return $no('A finalised review cannot change supervisor.', 422);
                }

                // A supervisor who has already rated stays; HR sends it back to them instead of swapping the rater underneath them
                return $status === PerformanceReview::STATUS_AWAITING_HR && $review->supervisor_submitted_at
                    ? $no('The supervisor has already rated this review. Send it back to them instead.', 422)
                    : null;

            case 'correct':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can correct a finalised review.');
                }
                if ($ratedAsSupervisor) {
                    return $no('You are this review\'s supervisor, so a different HR person corrects it.');
                }

                return $status === PerformanceReview::STATUS_FINALISED ? null : $no('Only a finalised review is corrected.', 422);

            case 'schedule_meeting':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can schedule the joint review meeting.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }

                return $status === PerformanceReview::STATUS_AWAITING_MEETING ? null : $no('This review is not waiting for the joint review meeting.', 422);

            case 'record_meeting':
                // The supervisor records it; when the review goes straight to HR, HR does
                $recorder = $review->route === PerformanceReview::ROUTE_SUPERVISOR ? $is(self::ROLE_SUPERVISOR) : $is(self::ROLE_HR);
                if (! $recorder) {
                    return $no($review->route === PerformanceReview::ROUTE_SUPERVISOR ? 'Only the supervisor records the joint review meeting.' : 'Only HR records the joint review meeting.');
                }
                if (! $open) {
                    return $no($closed, 422);
                }
                if ($status !== PerformanceReview::STATUS_AWAITING_MEETING) {
                    return $no('This review is not waiting for the joint review meeting.', 422);
                }

                return $this->meetingOf($review, PerformanceMeeting::KIND_REVIEW)?->status === PerformanceMeeting::STATUS_SCHEDULED ? null : $no('Schedule the meeting first.', 422);

            case 'file_appeal':
                if (! $is(self::ROLE_SUBJECT)) {
                    return $no('Only the staff member can appeal their own review.');
                }
                if ($status !== PerformanceReview::STATUS_FINALISED) {
                    return $no('A review can be appealed once it is finalised.', 422);
                }
                if ($this->appealOf($review)) {
                    return $no('This review has already been appealed.', 422);
                }

                return $this->appealWindowOpen($review) ? null : $no('The appeal period for this review has ended. Ask HR if you still want to appeal.', 422);

            case 'file_appeal_on_behalf':
            case 'schedule_hearing':
            case 'record_hearing':
                if (! $is(self::ROLE_HR)) {
                    return $no('Only HR can do this for an appeal.');
                }
                if ($ratedAsSupervisor) {
                    return $no('You are this review\'s supervisor, so a different HR person handles the appeal.');
                }
                if ($status !== PerformanceReview::STATUS_FINALISED) {
                    return $no('A review can be appealed once it is finalised.', 422);
                }
                $appeal = $this->appealOf($review);
                if ($action === 'file_appeal_on_behalf') {
                    return $appeal ? $no('This review has already been appealed.', 422) : null;
                }
                if (! $appeal) {
                    return $no('No appeal has been filed for this review.', 422);
                }
                if ($appeal->isDecided()) {
                    return $no('This appeal has already been decided.', 422);
                }
                if ($action === 'record_hearing') {
                    return $this->meetingOf($review, PerformanceMeeting::KIND_APPEAL)?->status === PerformanceMeeting::STATUS_SCHEDULED ? null : $no('Schedule the hearing first.', 422);
                }

                return null;
        }

        return $no('That is not something you can do to a review.');
    }
}

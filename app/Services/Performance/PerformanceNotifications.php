<?php

namespace App\Services\Performance;

use App\Models\Notification;
use App\Models\PerformanceAppeal;
use App\Models\PerformanceCycle;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserStaffLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * In-app alerts (the bell) for Performance Review: whoever has to do the next thing on a review is told, and the staff member
 * is told when their review opens and when it is finalised. Nothing is emailed, and no alert ever carries a rating, a
 * score or a comment about the person: it names the staff member and the cycle and links to the review, whose page decides
 * what each person may see.
 *
 * Alerts are secondary to the review itself, so a fault in sending one is reported and never stops a rating.
 */
class PerformanceNotifications
{
    public const OPENED = 'PerformanceReviewOpened';

    public const WAITING_FOR_SUPERVISOR = 'PerformanceReviewWaitingForSupervisor';

    public const WAITING_FOR_HR = 'PerformanceReviewWaitingForHr';

    public const RETURNED = 'PerformanceReviewReturned';

    public const FINALISED = 'PerformanceReviewFinalised';

    public const CORRECTED = 'PerformanceReviewCorrected';

    public const WAITING_FOR_MEETING = 'PerformanceReviewWaitingForMeeting';

    public const MEETING_SCHEDULED = 'PerformanceMeetingScheduled';

    public const MEETING_HELD = 'PerformanceMeetingHeld';

    public const APPEAL_FILED = 'PerformanceAppealFiled';

    public const APPEAL_DECIDED = 'PerformanceAppealDecided';

    /** Staff whose reviews have just been made: each one with a login is told theirs is open. */
    public function opened(PerformanceCycle $cycle, iterable $reviews): void
    {
        $this->safely(function () use ($cycle, $reviews) {
            $deadline = $cycle->deadline ? ' Please finish it by '.$cycle->deadline->format('d M Y').'.' : '';
            foreach ($reviews as $review) {
                foreach ($this->usersForStaff((int) $review->staff_id) as $user) {
                    $this->send($user, $review, self::OPENED, 'Your performance review is open',
                        "Your review for {$cycle->name} is ready. Rate yourself on each criterion.{$deadline}");
                }
            }
        });
    }

    /** The self-rating is in (or was skipped): HR is asked to schedule the joint review meeting. */
    public function selfDone(PerformanceReview $review): void
    {
        $this->safely(function () use ($review) {
            foreach ($this->hrUsersWhoCanGiveTheFinalRating($review) as $user) {
                $this->send($user, $review, self::WAITING_FOR_MEETING, 'A joint review meeting needs to be scheduled',
                    "{$this->nameOf($review)}'s self-rating for {$this->cycleOf($review)} is in. Schedule the joint review meeting.",
                    route('performance.meeting', $review->id, false));
            }
        });
    }

    /**
     * A joint review meeting or an appeal hearing was scheduled or moved: everyone invited is told, that is the staff member,
     * the supervisor, the HR person and each committee member that has a login. Nobody is told anything about ratings.
     */
    public function meetingScheduled(PerformanceMeeting $meeting, bool $rescheduled): void
    {
        $this->safely(function () use ($meeting, $rescheduled) {
            $review = $meeting->review()->with(['cycle', 'staff'])->first();
            if (! $review) {
                return;
            }
            $hearing = $meeting->kind === PerformanceMeeting::KIND_APPEAL;
            $what = $hearing ? 'appeal hearing' : 'joint review meeting';
            $title = ucfirst($what).($rescheduled ? ' rescheduled' : ' scheduled');
            $when = $meeting->scheduled_at->format('d M Y \a\t H:i');
            $where = $meeting->venue ? ", {$meeting->venue}" : '';
            $url = $this->meetingUrl($meeting, $review);

            foreach ($this->attendeeUsers($meeting) as [$user, $staffId]) {
                $whose = $staffId === (int) $review->staff_id ? 'Your' : $this->nameOf($review)."'s";
                $this->send($user, $review, self::MEETING_SCHEDULED, $title,
                    "{$whose} {$what} for {$this->cycleOf($review)} is ".($rescheduled ? 'now ' : '')."on {$when}{$where}.", $url);
            }
        });
    }

    /** The meeting was held and recorded: the staff member can read the feedback, and HR is asked when it has no supervisor. */
    public function meetingHeld(PerformanceMeeting $meeting): void
    {
        $this->safely(function () use ($meeting) {
            $review = $meeting->review()->with(['cycle', 'staff'])->first();
            if (! $review) {
                return;
            }
            foreach ($this->usersForStaff((int) $review->staff_id) as $user) {
                $this->send($user, $review, self::MEETING_HELD, 'Feedback from your joint review meeting is ready',
                    "The feedback from your joint review meeting for {$this->cycleOf($review)} has been recorded. Open it to read it.",
                    route('performance.meeting', $review->id, false));
            }
            if ($review->route === PerformanceReview::ROUTE_HR) {
                $this->askHr($review);
            }
        });
    }

    /** A staff member appealed: they are told it was received; HR and their supervisor are told there is an appeal to deal with. */
    public function appealFiled(PerformanceAppeal $appeal): void
    {
        $this->safely(function () use ($appeal) {
            $review = $appeal->review()->with(['cycle', 'staff'])->first();
            if (! $review) {
                return;
            }
            $url = route('performance.appeal', $review->id, false);
            $name = $this->nameOf($review);
            $cycle = $this->cycleOf($review);

            foreach ($this->usersForStaff((int) $review->staff_id) as $user) {
                $this->send($user, $review, self::APPEAL_FILED, 'Your appeal was received',
                    "Your appeal on your {$cycle} review was received. HR will schedule the hearing with the review committee and tell you when.", $url);
            }
            foreach ($this->hrUsersWhoCanGiveTheFinalRating($review) as $user) {
                $this->send($user, $review, self::APPEAL_FILED, 'A review has been appealed',
                    "{$name} appealed their review for {$cycle}. Schedule the appeal hearing with the committee.", $url);
            }
            if ($review->route === PerformanceReview::ROUTE_SUPERVISOR && $review->supervisor_staff_id) {
                foreach ($this->usersForStaff((int) $review->supervisor_staff_id) as $user) {
                    $this->send($user, $review, self::APPEAL_FILED, 'A review you supervised was appealed',
                        "{$name} appealed their review for {$cycle}. Open it to read what they dispute. You will be invited to the hearing.", $url);
                }
            }
        });
    }

    /** The committee decided the appeal: everyone involved is told. The decision itself is read on the appeal page. */
    public function appealDecided(PerformanceAppeal $appeal): void
    {
        $this->safely(function () use ($appeal) {
            $review = $appeal->review()->with(['cycle', 'staff'])->first();
            if (! $review) {
                return;
            }
            $url = route('performance.appeal', $review->id, false);
            $cycle = $this->cycleOf($review);

            $told = [];
            $tell = function (User $user, string $message) use ($review, $url, &$told) {
                if (isset($told[$user->id])) {
                    return;
                }
                $told[$user->id] = true;
                $this->send($user, $review, self::APPEAL_DECIDED, 'An appeal was decided', $message, $url);
            };

            foreach ($this->usersForStaff((int) $review->staff_id) as $user) {
                $tell($user, "The review committee has decided your appeal on your {$cycle} review. Open it to read the decision.");
            }
            $hearing = $this->hearingOf($review);
            $others = collect();
            if ($hearing) {
                $others = $others->merge(collect($this->attendeeUsers($hearing))->map(fn ($pair) => $pair[0]));
            }
            if ($review->route === PerformanceReview::ROUTE_SUPERVISOR && $review->supervisor_staff_id) {
                $others = $others->merge($this->usersForStaff((int) $review->supervisor_staff_id));
            }
            $others = $others->merge($this->hrUsersWhoCanGiveTheFinalRating($review));
            foreach ($others as $user) {
                $tell($user, "The review committee has decided the appeal on {$this->nameOf($review)}'s {$cycle} review. Open it to read the decision.");
            }
        });
    }

    /** The supervisor has rated: HR gives the final rating. */
    public function supervisorDone(PerformanceReview $review): void
    {
        $this->safely(fn () => $this->askHr($review));
    }

    /**
     * HR changed the supervisor: the new one is asked, as long as the review is waiting for them, or told about the joint
     * review meeting when one is already scheduled.
     */
    public function supervisorChanged(PerformanceReview $review): void
    {
        $this->safely(function () use ($review) {
            if ($review->status === PerformanceReview::STATUS_AWAITING_SUPERVISOR) {
                $this->askSupervisor($review);
            }
            $meeting = $review->status === PerformanceReview::STATUS_AWAITING_MEETING && $review->supervisor_staff_id
                ? PerformanceMeeting::where('review_id', $review->id)->where('kind', PerformanceMeeting::KIND_REVIEW)->where('status', PerformanceMeeting::STATUS_SCHEDULED)->first()
                : null;
            if ($meeting) {
                foreach ($this->usersForStaff((int) $review->supervisor_staff_id) as $user) {
                    $this->send($user, $review, self::MEETING_SCHEDULED, 'Joint review meeting scheduled',
                        "{$this->nameOf($review)}'s joint review meeting for {$this->cycleOf($review)} is on {$meeting->scheduled_at->format('d M Y \a\t H:i')}".($meeting->venue ? ", {$meeting->venue}" : '').'. You are the supervisor.',
                        route('performance.meeting', $review->id, false));
                }
            }
        });
    }

    /** HR sent the review back to its supervisor, with a reason that is for the supervisor. */
    public function returned(PerformanceReview $review, string $reason): void
    {
        $this->safely(function () use ($review, $reason) {
            $name = $this->nameOf($review);
            foreach ($this->usersForStaff((int) $review->supervisor_staff_id) as $user) {
                $this->send($user, $review, self::RETURNED, 'A review was sent back to you',
                    "HR sent {$name}'s review for {$this->cycleOf($review)} back to you: {$reason}");
            }
        });
    }

    /** HR finalised the review: the staff member can now read their final rating. */
    public function finalised(PerformanceReview $review): void
    {
        $this->safely(function () use ($review) {
            foreach ($this->usersForStaff((int) $review->staff_id) as $user) {
                $this->send($user, $review, self::FINALISED, 'Your performance review is finalised',
                    "HR has finalised your review for {$this->cycleOf($review)}. Open it to see your final rating.");
            }
        });
    }

    /** HR corrected a finalised review. */
    public function corrected(PerformanceReview $review): void
    {
        $this->safely(function () use ($review) {
            foreach ($this->usersForStaff((int) $review->staff_id) as $user) {
                $this->send($user, $review, self::CORRECTED, 'Your final rating was corrected',
                    "HR corrected the final rating on your review for {$this->cycleOf($review)}. Open it to see it.");
            }
        });
    }

    // ── who is asked ──────────────────────────────────────────────────────

    private function askSupervisor(PerformanceReview $review): void
    {
        if (! $review->supervisor_staff_id) {
            return;
        }
        foreach ($this->usersForStaff((int) $review->supervisor_staff_id) as $user) {
            $this->send($user, $review, self::WAITING_FOR_SUPERVISOR, 'A review is waiting for your rating',
                "{$this->nameOf($review)}'s review for {$this->cycleOf($review)} is waiting for your rating.");
        }
    }

    private function askHr(PerformanceReview $review): void
    {
        foreach ($this->hrUsersWhoCanGiveTheFinalRating($review) as $user) {
            $this->send($user, $review, self::WAITING_FOR_HR, 'A review is waiting for HR',
                "{$this->nameOf($review)}'s review for {$this->cycleOf($review)} is waiting for the final rating.");
        }
    }

    /**
     * Everyone with an HR role, except the staff member the review is about and (when it had a supervisor) the supervisor,
     * who may not give the final rating on a review they are part of.
     */
    private function hrUsersWhoCanGiveTheFinalRating(PerformanceReview $review): Collection
    {
        $ids = DB::table('cb_role_user as ru')->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('r.name', PerformanceAccess::HR_ROLES)->pluck('ru.user_id')->unique()->all();

        return $this->usable(User::whereIn('id', $ids))->filter(function (User $user) use ($review) {
            $staffId = $user->linkedStaff()?->id;
            if ($staffId && (int) $staffId === (int) $review->staff_id) {
                return false;
            }

            return ! ($staffId && $review->route === PerformanceReview::ROUTE_SUPERVISOR && (int) $staffId === (int) $review->supervisor_staff_id);
        })->values();
    }

    /** The logins linked to a staff member that can be used: not blocked and activated. Someone who has left is not told. */
    private function usersForStaff(int $staffId): Collection
    {
        if ($staffId <= 0 || ! Staff::whereKey($staffId)->where('current_status_id', 1)->exists()) {
            return collect();
        }

        $ids = UserStaffLink::where('staff_id', $staffId)->pluck('user_id')->merge(User::where('staff_id', $staffId)->pluck('id'))->unique()->all();

        return $this->usable(User::whereIn('id', $ids));
    }

    private function usable($query): Collection
    {
        return $query->where(fn ($q) => $q->whereNull('block')->orWhere('block', 0))->get()->filter(fn (User $user) => $user->activation != 0)->values();
    }

    // ── the alert ─────────────────────────────────────────────────────────

    private function send(User $user, PerformanceReview $review, string $type, string $title, string $message, ?string $url = null): void
    {
        Notification::create([
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['review_id' => $review->id, 'cycle_id' => $review->cycle_id, 'event' => $type],
            'title' => $title,
            'message' => $message,
            'action_url' => $url ?? route('performance.review', $review->id, false),
        ]);
    }

    /**
     * The logins of everyone invited to a meeting, once each, with the staff id they were invited as (0 for an HR person with no
     * staff record). Someone with no usable login is simply not here: HR sees who was not reached on the meeting page.
     *
     * @return array<int, array{0: User, 1: int}>
     */
    private function attendeeUsers(PerformanceMeeting $meeting): array
    {
        $found = [];
        foreach ($meeting->attendees()->get() as $attendee) {
            $users = collect();
            if ($attendee->user_id) {
                $users = $this->usable(User::whereKey($attendee->user_id));
            } elseif ($attendee->staff_id) {
                $users = $this->usersForStaff((int) $attendee->staff_id);
            }
            foreach ($users as $user) {
                $found[$user->id] ??= [$user, (int) $attendee->staff_id];
            }
        }

        return array_values($found);
    }

    private function hearingOf(PerformanceReview $review): ?PerformanceMeeting
    {
        return PerformanceMeeting::where('review_id', $review->id)->where('kind', PerformanceMeeting::KIND_APPEAL)->first();
    }

    private function meetingUrl(PerformanceMeeting $meeting, PerformanceReview $review): string
    {
        return $meeting->kind === PerformanceMeeting::KIND_APPEAL
            ? route('performance.appeal', $review->id, false)
            : route('performance.meeting', $review->id, false);
    }

    private function nameOf(PerformanceReview $review): string
    {
        return $review->staff?->full_names ?? 'A staff member';
    }

    private function cycleOf(PerformanceReview $review): string
    {
        return $review->cycle?->name ?? 'the review cycle';
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

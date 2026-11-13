<?php

namespace App\Services\Performance;

use App\Models\PerformanceCycle;
use App\Models\PerformanceKpi;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewEvent;
use App\Models\PerformanceReviewItem;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Previews and publishes a review cycle. Every active staff member whose position has at least one active criterion gets
 * a review; the review keeps a copy of the criteria and of who the supervisor is, so editing either afterwards changes
 * nothing that was already published. Staff whose position has no criteria are left out and listed, so HR can fix it.
 */
class PerformanceCycleService
{
    public function __construct(private PerformanceAccess $access, private PerformanceNotifications $notifications) {}

    /**
     * What publishing would do: who gets a review and who it goes to first, and who is left out and why.
     *
     * @return array{include: array<int, array>, exclude: array<int, array>, counts: array<string, int>}
     */
    public function preview(PerformanceCycle $cycle): array
    {
        $existing = PerformanceReview::where('cycle_id', $cycle->id)->pluck('staff_id')->map(fn ($id) => (int) $id)->flip();
        $kpiCount = PerformanceKpi::active()->selectRaw('position_id, count(*) as total')->groupBy('position_id')->pluck('total', 'position_id');
        $assigned = PerformanceSupervisorAssignment::pluck('supervisor_staff_id', 'staff_id');
        $positions = Position::pluck('position_name', 'id');

        $include = [];
        $exclude = [];
        foreach (Staff::where('current_status_id', 1)->orderBy('full_names')->get() as $staff) {
            if ($existing->has((int) $staff->id)) {
                continue; // already has a review in this cycle
            }

            $base = ['staff_id' => (int) $staff->id, 'name' => $staff->full_names, 'position' => $positions[$staff->position_id] ?? null];

            if (! $staff->position_id) {
                $exclude[] = $base + ['reason' => 'No position'];

                continue;
            }
            if (! ($kpiCount[$staff->position_id] ?? 0)) {
                $exclude[] = $base + ['reason' => 'No criteria set up for this position'];

                continue;
            }

            $route = $this->access->route($staff, isset($assigned[$staff->id]) ? (int) $assigned[$staff->id] : null);
            $include[] = $base + [
                'criteria' => (int) $kpiCount[$staff->position_id],
                'route' => $route['route'],
                'reason' => $route['reason'],
                'reason_text' => PerformanceAccess::REASONS[$route['reason']] ?? $route['reason'],
                'supervisor' => $route['supervisor_staff_id'] ? Staff::whereKey($route['supervisor_staff_id'])->value('full_names') : null,
                'has_login' => $this->access->hasLogin($staff),
            ];
        }

        return [
            'include' => $include,
            'exclude' => $exclude,
            'counts' => [
                'reviews' => count($include),
                'with_supervisor' => count(array_filter($include, fn ($row) => $row['route'] === PerformanceReview::ROUTE_SUPERVISOR)),
                'straight_to_hr' => count(array_filter($include, fn ($row) => $row['route'] === PerformanceReview::ROUTE_HR)),
                'left_out' => count($exclude),
                'already_made' => $existing->count(),
            ],
        ];
    }

    /** Publishes a draft cycle: makes a review for everyone in the preview. */
    public function publish(PerformanceCycle $cycle, User $actor): int
    {
        if (! $cycle->isDraft()) {
            throw new PerformanceException('Only a draft cycle can be published.');
        }

        $made = DB::transaction(function () use ($cycle, $actor) {
            $made = $this->makeReviews($cycle, $actor);
            if ($made->isEmpty()) {
                throw new PerformanceException('Nobody would get a review. Set up criteria for at least one position that has active staff first.');
            }
            $cycle->update(['status' => PerformanceCycle::STATUS_PUBLISHED, 'published_at' => now()]);

            return $made;
        });

        $this->notifications->opened($cycle, $made);

        return $made->count();
    }

    /** Adds reviews for staff who have none yet in a published cycle (for example someone who joined after it opened). */
    public function addMissing(PerformanceCycle $cycle, User $actor): int
    {
        if (! $cycle->isPublished()) {
            throw new PerformanceException('Reviews can only be added to a published cycle.');
        }

        $made = DB::transaction(fn () => $this->makeReviews($cycle, $actor));

        $this->notifications->opened($cycle, $made);

        return $made->count();
    }

    public function close(PerformanceCycle $cycle): void
    {
        if (! $cycle->isPublished()) {
            throw new PerformanceException('Only a published cycle can be closed.');
        }
        $cycle->update(['status' => PerformanceCycle::STATUS_CLOSED, 'closed_at' => now()]);
    }

    /** @return Collection<int, PerformanceReview> the reviews made */
    private function makeReviews(PerformanceCycle $cycle, User $actor): Collection
    {
        $preview = $this->preview($cycle);
        if ($preview['include'] === []) {
            return collect();
        }

        $assigned = PerformanceSupervisorAssignment::pluck('supervisor_staff_id', 'staff_id');
        $kpis = PerformanceKpi::active()->ordered()->get()->groupBy('position_id');
        $made = collect();

        foreach ($preview['include'] as $row) {
            $staff = Staff::find($row['staff_id']);
            $route = $this->access->route($staff, isset($assigned[$staff->id]) ? (int) $assigned[$staff->id] : null);

            $review = PerformanceReview::create([
                'cycle_id' => $cycle->id,
                'staff_id' => $staff->id,
                'position_id' => $staff->position_id,
                'position_name' => $row['position'],
                'reports_to_position_id' => $this->access->bossPositionId($staff),
                'supervisor_staff_id' => $route['supervisor_staff_id'],
                'route' => $route['route'],
                'route_reason' => $route['reason'],
                'status' => PerformanceReview::STATUS_AWAITING_SELF,
            ]);

            foreach ($kpis->get($staff->position_id, collect()) as $kpi) {
                PerformanceReviewItem::create([
                    'review_id' => $review->id,
                    'kpi_id' => $kpi->id,
                    'name' => $kpi->name,
                    'description' => $kpi->description,
                    'weight' => $kpi->weight,
                    'sort_order' => $kpi->sort_order,
                ]);
            }

            PerformanceReviewEvent::record($review, 'created', $actor->id, ['note' => PerformanceAccess::REASONS[$route['reason']] ?? null]);
            $made->push($review);
        }

        return $made;
    }
}

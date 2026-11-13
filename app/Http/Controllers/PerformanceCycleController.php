<?php

namespace App\Http\Controllers;

use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceAccess;
use App\Services\Performance\PerformanceCycleService;
use App\Traits\RespondsToPerformanceErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR > Performance Review > Review Cycles: HR creates a cycle for a period, previews who it would reach and who each
 * review goes to first, publishes it (which makes the reviews), adds late joiners, and closes it. A draft can still be
 * edited or deleted; a published cycle cannot, because reviews were made from it. Only HR can open this.
 */
class PerformanceCycleController extends Controller
{
    use RespondsToPerformanceErrors;

    private function allow(): void
    {
        abort_unless(Auth::check() && app(PerformanceAccess::class)->isHr(Auth::user()), 403, 'Only HR can manage review cycles.');
    }

    public function index()
    {
        $this->allow();

        return view('hr.performance.cycles');
    }

    public function list(): JsonResponse
    {
        $this->allow();

        $counts = PerformanceReview::selectRaw("cycle_id, count(*) as total, sum(case when status = 'finalised' then 1 else 0 end) as done")
            ->groupBy('cycle_id')->get()->keyBy('cycle_id');

        return response()->json([
            'success' => true,
            'cycles' => PerformanceCycle::orderByDesc('period_start')->orderByDesc('id')->get()->map(fn (PerformanceCycle $cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'period_start' => $cycle->period_start?->toDateString(),
                'period_end' => $cycle->period_end?->toDateString(),
                'open_date' => $cycle->open_date?->toDateString(),
                'deadline' => $cycle->deadline?->toDateString(),
                'status' => $cycle->status,
                'reviews' => (int) ($counts[$cycle->id]->total ?? 0),
                'finalised' => (int) ($counts[$cycle->id]->done ?? 0),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow();

        $cycle = PerformanceCycle::create($this->validated($request) + ['status' => PerformanceCycle::STATUS_DRAFT, 'created_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => "{$cycle->name} was created as a draft.", 'id' => $cycle->id]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->allow();

        $cycle = PerformanceCycle::findOrFail($id);
        if (! $cycle->isDraft()) {
            return response()->json(['success' => false, 'error' => 'Reviews were made from this cycle, so it can no longer be edited.'], 422);
        }
        $cycle->update($this->validated($request, $cycle));

        return response()->json(['success' => true, 'message' => "{$cycle->name} was updated."]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->allow();

        $cycle = PerformanceCycle::findOrFail($id);
        if (! $cycle->isDraft()) {
            return response()->json(['success' => false, 'error' => 'Only a draft cycle can be deleted. Close this one instead.'], 422);
        }
        $cycle->delete();

        return response()->json(['success' => true, 'message' => "{$cycle->name} was deleted."]);
    }

    public function preview(PerformanceCycleService $service, int $id): JsonResponse
    {
        $this->allow();

        $cycle = PerformanceCycle::findOrFail($id);

        return response()->json(['success' => true] + $service->preview($cycle));
    }

    public function publish(PerformanceCycleService $service, int $id): JsonResponse
    {
        $this->allow();

        return $this->attempt(function () use ($service, $id) {
            $cycle = PerformanceCycle::findOrFail($id);
            $made = $service->publish($cycle, Auth::user());

            return response()->json(['success' => true, 'message' => "{$cycle->name} is published. {$made} ".($made === 1 ? 'review was' : 'reviews were').' made.']);
        });
    }

    public function addMissing(PerformanceCycleService $service, int $id): JsonResponse
    {
        $this->allow();

        return $this->attempt(function () use ($service, $id) {
            $cycle = PerformanceCycle::findOrFail($id);
            $made = $service->addMissing($cycle, Auth::user());

            return response()->json(['success' => true, 'message' => $made === 0 ? 'Everyone who can be reviewed already has a review.' : "{$made} ".($made === 1 ? 'review was' : 'reviews were').' added.']);
        });
    }

    public function close(PerformanceCycleService $service, int $id): JsonResponse
    {
        $this->allow();

        return $this->attempt(function () use ($service, $id) {
            $cycle = PerformanceCycle::findOrFail($id);
            $service->close($cycle);

            return response()->json(['success' => true, 'message' => "{$cycle->name} is closed. Its reviews can be read but no longer changed."]);
        });
    }

    private function validated(Request $request, ?PerformanceCycle $cycle = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', function ($attribute, $value, $fail) use ($cycle) {
                $taken = PerformanceCycle::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
                    ->when($cycle, fn ($query) => $query->where('id', '!=', $cycle->id))->exists();
                if ($taken) {
                    $fail('There is already a cycle with this name.');
                }
            }],
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'open_date' => 'nullable|date',
            // The deadline cannot come before the day the cycle opens, when HR gave one
            'deadline' => $request->filled('open_date') ? 'nullable|date|after_or_equal:open_date' : 'nullable|date',
        ]);

        return [
            'name' => trim($data['name']),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'open_date' => $data['open_date'] ?? null,
            'deadline' => $data['deadline'] ?? null,
        ];
    }
}

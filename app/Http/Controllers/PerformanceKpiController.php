<?php

namespace App\Http\Controllers;

use App\Models\PerformanceKpi;
use App\Models\PerformanceReviewItem;
use App\Models\Position;
use App\Models\Staff;
use App\Services\Performance\PerformanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR > Performance Review > KPIs: the criteria each position is rated on, and what each one is worth (its weight). HR adds,
 * edits, switches off and deletes them. A criterion that a review was made from can only be switched off, because the
 * review keeps its own copy and HR should still be able to see what was rated. Only HR can open this.
 */
class PerformanceKpiController extends Controller
{
    private function allow(): void
    {
        abort_unless(Auth::check() && app(PerformanceAccess::class)->isHr(Auth::user()), 403, 'Only HR can set up the review criteria.');
    }

    public function index()
    {
        $this->allow();

        return view('hr.performance.kpis');
    }

    /** The positions HR can set criteria for, each with who it reports to and how many criteria it has. */
    public function positions(): JsonResponse
    {
        $this->allow();

        $staffCounts = Staff::where('current_status_id', 1)->selectRaw('position_id, count(*) as total')->groupBy('position_id')->pluck('total', 'position_id');
        $kpis = PerformanceKpi::active()->selectRaw('position_id, count(*) as total, sum(weight) as weight_total')->groupBy('position_id')->get()->keyBy('position_id');
        $all = Position::orderBy('position_name')->get();
        $names = $all->pluck('position_name', 'id');

        return response()->json([
            'success' => true,
            'positions' => $all->where('is_active', true)->map(fn (Position $position) => [
                'id' => $position->id,
                'name' => $position->position_name,
                'reports_to' => $position->reports_to_id ? ($names[$position->reports_to_id] ?? null) : null,
                'staff_count' => (int) ($staffCounts[$position->id] ?? 0),
                'kpi_count' => (int) ($kpis[$position->id]->total ?? 0),
            ])->values(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $this->allow();

        $positionId = (int) $request->query('position_id');
        $position = Position::find($positionId);
        if (! $position) {
            return response()->json(['success' => false, 'error' => 'Choose a position.'], 422);
        }

        return response()->json([
            'success' => true,
            'position' => [
                'id' => $position->id,
                'name' => $position->position_name,
                'reports_to' => $position->reports_to_id ? Position::whereKey($position->reports_to_id)->value('position_name') : null,
            ],
            'kpis' => PerformanceKpi::where('position_id', $position->id)->ordered()->get()->map(fn (PerformanceKpi $kpi) => [
                'id' => $kpi->id,
                'name' => $kpi->name,
                'description' => $kpi->description,
                'weight' => $kpi->weight,
                'sort_order' => $kpi->sort_order,
                'is_active' => $kpi->is_active,
                'used' => $kpi->isUsed(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow();

        $data = $this->validated($request);
        $position = Position::findOrFail($data['position_id']);

        $kpi = PerformanceKpi::create($data + [
            'sort_order' => $data['sort_order'] ?? (((int) PerformanceKpi::where('position_id', $position->id)->max('sort_order')) + 10),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => "{$kpi->name} was added to {$position->position_name}.", 'id' => $kpi->id]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->allow();

        $kpi = PerformanceKpi::findOrFail($id);
        $data = $this->validated($request, $kpi);
        // A criterion stays with its position; copy it to move it somewhere else
        unset($data['position_id']);
        $kpi->update($data);

        return response()->json(['success' => true, 'message' => "{$kpi->name} was updated. Reviews already published keep what they had."]);
    }

    public function toggle(int $id): JsonResponse
    {
        $this->allow();

        $kpi = PerformanceKpi::findOrFail($id);
        $kpi->update(['is_active' => ! $kpi->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $kpi->is_active,
            'message' => $kpi->is_active
                ? "{$kpi->name} is on again and will be in the next cycle you publish."
                : "{$kpi->name} is switched off. It will not be in the next cycle you publish, and reviews already published keep it.",
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->allow();

        $kpi = PerformanceKpi::findOrFail($id);
        $used = PerformanceReviewItem::where('kpi_id', $kpi->id)->count();
        if ($used > 0) {
            return response()->json([
                'success' => false,
                'error' => "Cannot delete {$kpi->name} because ".$used.($used === 1 ? ' review was' : ' reviews were').' made with it. Switch it off instead.',
            ], 422);
        }

        $kpi->delete();

        return response()->json(['success' => true, 'message' => "{$kpi->name} was deleted."]);
    }

    /** Copies the active criteria of one position to another, skipping any the other already has by the same name. */
    public function copy(Request $request): JsonResponse
    {
        $this->allow();

        $data = $request->validate([
            'from_position_id' => 'required|integer|exists:sys_set_staff_positions,id',
            'to_position_id' => 'required|integer|exists:sys_set_staff_positions,id|different:from_position_id',
        ]);

        $have = PerformanceKpi::where('position_id', $data['to_position_id'])->pluck('name')->map(fn ($name) => mb_strtolower($name))->all();
        $next = ((int) PerformanceKpi::where('position_id', $data['to_position_id'])->max('sort_order')) + 10;
        $copied = 0;

        foreach (PerformanceKpi::where('position_id', $data['from_position_id'])->active()->ordered()->get() as $kpi) {
            if (in_array(mb_strtolower($kpi->name), $have, true)) {
                continue;
            }
            PerformanceKpi::create([
                'position_id' => $data['to_position_id'],
                'name' => $kpi->name,
                'description' => $kpi->description,
                'weight' => $kpi->weight,
                'sort_order' => $next,
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);
            $next += 10;
            $copied++;
        }

        return response()->json([
            'success' => true,
            'copied' => $copied,
            'message' => $copied === 0 ? 'Nothing to copy: that position has no criteria, or this one already has them all.' : $copied.($copied === 1 ? ' criterion was' : ' criteria were').' copied.',
        ]);
    }

    private function validated(Request $request, ?PerformanceKpi $kpi = null): array
    {
        $data = $request->validate([
            'position_id' => [$kpi ? 'nullable' : 'required', 'integer', 'exists:sys_set_staff_positions,id'],
            'name' => ['required', 'string', 'max:150', function ($attribute, $value, $fail) use ($request, $kpi) {
                // "attendance" and "Attendance" are the same criterion on one position
                $positionId = $kpi ? $kpi->position_id : (int) $request->input('position_id');
                $taken = PerformanceKpi::where('position_id', $positionId)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
                    ->when($kpi, fn ($query) => $query->where('id', '!=', $kpi->id))->exists();
                if ($taken) {
                    $fail('This position already has a criterion with this name.');
                }
            }],
            'description' => 'nullable|string|max:1000',
            'weight' => 'nullable|numeric|min:0.01|max:100',
            'sort_order' => 'nullable|integer|min:0|max:65000',
        ]);

        return [
            'position_id' => $data['position_id'] ?? null,
            'name' => trim($data['name']),
            'description' => isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : null,
            'weight' => isset($data['weight']) && $data['weight'] !== '' ? (float) $data['weight'] : 1.0,
        ] + (isset($data['sort_order']) ? ['sort_order' => (int) $data['sort_order']] : []);
    }
}

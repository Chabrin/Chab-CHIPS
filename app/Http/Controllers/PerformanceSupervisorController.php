<?php

namespace App\Http\Controllers;

use App\Models\PerformanceSupervisorAssignment;
use App\Models\Position;
use App\Models\Staff;
use App\Services\Performance\PerformanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * HR > Performance Review > Supervisors: where several people hold the position a staff member reports to, HR names which
 * one is their supervisor. The choice is kept and used by every cycle after. Only someone who holds the position the staff
 * member reports to can be named. Only HR can open this.
 */
class PerformanceSupervisorController extends Controller
{
    private function allow(): void
    {
        abort_unless(Auth::check() && app(PerformanceAccess::class)->isHr(Auth::user()), 403, 'Only HR can name supervisors.');
    }

    public function index()
    {
        $this->allow();

        return view('hr.performance.supervisors');
    }

    /** Every active staff member who reports to a position, with who their supervisor would be and who could be named. */
    public function list(PerformanceAccess $access): JsonResponse
    {
        $this->allow();

        $positions = Position::pluck('position_name', 'id');
        $bosses = Position::pluck('reports_to_id', 'id');
        $assigned = PerformanceSupervisorAssignment::pluck('supervisor_staff_id', 'staff_id');

        $rows = [];
        foreach (Staff::where('current_status_id', 1)->whereNotNull('position_id')->orderBy('full_names')->get() as $staff) {
            $boss = $bosses[$staff->position_id] ?? null;
            if (! $boss) {
                continue; // no "reports to": these always go straight to HR
            }

            $candidates = $access->supervisorCandidates($staff);
            $route = $access->route($staff, isset($assigned[$staff->id]) ? (int) $assigned[$staff->id] : null);

            $rows[] = [
                'staff_id' => (int) $staff->id,
                'name' => $staff->full_names,
                'position_id' => (int) $staff->position_id,
                'position' => $positions[$staff->position_id] ?? null,
                'reports_to' => $positions[$boss] ?? null,
                'named_id' => isset($assigned[$staff->id]) ? (int) $assigned[$staff->id] : null,
                'candidates' => $candidates->map(fn (Staff $candidate) => [
                    'id' => (int) $candidate->id,
                    'name' => $candidate->full_names,
                    'has_login' => $access->hasLogin($candidate),
                ])->values(),
                'route' => $route['route'],
                'reason' => $route['reason'],
                'reason_text' => PerformanceAccess::REASONS[$route['reason']] ?? $route['reason'],
                'supervisor' => $route['supervisor_staff_id'] ? $candidates->firstWhere('id', $route['supervisor_staff_id'])?->full_names : null,
            ];
        }

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    /**
     * Names one supervisor for several staff at once, or clears the name (supervisor_staff_id empty). Nothing is saved
     * unless every staff member in the request can have that supervisor.
     */
    public function assign(Request $request, PerformanceAccess $access): JsonResponse
    {
        $this->allow();

        $data = $request->validate([
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => 'integer|exists:sys_staff,id',
            'supervisor_staff_id' => 'nullable|integer|exists:sys_staff,id',
        ]);
        $supervisorId = $data['supervisor_staff_id'] ?? null;
        $staffList = Staff::whereIn('id', $data['staff_ids'])->get();

        if ($supervisorId) {
            $refused = $staffList->filter(fn (Staff $staff) => ! $access->supervisorCandidates($staff)->contains(fn (Staff $candidate) => (int) $candidate->id === (int) $supervisorId));
            if ($refused->isNotEmpty()) {
                $supervisor = Staff::find($supervisorId);

                return response()->json([
                    'success' => false,
                    'error' => ($supervisor?->full_names ?? 'That person').' does not hold the position these staff report to: '.$refused->pluck('full_names')->implode(', ').'. Nothing was changed.',
                ], 422);
            }
        }

        DB::transaction(function () use ($staffList, $supervisorId) {
            foreach ($staffList as $staff) {
                if ($supervisorId) {
                    PerformanceSupervisorAssignment::updateOrCreate(['staff_id' => $staff->id], ['supervisor_staff_id' => $supervisorId, 'set_by' => Auth::id()]);
                } else {
                    PerformanceSupervisorAssignment::where('staff_id', $staff->id)->get()->each->delete();
                }
            }
        });

        $count = $staffList->count();
        $people = $count.($count === 1 ? ' staff member' : ' staff members');

        return response()->json([
            'success' => true,
            'message' => $supervisorId
                ? Staff::whereKey($supervisorId)->value('full_names').' is now the supervisor of '.$people.'. Reviews already published keep the supervisor they had; HR can change those one by one.'
                : 'The named supervisor was cleared for '.$people.'.',
        ]);
    }
}

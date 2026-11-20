<?php

namespace App\Http\Controllers;

use App\Models\PerformanceCommitteeMember;
use App\Models\PerformanceSetting;
use App\Models\Staff;
use App\Services\Performance\PerformanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR > Performance Review > Committee: the staff HR may pick for a joint review meeting or an appeal hearing, and the two
 * numbers HR can change without a developer (the fewest committee members who must attend, and the appeal period). The same
 * committee sits for both. Only HR can open this.
 */
class PerformanceCommitteeController extends Controller
{
    public function __construct(private PerformanceAccess $access) {}

    private function allow(): void
    {
        abort_unless(Auth::check() && $this->access->isHr(Auth::user()), 403, 'Only HR can manage the committee.');
    }

    public function index()
    {
        $this->allow();

        return view('hr.performance.committee');
    }

    /** The members, the active staff who could be added, and the two settings. */
    public function list(): JsonResponse
    {
        $this->allow();

        $members = PerformanceCommitteeMember::with('staff')->get()->filter(fn ($member) => $member->staff)
            ->map(fn ($member) => [
                'id' => $member->id,
                'staff_id' => (int) $member->staff_id,
                'name' => $member->staff->full_names,
                'active' => (int) $member->staff->current_status_id === 1,
                'has_login' => $this->access->hasLogin($member->staff),
            ])->sortBy('name')->values();

        $taken = $members->pluck('staff_id')->all();
        $candidates = Staff::where('current_status_id', 1)->whereNotIn('id', $taken)->orderBy('full_names')->get(['id', 'full_names'])
            ->map(fn ($staff) => ['staff_id' => (int) $staff->id, 'name' => $staff->full_names])->values();

        return response()->json([
            'success' => true,
            'members' => $members,
            'candidates' => $candidates,
            'settings' => [
                'min_committee_present' => max(1, PerformanceSetting::number(PerformanceSetting::MIN_COMMITTEE)),
                'appeal_window_days' => PerformanceSetting::number(PerformanceSetting::APPEAL_DAYS),
            ],
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $this->allow();

        $staff = Staff::where('current_status_id', 1)->find((int) $request->input('staff_id'));
        if (! $staff) {
            return response()->json(['success' => false, 'error' => 'Pick an active staff member.'], 422);
        }
        PerformanceCommitteeMember::firstOrCreate(['staff_id' => $staff->id], ['added_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => $staff->full_names.' is on the committee.']);
    }

    public function remove(int $id): JsonResponse
    {
        $this->allow();

        PerformanceCommitteeMember::whereKey($id)->delete();

        return response()->json(['success' => true, 'message' => 'Removed from the committee. Meetings already scheduled keep their invitations.']);
    }

    public function settings(Request $request): JsonResponse
    {
        $this->allow();

        $min = $request->input('min_committee_present');
        $days = $request->input('appeal_window_days');
        if (! is_numeric($min) || (int) $min < 1 || (int) $min > 50 || (string) (int) $min !== (string) $min) {
            return response()->json(['success' => false, 'error' => 'The least number of committee members must be a whole number from 1 to 50.'], 422);
        }
        if (! is_numeric($days) || (int) $days < 1 || (int) $days > 365 || (string) (int) $days !== (string) $days) {
            return response()->json(['success' => false, 'error' => 'The appeal period must be a whole number of days from 1 to 365.'], 422);
        }
        PerformanceSetting::put(PerformanceSetting::MIN_COMMITTEE, (int) $min);
        PerformanceSetting::put(PerformanceSetting::APPEAL_DAYS, (int) $days);

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }
}

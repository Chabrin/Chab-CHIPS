<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Services\Leave\LeaveOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeaveOverviewController extends Controller
{
    public function show(Request $request, LeaveOverviewService $overview)
    {
        abort_unless($request->user()->hasRole('hr_manager') || $request->user()->hasRole('directors'), 403);
        $input = $request->validate(['staff_id' => 'nullable|integer', 'year' => 'nullable|integer|min:2000|max:2100']);
        if (empty($input['staff_id'])) {
            return response()->json(['employees' => Staff::whereIn('current_status_id', [1, 4])
                ->orderBy('full_names')->get(['id', 'full_names', 'staff_number'])->map(fn ($staff) => [
                    'id' => $staff->id, 'name' => $staff->full_names, 'number' => $staff->staff_number,
                ])]);
        }
        $staff = Staff::whereIn('current_status_id', [1, 4])->findOrFail($input['staff_id']);
        try {
            return response()->json($overview->forEmployee($staff, (int) ($input['year'] ?? now()->year)));
        } catch (\Throwable $exception) {
            Log::error('Leave overview could not be loaded', ['exception' => $exception->getMessage()]);
            return response()->json(['message' => 'Unable to load the leave overview. Please try again.'], 500);
        }
    }
}

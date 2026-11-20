<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * A person who worked here before comes back: their existing record is brought back to Active, keeping their staff number, and a
 * new contract is added to it. Nobody is added a second time.
 */
class StaffRehireController extends Controller
{
    /**
     * Whether an ID number is already on record, for the Add Staff form to say so as soon as it is typed. Says who and where to find them,
     * and whether they can be rehired (they left). Nothing else about them is given away.
     */
    public function former(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('create_staff') || auth()->user()->hasPermission('read_staff'), 403);

        $nationalId = trim((string) $request->query('national_id', ''));
        if (mb_strlen($nationalId) < 4) {
            return response()->json(['found' => false]);
        }

        $staff = Staff::where('national_id', $nationalId)->first(['id', 'full_names', 'staff_number', 'current_status_id']);
        if (!$staff) {
            return response()->json(['found' => false]);
        }

        $left = (int) $staff->current_status_id === 3;

        return response()->json([
            'found' => true,
            'name' => $staff->full_names,
            'staff_number' => $staff->staff_number,
            'status' => Staff::STATUSES[(int) $staff->current_status_id] ?? 'Unknown',
            'can_rehire' => $left,
            'url' => route('staff.show', Crypt::encrypt($staff->id)),
        ]);
    }

    /**
     * Bring a former staff member back. They keep their staff number; the next step is their new contract.
     */
    public function rehire(Request $request, string $encryptedId)
    {
        abort_unless(auth()->user()->hasPermission('update_staff'), 403);
        $validated = $request->validate(['hire_date' => 'nullable|date']);
        $staff = Staff::findOrFail((int) Crypt::decrypt($encryptedId));

        if ((int) $staff->current_status_id !== 3) {
            return redirect()->route('staff.show', Crypt::encrypt($staff->id))
                ->with('error', $staff->full_names . ' has not left, so there is nothing to rehire. Their status is ' . (Staff::STATUSES[(int) $staff->current_status_id] ?? 'not Terminated') . '.');
        }

        DB::transaction(function () use ($staff, $validated) {
            // Same record, same staff number: only the status and the hire date change
            $staff->current_status_id = 1;
            $staff->hire_date = $validated['hire_date'] ?? now()->toDateString();
            $staff->save();
        });

        $redirect = auth()->user()->hasPermission('create_contracts')
            ? redirect()->route('contract.create', ['staff_id' => $staff->id, 'position_id' => $staff->position_id])
            : redirect()->route('staff.show', Crypt::encrypt($staff->id));

        return $redirect->with('success', $staff->full_names . ' is back as Active and keeps staff number ' . $staff->staff_number . '. They have no current contract, so add the new one now.');
    }
}

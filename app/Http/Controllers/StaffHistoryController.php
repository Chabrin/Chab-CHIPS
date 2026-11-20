<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\StaffHistory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The History tab of a staff profile and of a contract: who changed what, when, and what it was before. Read only.
 */
class StaffHistoryController extends Controller
{
    /**
     * History of a staff member (the id is encrypted, as in the profile's own address).
     */
    public function staff(StaffHistory $history, string $encryptedId)
    {
        abort_unless(auth()->user()->hasPermission('read_staff'), 403);

        try {
            $staffId = (int) Crypt::decrypt($encryptedId);
        } catch (DecryptException) {
            abort(404);
        }

        return view('staff.partials.history', ['history' => $history->forStaff($staffId), 'subject' => 'staff member']);
    }

    /**
     * History of one contract.
     */
    public function contract(StaffHistory $history, int $id)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('read_contracts') || $user->hasPermission('read_staff'), 403);
        abort_unless(Contract::whereKey($id)->exists(), 404);

        return view('staff.partials.history', ['history' => $history->forContract($id), 'subject' => 'contract']);
    }
}

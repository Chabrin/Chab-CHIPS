<?php

namespace App\Http\Controllers;

use App\Models\Recovery;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manual, staff-triggered bulk action that posts Approved Recovery records to a
 * landlord's account (cb_landlord_account) as a debit. Deliberately NOT wired into
 * automatic returns processing — nothing here runs unless a user explicitly selects
 * recoveries and clicks "Post to Landlord Account" on this page. Once posted, the
 * debit is picked up the normal way by the existing, already-live
 * ReturnsDeductionsTrait::insertAdvancePaymentLines() the next time that landlord's
 * return is generated — same as any other landlord-account charge in this app.
 */
class RecoveryLandlordPostingController extends Controller
{
    /** Existing "Rent recovery" billing code, already whitelisted for automatic
     *  pickup in ReturnsDeductionsTrait::insertAdvancePaymentLines(). */
    private const ENTRY_TYPE_CODE = '0024';

    public function index()
    {
        if (!Auth::user()->isAbleTo('update_recoveries')) {
            abort(403);
        }

        return view('property.properties.recovery.landlord-posting');
    }

    /**
     * List Approved, not-yet-posted recoveries (AJAX).
     */
    public function getData(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('read_recoveries')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $recoveries = Recovery::with(['client', 'property'])
                ->where('current_status_id', 5) // Approved
                ->whereNull('posted_to_landlord_at')
                ->orderBy('recovery_date', 'desc')
                ->get()
                ->map(function ($recovery) {
                    return [
                        'id' => $recovery->id,
                        'encrypted_id' => Crypt::encrypt($recovery->id),
                        'ref_number' => $recovery->ref_number ?? '-',
                        'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-',
                        'amount' => (float) $recovery->amount,
                        'amount_formatted' => number_format($recovery->amount ?? 0, 2),
                        'client_name' => $recovery->client->names ?? '-',
                        'property_name' => $recovery->property->property_name ?? '-',
                        'narration' => $recovery->narration ?? '-',
                    ];
                });

            return response()->json(['data' => $recoveries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recoveries for landlord posting: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load recoveries'], 500);
        }
    }

    /**
     * Bulk-post the selected recoveries to their landlords' accounts.
     */
    public function post(Request $request)
    {
        if (!Auth::user()->isAbleTo('update_recoveries')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No recoveries selected'], 422);
        }

        $posted = 0;
        $skipped = [];

        try {
            $recoveries = Recovery::with(['client', 'property'])->whereIn('id', $ids)->get();

            foreach ($recoveries as $recovery) {
                if ($recovery->current_status_id !== 5) {
                    $skipped[] = $recovery->ref_number . ' (not Approved)';
                    continue;
                }
                if ($recovery->posted_to_landlord_at !== null) {
                    $skipped[] = $recovery->ref_number . ' (already posted)';
                    continue;
                }

                DB::transaction(function () use ($recovery) {
                    $this->postRecoveryToLandlordAccount($recovery);
                    $recovery->posted_to_landlord_at = now();
                    $recovery->save();
                });

                $posted++;
            }

            $message = $posted . ' recover' . ($posted === 1 ? 'y' : 'ies') . ' posted to landlord accounts.';
            if (!empty($skipped)) {
                $message .= ' Skipped: ' . implode(', ', $skipped);
            }

            Log::info('Recoveries posted to landlord account by user ' . Auth::id(), ['ids' => $ids, 'posted' => $posted, 'skipped' => $skipped]);

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error posting recoveries to landlord account: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to post recoveries'], 500);
        }
    }

    /**
     * Insert a single debit entry into cb_landlord_account for this recovery,
     * mirroring ClientAdvanceController::postToLandlordAccount()'s write pattern.
     */
    private function postRecoveryToLandlordAccount(Recovery $recovery): void
    {
        $clientId = $recovery->client_id;
        $propertyId = $recovery->property_id;
        $ref = $recovery->ref_number ?? 'N/A';
        $amount = (float) $recovery->amount;

        $codes = DB::table('sys_pm_clients as c')
            ->crossJoin('sys_pm_properties as p')
            ->where('c.id', $clientId)
            ->where('p.id', $propertyId)
            ->select('c.reference_number as landlord_code', 'p.reference_number as property_code')
            ->first();

        if (!$codes) {
            throw new \Exception("Could not resolve client/property codes for recovery {$ref}");
        }

        $recoveryDate = $recovery->recovery_date
            ? \Carbon\Carbon::parse($recovery->recovery_date)
            : now();

        $particulars = 'Recovery ' . $ref . ($recovery->narration ? ' — ' . $recovery->narration : '');

        // Field list built strictly from the live cb_landlord_account schema
        // (verified directly via information_schema, cross-checked against real
        // existing rows for entry_type_code 0024) — this table has no "balance",
        // "server_time", or "user_id" column despite those appearing in
        // ClientAdvanceController::postToLandlordAccount()'s insert, which this was
        // otherwise modeled on; audit_user is a name string, not a user id;
        // transaction_number is NOT NULL with no default. recovery_server_time/
        // recovery_year/recovery_month are deliberately left unset here — those are
        // written later, automatically, by
        // ReturnsDeductionsTrait::insertAdvancePaymentLines() when it marks this
        // entry as recovered during the landlord's next return generation.
        DB::table('cb_landlord_account')->insert([
            'year' => $recoveryDate->year,
            'month' => $recoveryDate->month,
            'landlord_code' => $codes->landlord_code,
            'landlord_name' => $recovery->client->names ?? null,
            'property_code' => $codes->property_code,
            'transaction_number' => $ref,
            'entry_type_code' => self::ENTRY_TYPE_CODE,
            'entry_description' => 'RECOVERY',
            'entry_particulars' => $particulars,
            'entry_date' => now(),
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'total_amount' => $amount,
            'gl_account' => 'EXPENSE', // matches existing 0024 (Rent recovery) entries
            'vendor_id' => $codes->landlord_code,
            'is_recovered' => 0,
            'client_id' => $clientId,
            'property_id' => $propertyId,
            'audit_user' => Auth::user()->name ?? (string) Auth::id(),
        ]);

        // No recovery-specific GL account pair exists in this chart of accounts yet
        // (checked — nothing named/coded for "recovery"). Reusing the advance
        // controller's 1150/2297 pair would misclassify these in the GL, so GL
        // posting is deliberately skipped here until the correct accounts are
        // identified; the cb_landlord_account entry above is what actually drives
        // the landlord's return calculation and is unaffected by this.
        Log::info('postRecoveryToLandlordAccount: GL posting skipped — no recovery-specific chart of accounts configured', [
            'recovery_id' => $recovery->id,
        ]);
    }
}

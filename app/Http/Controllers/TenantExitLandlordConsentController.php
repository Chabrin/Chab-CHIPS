<?php

namespace App\Http\Controllers;

use App\Models\TenantExitLandlordConsent;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Public, signed-link pages a landlord uses to approve or reject having a
 * departing tenant's refund deducted from their payout — no login required,
 * mirroring ClientAdvanceController's advance-approval flow.
 */
class TenantExitLandlordConsentController extends Controller
{
    /**
     * Show the consent review page for a signed link, or an "already
     * processed" notice if the landlord (or a double-click) already responded.
     */
    public function show(Request $request, $encryptedId) {
        try {
            $id = Crypt::decrypt($encryptedId);
            $consent = TenantExitLandlordConsent::with(['exit.property', 'exit.unit', 'landlord', 'tenant'])->findOrFail($id);

            if (!$consent->isPending()) {
                return view('property.exit-consents.processed', compact('consent'));
            }

            return view('property.exit-consents.review', [
                'consent' => $consent,
                'confirmUrl' => $request->fullUrl(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant exit consent page: ' . $e->getMessage());
            abort(404);
        }
    }

    /**
     * Record the landlord's decision. The status check-and-update is a single
     * atomic conditional query, so a double submission (double-click, replay,
     * two tabs) can only ever succeed once.
     */
    public function decide(Request $request, $encryptedId) {
        try {
            $validated = $request->validate([
                'decision' => 'required|in:approve,reject',
                'rejection_reason' => 'required_if:decision,reject|nullable|string|max:1000',
            ]);

            $id = Crypt::decrypt($encryptedId);

            $updates = $validated['decision'] === 'approve'
                ? ['current_status_id' => 5, 'responded_at' => now()]
                : ['current_status_id' => 41, 'responded_at' => now(), 'rejection_reason' => $validated['rejection_reason']];

            $updated = TenantExitLandlordConsent::where('id', $id)->where('current_status_id', 1)->update($updates);

            $consent = TenantExitLandlordConsent::with(['exit.property', 'exit.unit', 'landlord', 'tenant'])->findOrFail($id);

            if ($updated > 0) {
                Log::info('Tenant exit landlord consent recorded', [
                    'consent_id' => $consent->id,
                    'decision' => $validated['decision'],
                ]);
            }

            return view('property.exit-consents.processed', compact('consent'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error recording tenant exit landlord consent: ' . $e->getMessage());
            abort(404);
        }
    }
}

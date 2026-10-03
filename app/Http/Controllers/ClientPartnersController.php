<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ClientPartner;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

/**
 * AJAX CRUD for a Partnership/Trust client's individual partners' KYC — same
 * modal+table pattern as ClientBankDetailsController, used from the client
 * edit page. See ClientOtherContactsController's docblock for why create-page
 * staging is handled separately (ClientDraftRepeatersController).
 */
class ClientPartnersController extends Controller
{
    public function getClientPartners($encryptedClientId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedClientId);

            $partners = ClientPartner::where('client_id', $clientId)
                ->orderBy('id')
                ->get();

            $data = $partners->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'encrypted_id' => Crypt::encrypt($partner->id),
                    'name' => $partner->name,
                    'national_id' => $partner->national_id,
                    'kra_pin' => $partner->kra_pin,
                    'phone' => $partner->phone,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching client partners: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch partners'], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required',
                'name' => 'required|string|max:255',
                'national_id' => 'nullable|string|max:50',
                'kra_pin' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
            ]);

            $clientId = Crypt::decrypt($validated['client_id']);

            $partner = ClientPartner::create([
                'client_id' => $clientId,
                'name' => $validated['name'],
                'national_id' => $validated['national_id'] ?? null,
                'kra_pin' => $validated['kra_pin'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Partner added successfully',
                'data' => ['id' => $partner->id, 'encrypted_id' => Crypt::encrypt($partner->id)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing partner: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add partner: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $partner = ClientPartner::find($id);

            if (!$partner) {
                return response()->json(['success' => false, 'message' => 'Partner not found'], 404);
            }

            $partner->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Partner deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting partner: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete partner'], 500);
        }
    }
}

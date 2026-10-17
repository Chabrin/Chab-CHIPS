<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ClientNextOfKin;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use App\Services\NextOfKinContactGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

/**
 * AJAX CRUD for a landlord's "Next of Kin" — same modal+table pattern as
 * ClientBankDetailsController, used from the client edit page. See
 * ClientOtherContactsController's docblock for why create-page staging is
 * handled separately (ClientDraftRepeatersController).
 */
class ClientNextOfKinController extends Controller
{
    protected NextOfKinContactGuard $kinGuard;

    /**
     * Inject the next-of-kin contact guard.
     */
    public function __construct(NextOfKinContactGuard $kinGuard) {
        $this->kinGuard = $kinGuard;
    }

    public function getClientNextOfKin($encryptedClientId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedClientId);

            $kin = ClientNextOfKin::where('parent_id', $clientId)
                ->orderBy('id')
                ->get();

            $data = $kin->map(function ($k) {
                return [
                    'id' => $k->id,
                    'encrypted_id' => Crypt::encrypt($k->id),
                    'first_name' => $k->kin_first_name,
                    'second_name' => $k->kin_second_name,
                    'last_name' => $k->kin_last_name,
                    'mobile' => $k->mobile_number,
                    'email' => $k->email_address,
                    'relation' => $k->relationship,
                    'national_id' => $k->national_id,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching client next of kin: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch next of kin'], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required',
                'first_name' => 'required|string|max:255',
                'second_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'relation' => 'nullable|string|max:100',
                'national_id' => 'nullable|string|max:50',
            ]);

            $clientId = Crypt::decrypt($validated['client_id']);

            $kinErrors = $this->kinGuard->kinErrorsForClient((int) $clientId, $validated['email'] ?? null, $validated['mobile'] ?? null);
            if ($kinErrors) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => collect($kinErrors)->flatten()->first(), 'errors' => $kinErrors], 422);
            }

            $fullName = trim(
                $validated['first_name'] . ' ' .
                    ($validated['second_name'] ?? '') . ' ' .
                    $validated['last_name']
            );

            $kin = ClientNextOfKin::create([
                'parent_id' => $clientId,
                'kin_first_name' => $validated['first_name'],
                'kin_second_name' => $validated['second_name'] ?? null,
                'kin_last_name' => $validated['last_name'],
                'names' => $fullName,
                'mobile_number' => $validated['mobile'] ?? null,
                'email_address' => $validated['email'] ?? null,
                'relationship' => $validated['relation'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Next of kin added successfully',
                'data' => ['id' => $kin->id, 'encrypted_id' => Crypt::encrypt($kin->id)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing next of kin: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add next of kin: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $kin = ClientNextOfKin::find($id);

            if (!$kin) {
                return response()->json(['success' => false, 'message' => 'Next of kin not found'], 404);
            }

            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'second_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'relation' => 'nullable|string|max:100',
                'national_id' => 'nullable|string|max:50',
            ]);

            $fullName = trim(
                $validated['first_name'] . ' ' .
                    ($validated['second_name'] ?? '') . ' ' .
                    $validated['last_name']
            );

            $kinErrors = $this->kinGuard->kinErrorsForClient((int) $kin->parent_id, $validated['email'] ?? null, $validated['mobile'] ?? null);
            if ($kinErrors) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => collect($kinErrors)->flatten()->first(), 'errors' => $kinErrors], 422);
            }

            $kin->update([
                'kin_first_name' => $validated['first_name'],
                'kin_second_name' => $validated['second_name'] ?? null,
                'kin_last_name' => $validated['last_name'],
                'names' => $fullName,
                'mobile_number' => $validated['mobile'] ?? null,
                'email_address' => $validated['email'] ?? null,
                'relationship' => $validated['relation'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Next of kin updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating next of kin: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update next of kin: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $kin = ClientNextOfKin::find($id);

            if (!$kin) {
                return response()->json(['success' => false, 'message' => 'Next of kin not found'], 404);
            }

            $kin->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Next of kin deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting next of kin: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete next of kin'], 500);
        }
    }
}

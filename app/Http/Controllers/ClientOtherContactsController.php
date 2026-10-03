<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ClientOtherContact;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

/**
 * AJAX CRUD for a landlord's "Other Contacts" — same modal+table pattern as
 * ClientBankDetailsController, used from the client edit page (the client
 * already has a real id there, so this writes straight to the real table;
 * the create page stages equivalent rows via ClientDraftRepeatersController
 * instead, since the client doesn't exist yet at that point).
 */
class ClientOtherContactsController extends Controller
{
    public function getClientOtherContacts($encryptedClientId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedClientId);

            $contacts = ClientOtherContact::where('parent_id', $clientId)
                ->orderBy('id')
                ->get();

            $data = $contacts->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'encrypted_id' => Crypt::encrypt($contact->id),
                    'names' => $contact->contact_names,
                    'email' => $contact->other_email,
                    'mobile' => $contact->other_mobile_number,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching client other contacts: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch contacts'], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required',
                'names' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'mobile' => 'nullable|string|max:20',
            ]);

            $clientId = Crypt::decrypt($validated['client_id']);

            $contact = ClientOtherContact::create([
                'parent_id' => $clientId,
                'contact_names' => $validated['names'],
                'other_email' => $validated['email'] ?? null,
                'other_mobile_number' => $validated['mobile'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contact added successfully',
                'data' => ['id' => $contact->id, 'encrypted_id' => Crypt::encrypt($contact->id)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing other contact: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add contact: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $contact = ClientOtherContact::find($id);

            if (!$contact) {
                return response()->json(['success' => false, 'message' => 'Contact not found'], 404);
            }

            $contact->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Contact deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting other contact: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete contact'], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\ClientDraftRepeater;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * Staging CRUD for the client CREATE page's Banks/Other Contacts/Next of
 * Kin/Partners modals. The client doesn't have an id yet at that point, so
 * rows here are tagged by a one-time draft_token (generated when the create
 * page loads) instead of a real client_id. ClientsController::store()
 * commits every staged row for that token into the real tables — then
 * deletes them — once the client itself is successfully saved.
 *
 * The edit page already has a real client id, so it talks directly to the
 * real-table controllers instead (ClientBankDetailsController,
 * ClientOtherContactsController, ClientNextOfKinController,
 * ClientPartnersController) — this controller is create-only.
 */
class ClientDraftRepeatersController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'draft_token' => 'required|string|max:64',
                'kind' => ['required', Rule::in(ClientDraftRepeater::KINDS)],
            ]);

            $rows = ClientDraftRepeater::where('draft_token', $validated['draft_token'])
                ->where('kind', $validated['kind'])
                ->orderBy('id')
                ->get();

            $data = $rows->map(function ($row) use ($validated) {
                $shaped = ['id' => $row->id, 'encrypted_id' => Crypt::encrypt($row->id)];

                switch ($validated['kind']) {
                    case 'bank':
                        $shaped += [
                            'bank_id' => $row->bank_id,
                            'bank_name' => Bank::find($row->bank_id)->bank_name ?? '-',
                            'branch_id' => $row->branch_id,
                            'branch_name' => $row->branch_id ? (BankBranch::find($row->branch_id)->branch_name ?? '-') : '-',
                            'account_name' => $row->account_name,
                            'account_number' => $row->account_number,
                        ];
                        break;
                    case 'contact':
                        $shaped += [
                            'names' => $row->contact_name,
                            'email' => $row->contact_email,
                            'mobile' => $row->contact_mobile,
                        ];
                        break;
                    case 'nextofkin':
                        $shaped += [
                            'first_name' => $row->kin_first_name,
                            'second_name' => $row->kin_second_name,
                            'last_name' => $row->kin_last_name,
                            'mobile' => $row->kin_mobile,
                            'email' => $row->kin_email,
                            'relation' => $row->kin_relationship,
                            'national_id' => $row->kin_national_id,
                        ];
                        break;
                    case 'partner':
                        $shaped += [
                            'name' => $row->partner_name,
                            'national_id' => $row->partner_national_id,
                            'kra_pin' => $row->partner_kra_pin,
                            'phone' => $row->partner_phone,
                        ];
                        break;
                }

                return $shaped;
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching draft repeaters: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch rows'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $kind = $request->input('kind');

            $rules = [
                'draft_token' => 'required|string|max:64',
                'kind' => ['required', Rule::in(ClientDraftRepeater::KINDS)],
            ];

            switch ($kind) {
                case 'bank':
                    $rules += [
                        'bank_id' => 'required|integer|exists:sys_set_banks,id',
                        'branch_id' => 'nullable|integer|exists:sys_set_banks_branches,id',
                        'account_name' => 'required|string|max:255',
                        'account_number' => 'required|string|max:50',
                    ];
                    break;
                case 'contact':
                    $rules += [
                        'names' => 'required|string|max:255',
                        'email' => 'nullable|email|max:255',
                        'mobile' => 'nullable|string|max:20',
                    ];
                    break;
                case 'nextofkin':
                    $rules += [
                        'first_name' => 'required|string|max:255',
                        'second_name' => 'nullable|string|max:255',
                        'last_name' => 'required|string|max:255',
                        'mobile' => 'nullable|string|max:20',
                        'email' => 'nullable|email|max:255',
                        'relation' => 'nullable|string|max:100',
                        'national_id' => 'nullable|string|max:50',
                    ];
                    break;
                case 'partner':
                    $rules += [
                        'name' => 'required|string|max:255',
                        'national_id' => 'nullable|string|max:50',
                        'kra_pin' => 'nullable|string|max:50',
                        'phone' => 'nullable|string|max:20',
                    ];
                    break;
            }

            $validated = $request->validate($rules);

            $row = ClientDraftRepeater::create($this->mapToColumns($validated));

            return response()->json([
                'success' => true,
                'message' => 'Added successfully',
                'data' => ['id' => $row->id, 'encrypted_id' => Crypt::encrypt($row->id)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing draft repeater: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $row = ClientDraftRepeater::find($id);

            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Row not found'], 404);
            }

            $row->delete();

            return response()->json(['success' => true, 'message' => 'Deleted successfully']);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting draft repeater: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete'], 500);
        }
    }

    /**
     * Translate the (kind-specific, form-facing) validated payload into this
     * table's generic, kind-prefixed columns.
     */
    private function mapToColumns(array $validated): array
    {
        $row = ['draft_token' => $validated['draft_token'], 'kind' => $validated['kind']];

        switch ($validated['kind']) {
            case 'bank':
                $row['bank_id'] = $validated['bank_id'];
                $row['branch_id'] = $validated['branch_id'] ?? null;
                $row['account_name'] = $validated['account_name'];
                $row['account_number'] = $validated['account_number'];
                break;
            case 'contact':
                $row['contact_name'] = $validated['names'];
                $row['contact_email'] = $validated['email'] ?? null;
                $row['contact_mobile'] = $validated['mobile'] ?? null;
                break;
            case 'nextofkin':
                $row['kin_first_name'] = $validated['first_name'];
                $row['kin_second_name'] = $validated['second_name'] ?? null;
                $row['kin_last_name'] = $validated['last_name'];
                $row['kin_mobile'] = $validated['mobile'] ?? null;
                $row['kin_email'] = $validated['email'] ?? null;
                $row['kin_relationship'] = $validated['relation'] ?? null;
                $row['kin_national_id'] = $validated['national_id'] ?? null;
                break;
            case 'partner':
                $row['partner_name'] = $validated['name'];
                $row['partner_national_id'] = $validated['national_id'] ?? null;
                $row['partner_kra_pin'] = $validated['kra_pin'] ?? null;
                $row['partner_phone'] = $validated['phone'] ?? null;
                break;
        }

        return $row;
    }
}

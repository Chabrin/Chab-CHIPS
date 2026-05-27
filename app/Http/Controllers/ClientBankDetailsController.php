<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Bank;
use App\Models\Client;
use App\Models\BankBranch;
use Illuminate\Http\Request;
use App\Models\ClientBankDetail;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class ClientBankDetailsController extends Controller
{
    /**
     * Get all bank details for a client (AJAX)
     * Reusable method - can be called from other controllers
     */
    public function getClientBankDetails($encryptedClientId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedClientId);

            $bankDetails = ClientBankDetail::with(['bank', 'branch', 'addedBy'])
                ->forClient($clientId)
                ->orderByDesc('is_default')
                ->orderBy('account_name')
                ->get();

            $data = $bankDetails->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'encrypted_id' => Crypt::encrypt($detail->id),
                    'bank_id' => $detail->bank_id,
                    'bank_name' => $detail->bank->bank_name ?? '-',
                    'branch_id' => $detail->branch_id,
                    'branch_name' => $detail->branch->branch_name ?? '-',
                    'account_number' => $detail->account_number,
                    'account_name' => $detail->account_name,
                    'is_default' => $detail->is_default,
                    'added_by_name' => $detail->addedBy->name ?? '-',
                    'added_on' => $detail->added_on ? $detail->added_on->format('d-m-Y H:i') : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching client bank details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank details',
            ], 500);
        }
    }

    /**
     * Get a single bank detail (AJAX)
     * Reusable method - can be called from other controllers
     */
    public function getBankDetail($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);

            $detail = ClientBankDetail::with(['bank', 'branch'])->find($id);

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank detail not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $detail->id,
                    'encrypted_id' => Crypt::encrypt($detail->id),
                    'client_id' => $detail->client_id,
                    'bank_id' => $detail->bank_id,
                    'branch_id' => $detail->branch_id,
                    'account_number' => $detail->account_number,
                    'account_name' => $detail->account_name,
                    'is_default' => $detail->is_default,
                ],
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching bank detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank detail',
            ], 500);
        }
    }

    /**
     * Store a new bank detail (AJAX)
     * Reusable method - can be called from other controllers
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required',
                'bank_id' => 'required|exists:sys_set_banks,id',
                'branch_id' => 'nullable|exists:sys_set_banks_branches,id',
                'account_number' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('clients_bank_details')->where(function ($query) use ($request) {
                        return $query->where('bank_id', $request->bank_id);
                    }),
                ],
                'account_name' => 'required|string|max:255',
                'is_default' => 'nullable|in:0,1',
            ], [
                'account_number.unique' => 'This account number already exists for the selected bank.',
            ]);

            $clientId = Crypt::decrypt($validated['client_id']);
            $isDefault = $validated['is_default'] ?? 0;

            if ($isDefault == 1) {
                ClientBankDetail::where('client_id', $clientId)
                    ->where('is_default', 1)
                    ->update(['is_default' => 0]);
            }

            $firstForClient = ClientBankDetail::where('client_id', $clientId)->count() === 0;
            if ($firstForClient) {
                $isDefault = 1;
            }

            $bankDetail = ClientBankDetail::create([
                'client_id' => $clientId,
                'bank_id' => $validated['bank_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'account_number' => $validated['account_number'],
                'account_name' => $validated['account_name'],
                'is_default' => $isDefault,
                'added_by' => Auth::id(),
                'added_on' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank detail added successfully',
                'data' => [
                    'id' => $bankDetail->id,
                    'encrypted_id' => Crypt::encrypt($bankDetail->id),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing bank detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add bank detail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a bank detail (AJAX)
     * Reusable method - can be called from other controllers
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $detail = ClientBankDetail::find($id);

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank detail not found',
                ], 404);
            }

            $validated = $request->validate([
                'bank_id' => 'required|exists:sys_set_banks,id',
                'branch_id' => 'nullable|exists:sys_set_banks_branches,id',
                'account_number' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('clients_bank_details')->where(function ($query) use ($request) {
                        return $query->where('bank_id', $request->bank_id);
                    })->ignore($id),
                ],
                'account_name' => 'required|string|max:255',
                'is_default' => 'nullable|in:0,1',
            ], [
                'account_number.unique' => 'This account number already exists for the selected bank.',
            ]);

            $isDefault = $validated['is_default'] ?? 0;

            if ($isDefault == 1 && $detail->is_default != 1) {
                ClientBankDetail::where('client_id', $detail->client_id)
                    ->where('is_default', 1)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => 0]);
            }

            $detail->update([
                'bank_id' => $validated['bank_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'account_number' => $validated['account_number'],
                'account_name' => $validated['account_name'],
                'is_default' => $isDefault,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank detail updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating bank detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank detail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a bank detail (AJAX)
     * Reusable method - can be called from other controllers
     */
    public function destroy($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $detail = ClientBankDetail::find($id);

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank detail not found',
                ], 404);
            }

            $wasDefault = $detail->is_default == 1;
            $clientId = $detail->client_id;

            $detail->delete();

            if ($wasDefault) {
                $newDefault = ClientBankDetail::where('client_id', $clientId)
                    ->orderBy('id')
                    ->first();
                if ($newDefault) {
                    $newDefault->update(['is_default' => 1]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank detail deleted successfully',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting bank detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank detail',
            ], 500);
        }
    }

    /**
     * Set a bank detail as default (AJAX)
     * Reusable method - can be called from other controllers
     */
    public function setDefault($encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $detail = ClientBankDetail::find($id);

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank detail not found',
                ], 404);
            }

            ClientBankDetail::where('client_id', $detail->client_id)
                ->where('is_default', 1)
                ->update(['is_default' => 0]);

            $detail->update(['is_default' => 1]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default bank detail updated successfully',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error setting default bank detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default bank detail',
            ], 500);
        }
    }

    /**
     * Get banks list (AJAX)
     * Reusable method - can be called from other controllers
     */
    public static function getBanks()
    {
        try {
            $banks = Bank::select(['id', 'bank_name'])
                ->orderBy('bank_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $banks,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching banks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch banks',
            ], 500);
        }
    }

    /**
     * Get branches for a bank (AJAX)
     * Reusable method - can be called from other controllers
     */
    public static function getBranches($bankId)
    {
        try {
            $branches = BankBranch::select(['id', 'branch_name'])
                ->where('bank_id', $bankId)
                ->orderBy('branch_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching branches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
            ], 500);
        }
    }

    /**
     * Get default bank detail for a client
     * Reusable helper method - can be called programmatically
     */
    public static function getDefaultBankDetail($clientId)
    {
        return ClientBankDetail::with(['bank', 'branch'])
            ->where('client_id', $clientId)
            ->where('is_default', 1)
            ->first();
    }

    /**
     * Get all bank details for a client
     * Reusable helper method - can be called programmatically
     */
    public static function getAllBankDetails($clientId)
    {
        return ClientBankDetail::with(['bank', 'branch'])
            ->where('client_id', $clientId)
            ->orderByDesc('is_default')
            ->orderBy('account_name')
            ->get();
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exports\TransactionTypesExport;
use Maatwebsite\Excel\Facades\Excel;

class TransactionTypesController extends Controller
{
    public function getData()
    {
        try {
            $can_edit_transaction_types = Auth::user()->isAbleTo('update_transaction_types');
            $can_delete_transaction_types = Auth::user()->isAbleTo('delete_transaction_types');
            $transaction_types = TransactionType::with(['debitAccount', 'creditAccount', 'createdBy', 'category'])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transaction_types->map(function ($transaction_type) use ($can_edit_transaction_types, $can_delete_transaction_types) {
                    return [
                        'id' => $transaction_type->id,
                        'name' => $transaction_type->type_name,
                        'item_code' => $transaction_type->item_code,
                        'category_id' => $transaction_type->category_id,
                        'category_name' => $transaction_type->category?->category_name,
                        'debit_account_code' => $transaction_type->debitAccount?->account_code,
                        'debit_account_name' => $transaction_type->debitAccount?->account_name,
                        'credit_account_code' => $transaction_type->creditAccount?->account_code,
                        'credit_account_name' => $transaction_type->creditAccount?->account_name,
                        'current_status_id' => $transaction_type->current_status_id,
                        'can_edit' => $can_edit_transaction_types,
                        'can_delete' => $can_delete_transaction_types,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching finance transaction types data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load transaction types'], 500);
        }
    }

    /**
     * Export transaction types to Excel.
     */
    public function exportToExcel()
    {
        try {
            $transaction_types = TransactionType::with(['debitAccount', 'creditAccount', 'category'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($transaction_type) {
                    return [
                        'item_code' => $transaction_type->item_code,
                        'name' => $transaction_type->type_name,
                        'category_name' => $transaction_type->category?->category_name,
                        'debit_account_code' => $transaction_type->debitAccount?->account_code,
                        'debit_account_name' => $transaction_type->debitAccount?->account_name,
                        'credit_account_code' => $transaction_type->creditAccount?->account_code,
                        'credit_account_name' => $transaction_type->creditAccount?->account_name,
                        'current_status_id' => $transaction_type->current_status_id,
                    ];
                })
                ->toArray();

            $filename = 'Transaction_Types_' . date('Y-m-d_His') . '.xlsx';
            return Excel::download(new TransactionTypesExport($transaction_types), $filename);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting transaction types: ' . $e->getMessage());
            return back()->with('error', 'Failed to export transaction types');
        }
    }

    /**
     * Show the form for creating a new transaction type.
     */
    public function create()
    {
        return view('finance.transaction-types.create');
    }

    /**
     * Store a newly created transaction type in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'type_name' => 'required|string|max:255',
                'item_code' => 'required|string|max:50',
                'category_id' => 'nullable|integer',
                'default_amount' => 'nullable|numeric|min:0',
                'debit_account_id' => 'nullable|integer',
                'credit_account_id' => 'nullable|integer',
            ]);
            $validated['created_by_id'] = Auth::id();

            TransactionType::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Transaction type created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Show the form for editing the specified transaction type.
     */
    public function edit($encrypted_id)
    {
        try {
            $id = decrypt($encrypted_id);
            $transactionType = TransactionType::findOrFail($id);
            return view('finance.transaction-types.edit', compact('transactionType'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading finance transaction type edit page: ' . $e->getMessage());
            return redirect()->route('ledger.accounts.index')->with('error', 'Failed to load transaction type edit page');
        }
    }

    /**
     * Update the specified transaction type in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $transactionType = TransactionType::findOrFail($id);

            $validated = $request->validate([
                'type_name' => 'required|string|max:255',
                'item_code' => 'required|string|max:50',
                'category_id' => 'nullable|integer',
                'is_commissionable' => 'in:0,1',
                'is_client_expense' => 'in:0,1',
                'is_recovery' => 'in:0,1',
                'default_amount' => 'nullable|numeric|min:0',
                'debit_account_id' => 'nullable|integer',
                'credit_account_id' => 'nullable|integer',
                'current_status_id' => 'nullable|integer',
                'workflow_status_id' => 'nullable|integer',
            ]);

            $transactionType->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Transaction type updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified transaction type from storage.
     */
    public function destroy($id)
    {
        try {
            $transactionType = TransactionType::findOrFail($id);
            $transactionType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transaction type deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getOne($id)
    {
        try {
            $t = TransactionType::with(['debitAccount', 'creditAccount', 'category'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => [
                    'id'                  => $t->id,
                    'type_name'           => $t->type_name,
                    'item_code'           => $t->item_code,
                    'default_amount'      => $t->default_amount,
                    'category_id'         => $t->category_id,
                    'category_name'       => $t->category?->category_name,
                    'debit_account_id'    => $t->debit_account_id,
                    'debit_account_code'  => $t->debitAccount?->account_code,
                    'debit_account_name'  => $t->debitAccount?->account_name,
                    'credit_account_id'   => $t->credit_account_id,
                    'credit_account_code' => $t->creditAccount?->account_code,
                    'credit_account_name' => $t->creditAccount?->account_name,
                    'is_commissionable'   => (int) $t->is_commissionable,
                    'is_recovery'         => (int) $t->is_recovery,
                    'is_client_expense'   => (int) $t->is_client_expense,
                    'current_status_id'   => $t->current_status_id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionTypesController extends Controller
{
    /**
     * Display a listing of transaction types.
     */
    public function index()
    {
        return view('finance.transaction-types.index');
    }

    public function getData()
    {
        try {
            $can_edit_transaction_types = Auth::user()->isAbleTo('update_transaction_types');
            $transaction_types = TransactionType::with(['debitAccount', 'creditAccount', 'createdBy', 'category'])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transaction_types->map(function ($transaction_type) use ($can_edit_transaction_types) {
                    return [
                        'id' => $transaction_type->id,
                        'name' => $transaction_type->type_name,
                        'item_code' => $transaction_type->item_code,
                        'category_id' => $transaction_type->category_id,
                        'debit_account' => $transaction_type->debitAccount ? $transaction_type->debitAccount->account_name : null,
                        'credit_account' => $transaction_type->creditAccount ? $transaction_type->creditAccount->account_name : null,
                        'amount' => $transaction_type->default_amount,
                        'current_status_id' => $transaction_type->current_status_id,
                        'created_by' => optional($transaction_type->createdBy)->name,
                        'created_at' => optional($transaction_type->date_time)->format('Y-m-d H:i:s'),
                        'category' => $transaction_type->category ? $transaction_type->category->category_name : null,
                        'can_edit' => $can_edit_transaction_types,
                        'encrypted_id' => encrypt($transaction_type->id),
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
            $validated['created_by_id'] = 1;

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
            return redirect()->route('finance.transaction-types.index')->with('error', 'Failed to load transaction type edit page');
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

   
}

<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use App\Models\ChartAccount;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceTaxController extends Controller
{
    public function index()
    {
        try {
            return view('finance.taxes.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading finance tax setup page: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load tax setup page');
        }
    }

    public function create()
    {
        try {
            return view('finance.taxes.create');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading finance tax creation page: ' . $e->getMessage());
            return redirect()->route('finance.taxes.index')->with('error', 'Failed to load tax creation page');
        }
    }

    public function getData()
    {
        try {
            $taxes = Tax::with(['debitAccount', 'creditAccount', 'createdBy', 'defaultType', 'computation', 'category'])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $taxes->map(function ($tax) {
                    return [
                        'id' => $tax->id,
                        'name' => $tax->name,
                        'default_type_id' => $tax->default_type_id,
                        'computation_id' => $tax->computation_id,
                        'category_id' => $tax->category_id,
                        'debit_account' => $tax->debitAccount ? $tax->debitAccount->account_name : null,
                        'credit_account' => $tax->creditAccount ? $tax->creditAccount->account_name : null,
                        'amount' => $tax->amount,
                        'current_status_id' => $tax->current_status_id,
                        'created_by' => optional($tax->createdBy)->name,
                        'created_at' => optional($tax->date_time)->format('Y-m-d H:i:s'),
                        'default_type' => $tax->defaultType ? $tax->defaultType->type_name : null,
                        'computation' => $tax->computation ? $tax->computation->computation_name : null,
                        'category' => $tax->category ? $tax->category->category_name : null,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching finance taxes data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load taxes'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'default_type_id' => 'nullable|integer',
                'computation_id' => 'nullable|integer',
                'category_id' => 'nullable|integer',
                'debit_account_id' => 'nullable|integer|exists:sys_gl_chart_accounts_master,id',
                'credit_account_id' => 'nullable|integer|exists:sys_gl_chart_accounts_master,id',
                'amount' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            try {
                $tax = new Tax();
                $tax->date_time = now();
                $tax->name = $validated['name'];
                $tax->default_type_id = $validated['default_type_id'] ?? null;
                $tax->computation_id = $validated['computation_id'] ?? null;
                $tax->category_id = $validated['category_id'] ?? null;
                $tax->debit_account_id = $validated['debit_account_id'] ?? null;
                $tax->credit_account_id = $validated['credit_account_id'] ?? null;
                $tax->amount = $validated['amount'];
                $tax->created_by_id = Auth::id();
                $tax->current_status_id = 5;
                $tax->save();

                DB::commit();

                return response()->json(['success' => true, 'message' => 'Tax setup saved successfully']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing finance tax: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save tax setup'], 500);
        }
    }

    public function getAccounts()
    {
        try {
            $accounts = ChartAccount::active()
                ->select(['id', 'account_name', 'account_code', 'normally'])
                ->orderBy('account_name')
                ->get();

            return response()->json(['success' => true, 'accounts' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'label' => $account->account_code . ' - ' . $account->account_name,
                    'type' => $account->normally, // Assuming 'normally' indicates Debit or Credit
                ];
            })]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading chart accounts for finance taxes: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load accounts'], 500);
        }
    }
}

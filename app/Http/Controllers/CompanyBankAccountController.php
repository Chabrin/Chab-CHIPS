<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\ChequeBook;
use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CompanyBankAccountController extends Controller
{
    public function index()
    {
        try {
            return view('settings.company-bank-accounts.index');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to load company bank accounts page.');
        }
    }

    public function getData()
    {
        try {
            $accounts = CompanyBankAccount::with(['bank', 'branch', 'createdBy'])
                ->orderBy('account_name')
                ->get()
                ->map(function ($a) {
                    return [
                        'id'             => $a->id,
                        'account_name'   => $a->account_name,
                        'account_number' => $a->account_number,
                        'account_type'   => $a->account_type,
                        'bank_name'      => $a->bank ? $a->bank->bank_name : '—',
                        'branch_name'    => $a->branch ? $a->branch->branch_name : '—',
                        'status_label'   => $a->status_label,
                        'status_badge'   => $a->status_badge,
                        'notes'          => $a->notes,
                    ];
                });

            return response()->json(['data' => $accounts]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load accounts.'], 500);
        }
    }

    public function getBanks()
    {
        try {
            $banks = Bank::select('id', 'bank_name')
                ->where('current_status_id', 1)
                ->orderBy('bank_name')
                ->get();

            return response()->json(['banks' => $banks]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load banks.'], 500);
        }
    }

    public function getBranches(Request $request)
    {
        try {
            $request->validate(['bank_id' => 'required|integer']);

            $branches = BankBranch::select('id', 'branch_name', 'branch_code')
                ->where('bank_id', $request->bank_id)
                ->orderBy('branch_name')
                ->get();

            return response()->json(['branches' => $branches]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load branches.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_name'   => 'required|string|max:150',
                'account_number' => 'required|string|max:100',
                'bank_id'        => 'required|integer|exists:sys_set_banks,id',
                'branch_id'      => 'nullable|integer|exists:sys_set_banks_branches,id',
                'account_type'   => 'nullable|string|max:50',
                'notes'          => 'nullable|string|max:2000',
            ]);

            $exists = CompanyBankAccount::where('account_name', $validated['account_name'])
                ->where('bank_id', $validated['bank_id'])
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'error' => 'An account with this name already exists for the selected bank.'], 422);
            }

            $account = new CompanyBankAccount();
            $account->account_name   = $validated['account_name'];
            $account->account_number = $validated['account_number'];
            $account->bank_id        = $validated['bank_id'];
            $account->branch_id      = $validated['branch_id'] ?? null;
            $account->account_type   = $validated['account_type'] ?? null;
            $account->notes          = $validated['notes'] ?? null;
            $account->current_status_id = 1;
            $account->created_by     = Auth::id();
            $account->date_time      = now();
            $account->save();

            return response()->json(['success' => true, 'id' => $account->id]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to save account.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'account_name'   => 'required|string|max:150',
                'account_number' => 'required|string|max:100',
                'bank_id'        => 'required|integer|exists:sys_set_banks,id',
                'branch_id'      => 'nullable|integer|exists:sys_set_banks_branches,id',
                'account_type'   => 'nullable|string|max:50',
                'current_status_id' => 'nullable|integer|in:1,2',
                'notes'          => 'nullable|string|max:2000',
            ]);

            $account = CompanyBankAccount::findOrFail($id);

            $exists = CompanyBankAccount::where('account_name', $validated['account_name'])
                ->where('bank_id', $validated['bank_id'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'error' => 'An account with this name already exists for the selected bank.'], 422);
            }

            $account->account_name      = $validated['account_name'];
            $account->account_number    = $validated['account_number'];
            $account->bank_id           = $validated['bank_id'];
            $account->branch_id         = $validated['branch_id'] ?? null;
            $account->account_type      = $validated['account_type'] ?? null;
            $account->current_status_id = $validated['current_status_id'] ?? $account->current_status_id;
            $account->notes             = $validated['notes'] ?? null;
            $account->save();

            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to update account.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $account = CompanyBankAccount::findOrFail($id);

            if (ChequeBook::where('bank_account_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'This account is linked to cheque books and cannot be deleted.',
                ], 422);
            }

            $account->delete();

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to delete account.'], 500);
        }
    }
}

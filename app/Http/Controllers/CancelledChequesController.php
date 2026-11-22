<?php

namespace App\Http\Controllers;

use App\Models\CompanyBankAccount;
use App\Services\ErrorDispatcher;
use App\Services\VoucherCancellationService;
use App\Traits\ChecksMenuAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CancelledChequesController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/cheque/cancelled';

    protected VoucherCancellationService $cancellation;

    /**
     * Inject the service that lists cancelled cheques.
     */
    public function __construct(VoucherCancellationService $cancellation) {
        $this->cancellation = $cancellation;
    }

    /**
     * Show the Cancelled Cheques page.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $bankAccounts = CompanyBankAccount::orderBy('account_name')->get(['id', 'account_name', 'account_number']);

        return view('finance.cheques.cancelled', compact('bankAccounts'));
    }

    /**
     * The cancelled cheques as JSON for the page's table, narrowed by the date, bank account and search filters.
     */
    public function data(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $filters = $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date'],
                'bank_account_id' => ['nullable', 'integer'],
                'search' => ['nullable', 'string', 'max:100'],
            ]);

            return response()->json(['success' => true, 'data' => $this->cancellation->cancelledCheques($filters)]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading cancelled cheques: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load cancelled cheques'], 500);
        }
    }
}

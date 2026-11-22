<?php

namespace App\Http\Controllers;

use App\Models\CompanyBankAccount;
use App\Services\ChequeListingService;
use App\Services\ErrorDispatcher;
use App\Traits\ChecksMenuAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AllChequesController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/payments/cheques';

    protected ChequeListingService $cheques;

    /**
     * Inject the service that lists the cheques.
     */
    public function __construct(ChequeListingService $cheques) {
        $this->cheques = $cheques;
    }

    /**
     * Show the All Cheques page.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $bankAccounts = CompanyBankAccount::orderBy('account_name')->get(['id', 'account_name', 'account_number']);
        $statuses = ChequeListingService::STATUSES;
        $perPage = ChequeListingService::PER_PAGE;
        $canPrint = (bool) Auth::user()->isAbleTo('read_cheques_printing');

        return view('finance.cheques.all', compact('bankAccounts', 'statuses', 'perPage', 'canPrint'));
    }

    /**
     * One page of cheques as JSON for the page's table, narrowed by the page filters.
     */
    public function data(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $filters = $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date'],
                'status' => ['nullable', 'in:' . implode(',', ChequeListingService::STATUSES)],
                'printed' => ['nullable', 'in:yes,no'],
                'bank_account_id' => ['nullable', 'integer'],
                'search' => ['nullable', 'string', 'max:100'],
                'amount_min' => ['nullable', 'numeric', 'min:0'],
                'amount_max' => ['nullable', 'numeric', 'min:0'],
                'sort' => ['nullable', 'in:' . implode(',', array_keys(ChequeListingService::SORTS))],
                'dir' => ['nullable', 'in:asc,desc'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer'],
            ]);

            $result = $this->cheques->page($filters, $filters['sort'] ?? 'date', $filters['dir'] ?? 'desc', (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 50));

            return response()->json(['success' => true] + $result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading the cheques list: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load the cheques'], 500);
        }
    }
}

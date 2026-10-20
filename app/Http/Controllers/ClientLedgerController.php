<?php

namespace App\Http\Controllers;

use App\Exports\ClientLedgerExport;
use App\Services\ClientLedgerService;
use App\Services\ErrorDispatcher;
use App\Traits\ChecksMenuAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ClientLedgerController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/client/ledger';

    protected ClientLedgerService $ledger;

    /**
     * Inject the client ledger service.
     */
    public function __construct(ClientLedgerService $ledger) {
        $this->ledger = $ledger;
    }

    /**
     * Show the Client Ledgers page: every landlord with the state of their account.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        return view('property.landlord.client-ledger');
    }

    /**
     * Return the landlord accounts as JSON for the page's table, narrowed by the page filters.
     */
    public function data(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        $filters = $this->filters($request);

        try {
            return response()->json(['success' => true, 'data' => $this->ledger->summaries($filters)]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client ledgers: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load client ledgers'], 500);
        }
    }

    /**
     * Download the landlord accounts as Excel, narrowed by the same filters as the page.
     */
    public function export(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $filters = $this->filters($request);

        try {
            ini_set('memory_limit', '512M');

            $rows = $this->ledger->summaries($filters)->map(fn($r) => [
                $r['reference_number'],
                $r['names'],
                $r['mobile_number'],
                $r['has_account'] ? 'Open' : 'Not opened',
                $r['opened'] ? date('d/m/Y', strtotime($r['opened'])) : '',
                $r['last_activity'] ? date('d/m/Y', strtotime($r['last_activity'])) : '',
                $r['entries'],
                $r['has_account'] ? $r['balance'] : '',
                $r['unpaid_fee'] > 0 ? $r['unpaid_fee'] : '',
            ])->all();

            $export = $this->sheet(
                'Client Ledgers',
                ['Account no.', 'Client', 'Phone', 'Status', 'Opened', 'Last activity', 'Entries', 'Balance', 'Unpaid registration fee'],
                $rows,
                ['A' => 14, 'B' => 32, 'C' => 16, 'D' => 12, 'E' => 12, 'F' => 14, 'G' => 10, 'H' => 16, 'I' => 22]
            );

            return Excel::download($export, 'client-ledgers-' . now()->format('Ymd-His') . '.xlsx');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting client ledgers: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export client ledgers');
        }
    }

    /**
     * Return every ledger entry for one landlord as JSON for the row modal, with the landlord's
     * details and the closing balance. Expects ?client=<encrypted id>.
     */
    public function entries(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $account = $this->accountFromRequest($request);
            if (!$account) {
                return response()->json(['success' => false, 'message' => 'Landlord not found'], 404);
            }

            $entries = $this->ledger->entries($account['id']);

            return response()->json([
                'success' => true,
                'account' => $account,
                'balance' => $entries ? end($entries)['balance'] : 0,
                'data' => $entries,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client ledger entries: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load ledger entries'], 500);
        }
    }

    /**
     * Download every ledger entry for one landlord as Excel. Expects ?client=<encrypted id>.
     */
    public function exportEntries(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        try {
            ini_set('memory_limit', '512M');

            $account = $this->accountFromRequest($request);
            if (!$account) {
                return redirect()->back()->with('error', 'Landlord not found');
            }

            $entries = $this->ledger->entries($account['id']);
            $rows = array_map(fn($e) => [
                $e['date_formatted'],
                $e['reference'],
                $e['counted'] ? $e['narration'] : $e['narration'] . ' (not counted)',
                $e['debit'] > 0 ? $e['debit'] : '',
                $e['credit'] > 0 ? $e['credit'] : '',
                $e['balance'],
            ], $entries);

            $export = $this->sheet(
                'Client Ledger - ' . $account['reference_number'] . ' ' . $account['names'],
                ['Date', 'Reference', 'Narration', 'Dr', 'Cr', 'Balance'],
                $rows,
                ['A' => 14, 'B' => 18, 'C' => 60, 'D' => 16, 'E' => 16, 'F' => 16]
            );

            return Excel::download($export, 'client-ledger-' . $account['reference_number'] . '-' . now()->format('Ymd-His') . '.xlsx');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting client ledger entries: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export ledger entries');
        }
    }

    /**
     * Build the Excel sheet: title, balance note, generated stamp, then the headings on row 4.
     * GenericReportExport::build() puts a blank spacer before the headings and styles row 5,
     * but the blank row is dropped when the file is written, which leaves the real headings
     * unstyled and dresses the first data row as the heading. So the rows are laid out here.
     */
    protected function sheet(string $title, array $headings, array $rows, array $widths): ClientLedgerExport {
        $all = [
            [$title],
            ['Balance: positive = owed to the landlord, negative = owed by the landlord'],
            ['Generated: ' . now()->format('d/m/Y H:i:s')],
            $headings,
        ];

        return new ClientLedgerExport(array_merge($all, $rows), 4, count($headings), $widths);
    }

    /**
     * Find the landlord named by the request's ?client=<encrypted id>, or null when it is
     * missing, cannot be decrypted, or is not a landlord.
     */
    protected function accountFromRequest(Request $request): ?array {
        $value = $request->input('client');
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $clientId = (int) Crypt::decrypt($value);
        } catch (\Exception $e) {
            return null;
        }

        return $this->ledger->account($clientId);
    }

    /**
     * Read and validate the list filters from the request.
     */
    protected function filters(Request $request): array {
        return $request->validate([
            'account' => ['nullable', Rule::in(ClientLedgerService::FILTER_ACCOUNT)],
            'fee' => ['nullable', Rule::in(ClientLedgerService::FILTER_FEE)],
            'balance' => ['nullable', Rule::in(ClientLedgerService::FILTER_BALANCE)],
        ]);
    }
}

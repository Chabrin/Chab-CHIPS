<?php

namespace App\Http\Controllers;

use App\Exports\GlReportExport;
use App\Services\ErrorDispatcher;
use App\Services\GlBalanceService;
use App\Traits\ChecksMenuAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class GlBalancesController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/ledger/balances';

    protected GlBalanceService $balances;

    /**
     * Inject the balance service.
     */
    public function __construct(GlBalanceService $balances) {
        $this->balances = $balances;
    }

    /**
     * Show the Account Balances page: every account in the chart with its balance, as a trial balance.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        return view('ledger.balances', ['asAt' => now()->toDateString()]);
    }

    /**
     * The balances for ?as_at= (Y-m-d, default today) as an HTML fragment the page puts in place.
     * ?hide_zero=0 includes accounts that have no postings.
     */
    public function sheet(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('fragment');
        }

        $asAt = $this->asAt($request);

        try {
            return response()->view('ledger.partials.balances-sheet', ['data' => $this->balances->balances($asAt, $request->input('hide_zero', '1') !== '0')]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL balances: ' . $e->getMessage());
            return response('The balances could not be loaded.', 500);
        }
    }

    /**
     * Download the balances for ?as_at= as Excel, including the trial balance totals.
     */
    public function export(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $asAt = $this->asAt($request);

        try {
            $data = $this->balances->balances($asAt, $request->input('hide_zero', '1') !== '0');

            $rows = [];
            foreach ($data['groups'] as $group) {
                foreach ($group['accounts'] as $a) {
                    $rows[] = [$group['name'], $a['code'], $a['name'], $a['debit'], $a['credit'], $a['balance'], $a['side']];
                }
            }
            $rows[] = ['', '', 'Totals' . ($data['totals']['balanced'] ? ' (balanced)' : ' (NOT balanced, difference ' . number_format($data['totals']['difference'], 2) . ')'), $data['totals']['debit'], $data['totals']['credit'], '', ''];

            $export = GlReportExport::make(
                'Account Balances - as at ' . Carbon::parse($asAt)->format('d/m/Y'),
                'From the journals posted to the GL only. Balance is shown as an amount with its side (Dr or Cr).',
                ['Class', 'Code', 'Account', 'Debit', 'Credit', 'Balance', 'Side'],
                $rows,
                ['A' => 18, 'B' => 10, 'C' => 42, 'D' => 18, 'E' => 18, 'F' => 18, 'G' => 8],
                true
            );

            return Excel::download($export, 'account-balances-' . $asAt . '.xlsx');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting GL balances: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export the balances');
        }
    }

    /**
     * Show the account ledger page for ?account=<encrypted id>, opening on ?from=&to= or the last three months.
     */
    public function accountLedger(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $accountId = $this->accountId($request);
        [$from, $to] = $this->period($request);
        $account = $accountId ? $this->balances->account($accountId) : null;

        if (!$account) {
            return redirect()->route('gl.balances.index')->with('error', 'Account not found');
        }

        return view('ledger.account-ledger', [
            'account' => $account,
            'encrypted' => Crypt::encrypt($accountId),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * One account's ledger for ?account=&from=&to= as an HTML fragment the page puts in place.
     */
    public function accountLedgerSheet(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('fragment');
        }

        $accountId = $this->accountId($request);
        [$from, $to] = $this->period($request);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > GlBalanceService::MAX_RANGE_DAYS) {
            return response('Choose a period of 12 months or less.', 422);
        }

        try {
            $ledger = $accountId ? $this->balances->accountLedger($accountId, $from, $to) : null;
            if (!$ledger) {
                return response('Account not found.', 404);
            }

            return response()->view('ledger.partials.account-ledger-sheet', ['ledger' => $ledger]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL account ledger: ' . $e->getMessage());
            return response('The ledger could not be loaded.', 500);
        }
    }

    /**
     * Download one account's ledger for ?account=&from=&to= as Excel.
     */
    public function accountLedgerExport(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $accountId = $this->accountId($request);
        [$from, $to] = $this->period($request);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > GlBalanceService::MAX_RANGE_DAYS) {
            return redirect()->back()->with('error', 'Choose a period of 12 months or less.');
        }

        try {
            $ledger = $accountId ? $this->balances->accountLedger($accountId, $from, $to, 50000) : null;
            if (!$ledger) {
                return redirect()->back()->with('error', 'Account not found');
            }

            $rows = [['', '', 'Balance brought forward', '', '', $ledger['opening']]];
            foreach ($ledger['entries'] as $e) {
                $rows[] = [$e['date'], $e['reference'], $e['narration'], $e['debit'] > 0 ? $e['debit'] : '', $e['credit'] > 0 ? $e['credit'] : '', $e['balance']];
            }
            $rows[] = ['', '', 'Period totals and closing balance (' . $ledger['closing_side'] . ')', $ledger['debits'], $ledger['credits'], $ledger['closing']];

            $account = $ledger['account'];
            $export = GlReportExport::make(
                'Account Ledger - ' . $account['code'] . ' ' . $account['name'],
                Carbon::parse($from)->format('d/m/Y') . ' to ' . Carbon::parse($to)->format('d/m/Y') . '. Balance is in the account\'s normal direction (' . $account['normally'] . '); a negative balance means the opposite side.',
                ['Date', 'Reference', 'Narration', 'Debit', 'Credit', 'Balance'],
                $rows,
                ['A' => 14, 'B' => 18, 'C' => 60, 'D' => 18, 'E' => 18, 'F' => 18],
                true
            );

            return Excel::download($export, 'account-ledger-' . $account['code'] . '-' . $from . '-to-' . $to . '.xlsx');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting GL account ledger: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export the ledger');
        }
    }

    /**
     * The as-at date from ?as_at= (Y-m-d), or today when it is missing or invalid.
     */
    protected function asAt(Request $request): string {
        $value = $request->input('as_at');
        $date = is_string($value) ? \DateTime::createFromFormat('Y-m-d', $value) : false;

        return ($date && $date->format('Y-m-d') === $value) ? $value : now()->toDateString();
    }

    /**
     * The period from ?from=&to= (Y-m-d). A missing or invalid date falls back to the default
     * three months, and dates given the wrong way round are swapped.
     */
    protected function period(Request $request): array {
        $default = $this->balances->defaultPeriod();
        $valid = function ($value, $fallback) {
            $date = is_string($value) ? \DateTime::createFromFormat('Y-m-d', $value) : false;

            return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
        };

        $to = $valid($request->input('to'), $default['to']);
        $from = $valid($request->input('from'), $request->filled('to') ? Carbon::parse($to)->subMonthsNoOverflow(3)->addDay()->toDateString() : $default['from']);

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /**
     * The chart account id from ?account= (encrypted, or a plain id), or null when it is missing.
     */
    protected function accountId(Request $request): ?int {
        $value = $request->input('account');
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return (int) Crypt::decrypt($value);
        } catch (\Exception $e) {
            return (int) $value ?: null;
        }
    }
}

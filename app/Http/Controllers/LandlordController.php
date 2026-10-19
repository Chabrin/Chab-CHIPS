<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Client;
use App\Models\Property;
use App\Models\LandlordAccount;
use App\Exports\LandlordStatementExport;
use App\Services\ClientLedgerService;
use App\Services\ErrorDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class LandlordController extends Controller
{
    protected ClientLedgerService $ledger;

    public function __construct(ClientLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Display the landlord account statement page. When opened with ?landlord=<encrypted id>
     * (from Client Ledgers or the client page) that landlord is preselected and the statement
     * loads straight away for ?from=&to= (Y-m-d), or for the last three months when no dates are given.
     */
    public function accountStatement(Request $request)
    {
        try {
            $preselected = null;
            [$from, $to] = $this->statementPeriod($request);

            $account = $this->statementAccount($request);
            if ($account) {
                $preselected = [
                    'encrypted_id' => Crypt::encrypt($account['id']),
                    'names' => $account['names'] ?: 'N/A',
                    'mobile_number' => $account['mobile_number'],
                    'email_address' => $account['email_address'],
                    'national_id' => $account['national_id'],
                    'reference_number' => $account['reference_number'],
                ];
            }

            return view('property.landlord.account-statement', compact('preselected', 'from', 'to'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading landlord account statement page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load landlord account statement page');
        }
    }

    /**
     * Process landlord transactions into statement lines with opening/closing balances.
     * The balance rule (B/F and C/F markers) lives in ClientLedgerService so the Client Ledgers
     * page and this statement always agree.
     */
    private function processLandlordTransactions($transactions)
    {
        return $this->ledger->statementEntries($transactions);
    }

    /**
     * Search properties for landlord statements (AJAX)
     */
    public function getProperties(Request $request)
    {
        try {
            $query = Property::select('id', 'property_name', 'reference_number');

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('property_name', 'ILIKE', "%{$search}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$search}%");
                });
            }

            $properties = $query->orderBy('property_name')->limit(50)->get();

            return response()->json(['success' => true, 'properties' => $properties]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching properties: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to search properties'], 500);
        }
    }

    /**
     * Get landlords for a property (AJAX)
     */
    public function getLandlordsByProperty(Request $request)
    {
        try {
            $propertyId = $request->input('property_id');

            $clientQuery = Client::select(
                'sys_pm_clients.id',
                'sys_pm_clients.names',
                'sys_pm_clients.mobile_number',
                'sys_pm_clients.email_address',
                'sys_pm_clients.national_id',
                'sys_pm_clients.reference_number'
            )
                ->join('sys_pm_properties', 'sys_pm_properties.client_id', '=', 'sys_pm_clients.id')
                ->where('sys_pm_properties.id', $propertyId)
                ->orderBy('sys_pm_clients.names');

            // Log::info('[getLandlordsByProperty] raw SQL', [
            //     'sql'         => $clientQuery->toSql(),
            //     'bindings'    => $clientQuery->getBindings(),
            //     'property_id' => $propertyId,
            // ]);

            $landlords = $clientQuery->get()->map(function ($l) {
                return [
                    'id'               => $l->id,
                    'encrypted_id'     => encrypt($l->id),
                    'names'            => $l->names ?? 'N/A',
                    'mobile_number'    => $l->mobile_number,
                    'email_address'    => $l->email_address,
                    'national_id'      => $l->national_id,
                    'reference_number' => $l->reference_number,
                ];
            });

            return response()->json(['success' => true, 'landlords' => $landlords]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching landlords by property: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load landlords'], 500);
        }
    }

    /**
     * Search landlords directly by name/reference/phone/email (AJAX)
     */
    public function searchLandlords(Request $request)
    {
        try {
            $search = $request->input('search', '');
            if (strlen($search) < 2) {
                return response()->json(['success' => false, 'message' => 'Enter at least 2 characters'], 400);
            }

            // Advance creation is a finance operation, not property-management —
            // bypass the field-officer/zone visibility scope so any staff can search all landlords.
            $landlords = Client::withoutGlobalScope('user_clients_properties')->select(
                'sys_pm_clients.id',
                'sys_pm_clients.names',
                'sys_pm_clients.mobile_number',
                'sys_pm_clients.email_address',
                'sys_pm_clients.national_id',
                'sys_pm_clients.reference_number'
            )
                ->where('sys_pm_clients.reference_number', 'LIKE', 'LAN%')
                ->where(function ($q) use ($search) {
                    $q->where('sys_pm_clients.names', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.reference_number', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.mobile_number', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.email_address', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.national_id', 'ILIKE', "%{$search}%");
                })
                ->orderBy('sys_pm_clients.names')
                ->limit(50)
                ->get()
                ->map(function ($l) {
                    return [
                        'id'               => $l->id,
                        'encrypted_id'     => encrypt($l->id),
                        'names'            => $l->names ?? 'N/A',
                        'mobile_number'    => $l->mobile_number,
                        'email_address'    => $l->email_address,
                        'national_id'      => $l->national_id,
                        'reference_number' => $l->reference_number,
                    ];
                });

            return response()->json(['success' => true, 'landlords' => $landlords]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching landlords: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to search landlords'], 500);
        }
    }

    /**
     * Search landlord account statement (AJAX)
     */
    public function searchStatement(Request $request)
    {
        try {
            if (!$request->filled('landlord_id')) {
                return response()->json(['success' => false, 'message' => 'Landlord is required'], 400);
            }

            try {
                $landlordId = (int) Crypt::decrypt($request->landlord_id);
            } catch (\Exception $e) {
                $landlordId = (int) $request->landlord_id;
            }

            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $landlord = Client::find($landlordId);
            $landlordCode = $landlord->reference_number ?? null;


            $query = LandlordAccount::where(function ($q) use ($landlordId, $landlordCode) {
                $q->where('client_id', $landlordId);
                if ($landlordCode) {
                    $q->orWhere('landlord_code', $landlordCode);
                }
            });

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $query->whereDate('entry_date', '>=', $dateFrom)
                    ->whereDate('entry_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $query->whereDate('entry_date', '<=', $dateTo);
            }


            // Order chronologically: entry_date, year, month, id
            $transactions = $query->orderBy('entry_date', 'asc')
                ->orderByRaw('EXTRACT(YEAR FROM entry_date) ASC')
                ->orderByRaw('EXTRACT(MONTH FROM entry_date) ASC')
                ->orderBy('id', 'asc')
                ->get();

            Log::info('[searchStatement] Step 3 — results', [
                'count'       => $transactions->count(),
                'raw_count_sql' => "SELECT COUNT(*) FROM cb_landlord_account WHERE (client_id = {$landlordId}" . ($landlordCode ? " OR landlord_code = '{$landlordCode}'" : '') . ");",
                'full_check_sql' => "SELECT * FROM cb_landlord_account WHERE landlord_code = '" . ($landlordCode ?? '') . "' ORDER BY entry_date ASC, EXTRACT(YEAR FROM entry_date) ASC, EXTRACT(MONTH FROM entry_date) ASC, id ASC;",
            ]);

            // Process transactions with balance tracking
            $statementEntries = $this->processLandlordTransactions($transactions);

            return response()->json([
                'success' => true,
                'statement' => $statementEntries
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating landlord statement: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate statement'], 500);
        }
    }

    /**
     * The statement for ?landlord=&from=&to= as an HTML fragment the statement page puts straight
     * into the page. Uses the same statement data as the PDF and Excel exports.
     */
    public function statementSheet(Request $request)
    {
        try {
            $account = $this->statementAccount($request);
            if (!$account) {
                return response('Landlord not found.', 404);
            }

            [$from, $to] = $this->statementPeriod($request);

            return response()->view('property.landlord.statement-sheet', [
                'account' => $account,
                'statement' => $this->ledger->statement($account['id'], $from, $to),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading landlord statement sheet: ' . $e->getMessage());
            return response('The statement could not be loaded.', 500);
        }
    }

    /**
     * Export the statement for ?landlord=&from=&to= to Excel: opening balance, the period's entries
     * and a closing line carrying the period totals.
     */
    public function exportStatementExcel(Request $request)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $account = $this->statementAccount($request);
            if (!$account) {
                return redirect()->back()->with('error', 'Landlord not found');
            }

            [$from, $to] = $this->statementPeriod($request);
            $st = $this->ledger->statement($account['id'], $from, $to);

            $entries = array_merge(
                [['date' => $from, 'reference' => '', 'narration' => 'Opening balance', 'debit' => 0, 'credit' => 0, 'balance' => $st['opening']]],
                $st['rows'],
                [['date' => $to, 'reference' => '', 'narration' => 'Closing balance (period totals in Dr / Cr)', 'debit' => $st['debits'], 'credit' => $st['credits'], 'balance' => $st['closing']]]
            );

            $landlordInfo = [
                'landlord_name' => $account['names'] . ' (' . $account['reference_number'] . ') - ' . date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to)),
                'property_name' => $st['properties'] ? implode(', ', $st['properties']) : 'N/A',
            ];

            $fileName = 'Landlord_Statement_' . $account['reference_number'] . '_' . date('Y-m-d') . '.xlsx';

            return Excel::download(new LandlordStatementExport($entries, $landlordInfo), $fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting landlord statement Excel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export landlord statement');
        }
    }

    /**
     * The statement for ?landlord=&from=&to= as a PDF. With ?inline=1 it is shown in the browser
     * (the statement page embeds it); otherwise it downloads.
     */
    public function exportStatementPdf(Request $request)
    {
        $inline = $request->boolean('inline');

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $account = $this->statementAccount($request);
            if (!$account) {
                return $this->statementPdfError('Landlord not found.', $inline, 404);
            }

            [$from, $to] = $this->statementPeriod($request);

            $pdf = Pdf::loadView('property.landlord.statement-pdf', [
                'account' => $account,
                'statement' => $this->ledger->statement($account['id'], $from, $to),
                'generatedAt' => now()->format('d M Y, H:i'),
            ])->setPaper('A4', 'portrait')->setOption(['enable_font_subsetting' => true]);

            // Render first so the page numbers can be written on every page
            $pdf->render();
            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 23, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 6.5, [0.54, 0.58, 0.64]);

            $fileName = 'Account_Statement_' . $account['reference_number'] . '_' . $from . '_to_' . $to . '.pdf';

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"',
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting landlord statement PDF: ' . $e->getMessage());
            return $this->statementPdfError('The statement could not be generated.', $inline, 500);
        }
    }

    /**
     * The landlord named by the request's landlord parameter (encrypted id, or a plain id),
     * or null when it is missing or is not a landlord. Like the landlord search on the
     * statement page, this is a finance view and is not limited to the user's zones.
     */
    private function statementAccount(Request $request): ?array
    {
        if (!$request->filled('landlord')) {
            return null;
        }

        try {
            $landlordId = (int) Crypt::decrypt($request->input('landlord'));
        } catch (\Exception $e) {
            $landlordId = (int) $request->input('landlord');
        }

        return $this->ledger->account($landlordId);
    }

    /**
     * The period asked for with ?from=&to= (Y-m-d). A missing or invalid date falls back to the
     * default three months, and dates given the wrong way round are swapped.
     */
    private function statementPeriod(Request $request): array
    {
        $default = $this->ledger->defaultPeriod();
        $valid = function ($value, $fallback) {
            $date = is_string($value) ? \DateTime::createFromFormat('Y-m-d', $value) : false;

            return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
        };

        $from = $valid($request->input('from'), $default['from']);
        $to = $valid($request->input('to'), $default['to']);

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /**
     * A failure for the statement PDF. Shown inside the page's viewer, so it is a small page of
     * its own rather than a redirect back into the app.
     */
    private function statementPdfError(string $message, bool $inline, int $status)
    {
        if (!$inline) {
            return redirect()->back()->with('error', $message);
        }

        return response('<!DOCTYPE html><html><body style="font-family:sans-serif;color:#6b7486;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">' . e($message) . '</body></html>', $status);
    }
}

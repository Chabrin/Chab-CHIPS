<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Unit;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\DebtorInvoice;
use App\Models\InvoiceableItem;
use App\Models\LeaseAgreement;
use App\Models\InvoiceLineItem;
use App\Models\InvoiceWorkflow;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TenantInvoicesExport;
use App\Exports\ClientInvoicesExport;
use App\Exports\TenantStatementExport;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicingController extends Controller
{
    /**
     * Display tenant invoices page
     */
    public function tenantInvoices()
    {
        try {
            return view('property.invoices.tenant-invoices');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant invoices page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenant invoices page');
        }
    }

    /**
     * Display tenant invoice items listing page
     */
    public function tenantInvoiceItems()
    {
        try {
            return view('property.invoices.tenant-invoice-items');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant invoice items page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenant invoice items page');
        }
    }

    /**
     * Search invoice items (AJAX)
     */
    public function searchInvoiceItems(Request $request)
    {
        try {
            $query = DebtorInvoice::with(['tenant', 'property', 'unit', 'lease'])
                ->tenantInvoices();
            //Log Query
            Log::info('Initial invoice items query: ' . $query->toSql());

            Log::info('Searching invoice items with filters: ' . json_encode($request->all()));
            //Log count of total items before filters
            $totalCount = $query->count();
            Log::info('Total invoice items before filters: ' . $totalCount);

            // Apply filters
            if ($request->filled('status_id')) {
                $query->where('current_status_id', $request->status_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date_time', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date_time', '<=', $request->date_to);
            }

            // Limit to 100 if no filters applied
            if (!$request->filled('status_id') && !$request->filled('date_from') && !$request->filled('date_to')) {
                $query->limit(100);
            }

            $invoices = $query->orderBy('date_time', 'desc')->get();

            return response()->json([
                'success' => true,
                'invoices' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'date_time' => $invoice->date_time ? $invoice->date_time->format('d-m-Y') : '-',
                        'reference_number' => $invoice->reference_number ?? '-',
                        'tenant_name' => $invoice->tenant->names ?? '-',
                        'tenant_encrypted_id' => $invoice->tenant ? $invoice->tenant->encrypted_id : null,
                        'property_name' => $invoice->property->property_name ?? '-',
                        'property_encrypted_id' => $invoice->property ? $invoice->property->encrypted_id : null,
                        'unit_name' => $invoice->unit->unit_name ?? '-',
                        'total_amount' => number_format($invoice->total_amount, 2),
                        'current_balance' => number_format($invoice->current_balance, 2),
                        'status_name' => $invoice->status_name,
                        'current_status_id' => $invoice->current_status_id,
                        'narration' => $invoice->narration ?? '-'
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching invoice items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search invoice items'
            ], 500);
        }
    }

    /**
     * Display create invoice item page
     */
    public function createInvoiceItem()
    {
        try {
            return view('property.invoices.create-invoice-item');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading create invoice item page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load create invoice item page');
        }
    }

    /**
     * Store new invoice item (AJAX)
     */
    public function storeInvoiceItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|integer',
                'property_id' => 'required|integer',
                'unit_id' => 'required|integer',
                'lease_id' => 'required|integer',
                'item_type_id' => 'required|integer',
                'date_due' => 'required|date',
                'returns_period' => 'required|date',
                'total_amount' => 'required|numeric|min:0',
                'return_report_type' => 'required|string',
                'is_arrear' => 'required|in:Yes,No'
            ]);

            // Generate reference number
            $referenceNumber = 'TI-' . date('Ymd') . '-' . str_pad(DebtorInvoice::tenantInvoices()->count() + 1, 5, '0', STR_PAD_LEFT);

            $invoice = DebtorInvoice::create([
                'date_time' => now(),
                'date_due' => $validated['date_due'],
                'debtor_type_id' => 2, // Tenant
                'tenant_id' => $validated['tenant_id'],
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_id'],
                'lease_id' => $validated['lease_id'],
                'reference_number' => $referenceNumber,
                'narration' => $validated['return_report_type'],
                'total_amount' => $validated['total_amount'],
                'current_balance' => $validated['total_amount'],
                'current_status_id' => 1, // Pending
                'created_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing invoice item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice'
            ], 500);
        }
    }

    /**
     * Display create tenant invoice page
     */
    public function createTenantInvoice()
    {
        try {
            return view('property.invoices.create-tenant-invoice');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading create tenant invoice page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load create tenant invoice page');
        }
    }

    /**
     * Display client invoices page
     */
    public function clientInvoices()
    {
        try {
            return view('property.invoices.client-invoices');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client invoices page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load client invoices page');
        }
    }

    /**
     * Display create client invoice page
     */
    public function createClientInvoice()
    {
        try {
            return view('property.invoices.create-client-invoice');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading create client invoice page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load create client invoice page');
        }
    }

    /**
     * Get invoiceable items (AJAX)
     */
    public function getInvoiceableItems()
    {
        try {
            $items = InvoiceableItem::orderBy('item_name', 'asc')->get();
            return response()->json([
                'success' => true,
                'items' => $items
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading invoiceable items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load invoiceable items'
            ], 500);
        }
    }

    /**
     * Search tenant invoices (AJAX)
     */
    public function searchTenantInvoices(Request $request)
    {
        try {
            // Validate tenant_id is provided
            if (!$request->filled('tenant_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant ID is required'
                ], 400);
            }

            // Decrypt tenant_id if it's encrypted
            try {
                $tenantId = \Illuminate\Support\Facades\Crypt::decrypt($request->tenant_id);
            } catch (\Exception $e) {
                $tenantId = $request->tenant_id;
            }

            // Handle date filtering
            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // Get invoices (Debit entries)
            $invoicesQuery = DebtorInvoice::where('tenant_id', $tenantId);

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $invoicesQuery->whereDate('date_due', '>=', $dateFrom)
                    ->whereDate('date_due', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $invoicesQuery->whereDate('date_due', '<=', $dateTo);
            }

            $invoices = $invoicesQuery->orderBy('id', 'asc')->get();

            // Get payments (Credit entries)
            $paymentsQuery = DB::table('sys_pm_tenants_payments')
                ->where('tenant_id', $tenantId);

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $paymentsQuery->whereDate('payment_date', '>=', $dateFrom)
                    ->whereDate('payment_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $paymentsQuery->whereDate('payment_date', '<=', $dateTo);
            }

            $payments = $paymentsQuery->orderBy('payment_date', 'asc')->get();

            // Combine invoices and payments into statement entries
            $statementEntries = [];

            // Add invoices as debit entries
            foreach ($invoices as $invoice) {
                $statementEntries[] = [
                    'date' => $invoice->date_due,
                    'reference' => $invoice->reference_number ?? '-',
                    'narration' => $invoice->narration ?? 'Invoice',
                    'debit' => $invoice->total_amount,
                    'credit' => 0,
                    'type' => 'invoice',
                    'sort_date' => $invoice->date_due,
                    'sort_id' => $invoice->id,
                ];
            }

            // Add payments as credit entries
            foreach ($payments as $payment) {
                $statementEntries[] = [
                    'date' => $payment->payment_date,
                    'reference' => $payment->reference ?? '-',
                    'narration' => $payment->narration ?? 'Payment',
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'type' => 'payment',
                    'sort_date' => $payment->payment_date,
                    'sort_id' => $payment->id,
                ];
            }

            // Sort by date first (most recent first), then by ID for invoices
            usort($statementEntries, function ($a, $b) {
                $dateCompare = strtotime($b['sort_date']) - strtotime($a['sort_date']); // Reversed for descending
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                // If same date, sort invoices by ID (newest first)
                if ($a['type'] === 'invoice' && $b['type'] === 'invoice') {
                    return $b['sort_id'] - $a['sort_id']; // Reversed for descending
                }
                return 0;
            });

            // Calculate running balance
            $balance = 0;
            foreach ($statementEntries as &$entry) {
                $balance += $entry['credit'] - $entry['debit'];
                $entry['balance'] = $balance;
                $entry['date_formatted'] = date('d-m-Y', strtotime($entry['date']));
                $entry['debit_formatted'] = $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-';
                $entry['credit_formatted'] = $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-';
                $entry['balance_formatted'] = number_format($entry['balance'], 2);
            }

            return response()->json([
                'success' => true,
                'statement' => $statementEntries
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating tenant statement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate statement'
            ], 500);
        }
    }

    /**
     * Export tenant statement to Excel
     */
    public function exportTenantStatementExcel(Request $request)
    {
        try {
            // Decrypt tenant_id if encrypted
            try {
                $tenantId = \Illuminate\Support\Facades\Crypt::decrypt($request->tenant_id);
            } catch (\Exception $e) {
                $tenantId = $request->tenant_id;
            }

            // Get tenant details
            $tenant = Client::find($tenantId);

            if (!$tenant) {
                return redirect()->back()->with('error', 'Tenant not found');
            }

            // Get property and unit names from the first invoice with proper joins
            $firstInvoice = DB::table('sys_pm_debtors_invoices as inv')
                ->leftJoin('sys_pm_properties as prop', 'inv.property_id', '=', 'prop.id')
                ->leftJoin('sys_pm_units as unit', 'inv.unit_id', '=', 'unit.id')
                ->where('inv.tenant_id', $tenantId)
                ->select('prop.property_name', 'unit.unit_code')
                ->first();

            $tenantInfo = [
                'tenant_name' => $tenant->names ?? 'N/A',
                'property_name' => $firstInvoice->property_name ?? 'N/A',
                'unit_name' => $firstInvoice->unit_code ?? 'N/A',
            ];

            // Get statement data (reuse logic from searchTenantInvoices)
            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // Get invoices (Debit entries)
            $invoicesQuery = DebtorInvoice::where('tenant_id', $tenantId);

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $invoicesQuery->whereDate('date_due', '>=', $dateFrom)
                    ->whereDate('date_due', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $invoicesQuery->whereDate('date_due', '<=', $dateTo);
            }

            $invoices = $invoicesQuery->orderBy('id', 'asc')->get();

            // Get payments (Credit entries)
            $paymentsQuery = DB::table('sys_pm_tenants_payments')
                ->where('tenant_id', $tenantId);

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $paymentsQuery->whereDate('payment_date', '>=', $dateFrom)
                    ->whereDate('payment_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $paymentsQuery->whereDate('payment_date', '<=', $dateTo);
            }

            $payments = $paymentsQuery->orderBy('payment_date', 'asc')->get();

            // Combine into statement entries
            $statementEntries = [];

            foreach ($invoices as $invoice) {
                $statementEntries[] = [
                    'date' => $invoice->date_due,
                    'reference' => $invoice->reference_number ?? '-',
                    'narration' => $invoice->narration ?? 'Invoice',
                    'debit' => $invoice->total_amount,
                    'credit' => 0,
                    'type' => 'invoice',
                    'sort_date' => $invoice->date_due,
                    'sort_id' => $invoice->id,
                ];
            }

            foreach ($payments as $payment) {
                $statementEntries[] = [
                    'date' => $payment->payment_date,
                    'reference' => $payment->reference ?? '-',
                    'narration' => $payment->narration ?? 'Payment',
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'type' => 'payment',
                    'sort_date' => $payment->payment_date,
                    'sort_id' => $payment->id,
                ];
            }

            // Sort by date first (most recent first), then by ID for invoices  
            usort($statementEntries, function ($a, $b) {
                $dateCompare = strtotime($b['sort_date']) - strtotime($a['sort_date']); // Reversed for descending
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                if ($a['type'] === 'invoice' && $b['type'] === 'invoice') {
                    return $b['sort_id'] - $a['sort_id']; // Reversed for descending
                }
                return 0;
            });

            // Calculate running balance
            $balance = 0;
            foreach ($statementEntries as &$entry) {
                $balance += $entry['credit'] - $entry['debit'];
                $entry['balance'] = $balance;
            }

            $fileName = 'Tenant_Statement_' . ($tenant->reference_number ?? $tenantId) . '_' . date('Y-m-d') . '.xlsx';

            return Excel::download(new TenantStatementExport($statementEntries, $tenantInfo), $fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting tenant statement: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export tenant statement');
        }
    }

    /**
     * Export tenant statement to PDF
     */
    public function exportTenantStatementPdf(Request $request)
    {
        try {
            // Decrypt tenant_id if encrypted
            try {
                $tenantId = \Illuminate\Support\Facades\Crypt::decrypt($request->tenant_id);
            } catch (\Exception $e) {
                $tenantId = $request->tenant_id;
            }

            // Get tenant details with relationships
            $tenant = Client::find($tenantId);

            if (!$tenant) {
                return redirect()->back()->with('error', 'Tenant not found');
            }

            // Get property and unit names from the first invoice with proper joins
            $firstInvoice = DB::table('sys_pm_debtors_invoices as inv')
                ->leftJoin('sys_pm_properties as prop', 'inv.property_id', '=', 'prop.id')
                ->leftJoin('sys_pm_units as unit', 'inv.unit_id', '=', 'unit.id')
                ->where('inv.tenant_id', $tenantId)
                ->select('prop.property_name', 'unit.unit_code')
                ->first();

            $tenantInfo = [
                'tenant_name' => $tenant->names ?? 'N/A',
                'tenant_email' => $tenant->email_address ?? 'N/A',
                'tenant_phone' => $tenant->mobile_number ?? 'N/A',
                'property_name' => $firstInvoice->property_name ?? 'N/A',
                'unit_name' => $firstInvoice->unit_code ?? 'N/A',
            ];

            // Get statement data
            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // Get invoices (Debit entries)
            $invoicesQuery = DebtorInvoice::where('tenant_id', $tenantId);

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $invoicesQuery->whereDate('date_due', '>=', $dateFrom)
                    ->whereDate('date_due', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $invoicesQuery->whereDate('date_due', '<=', $dateTo);
            }

            $invoices = $invoicesQuery->orderBy('id', 'asc')->get();

            // Get payments (Credit entries)
            $paymentsQuery = DB::table('sys_pm_tenants_payments')
                ->where('tenant_id', $tenantId);

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $paymentsQuery->whereDate('payment_date', '>=', $dateFrom)
                    ->whereDate('payment_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $paymentsQuery->whereDate('payment_date', '<=', $dateTo);
            }

            $payments = $paymentsQuery->orderBy('payment_date', 'asc')->get();

            // Combine into statement entries
            $statementEntries = [];

            foreach ($invoices as $invoice) {
                $statementEntries[] = [
                    'date' => $invoice->date_due,
                    'reference' => $invoice->reference_number ?? '-',
                    'narration' => $invoice->narration ?? 'Invoice',
                    'debit' => $invoice->total_amount,
                    'credit' => 0,
                    'type' => 'invoice',
                    'sort_date' => $invoice->date_due,
                    'sort_id' => $invoice->id,
                ];
            }

            foreach ($payments as $payment) {
                $statementEntries[] = [
                    'date' => $payment->payment_date,
                    'reference' => $payment->reference ?? '-',
                    'narration' => $payment->narration ?? 'Payment',
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'type' => 'payment',
                    'sort_date' => $payment->payment_date,
                    'sort_id' => $payment->id,
                ];
            }

            // Sort by date first (most recent first), then by ID for invoices
            usort($statementEntries, function ($a, $b) {
                $dateCompare = strtotime($b['sort_date']) - strtotime($a['sort_date']); // Reversed for descending
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                if ($a['type'] === 'invoice' && $b['type'] === 'invoice') {
                    return $b['sort_id'] - $a['sort_id']; // Reversed for descending
                }
                return 0;
            });

            // Calculate running balance and format
            $balance = 0;
            foreach ($statementEntries as &$entry) {
                $balance += $entry['credit'] - $entry['debit'];
                $entry['balance'] = $balance;
                $entry['date_formatted'] = $this->formatOrdinalDate($entry['date']);
                $entry['debit_formatted'] = $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-';
                $entry['credit_formatted'] = $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-';
                $entry['balance_formatted'] = number_format($entry['balance'], 2);
            }

            $data = [
                'tenantInfo' => $tenantInfo,
                'statement' => $statementEntries,
                'dateFrom' => $dateFrom ? date('d M Y', strtotime($dateFrom)) : 'Beginning',
                'dateTo' => $dateTo ? date('d M Y', strtotime($dateTo)) : 'Today',
            ];

            $pdf = Pdf::loadView('property.invoices.tenant-statement-pdf', $data);
            $pdf->setPaper('A4', 'portrait');

            $fileName = 'Tenant_Statement_' . ($tenant->reference_number ?? $tenantId) . '_' . date('Y-m-d') . '.pdf';

            return $pdf->download($fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting tenant statement PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export tenant statement PDF');
        }
    }

    /**
     * Format date with ordinal suffix (1st, 2nd, 3rd, etc.)
     */
    private function formatOrdinalDate($date)
    {
        $timestamp = strtotime($date);
        $day = date('j', $timestamp);
        $month = date('M', $timestamp);
        $year = date('Y', $timestamp);

        $suffix = 'th';
        if ($day == 1 || $day == 21 || $day == 31) {
            $suffix = 'st';
        } elseif ($day == 2 || $day == 22) {
            $suffix = 'nd';
        } elseif ($day == 3 || $day == 23) {
            $suffix = 'rd';
        }

        return $day . $suffix . ' ' . $month . ' ' . $year;
    }

    /**
     * Search client invoices (AJAX)
     */
    public function searchClientInvoices(Request $request)
    {
        try {
            $query = DebtorInvoice::with(['client', 'property'])
                ->clientInvoices();

            // Apply filters
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($request->filled('reference_number')) {
                $query->where('reference_number', 'LIKE', '%' . $request->reference_number . '%');
            }

            if ($request->filled('status_id')) {
                $query->where('current_status_id', $request->status_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date_time', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date_time', '<=', $request->date_to);
            }

            $invoices = $query->orderBy('date_time', 'desc')->get();

            return response()->json([
                'success' => true,
                'invoices' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'reference_number' => $invoice->reference_number ?? '-',
                        'client_name' => $invoice->client->names ?? '-',
                        'property_name' => $invoice->property->property_name ?? '-',
                        'date_time' => $invoice->date_time ? $invoice->date_time->format('d-m-Y') : '-',
                        'date_due' => $invoice->date_due ? $invoice->date_due->format('d-m-Y') : '-',
                        'total_amount' => number_format($invoice->total_amount, 2),
                        'current_balance' => number_format($invoice->current_balance, 2),
                        'comm_ammount' => number_format($invoice->comm_ammount ?? 0, 2),
                        'payable_amount' => number_format($invoice->payable_amount ?? 0, 2),
                        'status' => $invoice->status_name,
                        'narration' => $invoice->narration ?? '-'
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching client invoices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search invoices'
            ], 500);
        }
    }

    /**
     * Create tenant invoice (AJAX)
     */
    public function storeTenantInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'lease_id' => 'required|exists:sys_pm_lease_agreements,id',
                'unit_id' => 'required|exists:sys_pm_units,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'date_due' => 'required|date',
                'narration' => 'required|string',
                'line_items' => 'required|array|min:1',
                'line_items.*.item_id' => 'required',
                'line_items.*.amount' => 'required|numeric|min:0',
                'line_items.*.quantity' => 'required|numeric|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Calculate total amount
            $totalAmount = 0;
            foreach ($request->line_items as $item) {
                $totalAmount += $item['amount'] * $item['quantity'];
            }

            // Generate reference number
            $referenceNumber = 'TI-' . date('Ymd') . '-' . str_pad(DebtorInvoice::tenantInvoices()->count() + 1, 5, '0', STR_PAD_LEFT);

            // Create invoice
            $invoice = DebtorInvoice::create([
                'date_time' => now(),
                'date_due' => $request->date_due,
                'debtor_type_id' => 2, // Tenant invoice
                'tenant_id' => $request->tenant_id,
                'lease_id' => $request->lease_id,
                'unit_id' => $request->unit_id,
                'property_id' => $request->property_id,
                'client_id' => $request->client_id ?? null,
                'zone_id' => $request->zone_id ?? null,
                'sla_id' => $request->sla_id ?? null,
                'reference_number' => $referenceNumber,
                'narration' => $request->narration,
                'total_amount' => $totalAmount,
                'current_balance' => $totalAmount,
                'current_status_id' => 1, // Pending
                'created_by' => Auth::id(),
                'created_date' => now()
            ]);

            // Create line items
            foreach ($request->line_items as $item) {
                InvoiceLineItem::create([
                    'parent_id' => $invoice->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'amount' => $item['amount'],
                    'current_status_id' => 1
                ]);
            }

            // Create workflow entry
            InvoiceWorkflow::create([
                'parent_id' => $invoice->id,
                'action_by_id' => Auth::id(),
                'action_position_id' => 1,
                'action_id' => 1,
                'action_date_time' => now(),
                'remark' => 'Created invoice: ' . $request->narration
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant invoice created successfully',
                'invoice' => [
                    'id' => $invoice->id,
                    'reference_number' => $invoice->reference_number
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating tenant invoice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant invoice'
            ], 500);
        }
    }

    /**
     * Create client invoice (AJAX)
     */
    public function storeClientInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client_id' => 'required|exists:sys_pm_clients,id',
                'date_due' => 'required|date',
                'narration' => 'required|string',
                'line_items' => 'required|array|min:1',
                'line_items.*.item_id' => 'required',
                'line_items.*.amount' => 'required|numeric|min:0',
                'line_items.*.quantity' => 'required|numeric|min:1',
                'comm_per' => 'nullable|numeric|min:0|max:100',
                'vat_per' => 'nullable|numeric|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Calculate amounts
            $totalAmount = 0;
            foreach ($request->line_items as $item) {
                $totalAmount += $item['amount'] * $item['quantity'];
            }

            $commPer = $request->comm_per ?? 0;
            $vatPer = $request->vat_per ?? 0;

            $commissionableAmount = $totalAmount;
            $commAmount = ($commissionableAmount * $commPer) / 100;
            $vatOnCommission = ($commAmount * $vatPer) / 100;
            $payableAmount = $totalAmount - $commAmount;

            // Generate reference number
            $referenceNumber = 'CI-' . date('Ymd') . '-' . str_pad(DebtorInvoice::clientInvoices()->count() + 1, 5, '0', STR_PAD_LEFT);

            // Create invoice
            $invoice = DebtorInvoice::create([
                'date_time' => now(),
                'date_due' => $request->date_due,
                'debtor_type_id' => 3, // Client invoice (not tenant)
                'client_id' => $request->client_id,
                'property_id' => $request->property_id ?? null,
                'reference_number' => $referenceNumber,
                'narration' => $request->narration,
                'total_amount' => $totalAmount,
                'current_balance' => $totalAmount,
                'comm_per' => $commPer,
                'comm_ammount' => $commAmount,
                'commisionable_amount' => $commissionableAmount,
                'payable_amount' => $payableAmount,
                'vat_per' => $vatPer,
                'vat_on_commission' => $vatOnCommission,
                'current_status_id' => 1, // Pending
                'created_by' => Auth::id(),
                'created_date' => now()
            ]);

            // Create line items
            foreach ($request->line_items as $item) {
                InvoiceLineItem::create([
                    'parent_id' => $invoice->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'amount' => $item['amount'],
                    'current_status_id' => 1
                ]);
            }

            // Create workflow entry
            InvoiceWorkflow::create([
                'parent_id' => $invoice->id,
                'action_by_id' => Auth::id(),
                'action_position_id' => 1,
                'action_id' => 1,
                'action_date_time' => now(),
                'remark' => 'Created invoice: ' . $request->narration
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Client invoice created successfully',
                'invoice' => [
                    'id' => $invoice->id,
                    'reference_number' => $invoice->reference_number
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating client invoice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create client invoice'
            ], 500);
        }
    }

    /**
     * Export tenant invoices to Excel
     */
    public function exportTenantInvoices(Request $request)
    {
        try {
            $query = DebtorInvoice::with(['tenant', 'property', 'unit', 'lease'])
                ->tenantInvoices();

            // Apply same filters as search
            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }
            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }
            if ($request->filled('reference_number')) {
                $query->where('reference_number', 'LIKE', '%' . $request->reference_number . '%');
            }
            if ($request->filled('status_id')) {
                $query->where('current_status_id', $request->status_id);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('date_time', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('date_time', '<=', $request->date_to);
            }

            $invoices = $query->orderBy('date_time', 'desc')->get();

            $fileName = 'tenant_invoices_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new TenantInvoicesExport($invoices), $fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting tenant invoices: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export tenant invoices');
        }
    }

    /**
     * Export client invoices to Excel
     */
    public function exportClientInvoices(Request $request)
    {
        try {
            $query = DebtorInvoice::with(['client', 'property'])
                ->clientInvoices();

            // Apply same filters as search
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }
            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }
            if ($request->filled('reference_number')) {
                $query->where('reference_number', 'LIKE', '%' . $request->reference_number . '%');
            }
            if ($request->filled('status_id')) {
                $query->where('current_status_id', $request->status_id);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('date_time', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('date_time', '<=', $request->date_to);
            }

            $invoices = $query->orderBy('date_time', 'desc')->get();

            $fileName = 'client_invoices_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new ClientInvoicesExport($invoices), $fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting client invoices: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export client invoices');
        }
    }

    /**
     * Get tenants for dropdown (AJAX)
     */
    public function getTenants(Request $request)
    {
        try {
            $search = $request->search;

            $tenants = Client::tenants()
                ->select(['id', 'names', 'mobile_number', 'reference_number'])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('names', 'LIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                            ->orWhere('reference_number', 'LIKE', "%{$search}%");
                    });
                })
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'tenants' => $tenants
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching tenants: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenants'
            ], 500);
        }
    }

    /**
     * Get clients for dropdown (AJAX)
     */
    public function getClients(Request $request)
    {
        try {
            $search = $request->search;

            $clients = Client::typeOne()
                ->select(['id', 'names', 'mobile_number', 'reference_number'])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('names', 'LIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                            ->orWhere('reference_number', 'LIKE', "%{$search}%");
                    });
                })
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'clients' => $clients
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching clients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clients'
            ], 500);
        }
    }

    /**
     * Get properties by client (AJAX)
     */
    public function getPropertiesByClient(Request $request, $clientId)
    {
        try {
            $properties = Property::where('client_id', $clientId)
                ->select(['id', 'property_name', 'reference_number'])
                ->get();

            return response()->json([
                'success' => true,
                'properties' => $properties
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching properties: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch properties'
            ], 500);
        }
    }

    /**
     * Get units by property (AJAX)
     */
    public function getUnitsByProperty(Request $request, $propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->select(['id', 'unit_name', 'reference_number'])
                ->get();

            return response()->json([
                'success' => true,
                'units' => $units
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching units: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch units'
            ], 500);
        }
    }

    /**
     * Get leases by tenant (AJAX)
     */
    public function getLeasesByTenant(Request $request, $tenantId)
    {
        try {
            $leases = LeaseAgreement::where('tenant_id', $tenantId)
                ->with(['property', 'unit'])
                ->select(['id', 'ref_number', 'property_id', 'unit_id', 'date_start', 'date_end'])
                ->get();

            return response()->json([
                'success' => true,
                'leases' => $leases->map(function ($lease) {
                    return [
                        'id' => $lease->id,
                        'ref_number' => $lease->ref_number,
                        'property_id' => $lease->property_id,
                        'unit_id' => $lease->unit_id,
                        'property_name' => $lease->property->property_name ?? '-',
                        'unit_name' => $lease->unit->unit_name ?? '-',
                        'date_start' => $lease->date_start ? $lease->date_start->format('d-m-Y') : '-',
                        'date_end' => $lease->date_end ? $lease->date_end->format('d-m-Y') : '-'
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching leases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leases'
            ], 500);
        }
    }

    /**
     * Display tenant deposits page
     */
    public function tenantDeposits()
    {
        try {
            return view('property.deposits.tenant-deposits');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant deposits page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenant deposits page');
        }
    }

    /**
     * Search deposits (AJAX)
     */
    public function searchDeposits(Request $request)
    {
        try {
            $query = Deposit::with(['tenant', 'property', 'unit', 'lease']);

            // Apply filters
            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }

            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($request->filled('reference_number')) {
                $query->where('reference_number', 'LIKE', '%' . $request->reference_number . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date_time', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date_time', '<=', $request->date_to);
            }

            // Limit to latest 100 if no specific filters
            if (!$request->filled('tenant_id') && !$request->filled('date_from') && !$request->filled('date_to')) {
                $deposits = $query->orderBy('date_time', 'desc')->limit(100)->get();
            } else {
                $deposits = $query->orderBy('date_time', 'desc')->get();
            }

            return response()->json([
                'success' => true,
                'deposits' => $deposits->map(function ($deposit) {
                    return [
                        'id' => $deposit->id,
                        'reference_number' => $deposit->reference_number ?? '-',
                        'tenant_name' => $deposit->tenant->names ?? '-',
                        'property_name' => $deposit->property->property_name ?? '-',
                        'unit_name' => $deposit->unit->unit_name ?? '-',
                        'date_time' => $deposit->date_time ? $deposit->date_time->format('d-m-Y') : '-',
                        'amount' => number_format($deposit->amount, 2),
                        'payment_mode' => $deposit->payment_mode ?? '-',
                        'narration' => $deposit->narration ?? '-'
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching deposits: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search deposits'
            ], 500);
        }
    }

    /**
     * Get deposit details with linked invoices (AJAX)
     */
    public function getDepositDetails($id)
    {
        try {
            $deposit = Deposit::with(['tenant', 'property', 'unit', 'lease'])->findOrFail($id);

            // Get linked invoices - assuming there's a relationship or join table
            // If deposits link directly to invoices via invoice_id field in deposits table
            $invoices = collect();

            // Check if there's a direct invoice_id relationship
            if (isset($deposit->invoice_id) && $deposit->invoice_id) {
                $invoices = DebtorInvoice::where('id', $deposit->invoice_id)->get();
            }
            // Or if there's a many-to-many relationship through allocations
            else {
                $invoices = $deposit->invoices ?? collect();
            }

            return response()->json([
                'success' => true,
                'deposit' => [
                    'id' => $deposit->id,
                    'reference_number' => $deposit->reference_number ?? 'N/A',
                    'date_time' => $deposit->date_time ? $deposit->date_time->format('d M Y') : 'N/A',
                    'amount' => number_format($deposit->amount, 2),
                    'payment_mode' => $deposit->payment_mode ?? 'N/A',
                    'narration' => $deposit->narration,
                    'tenant_name' => $deposit->tenant->names ?? 'N/A',
                    'property_name' => $deposit->property->property_name ?? 'N/A',
                    'unit_name' => $deposit->unit->unit_name ?? 'N/A'
                ],
                'invoices' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'reference_number' => $invoice->reference_number ?? 'N/A',
                        'date_time' => $invoice->date_time ? $invoice->date_time->format('d M Y') : 'N/A',
                        'date_due' => $invoice->date_due ? $invoice->date_due->format('d M Y') : 'N/A',
                        'narration' => $invoice->narration ?? 'N/A',
                        'total_amount' => number_format($invoice->total_amount, 2),
                        'current_balance' => number_format($invoice->current_balance, 2),
                        'current_status_id' => $invoice->current_status_id,
                        'status_name' => $invoice->status_name
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading deposit details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load deposit details'
            ], 500);
        }
    }

    /**
     * Display deposit details with linked invoices
     */
    public function depositDetails($id)
    {
        try {
            $deposit = Deposit::with(['tenant', 'property', 'unit', 'lease', 'invoices', 'allocations.invoice'])
                ->findOrFail($id);

            return view('property.deposits.deposit-details', compact('deposit'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading deposit details: ' . $e->getMessage());
            return redirect()->route('deposits.tenant.index')->with('error', 'Failed to load deposit details');
        }
    }

    /**
     * Display invoice generation page
     */
    public function showGenerateInvoices()
    {
        try {
            return view('property.invoices.generate-invoices');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading generate invoices page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load generate invoices page');
        }
    }

    /**
     * Get months where invoices have already been generated for current year (AJAX)
     */
    public function getGeneratedMonths(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));

            // Get months from the generated invoice months tracking table
            $generatedMonths = DB::table('sys_pm_generated_invoice_months')
                ->where('month_year', 'LIKE', $year . '-%')
                ->pluck('month_year')
                ->toArray();

            return response()->json([
                'success' => true,
                'generated_months' => $generatedMonths
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching generated months: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch generated months'
            ], 500);
        }
    }

    /**
     * Get generation history with details (AJAX)
     */
    public function getGenerationHistory(Request $request)
    {
        try {
            // Get generation history with user details
            $history = DB::table('sys_pm_generated_invoice_months as g')
                ->leftJoin('josya_users as u', 'g.generated_by', '=', 'u.id')
                ->select(
                    'g.month_year',
                    'g.invoice_count',
                    'g.generated_by',
                    'g.generated_at',
                    'u.name as generated_by_name'
                )
                ->orderBy('g.generated_at', 'desc')
                ->limit(10)
                ->get();

            Log::info('Generation history fetched: ' . $history->count() . ' records');

            return response()->json([
                'success' => true,
                'history' => $history
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching generation history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch generation history'
            ], 500);
        }
    }

    /**
     * Process bulk invoice generation (AJAX)
     */
    public function processGenerateInvoices(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'billing_month' => 'required|date_format:Y-m',
            ]);

            $billingMonth = $validated['billing_month'];
            $billingDate = $billingMonth . '-01'; // Set to 1st of the month
            $billingDateFormatted = date('Y-m-d', strtotime($billingDate));

            // Check if this month has already been generated using the tracking table
            $alreadyGenerated = DB::table('sys_pm_generated_invoice_months')
                ->where('month_year', $billingMonth)
                ->exists();

            if ($alreadyGenerated) {
                return response()->json([
                    'success' => false,
                    'message' => "Invoices for " . date('F Y', strtotime($billingDate)) . " have already been generated."
                ], 422);
            }

            // Get VAT on commission setting
            $vatOnCommission = DB::table('sys_set_global_settings')
                ->select('vat_on_commission')
                ->first();

            $vatOnCommRate = $vatOnCommission->vat_on_commission ?? 0;

            // Execute the invoice generation query
            $sql = "
                INSERT INTO sys_pm_debtors_invoices(
                    date_time,
                    date_due,
                    zone_id,
                    property_id,
                    unit_id,
                    lease_id,
                    tenant_id,
                    client_id,
                    sla_id,
                    created_by,
                    reference_number,
                    narration,
                    debtor_type_id,
                    total_amount,
                    trans_details,
                    current_status_id,
                    processed,
                    comm_ammount,
                    comm_per,
                    vat_ammount,
                    vat_per,
                    commisionable_amount,
                    payable_amount,
                    item_type_id,
                    cycle_id,
                    current_balance,
                    occupancy_status_id,
                    field_officer_id,
                    zone_supervisor_id,
                    zone_manager_id,
                    vat_on_commission,
                    override_period_check
                )
                SELECT
                    NOW() AS date_time,
                    ? AS date_due,
                    l.zone_id,
                    l.property_id,
                    l.unit_id,
                    l.id AS lease_id,
                    l.tenant_id,
                    l.client_id,
                    l.sla_id,
                    ? AS created_by,
                    CONCAT(
                        'INV',
                        DATE_FORMAT(?, '%Y'),
                        DATE_FORMAT(?, '%c'),
                        u.unit_code,
                        '-',
                        i.item_name,
                        ' for ',
                        DATE_FORMAT(?, '%M %Y'),
                        ' - ',
                        li.cycle_id,
                        '-',
                        l.id
                    ) AS reference_number,
                    CONCAT('Tenant Invoice - ', i.item_name) AS narration,
                    2 AS debtor_type_id,
                    li.amount AS total_amount,
                    CONCAT('18:', l.id, ':', UNIX_TIMESTAMP()) AS trans_details,
                    1 AS current_status_id,
                    'No' AS processed,
                    CASE 
                        WHEN i.commissionable = 'Yes' THEN
                            CASE
                                WHEN u.vat_able = 'Yes' THEN
                                    ROUND(
                                        (li.amount - ROUND(li.amount * 0.16, 2)) 
                                        * (sla.commission_percentage / 100), 
                                        2
                                    )
                                ELSE
                                    ROUND(li.amount * (sla.commission_percentage / 100), 2)
                            END
                        ELSE 0.00
                    END AS comm_ammount,
                    CASE 
                        WHEN i.commissionable = 'Yes' THEN sla.commission_percentage
                        ELSE 0.00
                    END AS comm_per,
                    CASE
                        WHEN u.vat_able = 'Yes' THEN
                            ROUND(li.amount * 0.16, 2)
                        ELSE 0.00
                    END AS vat_ammount,
                    16.00 AS vat_per,
                    CASE
                        WHEN i.commissionable = 'Yes' THEN
                            CASE
                                WHEN u.vat_able = 'Yes' THEN
                                    li.amount - ROUND(li.amount * 0.16, 2)
                                ELSE
                                    li.amount
                            END
                        ELSE 0.00
                    END AS commisionable_amount,
                    CASE 
                        WHEN i.commissionable = 'Yes' THEN
                            CASE
                                WHEN u.vat_able = 'Yes' THEN
                                    ROUND(
                                        li.amount - 
                                        ROUND(
                                            (li.amount - ROUND(li.amount * 0.16, 2)) 
                                            * (sla.commission_percentage / 100), 
                                            2
                                        ), 
                                        2
                                    )
                                ELSE
                                    ROUND(li.amount - ROUND(li.amount * (sla.commission_percentage / 100), 2), 2)
                            END
                        ELSE li.amount
                    END AS payable_amount,
                    li.item_id AS item_type_id,
                    li.cycle_id AS cycle_id,
                    li.amount AS current_balance,
                    u.occupancy_status_id,
                    p.field_officer_id,
                    p.zone_supervisor_id,
                    p.zone_manager_id,
                    CASE 
                        WHEN i.commissionable = 'Yes' THEN
                            ROUND(
                                CASE
                                    WHEN u.vat_able = 'Yes' THEN
                                        ROUND(
                                            (li.amount - ROUND(li.amount * 0.16, 2)) 
                                            * (sla.commission_percentage / 100), 
                                            2
                                        ) * (? / 100)
                                    ELSE
                                        ROUND(li.amount * (sla.commission_percentage / 100), 2) 
                                        * (? / 100)
                                END,
                                2
                            )
                        ELSE 0.00
                    END AS vat_on_commission,
                    1 AS override_period_check
                FROM sys_pm_lease_agreements l
                INNER JOIN sys_pm_lease_agreements_27_repeat li 
                    ON li.parent_id = l.id
                INNER JOIN sys_pm_units u 
                    ON u.id = l.unit_id
                INNER JOIN sys_pm_properties p 
                    ON p.id = l.property_id
                INNER JOIN sys_pm_service_agreements sla 
                    ON sla.id = l.sla_id
                INNER JOIN sys_set_invoiceable_items i 
                    ON i.id = li.item_id
                WHERE
                    (l.current_status_id = 5 OR l.current_status_id = 21)
                    AND l.date_end >= CURDATE()
                    AND l.date_start IS NOT NULL
                    AND l.date_start <= ?
                    AND li.amount > 0
                    AND (
                        li.cycle_id = 2
                        OR 
                        (li.cycle_id = 1 AND li.cycle_one_invoiced IS NULL)
                    )
                ORDER BY l.id, li.item_id
            ";

            // Wrap everything in a DB transaction
            DB::beginTransaction();

            try {
                // Execute the invoice generation query
                $insertedRows = DB::insert($sql, [
                    $billingDateFormatted,              // date_due
                    Auth::id(),                          // created_by
                    $billingDateFormatted,              // for reference year (YYYY)
                    $billingDateFormatted,              // for reference month number (M)
                    $billingDateFormatted,              // for reference month name (MMMM YYYY)
                    $vatOnCommRate,                      // vat_on_commission rate (first occurrence)
                    $vatOnCommRate,                      // vat_on_commission rate (second occurrence)
                    $billingDateFormatted               // lease start check
                ]);

                // Get count of inserted records
                $generatedCount = DB::table('sys_pm_debtors_invoices')
                    ->where('created_by', Auth::id())
                    ->where('date_time', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 5 SECOND)'))
                    ->count();

                // Flag cycle_id=1 items as invoiced to prevent re-invoicing
                DB::update("
                    UPDATE sys_pm_lease_agreements_27_repeat li
                    INNER JOIN sys_pm_lease_agreements l ON l.id = li.parent_id
                    SET li.cycle_one_invoiced = 1,
                        li.cycle_one_invoiced_date = CURDATE()
                    WHERE li.cycle_id = 1
                    AND li.cycle_one_invoiced IS NULL
                    AND (l.current_status_id = 5 OR l.current_status_id = 21)
                    AND l.date_end >= CURDATE()
                    AND l.date_start IS NOT NULL
                    AND l.date_start <= ?
                    AND li.amount > 0
                ", [$billingDateFormatted]);

                // Save the generated month to the tracking table
                DB::table('sys_pm_generated_invoice_months')->insert([
                    'month_year' => $billingMonth,
                    'invoice_count' => $generatedCount,
                    'generated_by' => Auth::id(),
                    'generated_at' => now(),
                    'notes' => "Generated {$generatedCount} invoices"
                ]);

                // Commit the transaction only if everything succeeds
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully generated {$generatedCount} invoices for " . date('F Y', strtotime($billingDate)),
                    'count' => $generatedCount
                ]);
            } catch (\PDOException $e) {
                // Rollback on database errors (including constraint violations)
                DB::rollBack();
                ErrorDispatcher::dispatch($e);
                Log::error('Database error during invoice generation: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ], 500);
            } catch (\Illuminate\Database\QueryException $e) {
                // Rollback on query errors
                DB::rollBack();
                ErrorDispatcher::dispatch($e);
                Log::error('Query error during invoice generation: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Query error: ' . $e->getMessage()
                ], 500);
            } catch (\Exception $e) {
                // Rollback on any other error
                DB::rollBack();
                ErrorDispatcher::dispatch($e);
                Log::error('Error during invoice generation: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating invoices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoices: ' . $e->getMessage()
            ], 500);
        }
    }
}

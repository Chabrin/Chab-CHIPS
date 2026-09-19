<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\ErrorDispatcher;
use App\Models\TenantPayment;
use App\Models\TenantAccountTransaction;
use App\Models\DebtorInvoice;
use App\Models\ReturnSummary;
use App\Models\ProcessedReturn;
use App\Models\Client;
use App\Models\Property;
use App\Models\TenantExit;

class AccountsReportsController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // 1. ARREARS / AGING REPORT
    // ═══════════════════════════════════════════════════════════

    public function arrears(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        return view('reports.accounts.arrears', compact('properties'));
    }

    public function getArrears(Request $request): JsonResponse
    {
        try {
            $propertyId = $request->input('property_id');
            $asAtDate   = $request->input('as_at_date', now()->toDateString());
            $showAll    = $request->boolean('show_all', false);

            $query = DB::table('sys_pm_tenants_accounts_trans as t')
                ->select(
                    't.tenant_id',
                    DB::raw('SUM(t.credit_amount) - SUM(t.debit_amount) as closing_balance'),
                    'c.names as tenant_name',
                    'c.reference_number as tenant_ref',
                    'p.property_name',
                    'u.unit_code'
                )
                ->join('sys_pm_clients as c', 'c.id', '=', 't.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 't.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 't.unit_id')
                ->where('t.current_status_id', 1)
                ->whereDate('t.date_time', '<=', $asAtDate)
                ->groupBy('t.tenant_id', 'c.names', 'c.reference_number', 'p.property_name', 'u.unit_code');

            if ($propertyId) {
                $query->where('t.property_id', $propertyId);
            }

            $results = $query->get();

            if (!$showAll) {
                $results = $results->filter(fn($r) => $r->closing_balance < 0);
            }

            $data = $results->values()->map(function ($item, $index) {
                $balance = (float) $item->closing_balance;
                return [
                    'no'              => $index + 1,
                    'tenant_ref'      => $item->tenant_ref ?? 'N/A',
                    'tenant_name'     => $item->tenant_name ?? 'N/A',
                    'property'        => $item->property_name ?? 'N/A',
                    'unit'            => $item->unit_code ?? 'N/A',
                    'closing_balance' => number_format(abs($balance), 2),
                    'status'          => $balance >= 0 ? 'CR' : 'DR',
                    'raw_balance'     => $balance,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'as_at'   => $asAtDate,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getArrears — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportArrears(Request $request)
    {
        try {
            $propertyId = $request->input('property_id');
            $asAtDate   = $request->input('as_at_date', now()->toDateString());
            $showAll    = $request->boolean('show_all', false);

            $query = DB::table('sys_pm_tenants_accounts_trans as t')
                ->select(
                    't.tenant_id',
                    DB::raw('SUM(t.credit_amount) - SUM(t.debit_amount) as closing_balance'),
                    'c.names as tenant_name',
                    'c.reference_number as tenant_ref',
                    'p.property_name',
                    'u.unit_code'
                )
                ->join('sys_pm_clients as c', 'c.id', '=', 't.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 't.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 't.unit_id')
                ->where('t.current_status_id', 1)
                ->whereDate('t.date_time', '<=', $asAtDate)
                ->groupBy('t.tenant_id', 'c.names', 'c.reference_number', 'p.property_name', 'u.unit_code');

            if ($propertyId) {
                $query->where('t.property_id', $propertyId);
            }

            $results = $query->get();
            if (!$showAll) {
                $results = $results->filter(fn($r) => $r->closing_balance < 0);
            }

            $headings = ['#', 'Tenant Ref', 'Tenant Name', 'Property', 'Unit', 'Closing Balance (KES)', 'Status'];
            $rows = $results->values()->map(function ($item, $index) {
                $balance = (float) $item->closing_balance;
                return [
                    $index + 1,
                    $item->tenant_ref ?? 'N/A',
                    $item->tenant_name ?? 'N/A',
                    $item->property_name ?? 'N/A',
                    $item->unit_code ?? 'N/A',
                    number_format(abs($balance), 2),
                    $balance >= 0 ? 'CR' : 'DR',
                ];
            })->values()->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Arrears / Aging Report',
                    $headings,
                    $rows,
                    ['As At: ' . $asAtDate, 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'arrears-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportArrears — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 2. PAYMENT COLLECTION REPORT
    // ═══════════════════════════════════════════════════════════

    public function paymentCollection(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        $channels = DB::table('sys_set_payment_channel')
            ->select('id', 'channel_name')
            ->orderBy('channel_name')
            ->get();

        return view('reports.accounts.payment-collection', compact('properties', 'channels'));
    }

    public function getPaymentCollection(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'required|date',
                'date_to'   => 'required|date|after_or_equal:date_from',
            ]);

            $dateFrom  = $request->input('date_from');
            $dateTo    = $request->input('date_to');
            $propertyId = $request->input('property_id');
            $channelId  = $request->input('channel_id');

            $statusMap = [11 => 'Unallocated', 12 => 'Partially Allocated', 13 => 'Fully Allocated'];

            $query = TenantPayment::with(['tenant', 'property', 'unit', 'channel'])
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->orderBy('payment_date');

            if ($propertyId) {
                $query->where('property_id', $propertyId);
            }
            if ($channelId) {
                $query->where('channel_id', $channelId);
            }

            $payments = $query->get();
            $total    = $payments->sum('amount');

            $data = $payments->map(function ($p, $index) use ($statusMap) {
                return [
                    'no'            => $index + 1,
                    'date'          => $p->payment_date ? Carbon::parse($p->payment_date)->format('d/m/Y') : 'N/A',
                    'receipt_no'    => $p->receipt_number ?? 'N/A',
                    'tenant_name'   => $p->tenant?->names ?? 'N/A',
                    'property'      => $p->property?->property_name ?? 'N/A',
                    'unit'          => $p->unit?->unit_code ?? 'N/A',
                    'amount'        => number_format($p->amount, 2),
                    'channel'       => $p->channel?->channel_name ?? 'N/A',
                    'narration'     => $p->narration ?? '-',
                    'status'        => $statusMap[$p->current_status_id] ?? 'Unknown',
                    'status_id'     => $p->current_status_id,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'total'   => number_format($total, 2),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getPaymentCollection — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportPaymentCollection(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');
            $channelId  = $request->input('channel_id');

            $statusMap = [11 => 'Unallocated', 12 => 'Partially Allocated', 13 => 'Fully Allocated'];

            $query = TenantPayment::with(['tenant', 'property', 'unit', 'channel'])
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->orderBy('payment_date');

            if ($propertyId) { $query->where('property_id', $propertyId); }
            if ($channelId)  { $query->where('channel_id', $channelId); }

            $payments = $query->get();
            $total    = $payments->sum('amount');

            $headings = ['#', 'Date', 'Receipt No', 'Tenant Name', 'Property', 'Unit', 'Amount (KES)', 'Channel', 'Narration', 'Status'];
            $rows = $payments->map(function ($p, $i) use ($statusMap) {
                return [
                    $i + 1,
                    $p->payment_date ? Carbon::parse($p->payment_date)->format('d/m/Y') : 'N/A',
                    $p->receipt_number ?? 'N/A',
                    $p->tenant?->names ?? 'N/A',
                    $p->property?->property_name ?? 'N/A',
                    $p->unit?->unit_code ?? 'N/A',
                    number_format($p->amount, 2),
                    $p->channel?->channel_name ?? 'N/A',
                    $p->narration ?? '-',
                    $statusMap[$p->current_status_id] ?? 'Unknown',
                ];
            })->toArray();

            $rows[] = ['', '', '', '', '', 'TOTAL', number_format($total, 2), '', '', ''];

            return Excel::download(
                GenericReportExport::build(
                    'Payment Collection Report',
                    $headings,
                    $rows,
                    ['Period: ' . $dateFrom . ' to ' . $dateTo, 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    [],
                    true
                ),
                'payment-collection-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportPaymentCollection — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 3. INVOICE REGISTER
    // ═══════════════════════════════════════════════════════════

    public function invoiceRegister(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        $statuses = [
            1 => 'Pending',
            6 => 'Posted',
            8 => 'Partially Cleared',
            9 => 'Cleared',
        ];

        return view('reports.accounts.invoice-register', compact('properties', 'statuses'));
    }

    public function getInvoiceRegister(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');
            $statusId   = $request->input('status');

            $statusMap = [1 => 'Pending', 6 => 'Posted', 8 => 'Partially Cleared', 9 => 'Cleared'];

            $query = DebtorInvoice::with(['tenant', 'property', 'unit'])
                ->tenantInvoices()
                ->orderBy('date_time', 'desc');

            if ($dateFrom) { $query->whereDate('date_time', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('date_time', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }
            if ($statusId)   { $query->where('current_status_id', $statusId); }

            $invoices = $query->get();

            $data = $invoices->map(function ($inv, $index) use ($statusMap) {
                return [
                    'no'           => $index + 1,
                    'reference'    => $inv->reference_number ?? 'N/A',
                    'date'         => $inv->date_time ? $inv->date_time->format('d/m/Y') : 'N/A',
                    'date_due'     => $inv->date_due ? Carbon::parse($inv->date_due)->format('d/m/Y') : 'N/A',
                    'tenant'       => $inv->tenant?->names ?? 'N/A',
                    'property'     => $inv->property?->property_name ?? 'N/A',
                    'unit'         => $inv->unit?->unit_code ?? 'N/A',
                    'total_amount' => number_format($inv->total_amount, 2),
                    'balance'      => number_format($inv->current_balance, 2),
                    'status'       => $statusMap[$inv->current_status_id] ?? 'Unknown',
                    'status_id'    => $inv->current_status_id,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getInvoiceRegister — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportInvoiceRegister(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');
            $statusId   = $request->input('status');

            $statusMap = [1 => 'Pending', 6 => 'Posted', 8 => 'Partially Cleared', 9 => 'Cleared'];

            $query = DebtorInvoice::with(['tenant', 'property', 'unit'])
                ->tenantInvoices()
                ->orderBy('date_time', 'desc');

            if ($dateFrom) { $query->whereDate('date_time', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('date_time', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }
            if ($statusId)   { $query->where('current_status_id', $statusId); }

            $invoices = $query->get();

            $headings = ['#', 'Ref', 'Date', 'Due Date', 'Tenant', 'Property', 'Unit', 'Total (KES)', 'Balance (KES)', 'Status'];
            $rows = $invoices->map(function ($inv, $i) use ($statusMap) {
                return [
                    $i + 1,
                    $inv->reference_number ?? 'N/A',
                    $inv->date_time ? $inv->date_time->format('d/m/Y') : 'N/A',
                    $inv->date_due ? Carbon::parse($inv->date_due)->format('d/m/Y') : 'N/A',
                    $inv->tenant?->names ?? 'N/A',
                    $inv->property?->property_name ?? 'N/A',
                    $inv->unit?->unit_code ?? 'N/A',
                    number_format($inv->total_amount, 2),
                    number_format($inv->current_balance, 2),
                    $statusMap[$inv->current_status_id] ?? 'Unknown',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Invoice Register',
                    $headings,
                    $rows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'invoice-register-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportInvoiceRegister — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 4. UNALLOCATED PAYMENTS
    // ═══════════════════════════════════════════════════════════

    public function unallocatedPayments(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        return view('reports.accounts.unallocated-payments', compact('properties'));
    }

    public function getUnallocatedPayments(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');
            $today      = now();

            $query = TenantPayment::with(['tenant', 'property', 'unit'])
                ->where('current_status_id', 11)
                ->orderBy('payment_date');

            if ($dateFrom) { $query->whereDate('payment_date', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('payment_date', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }

            $payments = $query->get();
            $total    = $payments->sum('amount');

            $data = $payments->map(function ($p, $index) use ($today) {
                $payDate = $p->payment_date ? Carbon::parse($p->payment_date) : null;
                $days    = $payDate ? (int) $payDate->diffInDays($today) : 0;
                return [
                    'no'             => $index + 1,
                    'date'           => $payDate ? $payDate->format('d/m/Y') : 'N/A',
                    'receipt_no'     => $p->receipt_number ?? 'N/A',
                    'tenant'         => $p->tenant?->names ?? 'N/A',
                    'property'       => $p->property?->property_name ?? 'N/A',
                    'unit'           => $p->unit?->unit_code ?? 'N/A',
                    'amount'         => number_format($p->amount, 2),
                    'days_unalloc'   => $days,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'total'   => number_format($total, 2),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getUnallocatedPayments — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportUnallocatedPayments(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');
            $today      = now();

            $query = TenantPayment::with(['tenant', 'property', 'unit'])
                ->where('current_status_id', 11)
                ->orderBy('payment_date');

            if ($dateFrom) { $query->whereDate('payment_date', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('payment_date', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }

            $payments = $query->get();
            $total    = $payments->sum('amount');

            $headings = ['#', 'Date', 'Receipt No', 'Tenant', 'Property', 'Unit', 'Amount (KES)', 'Days Unallocated'];
            $rows = $payments->map(function ($p, $i) use ($today) {
                $payDate = $p->payment_date ? Carbon::parse($p->payment_date) : null;
                $days    = $payDate ? (int) $payDate->diffInDays($today) : 0;
                return [
                    $i + 1,
                    $payDate ? $payDate->format('d/m/Y') : 'N/A',
                    $p->receipt_number ?? 'N/A',
                    $p->tenant?->names ?? 'N/A',
                    $p->property?->property_name ?? 'N/A',
                    $p->unit?->unit_code ?? 'N/A',
                    number_format($p->amount, 2),
                    $days,
                ];
            })->toArray();

            $rows[] = ['', '', '', '', '', 'TOTAL', number_format($total, 2), ''];

            return Excel::download(
                GenericReportExport::build(
                    'Unallocated Payments Report',
                    $headings,
                    $rows,
                    ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    [],
                    true
                ),
                'unallocated-payments-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportUnallocatedPayments — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 5. RETURNS SUMMARY
    // ═══════════════════════════════════════════════════════════

    public function returnsSummary(): View
    {
        return view('reports.accounts.returns-summary');
    }

    public function getReturnsSummary(Request $request): JsonResponse
    {
        try {
            $request->validate(['year' => 'required|integer|min:2000|max:2100']);

            $year       = $request->input('year');
            $month      = $request->input('month');
            $landlordId = $request->input('landlord_id');

            // Resolve landlord reference_number from client id if provided
            $landlordCode = null;
            if ($landlordId) {
                $landlordCode = DB::table('sys_pm_clients')
                    ->where('id', $landlordId)
                    ->value('reference_number');
            }

            $query = ReturnSummary::where('year', $year);

            if ($month) { $query->where('month', $month); }
            if ($landlordCode) { $query->where('landlord_code', $landlordCode); }

            $returns = $query->orderBy('year')->orderBy('month')->get();

            $monthNames = [
                1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
            ];

            $data = $returns->map(function ($r, $index) use ($monthNames) {
                $mon = (int) $r->month;
                return [
                    'no'              => $index + 1,
                    'period'          => ($monthNames[$mon] ?? $r->month) . ' ' . $r->year,
                    'landlord_code'   => $r->landlord_code ?? 'N/A',
                    'property_code'   => $r->property_code ?? 'N/A',
                    'commission_rate' => number_format((float)$r->commission_rate, 2) . '%',
                    'total_credit'    => number_format((float)$r->total_credit, 2),
                    'total_debit'     => number_format((float)$r->total_debit, 2),
                    'net_amount'      => number_format((float)$r->net_amount, 2),
                    'paid_status'     => $r->paid_status ? 'Paid' : 'Unpaid',
                    'voucher_number'  => $r->voucher_number ?? '-',
                    'cheque_number'   => $r->cheque_number ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getReturnsSummary — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportReturnsSummary(Request $request)
    {
        try {
            $year       = $request->input('year');
            $month      = $request->input('month');
            $landlordId = $request->input('landlord_id');

            $landlordCode = null;
            if ($landlordId) {
                $landlordCode = DB::table('sys_pm_clients')
                    ->where('id', $landlordId)
                    ->value('reference_number');
            }

            $query = ReturnSummary::where('year', $year);
            if ($month) { $query->where('month', $month); }
            if ($landlordCode) { $query->where('landlord_code', $landlordCode); }

            $returns = $query->orderBy('year')->orderBy('month')->get();

            $monthNames = [
                1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
            ];

            $headings = ['#', 'Period', 'Landlord Code', 'Property Code', 'Commission Rate (%)', 'Total Credit (KES)', 'Total Debit (KES)', 'Net Amount (KES)', 'Status', 'Voucher No', 'Cheque No'];
            $rows = $returns->map(function ($r, $i) use ($monthNames) {
                $mon = (int) $r->month;
                return [
                    $i + 1,
                    ($monthNames[$mon] ?? $r->month) . ' ' . $r->year,
                    $r->landlord_code ?? 'N/A',
                    $r->property_code ?? 'N/A',
                    number_format((float)$r->commission_rate, 2),
                    number_format((float)$r->total_credit, 2),
                    number_format((float)$r->total_debit, 2),
                    number_format((float)$r->net_amount, 2),
                    $r->paid_status ? 'Paid' : 'Unpaid',
                    $r->voucher_number ?? '-',
                    $r->cheque_number ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Returns Summary Report',
                    $headings,
                    $rows,
                    ['Year: ' . $year . ($month ? ', Month: ' . $month : ''), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'returns-summary-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportReturnsSummary — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 6. COMMISSION REPORT
    // ═══════════════════════════════════════════════════════════

    public function commission(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        return view('reports.accounts.commission', compact('properties'));
    }

    public function getCommission(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $statusMap = [5 => 'Approved', 17 => 'Processed'];

            $query = ProcessedReturn::with(['property', 'client'])
                ->processed()
                ->orderBy('period_date', 'desc');

            if ($dateFrom) { $query->whereDate('period_date', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('period_date', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }

            $records = $query->get();

            $totals = [
                'gross'      => $records->sum('total_gross_amount'),
                'commission' => $records->sum('total_commission'),
                'vat'        => $records->sum('total_vat'),
                'net'        => $records->sum('total_net_amount'),
            ];

            $data = $records->map(function ($r, $index) use ($statusMap) {
                return [
                    'no'          => $index + 1,
                    'period_date' => $r->period_date ? Carbon::parse($r->period_date)->format('d/m/Y') : 'N/A',
                    'property'    => $r->property?->property_name ?? 'N/A',
                    'landlord'    => $r->client?->names ?? 'N/A',
                    'gross'       => number_format($r->total_gross_amount, 2),
                    'commission'  => number_format($r->total_commission, 2),
                    'vat'         => number_format($r->total_vat, 2),
                    'net'         => number_format($r->total_net_amount, 2),
                    'status'      => $statusMap[$r->current_status_id] ?? 'Unknown',
                    'status_id'   => $r->current_status_id,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'totals'  => [
                    'gross'      => number_format($totals['gross'], 2),
                    'commission' => number_format($totals['commission'], 2),
                    'vat'        => number_format($totals['vat'], 2),
                    'net'        => number_format($totals['net'], 2),
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getCommission — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportCommission(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $statusMap = [5 => 'Approved', 17 => 'Processed'];

            $query = ProcessedReturn::with(['property', 'client'])
                ->processed()
                ->orderBy('period_date', 'desc');

            if ($dateFrom) { $query->whereDate('period_date', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('period_date', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }

            $records = $query->get();

            $totals = [
                'gross'      => $records->sum('total_gross_amount'),
                'commission' => $records->sum('total_commission'),
                'vat'        => $records->sum('total_vat'),
                'net'        => $records->sum('total_net_amount'),
            ];

            $headings = ['#', 'Period Date', 'Property', 'Landlord', 'Gross Amount (KES)', 'Commission (KES)', 'VAT (KES)', 'Net Amount (KES)', 'Status'];
            $rows = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    $i + 1,
                    $r->period_date ? Carbon::parse($r->period_date)->format('d/m/Y') : 'N/A',
                    $r->property?->property_name ?? 'N/A',
                    $r->client?->names ?? 'N/A',
                    number_format($r->total_gross_amount, 2),
                    number_format($r->total_commission, 2),
                    number_format($r->total_vat, 2),
                    number_format($r->total_net_amount, 2),
                    $statusMap[$r->current_status_id] ?? 'Unknown',
                ];
            })->toArray();

            $rows[] = ['', '', '', 'TOTALS',
                number_format($totals['gross'], 2),
                number_format($totals['commission'], 2),
                number_format($totals['vat'], 2),
                number_format($totals['net'], 2),
                '',
            ];

            return Excel::download(
                GenericReportExport::build(
                    'Commission Report',
                    $headings,
                    $rows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    [],
                    true
                ),
                'commission-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportCommission — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 8. CHEQUE REGISTER
    // ═══════════════════════════════════════════════════════════

    public function chequeRegister(): View
    {
        return view('reports.accounts.cheque-register');
    }

    public function getChequeRegister(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $status   = $request->input('status');

            $query = DB::table('sys_fin_cheque_books_261_repeat as cl')
                ->select(
                    'cl.id',
                    'cl.cheque_number',
                    'cl.status',
                    'cl.issued_to as payee',
                    'cl.amount',
                    'cl.issued_date',
                    'cl.issued_by',
                    'cb.reference_number as book_ref',
                    'c.names as client_name'
                )
                ->join('sys_fin_cheque_books as cb', 'cb.id', '=', 'cl.cheque_book_id')
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'cb.client_id')
                ->when($dateFrom, fn($q) => $q->whereDate('cl.issued_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('cl.issued_date', '<=', $dateTo))
                ->when($status,   fn($q) => $q->where('cl.status', $status))
                ->orderBy('cl.issued_date', 'desc')
                ->orderBy('cl.cheque_number');

            $rows = $query->get();

            $statusMap = [
                'available'  => 'Available',
                'locked'     => 'Locked',
                'used'       => 'Used',
                'cancelled'  => 'Cancelled',
            ];

            $data = $rows->map(function ($r, $i) use ($statusMap) {
                $rawStatus = strtolower($r->status ?? 'available');
                return [
                    'no'          => $i + 1,
                    'book_ref'    => $r->book_ref ?? 'N/A',
                    'cheque_no'   => $r->cheque_number ?? 'N/A',
                    'payee'       => $r->payee ?? ($r->client_name ?? '-'),
                    'amount'      => $r->amount ? number_format((float) $r->amount, 2) : '-',
                    'issue_date'  => $r->issued_date ? \Carbon\Carbon::parse($r->issued_date)->format('d/m/Y') : '-',
                    'status'      => $statusMap[$rawStatus] ?? ucfirst($rawStatus),
                    'status_raw'  => $rawStatus,
                    'issued_by'   => $r->issued_by ?? '-',
                ];
            });

            // Chart: count by status
            $statusCounts = $rows->groupBy(fn($r) => strtolower($r->status ?? 'available'))
                ->map->count();

            $chartLabels = ['Available', 'Locked', 'Used', 'Cancelled'];
            $chartKeys   = ['available', 'locked', 'used', 'cancelled'];
            $chartValues = array_map(fn($k) => $statusCounts->get($k, 0), $chartKeys);

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'chart'   => ['labels' => $chartLabels, 'values' => $chartValues],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getChequeRegister — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportChequeRegister(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $status   = $request->input('status');

            $rows = DB::table('sys_fin_cheque_books_261_repeat as cl')
                ->select(
                    'cl.cheque_number', 'cl.status', 'cl.issued_to as payee',
                    'cl.amount', 'cl.issued_date', 'cl.issued_by',
                    'cb.reference_number as book_ref', 'c.names as client_name'
                )
                ->join('sys_fin_cheque_books as cb', 'cb.id', '=', 'cl.cheque_book_id')
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'cb.client_id')
                ->when($dateFrom, fn($q) => $q->whereDate('cl.issued_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('cl.issued_date', '<=', $dateTo))
                ->when($status,   fn($q) => $q->where('cl.status', $status))
                ->orderBy('cl.issued_date', 'desc')
                ->orderBy('cl.cheque_number')
                ->get();

            $headings = ['#', 'Cheque Book Ref', 'Cheque Number', 'Payee/Description', 'Amount (KES)', 'Issue Date', 'Status', 'Issued By'];
            $exportRows = $rows->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->book_ref ?? 'N/A',
                    $r->cheque_number ?? 'N/A',
                    $r->payee ?? ($r->client_name ?? '-'),
                    $r->amount ? number_format((float) $r->amount, 2) : '-',
                    $r->issued_date ? \Carbon\Carbon::parse($r->issued_date)->format('d/m/Y') : '-',
                    ucfirst(strtolower($r->status ?? 'available')),
                    $r->issued_by ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Cheque Register',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'cheque-register-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportChequeRegister — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 9. VOUCHER REGISTER
    // ═══════════════════════════════════════════════════════════

    public function voucherRegister(): View
    {
        return view('reports.accounts.voucher-register');
    }

    public function getVoucherRegister(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $status   = $request->input('status');

            $query = DB::table('cb_payment_vouchers_headers_new as pvh')
                ->select(
                    'pvh.id',
                    'pvh.voucher_number',
                    'pvh.voucher_date',
                    'pvh.vendor_name',
                    'pvh.voucher_status',
                    'pvh.is_printed',
                    'pvh.prepared_by',
                    'pvh.checked_by',
                    'pvh.authorized_by',
                    DB::raw('COALESCE(SUM(pvl.amount), 0) as total_amount')
                )
                ->leftJoin('cb_payment_vouchers_lines_new as pvl', 'pvl.voucher_number', '=', 'pvh.voucher_number')
                ->groupBy(
                    'pvh.id', 'pvh.voucher_number', 'pvh.voucher_date', 'pvh.vendor_name',
                    'pvh.voucher_status', 'pvh.is_printed', 'pvh.prepared_by', 'pvh.checked_by',
                    'pvh.authorized_by'
                )
                ->when($dateFrom, fn($q) => $q->whereDate('pvh.voucher_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('pvh.voucher_date', '<=', $dateTo))
                ->when($status,   fn($q) => $q->whereRaw('LOWER(pvh.voucher_status) = ?', [strtolower($status)]))
                ->orderBy('pvh.voucher_date', 'desc');

            $rows = $query->get();

            $data = $rows->map(function ($r, $i) {
                return [
                    'no'              => $i + 1,
                    'voucher_no'      => $r->voucher_number ?? 'N/A',
                    'date'            => $r->voucher_date ? \Carbon\Carbon::parse($r->voucher_date)->format('d/m/Y') : 'N/A',
                    'payee'           => $r->vendor_name ?? '-',
                    'total_amount'    => number_format((float) $r->total_amount, 2),
                    'prepared_by'     => $r->prepared_by ?? '-',
                    'checked_by'      => $r->checked_by ?? '-',
                    'authorized_by'   => $r->authorized_by ?? '-',
                    'status'          => $r->voucher_status ?? '-',
                    'printed'         => $r->is_printed ? 'Yes' : 'No',
                ];
            });

            // Chart: count by status grouped by month
            $byStatus  = $rows->groupBy('voucher_status');
            $allMonths = $rows->map(fn($r) => $r->voucher_date
                ? \Carbon\Carbon::parse($r->voucher_date)->format('M Y') : null)
                ->filter()->unique()->values();

            $datasets = $byStatus->map(function ($items, $statusLabel) use ($allMonths) {
                $byMonth = $items->groupBy(fn($r) => $r->voucher_date
                    ? \Carbon\Carbon::parse($r->voucher_date)->format('M Y') : 'Unknown');
                return [
                    'label' => $statusLabel ?? 'Unknown',
                    'data'  => $allMonths->map(fn($m) => $byMonth->get($m, collect())->count())->values()->toArray(),
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'chart'   => ['labels' => $allMonths->toArray(), 'datasets' => $datasets],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getVoucherRegister — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportVoucherRegister(Request $request): StreamedResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $status   = $request->input('status');

        $query = DB::table('cb_payment_vouchers_headers_new as pvh')
            ->select(
                'pvh.voucher_number', 'pvh.voucher_date', 'pvh.vendor_name',
                'pvh.voucher_status', 'pvh.is_printed',
                'pvh.prepared_by', 'pvh.checked_by', 'pvh.authorized_by',
                DB::raw('COALESCE(SUM(pvl.amount), 0) as total_amount')
            )
            ->leftJoin('cb_payment_vouchers_lines_new as pvl', 'pvl.voucher_number', '=', 'pvh.voucher_number')
            ->groupBy(
                'pvh.id', 'pvh.voucher_number', 'pvh.voucher_date', 'pvh.vendor_name',
                'pvh.voucher_status', 'pvh.is_printed', 'pvh.prepared_by', 'pvh.checked_by', 'pvh.authorized_by'
            )
            ->when($dateFrom, fn($q) => $q->whereDate('pvh.voucher_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('pvh.voucher_date', '<=', $dateTo))
            ->when($status,   fn($q) => $q->whereRaw('LOWER(pvh.voucher_status) = ?', [strtolower($status)]))
            ->orderBy('pvh.voucher_date', 'desc')
            ->orderBy('pvh.id', 'desc');

        $headings = ['Voucher No', 'Date', 'Payee', 'Total Amount (KES)', 'Prepared By', 'Checked By', 'Authorized By', 'Status', 'Printed'];

        $response = new StreamedResponse(function () use ($query, $headings) {
            set_time_limit(0);
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings, ',', '"', '\\');

            $query->chunk(1000, function ($rows) use ($handle) {
                foreach ($rows as $r) {
                    fputcsv($handle, [
                        $r->voucher_number ?? 'N/A',
                        $r->voucher_date ? \Carbon\Carbon::parse($r->voucher_date)->format('d/m/Y') : 'N/A',
                        $r->vendor_name ?? '-',
                        number_format((float) $r->total_amount, 2),
                        $r->prepared_by ?? '-',
                        $r->checked_by ?? '-',
                        $r->authorized_by ?? '-',
                        $r->voucher_status ?? '-',
                        $r->is_printed ? 'Yes' : 'No',
                    ], ',', '"', '\\');
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="voucher-register-' . now()->format('Ymd') . '.csv"');

        return $response;
    }

    // ═══════════════════════════════════════════════════════════
    // 10. WITHHOLDING TAX REGISTER
    // ═══════════════════════════════════════════════════════════

    public function withholdingTax(): View
    {
        $clients = Client::landlords()
            ->select('id', 'names', 'reference_number')
            ->orderBy('names')
            ->get();

        return view('reports.accounts.withholding-tax', compact('clients'));
    }

    public function getWithholdingTax(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $clientId = $request->input('client_id');

            // Derive landlord reference_number from client id if provided
            $landlordCode = null;
            if ($clientId) {
                $landlordCode = DB::table('sys_pm_clients')
                    ->where('id', $clientId)
                    ->value('reference_number');
            }

            // Query cb_returns_summary; compute WHT as 5% of commission_amount (Kenya standard)
            $query = DB::table('cb_returns_summary as rs')
                ->select(
                    'rs.id',
                    'rs.year',
                    'rs.month',
                    'rs.landlord_code',
                    'rs.property_code',
                    'rs.commission_amount',
                    DB::raw('rs.commission_amount * 0.05 as wht_amount'),
                    DB::raw('rs.commission_amount * 0.95 as net_amount'),
                    'c.names as payee_name',
                    'c.reference_number as payee_ref'
                )
                ->leftJoin('sys_pm_clients as c', 'c.reference_number', '=', 'rs.landlord_code')
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->where(function ($sub) use ($dateFrom) {
                        $from = \Carbon\Carbon::parse($dateFrom);
                        $sub->where('rs.year', '>', $from->year)
                            ->orWhere(function ($s2) use ($from) {
                                $s2->where('rs.year', $from->year)
                                   ->where('rs.month', '>=', $from->month);
                            });
                    });
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->where(function ($sub) use ($dateTo) {
                        $to = \Carbon\Carbon::parse($dateTo);
                        $sub->where('rs.year', '<', $to->year)
                            ->orWhere(function ($s2) use ($to) {
                                $s2->where('rs.year', $to->year)
                                   ->where('rs.month', '<=', $to->month);
                            });
                    });
                })
                ->when($landlordCode, fn($q) => $q->where('rs.landlord_code', $landlordCode))
                ->orderBy('rs.year', 'desc')
                ->orderBy('rs.month', 'desc');

            $rows = $query->get();

            $monthNames = [
                1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
            ];

            $data = $rows->map(function ($r, $i) use ($monthNames) {
                $gross = (float) $r->commission_amount;
                $wht   = $gross * 0.05;
                $net   = $gross * 0.95;
                $mon   = (int) $r->month;
                return [
                    'no'           => $i + 1,
                    'period'       => ($monthNames[$mon] ?? $r->month) . ' ' . $r->year,
                    'payee_name'   => $r->payee_name ?? $r->landlord_code ?? 'N/A',
                    'payee_ref'    => $r->payee_ref ?? $r->landlord_code ?? 'N/A',
                    'gross_amount' => number_format($gross, 2),
                    'wht_rate'     => '5.00%',
                    'wht_amount'   => number_format($wht, 2),
                    'net_amount'   => number_format($net, 2),
                    'tax_category' => 'Management Fee WHT',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getWithholdingTax — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportWithholdingTax(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $clientId = $request->input('client_id');

            $landlordCode = null;
            if ($clientId) {
                $landlordCode = DB::table('sys_pm_clients')
                    ->where('id', $clientId)
                    ->value('reference_number');
            }

            $rows = DB::table('cb_returns_summary as rs')
                ->select(
                    'rs.year', 'rs.month', 'rs.landlord_code', 'rs.commission_amount',
                    'c.names as payee_name', 'c.reference_number as payee_ref'
                )
                ->leftJoin('sys_pm_clients as c', 'c.reference_number', '=', 'rs.landlord_code')
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->where(function ($sub) use ($dateFrom) {
                        $from = \Carbon\Carbon::parse($dateFrom);
                        $sub->where('rs.year', '>', $from->year)
                            ->orWhere(function ($s2) use ($from) {
                                $s2->where('rs.year', $from->year)->where('rs.month', '>=', $from->month);
                            });
                    });
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->where(function ($sub) use ($dateTo) {
                        $to = \Carbon\Carbon::parse($dateTo);
                        $sub->where('rs.year', '<', $to->year)
                            ->orWhere(function ($s2) use ($to) {
                                $s2->where('rs.year', $to->year)->where('rs.month', '<=', $to->month);
                            });
                    });
                })
                ->when($landlordCode, fn($q) => $q->where('rs.landlord_code', $landlordCode))
                ->orderBy('rs.year', 'desc')
                ->orderBy('rs.month', 'desc')
                ->get();

            $monthNames = [
                1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
            ];

            $headings = ['#', 'Period', 'Payee Name', 'Payee Ref', 'Gross Amount (KES)', 'WHT Rate %', 'WHT Amount (KES)', 'Net Amount (KES)', 'Tax Category'];
            $exportRows = $rows->map(function ($r, $i) use ($monthNames) {
                $gross = (float) $r->commission_amount;
                $mon   = (int) $r->month;
                return [
                    $i + 1,
                    ($monthNames[$mon] ?? $r->month) . ' ' . $r->year,
                    $r->payee_name ?? $r->landlord_code ?? 'N/A',
                    $r->payee_ref ?? $r->landlord_code ?? 'N/A',
                    number_format($gross, 2),
                    '5.00%',
                    number_format($gross * 0.05, 2),
                    number_format($gross * 0.95, 2),
                    'Management Fee WHT',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Withholding Tax Register',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'withholding-tax-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportWithholdingTax — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 11. APPROVAL AUDIT TRAIL
    // ═══════════════════════════════════════════════════════════

    public function approvalAudit(): View
    {
        return view('reports.accounts.approval-audit');
    }

    public function getApprovalAudit(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $actionType = $request->input('action_type');

            // Use cb_approval_items joined to cb_item_approvers as primary source
            $query = DB::table('cb_approval_items as ai')
                ->select(
                    'ai.id',
                    'ai.reference_number',
                    'ai.item_type',
                    'ai.created_at as submitted_date',
                    'ai.status as current_status',
                    'sub.name as submitted_by_name',
                    'app.action',
                    'app.action_date',
                    'app.comments',
                    'actor.name as actioned_by_name'
                )
                ->leftJoin('josya_users as sub', 'sub.id', '=', 'ai.created_by')
                ->leftJoin('cb_item_approvers as app', 'app.approval_item_id', '=', 'ai.id')
                ->leftJoin('josya_users as actor', 'actor.id', '=', 'app.user_id')
                ->when($dateFrom, fn($q) => $q->whereDate('ai.created_at', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('ai.created_at', '<=', $dateTo))
                ->when($actionType, fn($q) => $q->where('app.action', $actionType))
                ->orderBy('ai.created_at', 'desc');

            $rows = $query->get();

            $data = $rows->map(function ($r, $i) {
                return [
                    'no'             => $i + 1,
                    'reference'      => $r->reference_number ?? ('REQ-' . $r->id),
                    'type'           => $r->item_type ?? '-',
                    'submitted_by'   => $r->submitted_by_name ?? '-',
                    'submitted_date' => $r->submitted_date ? \Carbon\Carbon::parse($r->submitted_date)->format('d/m/Y H:i') : '-',
                    'action'         => $r->action ?? 'Pending',
                    'actioned_by'    => $r->actioned_by_name ?? '-',
                    'action_date'    => $r->action_date ? \Carbon\Carbon::parse($r->action_date)->format('d/m/Y H:i') : '-',
                    'comments'       => $r->comments ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getApprovalAudit — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportApprovalAudit(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $actionType = $request->input('action_type');

            $rows = DB::table('cb_approval_items as ai')
                ->select(
                    'ai.reference_number', 'ai.item_type', 'ai.created_at as submitted_date',
                    'ai.status as current_status', 'sub.name as submitted_by_name',
                    'app.action', 'app.action_date', 'app.comments', 'actor.name as actioned_by_name'
                )
                ->leftJoin('josya_users as sub', 'sub.id', '=', 'ai.created_by')
                ->leftJoin('cb_item_approvers as app', 'app.approval_item_id', '=', 'ai.id')
                ->leftJoin('josya_users as actor', 'actor.id', '=', 'app.user_id')
                ->when($dateFrom, fn($q) => $q->whereDate('ai.created_at', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('ai.created_at', '<=', $dateTo))
                ->when($actionType, fn($q) => $q->where('app.action', $actionType))
                ->orderBy('ai.created_at', 'desc')
                ->get();

            $headings = ['#', 'Reference', 'Type', 'Submitted By', 'Submitted Date', 'Action', 'Actioned By', 'Action Date', 'Comments'];
            $exportRows = $rows->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->reference_number ?? '-',
                    $r->item_type ?? '-',
                    $r->submitted_by_name ?? '-',
                    $r->submitted_date ? \Carbon\Carbon::parse($r->submitted_date)->format('d/m/Y H:i') : '-',
                    $r->action ?? 'Pending',
                    $r->actioned_by_name ?? '-',
                    $r->action_date ? \Carbon\Carbon::parse($r->action_date)->format('d/m/Y H:i') : '-',
                    $r->comments ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Approval Audit Trail',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'approval-audit-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportApprovalAudit — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 12. PAYMENT ALLOCATION DETAIL
    // ═══════════════════════════════════════════════════════════

    public function paymentAllocation(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        return view('reports.accounts.payment-allocation', compact('properties'));
    }

    public function getPaymentAllocation(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $query = DB::table('sys_pm_tenants_payments_invoices as tpi')
                ->select(
                    'tpi.id',
                    'tpi.allocated_amount',
                    'tpi.created_at as allocation_date',
                    'tp.receipt_number',
                    'tp.payment_date',
                    'tp.amount as payment_amount',
                    'di.reference_number as invoice_ref',
                    'di.date_time as invoice_date',
                    'c.names as tenant_name',
                    'p.property_name'
                )
                ->join('sys_pm_tenants_payments as tp', 'tp.id', '=', 'tpi.payment_id')
                ->join('sys_pm_debtors_invoices as di', 'di.id', '=', 'tpi.invoice_id')
                ->join('sys_pm_clients as c', 'c.id', '=', 'tp.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'tp.property_id')
                ->when($dateFrom, fn($q) => $q->whereDate('tp.payment_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('tp.payment_date', '<=', $dateTo))
                ->when($propertyId, fn($q) => $q->where('tp.property_id', $propertyId))
                ->orderBy('tp.payment_date', 'desc');

            $rows = $query->get();

            $data = $rows->map(function ($r, $i) {
                return [
                    'no'               => $i + 1,
                    'receipt_no'       => $r->receipt_number ?? 'N/A',
                    'payment_date'     => $r->payment_date ? \Carbon\Carbon::parse($r->payment_date)->format('d/m/Y') : 'N/A',
                    'tenant'           => $r->tenant_name ?? 'N/A',
                    'property'         => $r->property_name ?? 'N/A',
                    'invoice_ref'      => $r->invoice_ref ?? 'N/A',
                    'allocated_amount' => number_format((float) $r->allocated_amount, 2),
                    'allocation_date'  => $r->allocation_date ? \Carbon\Carbon::parse($r->allocation_date)->format('d/m/Y') : 'N/A',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getPaymentAllocation — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportPaymentAllocation(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $rows = DB::table('sys_pm_tenants_payments_invoices as tpi')
                ->select(
                    'tpi.allocated_amount', 'tpi.created_at as allocation_date',
                    'tp.receipt_number', 'tp.payment_date',
                    'di.reference_number as invoice_ref',
                    'c.names as tenant_name', 'p.property_name'
                )
                ->join('sys_pm_tenants_payments as tp', 'tp.id', '=', 'tpi.payment_id')
                ->join('sys_pm_debtors_invoices as di', 'di.id', '=', 'tpi.invoice_id')
                ->join('sys_pm_clients as c', 'c.id', '=', 'tp.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'tp.property_id')
                ->when($dateFrom, fn($q) => $q->whereDate('tp.payment_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('tp.payment_date', '<=', $dateTo))
                ->when($propertyId, fn($q) => $q->where('tp.property_id', $propertyId))
                ->orderBy('tp.payment_date', 'desc')
                ->get();

            $headings = ['#', 'Receipt No', 'Payment Date', 'Tenant', 'Property', 'Invoice Ref', 'Allocated Amount (KES)', 'Allocation Date'];
            $exportRows = $rows->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->receipt_number ?? 'N/A',
                    $r->payment_date ? \Carbon\Carbon::parse($r->payment_date)->format('d/m/Y') : 'N/A',
                    $r->tenant_name ?? 'N/A',
                    $r->property_name ?? 'N/A',
                    $r->invoice_ref ?? 'N/A',
                    number_format((float) $r->allocated_amount, 2),
                    $r->allocation_date ? \Carbon\Carbon::parse($r->allocation_date)->format('d/m/Y') : 'N/A',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Payment Allocation Detail',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'payment-allocation-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportPaymentAllocation — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 13. RECOVERY REGISTER
    // ═══════════════════════════════════════════════════════════

    public function recoveryRegister(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        return view('reports.accounts.recovery-register', compact('properties'));
    }

    public function getRecoveryRegister(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $query = DB::table('sys_pm_recoveries as rec')
                ->select(
                    'rec.id',
                    'rec.recovery_date',
                    'rec.narration',
                    'rec.amount',
                    'rec.is_commissionable',
                    'rec.returns_batch',
                    'c.names as tenant_name',
                    'p.property_name'
                )
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'rec.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rec.property_id')
                ->when($dateFrom,   fn($q) => $q->whereDate('rec.recovery_date', '>=', $dateFrom))
                ->when($dateTo,     fn($q) => $q->whereDate('rec.recovery_date', '<=', $dateTo))
                ->when($propertyId, fn($q) => $q->where('rec.property_id', $propertyId))
                ->orderBy('rec.recovery_date', 'desc');

            $rows = $query->get();

            $data = $rows->map(function ($r, $i) {
                return [
                    'no'              => $i + 1,
                    'recovery_date'   => $r->recovery_date ? \Carbon\Carbon::parse($r->recovery_date)->format('d/m/Y') : 'N/A',
                    'tenant'          => $r->tenant_name ?? 'N/A',
                    'property'        => $r->property_name ?? 'N/A',
                    'narration'       => $r->narration ?? '-',
                    'amount'          => number_format((float) $r->amount, 2),
                    'commissionable'  => $r->is_commissionable ? 'Yes' : 'No',
                    'returns_batch'   => $r->returns_batch ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getRecoveryRegister — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportRecoveryRegister(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $rows = DB::table('sys_pm_recoveries as rec')
                ->select(
                    'rec.recovery_date', 'rec.narration', 'rec.amount',
                    'rec.is_commissionable', 'rec.returns_batch',
                    'c.names as tenant_name', 'p.property_name'
                )
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'rec.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rec.property_id')
                ->when($dateFrom,   fn($q) => $q->whereDate('rec.recovery_date', '>=', $dateFrom))
                ->when($dateTo,     fn($q) => $q->whereDate('rec.recovery_date', '<=', $dateTo))
                ->when($propertyId, fn($q) => $q->where('rec.property_id', $propertyId))
                ->orderBy('rec.recovery_date', 'desc')
                ->get();

            $headings = ['#', 'Recovery Date', 'Tenant', 'Property', 'Narration', 'Amount (KES)', 'Commissionable', 'Returns Batch'];
            $exportRows = $rows->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->recovery_date ? \Carbon\Carbon::parse($r->recovery_date)->format('d/m/Y') : 'N/A',
                    $r->tenant_name ?? 'N/A',
                    $r->property_name ?? 'N/A',
                    $r->narration ?? '-',
                    number_format((float) $r->amount, 2),
                    $r->is_commissionable ? 'Yes' : 'No',
                    $r->returns_batch ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Recovery Register',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'recovery-register-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportRecoveryRegister — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 14. LANDLORD DISBURSEMENT SCHEDULE
    // ═══════════════════════════════════════════════════════════

    public function landlordDisbursement(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        $landlords = Client::landlords()
            ->select('id', 'names', 'reference_number')
            ->orderBy('names')
            ->get();

        return view('reports.accounts.landlord-disbursement', compact('properties', 'landlords'));
    }

    public function getLandlordDisbursement(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $clientId   = $request->input('client_id');
            $propertyId = $request->input('property_id');

            $query = DB::table('cb_landlord_account as la')
                ->select(
                    'la.id',
                    'la.entry_date',
                    'la.entry_type',
                    'la.amount',
                    'la.pv_number',
                    'la.payment_status',
                    'la.narration',
                    'c.names as landlord_name',
                    'c.reference_number as landlord_ref',
                    'p.property_name'
                )
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'la.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->when($dateFrom,   fn($q) => $q->whereDate('la.entry_date', '>=', $dateFrom))
                ->when($dateTo,     fn($q) => $q->whereDate('la.entry_date', '<=', $dateTo))
                ->when($clientId,   fn($q) => $q->where('la.client_id', $clientId))
                ->when($propertyId, fn($q) => $q->where('la.property_id', $propertyId))
                ->orderBy('la.entry_date', 'desc');

            $rows = $query->get();

            $data = $rows->map(function ($r, $i) {
                return [
                    'no'             => $i + 1,
                    'landlord_ref'   => $r->landlord_ref ?? 'N/A',
                    'landlord_name'  => $r->landlord_name ?? 'N/A',
                    'property'       => $r->property_name ?? 'N/A',
                    'entry_date'     => $r->entry_date ? \Carbon\Carbon::parse($r->entry_date)->format('d/m/Y') : 'N/A',
                    'entry_type'     => $r->entry_type ?? '-',
                    'amount'         => number_format((float) $r->amount, 2),
                    'pv_number'      => $r->pv_number ?? '-',
                    'payment_status' => $r->payment_status ?? '-',
                    'narration'      => $r->narration ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getLandlordDisbursement — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportLandlordDisbursement(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $clientId   = $request->input('client_id');
            $propertyId = $request->input('property_id');

            $rows = DB::table('cb_landlord_account as la')
                ->select(
                    'la.entry_date', 'la.entry_type', 'la.amount', 'la.pv_number',
                    'la.payment_status', 'la.narration',
                    'c.names as landlord_name', 'c.reference_number as landlord_ref',
                    'p.property_name'
                )
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'la.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->when($dateFrom,   fn($q) => $q->whereDate('la.entry_date', '>=', $dateFrom))
                ->when($dateTo,     fn($q) => $q->whereDate('la.entry_date', '<=', $dateTo))
                ->when($clientId,   fn($q) => $q->where('la.client_id', $clientId))
                ->when($propertyId, fn($q) => $q->where('la.property_id', $propertyId))
                ->orderBy('la.entry_date', 'desc')
                ->get();

            $headings = ['#', 'Landlord Ref', 'Landlord Name', 'Property', 'Entry Date', 'Entry Type', 'Amount (KES)', 'PV Number', 'Payment Status', 'Narration'];
            $exportRows = $rows->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->landlord_ref ?? 'N/A',
                    $r->landlord_name ?? 'N/A',
                    $r->property_name ?? 'N/A',
                    $r->entry_date ? \Carbon\Carbon::parse($r->entry_date)->format('d/m/Y') : 'N/A',
                    $r->entry_type ?? '-',
                    number_format((float) $r->amount, 2),
                    $r->pv_number ?? '-',
                    $r->payment_status ?? '-',
                    $r->narration ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Landlord Disbursement Schedule',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'landlord-disbursement-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportLandlordDisbursement — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 15. RETURNS PROCESSING STATUS
    // ═══════════════════════════════════════════════════════════

    public function returnsStatus(): View
    {
        return view('reports.accounts.returns-status');
    }

    public function getReturnsStatus(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $status   = $request->input('status');

            $query = DB::table('cb_returns_summary as rs')
                ->select(
                    'rs.id',
                    'rs.reference_number',
                    'rs.landlord_code',
                    'rs.property_code',
                    'rs.year',
                    'rs.month',
                    'rs.total_credit as gross_rent',
                    'rs.commission_amount as commission',
                    'rs.vat_amount as vat',
                    'rs.net_amount',
                    'rs.status',
                    'rs.batch_reference',
                    'rs.submitted_date',
                    'rs.approved_date',
                    'c.names as landlord_name'
                )
                ->leftJoin('sys_pm_clients as c', 'c.reference_number', '=', 'rs.landlord_code')
                ->when($dateFrom, fn($q) => $q->whereDate('rs.submitted_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('rs.submitted_date', '<=', $dateTo))
                ->when($status,   fn($q) => $q->where('rs.status', $status))
                ->orderBy('rs.submitted_date', 'desc');

            $rows = $query->get();

            $monthNames = [
                1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
            ];

            $data = $rows->map(function ($r, $i) use ($monthNames) {
                $mon = (int) $r->month;
                return [
                    'no'             => $i + 1,
                    'returns_ref'    => $r->reference_number ?? ('RS-' . $r->id),
                    'landlord'       => $r->landlord_name ?? $r->landlord_code ?? 'N/A',
                    'property'       => $r->property_code ?? 'N/A',
                    'period'         => ($monthNames[$mon] ?? $r->month) . ' ' . $r->year,
                    'gross_rent'     => number_format((float) $r->gross_rent, 2),
                    'commission'     => number_format((float) $r->commission, 2),
                    'vat'            => number_format((float) $r->vat, 2),
                    'net_amount'     => number_format((float) $r->net_amount, 2),
                    'status'         => $r->status ?? 'Pending',
                    'batch_ref'      => $r->batch_reference ?? '-',
                    'submitted_date' => $r->submitted_date ? \Carbon\Carbon::parse($r->submitted_date)->format('d/m/Y') : '-',
                    'approved_date'  => $r->approved_date ? \Carbon\Carbon::parse($r->approved_date)->format('d/m/Y') : '-',
                ];
            });

            // Chart: grouped bar — count by status and month
            $allMonths = $rows->map(fn($r) => $r->submitted_date
                ? \Carbon\Carbon::parse($r->submitted_date)->format('M Y') : null)
                ->filter()->unique()->values();

            $byStatus  = $rows->groupBy('status');
            $datasets  = $byStatus->map(function ($items, $statusLabel) use ($allMonths) {
                $byMonth = $items->groupBy(fn($r) => $r->submitted_date
                    ? \Carbon\Carbon::parse($r->submitted_date)->format('M Y') : 'Unknown');
                return [
                    'label' => $statusLabel ?? 'Unknown',
                    'data'  => $allMonths->map(fn($m) => $byMonth->get($m, collect())->count())->values()->toArray(),
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'chart'   => [
                    'labels'   => $allMonths->toArray(),
                    'datasets' => $datasets,
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getReturnsStatus — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportReturnsStatus(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $status   = $request->input('status');

            $rows = DB::table('cb_returns_summary as rs')
                ->select(
                    'rs.reference_number', 'rs.landlord_code', 'rs.property_code',
                    'rs.year', 'rs.month', 'rs.total_credit as gross_rent',
                    'rs.commission_amount as commission', 'rs.vat_amount as vat',
                    'rs.net_amount', 'rs.status', 'rs.batch_reference',
                    'rs.submitted_date', 'rs.approved_date',
                    'c.names as landlord_name'
                )
                ->leftJoin('sys_pm_clients as c', 'c.reference_number', '=', 'rs.landlord_code')
                ->when($dateFrom, fn($q) => $q->whereDate('rs.submitted_date', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('rs.submitted_date', '<=', $dateTo))
                ->when($status,   fn($q) => $q->where('rs.status', $status))
                ->orderBy('rs.submitted_date', 'desc')
                ->get();

            $monthNames = [
                1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
            ];

            $headings = ['#', 'Returns Ref', 'Landlord', 'Property', 'Period', 'Gross Rent (KES)', 'Commission (KES)', 'VAT (KES)', 'Net Amount (KES)', 'Status', 'Batch Ref', 'Submitted Date', 'Approved Date'];
            $exportRows = $rows->map(function ($r, $i) use ($monthNames) {
                $mon = (int) $r->month;
                return [
                    $i + 1,
                    $r->reference_number ?? '-',
                    $r->landlord_name ?? $r->landlord_code ?? 'N/A',
                    $r->property_code ?? 'N/A',
                    ($monthNames[$mon] ?? $r->month) . ' ' . $r->year,
                    number_format((float) $r->gross_rent, 2),
                    number_format((float) $r->commission, 2),
                    number_format((float) $r->vat, 2),
                    number_format((float) $r->net_amount, 2),
                    $r->status ?? 'Pending',
                    $r->batch_reference ?? '-',
                    $r->submitted_date ? \Carbon\Carbon::parse($r->submitted_date)->format('d/m/Y') : '-',
                    $r->approved_date ? \Carbon\Carbon::parse($r->approved_date)->format('d/m/Y') : '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Returns Processing Status',
                    $headings,
                    $exportRows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'returns-status-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportReturnsStatus — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 7. DEPOSIT REFUNDS
    // ═══════════════════════════════════════════════════════════

    public function depositRefunds(): View
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')
            ->get();

        return view('reports.accounts.deposit-refunds', compact('properties'));
    }

    public function getDepositRefunds(Request $request): JsonResponse
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $query = TenantExit::with(['tenant', 'property', 'unit'])
                ->completed()
                ->orderBy('actual_exit_date', 'desc');

            if ($dateFrom) { $query->whereDate('actual_exit_date', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('actual_exit_date', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }

            $exits = $query->get();

            // Bulk load refunds and deposits keyed by tenant_id
            $exitIds = $exits->pluck('id');
            $tenantIds = $exits->pluck('tenant_id');
            $propertyIds = $exits->pluck('property_id');

            $refunds = DB::table('cb_tenant_exit_deposit_refunds')
                ->whereIn('exit_id', $exitIds)
                ->selectRaw('exit_id, SUM(refund_amount) as total_refunded, MAX(refunded_at) as refunded_at')
                ->groupBy('exit_id')
                ->get()
                ->keyBy('exit_id');

            $deposits = DB::table('sys_pm_deposits')
                ->whereIn('tenant_id', $tenantIds)
                ->selectRaw('tenant_id, property_id, SUM(amount) as total_deposit')
                ->groupBy('tenant_id', 'property_id')
                ->get();

            $depositMap = $deposits->mapWithKeys(
                fn($d) => [$d->tenant_id . '_' . $d->property_id => $d->total_deposit]
            );

            $data = $exits->map(function ($exit, $index) use ($refunds, $depositMap) {
                $refundRow      = $refunds->get($exit->id);
                $depositKey     = $exit->tenant_id . '_' . $exit->property_id;
                $originalDeposit = $depositMap->get($depositKey, 0);
                $refunded        = $refundRow?->total_refunded ?? 0;
                $refundDate      = $refundRow?->refunded_at
                    ? Carbon::parse($refundRow->refunded_at)->format('d/m/Y')
                    : '-';

                return [
                    'no'               => $index + 1,
                    'exit_date'        => $exit->actual_exit_date ? $exit->actual_exit_date->format('d/m/Y') : 'N/A',
                    'tenant_name'      => $exit->tenant?->names ?? 'N/A',
                    'tenant_ref'       => $exit->tenant?->reference_number ?? 'N/A',
                    'property'         => $exit->property?->property_name ?? 'N/A',
                    'unit'             => $exit->unit?->unit_code ?? 'N/A',
                    'original_deposit' => number_format((float) $originalDeposit, 2),
                    'refunded'         => number_format((float) $refunded, 2),
                    'refund_date'      => $refundDate,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::getDepositRefunds — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportDepositRefunds(Request $request)
    {
        try {
            $dateFrom   = $request->input('date_from');
            $dateTo     = $request->input('date_to');
            $propertyId = $request->input('property_id');

            $query = TenantExit::with(['tenant', 'property', 'unit'])
                ->completed()
                ->orderBy('actual_exit_date', 'desc');

            if ($dateFrom) { $query->whereDate('actual_exit_date', '>=', $dateFrom); }
            if ($dateTo)   { $query->whereDate('actual_exit_date', '<=', $dateTo); }
            if ($propertyId) { $query->where('property_id', $propertyId); }

            $exits = $query->get();
            $exitIds   = $exits->pluck('id');
            $tenantIds = $exits->pluck('tenant_id');

            $refunds = DB::table('cb_tenant_exit_deposit_refunds')
                ->whereIn('exit_id', $exitIds)
                ->selectRaw('exit_id, SUM(refund_amount) as total_refunded, MAX(refunded_at) as refunded_at')
                ->groupBy('exit_id')
                ->get()
                ->keyBy('exit_id');

            $deposits = DB::table('sys_pm_deposits')
                ->whereIn('tenant_id', $tenantIds)
                ->selectRaw('tenant_id, property_id, SUM(amount) as total_deposit')
                ->groupBy('tenant_id', 'property_id')
                ->get();

            $depositMap = $deposits->mapWithKeys(
                fn($d) => [$d->tenant_id . '_' . $d->property_id => $d->total_deposit]
            );

            $headings = ['#', 'Exit Date', 'Tenant Name', 'Tenant Ref', 'Property', 'Unit', 'Original Deposit (KES)', 'Refunded (KES)', 'Refund Date'];
            $rows = $exits->map(function ($exit, $i) use ($refunds, $depositMap) {
                $refundRow       = $refunds->get($exit->id);
                $depositKey      = $exit->tenant_id . '_' . $exit->property_id;
                $originalDeposit = $depositMap->get($depositKey, 0);
                $refunded        = $refundRow?->total_refunded ?? 0;
                $refundDate      = $refundRow?->refunded_at
                    ? Carbon::parse($refundRow->refunded_at)->format('d/m/Y')
                    : '-';
                return [
                    $i + 1,
                    $exit->actual_exit_date ? $exit->actual_exit_date->format('d/m/Y') : 'N/A',
                    $exit->tenant?->names ?? 'N/A',
                    $exit->tenant?->reference_number ?? 'N/A',
                    $exit->property?->property_name ?? 'N/A',
                    $exit->unit?->unit_code ?? 'N/A',
                    number_format((float) $originalDeposit, 2),
                    number_format((float) $refunded, 2),
                    $refundDate,
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    'Deposit Refunds Report',
                    $headings,
                    $rows,
                    ['Period: ' . ($dateFrom ?? 'All') . ' to ' . ($dateTo ?? 'All'), 'Generated: ' . now()->format('d/m/Y H:i:s')]
                ),
                'deposit-refunds-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('AccountsReportsController::exportDepositRefunds — ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}

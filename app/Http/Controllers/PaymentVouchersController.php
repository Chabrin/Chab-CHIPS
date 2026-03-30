<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;

class PaymentVouchersController extends Controller
{
    /**
     * Display the payment vouchers listing page.
     */
    public function index()
    {
        try {
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('m');
            $years = range($currentYear, $currentYear - 4);

            return view('finance.vouchers.index', compact('currentYear', 'currentMonth', 'years'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading vouchers page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load payment vouchers page');
        }
    }

    /**
     * Get vouchers data for DataTable (AJAX).
     */
    public function getVouchersData(Request $request)
    {
        try {
            $year  = $request->input('year', date('Y'));
            $month = $request->input('month', date('m'));

            $vouchers = PaymentVoucherHeader::where('year', $year)
                ->where('month', $month)
                ->where('is_deleted', 0)
                ->orderByDesc('voucher_sequence')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $vouchers->count(),
                'data'    => $vouchers->map(function ($v) {
                    return [
                        'id'                => $v->id,
                        'encrypted_id'      => encrypt($v->id),
                        'voucher_number'    => $v->voucher_number,
                        'voucher_date'      => $v->voucher_date?->format('d/m/Y') ?? '-',
                        'description'       => $v->description ?? '-',
                        'vendor_id'         => $v->vendor_id ?? '-',
                        'vendor_name'       => $v->vendor_name ?? '-',
                        'debit_amount'      => number_format($v->debit_amount ?? 0, 2),
                        'entry_count'       => $v->entry_count ?? 0,
                        'voucher_status'    => $v->voucher_status ?? 'Pending',
                        'prepared_by'       => $v->prepared_by ?? '-',
                    ];
                }),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching vouchers data: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch vouchers data'], 500);
        }
    }

    /**
     * Show voucher details page.
     */
    public function show($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            return view('finance.vouchers.details', compact('voucher'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading voucher details: ' . $e->getMessage());
            return redirect()->route('payment.vouchers.index')->with('error', 'Failed to load voucher details');
        }
    }

    /**
     * Get voucher lines data (AJAX).
     */
    public function getVoucherLines($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            $lines = PaymentVoucherLine::where('voucher_number', $voucher->voucher_number)
                ->orderBy('entry_number')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $lines->count(),
                'header'  => [
                    'voucher_number'  => $voucher->voucher_number,
                    'voucher_date'    => $voucher->voucher_date?->format('d/m/Y') ?? '-',
                    'description'     => $voucher->description,
                    'vendor_id'       => $voucher->vendor_id,
                    'vendor_name'     => $voucher->vendor_name,
                    'debit_amount'    => number_format($voucher->debit_amount ?? 0, 2),
                    'credit_amount'   => number_format($voucher->credit_amount ?? 0, 2),
                    'voucher_status'  => $voucher->voucher_status,
                    'prepared_by'     => $voucher->prepared_by,
                    'checked_by'      => $voucher->checked_by,
                    'authorized_by'   => $voucher->authorized_by,
                ],
                'data'    => $lines->map(function ($l) {
                    return [
                        'entry_number'         => $l->entry_number,
                        'property_code'        => $l->property_code ?? '-',
                        'property_description' => $l->property_description ?? '-',
                        'entry_description'    => $l->entry_description ?? '-',
                        'dr_cr'                => $l->dr_cr ?? 'DR',
                        'amount'               => number_format($l->amount ?? 0, 2),
                        'gl_account'           => $l->gl_account ?? '-',
                        'payee_name'           => $l->payee_name ?? '-',
                        'is_approved'          => $l->is_approved ?? '-',
                        'cheque_number'        => $l->cheque_number ?? '-',
                    ];
                }),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching voucher lines: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch voucher lines'], 500);
        }
    }
}

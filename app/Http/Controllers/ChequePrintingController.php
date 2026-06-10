<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use App\Services\ApprovalService;
use App\Services\ErrorDispatcher;

class ChequePrintingController extends Controller
{
    /**
     * List all unprinted vouchers pending cheque printing.
     */
    public function index()
    {
        try {
            return view('finance.cheques.printing');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading cheque printing page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load cheque printing page');
        }
    }

    /**
     * AJAX: fetch unprinted vouchers for DataTable.
     */
    public function getData()
    {
        try {
            $cutoff = now()->subDays(3)->startOfDay();

            $vouchers = PaymentVoucherHeader::where('is_printed', 0)
                ->where('is_deleted', 0)
                ->where('voucher_status', 'Pending')
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('id')
                ->get();

            $data = $vouchers->map(function ($v) {
                return [
                    'id'             => $v->id,
                    'encrypted_id'   => encrypt($v->id),
                    'voucher_number' => $v->voucher_number,
                    'voucher_date'   => $v->voucher_date?->format('d/m/Y') ?? '-',
                    'vendor_name'    => $v->vendor_name ?? '-',
                    'vendor_type'    => $v->vendor_type ?? '-',
                    'debit_amount'   => number_format($v->debit_amount ?? 0, 2),
                    'cheque_number'  => $v->cheque_number ?? '-',
                    'prepared_by'    => $v->prepared_by ?? '-',
                    'is_printed'     => $v->is_printed,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching unprinted vouchers: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch data'], 500);
        }
    }

    /**
     * Show cheque printing details for a single voucher.
     */
    public function show(string $encryptedId)
    {
        try {
            $id      = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);
            $lines   = PaymentVoucherLine::where('voucher_number', $voucher->voucher_number)
                ->orderBy('id')
                ->get();

            $approvalRequest = $voucher->approvalRequest();

            return view('finance.cheques.printing-details', compact('voucher', 'lines', 'approvalRequest'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading cheque printing details: ' . $e->getMessage());
            return redirect()->route('cheque.printing.index')->with('error', 'Failed to load voucher details');
        }
    }

    /**
     * Mark voucher as printed and optionally send to approval.
     */
    public function markPrinted(Request $request, string $encryptedId)
    {
        try {
            $id      = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            $sendToApproval = (bool) $request->input('send_to_approval', false);

            $voucher->update(['is_printed' => 1]);

            $approvalInitiated = false;
            if ($sendToApproval) {
                $approvalInitiated = $this->initiateApproval($voucher);
            }

            return response()->json([
                'success'           => true,
                'message'           => 'Voucher marked as printed' . ($approvalInitiated ? ' and sent for approval' : ''),
                'approval_initiated' => $approvalInitiated,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error marking voucher as printed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update voucher'], 500);
        }
    }

    /**
     * Submit voucher to approval workflow regardless of print status.
     */
    public function submitToApproval(string $encryptedId)
    {
        try {
            $id      = decrypt($encryptedId);
            $voucher = PaymentVoucherHeader::findOrFail($id);

            $existing = $voucher->approvalRequest();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This voucher is already in the approval workflow',
                ], 422);
            }

            $initiated = $this->initiateApproval($voucher);

            if (!$initiated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not initiate approval workflow. Please check workflow configuration.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Voucher submitted for approval successfully',
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error submitting voucher to approval: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to submit for approval'], 500);
        }
    }

    private function initiateApproval(PaymentVoucherHeader $voucher): bool
    {
        try {
            $approvalService = app(ApprovalService::class);
            $approvalService->initiateApproval(
                'voucher',
                $voucher->id,
                'voucher_approval',
                Auth::id()
            );
            return true;
        } catch (Exception $e) {
            Log::warning('Could not initiate approval workflow for voucher ' . $voucher->voucher_number . ': ' . $e->getMessage());
            return false;
        }
    }
}

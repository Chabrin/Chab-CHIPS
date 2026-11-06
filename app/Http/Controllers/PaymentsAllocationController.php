<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;
use App\Services\PaymentAllocationService;
use App\Services\ReturnThresholdService;

class PaymentsAllocationController extends Controller
{
    protected $allocationService;
    protected $thresholdService;

    public function __construct(
        PaymentAllocationService $allocationService,
        ReturnThresholdService $thresholdService
    ) {
        $this->allocationService = $allocationService;
        $this->thresholdService = $thresholdService;
    }

    /**
     * Show payment allocations page
     */
    public function showPaymentAllocations()
    {
        try {
            return view('property.payments.payment-allocations');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading payment allocations page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load payment allocations page');
        }
    }

    /**
     * Build the JSON answer for a batch run. A run where every payment failed is an error, not a success, and a run
     * with some failures says how many, so the screen never reports success over rolled-back payments.
     *
     * @param array $results the counters from PaymentAllocationService
     * @param string $okMessage the message for a clean run
     */
    private function batchResponse(array $results, string $okMessage) {
        $failed = count($results['errors'] ?? []);

        if ($failed > 0 && ($results['payments_processed'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No payment could be allocated: ' . ($results['errors'][0]['error'] ?? 'unknown error'), 'results' => $results], 422);
        }

        return response()->json(['success' => true, 'message' => $failed > 0 ? $okMessage . " ({$failed} payment(s) failed and were left unallocated)" : $okMessage, 'results' => $results]);
    }

    /**
     * Process payments with optional filters
     */
    public function processPayments(Request $request)
    {
        try {
            $unitId = $request->input('unit_id');
            $clientId = $request->input('client_id');
            $leaseId = $request->input('lease_id');
            $results = $this->allocationService->processPayments($unitId, $clientId, $leaseId);
            return $this->batchResponse($results, 'Payment allocation completed successfully');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error processing payments: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function processAllPayments()
    {
        try {
            $results = $this->allocationService->processAllPayments();

            return $this->batchResponse($results, 'All unallocated payments processed successfully');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error processing all payments: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process all payments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function processLeasePayments(Request $request)
    {
        try {
            $request->validate([
                'lease_id' => 'required|integer|exists:sys_pm_lease_agreements_27_repeat,id'
            ]);

            $results = $this->allocationService->processPayments(null, null, $request->lease_id);

            return $this->batchResponse($results, "Processed payments for lease {$request->lease_id}");
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error processing lease payments: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process lease payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual allocation of specific payment to specific invoice
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function manualAllocate(Request $request)
    {
        try {
            $request->validate([
                'payment_id' => 'required|integer|exists:sys_pm_tenants_payments,id',
                'invoice_id' => 'required|integer|exists:sys_pm_debtors_invoices,id'
            ]);

            $result = $this->allocationService->manualAllocate(
                $request->payment_id,
                $request->invoice_id
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment allocated successfully',
                    'allocation' => $result['result']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation failed - payment may be exhausted or invoice already paid'
                ], 422);
            }
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in manual allocation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get allocation history for a payment
     * 
     * @param int $paymentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentAllocations($paymentId)
    {
        try {
            $allocations = $this->allocationService->getPaymentAllocations($paymentId);

            return response()->json([
                'success' => true,
                'payment_id' => $paymentId,
                'allocations' => $allocations
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching payment allocations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allocations'
            ], 500);
        }
    }

    /**
     * Get allocation history for an invoice
     * 
     * @param int $invoiceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInvoiceAllocations($invoiceId)
    {
        try {
            $allocations = $this->allocationService->getInvoiceAllocations($invoiceId);

            return response()->json([
                'success' => true,
                'invoice_id' => $invoiceId,
                'allocations' => $allocations
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching invoice allocations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allocations'
            ], 500);
        }
    }

    /**
     * Update return threshold for a specific invoice
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateReturnThreshold(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|integer|exists:sys_pm_debtors_invoices,id'
            ]);

            $result = $this->thresholdService->updateSingleReturn($request->invoice_id);

            return response()->json([
                'success' => true,
                'message' => 'Return threshold updated successfully',
                'result' => $result
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating return threshold: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update return threshold: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update all returns for a specific month (cron job endpoint)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMonthlyReturns(Request $request)
    {
        try {
            $request->validate([
                'return_month' => 'nullable|date_format:Y-m-d',
                'client_id' => 'nullable|integer|exists:sys_pm_clients,id',
                'property_id' => 'nullable|integer|exists:sys_pm_properties,id'
            ]);

            $results = $this->thresholdService->updateMonthlyReturns(
                $request->return_month,
                $request->client_id,
                $request->property_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Monthly returns updated successfully',
                'results' => $results
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating monthly returns: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update monthly returns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get returns summary for a period
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReturnsSummary(Request $request)
    {
        try {
            $request->validate([
                'return_month' => 'required|date_format:Y-m-d',
                'client_id' => 'nullable|integer',
                'property_id' => 'nullable|integer'
            ]);

            $summary = $this->thresholdService->getReturnsSummary(
                $request->return_month,
                $request->client_id,
                $request->property_id
            );

            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching returns summary: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch returns summary'
            ], 500);
        }
    }

    /**
     * Get threshold breakdown by item type
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getThresholdBreakdown(Request $request)
    {
        try {
            $request->validate([
                'return_month' => 'required|date_format:Y-m-d',
                'client_id' => 'nullable|integer',
                'property_id' => 'nullable|integer'
            ]);

            $breakdown = $this->thresholdService->getThresholdBreakdown(
                $request->return_month,
                $request->client_id,
                $request->property_id
            );

            return response()->json([
                'success' => true,
                'breakdown' => $breakdown
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching threshold breakdown: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch threshold breakdown'
            ], 500);
        }
    }

    /**
     * Get returns with low threshold (need attention)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLowThresholdReturns(Request $request)
    {
        try {
            $request->validate([
                'return_month' => 'required|date_format:Y-m-d',
                'threshold_limit' => 'nullable|numeric|min:0|max:100',
                'client_id' => 'nullable|integer',
                'property_id' => 'nullable|integer'
            ]);

            $returns = $this->thresholdService->getLowThresholdReturns(
                $request->return_month,
                $request->threshold_limit ?? 85,
                $request->client_id,
                $request->property_id
            );

            return response()->json([
                'success' => true,
                'count' => $returns->count(),
                'returns' => $returns
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching low threshold returns: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low threshold returns'
            ], 500);
        }
    }

    /**
     * Check payability status for a specific return
     * 
     * @param int $returnId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkReturnPayability($returnId)
    {
        try {
            $details = $this->thresholdService->checkReturnPayability($returnId);

            return response()->json([
                'success' => true,
                'details' => $details
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error checking return payability: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to check return payability'
            ], 500);
        }
    }

    /**
     * Recalculate thresholds for specific invoices
     * Useful for data corrections
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recalculateThresholds(Request $request)
    {
        try {
            $request->validate([
                'invoice_ids' => 'required|array',
                'invoice_ids.*' => 'integer|exists:sys_pm_debtors_invoices,id'
            ]);

            $results = $this->thresholdService->recalculateThresholds($request->invoice_ids);

            return response()->json([
                'success' => true,
                'message' => 'Thresholds recalculated successfully',
                'results' => $results
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error recalculating thresholds: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate thresholds: ' . $e->getMessage()
            ], 500);
        }
    }
}

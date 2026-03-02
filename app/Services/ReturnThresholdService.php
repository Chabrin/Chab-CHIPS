<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\ReturnThresholdTrait;

class ReturnThresholdService
{
    use ReturnThresholdTrait;

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * BACKEND SERVICE: Update ALL returns for entire system (no filters)
     * ═══════════════════════════════════════════════════════════════════════════
     * 
     * Processes ALL returns system-wide for the current month
     * 
     * @return array Results summary
     */
    public function updateAllReturns()
    {
        Log::info("╔════════════════════════════════════════════════════════════════╗");
        Log::info("║   STARTING FULL RETURNS UPDATE - ALL RETURNS CURRENT MONTH    ║");
        Log::info("╚════════════════════════════════════════════════════════════════╝");

        return $this->updateMonthlyReturns(null, null, null);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * BACKEND SERVICE: Update ALL returns for a month (batch processing)
     * ═══════════════════════════════════════════════════════════════════════════
     * 
     * This recalculates thresholds and payable status for ALL returns in a period.
     * 
     * Use this when:
     * - Monthly cron job to ensure all returns are up-to-date
     * - Data corrections or imports
     * - Manual recalculation needed
     * 
     * NOTE: Normally returns are updated AUTOMATICALLY during payment allocation.
     * This is a backup/manual method for batch processing.
     * 
     * @param string|null $returnMonth Format: YYYY-MM-01 or null for current month
     * @param int|null $clientId Filter by client/landlord (null = ALL clients)
     * @param int|null $propertyId Filter by property (null = ALL properties)
     * @return array Results summary
     */
    public function updateMonthlyReturns($returnMonth = null, $clientId = null, $propertyId = null)
    {
        $returnMonth = $returnMonth ?? date('Y-m-01');

        $results = [
            'month' => $returnMonth,
            'total_returns' => 0,
            'updated' => 0,
            'failed' => 0,
            'payable_count' => 0,
            'unpayable_count' => 0,
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            // Get all returns for the period
            $query = DB::table('sys_pm_clients_returns as cr')
                ->join('sys_pm_debtors_invoices as i', 'cr.invoice_id', '=', 'i.id')
                ->where('cr.return_date', $returnMonth);

            if ($clientId) {
                $query->where('cr.client_id', $clientId);
            }

            if ($propertyId) {
                $query->where('cr.property_id', $propertyId);
            }

            $returns = $query->select([
                'cr.*',
                'i.total_amount',
                'i.current_balance',
                'i.current_status_id',
                'i.item_type_id',
                'i.lease_id',
                'i.date_due'
            ])->get();

            $results['total_returns'] = $returns->count();

            foreach ($returns as $return) {
                try {
                    if ($this->executeThresholdUpdate($return->invoice_id, null)) {
                        $results['updated']++;

                        // Get updated payable status
                        $updatedReturn = $this->getReturnByInvoiceId($return->invoice_id);
                        if ($updatedReturn && $updatedReturn->payable_status_id == 1) {
                            $results['payable_count']++;
                        } else {
                            $results['unpayable_count']++;
                        }
                    } else {
                        $results['failed']++;
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'invoice_id' => $return->invoice_id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            Log::info("Monthly returns update completed", $results);
            return $results;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error updating monthly returns: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update single return threshold by invoice ID
     * 
     * @param int $invoiceId
     * @return array Result with details
     */
    public function updateSingleReturn($invoiceId)
    {
        try {
            $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $invoiceId)->first();

            if (!$invoice) {
                throw new Exception("Invoice not found: {$invoiceId}");
            }

            $return = $this->getReturnByInvoiceId($invoiceId);

            if (!$return) {
                throw new Exception("Return not found for invoice: {$invoiceId}");
            }

            $success = $this->executeThresholdUpdate($invoiceId, null);

            // Get updated values
            $updatedReturn = $this->getReturnByInvoiceId($invoiceId);

            return [
                'success' => $success,
                'invoice_id' => $invoiceId,
                'threshold' => $updatedReturn->threshold_percentage ?? 0,
                'payable_status_id' => $updatedReturn->payable_status_id ?? 0,
                'invoice_status' => $invoice->current_status_id,
                'current_balance' => $invoice->current_balance
            ];
        } catch (Exception $e) {
            Log::error("Error updating single return: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get returns summary for a property or client
     * Shows payability breakdown
     * 
     * @param string $returnMonth Format: YYYY-MM-01
     * @param int|null $clientId
     * @param int|null $propertyId
     * @return array Summary data
     */
    public function getReturnsSummary($returnMonth, $clientId = null, $propertyId = null)
    {
        $query = DB::table('sys_pm_clients_returns as cr')
            ->where('cr.return_date', $returnMonth);

        if ($clientId) {
            $query->where('cr.client_id', $clientId);
        }

        if ($propertyId) {
            $query->where('cr.property_id', $propertyId);
        }

        $summary = $query->selectRaw('
            COUNT(*) as total_returns,
            SUM(CASE WHEN payable_status_id = 1 THEN 1 ELSE 0 END) as payable_count,
            SUM(CASE WHEN payable_status_id = 0 THEN 1 ELSE 0 END) as unpayable_count,
            SUM(CASE WHEN payable_status_id = 2 THEN 1 ELSE 0 END) as no_past_count,
            SUM(CASE WHEN payable_status_id = 3 THEN 1 ELSE 0 END) as past_passed_count,
            SUM(CASE WHEN payable_status_id IN (1, 3) THEN payable_amount ELSE 0 END) as total_payable_amount,
            SUM(CASE WHEN payable_status_id = 0 THEN payable_amount ELSE 0 END) as total_unpayable_amount,
            AVG(threshold_percentage) as avg_threshold
        ')->first();

        return [
            'month' => $returnMonth,
            'total_returns' => $summary->total_returns,
            'payable' => [
                'count' => $summary->payable_count + $summary->past_passed_count,
                'amount' => $summary->total_payable_amount,
                'breakdown' => [
                    'fully_paid' => $summary->payable_count,
                    'past_passed_threshold' => $summary->past_passed_count
                ]
            ],
            'unpayable' => [
                'count' => $summary->unpayable_count,
                'amount' => $summary->total_unpayable_amount
            ],
            'no_past_invoice' => $summary->no_past_count,
            'average_threshold' => round($summary->avg_threshold, 2)
        ];
    }

    /**
     * Get detailed threshold breakdown by item type
     * 
     * @param string $returnMonth
     * @param int|null $clientId
     * @param int|null $propertyId
     * @return \Illuminate\Support\Collection
     */
    public function getThresholdBreakdown($returnMonth, $clientId = null, $propertyId = null)
    {
        $query = DB::table('sys_pm_clients_returns as cr')
            ->join('sys_set_invoiceable_items as si', 'cr.trans_type_id', '=', 'si.id')
            ->where('cr.return_date', $returnMonth);

        if ($clientId) {
            $query->where('cr.client_id', $clientId);
        }

        if ($propertyId) {
            $query->where('cr.property_id', $propertyId);
        }

        return $query->selectRaw('
            si.item_name,
            COUNT(*) as count,
            AVG(cr.threshold_percentage) as avg_threshold,
            SUM(CASE WHEN cr.payable_status_id IN (1, 3) THEN 1 ELSE 0 END) as payable_count,
            SUM(CASE WHEN cr.payable_status_id = 0 THEN 1 ELSE 0 END) as unpayable_count,
            SUM(CASE WHEN cr.payable_status_id IN (1, 3) THEN cr.payable_amount ELSE 0 END) as payable_amount,
            MIN(cr.threshold_percentage) as min_threshold,
            MAX(cr.threshold_percentage) as max_threshold
        ')
            ->groupBy('si.id', 'si.item_name')
            ->orderBy('si.payment_priority')
            ->get();
    }

    /**
     * Get returns that need attention (low threshold)
     * 
     * @param string $returnMonth
     * @param float $thresholdLimit Default: 85
     * @param int|null $clientId
     * @param int|null $propertyId
     * @return \Illuminate\Support\Collection
     */
    public function getLowThresholdReturns($returnMonth, $thresholdLimit = 85, $clientId = null, $propertyId = null)
    {
        $query = DB::table('sys_pm_clients_returns as cr')
            ->join('sys_pm_debtors_invoices as i', 'cr.invoice_id', '=', 'i.id')
            ->join('sys_pm_clients as c', 'cr.tenant_id', '=', 'c.id')
            ->join('sys_pm_units as u', 'cr.unit_id', '=', 'u.id')
            ->join('sys_set_invoiceable_items as si', 'cr.trans_type_id', '=', 'si.id')
            ->where('cr.return_date', $returnMonth)
            ->where('cr.threshold_percentage', '<', $thresholdLimit)
            ->where('cr.payable_status_id', 0); // Unpayable

        if ($clientId) {
            $query->where('cr.client_id', $clientId);
        }

        if ($propertyId) {
            $query->where('cr.property_id', $propertyId);
        }

        return $query->select([
            'cr.id',
            'cr.invoice_id',
            'cr.threshold_percentage',
            'cr.payable_status_id',
            'cr.trans_amount',
            'cr.payable_amount',
            'i.reference_number as invoice_ref',
            'i.total_amount as invoice_total',
            'i.current_balance as invoice_balance',
            'c.names as tenant_name',
            'u.unit_code',
            'si.item_name'
        ])
            ->orderBy('cr.threshold_percentage', 'asc')
            ->get();
    }

    /**
     * Recalculate thresholds for invoices that were updated outside normal flow
     * Useful for data corrections or imports
     * 
     * @param array $invoiceIds
     * @return array Results
     */
    public function recalculateThresholds(array $invoiceIds)
    {
        return $this->batchUpdateThresholds($invoiceIds);
    }

    /**
     * Get return payability status name
     * 
     * @param int $statusId
     * @return string
     */
    public function getPayableStatusName($statusId)
    {
        $statuses = [
            0 => 'Unpayable',
            1 => 'Payable',
            2 => 'No Past Invoice',
            3 => 'Past Passed Threshold',
            17 => 'Processed'
        ];

        return $statuses[$statusId] ?? 'Unknown';
    }

    /**
     * Check if a return is payable (considering all rules)
     * 
     * @param int $returnId
     * @return array Details
     */
    public function checkReturnPayability($returnId)
    {
        $return = DB::table('sys_pm_clients_returns as cr')
            ->join('sys_pm_debtors_invoices as i', 'cr.invoice_id', '=', 'i.id')
            ->join('sys_set_invoiceable_items as si', 'cr.trans_type_id', '=', 'si.id')
            ->where('cr.id', $returnId)
            ->select([
                'cr.*',
                'i.total_amount',
                'i.current_balance',
                'i.current_status_id',
                'si.item_name',
                'si.advance_payable'
            ])
            ->first();

        if (!$return) {
            throw new Exception("Return not found: {$returnId}");
        }

        $threshold = $this->calculateThreshold($return->total_amount, $return->current_balance);
        $previousThreshold = $this->checkPreviousMonthThreshold(
            $return->lease_id,
            $return->trans_type_id,
            $return->return_date
        );

        return [
            'return_id' => $returnId,
            'invoice_id' => $return->invoice_id,
            'current_threshold' => $threshold,
            'previous_threshold' => $previousThreshold,
            'payable_status_id' => $return->payable_status_id,
            'payable_status_name' => $this->getPayableStatusName($return->payable_status_id),
            'is_payable' => in_array($return->payable_status_id, [1, 3]),
            'item_name' => $return->item_name,
            'advance_payable' => $return->advance_payable,
            'invoice_status' => $return->current_status_id,
            'payable_amount' => $return->payable_amount
        ];
    }
}

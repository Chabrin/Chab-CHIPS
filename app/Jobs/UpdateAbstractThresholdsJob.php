<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateAbstractThresholdsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    protected $batchId;
    protected $returnsMonth;

    /**
     * Create a new job instance.
     */
    public function __construct($batchId, $returnsMonth)
    {
        $this->batchId = $batchId;
        $this->returnsMonth = $returnsMonth;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            // Calculate date boundaries
            $currentMonthStart = date('Y-m-01', strtotime($this->returnsMonth . '-01'));
            $previousMonthStart = date('Y-m-01', strtotime($this->returnsMonth . '-01 -1 month'));
            $previousMonthEnd = date('Y-m-t', strtotime($this->returnsMonth . '-01 -1 month'));
            $allowedThreshold = 85;

            Log::info("Background job: Starting optimized batch-filtered threshold update", [
                'batch_id' => $this->batchId,
                'previous_month_range' => "$previousMonthStart to $previousMonthEnd",
                'current_month' => $this->returnsMonth,
                'params' => [
                    'prev_start' => $previousMonthStart,
                    'prev_end' => $previousMonthEnd,
                    'curr_start' => $currentMonthStart,
                    'batch' => $this->batchId,
                    'threshold' => $allowedThreshold
                ]
            ]);

            $abstractThresholdSql = "
                UPDATE sys_pm_clients_returns AS curr
                INNER JOIN sys_pm_debtors_invoices AS inv ON curr.invoice_id = inv.id
                INNER JOIN sys_set_invoiceable_items AS items ON inv.item_type_id = items.id
                
                -- Previous month threshold (join with invoice to match item types)
                LEFT JOIN (
                    SELECT 
                        p.lease_id,
                        pinv.item_type_id,
                        p.threshold_percentage
                    FROM sys_pm_clients_returns p
                    INNER JOIN sys_pm_debtors_invoices pinv ON p.invoice_id = pinv.id
                    WHERE p.return_date >= ?
                      AND p.return_date <= ?
                      AND p.current_status_id = 1
                ) AS prev
                    ON prev.lease_id = curr.lease_id
                    AND prev.item_type_id = inv.item_type_id
                
                -- Historical average (use payable_amount if commisionable_amount is 0/null for advance_payable items)
                LEFT JOIN (
                    SELECT
                        r.lease_id,
                        hinv.item_type_id,
                        ROUND(AVG(
                            CASE 
                                WHEN (
                                    (COALESCE(NULLIF(hinv.commisionable_amount, 0), hinv.payable_amount) - hinv.current_balance) 
                                    / COALESCE(NULLIF(hinv.commisionable_amount, 0), hinv.payable_amount) * 100
                                ) > 100 
                                THEN 100
                                ELSE (
                                    (COALESCE(NULLIF(hinv.commisionable_amount, 0), hinv.payable_amount) - hinv.current_balance) 
                                    / COALESCE(NULLIF(hinv.commisionable_amount, 0), hinv.payable_amount) * 100
                                )
                            END
                        ), 2) as avg_historical_threshold,
                        COUNT(*) as historical_count
                    FROM sys_pm_clients_returns r
                    INNER JOIN sys_pm_debtors_invoices hinv ON r.invoice_id = hinv.id
                    WHERE r.return_date < ?
                      AND r.current_status_id = 1
                      AND COALESCE(NULLIF(hinv.commisionable_amount, 0), hinv.payable_amount) > 0
                      -- CRITICAL: Only calculate for leases in current batch
                      AND r.lease_id IN (
                          SELECT DISTINCT lease_id 
                          FROM sys_pm_clients_returns 
                          WHERE batch_id = ?
                      )
                    GROUP BY r.lease_id, hinv.item_type_id
                ) AS hist
                    ON hist.lease_id = curr.lease_id
                    AND hist.item_type_id = inv.item_type_id
                
                SET
                    curr.abstract_threshold = CASE
                        -- If previous month was 100%, abstract is 100%
                        WHEN prev.threshold_percentage = 100 THEN 100.00
                        -- No historical data = new landlord or all paid = 100%
                        WHEN hist.historical_count IS NULL THEN 100.00
                        -- Use historical average but cap at 100%
                        WHEN hist.avg_historical_threshold > 100 THEN 100.00
                        ELSE hist.avg_historical_threshold
                    END,
                    
                    curr.payable_status_id = CASE
                        -- Previous month 100% = payable
                        WHEN prev.threshold_percentage = 100 THEN 1
                        -- No historical data = new landlord = payable
                        WHEN hist.historical_count IS NULL THEN 1
                        -- Historical average >= 85% = payable
                        WHEN hist.avg_historical_threshold >= ? THEN 1
                        -- Otherwise not payable
                        ELSE 0
                    END
                
                WHERE curr.batch_id = ?
                  AND items.advance_payable = 'Yes'
                  AND curr.current_status_id = 1
            ";

            $updatedCount = DB::update($abstractThresholdSql, [
                $previousMonthStart,
                $previousMonthEnd,
                $currentMonthStart,
                $this->batchId,
                $allowedThreshold,
                $this->batchId
            ]);

            //Update landlord ledger for payable returns
            $ledgerUpdateSql = "
                UPDATE sys_pm_landlord_accounts_transaction AS l
                JOIN sys_pm_clients_returns AS r ON l.invoice_id = r.invoice_id
                SET l.payable_status = r.payable_status_id
                WHERE r.batch_id = ?
                  AND r.payable_status_id IN (1, 2)
                  AND l.amount_type = 'Credit'
                  AND l.transaction_type_id = 13";

            $updatedLedgers = DB::update($ledgerUpdateSql, [$this->batchId]);
            $executionTime = microtime(true) - $startTime;

            Log::info("✓ Background job: Completed successfully", [
                'batch_id' => $this->batchId,
                'returns_updated' => $updatedCount,
                'ledgers_updated' => $updatedLedgers,
                'execution_time_seconds' => round($executionTime, 2),
                'avg_time_per_return' => $updatedCount > 0 ? round($executionTime / $updatedCount, 4) : 0
            ]);
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error("Background job: Abstract threshold update failed", [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'execution_time_seconds' => round($executionTime, 2),
                'trace' => $e->getTraceAsString()
            ]);

            // Log to console as well
            echo "JOB FAILED: " . $e->getMessage() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Background job: Abstract threshold update failed permanently", [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage()
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Trans;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;

class BulkConfirmTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $transactionIds;
    protected string $confirmedBy;

    /**
     * Create a new job instance.
     */
    public function __construct(array $transactionIds, string $confirmedBy)
    {
        $this->transactionIds = $transactionIds;
        $this->confirmedBy = $confirmedBy;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $confirmedCount = 0;
            $skippedCount = 0;
            $alreadyConfirmedCount = 0;

            DB::transaction(function () use (&$confirmedCount, &$skippedCount, &$alreadyConfirmedCount) {
                $transactions = Trans::whereIn('id', $this->transactionIds)
                    ->where(function ($q) {
                        $q->where('confirmed', '!=', 1)->orWhereNull('confirmed');
                    })
                    ->get();

                $confirmedTransactions = [];

                foreach ($transactions as $transaction) {
                    // Skip transactions created by the current user (self-confirmation not allowed)
                    if ($transaction->transacted_by === $this->confirmedBy) {
                        $skippedCount++;
                        continue;
                    }

                    $transaction->confirmed = 1;
                    $transaction->confirmed_by = $this->confirmedBy;
                    $transaction->confirmed_on = now();
                    $transaction->save();
                    $confirmedCount++;

                    // Store confirmed transactions for email sending
                    $confirmedTransactions[] = $transaction;
                }

                // Send emails for all confirmed transactions (outside the DB transaction)
                foreach ($confirmedTransactions as $transaction) {
                    try {
                        $this->sendTransactionConfirmationEmail($transaction);
                    } catch (\Exception $emailError) {
                        Log::warning('Failed to send bulk confirmation email', [
                            'transaction_id' => $transaction->id,
                            'error' => $emailError->getMessage()
                        ]);
                    }
                }

                // Count transactions that were already confirmed
                $alreadyConfirmedTransactions = Trans::whereIn('id', $this->transactionIds)
                    ->where('confirmed', 1)
                    ->count();
                $alreadyConfirmedCount = $alreadyConfirmedTransactions;
            });

            // Log the results
            Log::info('Bulk confirmation job completed', [
                'confirmed_by' => $this->confirmedBy,
                'confirmed_count' => $confirmedCount,
                'skipped_count' => $skippedCount,
                'already_confirmed_count' => $alreadyConfirmedCount,
                'total_requested' => count($this->transactionIds)
            ]);
        } catch (\Exception $e) {
            Log::error('Error in bulk confirmation job: ' . $e->getMessage(), [
                'transaction_ids' => $this->transactionIds,
                'confirmed_by' => $this->confirmedBy,
                'trace' => $e->getTraceAsString()
            ]);
            ErrorDispatcher::dispatch($e);
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Send transaction confirmation email with PDF receipt
     */
    private function sendTransactionConfirmationEmail(Trans $transaction): void
    {
        try {
            // Dispatch the job to send email with PDF attachment
            TransactionConfirmationJob::dispatch($transaction);

            Log::info('Transaction confirmation email job dispatched from bulk job', [
                'transaction_id' => $transaction->id,
                'member_no' => $transaction->member_no
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch transaction confirmation email job from bulk job', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

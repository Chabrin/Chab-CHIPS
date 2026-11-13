<?php

namespace App\Listeners;

use App\Events\BankStatementConfirmed;
use App\Services\BankStatement\BankStatementMatchService;
use App\Services\BankStatement\BankStatementPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Works through a confirmed bank statement on the queue, so the person who confirmed it is not kept waiting: first every
 * line is matched to a lease, then every matched line is paid in and allocated. It runs once; a crash marks the upload
 * Failed with the reason and leaves what is already decided or paid as it is, and the run can be started again because
 * only Pending lines are matched and only unpaid Matched lines are paid.
 */
class MatchBankStatementLines implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 1;
    public int $timeout = 1800;
    public bool $afterCommit = true;

    protected BankStatementMatchService $matcher;
    protected BankStatementPaymentService $payer;

    /**
     * @param BankStatementMatchService $matcher
     * @param BankStatementPaymentService $payer
     */
    public function __construct(BankStatementMatchService $matcher, BankStatementPaymentService $payer) {
        $this->matcher = $matcher;
        $this->payer = $payer;
    }

    /**
     * Match the lines of the confirmed upload, then pay the matched ones in.
     *
     * @param BankStatementConfirmed $event
     */
    public function handle(BankStatementConfirmed $event): void {
        $this->matcher->matchUpload($event->uploadId);
        $this->payer->payUpload($event->uploadId);
    }

    /**
     * The run crashed: say so on the upload, with the reason, so the page shows it.
     *
     * @param BankStatementConfirmed $event
     * @param Throwable $exception
     */
    public function failed(BankStatementConfirmed $event, Throwable $exception): void {
        DB::table('cb_bank_statement_uploads')->where('id', $event->uploadId)->update([
            'status' => 'Failed',
            'error' => mb_substr($exception->getMessage(), 0, 1000),
            'updated_at' => now(),
        ]);
    }
}

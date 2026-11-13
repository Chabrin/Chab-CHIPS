<?php

namespace App\Jobs;

use App\Services\BankStatement\BankStatementBanks;
use App\Services\BankStatement\PaymentReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends the receipt of one payment that came in through a bank statement. It runs on its own after the payment is saved,
 * so an email problem can never undo or hold up a payment. It tries once: a receipt that failed is shown as Failed on
 * the statement line and in the email log, instead of being sent twice.
 */
class SendPaymentReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public string $bank;
    public int $lineId;
    public int $paymentId;
    public ?int $sentBy;

    /**
     * @param string $bank equity or coop
     * @param int $lineId the statement line that was paid
     * @param int $paymentId
     * @param int|null $sentBy the user shown as the sender in the email log
     */
    public function __construct(string $bank, int $lineId, int $paymentId, ?int $sentBy = null) {
        $this->bank = $bank;
        $this->lineId = $lineId;
        $this->paymentId = $paymentId;
        $this->sentBy = $sentBy;
    }

    /**
     * Send the receipt.
     *
     * @param PaymentReceiptService $receipts
     */
    public function handle(PaymentReceiptService $receipts): void {
        $receipts->send($this->bank, $this->lineId, $this->paymentId, $this->sentBy);
    }

    /**
     * The receipt crashed before it could be sent or logged: show it on the statement line.
     *
     * @param Throwable $exception
     */
    public function failed(Throwable $exception): void {
        DB::table(BankStatementBanks::get($this->bank)['table'])->where('id', $this->lineId)->update(['receipt_status' => 'Failed', 'updated_at' => now()]);
    }
}

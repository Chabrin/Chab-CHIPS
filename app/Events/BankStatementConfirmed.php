<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An uploaded bank statement was confirmed and is ready to be matched. The queued listener
 * App\Listeners\MatchBankStatementLines does the work.
 */
class BankStatementConfirmed
{
    use Dispatchable;

    public int $uploadId;

    /**
     * @param int $uploadId the cb_bank_statement_uploads id that was confirmed
     */
    public function __construct(int $uploadId) {
        $this->uploadId = $uploadId;
    }
}

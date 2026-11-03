<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\ChartAccount;
use App\Models\CompanyBankAccount;
use App\Models\GlAccountSetup;
use App\Models\TransactionType;
use App\Traits\GlPostingTrait;
use Illuminate\Support\Facades\DB;

/**
 * The one place the client-money services (advances, returns) post a balanced GL journal and look up the bank
 * account's GL account. A journal that cannot be posted throws a ClientAccountException, so the caller's database
 * transaction rolls back and nothing is half posted.
 */
class GlJournalPoster
{
    use GlPostingTrait;

    /**
     * Post one balanced journal.
     *
     * @param string $reference
     * @param string $narration
     * @param string $details stored on the journal header, used to find the journal again
     * @param array $lines each ['account_id' => int, 'type' => 'Debit'|'Credit', 'amount' => float]
     * @param int $userId
     * @param string $transactionTypeCode item_code of the transaction type, for example '2017' or '2009'
     * @return int the journal header id
     */
    public function journal(string $reference, string $narration, string $details, array $lines, int $userId, string $transactionTypeCode): int {
        $headerId = $this->postGlEntry([
            'type' => 'generic',
            'lines' => $lines,
            'date' => now()->toDateString(),
            'reference' => $reference,
            'narration' => mb_substr($narration, 0, 250),
            'trans_details' => $details,
            'trans_type_id' => TransactionType::where('item_code', $transactionTypeCode)->value('id'),
            'entered_by' => $userId,
        ]);

        if (!$headerId) {
            throw new ClientAccountException('The GL journal for ' . $reference . ' could not be posted.');
        }

        return $headerId;
    }

    /**
     * Whether a journal with these details is already in the GL, the duplicate guard for a posting.
     *
     * @param string $details the details the journal was posted with
     */
    public function exists(string $details): bool {
        return DB::table('sys_gl_transactions')->where('trans_details', $details)->exists();
    }

    /**
     * The GL account the bank side of a payment goes to: the company bank account's own GL account when it
     * has one, otherwise the GL setup's business bank account.
     *
     * @param int|null $sourceAccountId the company bank account the voucher is paid from
     */
    public function bankGlAccountId(?int $sourceAccountId): int {
        $own = $sourceAccountId ? CompanyBankAccount::whereKey($sourceAccountId)->value('gl_account_id') : null;

        if ($own && !ChartAccount::whereKey($own)->exists()) {
            throw new ClientAccountException('The GL account set on the bank account (id ' . $own . ') is not in the chart of accounts.');
        }

        $id = $own ?: (GlAccountSetup::first()->business_bank_account_id ?? null);

        if (!$id || !ChartAccount::whereKey($id)->exists()) {
            throw new ClientAccountException('The bank account has no GL account, and the GL setup has no business bank account, so the payment cannot be posted.');
        }

        return (int) $id;
    }
}

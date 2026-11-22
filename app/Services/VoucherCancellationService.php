<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestLog;
use App\Models\ChequeLeaf;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a payment voucher together with the cheque leaf it used, and lists the cheques that were cancelled.
 *
 * A cancelled voucher keeps its number and its row (status 'Cancelled', who, when and why are saved on the header). Its
 * cheque leaf is cancelled with it, so the number can never be issued again, and the return or advance it was paying is
 * freed for a new voucher. An approved return or advance voucher is refused: approving it already posted the payout on
 * the client's ledger and the GL, and reversing a payout is not built.
 */
class VoucherCancellationService
{
    const STATUS_CANCELLED = 'Cancelled';

    protected ClientAdvanceService $advances;
    protected ReturnPostingService $returnPosting;
    protected ChequeBookService $chequeBooks;

    /**
     * Inject the services that free a return or an advance, and the one that keeps a cheque book's status right.
     *
     * @param ClientAdvanceService $advances
     * @param ReturnPostingService $returnPosting
     * @param ChequeBookService $chequeBooks
     */
    public function __construct(ClientAdvanceService $advances, ReturnPostingService $returnPosting, ChequeBookService $chequeBooks) {
        $this->advances = $advances;
        $this->returnPosting = $returnPosting;
        $this->chequeBooks = $chequeBooks;
    }

    /**
     * Why a voucher cannot be cancelled, or null when it can.
     *
     * @param PaymentVoucherHeader $voucher
     */
    public function refusal(PaymentVoucherHeader $voucher): ?string {
        if ($voucher->voucher_status === self::STATUS_CANCELLED) {
            return 'This voucher is already cancelled.';
        }

        if ($voucher->voucher_status === 'Approved') {
            $postsPayout = PaymentVoucherLine::where('entry_id', $voucher->id)
                ->whereIn('voucher_type', ['client_returns', ClientAdvanceService::VOUCHER_TYPE])
                ->exists();

            if ($postsPayout) {
                return 'This voucher is approved and its payout is already on the client account and the GL. A posted payout cannot be cancelled here.';
            }
        }

        return null;
    }

    /**
     * Cancel the voucher, its cheque leaves and any open approval, and free the return or advance it was paying. All or
     * nothing.
     *
     * @param PaymentVoucherHeader $voucher
     * @param int $userId
     * @param string $userName
     * @param string $reason
     * @return int the number of cheque leaves cancelled
     * @throws RuntimeException when the voucher cannot be cancelled
     */
    public function cancel(PaymentVoucherHeader $voucher, int $userId, string $userName, string $reason): int {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Give the reason for cancelling (at least 5 characters).');
        }

        return DB::transaction(function () use ($voucher, $userId, $userName, $reason) {
            $locked = PaymentVoucherHeader::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($message = $this->refusal($locked)) {
                throw new RuntimeException($message);
            }

            $leafIds = PaymentVoucherLine::where('entry_id', $locked->id)->whereNotNull('cheque_leaf_id')->pluck('cheque_leaf_id')->unique()->all();
            $cancelled = $this->cancelLeaves($leafIds, $userId, 'Voucher ' . $locked->voucher_number . ' cancelled: ' . $reason);

            $this->advances->releaseVoucher($locked);
            $this->returnPosting->releaseVoucher($locked);
            $this->closeApproval($locked, $userId, $reason);

            $locked->update([
                'voucher_status' => self::STATUS_CANCELLED,
                'cancelled_by' => $userName,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $cancelled;
        });
    }

    /**
     * Cancel the cheque leaves that are locked or used (a leaf already cancelled is left as it is) and refresh their books.
     *
     * @param array $leafIds
     * @param int $userId
     * @param string $reason
     * @return int the number of leaves cancelled
     */
    protected function cancelLeaves(array $leafIds, int $userId, string $reason): int {
        if (!$leafIds) {
            return 0;
        }

        $books = ChequeLeaf::whereIn('id', $leafIds)->pluck('parent_id')->unique()->all();

        $count = DB::table('sys_fin_cheque_books_261_repeat')
            ->whereIn('id', $leafIds)
            ->whereIn('current_status_id', [ChequeLeaf::STATUS_LOCKED, ChequeLeaf::STATUS_USED])
            ->update([
                'current_status_id' => ChequeLeaf::STATUS_CANCELLED,
                'cancelled_by_id' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'lock_token' => null,
                'locked_by_id' => null,
                'locked_at' => null,
            ]);

        foreach ($books as $bookId) {
            $this->chequeBooks->refreshBookStatus($bookId ? (int) $bookId : null);
        }

        return $count;
    }

    /**
     * Take a waiting approval off the approvers' queue, with the cancellation on its log.
     *
     * @param PaymentVoucherHeader $voucher
     * @param int $userId
     * @param string $reason
     */
    protected function closeApproval(PaymentVoucherHeader $voucher, int $userId, string $reason): void {
        $request = $voucher->approvalRequest();

        if (!$request || $request->isComplete()) {
            return;
        }

        ApprovalRequestLog::logAction($request->id, $userId, $request->current_level, ApprovalRequestLog::ACTION_REJECTED, 'Voucher cancelled: ' . $reason);

        $request->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'current_approver_id' => null,
            'completed_on' => now(),
        ]);
    }

    /**
     * The cancelled cheques, newest first, each with the voucher it was cancelled for, the bank account and who cancelled it.
     *
     * @param array $filters optional date_from and date_to (cancellation date), bank_account_id and search (cheque or voucher number)
     * @return array
     */
    public function cancelledCheques(array $filters = []): array {
        $query = DB::table('sys_fin_cheque_books_261_repeat as leaf')
            ->leftJoin('sys_fin_cheque_books as book', 'book.id', '=', 'leaf.parent_id')
            ->leftJoin('sys_set_bank_accounts as account', 'account.id', '=', 'leaf.bank_account_id')
            ->leftJoin('josya_users as who', 'who.id', '=', 'leaf.cancelled_by_id')
            ->where('leaf.current_status_id', ChequeLeaf::STATUS_CANCELLED)
            ->orderByDesc('leaf.cancelled_at')
            ->orderByDesc('leaf.id')
            ->select([
                'leaf.id', 'leaf.cheque_number', 'leaf.cancelled_at', 'leaf.cancel_reason', 'leaf.used_for',
                'book.reference_number as book_reference', 'account.account_name', 'account.account_number',
                'who.name as cancelled_by',
            ])
            ->selectSub(
                DB::table('cb_payment_vouchers_lines_new as line')->select('line.voucher_number')->whereColumn('line.cheque_leaf_id', 'leaf.id')->orderByDesc('line.id')->limit(1),
                'voucher_number'
            );

        if (!empty($filters['date_from'])) {
            $query->whereDate('leaf.cancelled_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('leaf.cancelled_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['bank_account_id'])) {
            $query->where('leaf.bank_account_id', (int) $filters['bank_account_id']);
        }
        if (!empty($filters['search'])) {
            $like = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('leaf.cheque_number', 'like', $like)
                    ->orWhere('leaf.cancel_reason', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('cb_payment_vouchers_lines_new as line')
                            ->whereColumn('line.cheque_leaf_id', 'leaf.id')->where('line.voucher_number', 'like', $like);
                    });
            });
        }

        return $query->limit(2000)->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'cheque_number' => (string) $row->cheque_number,
            'bank_account' => trim(($row->account_name ?? '') . ' ' . ($row->account_number ? '(' . $row->account_number . ')' : '')) ?: '-',
            'book_reference' => $row->book_reference ?? '-',
            'voucher_number' => $row->voucher_number ?? '-',
            'cancelled_by' => $row->cancelled_by ?? '-',
            'cancelled_at' => $row->cancelled_at ? date('d/m/Y H:i', strtotime((string) $row->cancelled_at)) : '-',
            'reason' => (string) ($row->cancel_reason ?? ''),
        ])->all();
    }
}

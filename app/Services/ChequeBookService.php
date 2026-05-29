<?php

namespace App\Services;

use App\Models\ChequeBook;
use App\Models\ChequeLeaf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChequeBookService
{
    /**
     * Generate all cheque leaf rows for a newly created cheque book.
     * Supports purely numeric first numbers (e.g. "100200") and
     * alphanumeric numbers with a numeric suffix (e.g. "ABC001050").
     */
    public function generateLeaves(ChequeBook $book): void
    {
        $first = $book->first_cheque_number;
        $count = (int) $book->leafs;

        DB::transaction(function () use ($book, $first, $count) {
            if (ctype_digit($first)) {
                // Purely numeric — increment as integer, zero-pad to original length
                $length = strlen($first);
                $base   = (int) $first;

                for ($i = 0; $i < $count; $i++) {
                    ChequeLeaf::create([
                        'parent_id'         => $book->id,
                        'bank_account_id'   => $book->bank_account_id,
                        'cheque_number'     => str_pad((string) ($base + $i), $length, '0', STR_PAD_LEFT),
                        'current_status_id' => ChequeLeaf::STATUS_AVAILABLE,
                    ]);
                }
            } else {
                // Alphanumeric — extract trailing numeric portion and increment it
                preg_match('/^(.*?)(\d+)$/', $first, $matches);

                if (!isset($matches[2])) {
                    // No numeric suffix — just append sequential suffix
                    for ($i = 0; $i < $count; $i++) {
                        ChequeLeaf::create([
                            'parent_id'         => $book->id,
                            'bank_account_id'   => $book->bank_account_id,
                            'cheque_number'     => $first . '-' . ($i + 1),
                            'current_status_id' => ChequeLeaf::STATUS_AVAILABLE,
                        ]);
                    }
                } else {
                    $prefix    = $matches[1];
                    $numPart   = $matches[2];
                    $numLength = strlen($numPart);
                    $base      = (int) $numPart;

                    for ($i = 0; $i < $count; $i++) {
                        ChequeLeaf::create([
                            'parent_id'         => $book->id,
                            'bank_account_id'   => $book->bank_account_id,
                            'cheque_number'     => $prefix . str_pad((string) ($base + $i), $numLength, '0', STR_PAD_LEFT),
                            'current_status_id' => ChequeLeaf::STATUS_AVAILABLE,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Atomically lock a cheque leaf for exclusive use by a session.
     * Returns the updated leaf (with lock_token) on success.
     * Throws \RuntimeException if the leaf is no longer available.
     */
    public function lockCheque(int $leafId, int $userId): ChequeLeaf
    {
        $token = (string) Str::uuid();

        $affected = DB::table('sys_fin_cheque_books_261_repeat')
            ->where('id', $leafId)
            ->where('current_status_id', ChequeLeaf::STATUS_AVAILABLE)
            ->update([
                'current_status_id' => ChequeLeaf::STATUS_LOCKED,
                'locked_by_id'      => $userId,
                'locked_at'         => now(),
                'lock_token'        => $token,
            ]);

        if ($affected === 0) {
            throw new \RuntimeException('This cheque is no longer available. It may have been selected by another user.');
        }

        return ChequeLeaf::findOrFail($leafId);
    }

    /**
     * Release a lock using the token issued at lock time.
     * Only the session that holds the token can release the lock.
     */
    public function releaseCheque(int $leafId, string $lockToken): void
    {
        Log::info('Leaf ID: ' . $leafId . ' | Lock Token: ' . $lockToken);
        $affected = DB::table('sys_fin_cheque_books_261_repeat')
            ->where('cheque_number', $leafId)
            ->where('lock_token', $lockToken)
            ->where('current_status_id', ChequeLeaf::STATUS_LOCKED)
            ->update([
                'current_status_id' => ChequeLeaf::STATUS_AVAILABLE,
                'locked_by_id'      => null,
                'locked_at'         => null,
                'lock_token'        => null,
            ]);

        if ($affected === 0) {
            throw new \RuntimeException('Could not release cheque lock — token mismatch or cheque is not locked.');
        }
    }

    /**
     * Admin-only: force-release a lock regardless of token.
     */
    public function forceReleaseCheque(int $leafId): void
    {
        DB::table('sys_fin_cheque_books_261_repeat')
            ->where('id', $leafId)
            ->where('current_status_id', ChequeLeaf::STATUS_LOCKED)
            ->update([
                'current_status_id' => ChequeLeaf::STATUS_AVAILABLE,
                'locked_by_id'      => null,
                'locked_at'         => null,
                'lock_token'        => null,
            ]);
    }

    /**
     * Mark a cheque as used.
     */
    public function useCheque(int $leafId, int $userId, string $usedFor = ''): void
    {
        DB::table('sys_fin_cheque_books_261_repeat')
            ->where('id', $leafId)
            ->update([
                'current_status_id' => ChequeLeaf::STATUS_USED,
                'used_by_id'        => $userId,
                'used_at'           => now(),
                'used_for'          => $usedFor,
                'lock_token'        => null,
            ]);
    }
}

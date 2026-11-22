<?php

namespace App\Services;

/**
 * The standard narrations of a payment voucher entry. The old system's voucher lines were almost all one of these
 * followed by the property or unit (CHEQUE CHARGES, CARETAKER SALARY PLOT 14, REFUND OF DEPOSITS, ADVANCE PAYMENTS ...), so
 * the screen offers the list and one box for the unit or property, and the entry reads the same way every time.
 */
class VoucherNarrations
{
    const OTHER = 'OTHER';

    const LIST = [
        'ADVANCE PAYMENTS',
        'CARETAKER SALARY',
        'CHEQUE CHARGES',
        'ELECTRICITY BILL',
        'FINAL PAYMENTS',
        'GARBAGE COLLECTION',
        'INTEREST ON ADVANCES',
        'REFUND OF DEPOSITS',
        'REFUND OF SALARY DEDUCTION',
        'SECURITY PAYMENT',
        'WATCHMAN SALARY',
        'WATER BILL',
        'WITHHOLDING TAX',
        'WORKER SALARY',
    ];

    /**
     * The narration text of an entry: the standard narration followed by the unit or property, in capitals.
     *
     * @param string $narration one of LIST, or OTHER / empty when the text is typed
     * @param string $detail the unit number or property, may be empty
     * @param string $typed the text typed when the narration is OTHER
     */
    public static function compose(string $narration, string $detail = '', string $typed = ''): string {
        $base = in_array($narration, self::LIST, true) ? $narration : trim($typed);

        return mb_strtoupper(trim($base . ' ' . trim($detail)));
    }
}

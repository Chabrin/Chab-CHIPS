<?php

namespace App\Support;

/**
 * How an ID number, KRA PIN or bank account number is shown in a list: only the last four characters, for example ****5678.
 *
 * This changes only what is shown. The number itself stays saved exactly as it was typed, and everything that needs it (payslips,
 * the bank payment file, statutory returns, exports, the profile and the edit form) still reads the full value.
 */
final class MaskedNumber
{
    /**
     * @param  int  $visible  how many characters at the end are left showing
     */
    public static function tail(?string $value, int $visible = 4): string
    {
        $text = preg_replace('/\s+/', '', (string) $value) ?? '';
        if ($text === '') {
            return '';
        }

        // A number no longer than what stays showing is hidden completely, so nothing is given away
        return mb_strlen($text) <= $visible ? '****' : '****' . mb_substr($text, -$visible);
    }

    /**
     * The same for a list cell that may have nothing to show (a dash stays a dash).
     */
    public static function orDash(?string $value, string $dash = '—'): string
    {
        $masked = self::tail($value);

        return $masked === '' ? $dash : $masked;
    }
}

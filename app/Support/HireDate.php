<?php

namespace App\Support;

use Carbon\Carbon;

class HireDate
{
    /**
     * Read a staff hire date, which is stored in mixed formats: ISO, legacy "Jan  1 2010 12:00AM", and slash dates
     * in both orders. Slash dates are day-first unless the second part cannot be a month (05/23/2023).
     * Returns null when the value is empty or unreadable.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    public static function parse($value): ?Carbon {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $text = trim((string) $value);

        try {
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $text, $m)) {
                $format = ((int) $m[2] > 12) ? 'm/d/Y' : 'd/m/Y';

                return Carbon::createFromFormat($format, $m[1] . '/' . $m[2] . '/' . $m[3])->startOfDay();
            }

            return Carbon::parse($text);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

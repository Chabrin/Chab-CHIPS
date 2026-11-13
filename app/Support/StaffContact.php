<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * How staff contact details are written: mobile numbers start with the country code (+254712345678) and
 * company emails are firstname.surname@chabrinagencies.co.ke. public/assets/js/staff-contact-format.js applies
 * the same rules in the browser, so keep the two in step.
 */
class StaffContact
{
    private static function countryCode(): string
    {
        return (string) config('staff.country_code', '254');
    }

    private static function emailDomain(): string
    {
        return strtolower((string) config('staff.email_domain', 'chabrinagencies.co.ke'));
    }

    /**
     * A mobile number with its country code and no spaces: 0712 345 678, 254712345678 and +254 712 345 678 all
     * become +254712345678. Anything that cannot be read as a number is returned tidied up but unchanged.
     */
    public static function mobile(?string $input): ?string
    {
        $number = preg_replace('/[\s\-().]/', '', trim((string) $input));

        if ($number === '') {
            return null;
        }
        $code = self::countryCode();

        if (str_starts_with($number, '00')) {
            $number = '+' . substr($number, 2);
        }
        // +254 0712..., a zero left in after the country code
        if (preg_match('/^\+?' . $code . '0([17]\d{8})$/', $number, $match)) {
            return '+' . $code . $match[1];
        }
        if (str_starts_with($number, '+')) {
            return $number;
        }
        if (preg_match('/^' . $code . '[17]\d{8}$/', $number)) {
            return '+' . $number;
        }
        if (preg_match('/^0([17]\d{8})$/', $number, $match)) {
            return '+' . $code . $match[1];
        }
        if (preg_match('/^[17]\d{8}$/', $number)) {
            return '+' . $code . $number;
        }

        return $number;
    }

    /** A number as returned by mobile(): a Kenyan mobile, or another country's number written with its + code. */
    public static function isMobile(?string $number): bool
    {
        $number = (string) $number;
        $code = self::countryCode();

        return (bool) preg_match('/^\+' . $code . '[17]\d{8}$/', $number)
            || (bool) preg_match('/^\+(?!' . $code . ')[1-9]\d{7,14}$/', $number);
    }

    /** The names in full names as lower-case letters (and hyphens), accents removed, in the order written. */
    private static function nameWords(?string $fullNames): array
    {
        return array_values(array_filter(array_map(
            fn ($word) => trim(preg_replace('/[^a-z-]/', '', strtolower(Str::ascii($word))), '-'),
            preg_split('/\s+/', trim((string) $fullNames))
        )));
    }

    /** firstname.surname@domain from full names (first word and last word), or null when there is only one name. */
    public static function emailFor(?string $fullNames): ?string
    {
        $words = self::nameWords($fullNames);

        return count($words) < 2 ? null : $words[0] . '.' . $words[count($words) - 1] . '@' . self::emailDomain();
    }

    /**
     * firstname.m.surname@domain with the initial of the first middle name (mary.a.wanjiku@...), for when firstname.surname
     * is taken. Null when there is no middle name.
     */
    public static function emailWithInitial(?string $fullNames): ?string
    {
        $words = self::nameWords($fullNames);

        return count($words) < 3 ? null : $words[0] . '.' . $words[1][0] . '.' . $words[count($words) - 1] . '@' . self::emailDomain();
    }

    /** firstname.surname2@domain and so on, for when there is no middle name to tell two people apart. Null with one name. */
    public static function emailWithNumber(?string $fullNames, int $number): ?string
    {
        $words = self::nameWords($fullNames);

        return count($words) < 2 ? null : $words[0] . '.' . $words[count($words) - 1] . $number . '@' . self::emailDomain();
    }

    /**
     * firstname.surname@domain, in any capitals, with a middle initial when two staff share a name (mary.a.wanjiku) or,
     * with no middle name to use, a number on the surname (john.kamau2).
     */
    public static function isCompanyEmail(?string $email): bool
    {
        return (bool) preg_match('/^[a-z][a-z-]*\.(?:[a-z]\.)?[a-z][a-z-]*\d{0,2}@' . preg_quote(self::emailDomain(), '/') . '$/', strtolower(trim((string) $email)));
    }

    /** The format, for hints and messages. */
    public static function emailExample(): string
    {
        return 'firstname.surname@' . self::emailDomain();
    }
}

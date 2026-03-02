<?php

namespace App\Services;

use App\Models\Account;

class AccountNumberGenerator
{
    public static function generate($memberNo, $securityCode)
    {
        $base = $memberNo . '-' . $securityCode;
        $candidate = $base;
        $i = 1;
        while (Account::where('account_no', $candidate)->where('member_no', $memberNo)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }
}

<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembersHost;

class MemberNumberGenerator
{
    private static function nextUniqueIdFromStart(int $start): int
    {
        $candidate = $start + 1;
        while (
            Member::where('id', $candidate)->exists()
            || MembersHost::where('id', $candidate)->exists()
        ) {
            $candidate++;
        }
        return $candidate;
    }

    public static function generate(): string
    {
        $maxMember = (int) Member::max('id');
        $next = self::nextUniqueIdFromStart($maxMember);
        $padded = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        return $padded;
    }

    public static function generateHost(): string
    {
        $maxHost = (int) MembersHost::max('id');
        $next = self::nextUniqueIdFromStart($maxHost);
        $padded = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        return 'P' . $padded;
    }
}

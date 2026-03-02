<?php

namespace App\Traits\Retirement;

use App\Models\Member;
use App\Models\MembersHost;
use Illuminate\Support\Facades\Log;


trait RetirementActions
{

    public function unflagMemberRetirement(string $memberNo): void
    {
        try {
            $memberPrimary = Member::where('member_no', $memberNo)->first();
            $memberHost    = MembersHost::where('member_no', $memberNo)->first();

            if (! $memberPrimary && ! $memberHost) {
                throw new \RuntimeException("Member {$memberNo} not found in either table.");
            }

            if ($memberPrimary && $memberHost) {
                throw new \RuntimeException("Member {$memberNo} exists in both tables — expected only one.");
            }

            // Use a single variable for update
            $memberData = $memberPrimary ?? $memberHost;
            $memberData->retirement_initiated = FALSE;
            $memberData->retirement_initiated_by = NULL;
            $memberData->retirement_initiated_on = NULL;
            $memberData->save();
        } catch (\Throwable $e) {
            Log::error("Error unflagging retirement for {$memberNo}: {$e->getMessage()}", [
                'member_no' => $memberNo,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

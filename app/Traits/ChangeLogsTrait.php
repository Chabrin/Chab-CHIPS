<?php

namespace App\Traits;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\SmsController;
use App\Models\Account;
use App\Models\AuditLogs;
use App\Models\MemberBankDetail;

trait ChangeLogsTrait
{
    public function getChangeLogDataPoints($memberId, $member_no, $isHost)
    {
        try {
            $bankDetailsIds = MemberBankDetail::where('member_no', $member_no)->pluck('id');
            $savingsIds = Account::where('member_no', $member_no)->pluck('id');

            $memberTable = $isHost ? 'members_host' : 'members';

            $activityLogQuery = AuditLogs::join('users', 'cb_audit_logs.user_id', '=', 'users.id')
                // left join the appropriate members table so member-related logs can access member rows when needed
                ->leftJoin($memberTable, function ($join) use ($memberTable) {
                    $join->on('cb_audit_logs.record_id', '=', "{$memberTable}.id")
                        ->where('cb_audit_logs.table_name', '=', $memberTable);
                })
                ->where(function ($query) use ($memberId, $bankDetailsIds, $savingsIds, $memberTable) {
                    if ($bankDetailsIds->isNotEmpty()) {
                        $query->orWhere(function ($q) use ($bankDetailsIds) {
                            $q->whereIn('record_id', $bankDetailsIds)
                                ->where('table_name', 'members_bank_details');
                        });
                    }
                    if ($savingsIds->isNotEmpty()) {
                        $query->orWhere(function ($q) use ($savingsIds) {
                            $q->whereIn('record_id', $savingsIds)
                                ->where('table_name', 'accounts');
                        });
                    }
                    $query->orWhere(function ($q) use ($memberId, $memberTable) {
                        $q->where('record_id', $memberId)
                            ->where('table_name', $memberTable);
                    });
                })
                ->where('approved', null)
                ->whereIn('operation', ['create', 'update'])
                ->orderBy('cb_audit_logs.id', 'desc');

            // Return the IDs
            return $activityLogQuery->pluck('cb_audit_logs.id');
        } catch (Exception $e) {
            Log::error($e);
            return FALSE;
        }
    }
}

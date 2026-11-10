<?php

namespace App\Services;

use App\Models\ClientBankDetail;
use App\Models\ReturnSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads of a merged return (MASTERRETURN): the property returns it covers and the bank account it is paid into.
 * A client with several properties can ask to be paid into one bank account. Each property keeps its own return, and
 * the merged return sums them, takes the landlord-level deductions, and is paid by one voucher.
 */
class MergedReturnService
{
    /**
     * The code of a landlord's merged return for a period. The first batch keeps the plain code (R1 + period + landlord
     * code, the shape the old system used). Each further batch of the same landlord in the same period takes -2, -3 and
     * so on, so two batches never share a code and their lines, deductions and vouchers stay apart. A code freed by a
     * deleted merged return is taken again.
     *
     * @param string $returnPeriod e.g. 202611
     * @param string $landlordCode e.g. LAN-01072
     * @param array $used the return codes already in use (codes that do not belong to this landlord and period are ignored)
     */
    public static function masterCode(string $returnPeriod, string $landlordCode, array $used): string {
        $base = 'R1' . $returnPeriod . $landlordCode;
        $code = $base;
        $sequence = 1;

        while (in_array($code, $used, true)) {
            $sequence++;
            $code = $base . '-' . $sequence;
        }

        return $code;
    }

    /**
     * The property returns a merged return covers, with each one's property, net and approval state.
     *
     * @param ReturnSummary $master
     * @return Collection of objects: id, return_code, property_code, property_name, net_balance, approved_status, paid_status
     */
    public function propertyReturns(ReturnSummary $master): Collection {
        return DB::table('cb_returns_summary as rs')
            ->leftJoin('sys_pm_properties as p', 'p.reference_number', '=', 'rs.property_code')
            ->where('rs.parent_return_code', $master->return_code)
            ->where('rs.return_type', 'MAINRETURN')
            ->orderBy('rs.property_code')
            ->get(['rs.id', 'rs.return_code', 'rs.property_code', 'p.property_name', 'rs.net_balance', 'rs.approved_status', 'rs.paid_status']);
    }

    /**
     * The merge batch a merged return was made from: the batch holding the properties it covers.
     *
     * @param ReturnSummary $master
     */
    public function batchOf(ReturnSummary $master): ?object {
        $propertyIds = DB::table('sys_pm_properties')
            ->whereIn('reference_number', $this->propertyReturns($master)->pluck('property_code')->filter()->all())
            ->pluck('id');

        if ($propertyIds->isEmpty()) {
            return null;
        }

        return DB::table('cb_returns_merge_batches as mb')
            ->join('cb_returns_merge_batch_items as mbi', 'mbi.batch_id', '=', 'mb.id')
            ->whereIn('mbi.property_id', $propertyIds)
            ->orderByDesc('mb.id')
            ->first(['mb.id', 'mb.batch_reference', 'mb.bank_detail_id']);
    }

    /**
     * The bank account a merged return is paid into: the one chosen on its batch. Null when none was chosen, in which
     * case the voucher is paid by cheque.
     *
     * @param ReturnSummary $master
     * @return array|null bank_name, account_name, account_number
     */
    public function payeeBank(ReturnSummary $master): ?array {
        $batch = $this->batchOf($master);

        if (!$batch || !$batch->bank_detail_id) {
            return null;
        }

        $detail = ClientBankDetail::with('bank')->find($batch->bank_detail_id);

        if (!$detail) {
            return null;
        }

        return [
            'bank_name' => $detail->bank->bank_name ?? null,
            'account_name' => $detail->account_name,
            'account_number' => $detail->account_number,
        ];
    }
}

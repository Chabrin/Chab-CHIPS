<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Where every client return is in its flow, for people who only watch it (accounts, auditors): generated, the approval
 * levels with who acted and when, the payment voucher and whether it is paid. Nothing here changes a return.
 *
 * A return is seen only for the landlords the signed-in user may see (the same zone limit as the Client screens).
 */
class ReturnsFlowService
{
    const STAGE_GENERATED = 'Generated, not submitted';
    const STAGE_REJECTED = 'Rejected';
    const STAGE_APPROVED = 'Approved, no voucher yet';
    const STAGE_VOUCHER_WAITING = 'Voucher awaiting approval';
    const STAGE_VOUCHER_APPROVED = 'Voucher approved';
    const STAGE_PAID = 'Paid';

    const LIMIT = 2000;

    /**
     * The returns with their flow, newest first.
     *
     * @param array $filters optional year, month, stage (one of the stage names, or 'awaiting' for any approval level), search (return, landlord or property)
     * @return array
     */
    public function rows(array $filters = []): array {
        $query = DB::table('cb_returns_summary as rs')
            ->leftJoin('sys_pm_clients as c', 'c.reference_number', '=', 'rs.landlord_code')
            ->leftJoin('cb_approval_items as ai', function ($join) {
                $join->on('ai.item_id', '=', 'rs.id')->where('ai.item_type', '=', 'return_summary');
            })
            ->whereIn('rs.landlord_code', Client::query()->select('reference_number'))
            ->select([
                'rs.id', 'rs.return_code', 'rs.return_type', 'rs.landlord_code', 'rs.property_code', 'rs.year', 'rs.month',
                'rs.net_balance', 'rs.prepared_by', 'rs.prepared_date', 'rs.approved_status', 'rs.paid_status', 'rs.payment_date',
                'c.names as landlord_name',
                'ai.id as item_id', 'ai.status as item_status', 'ai.current_level', 'ai.total_levels', 'ai.rejection_reason', 'ai.rejected_at',
            ])
            ->orderByDesc('rs.prepared_date')
            ->orderByDesc('rs.id');

        if (!empty($filters['year'])) {
            $query->where('rs.year', (int) $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->where('rs.month', (int) $filters['month']);
        }
        if (!empty($filters['search'])) {
            $like = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('rs.return_code', 'like', $like)->orWhere('c.names', 'like', $like)->orWhere('rs.property_code', 'like', $like)->orWhere('rs.landlord_code', 'like', $like);
            });
        }

        $rows = $query->limit(self::LIMIT * 3)->get();
        $actions = $this->approvalsByItem($rows->pluck('item_id')->filter()->unique()->all());
        $vouchers = $this->vouchersByReturn($rows->pluck('id')->all());

        $result = [];
        foreach ($rows as $row) {
            $voucher = $vouchers[$row->id] ?? null;
            $stage = self::stageOf((array) $row + ['voucher_status' => $voucher->voucher_status ?? null]);

            if (!empty($filters['stage']) && !$this->matchesStage($filters['stage'], $stage)) {
                continue;
            }

            $result[] = [
                'id' => (int) $row->id,
                'return_code' => (string) $row->return_code,
                'type' => (string) $row->return_type,
                'landlord' => trim(($row->landlord_name ?? '') . ' ' . ($row->landlord_code ? '(' . $row->landlord_code . ')' : '')) ?: '-',
                'property' => (string) ($row->property_code ?: '-'),
                'period' => $row->year ? $row->year . '/' . str_pad((string) $row->month, 2, '0', STR_PAD_LEFT) : '-',
                'net_balance' => round((float) $row->net_balance, 2),
                'stage' => $stage,
                'generated' => trim(($row->prepared_by ?: '-') . ($row->prepared_date ? ' · ' . date('d/m/Y H:i', strtotime((string) $row->prepared_date)) : '')),
                'approvals' => $actions[$row->item_id] ?? [],
                'levels' => $row->total_levels ? (int) $row->total_levels : null,
                'voucher' => $voucher ? $voucher->voucher_number . ' (' . $voucher->voucher_status . ')' : '-',
                'paid' => (int) $row->paid_status === 1,
                'rejection' => $row->item_status === 'Rejected' ? trim((string) $row->rejection_reason) : '',
            ];

            if (count($result) >= self::LIMIT) {
                break;
            }
        }

        return $result;
    }

    /**
     * The stage a return is at, from its own row.
     *
     * @param array $row return_code fields: paid_status, voucher_status, approved_status, item_status, current_level, total_levels
     */
    public static function stageOf(array $row): string {
        if ((int) ($row['paid_status'] ?? 0) === 1) {
            return self::STAGE_PAID;
        }
        if (($row['voucher_status'] ?? null) === 'Approved') {
            return self::STAGE_VOUCHER_APPROVED;
        }
        if (($row['voucher_status'] ?? null) === 'Pending') {
            return self::STAGE_VOUCHER_WAITING;
        }
        if ((int) ($row['approved_status'] ?? 0) === 1) {
            return self::STAGE_APPROVED;
        }
        if (($row['item_status'] ?? null) === 'Rejected') {
            return self::STAGE_REJECTED;
        }
        if (!empty($row['item_id']) || !empty($row['item_status'])) {
            return 'Awaiting approval level ' . max(1, (int) ($row['current_level'] ?? 1)) . ' of ' . max(1, (int) ($row['total_levels'] ?? 1));
        }

        return self::STAGE_GENERATED;
    }

    /**
     * Whether a stage passes the stage filter; 'awaiting' matches every approval level.
     *
     * @param string $wanted
     * @param string $stage
     */
    protected function matchesStage(string $wanted, string $stage): bool {
        return $wanted === 'awaiting' ? str_starts_with($stage, 'Awaiting approval') : $stage === $wanted;
    }

    /**
     * The newest payment voucher of each return: return id => voucher number and status.
     *
     * @param array $returnIds
     */
    protected function vouchersByReturn(array $returnIds): array {
        if (!$returnIds) {
            return [];
        }

        $map = [];
        $rows = DB::table('cb_payment_vouchers_lines_new as vl')
            ->join('cb_payment_vouchers_headers_new as vh', 'vh.voucher_number', '=', 'vl.voucher_number')
            ->where('vl.voucher_type', 'client_returns')
            ->whereIn('vl.entry_number', $returnIds)
            ->orderBy('vh.id')
            ->get(['vl.entry_number', 'vh.voucher_number', 'vh.voucher_status']);

        foreach ($rows as $row) {
            $map[(int) $row->entry_number] = $row; // the newest voucher wins, the rows come oldest first
        }

        return $map;
    }

    /**
     * Who approved each item at each level, and when: item id => list of 'Level 1: Name · date'.
     *
     * @param array $itemIds
     */
    protected function approvalsByItem(array $itemIds): array {
        if (!$itemIds) {
            return [];
        }

        $map = [];
        $rows = DB::table('cb_item_approvers as a')
            ->leftJoin('josya_users as u', 'u.id', '=', 'a.approver_id')
            ->whereIn('a.approval_item_id', $itemIds)
            ->where('a.approval_status', 'Approved')
            ->where('a.approver_level', '>', 0)
            ->orderBy('a.approver_level')
            ->get(['a.approval_item_id', 'a.approver_level', 'a.actioned_at', 'u.name']);

        foreach ($rows as $row) {
            $map[$row->approval_item_id][] = 'Level ' . $row->approver_level . ': ' . ($row->name ?: 'unknown') . ($row->actioned_at ? ' · ' . date('d/m/Y H:i', strtotime((string) $row->actioned_at)) : '');
        }

        return $map;
    }

    /**
     * The stage names for the page's filter.
     */
    public function stages(): array {
        return [
            'awaiting' => 'Awaiting approval (any level)',
            self::STAGE_REJECTED => self::STAGE_REJECTED,
            self::STAGE_APPROVED => self::STAGE_APPROVED,
            self::STAGE_VOUCHER_WAITING => self::STAGE_VOUCHER_WAITING,
            self::STAGE_VOUCHER_APPROVED => self::STAGE_VOUCHER_APPROVED,
            self::STAGE_PAID => self::STAGE_PAID,
            self::STAGE_GENERATED => self::STAGE_GENERATED,
        ];
    }
}

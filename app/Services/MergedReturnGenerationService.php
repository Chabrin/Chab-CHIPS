<?php

namespace App\Services;

use App\Exceptions\ReturnApprovalException;
use App\Models\ReturnSummary;
use App\Traits\ReturnsDeductionsTrait;
use App\Traits\ReturnsMasterReturnTrait;
use Illuminate\Support\Facades\DB;

/** Creates a batch's merged returns only from its fully approved, unpaid property returns. */
class MergedReturnGenerationService
{
    use ReturnsDeductionsTrait;
    use ReturnsMasterReturnTrait;

    public function __construct(protected ReturnPostingService $posting) {}

    public function readiness(int $batchId, string $period, bool $lock = false): array
    {
        $batchQuery = DB::table('cb_returns_merge_batches')->where('id', $batchId);
        $batch = ($lock ? $batchQuery->lockForUpdate() : $batchQuery)->first();
        if (!$batch) {
            throw new ReturnApprovalException('The merge batch no longer exists.');
        }

        // Batch edits update this row before replacing its members. The client lock also serialises deductions and
        // master-code allocation across different batches of the same client.
        $clientQuery = DB::table('sys_pm_clients')->where('id', $batch->landlord_id);
        $client = ($lock ? $clientQuery->lockForUpdate() : $clientQuery)->first();
        $members = DB::table('cb_returns_merge_batch_items as mbi')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'mbi.property_id')
            ->where('mbi.batch_id', $batchId)
            ->orderBy('mbi.property_id')
            ->get(['mbi.property_id', 'mbi.landlord_ref', 'p.reference_number', 'p.property_name']);
        $query = ReturnSummary::where('return_period', $period)
            ->whereIn('property_code', $members->pluck('reference_number')->filter())
            ->whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])->orderBy('id');
        $returns = ($lock ? $query->lockForUpdate() : $query)->get();
        $items = DB::table('cb_approval_items')->where('item_type', 'return_summary')
            ->whereIn('item_id', $returns->pluck('id'))->get()->keyBy('item_id');
        $voucherIds = DB::table('cb_payment_vouchers_lines_new as vl')
            ->join('cb_payment_vouchers_headers_new as vh', 'vh.id', '=', 'vl.entry_id')
            ->where('vl.voucher_type', 'client_returns')->whereIn('vl.entry_number', $returns->pluck('id'))
            ->where('vh.is_deleted', 0)->whereIn('vh.voucher_status', ['Pending', 'Approved'])
            ->pluck('vl.entry_number')->all();
        // Migrated masters may list their children in transaction_ref without the newer parent/merged flags.
        $legacyMasters = ReturnSummary::where('return_period', $period)->where('return_type', 'MASTERRETURN')
            ->whereIn('landlord_code', $returns->pluck('landlord_code')->push($client->reference_number ?? ''))
            ->get(['return_code', 'landlord_code', 'transaction_ref']);

        $blockers = [];
        if (!$client || !$client->reference_number) {
            $blockers[] = 'The batch has no valid landlord reference. Review its configuration.';
        }
        $properties = [];
        foreach ($members as $member) {
            $label = trim(($member->property_name ?? 'Missing property') . ' (' . ($member->reference_number ?? $member->property_id) . ')');
            $children = $returns->where('property_code', $member->reference_number);
            $reasons = [];
            if (!$member->reference_number || $children->where('return_type', 'MAINRETURN')->count() !== 1) {
                $reasons[] = 'requires exactly one main return for this period';
            }
            if ($children->where('return_type', 'UTILITYRETURN')->count() > 1) {
                $reasons[] = 'has duplicate utility returns';
            }
            foreach ($children as $child) {
                if ($member->landlord_ref && $child->landlord_code !== $member->landlord_ref) {
                    $reasons[] = $child->return_code . ': landlord differs from the batch configuration';
                }
                if ((int) $child->approved_status !== 1) {
                    $item = $items->get($child->id);
                    $stage = !$item ? 'draft, not forwarded' : ($item->status === 'Rejected' ? 'rejected' : 'awaiting approval level ' . $item->current_level);
                    $reasons[] = $child->return_code . ': ' . $stage;
                }
                if ((int) $child->paid_status === 1) {
                    $reasons[] = $child->return_code . ': already paid';
                }
                if ((int) $child->voucher_generated === 1 || in_array($child->id, $voucherIds)) {
                    $reasons[] = $child->return_code . ': already has a payment voucher';
                }
                if ($child->is_merged || $child->parent_return_code) {
                    $reasons[] = $child->return_code . ': already included in a merged return';
                } else {
                    foreach ($legacyMasters as $master) {
                        $codes = array_filter(array_map('trim', explode(',', (string) $master->transaction_ref)));
                        if (in_array($child->return_code, $codes, true)
                            || (!$codes && $master->landlord_code === $child->landlord_code)) {
                            $reasons[] = $child->return_code . ': existing merged return ' . $master->return_code . ' must be reviewed before another merge';
                            break;
                        }
                    }
                }
            }
            $properties[] = ['property_id' => $member->property_id, 'name' => $label,
                'status' => $reasons ? implode('; ', $reasons) : 'Approved', 'ready' => !$reasons];
            foreach ($reasons as $reason) {
                $blockers[] = $label . ': ' . $reason;
            }
        }
        if ($members->count() < 2) {
            $blockers[] = 'A merge batch must contain at least two properties.';
        }

        $rentNet = round((float) $returns->where('return_type', 'MAINRETURN')->sum('net_amount'), 2);
        $utilityNet = round((float) $returns->where('return_type', 'UTILITYRETURN')->sum('net_amount'), 2);
        $deductions = [];
        if (!$blockers) {
            $deductions = $this->pickMasterDeductionRows((int) $batch->landlord_id, (int) substr($period, 0, 4), (int) substr($period, 4, 2), $rentNet);
        }
        $deductionTotal = round(array_sum(array_column($deductions, 'debit_amount')), 2);

        return ['ready' => !$blockers, 'batch_id' => $batchId, 'batch_reference' => $batch->batch_reference,
            'properties' => $properties, 'blockers' => $blockers,
            'approved' => count(array_filter($properties, fn ($p) => $p['ready'])), 'total' => $members->count(),
            'rent_net' => $rentNet, 'utility_net' => $utilityNet, 'deductions' => $deductionTotal,
            'net' => round($rentNet + $utilityNet - $deductionTotal, 2),
            'preview_token' => hash('sha256', json_encode([$period, $batch, $properties,
                $returns->map(fn ($r) => [$r->id, $r->net_amount, $r->approved_status])->all(), $deductions]))];
    }

    public function generate(int $batchId, string $period, int $userId, string $previewToken): array
    {
        return DB::transaction(function () use ($batchId, $period, $userId, $previewToken) {
            $state = $this->readiness($batchId, $period, true);
            if (!$state['ready']) {
                throw new ReturnApprovalException('Cannot generate merged return. ' . implode(' ', $state['blockers']));
            }
            if (!hash_equals($state['preview_token'], $previewToken)) {
                throw new ReturnApprovalException('The batch or its deductions changed. Review generation again before continuing.');
            }
            $year = (int) substr($period, 0, 4);
            $month = (int) substr($period, 4, 2);
            $this->generateMasterReturns($period, date('d-M-Y', strtotime("{$year}-{$month}-05")), $userId, $year, $month, $batchId);
            $children = ReturnSummary::where('return_period', $period)
                ->whereIn('property_code', DB::table('cb_returns_merge_batch_items as mbi')
                    ->join('sys_pm_properties as p', 'p.id', '=', 'mbi.property_id')->where('mbi.batch_id', $batchId)->select('p.reference_number'))
                ->whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])->get();
            foreach ($children as $child) {
                // The existing settlement posts only merged deductions; property credits were posted at approval.
                $this->posting->finaliseMasterIfReady($child, $userId);
            }
            $masters = ReturnSummary::whereIn('return_code', $children->pluck('parent_return_code')->filter()->unique())->get();
            if ($masters->isEmpty() || $children->contains(fn ($c) => !$c->parent_return_code)
                || $masters->contains(fn ($m) => (int) $m->approved_status !== 1)) {
                throw new ReturnApprovalException('The complete batch could not be merged. Nothing was saved.');
            }
            return $masters->pluck('id')->all();
        });
    }
}

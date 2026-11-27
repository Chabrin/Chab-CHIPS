<?php

namespace App\Services;

use App\Models\ReturnSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only presentation of the returns the controller has authorised the user to see. */
class ReturnListService
{
    public static function approvalState(object $return, ?object $item, ?object $lastAction, ?int $configuredLevels): array
    {
        $total = $configuredLevels ?: max(1, (int) ($item->total_levels ?? 1), (int) ($item->current_level ?? 1));
        $level = $item ? max(1, min((int) $item->current_level, $total)) : null;
        $state = ['status_key' => 'review', 'stage' => 'Needs review', 'current_level' => $level,
            'total_levels' => $item ? $total : null, 'is_draft' => false, 'status_note' => 'Approval information is incomplete.'];
        if ((int) $return->paid_status === 1) {
            return array_replace($state, ['status_key' => 'paid', 'stage' => 'Paid', 'status_note' => '']);
        }
        if ((int) $return->approved_status === 1) {
            return array_replace($state, ['status_key' => 'approved', 'stage' => 'Approved',
                'status_note' => $item && (int) $item->total_levels > 0 ? 'Final approval complete' : '']);
        }
        if (($item->status ?? '') === 'Rejected') {
            return array_replace($state, ['status_key' => 'rejected', 'stage' => 'Rejected', 'status_note' => (string) ($lastAction->comments ?? '')]);
        }
        if (!$item && (int) $return->approved_status === 0) {
            return array_replace($state, ['status_key' => 'draft', 'stage' => 'Draft', 'is_draft' => true,
                'status_note' => 'Not forwarded to the manager']);
        }
        if ($item && in_array($item->status, ['Pending', 'InProgress'], true)) {
            $returned = ($lastAction->action ?? '') === 'Returned';
            return array_replace($state, ['status_key' => $returned ? 'returned' : 'awaiting',
                'stage' => $returned ? "Returned to level {$level} of {$total}"
                    : ($level === $total ? "Awaiting final approval — level {$level} of {$total}" : "Awaiting level {$level} of {$total}"),
                'status_note' => $returned ? (string) ($lastAction->comments ?? '') : '']);
        }
        if (($item->status ?? '') === 'Approved') {
            return array_replace($state, ['status_key' => 'processing', 'stage' => 'Finalising approval', 'status_note' => '']);
        }
        return $state;
    }

    public function present(Collection $returns, ?array $allowedPropertyCodes, ?int $configuredLevels): array
    {
        $masters = $returns->where('return_type', 'MASTERRETURN');
        $peerMasters = collect();
        $pool = $returns->where('return_type', '!=', 'MASTERRETURN')->keyBy('id');
        if ($masters->isNotEmpty()) {
            $peerMasters = ReturnSummary::where('return_type', 'MASTERRETURN')->where(function ($q) use ($masters) {
                foreach ($masters as $master) {
                    $q->orWhere(fn ($peer) => $peer->where('landlord_code', $master->landlord_code)
                        ->where('year', $master->year)->where('month', $master->month));
                }
            })->get(['return_code', 'landlord_code', 'year', 'month']);
            $listed = $masters->flatMap(fn ($m) => $this->codes($m->transaction_ref))->unique();
            $related = ReturnSummary::whereIn('return_type', ['MAINRETURN', 'UTILITYRETURN'])
                ->where(function ($q) use ($masters, $listed) {
                    $q->whereIn('parent_return_code', $masters->pluck('return_code'))->orWhereIn('return_code', $listed);
                    foreach ($masters as $master) {
                        if (!$this->codes($master->transaction_ref)) {
                            $q->orWhere(fn ($legacy) => $legacy->where('landlord_code', $master->landlord_code)
                                ->where('year', $master->year)->where('month', $master->month));
                        }
                    }
                })->get();
            $pool = $pool->union($related->keyBy('id'));
        }

        // Explicit parent links take precedence over CSV references. Infer a legacy parent's membership only when
        // there is exactly one possible parent of that kind for the landlord and period.
        $parents = [];
        $ambiguous = [];
        $childrenByMaster = [];
        foreach ($pool as $child) {
            if (!in_array($child->return_type, ['MAINRETURN', 'UTILITYRETURN'], true)) continue;
            $kind = $child->return_type === 'UTILITYRETURN';
            $candidates = $masters->filter(fn ($m) => (string) $child->parent_return_code === (string) $m->return_code);
            if ($candidates->isEmpty() && !$child->parent_return_code) {
                $candidates = $masters->filter(fn ($m) => in_array($child->return_code, $this->codes($m->transaction_ref), true));
            }
            if ($candidates->isEmpty() && !$child->parent_return_code) {
                $candidates = $masters->filter(fn ($m) => !$this->codes($m->transaction_ref)
                    && $m->landlord_code === $child->landlord_code && (int) $m->year === (int) $child->year
                    && (int) $m->month === (int) $child->month && str_starts_with($m->return_code, 'RU') === $kind);
                if ($candidates->isNotEmpty() && $peerMasters->filter(fn ($m) => $m->landlord_code === $child->landlord_code
                    && (int) $m->year === (int) $child->year && (int) $m->month === (int) $child->month
                    && str_starts_with($m->return_code, 'RU') === $kind)->count() > 1) {
                    $ambiguous[$child->id] = true;
                    continue;
                }
            }
            if ($candidates->count() === 1) {
                $parent = $candidates->first();
                $parents[$child->id] = $parent->return_code;
                $childrenByMaster[$parent->id][] = $child;
            } elseif ($candidates->count() > 1) {
                $ambiguous[$child->id] = true;
            }
        }

        $visiblePool = $pool->filter(fn ($r) => $allowedPropertyCodes === null || in_array($r->property_code, $allowedPropertyCodes, true));
        $visible = $returns->keyBy('id')->union($visiblePool);
        $propertyNames = DB::table('sys_pm_properties')->whereIn('reference_number', $visiblePool->pluck('property_code'))
            ->pluck('property_name', 'reference_number');
        $clientNames = DB::table('sys_pm_clients')->whereIn('reference_number', $visible->pluck('landlord_code'))
            ->pluck('names', 'reference_number');
        $all = $returns->keyBy('id')->union($pool);
        $items = DB::table('cb_approval_items')->where('item_type', 'return_summary')->whereIn('item_id', $all->keys())
            ->get(['id', 'item_id', 'status', 'current_level', 'total_levels'])->keyBy('item_id');
        $actions = DB::table('cb_approval_actions')->whereIn('approval_item_id', $items->pluck('id'))
            ->whereIn('action', ['Approved', 'Returned', 'Rejected', 'Initiated', 'Forwarded'])
            ->orderByDesc('sequence_number')->orderByDesc('id')->get(['approval_item_id', 'action', 'comments'])
            ->unique('approval_item_id')->keyBy('approval_item_id');
        $reasons = $this->notPayableSummaries($visiblePool->pluck('return_code')->all());
        $rows = [];
        foreach ($returns as $return) {
            if ($return->return_type !== 'MASTERRETURN') {
                if ($allowedPropertyCodes !== null && !in_array($return->property_code, $allowedPropertyCodes, true)) continue;
                if (isset($parents[$return->id])) continue;
                $row = $this->row($return, $items, $actions, $configuredLevels, $propertyNames, $clientNames, $reasons);
                if (isset($ambiguous[$return->id])) {
                    $row['is_component'] = true;
                    $row['include_in_total'] = false;
                    $row['membership_note'] = 'Merged membership needs review';
                }
                $rows[] = $row;
                continue;
            }
            $children = collect($childrenByMaster[$return->id] ?? []);
            $allowedChildren = $children->filter(fn ($c) => $visiblePool->has($c->id));
            $row = $this->row($return, $items, $actions, $configuredLevels, $propertyNames, $clientNames, $reasons);
            $row['group'] = 'merged';
            $row['current_level'] = null;
            $row['total_levels'] = null;
            $row['children'] = $allowedChildren->sortBy('property_code')->map(fn ($child) =>
                $this->row($child, $items, $actions, $configuredLevels, $propertyNames, $clientNames, $reasons))->values()->all();
            $row['property_codes'] = $allowedChildren->pluck('property_code')->unique()->values()->all();
            $row['property_name'] = $allowedChildren->map(fn ($c) => $propertyNames[$c->property_code] ?? $c->property_code)->unique()->implode(', ');
            $row['child_count'] = $children->count();
            $row['hidden_child_count'] = $children->count() - $allowedChildren->count();
            $row['approved_children'] = $children->filter(fn ($c) => (int) $c->approved_status === 1 || (int) $c->paid_status === 1)->count();
            $row['approval_summary'] = $children->isEmpty() ? 'Property links need review'
                : $row['approved_children'] . ' of ' . $children->count() . ' property returns approved';
            // A master has no approval level of its own: it follows its individual property returns.
            if ((int) $return->approved_status !== 1) {
                $row = array_replace($row, ['status_key' => $children->isEmpty() ? 'review' : 'awaiting',
                    'stage' => $children->isEmpty() ? 'Needs review' : ($row['approved_children'] === $children->count() ? 'Awaiting merged finalisation' : 'Awaiting property approvals'),
                    'status_note' => '', 'current_level' => null, 'total_levels' => null, 'is_draft' => false]);
            }
            $rows[] = $row;
        }
        return ['data' => $rows, 'count' => count($rows), 'properties' => $propertyNames->map(fn ($name, $code) => ['code' => $code, 'name' => $name])
            ->sortBy(fn ($p) => strtolower($p['name']))->values()->all()];
    }

    private function row($return, $items, $actions, ?int $levels, $propertyNames, $clientNames, array $reasons): array
    {
        $item = $items->get($return->id);
        $state = self::approvalState($return, $item, $item ? $actions->get($item->id) : null, $levels);
        $component = !empty($return->parent_return_code) || (bool) $return->is_merged;
        return $state + ['id' => $return->id, 'encrypted_id' => encrypt($return->id), 'return_code' => $return->return_code,
            'client_name' => ($clientNames[$return->landlord_code] ?? '-') . ' (' . preg_replace('/^LAN-?/i', '', $return->landlord_code ?? '') . ')',
            'landlord_code' => $return->landlord_code, 'property_code' => $return->property_code,
            'property_codes' => array_values(array_filter([$return->property_code])),
            'property_name' => $propertyNames[$return->property_code] ?? $return->property_code ?? '-',
            'return_type' => $return->return_type, 'group' => 'individual', 'children' => [],
            'net_amount' => number_format($return->net_amount ?? 0, 2), 'net_amount_raw' => (float) $return->net_amount,
            'payable_units' => ($return->payable_units ?? 0) . '/' . ($return->total_units ?? 0),
            'withheld' => number_format($return->non_payable_amount ?? 0, 2), 'not_payable_reason' => $reasons[$return->return_code] ?? '',
            'is_component' => $component, 'parent_return_code' => $return->parent_return_code,
            'include_in_total' => !$component, 'approved_status' => (int) $return->approved_status];
    }

    private function codes(?string $ref): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $ref))));
    }

    private function notPayableSummaries(array $codes): array
    {
        $summaries = [];
        foreach (array_chunk(array_unique($codes), 500) as $chunk) {
            $lines = DB::table('cb_returns_clients')->whereIn('return_code', $chunk)->where('dr_cr', 'CR')
                ->where(fn ($q) => $q->where('payable_status', 0)->orWhereIn('payable_status', [34, 35, 36, 37]))
                ->get(['return_code', 'entry_type', 'payable_status', 'payable_status_reason', 'unit_id'])->groupBy('return_code');
            foreach ($lines as $code => $group) {
                $held = $group->where('payable_status', 0);
                $rent = $held->where('entry_type', 'RENT CURRENT')->pluck('unit_id')->unique()->count();
                $bills = $held->where('entry_type', '!=', 'RENT CURRENT')->count();
                $parts = [];
                if ($rent) $parts[] = $rent . ($rent === 1 ? ' unit' : ' units') . ' held back: previous rent invoice under 85% paid';
                foreach ($group->filter(fn ($l) => (int) $l->payable_status !== 0)->groupBy('payable_status_reason') as $reason => $marked) {
                    $parts[] = $marked->count() . ($marked->count() === 1 ? ' unit' : ' units') . ' marked ' . ($reason ?: 'recovered');
                }
                if ($bills) $parts[] = $bills . ($bills === 1 ? ' bill' : ' bills') . ' not paid by the tenant yet';
                if ($parts) $summaries[$code] = implode('; ', $parts);
            }
        }
        return $summaries;
    }
}

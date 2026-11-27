<?php

namespace App\Services;

use App\Exceptions\ReturnApprovalException;
use App\Models\ReturnSummary;
use Illuminate\Support\Facades\DB;

/** An explicit final approval, with its own audit entry; it never records skipped reviews as completed. */
class DirectReturnApprovalService
{
    public function __construct(private ReturnAccessService $access, private ReturnApprovalWorkflowService $workflow, private ReturnPostingService $posting) {}

    public function approve(int $returnId, int $userId, ?string $comments, ?string $ip): void
    {
        DB::transaction(function () use ($returnId, $userId, $comments, $ip) {
            if (!$this->access->canApproveDirectly($userId)) throw new ReturnApprovalException('Only directors and the chief accountant can approve returns directly.');
            $return = ReturnSummary::whereKey($returnId)->lockForUpdate()->firstOrFail();
            if ($return->return_type === 'MASTERRETURN' || !$return->property_code) {
                throw new ReturnApprovalException('Approve the individual property returns first. A merged return cannot bypass their approvals.');
            }
            if ((int) $return->approved_status === 1 || (int) $return->paid_status === 1 || (int) $return->voucher_generated === 1) {
                throw new ReturnApprovalException('This return is already approved, paid or committed to a payment voucher.');
            }
            $item = DB::table('cb_approval_items')->where('item_type', ReturnApprovalWorkflowService::ITEM_TYPE)
                ->where('item_id', $returnId)->lockForUpdate()->first();
            try {
                $total = $this->workflow->totalLevels();
            } catch (ReturnApprovalException $e) {
                $total = max(1, (int) ($item->total_levels ?? 1));
            }
            $fromLevel = (int) ($item->current_level ?? 0);
            if ($item && $item->item_reference !== $return->return_code) throw new ReturnApprovalException('Approval records do not match this return. Review the return before approving.');
            if (!$item) {
                $itemId = DB::table('cb_approval_items')->insertGetId([
                    'item_type' => ReturnApprovalWorkflowService::ITEM_TYPE, 'item_id' => $returnId, 'item_reference' => $return->return_code,
                    'status' => 'Pending', 'current_level' => $total, 'total_levels' => $total,
                    'initiated_by' => $userId, 'initiated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $itemId = (int) $item->id;
            }
            $actorId = $this->workflow->recordActor($itemId, $total, $userId, 'Approved');
            DB::table('cb_approval_actions')->insert([
                'approval_item_id' => $itemId, 'item_approver_id' => $actorId, 'actor_id' => $userId,
                'action' => 'Approved', 'from_level' => $fromLevel, 'to_level' => $total,
                'comments' => 'Direct final approval (director / chief accountant).' . ($comments ? ' ' . $comments : ''),
                'action_timestamp' => now(), 'sequence_number' => (int) DB::table('cb_approval_actions')->where('approval_item_id', $itemId)->max('sequence_number') + 1,
                'is_current' => 1, 'ip_address' => $ip, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cb_approval_items')->where('id', $itemId)->update([
                'status' => 'Approved', 'current_level' => $total, 'total_levels' => $total,
                'approved_by' => $userId, 'approved_at' => now(), 'updated_at' => now(),
            ]);
            $return->update(['approved_status' => 1, 'approved_by' => DB::table('josya_users')->where('id', $userId)->value('name'), 'approved_date' => now()]);
            $this->posting->postApproval($return->fresh(), $userId);
            $this->posting->finaliseMasterIfReady($return->fresh(), $userId);
        });
    }
}

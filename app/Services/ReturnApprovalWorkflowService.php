<?php

namespace App\Services;

use App\Exceptions\ReturnApprovalException;
use Illuminate\Support\Facades\DB;

/**
 * Who approves a client return: the active approvers of the 'returns_approval' workflow (Accounting > Approvals),
 * level by level. The last level is the final approval. Any approver listed at a level can approve that level.
 *
 * A return that is waiting for approval has one row in cb_approval_items (total_levels = the workflow's levels) and
 * one row in cb_item_approvers per approver of the level it has reached.
 */
class ReturnApprovalWorkflowService
{
    const WORKFLOW_NAME = 'returns_approval';
    const ITEM_TYPE = 'return_summary';

    /**
     * Read the workflow's levels from the database.
     *
     * @return array level number => list of approver user ids
     */
    public function levels(): array {
        $workflow = $this->workflow();

        $rows = DB::table('workflow_approvers')
            ->where('workflow_id', $workflow->id)
            ->where('is_active', 1)
            ->orderBy('approval_level')
            ->get(['approval_level', 'approver_id'])
            ->map(fn ($row) => [(int) $row->approval_level, (int) $row->approver_id])
            ->all();

        return self::validateLevels($rows);
    }

    /**
     * Turn the workflow's approver rows into levels, and refuse a workflow that cannot run: no approvers, or levels
     * that skip a number (a return would stop at the gap with nobody to approve it).
     *
     * @param array $rows list of [level, approver user id]
     * @return array level number => list of approver user ids, in level order
     */
    public static function validateLevels(array $rows): array {
        $levels = [];
        foreach ($rows as [$level, $approverId]) {
            if ($level < 1 || $approverId < 1) {
                continue;
            }
            $levels[$level][] = $approverId;
        }

        if (empty($levels)) {
            throw new ReturnApprovalException("The '" . self::WORKFLOW_NAME . "' approval workflow has no active approvers. Add them under Accounting > Approvals, then generate the returns again.");
        }

        ksort($levels);
        $expected = 1;
        foreach (array_keys($levels) as $level) {
            if ($level !== $expected) {
                throw new ReturnApprovalException("Level {$expected} of the '" . self::WORKFLOW_NAME . "' approval workflow has no approver. Add one under Accounting > Approvals, then generate the returns again.");
            }
            $expected++;
        }

        return $levels;
    }

    /**
     * Refuse to go on when the returns approval workflow is missing, inactive or incomplete.
     */
    public function assertConfigured(): void {
        $this->levels();
    }

    /**
     * The number of levels a new return will go through.
     */
    public function totalLevels(): int {
        return count($this->levels());
    }

    /**
     * Give a return's approval item the approvers of one level of the workflow. An approver already on the item (from
     * an earlier round, after a return to this level) is put back to Pending so they can act again.
     *
     * @param object $approvalItem the cb_approval_items row
     * @param int $level the level that is now up for approval
     */
    public function addApproversForLevel(object $approvalItem, int $level): void {
        $approvers = $this->levels()[$level] ?? null;
        if (empty($approvers)) {
            throw new ReturnApprovalException("Level {$level} of the '" . self::WORKFLOW_NAME . "' approval workflow has no approver, so this return cannot move on. Add an approver for level {$level} under Accounting > Approvals. If the workflow was changed after this return was sent for approval, generate the returns again.");
        }

        foreach ($approvers as $approverId) {
            $existing = DB::table('cb_item_approvers')
                ->where('approval_item_id', $approvalItem->id)
                ->where('approver_level', $level)
                ->where('approver_id', $approverId)
                ->first();

            if ($existing) {
                DB::table('cb_item_approvers')->where('id', $existing->id)
                    ->update(['approval_status' => 'Pending', 'actioned_at' => null, 'updated_at' => now()]);
                continue;
            }

            DB::table('cb_item_approvers')->insert([
                'approval_item_id' => $approvalItem->id,
                'approver_id' => $approverId,
                'approver_level' => $level,
                'is_optional' => 0,
                'approval_status' => 'Pending',
                'assigned_at' => now(),
                'actioned_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Send the unapproved returns of a period for approval: one approval item per return, with the level 1 approvers of the
     * workflow and an Initiated action. An item left behind by a return that no longer exists, whose id a new return has
     * since taken, is replaced; the check is the return code stored on the item.
     *
     * @param string $returnPeriod YYYYMM
     * @param int $userId the user who generated the returns
     * @param string|null $ipAddress the generating user's address, for the action log
     * @param int|null $propertyId only this property's return, or every return of the period when null
     * @return int the number of approval items created
     */
    public function createItems(string $returnPeriod, int $userId, ?string $ipAddress, ?int $propertyId = null): int {
        $levels = $this->levels();
        $workflowId = (int) $this->workflow()->id;
        $propertyFilter = $propertyId ? " AND rs.property_code = (SELECT reference_number FROM sys_pm_properties WHERE id = ?)" : "";
        $filterBindings = $propertyId ? [$propertyId] : [];

        // Replace stale items: same return id, different return code
        $staleIds = "SELECT ai.id FROM cb_approval_items ai
                JOIN cb_returns_summary rs ON rs.id = ai.item_id
                WHERE ai.item_type = '" . self::ITEM_TYPE . "' AND rs.return_period = ? AND ai.item_reference <> rs.return_code {$propertyFilter}";
        $staleBindings = array_merge([$returnPeriod], $filterBindings);
        DB::delete("DELETE FROM cb_approval_actions WHERE approval_item_id IN ({$staleIds})", $staleBindings);
        DB::delete("DELETE FROM cb_item_approvers WHERE approval_item_id IN ({$staleIds})", $staleBindings);
        DB::delete("DELETE FROM cb_approval_items WHERE id IN ({$staleIds})", $staleBindings);

        $created = DB::affectingStatement("
            INSERT INTO cb_approval_items (
                item_type, item_id, item_reference, status,
                current_level, total_levels, initiated_by, initiated_at,
                created_at, updated_at
            )
            SELECT '" . self::ITEM_TYPE . "', rs.id, rs.return_code, 'Pending', 1, ?, ?, NOW(), NOW(), NOW()
            FROM cb_returns_summary rs
            WHERE rs.return_period = ?
              AND rs.approved_status = 0
              AND EXISTS (SELECT 1 FROM sys_pm_properties p WHERE p.reference_number = rs.property_code)
              {$propertyFilter}
              AND NOT EXISTS (
                  SELECT 1 FROM cb_approval_items ai
                  WHERE ai.item_type = '" . self::ITEM_TYPE . "' AND ai.item_id = rs.id
              )
        ", array_merge([count($levels), $userId, $returnPeriod], $filterBindings));

        // Level 1 approvers, from the workflow, for every item of the period that has none yet
        foreach ($levels[1] as $approverId) {
            DB::statement("
                INSERT INTO cb_item_approvers (
                    approval_item_id, approver_id, approver_level,
                    is_optional, approval_status, assigned_at,
                    created_at, updated_at
                )
                SELECT ai.id, ?, 1, 0, 'Pending', NOW(), NOW(), NOW()
                FROM cb_approval_items ai
                JOIN cb_returns_summary rs ON rs.id = ai.item_id AND ai.item_type = '" . self::ITEM_TYPE . "' AND rs.return_code = ai.item_reference
                WHERE rs.return_period = ?
                  AND ai.current_level = 1
                  {$propertyFilter}
                  AND NOT EXISTS (
                      SELECT 1 FROM cb_item_approvers ia
                      WHERE ia.approval_item_id = ai.id AND ia.approver_level = 1 AND ia.approver_id = ?
                  )
            ", array_merge([$approverId, $returnPeriod], $filterBindings, [$approverId]));
        }

        // One Initiated action per item, on its first level 1 approver
        DB::statement("
            INSERT INTO cb_approval_actions (
                item_approver_id, approval_item_id, actor_id,
                from_level, to_level, action, comments,
                action_timestamp, ip_address, sequence_number, is_current,
                created_at, updated_at
            )
            SELECT MIN(ia.id), ai.id, ?, 0, 1, 'Initiated', 'Return submitted for approval', NOW(), ?, 1, 1, NOW(), NOW()
            FROM cb_item_approvers ia
            JOIN cb_approval_items ai ON ai.id = ia.approval_item_id
            JOIN cb_returns_summary rs ON rs.id = ai.item_id AND ai.item_type = '" . self::ITEM_TYPE . "' AND rs.return_code = ai.item_reference
            WHERE rs.return_period = ?
              AND ia.approver_level = 1
              {$propertyFilter}
              AND NOT EXISTS (
                  SELECT 1 FROM cb_approval_actions aa
                  WHERE aa.approval_item_id = ai.id AND aa.action = 'Initiated'
              )
            GROUP BY ai.id
        ", array_merge([$userId, $ipAddress, $returnPeriod], $filterBindings));

        return $created;
    }

    /**
     * The active returns approval workflow, or an error that says how to set it up.
     */
    protected function workflow(): object {
        $workflow = DB::table('approval_workflows')
            ->where('workflow_name', self::WORKFLOW_NAME)
            ->where('status', 1)
            ->first();

        if (!$workflow) {
            throw new ReturnApprovalException("The '" . self::WORKFLOW_NAME . "' approval workflow is missing or inactive. Set it up under Accounting > Approvals, then generate the returns again.");
        }

        return $workflow;
    }
}

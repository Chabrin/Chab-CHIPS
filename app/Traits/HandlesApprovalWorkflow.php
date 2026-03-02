<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait HandlesApprovalWorkflow
{
    /**
     * Add next level approver when current level approves
     * 
     * @param object $approvalItem The approval item record
     * @param object $batch The batch being approved
     * @param int $currentLevel The level that just approved
     * @return void
     */
    protected function addNextLevelApprover($approvalItem, $batch, $currentLevel)
    {
        // Only proceed if not final level
        if ($currentLevel >= $approvalItem->total_levels) {
            return;
        }

        $nextLevel = $currentLevel + 1;
        $nextApproverId = null;

        // Get property with field officer and zone manager
        $property = DB::table('sys_pm_properties')
            ->where('id', $batch->property_id)
            ->select('field_officer_id', 'zone_manager_id')
            ->first();

        if (!$property) {
            Log::warning("Property not found for batch", [
                'batch_id' => $batch->id,
                'property_id' => $batch->property_id
            ]);
            return;
        }

        // Determine next approver based on level
        switch ($nextLevel) {
            case 1:
                $nextApproverId = $property->field_officer_id;
                break;
            case 2:
                $nextApproverId = $property->zone_manager_id;
                break;
            case 3:
                // Get any one user with clerical_officers role
                $clericalOfficer = DB::table('josya_users as u')
                    ->join('cb_role_user as ru', 'ru.user_id', '=', 'u.id')
                    ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                    ->where('r.name', 'clerical_officers')
                    ->select('u.id')
                    ->first();
                $nextApproverId = $clericalOfficer ? $clericalOfficer->id : null;
                break;
            case 4:
                // Get any one user with accountants role (final approver)
                $accountant = DB::table('josya_users as u')
                    ->join('cb_role_user as ru', 'ru.user_id', '=', 'u.id')
                    ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                    ->where('r.name', 'accountants')
                    ->select('u.id')
                    ->first();
                $nextApproverId = $accountant ? $accountant->id : null;
                break;
        }

        if (!$nextApproverId) {
            Log::warning("Next approver not found for level", [
                'batch_id' => $batch->id,
                'property_id' => $batch->property_id,
                'next_level' => $nextLevel
            ]);
            return;
        }

        // Check if approver already exists for this level
        $existingApprover = DB::table('cb_item_approvers')
            ->where('approval_item_id', $approvalItem->id)
            ->where('approver_level', $nextLevel)
            ->where('approver_id', $nextApproverId)
            ->first();

        if ($existingApprover) {
            // Approver exists from previous cycle - reset their status to Pending
            // so they can approve/reject again
            DB::table('cb_item_approvers')
                ->where('id', $existingApprover->id)
                ->update([
                    'approval_status' => 'Pending',
                    'actioned_at' => null,
                    'updated_at' => now()
                ]);

            Log::info("Reset existing approver to Pending for next cycle", [
                'approval_item_id' => $approvalItem->id,
                'level' => $nextLevel,
                'approver_id' => $nextApproverId,
                'item_approver_id' => $existingApprover->id
            ]);
            return;
        }

        // Insert new approver for next level
        DB::table('cb_item_approvers')->insert([
            'approval_item_id' => $approvalItem->id,
            'approver_id' => $nextApproverId,
            'approver_level' => $nextLevel,
            'is_optional' => 0,
            'approval_status' => 'Pending',
            'assigned_at' => now(),
            'actioned_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Log::info("Added next level approver", [
            'approval_item_id' => $approvalItem->id,
            'level' => $nextLevel,
            'approver_id' => $nextApproverId,
            'batch_id' => $batch->id
        ]);
    }

    /**
     * Initialize approval action for new approver
     * 
     * @param int $itemApproverId The item_approver ID
     * @param int $approvalItemId The approval_item ID
     * @param int $level The approver level
     * @return void
     */
    protected function initializeApprovalAction($itemApproverId, $approvalItemId, $level)
    {
        // Insert initial action record
        DB::table('cb_approval_actions')->insert([
            'item_approver_id' => $itemApproverId,
            'approval_item_id' => $approvalItemId,
            'actor_id' => auth()->id(),
            'from_level' => $level - 1,
            'to_level' => $level,
            'action' => 'Initiated',
            'comments' => 'Forwarded to Level ' . $level,
            'action_timestamp' => now(),
            'ip_address' => request()->ip(),
            'sequence_number' => DB::table('cb_approval_actions')
                ->where('approval_item_id', $approvalItemId)
                ->max('sequence_number') + 1,
            'is_current' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}

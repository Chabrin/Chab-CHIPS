<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Explicit, repeatable setup for the business's return-review roles; never approves a return. */
class ConfigureReturnApprovalPolicy extends Command
{
    protected $signature = 'returns:configure-approval-policy {--apply : Apply the reviewed workflow configuration}';
    protected $description = 'Configure zone manager, internal auditor, then directors/accountant for client returns';

    public function handle(): int
    {
        $this->table(['Level', 'Approver'], [[1, 'Property zone manager'], [2, 'Internal auditor'], [3, 'Directors / accountant (final)']]);
        if (!$this->option('apply')) {
            $this->info('Preview only. Use --apply to update the returns_approval workflow.');
            return self::SUCCESS;
        }
        DB::transaction(function () {
            $workflow = DB::table('approval_workflows')->where('workflow_name', 'returns_approval')->where('status', 1)->lockForUpdate()->first();
            if (!$workflow) throw new \RuntimeException('The active returns_approval workflow must exist before applying this policy.');
            $roles = DB::table('cb_roles')->whereIn('name', ['internal_auditor', 'directors', 'accountant'])->pluck('id', 'name');
            if ($roles->count() !== 3) throw new \RuntimeException('The internal_auditor, directors and accountant roles must exist.');
            $desired = [
                ['approval_level' => 1, 'position_type' => 'zone_manager', 'role_id' => null, 'approver_id' => null, 'is_final' => 0],
                ['approval_level' => 2, 'position_type' => 'role', 'role_id' => $roles['internal_auditor'], 'approver_id' => null, 'is_final' => 0],
                ['approval_level' => 3, 'position_type' => 'role', 'role_id' => $roles['directors'], 'approver_id' => null, 'is_final' => 1],
                ['approval_level' => 3, 'position_type' => 'role', 'role_id' => $roles['accountant'], 'approver_id' => null, 'is_final' => 1],
            ];
            $before = DB::table('workflow_approvers')->where('workflow_id', $workflow->id)->where('is_active', 1)->get();
            $normalise = fn ($rows) => collect($rows)->map(fn ($row) => [
                (int) $row->approval_level, $row->position_type, $row->role_id ? (int) $row->role_id : null,
                $row->approver_id ? (int) $row->approver_id : null, (int) $row->is_final,
            ])->sort()->values()->all();
            if ($normalise($before) === $normalise(array_map(fn ($row) => (object) $row, $desired))) return;
            DB::table('workflow_approvers')->where('workflow_id', $workflow->id)->where('is_active', 1)->update(['is_active' => 0]);
            foreach ($desired as $row) {
                DB::table('workflow_approvers')->updateOrInsert([
                    'workflow_id' => $workflow->id, 'approval_level' => $row['approval_level'],
                    'position_type' => $row['position_type'], 'role_id' => $row['role_id'], 'approver_id' => null,
                ], $row + ['is_active' => 1, 'added_on' => now()]);
            }
            DB::table('workflow_approvers_changes_log')->insert([
                'workflow_id' => $workflow->id, 'action' => 'Return policy configured', 'changed_on' => now(),
                'details' => json_encode(['previous_active_approvers' => $before, 'new_active_approvers' => $desired]),
            ]);
        });
        $this->info('Return review policy applied. Existing approval history and return statuses were preserved.');
        return self::SUCCESS;
    }
}

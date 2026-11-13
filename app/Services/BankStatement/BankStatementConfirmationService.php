<?php

namespace App\Services\BankStatement;

use App\Events\BankStatementConfirmed;
use App\Exceptions\BankStatementException;
use Illuminate\Support\Facades\DB;

/**
 * Who may confirm an uploaded statement, and the confirmation itself. Confirming only marks the upload and announces
 * it with an event; the matching runs from a queued listener so the person who clicks Confirm gets their answer at once
 * while the work goes on in the background. The confirming roles are kept as approvers of the
 * bank_statement_confirmation workflow, the same place the Approvals page shows.
 */
class BankStatementConfirmationService
{
    const WORKFLOW_NAME = 'bank_statement_confirmation';

    /**
     * The seats of the confirmer workflow: the named users and the roles whose members may confirm.
     *
     * @return array{users: int[], roles: int[]}
     */
    public function seats(): array {
        $rows = DB::table('workflow_approvers as a')
            ->join('approval_workflows as w', 'w.id', '=', 'a.workflow_id')
            ->where('w.workflow_name', self::WORKFLOW_NAME)
            ->where('a.is_active', 1)
            ->get(['a.approver_id', 'a.position_type', 'a.role_id']);

        $seats = ['users' => [], 'roles' => []];

        foreach ($rows as $row) {
            if ($row->position_type === 'role' && (int) $row->role_id > 0) {
                $seats['roles'][] = (int) $row->role_id;
            } elseif ($row->position_type === null && (int) $row->approver_id > 0) {
                $seats['users'][] = (int) $row->approver_id;
            }
        }

        return $seats;
    }

    /**
     * A readable name for who confirms, for the screens.
     */
    public function label(): string {
        $seats = $this->seats();
        $names = [];

        if ($seats['roles']) {
            $names = array_merge($names, DB::table('cb_roles')->whereIn('id', $seats['roles'])->get(['name', 'display_name'])->map(fn ($role) => $role->display_name ?: $role->name)->all());
        }
        if ($seats['users']) {
            $names = array_merge($names, DB::table('josya_users')->whereIn('id', $seats['users'])->pluck('name')->all());
        }

        return $names ? implode(', ', $names) : 'nobody (not set)';
    }

    /**
     * The roles that can be chosen as confirmers, for the setting on the page.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function roleOptions(): array {
        return DB::table('cb_roles')->orderBy('display_name')->get(['id', 'name', 'display_name'])
            ->map(fn ($role) => ['id' => (int) $role->id, 'name' => $role->display_name ?: $role->name])
            ->all();
    }

    /**
     * Make these roles the confirmers. Whoever held a seat before (named users or other roles) is removed, and every
     * removal and addition goes into the workflow change log the Approvals page shows. Saving the same roles again
     * changes nothing.
     *
     * @param int[] $roleIds the roles whose members may confirm
     * @param int $userId the person changing the setting
     */
    public function setConfirmerRoles(array $roleIds, int $userId): void {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        if (!$roleIds) {
            throw new BankStatementException('Choose at least one role that confirms.');
        }

        $roles = DB::table('cb_roles')->whereIn('id', $roleIds)->get(['id', 'name'])->keyBy('id');
        if ($roles->count() !== count($roleIds)) {
            throw new BankStatementException('One of those roles does not exist.');
        }

        $workflowId = DB::table('approval_workflows')->where('workflow_name', self::WORKFLOW_NAME)->value('id');
        if (!$workflowId) {
            throw new BankStatementException('The bank statement confirmation workflow is missing. Run the migrations.');
        }

        DB::transaction(function () use ($workflowId, $roleIds, $roles, $userId) {
            $current = DB::table('workflow_approvers')->where('workflow_id', $workflowId)->get();
            $currentRoles = $current->where('position_type', 'role')->where('is_active', 1)->pluck('role_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $wanted = collect($roleIds)->sort()->values()->all();

            if ($current->count() === count($wanted) && $currentRoles === $wanted) {
                return;
            }

            $log = fn (string $action, string $details, ?int $oldLevel, ?int $newLevel) => DB::table('workflow_approvers_changes_log')->insert([
                'workflow_id' => $workflowId,
                'approver_id' => null,
                'action' => $action,
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
                'old_is_final' => null,
                'new_is_final' => $newLevel ? 1 : null,
                'details' => $details,
                'changed_by' => $userId,
                'changed_on' => now(),
            ]);

            foreach ($current as $row) {
                $name = $row->position_type === 'role'
                    ? 'role "' . DB::table('cb_roles')->where('id', $row->role_id)->value('name') . '"'
                    : '"' . DB::table('josya_users')->where('id', $row->approver_id)->value('name') . '"';
                $log('APPROVER_REMOVED', 'Removed ' . $name . ' as bank statement confirmer (from the Bank Statements page)', (int) $row->approval_level, null);
            }

            DB::table('workflow_approvers')->where('workflow_id', $workflowId)->delete();

            foreach ($roleIds as $roleId) {
                DB::table('workflow_approvers')->insert([
                    'workflow_id' => $workflowId,
                    'approver_id' => null,
                    'approval_level' => 1,
                    'is_final' => 1,
                    'is_active' => 1,
                    'added_by' => $userId,
                    'added_on' => now(),
                    'position_type' => 'role',
                    'role_id' => $roleId,
                ]);
                $log('APPROVER_ADDED', 'Added role "' . $roles[$roleId]->name . '" as bank statement confirmer (from the Bank Statements page)', null, 1);
            }
        });
    }

    /**
     * Whether a user holds a confirmer seat, by name or through a role.
     *
     * @param int $userId
     */
    public function canConfirm(int $userId): bool {
        $seats = $this->seats();

        if (in_array($userId, $seats['users'], true)) {
            return true;
        }

        return $seats['roles'] && DB::table('cb_role_user')->where('user_id', $userId)->whereIn('role_id', $seats['roles'])->exists();
    }

    /**
     * Confirm an uploaded statement and start matching in the background. Whoever captured (uploaded) a statement cannot
     * confirm it: a second person has to. The upload row is locked so two people clicking Confirm cannot both start the
     * run. The event goes out after the status is saved, and the queued listener picks it up.
     *
     * @param int $uploadId
     * @param int $userId the confirmer
     */
    public function confirm(int $uploadId, int $userId): void {
        if (!$this->canConfirm($userId)) {
            throw new BankStatementException('Only ' . $this->label() . ' can confirm a bank statement.');
        }

        DB::transaction(function () use ($uploadId, $userId) {
            $upload = DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->lockForUpdate()->first();

            if (!$upload) {
                throw new BankStatementException('That statement does not exist.');
            }
            if ($upload->status !== 'Uploaded') {
                throw new BankStatementException('This statement is already ' . strtolower($upload->status) . '.');
            }
            if ((int) $upload->uploaded_by === $userId) {
                throw new BankStatementException('You captured this statement, so someone else has to confirm it.');
            }

            DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->update([
                'status' => 'Confirmed',
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        event(new BankStatementConfirmed($uploadId));
    }

    /**
     * Start matching again for a statement whose run failed. The lines already decided stay as they are.
     *
     * @param int $uploadId
     * @param int $userId the confirmer
     */
    public function retry(int $uploadId, int $userId): void {
        if (!$this->canConfirm($userId)) {
            throw new BankStatementException('Only ' . $this->label() . ' can start matching again.');
        }

        $status = DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->value('status');

        if ($status !== 'Failed') {
            throw new BankStatementException('Only a statement whose matching failed can be run again.');
        }

        event(new BankStatementConfirmed($uploadId));
    }
}

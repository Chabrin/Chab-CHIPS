<?php

namespace App\Services;

use App\Exceptions\ReturnApprovalException;
use Illuminate\Support\Facades\DB;

/**
 * Who approves a client return: the levels of the 'returns_approval' workflow (Accounting > Approvals). The last level is
 * the final approval.
 *
 * A level is made of seats. A seat is a named user (their id), the manager of the return's zone ('zone_manager') or
 * everybody holding a role ('role:ID'). Who may act is worked out when the return is opened or acted on, from the
 * workflow as it is then, so changing an approver or the holder of a position works on returns already waiting.
 *
 * A return waiting for approval has one row in cb_approval_items. cb_item_approvers only records who acted: the user who
 * generated the return (level 0) and each user who checked or approved it at a level. Nobody who has not acted is saved.
 * The person who generated a return cannot check or approve it, and nobody who acted at a level can act at a higher one.
 */
class ReturnApprovalWorkflowService
{
    const WORKFLOW_NAME = 'returns_approval';
    const ITEM_TYPE = 'return_summary';
    const SEAT_ZONE_MANAGER = 'zone_manager';
    const SEAT_ROLE_PREFIX = 'role:';

    /**
     * Read the workflow's levels from the database.
     *
     * @return array level number => list of seats (user id, 'zone_manager' or 'role:ID')
     */
    public function levels(): array {
        $workflow = $this->workflow();

        $rows = DB::table('workflow_approvers')
            ->where('workflow_id', $workflow->id)
            ->where('is_active', 1)
            ->orderBy('approval_level')
            ->orderBy('id')
            ->get(['approval_level', 'approver_id', 'position_type', 'role_id'])
            ->map(fn ($row) => [(int) $row->approval_level, (int) $row->approver_id, $row->position_type, (int) $row->role_id])
            ->all();

        return self::validateLevels($rows);
    }

    /**
     * Turn the workflow's approver rows into levels, and refuse a workflow that cannot run: no approvers, or levels
     * that skip a number (a return would stop at the gap with nobody to approve it).
     *
     * @param array $rows list of [level, approver user id, position type or null, role id]
     * @return array level number => list of seats, in level order
     */
    public static function validateLevels(array $rows): array {
        $levels = [];
        foreach ($rows as $row) {
            $level = (int) ($row[0] ?? 0);
            $seat = self::seatOf((int) ($row[1] ?? 0), $row[2] ?? null, (int) ($row[3] ?? 0));

            if ($level < 1 || $seat === null) {
                continue;
            }
            if (!in_array($seat, $levels[$level] ?? [], true)) {
                $levels[$level][] = $seat;
            }
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
     * The seat a workflow approver row stands for: the user, the zone manager or a role. Null when the row has none.
     *
     * @param int $approverId the user of the row, 0 when it is a position
     * @param string|null $positionType 'zone_manager', 'role' or null for a named user
     * @param int $roleId the role of a 'role' position
     * @return int|string|null
     */
    public static function seatOf(int $approverId, ?string $positionType, int $roleId) {
        if ($positionType === self::SEAT_ZONE_MANAGER) {
            return self::SEAT_ZONE_MANAGER;
        }
        if ($positionType === 'role') {
            return $roleId > 0 ? self::SEAT_ROLE_PREFIX . $roleId : null;
        }

        return $approverId > 0 ? $approverId : null;
    }

    /**
     * Why a user may not act on a return at a level, or null when they may. Whoever generated the return cannot check or
     * approve it, and whoever acted at a lower level cannot act at a higher one.
     *
     * @param int $userId the user who wants to act
     * @param int $initiatedBy the user who generated the return
     * @param array $earlierActorIds users who already acted at a lower level of this return
     * @param bool $isEligible whether the user holds a seat of the level
     * @return string|null
     */
    public static function refusal(int $userId, int $initiatedBy, array $earlierActorIds, bool $isEligible): ?string {
        if (!$isEligible) {
            return 'You are not authorized to act on this return at this level';
        }
        if ($userId === $initiatedBy) {
            return 'You generated this return, so someone else has to check and approve it';
        }
        if (in_array($userId, array_map('intval', $earlierActorIds), true)) {
            return 'You already checked this return at a lower level, so someone else has to approve it';
        }

        return null;
    }

    /**
     * Refuse to go on when the returns approval workflow is missing, inactive or incomplete.
     */
    public function assertConfigured(): void {
        $this->levels();
    }

    /**
     * The number of levels a return goes through, as the workflow is now.
     */
    public function totalLevels(): int {
        return count($this->levels());
    }

    /**
     * The level a return is at, held to the workflow as it is now: a return that was waiting at level 3 or 4 when the
     * workflow had more levels is at the last level of a shorter workflow, so it can still be given its final approval.
     *
     * @param object $approvalItem the cb_approval_items row
     */
    public function effectiveLevel(object $approvalItem): int {
        return max(1, min((int) $approvalItem->current_level, $this->totalLevels()));
    }

    /**
     * Whether a level is the last one, which gives the final approval.
     *
     * @param int $level
     */
    public function isFinalLevel(int $level): bool {
        return $level >= $this->totalLevels();
    }

    /**
     * The users who may act on a return at a level right now.
     *
     * @param object $approvalItem the cb_approval_items row
     * @param int $level
     * @return array user ids
     */
    public function eligibleUserIds(object $approvalItem, int $level): array {
        $ids = [];
        foreach ($this->levels()[$level] ?? [] as $seat) {
            if (is_int($seat)) {
                $ids[] = $seat;
            } elseif ($seat === self::SEAT_ZONE_MANAGER) {
                $ids = array_merge($ids, $this->zoneManagerUserIds((string) $approvalItem->item_reference));
            } elseif (str_starts_with($seat, self::SEAT_ROLE_PREFIX)) {
                $ids = array_merge($ids, DB::table('cb_role_user')->where('role_id', (int) substr($seat, strlen(self::SEAT_ROLE_PREFIX)))->pluck('user_id')->map(fn ($id) => (int) $id)->all());
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A readable name for who holds a level of a return: the named users, the manager of the return's zone, or the role.
     *
     * @param object $approvalItem the cb_approval_items row
     * @param int $level
     * @return string
     */
    public function levelLabel(object $approvalItem, int $level): string {
        $labels = [];
        foreach ($this->levels()[$level] ?? [] as $seat) {
            if (is_int($seat)) {
                $labels[] = (string) (DB::table('josya_users')->where('id', $seat)->value('name') ?? ('User ' . $seat));
            } elseif ($seat === self::SEAT_ZONE_MANAGER) {
                $names = DB::table('josya_users')->whereIn('id', $this->zoneManagerUserIds((string) $approvalItem->item_reference))->pluck('name')->all();
                $labels[] = 'Zone Manager' . (empty($names) ? ' (none set)' : ' - ' . implode(', ', $names));
            } elseif (str_starts_with($seat, self::SEAT_ROLE_PREFIX)) {
                $labels[] = (string) (DB::table('cb_roles')->where('id', (int) substr($seat, strlen(self::SEAT_ROLE_PREFIX)))->value('name') ?? 'Role') . ' (role)';
            }
        }

        return implode(' / ', $labels);
    }

    /**
     * The logins of the managers of the zones a return belongs to: the zone of each of its lines, or of its property
     * when it has none. A master return belongs to the zones of every property it covers.
     *
     * @param string $returnCode
     * @return array user ids
     */
    public function zoneManagerUserIds(string $returnCode): array {
        $staffIds = DB::table('sys_set_zones')
            ->whereIn('id', $this->zoneIdsOfReturn($returnCode))
            ->whereNotNull('zone_manager_id')
            ->pluck('zone_manager_id')
            ->all();

        if (empty($staffIds)) {
            return [];
        }

        return DB::table('sys_user_staff_links')->whereIn('staff_id', $staffIds)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The zones a return belongs to.
     *
     * @param string $returnCode
     * @return array zone ids
     */
    protected function zoneIdsOfReturn(string $returnCode): array {
        $fromLines = DB::table('cb_returns_clients')->where('return_code', $returnCode)->whereNotNull('zone_id')->distinct()->pluck('zone_id')->all();
        $fromProperty = DB::table('cb_returns_summary as rs')
            ->join('sys_pm_properties as p', 'p.reference_number', '=', 'rs.property_code')
            ->where('rs.return_code', $returnCode)
            ->whereNotNull('p.zone_id')
            ->pluck('p.zone_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($fromLines, $fromProperty))));
    }

    /**
     * Why a return cannot move because nobody holds the seat of its zone: the zones with no manager, or whose manager has
     * no login. Null when the level has someone who can act.
     *
     * @param object $approvalItem the cb_approval_items row
     * @param int $level
     * @return string|null
     */
    public function blockedReason(object $approvalItem, int $level): ?string {
        if (!in_array(self::SEAT_ZONE_MANAGER, $this->levels()[$level] ?? [], true)) {
            return null;
        }

        $zones = $this->zonesWithoutManagerLogin($this->zoneIdsOfReturn((string) $approvalItem->item_reference));

        return empty($zones) ? null : $this->noManagerMessage($zones);
    }

    /**
     * The zones, among the given ones, that have no manager or whose manager has no login.
     *
     * @param array $zoneIds
     * @return array zone names
     */
    public function zonesWithoutManagerLogin(array $zoneIds): array {
        if (empty($zoneIds)) {
            return [];
        }

        return DB::table('sys_set_zones as z')
            ->whereIn('z.id', $zoneIds)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('sys_user_staff_links as l')->whereColumn('l.staff_id', 'z.zone_manager_id');
            })
            ->orderBy('z.zone_name')
            ->pluck('z.zone_name')
            ->all();
    }

    /**
     * The message that says which zones need a manager.
     *
     * @param array $zoneNames
     */
    protected function noManagerMessage(array $zoneNames): string {
        return 'Zone ' . implode(', ', $zoneNames) . ' has no zone manager with a login, so its returns cannot be checked. Set the zone manager under Zones (the person needs a login), then try again.';
    }

    /**
     * Why a user may not act on a return at a level, or null when they may.
     *
     * @param object $approvalItem the cb_approval_items row
     * @param int $level
     * @param int $userId
     * @return string|null
     */
    public function actorRefusal(object $approvalItem, int $level, int $userId): ?string {
        $isEligible = in_array($userId, $this->eligibleUserIds($approvalItem, $level), true);

        return self::refusal($userId, (int) $approvalItem->initiated_by, $this->earlierActorIds((int) $approvalItem->id, $level), $isEligible);
    }

    /**
     * Refuse to move a return to a level at which nobody could act: nobody holds the seat (a zone with no manager login,
     * an empty role), or the only holders are the person who generated the return or who already acted below.
     *
     * @param object $approvalItem the cb_approval_items row
     * @param int $level the level the return would move to
     */
    public function assertSomeoneCanAct(object $approvalItem, int $level): void {
        $excluded = array_merge([(int) $approvalItem->initiated_by], $this->earlierActorIds((int) $approvalItem->id, $level));
        $candidates = array_diff($this->eligibleUserIds($approvalItem, $level), $excluded);

        if (empty($candidates)) {
            throw new ReturnApprovalException($this->blockedReason($approvalItem, $level) ?? "Nobody can act on this return at level {$level}: the people who hold it are the one who generated the return or who already checked it. Add another person or position for level {$level} under Accounting > Approvals.");
        }
    }

    /**
     * The users who acted on a return at a level below the given one.
     *
     * @param int $approvalItemId
     * @param int $level
     * @return array user ids
     */
    public function earlierActorIds(int $approvalItemId, int $level): array {
        return DB::table('cb_item_approvers')
            ->where('approval_item_id', $approvalItemId)
            ->where('approver_level', '>=', 1)
            ->where('approver_level', '<', $level)
            ->where('approval_status', 'Approved')
            ->pluck('approver_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Save that a user acted on a return at a level, and return the row the action log points to. Acting again at the
     * same level (after the return was sent back to it) updates the same row.
     *
     * @param int $approvalItemId
     * @param int $level
     * @param int $userId
     * @param string $status Approved, Rejected or Pending (a comment only)
     * @return int the cb_item_approvers id
     */
    public function recordActor(int $approvalItemId, int $level, int $userId, string $status): int {
        $row = DB::table('cb_item_approvers')
            ->where('approval_item_id', $approvalItemId)
            ->where('approver_level', $level)
            ->where('approver_id', $userId)
            ->first();

        $actioned = $status === 'Pending' ? null : now();

        if ($row) {
            if ($status !== 'Pending') {
                DB::table('cb_item_approvers')->where('id', $row->id)->update(['approval_status' => $status, 'actioned_at' => $actioned, 'updated_at' => now()]);
            }

            return (int) $row->id;
        }

        return (int) DB::table('cb_item_approvers')->insertGetId([
            'approval_item_id' => $approvalItemId,
            'approver_id' => $userId,
            'approver_level' => $level,
            'is_optional' => 0,
            'approval_status' => $status,
            'assigned_at' => now(),
            'actioned_at' => $actioned,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Put the levels from the given one upwards back to waiting, after the return was sent back to that level.
     *
     * @param int $approvalItemId
     * @param int $level
     */
    public function reopenFrom(int $approvalItemId, int $level): void {
        DB::table('cb_item_approvers')
            ->where('approval_item_id', $approvalItemId)
            ->where('approver_level', '>=', $level)
            ->where('approver_level', '>=', 1)
            ->update(['approval_status' => 'Pending', 'actioned_at' => null, 'updated_at' => now()]);
    }

    /**
     * Refuse to generate returns for a zone that has no manager with a login, since nobody could check them.
     *
     * @param string $returnPeriod YYYYMM
     * @param int|null $propertyId only this property, or every unapproved return of the period when null
     */
    public function assertZoneManagers(string $returnPeriod, ?int $propertyId = null): void {
        $needed = false;
        foreach ($this->levels() as $seats) {
            $needed = $needed || in_array(self::SEAT_ZONE_MANAGER, $seats, true);
        }
        if (!$needed) {
            return;
        }

        $zoneIds = DB::table('cb_returns_summary as rs')
            ->join('sys_pm_properties as p', 'p.reference_number', '=', 'rs.property_code')
            ->where('rs.return_period', $returnPeriod)
            ->where('rs.approved_status', 0)
            ->whereNotNull('p.zone_id')
            ->when($propertyId, fn ($query) => $query->where('p.id', $propertyId))
            ->distinct()
            ->pluck('p.zone_id')
            ->all();

        $zones = $this->zonesWithoutManagerLogin($zoneIds);
        if (!empty($zones)) {
            throw new ReturnApprovalException($this->noManagerMessage($zones));
        }
    }

    /**
     * Send the unapproved returns of a period for approval: one approval item per return, with an Initiated action by the
     * user who generated it. Nobody is assigned: who may act is worked out from the workflow when a return is opened.
     * An item left behind by a return that no longer exists, whose id a new return has since taken, is replaced; the
     * check is the return code stored on the item.
     *
     * @param string $returnPeriod YYYYMM
     * @param int $userId the user who generated the returns
     * @param string|null $ipAddress the generating user's address, for the action log
     * @param int|null $propertyId only this property's return, or every return of the period when null
     * @param int|null $returnId only this one return (with its property), when a single return is forwarded
     * @return int the number of approval items created
     */
    public function createItems(string $returnPeriod, int $userId, ?string $ipAddress, ?int $propertyId = null, ?int $returnId = null): int {
        $this->assertZoneManagers($returnPeriod, $propertyId);

        $levelCount = $this->totalLevels();
        $propertyFilter = $propertyId ? " AND rs.property_code = (SELECT reference_number FROM sys_pm_properties WHERE id = ?)" : "";
        $filterBindings = $propertyId ? [$propertyId] : [];
        if ($returnId) {
            $propertyFilter .= " AND rs.id = ?";
            $filterBindings[] = $returnId;
        }

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
        ", array_merge([$levelCount, $userId, $returnPeriod], $filterBindings));

        // The user who generated each new return is saved at level 0, and the Initiated action points to that row
        DB::statement("
            INSERT INTO cb_item_approvers (
                approval_item_id, approver_id, approver_level,
                is_optional, approval_status, assigned_at,
                created_at, updated_at
            )
            SELECT ai.id, ai.initiated_by, 0, 0, 'Created', NOW(), NOW(), NOW()
            FROM cb_approval_items ai
            JOIN cb_returns_summary rs ON rs.id = ai.item_id AND ai.item_type = '" . self::ITEM_TYPE . "' AND rs.return_code = ai.item_reference
            WHERE rs.return_period = ?
              {$propertyFilter}
              AND NOT EXISTS (
                  SELECT 1 FROM cb_approval_actions aa
                  WHERE aa.approval_item_id = ai.id AND aa.action = 'Initiated'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM cb_item_approvers ia
                  WHERE ia.approval_item_id = ai.id AND ia.approver_level = 0
              )
        ", array_merge([$returnPeriod], $filterBindings));

        DB::statement("
            INSERT INTO cb_approval_actions (
                item_approver_id, approval_item_id, actor_id,
                from_level, to_level, action, comments,
                action_timestamp, ip_address, sequence_number, is_current,
                created_at, updated_at
            )
            SELECT MIN(ia.id), ai.id, ?, 0, 1, 'Initiated', 'Forwarded to the manager for approval', NOW(), ?, 1, 1, NOW(), NOW()
            FROM cb_item_approvers ia
            JOIN cb_approval_items ai ON ai.id = ia.approval_item_id
            JOIN cb_returns_summary rs ON rs.id = ai.item_id AND ai.item_type = '" . self::ITEM_TYPE . "' AND rs.return_code = ai.item_reference
            WHERE rs.return_period = ?
              AND ia.approver_level = 0
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
     * Why a return cannot be forwarded to the manager, or null when it can: it must be a property return that is not
     * approved or paid and has not been forwarded already. The zone and the menu are checked by the caller.
     *
     * @param object $return a cb_returns_summary row
     * @return string|null
     */
    public function forwardRefusal(object $return): ?string {
        if (($return->return_type ?? null) === 'MASTERRETURN' || empty($return->property_code)) {
            return 'A merged return is not forwarded: it is approved when every property return in it is approved.';
        }
        if ((int) $return->approved_status === 1 || (int) $return->paid_status === 1) {
            return 'This return is already approved.';
        }
        if ($this->isForwarded((int) $return->id)) {
            return 'This return has already been forwarded.';
        }

        return null;
    }

    /**
     * Why the signed-in user may not forward a property's return, or null when they may: a user limited to a zone can
     * forward only the returns of properties in their own zone (the same limit as generating).
     *
     * @param int $propertyId
     * @return string|null
     */
    public function zoneRefusal(int $propertyId): ?string {
        if (\App\Models\Client::zoneAccessState() === null) {
            return null;
        }

        return \App\Models\Property::whereKey($propertyId)->exists() ? null : 'That property is not in your zone, so you cannot forward its returns.';
    }

    /**
     * Whether a return has been forwarded, that is whether it has an approval item.
     *
     * @param int $returnId
     */
    public function isForwarded(int $returnId): bool {
        return DB::table('cb_approval_items')->where('item_type', self::ITEM_TYPE)->where('item_id', $returnId)->exists();
    }

    /**
     * Forward one return to the manager: it gets its approval item, from level 1, with the forwarding user saved at level 0.
     * The first person who has to act is the zone manager, so a zone with no manager login refuses it with a message.
     *
     * @param object $return a cb_returns_summary row
     * @param int $userId the user who forwards it
     * @param string|null $ipAddress
     * @throws ReturnApprovalException when it cannot be forwarded
     */
    public function forward(object $return, int $userId, ?string $ipAddress): void {
        if ($message = $this->forwardRefusal($return)) {
            throw new ReturnApprovalException($message);
        }

        $propertyId = (int) DB::table('sys_pm_properties')->where('reference_number', $return->property_code)->value('id');
        if (!$propertyId) {
            throw new ReturnApprovalException('The property of this return was not found.');
        }

        if ($this->createItems((string) $return->return_period, $userId, $ipAddress, $propertyId, (int) $return->id) === 0) {
            throw new ReturnApprovalException('This return could not be forwarded.');
        }
    }

    /**
     * What the button of a level says: the levels before the last one verify, the last one approves.
     *
     * @param int $level
     * @return string Verify or Approve
     */
    public function actionLabel(int $level): string {
        return self::labelFor($level, $this->totalLevels());
    }

    /**
     * The button word for a level of a workflow with this many levels: Verify before the last level, Approve at it.
     *
     * @param int $level
     * @param int $totalLevels
     * @return string
     */
    public static function labelFor(int $level, int $totalLevels): string {
        return $level < $totalLevels ? 'Verify' : 'Approve';
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

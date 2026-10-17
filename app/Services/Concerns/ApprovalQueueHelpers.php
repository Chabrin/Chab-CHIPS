<?php

namespace App\Services\Concerns;

use App\Models\ConfirmationLog;
use Carbon\Carbon;

/**
 * Small helpers shared by the approval queue services (SLA, lease).
 */
trait ApprovalQueueHelpers
{
    /**
     * Normalise a list of ids to unique integers.
     *
     * @param array $ids
     */
    protected function cleanIds(array $ids): array {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * Human label for a workflow status id.
     *
     * @param int $statusId
     */
    protected function statusLabel(int $statusId): string {
        return match ($statusId) {
            1 => 'Pending',
            5 => 'Approved',
            41 => 'Rejected',
            default => 'Status ' . $statusId,
        };
    }

    /**
     * Format a date value, or a dash when empty.
     *
     * @param mixed $value
     * @param string $format
     */
    protected function formatDate($value, string $format = 'd M Y'): string {
        return $value ? Carbon::parse($value)->format($format) : '-';
    }

    /**
     * Write one confirmation_logs row per record type that changed, as the existing
     * Property Confirmations page does.
     *
     * @param array $flipped section name => record ids
     * @param array $models section name => model class
     * @param string $action
     * @param int $userId
     * @param int $newStatus
     * @param string $actionType
     * @param string|null $reason
     * @param string $source
     */
    protected function logConfirmations(array $flipped, array $models, string $action, int $userId, int $newStatus, string $actionType, ?string $reason, string $source): void {
        foreach ($flipped as $section => $ids) {
            if (empty($ids)) {
                continue;
            }
            ConfirmationLog::create([
                'section' => $section,
                'model' => $models[$section] ?? '',
                'action' => $action,
                'ids' => array_values($ids),
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
                'approval_stage' => null,
                'metadata' => [
                    'new_status_id' => $newStatus,
                    'action_type' => $actionType,
                    'rejection_reason' => $reason,
                    'source' => $source,
                ],
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Client;
use App\Models\LeaseAgreement;
use App\Services\Concerns\ApprovalQueueHelpers;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Approval queue for Lease Agreements.
 *
 * A lease can only be approved once its service agreement exists and is approved: the SLA
 * linked on the lease, or the property's latest SLA when the lease has none. Approving a
 * lease also approves its tenant when the tenant is still Pending, so the separate Tenants
 * confirmation is no longer needed. Rejecting a lease rejects only that
 * lease. Every action is recorded in confirmation_logs, as the existing page does.
 */
class LeaseAgreementApprovalService
{
    use ApprovalQueueHelpers;

    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 5;
    const STATUS_REJECTED = 41;

    const ORDER_COLUMNS = [
        'reference' => 'l.ref_number',
        'tenant' => 't.names',
        'unit' => 'un.unit_code',
        'property' => 'p.property_name',
        'zone' => 'z.zone_name',
        'rent' => 'un.rent_amount',
        'start' => 'l.date_start',
        'created' => 'l.date_time',
    ];

    /**
     * Base query for the lease queue. The zone falls back to the property's zone when the
     * lease row has none.
     *
     * @param array $filters
     * @param bool $applyOptionalFilters
     */
    protected function baseQuery(array $filters, bool $applyOptionalFilters = true): Builder {
        $query = DB::table('sys_pm_lease_agreements as l')
            ->leftJoin('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
            ->leftJoin('sys_pm_units as un', 'un.id', '=', 'l.unit_id')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'l.property_id')
            ->leftJoin('sys_set_zones as z', function ($join) {
                $join->whereRaw('z.id = COALESCE(l.zone_id, p.zone_id)');
            })
            ->leftJoin('josya_users as u', 'u.id', '=', 'l.created_by_id');
        TenantReviewService::legacyOnly($query, 'l.id', 'lease');

        $status = $filters['status'] ?? (string) self::STATUS_PENDING;
        if ($status !== 'all') {
            $query->where('l.current_status_id', (int) $status);
        }

        if (!$applyOptionalFilters) {
            return $query;
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('l.ref_number', 'ILIKE', $term)
                    ->orWhere('t.names', 'ILIKE', $term)
                    ->orWhere('t.reference_number', 'ILIKE', $term)
                    ->orWhere('un.unit_code', 'ILIKE', $term)
                    ->orWhere('un.unit_name', 'ILIKE', $term)
                    ->orWhere('un.unit_number', 'ILIKE', $term)
                    ->orWhere('p.property_name', 'ILIKE', $term)
                    ->orWhere('p.reference_number', 'ILIKE', $term);
            });
        }
        if (!empty($filters['zone_id'])) {
            $query->whereRaw('COALESCE(l.zone_id, p.zone_id) = ?', [(int) $filters['zone_id']]);
        }
        if (!empty($filters['created_by'])) {
            $query->where('l.created_by_id', (int) $filters['created_by']);
        }
        if (($filters['source'] ?? '') === 'chips') {
            $query->whereNotNull('l.created_by_id');
        } elseif (($filters['source'] ?? '') === 'migrated') {
            $query->whereNull('l.created_by_id');
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('l.date_time', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('l.date_time', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * One page of the lease queue for the server-side data table.
     *
     * @param array $filters
     * @param int $start
     * @param int $length
     * @param string $orderKey
     * @param string $orderDir
     */
    public function paginate(array $filters, int $start, int $length, string $orderKey = 'created', string $orderDir = 'desc'): array {
        $total = $this->baseQuery($filters, false)->count();
        $filtered = $this->baseQuery($filters)->count();

        $orderColumn = self::ORDER_COLUMNS[$orderKey] ?? self::ORDER_COLUMNS['created'];
        $direction = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        $rows = $this->baseQuery($filters)
            ->select([
                'l.id',
                'l.ref_number',
                'l.current_status_id',
                'l.date_start',
                'l.date_end',
                'l.open_ended',
                'l.years',
                'l.date_time',
                'l.created_by_id',
                't.names as tenant_name',
                't.reference_number as tenant_ref',
                'un.unit_code',
                'un.unit_name',
                'un.rent_amount',
                'p.property_name',
                'p.reference_number as property_ref',
                'z.zone_name',
                'u.name as created_by_name',
                DB::raw('(select s.current_status_id from sys_pm_service_agreements s where s.id = COALESCE(l.sla_id, (select s2.id from sys_pm_service_agreements s2 where s2.property_id = l.property_id order by s2.date_start desc nulls last, s2.id desc limit 1))) as sla_status'),
            ])
            ->orderBy($orderColumn, $direction)
            ->orderBy('l.id', 'desc')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'reference' => $row->ref_number ?? '-',
                    'status_id' => (int) $row->current_status_id,
                    'status' => $this->statusLabel((int) $row->current_status_id),
                    'tenant_name' => $row->tenant_name ?? '-',
                    'tenant_ref' => $row->tenant_ref ?? '',
                    'unit_code' => $row->unit_code ?? '-',
                    'unit_name' => $row->unit_name ?? '',
                    'rent' => $row->rent_amount !== null ? number_format((float) $row->rent_amount, 2) : '-',
                    'property_name' => $row->property_name ?? '-',
                    'property_ref' => $row->property_ref ?? '',
                    'zone' => $row->zone_name ?? '-',
                    'date_start' => $this->formatDate($row->date_start),
                    'date_end' => $row->open_ended ? 'Open-ended' : $this->formatDate($row->date_end),
                    'years' => $row->years,
                    'created_at' => $this->formatDate($row->date_time, 'd M Y H:i'),
                    'created_by' => $row->created_by_id ? ($row->created_by_name ?? '-') : 'Migrated',
                    'sla_status' => $row->sla_status !== null ? (int) $row->sla_status : null,
                    'block_reason' => (int) $row->current_status_id === self::STATUS_PENDING ? $this->listBlockReason($row->sla_status) : null,
                ];
            })
            ->all();

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * Lease counts per status, for the tabs above the table.
     */
    public function statusCounts(): array {
        $query = DB::table('sys_pm_lease_agreements');
        TenantReviewService::legacyOnly($query, 'sys_pm_lease_agreements.id', 'lease');
        $counts = $query
            ->whereIn('current_status_id', [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED])
            ->selectRaw('current_status_id, count(*) as total')
            ->groupBy('current_status_id')
            ->pluck('total', 'current_status_id');

        return [
            'pending' => (int) ($counts[self::STATUS_PENDING] ?? 0),
            'approved' => (int) ($counts[self::STATUS_APPROVED] ?? 0),
            'rejected' => (int) ($counts[self::STATUS_REJECTED] ?? 0),
        ];
    }

    /**
     * Zones and creators that appear on leases, for the filter dropdowns.
     */
    public function filterOptions(): array {
        $zones = DB::table('sys_set_zones')->orderBy('zone_name')->get(['id', 'zone_name as name']);

        $creators = DB::table('sys_pm_lease_agreements as l')
            ->join('josya_users as u', 'u.id', '=', 'l.created_by_id')
            ->distinct()
            ->orderBy('u.name')
            ->get(['u.id', 'u.name']);

        return ['zones' => $zones, 'creators' => $creators];
    }

    /**
     * Everything the approver needs to judge one lease: its terms, the tenant, the unit and the property.
     *
     * @param int $id
     */
    public function details(int $id): ?array {
        $lease = DB::table('sys_pm_lease_agreements as l')
            ->leftJoin('josya_users as u', 'u.id', '=', 'l.created_by_id')
            ->leftJoin('cb_lease_contract_templates as ct', 'ct.id', '=', 'l.contract_template_id')
            ->leftJoin('sys_pm_lease_agreements as linked', 'linked.id', '=', 'l.linked_lease_id')
            ->where('l.id', $id)
            ->first(['l.*', 'u.name as created_by_name', 'ct.template_name', 'linked.ref_number as linked_ref']);

        if (!$lease) {
            return null;
        }

        $tenant = $lease->tenant_id ? DB::table('sys_pm_clients')->where('id', $lease->tenant_id)->first() : null;
        $unit = $lease->unit_id ? DB::table('sys_pm_units')->where('id', $lease->unit_id)->first() : null;
        $leaseModel = LeaseAgreement::find($id);
        $sla = $leaseModel->resolvedServiceAgreement();
        $blockReason = (int) $lease->current_status_id === self::STATUS_PENDING ? $leaseModel->approvalBlockReason($sla) : null;

        $property = $lease->property_id
            ? DB::table('sys_pm_properties as p')
                ->leftJoin('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
                ->leftJoin('sys_set_zone_areas as za', 'za.id', '=', 'p.zone_area_id')
                ->leftJoin('sys_staff as fo', 'fo.id', '=', 'p.field_officer_id')
                ->leftJoin('sys_pm_clients as ll', 'll.id', '=', 'p.client_id')
                ->where('p.id', $lease->property_id)
                ->first([
                    'p.id',
                    'p.property_name',
                    'p.reference_number',
                    'z.zone_name',
                    'za.area_name',
                    'fo.full_names as field_officer',
                    'll.names as landlord',
                    'll.reference_number as landlord_ref',
                ])
            : null;

        $items = DB::table('sys_pm_lease_agreements_27_repeat as i')
            ->leftJoin('sys_set_invoiceable_items as ii', 'ii.id', '=', 'i.item_id')
            ->leftJoin('sys_set_invoiceable_items_cycles as c', 'c.id', '=', 'i.cycle_id')
            ->where('i.parent_id', $lease->id)
            ->get(['ii.item_name', 'i.amount', 'c.cycle_name', 'i.vatable']);

        $otherLeases = $lease->unit_id
            ? DB::table('sys_pm_lease_agreements as o')
                ->leftJoin('sys_pm_clients as ot', 'ot.id', '=', 'o.tenant_id')
                ->where('o.unit_id', $lease->unit_id)
                ->where('o.id', '<>', $lease->id)
                ->orderByDesc('o.date_start')
                ->limit(6)
                ->get(['o.ref_number', 'ot.names as tenant', 'o.lease_status', 'o.current_status_id', 'o.date_start', 'o.date_end'])
            : collect();

        $otherActive = $lease->unit_id
            ? DB::table('sys_pm_lease_agreements')->where('unit_id', $lease->unit_id)->where('id', '<>', $lease->id)->where('lease_status', 'Active')->count()
            : 0;

        return [
            'id' => $lease->id,
            'status_id' => (int) $lease->current_status_id,
            'status' => $this->statusLabel((int) $lease->current_status_id),
            'lease' => [
                'reference' => $lease->ref_number ?? '-',
                'lease_status' => $lease->lease_status,
                'date_start' => $this->formatDate($lease->date_start),
                'date_end' => $lease->open_ended ? 'Open-ended' : $this->formatDate($lease->date_end),
                'open_ended' => (bool) $lease->open_ended,
                'years' => $lease->years,
                'auto_renewal' => $lease->auto_renewal,
                'escalation_rate' => $lease->escalation_rate !== null ? (float) $lease->escalation_rate : null,
                'escalation_frequency' => $lease->escalation_frequency,
                'service_charges' => $lease->service_charges_amount !== null ? (float) $lease->service_charges_amount : null,
                'water_meter' => $lease->initial_water_meter_reading,
                'linked_lease' => $lease->linked_ref,
                'contract_template' => $lease->template_name,
                'contract_sent' => $this->formatDate($lease->contract_sent_at, 'd M Y H:i'),
                'contract_signed' => $this->formatDate($lease->contract_signed_at, 'd M Y H:i'),
                'tenant_verified' => $this->formatDate($lease->tenant_verified_at, 'd M Y H:i'),
                'signer' => $lease->signer_name,
                'source' => $lease->created_by_id ? 'Entered in CHIPS' : 'Migrated',
                'created_by' => $lease->created_by_id ? ($lease->created_by_name ?? '-') : null,
                'created_at' => $this->formatDate($lease->date_time, 'd M Y H:i'),
                'items' => $items->map(fn($i) => [
                    'name' => $i->item_name ?? 'Item',
                    'amount' => $i->amount !== null ? (float) $i->amount : null,
                    'cycle' => $i->cycle_name,
                    'vatable' => (bool) $i->vatable,
                ])->all(),
            ],
            'can_approve' => $blockReason === null,
            'block_reason' => $blockReason,
            'sla' => $sla ? [
                'reference' => $sla->reference_number,
                'status' => $this->statusLabel((int) $sla->current_status_id),
                'approved' => (int) $sla->current_status_id === self::STATUS_APPROVED,
                'via_property' => !$lease->sla_id || (int) $lease->sla_id !== (int) $sla->id,
                'commission' => $sla->commission_percentage !== null ? (float) $sla->commission_percentage : null,
            ] : null,
            'tenant' => $tenant ? [
                'names' => $tenant->names,
                'reference' => $tenant->reference_number,
                'mobile' => $tenant->mobile_number,
                'email' => $tenant->email_address,
                'national_id' => $tenant->national_id,
                'passport' => $tenant->passport_number,
                'pin' => $tenant->pin_number,
                'address' => $tenant->address,
                'status' => $this->statusLabel((int) $tenant->current_status_id),
            ] : null,
            'unit' => $unit ? [
                'code' => $unit->unit_code,
                'name' => $unit->unit_name,
                'number' => $unit->unit_number,
                'rent' => $unit->rent_amount !== null ? (float) $unit->rent_amount : null,
                'vatable' => $unit->vat_able,
                'status' => $this->statusLabel((int) $unit->current_status_id),
            ] : null,
            'property' => $property ? [
                'name' => $property->property_name,
                'reference' => $property->reference_number,
                'zone' => $property->zone_name,
                'area' => $property->area_name,
                'field_officer' => $property->field_officer,
                'landlord' => $property->landlord,
                'landlord_ref' => $property->landlord_ref,
            ] : null,
            'other_leases' => $otherLeases->map(fn($o) => [
                'reference' => $o->ref_number,
                'tenant' => $o->tenant,
                'lease_status' => $o->lease_status,
                'status' => $this->statusLabel((int) $o->current_status_id),
                'date_start' => $this->formatDate($o->date_start),
                'date_end' => $this->formatDate($o->date_end),
            ])->all(),
            'checks' => [
                ['label' => 'Tenant found', 'ok' => (bool) $tenant],
                ['label' => 'Tenant has a phone or email', 'ok' => $tenant && (!empty($tenant->mobile_number) || !empty($tenant->email_address))],
                ['label' => 'Tenant has an ID, passport or PIN', 'ok' => $tenant && (!empty($tenant->national_id) || !empty($tenant->passport_number) || !empty($tenant->pin_number))],
                ['label' => 'Unit found', 'ok' => (bool) $unit],
                ['label' => 'Unit rent is set', 'ok' => $unit && $unit->rent_amount !== null && (float) $unit->rent_amount > 0],
                ['label' => 'Property found', 'ok' => (bool) $property],
                ['label' => 'Service agreement exists', 'ok' => (bool) $sla],
                ['label' => 'Service agreement is approved (required)', 'ok' => $sla && (int) $sla->current_status_id === self::STATUS_APPROVED],
                ['label' => 'Start date is set', 'ok' => !empty($lease->date_start)],
                ['label' => 'End date set or open-ended', 'ok' => !empty($lease->date_end) || (bool) $lease->open_ended],
                ['label' => 'No other active lease on this unit', 'ok' => $otherActive === 0],
            ],
        ];
    }

    /**
     * Approve the given pending leases, together with each tenant that is still pending.
     * Leases that are not pending are skipped and reported back.
     *
     * @param array $ids
     * @param int $userId
     */
    public function approve(array $ids, int $userId): array {
        $ids = $this->cleanIds($ids);
        $leases = LeaseAgreement::whereIn('id', $ids)->get()->keyBy('id');
        $skipped = [];
        $flipped = ['Lease Agreements' => [], 'Tenants' => []];

        foreach ($ids as $id) {
            if (!$leases->has($id)) {
                $skipped[$id] = 'Not found';
            }
        }

        DB::transaction(function () use ($leases, &$skipped, &$flipped) {
            foreach ($leases as $lease) {
                if (\Illuminate\Support\Facades\Schema::hasTable('tenant_reviews') && \App\Models\TenantReview::where('lease_id', $lease->id)->exists()) {
                    $skipped[$lease->id] = 'Use New Tenant Approvals for this record.';
                    continue;
                }
                if ((int) $lease->current_status_id !== self::STATUS_PENDING) {
                    $skipped[$lease->id] = 'Not pending';
                    continue;
                }

                $reason = $lease->approvalBlockReason();
                if ($reason !== null) {
                    $skipped[$lease->id] = $reason;
                    continue;
                }

                $lease->current_status_id = self::STATUS_APPROVED;
                $lease->save();
                $flipped['Lease Agreements'][] = $lease->id;

                $tenant = $lease->tenant_id ? Client::withoutGlobalScopes()->find($lease->tenant_id) : null;
                if ($tenant && (int) $tenant->current_status_id === self::STATUS_PENDING) {
                    $tenant->current_status_id = self::STATUS_APPROVED;
                    $tenant->save();
                    $flipped['Tenants'][] = $tenant->id;
                }
            }
        });

        $this->logConfirmations($flipped, $this->logModels(), 'approve', $userId, self::STATUS_APPROVED, 'lease_approval', null, 'approve_leases_page');

        return [
            'done' => $flipped['Lease Agreements'],
            'skipped' => $skipped,
            'labels' => $leases->pluck('ref_number', 'id')->all(),
            'flipped' => array_map('count', $flipped),
        ];
    }

    /**
     * Reject the given pending leases. Only the lease is rejected; the tenant stays as it is.
     * Leases that are not pending are skipped and reported back.
     *
     * @param array $ids
     * @param string $reason
     * @param int $userId
     */
    public function reject(array $ids, string $reason, int $userId): array {
        $ids = $this->cleanIds($ids);
        $leases = LeaseAgreement::whereIn('id', $ids)->get()->keyBy('id');
        $skipped = [];
        $rejected = [];

        foreach ($ids as $id) {
            if (!$leases->has($id)) {
                $skipped[$id] = 'Not found';
            }
        }

        DB::transaction(function () use ($leases, &$skipped, &$rejected) {
            foreach ($leases as $lease) {
                if (\Illuminate\Support\Facades\Schema::hasTable('tenant_reviews') && \App\Models\TenantReview::where('lease_id', $lease->id)->exists()) {
                    $skipped[$lease->id] = 'Use New Tenant Approvals for this record.';
                    continue;
                }
                if ((int) $lease->current_status_id !== self::STATUS_PENDING) {
                    $skipped[$lease->id] = 'Not pending';
                    continue;
                }
                $lease->current_status_id = self::STATUS_REJECTED;
                $lease->save();
                $rejected[] = $lease->id;
            }
        });

        $this->logConfirmations(['Lease Agreements' => $rejected], $this->logModels(), 'reject', $userId, self::STATUS_REJECTED, 'lease_rejection', $reason, 'approve_leases_page');

        return ['done' => $rejected, 'skipped' => $skipped, 'labels' => $leases->pluck('ref_number', 'id')->all(), 'flipped' => ['Lease Agreements' => count($rejected)]];
    }

    /**
     * Short block reason for the table, from the SLA status alone.
     *
     * @param mixed $slaStatus
     */
    protected function listBlockReason($slaStatus): ?string {
        if ($slaStatus === null) {
            return 'No service agreement. Create and approve one first.';
        }

        return (int) $slaStatus === self::STATUS_APPROVED ? null : 'Service agreement is not approved yet. Approve the SLA first.';
    }

    /**
     * Model class per confirmation section, for the confirmation log.
     */
    protected function logModels(): array {
        return [
            'Lease Agreements' => LeaseAgreement::class,
            'Tenants' => Client::class,
        ];
    }
}

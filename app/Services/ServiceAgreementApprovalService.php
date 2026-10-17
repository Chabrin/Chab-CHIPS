<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ConfirmationLog;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Models\Unit;
use App\Services\Concerns\ApprovalQueueHelpers;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Approval queue for Service Agreements (SLAs).
 *
 * Approving an SLA also approves the landlord, property and units that are still Pending
 * behind it, mirroring the Landlord Onboarding bundle on the Property Confirmations page.
 * Rejecting an SLA rejects only that SLA, since the landlord and property may have other
 * agreements. Every action is recorded in confirmation_logs, as the existing page does.
 */
class ServiceAgreementApprovalService
{
    use ApprovalQueueHelpers;

    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 5;
    const STATUS_REJECTED = 41;

    const ORDER_COLUMNS = [
        'reference' => 's.reference_number',
        'client' => 'c.names',
        'property' => 'p.property_name',
        'zone' => 'z.zone_name',
        'commission' => 's.commission_percentage',
        'start' => 's.date_start',
        'created' => 's.date_time',
    ];

    /**
     * Base query for the SLA queue. The client is resolved through the property when the
     * SLA row carries no client of its own (most migrated and uploaded SLAs).
     *
     * @param array $filters
     * @param bool $applyOptionalFilters
     */
    protected function baseQuery(array $filters, bool $applyOptionalFilters = true): Builder {
        $query = DB::table('sys_pm_service_agreements as s')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 's.property_id')
            ->leftJoin('sys_pm_clients as c', function ($join) {
                $join->whereRaw('c.id = COALESCE(s.client_d, p.client_id)');
            })
            ->leftJoin('sys_set_zones as z', 'z.id', '=', 's.zone_id')
            ->leftJoin('josya_users as u', 'u.id', '=', 's.created_by_id');

        $status = $filters['status'] ?? (string) self::STATUS_PENDING;
        if ($status !== 'all') {
            $query->where('s.current_status_id', (int) $status);
        }

        if (!$applyOptionalFilters) {
            return $query;
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('s.reference_number', 'ILIKE', $term)
                    ->orWhere('c.names', 'ILIKE', $term)
                    ->orWhere('c.reference_number', 'ILIKE', $term)
                    ->orWhere('p.property_name', 'ILIKE', $term)
                    ->orWhere('p.reference_number', 'ILIKE', $term);
            });
        }
        if (!empty($filters['zone_id'])) {
            $query->where('s.zone_id', (int) $filters['zone_id']);
        }
        if (!empty($filters['created_by'])) {
            $query->where('s.created_by_id', (int) $filters['created_by']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('s.date_time', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('s.date_time', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * One page of the SLA queue for the server-side data table.
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
                's.id',
                's.reference_number',
                's.current_status_id',
                's.commission_percentage',
                's.date_start',
                's.date_end',
                's.years',
                's.date_time',
                's.charge_registration_fee',
                'c.names as client_name',
                'c.reference_number as client_ref',
                'p.property_name',
                'p.reference_number as property_ref',
                'z.zone_name',
                'u.name as created_by_name',
                DB::raw('(select count(*) from sys_pm_units un where un.property_id = p.id) as units_count'),
            ])
            ->orderBy($orderColumn, $direction)
            ->orderBy('s.id', 'desc')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'reference' => $row->reference_number ?? '-',
                    'status_id' => (int) $row->current_status_id,
                    'status' => $this->statusLabel((int) $row->current_status_id),
                    'client_name' => $row->client_name ?? '-',
                    'client_ref' => $row->client_ref ?? '',
                    'property_name' => $row->property_name ?? '-',
                    'property_ref' => $row->property_ref ?? '',
                    'units_count' => (int) $row->units_count,
                    'zone' => $row->zone_name ?? '-',
                    'commission' => $row->commission_percentage !== null ? rtrim(rtrim(number_format((float) $row->commission_percentage, 2), '0'), '.') . '%' : '-',
                    'date_start' => $this->formatDate($row->date_start),
                    'date_end' => $this->formatDate($row->date_end),
                    'years' => $row->years,
                    'registration_fee' => (bool) $row->charge_registration_fee,
                    'created_at' => $this->formatDate($row->date_time, 'd M Y H:i'),
                    'created_by' => $row->created_by_name ?? '-',
                ];
            })
            ->all();

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * SLA counts per status, for the summary chips above the table.
     */
    public function statusCounts(): array {
        $counts = DB::table('sys_pm_service_agreements')
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
     * Zones and creators that appear on SLAs, for the filter dropdowns.
     */
    public function filterOptions(): array {
        $zones = DB::table('sys_pm_service_agreements as s')
            ->join('sys_set_zones as z', 'z.id', '=', 's.zone_id')
            ->distinct()
            ->orderBy('z.zone_name')
            ->get(['z.id', 'z.zone_name as name']);

        $creators = DB::table('sys_pm_service_agreements as s')
            ->join('josya_users as u', 'u.id', '=', 's.created_by_id')
            ->distinct()
            ->orderBy('u.name')
            ->get(['u.id', 'u.name']);

        return ['zones' => $zones, 'creators' => $creators];
    }

    /**
     * Everything the approver needs to judge one SLA: its terms, the client and the property.
     *
     * @param int $id
     */
    public function details(int $id): ?array {
        $sla = DB::table('sys_pm_service_agreements as s')
            ->leftJoin('sys_set_zones as z', 'z.id', '=', 's.zone_id')
            ->leftJoin('josya_users as u', 'u.id', '=', 's.created_by_id')
            ->where('s.id', $id)
            ->first(['s.*', 'z.zone_name', 'u.name as created_by_name']);

        if (!$sla) {
            return null;
        }

        $property = DB::table('sys_pm_properties as p')
            ->leftJoin('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
            ->leftJoin('sys_set_zone_areas as za', 'za.id', '=', 'p.zone_area_id')
            ->leftJoin('sys_pm_usage_types as ut', 'ut.id', '=', 'p.usage_type_id')
            ->leftJoin('sys_staff as fo', 'fo.id', '=', 'p.field_officer_id')
            ->leftJoin('sys_staff as zs', 'zs.id', '=', 'p.zone_supervisor_id')
            ->leftJoin('sys_staff as zm', 'zm.id', '=', 'p.zone_manager_id')
            ->where('p.id', $sla->property_id)
            ->first([
                'p.id',
                'p.client_id',
                'p.property_name',
                'p.reference_number',
                'p.lr_number',
                'p.description',
                'p.current_status_id',
                'z.zone_name',
                'za.area_name',
                'ut.type_name as usage_type',
                'fo.full_names as field_officer',
                'zs.full_names as zone_supervisor',
                'zm.full_names as zone_manager',
            ]);

        $clientId = $sla->client_d ?: ($property->client_id ?? null);
        $client = $clientId ? DB::table('sys_pm_clients')->where('id', $clientId)->first() : null;

        $banks = $client
            ? DB::table('clients_bank_details as b')
                ->leftJoin('sys_set_banks as bk', 'bk.id', '=', 'b.bank_id')
                ->where('b.client_id', $client->id)
                ->get(['bk.bank_name', 'b.account_name', 'b.account_number', 'b.is_default'])
            : collect();

        $unitsQuery = DB::table('sys_pm_units')->where('property_id', $sla->property_id);
        $unitsTotal = (clone $unitsQuery)->count();
        $unitsPending = (clone $unitsQuery)->where('current_status_id', self::STATUS_PENDING)->count();
        $units = $unitsQuery->orderBy('unit_code')->limit(100)->get(['unit_code', 'unit_name', 'rent_amount', 'current_status_id']);

        $otherSlas = DB::table('sys_pm_service_agreements')
            ->where('property_id', $sla->property_id)
            ->where('id', '<>', $sla->id)
            ->count();

        $hasBank = $banks->isNotEmpty() || ($client && !empty($client->account_number));

        return [
            'id' => $sla->id,
            'status_id' => (int) $sla->current_status_id,
            'status' => $this->statusLabel((int) $sla->current_status_id),
            'sla' => [
                'reference' => $sla->reference_number ?? '-',
                'commission' => $sla->commission_percentage !== null ? (float) $sla->commission_percentage : null,
                'vat' => $sla->vat,
                'vat_per' => $sla->vat_per,
                'flat_rate_vat' => $sla->flat_rate_vat,
                'date_start' => $this->formatDate($sla->date_start),
                'date_end' => $this->formatDate($sla->date_end),
                'years' => $sla->years,
                'payment_date' => $sla->landlord_payment_date,
                'auto_renewal' => (bool) $sla->auto_renewal,
                'registration_fee' => (bool) $sla->charge_registration_fee,
                'move_in_inspection' => (bool) $sla->move_in_inspection_required,
                'returns_combination' => $sla->returns_combination,
                'zone' => $sla->zone_name ?? '-',
                'created_by' => $sla->created_by_name ?? '-',
                'created_at' => $this->formatDate($sla->date_time, 'd M Y H:i'),
                'items' => [
                    ['label' => 'Rent', 'selected' => (bool) $sla->rent_selected, 'cost' => null],
                    ['label' => 'Electricity bill', 'selected' => (bool) $sla->electricity_bill_selected, 'cost' => $sla->electricity_bill_cost],
                    ['label' => 'Water bill', 'selected' => (bool) $sla->water_bill_selected, 'cost' => $sla->water_bill_cost],
                    ['label' => 'Service charges', 'selected' => (bool) $sla->service_charges_selected, 'cost' => $sla->service_charges_cost],
                ],
            ],
            'client' => $client ? [
                'names' => $client->names,
                'reference' => $client->reference_number,
                'mobile' => $client->mobile_number,
                'email' => $client->email_address,
                'pin' => $client->pin_number,
                'national_id' => $client->national_id,
                'vat_number' => $client->vat_number,
                'address' => $client->address,
                'status' => $this->statusLabel((int) $client->current_status_id),
                'banks' => $banks->map(fn($b) => [
                    'bank' => $b->bank_name,
                    'account_name' => $b->account_name,
                    'account_number' => $b->account_number,
                    'is_default' => (bool) $b->is_default,
                ])->all(),
                'legacy_bank' => !empty($client->account_number) ? ['account_name' => $client->account_name, 'account_number' => $client->account_number] : null,
            ] : null,
            'property' => $property ? [
                'name' => $property->property_name,
                'reference' => $property->reference_number,
                'lr_number' => $property->lr_number,
                'description' => $property->description,
                'zone' => $property->zone_name,
                'area' => $property->area_name,
                'usage_type' => $property->usage_type,
                'field_officer' => $property->field_officer,
                'zone_supervisor' => $property->zone_supervisor,
                'zone_manager' => $property->zone_manager,
                'status' => $this->statusLabel((int) $property->current_status_id),
                'units_total' => $unitsTotal,
                'units_pending' => $unitsPending,
                'units' => $units->map(fn($u) => [
                    'code' => $u->unit_code,
                    'name' => $u->unit_name,
                    'rent' => $u->rent_amount !== null ? (float) $u->rent_amount : null,
                    'status' => $this->statusLabel((int) $u->current_status_id),
                ])->all(),
            ] : null,
            'checks' => [
                ['label' => 'Client found', 'ok' => (bool) $client],
                ['label' => 'Client has a payout bank account', 'ok' => $hasBank],
                ['label' => 'Client has a phone or email', 'ok' => $client && (!empty($client->mobile_number) || !empty($client->email_address))],
                ['label' => 'Property found', 'ok' => (bool) $property],
                ['label' => 'Property has units', 'ok' => $unitsTotal > 0],
                ['label' => 'Property has a field officer', 'ok' => $property && !empty($property->field_officer)],
                ['label' => 'Commission is set', 'ok' => $sla->commission_percentage !== null && (float) $sla->commission_percentage > 0],
                ['label' => 'Start date is set', 'ok' => !empty($sla->date_start)],
                ['label' => 'No other SLA on this property', 'ok' => $otherSlas === 0],
            ],
        ];
    }

    /**
     * Approve the given pending SLAs, together with the pending landlord, property and units
     * behind each one. SLAs that are not pending are skipped and reported back.
     *
     * @param array $ids
     * @param int $userId
     */
    public function approve(array $ids, int $userId): array {
        $ids = $this->cleanIds($ids);
        $slas = ServiceAgreement::whereIn('id', $ids)->get()->keyBy('id');
        $skipped = [];
        $flipped = ['Service Agreements' => [], 'Properties' => [], 'Clients' => [], 'Units' => []];

        foreach ($ids as $id) {
            if (!$slas->has($id)) {
                $skipped[$id] = 'Not found';
            }
        }

        DB::transaction(function () use ($slas, &$skipped, &$flipped) {
            foreach ($slas as $sla) {
                if ((int) $sla->current_status_id !== self::STATUS_PENDING) {
                    $skipped[$sla->id] = 'Not pending';
                    continue;
                }

                $sla->current_status_id = self::STATUS_APPROVED;
                $sla->save();
                $flipped['Service Agreements'][] = $sla->id;

                $property = $sla->property_id ? Property::withoutGlobalScopes()->find($sla->property_id) : null;
                if ($property && (int) $property->current_status_id === self::STATUS_PENDING) {
                    $property->current_status_id = self::STATUS_APPROVED;
                    $property->save();
                    $flipped['Properties'][] = $property->id;
                }

                $clientId = $sla->client_d ?: ($property->client_id ?? null);
                $client = $clientId ? Client::withoutGlobalScopes()->find($clientId) : null;
                if ($client && (int) $client->current_status_id === self::STATUS_PENDING) {
                    $client->current_status_id = self::STATUS_APPROVED;
                    $client->save();
                    $flipped['Clients'][] = $client->id;
                }

                if ($property) {
                    $unitIds = Unit::withoutGlobalScopes()
                        ->where('property_id', $property->id)
                        ->where('current_status_id', self::STATUS_PENDING)
                        ->pluck('id')
                        ->all();
                    if ($unitIds) {
                        Unit::withoutGlobalScopes()->whereIn('id', $unitIds)->update(['current_status_id' => self::STATUS_APPROVED]);
                        $flipped['Units'] = array_merge($flipped['Units'], $unitIds);
                    }
                }
            }
        });

        $this->logConfirmations($flipped, $this->logModels(), 'approve', $userId, self::STATUS_APPROVED, 'sla_approval', null, 'approve_sla_page');

        return ['done' => $flipped['Service Agreements'], 'skipped' => $skipped, 'flipped' => array_map('count', $flipped)];
    }

    /**
     * Reject the given pending SLAs. Only the SLA is rejected; the landlord, property and
     * units stay as they are. SLAs that are not pending are skipped and reported back.
     *
     * @param array $ids
     * @param string $reason
     * @param int $userId
     */
    public function reject(array $ids, string $reason, int $userId): array {
        $ids = $this->cleanIds($ids);
        $slas = ServiceAgreement::whereIn('id', $ids)->get()->keyBy('id');
        $skipped = [];
        $rejected = [];

        foreach ($ids as $id) {
            if (!$slas->has($id)) {
                $skipped[$id] = 'Not found';
            }
        }

        DB::transaction(function () use ($slas, &$skipped, &$rejected) {
            foreach ($slas as $sla) {
                if ((int) $sla->current_status_id !== self::STATUS_PENDING) {
                    $skipped[$sla->id] = 'Not pending';
                    continue;
                }
                $sla->current_status_id = self::STATUS_REJECTED;
                $sla->save();
                $rejected[] = $sla->id;
            }
        });

        $this->logConfirmations(['Service Agreements' => $rejected], $this->logModels(), 'reject', $userId, self::STATUS_REJECTED, 'sla_rejection', $reason, 'approve_sla_page');

        return ['done' => $rejected, 'skipped' => $skipped, 'flipped' => ['Service Agreements' => count($rejected)]];
    }

    /**
     * Model class per confirmation section, for the confirmation log.
     */
    protected function logModels(): array {
        return [
            'Service Agreements' => ServiceAgreement::class,
            'Properties' => Property::class,
            'Clients' => Client::class,
            'Units' => Unit::class,
        ];
    }
}

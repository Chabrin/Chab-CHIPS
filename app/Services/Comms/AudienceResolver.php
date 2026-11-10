<?php

namespace App\Services\Comms;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns an audience choice (all tenants, tenants of a property, tenants of a zone, landlords,
 * chosen clients, active staff) into a flat recipient list with the contact and placeholder
 * context each message needs. "Tenants" always means current tenants (see tenantsOnOpenLeases),
 * so a tenant who has left is never messaged by a bulk send.
 *
 * Each recipient is an array: recipient_type, recipient_id, name, email, mobile, reference,
 * property, unit.
 */
class AudienceResolver
{
    /**
     * A tenant's name, email and phone as the app reads them: the tenant record (cb_tenant_records,
     * the normalized profile) when it has a value, else the old shared sys_pm_clients row. This is
     * the rule the Client model's accessors follow, so an email corrected on the Tenants screen is
     * the one that gets the message.
     */
    const TENANT_CONTACT_SQL = "COALESCE(NULLIF(TRIM(r.names), ''), c.names) AS names, COALESCE(NULLIF(TRIM(r.email), ''), NULLIF(TRIM(c.email_address), '')) AS email_address, COALESCE(NULLIF(TRIM(r.phone), ''), NULLIF(TRIM(c.mobile_number), '')) AS mobile_number";

    /** Same rule for a client row that may be a tenant or a landlord: the tenant record only counts for tenants (r is null for the rest). */
    const CLIENT_CONTACT_SQL = "COALESCE(NULLIF(TRIM(r.names), ''), c.names) AS names, COALESCE(NULLIF(TRIM(r.email), ''), NULLIF(TRIM(c.email_address), '')) AS email_address, COALESCE(NULLIF(TRIM(r.phone), ''), NULLIF(TRIM(c.mobile_number), '')) AS mobile_number";

    const TYPES = ['single_client', 'clients', 'tenants_all', 'landlords_all', 'property', 'zone', 'staff', 'staff_all'];

    /**
     * Resolve an audience into recipients.
     *
     * @param string $audienceType one of self::TYPES
     * @param array $params property_id | zone_id | client_ids | staff_ids, per type
     * @return Collection recipient arrays, deduplicated per person
     */
    public function resolve(string $audienceType, array $params = []): Collection {
        return match ($audienceType) {
            'single_client' => $this->clientsByIds([(int) ($params['client_id'] ?? 0)]),
            'clients' => $this->clientsByIds(array_map('intval', (array) ($params['client_ids'] ?? []))),
            'tenants_all' => $this->tenantsOnOpenLeases(),
            'landlords_all' => $this->landlords(),
            'property' => $this->tenantsOnOpenLeases((int) ($params['property_id'] ?? 0), null),
            'zone' => $this->tenantsOnOpenLeases(null, (int) ($params['zone_id'] ?? 0)),
            'staff' => $this->staffByIds(array_map('intval', (array) ($params['staff_ids'] ?? []))),
            'staff_all' => $this->activeStaff(),
            default => collect(),
        };
    }

    /**
     * A short human description of an audience, for the batch list.
     *
     * @param string $audienceType
     * @param array $params
     */
    public function label(string $audienceType, array $params = []): string {
        switch ($audienceType) {
            case 'single_client':
            case 'clients':
                $ids = $audienceType === 'single_client' ? [(int) ($params['client_id'] ?? 0)] : array_map('intval', (array) ($params['client_ids'] ?? []));
                $names = DB::table('sys_pm_clients as c')
                    ->leftJoin('cb_tenant_records as r', function ($join) {
                        $join->on('r.id', '=', 'c.id')->where('c.type_id', '2');
                    })
                    ->whereIn('c.id', $ids)
                    ->selectRaw("COALESCE(NULLIF(TRIM(r.names), ''), c.names) AS display_name")
                    ->pluck('display_name');
                return $names->count() === 1 ? (string) $names->first() : $names->count() . ' selected clients';
            case 'tenants_all':
                return 'All current tenants';
            case 'landlords_all':
                return 'All landlords';
            case 'property':
                $name = DB::table('sys_pm_properties')->where('id', (int) ($params['property_id'] ?? 0))->value('property_name');
                return ($name ?: 'Property') . ' — tenants';
            case 'zone':
                $name = DB::table('sys_set_zones')->where('id', (int) ($params['zone_id'] ?? 0))->value('zone_name');
                return 'Zone ' . ($name ?: '?') . ' — tenants';
            case 'staff':
                $names = DB::table('sys_staff')->whereIn('id', array_map('intval', (array) ($params['staff_ids'] ?? [])))->pluck('full_names');
                return $names->count() === 1 ? (string) $names->first() : $names->count() . ' selected staff';
            case 'staff_all':
                return 'All active staff';
            default:
                return $audienceType;
        }
    }

    /**
     * Current tenants, optionally narrowed to one property or one zone, each with the property and
     * unit they live in for the {property}/{unit} placeholders.
     *
     * A current tenant is the holder of the latest Active lease on a unit that is occupied. The
     * migrated leases need that much care: most have no end date, 15,000 are marked Terminated and
     * still have none, thousands of Active ones sit on units since marked vacant, and a unit can
     * carry several old Active leases. Counting every lease without an end date gave 56,000 tenants
     * for 33,000 occupied units; this rule gives 32,500, in line with the dashboard.
     *
     * @param int|null $propertyId
     * @param int|null $zoneId
     */
    protected function tenantsOnOpenLeases(?int $propertyId = null, ?int $zoneId = null): Collection {
        $query = DB::table('sys_pm_lease_agreements as l')
            ->join('sys_pm_clients as c', 'c.id', '=', 'l.tenant_id')
            ->leftJoin('cb_tenant_records as r', 'r.id', '=', 'l.tenant_id')
            ->join('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'l.property_id')
            ->where('u.occupancy_status_id', 1)
            ->whereIn('l.id', $this->latestActiveLeaseIds());

        if ($propertyId) {
            $query->where('l.property_id', $propertyId);
        }

        if ($zoneId) {
            $query->where('p.zone_id', $zoneId);
        }

        return $query
            ->selectRaw(self::TENANT_CONTACT_SQL . ', l.tenant_id, c.reference_number, p.property_name, u.unit_name, u.unit_number')
            ->orderByDesc('l.date_start')
            ->get()
            ->unique('tenant_id')
            ->map(fn ($r) => $this->recipient('tenant', (int) $r->tenant_id, $r->names, $r->email_address, $r->mobile_number, $r->reference_number, $r->property_name, $r->unit_name ?: $r->unit_number))
            ->values();
    }

    /**
     * A query for the id of the latest Active lease on each unit: Active, not ended, and the one
     * with the newest start date when a unit has more than one.
     */
    protected function latestActiveLeaseIds() {
        $today = date('Y-m-d');

        return function ($outer) use ($today) {
            $outer->select('current_lease.id')->fromSub(function ($inner) use ($today) {
                $inner->from('sys_pm_lease_agreements')
                    ->select('id')
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY unit_id ORDER BY date_start DESC, id DESC) AS rn')
                    ->where('lease_status', 'Active')
                    ->where(function ($q) use ($today) {
                        $q->whereNull('date_end')->orWhere('date_end', '>=', $today);
                    });
            }, 'current_lease')->where('current_lease.rn', 1);
        };
    }

    /**
     * All landlords (clients of type 1), as on the Client model's landlords() scope.
     */
    protected function landlords(): Collection {
        return DB::table('sys_pm_clients')
            ->where('type_id', '1')
            ->get(['id', 'names', 'email_address', 'mobile_number', 'reference_number'])
            ->map(fn ($r) => $this->recipient('landlord', (int) $r->id, $r->names, $r->email_address, $r->mobile_number, $r->reference_number))
            ->values();
    }

    /**
     * Specific clients picked by id — a single tenant or landlord, or a hand-picked list.
     *
     * @param array $ids sys_pm_clients ids
     */
    protected function clientsByIds(array $ids): Collection {
        $ids = array_values(array_filter($ids));
        if (empty($ids)) {
            return collect();
        }

        $clients = DB::table('sys_pm_clients as c')
            ->leftJoin('cb_tenant_records as r', function ($join) {
                $join->on('r.id', '=', 'c.id')->where('c.type_id', '2');
            })
            ->whereIn('c.id', $ids)
            ->selectRaw('c.id, c.reference_number, c.type_id, ' . self::CLIENT_CONTACT_SQL)
            ->get();
        $leases = $this->openLeaseContext($clients->filter(fn ($r) => ((string) $r->type_id) === '2')->pluck('id')->all());

        return $clients->map(function ($r) use ($leases) {
            $lease = $leases[$r->id] ?? null;

            return $this->recipient(((string) $r->type_id) === '2' ? 'tenant' : 'landlord', (int) $r->id, $r->names, $r->email_address, $r->mobile_number, $r->reference_number, $lease->property_name ?? null, $lease ? ($lease->unit_name ?: $lease->unit_number) : null);
        })->values();
    }

    /**
     * The property and unit of each tenant's most recent open lease, so {property} and {unit} are
     * filled in when a tenant is picked by name. Tenants without an open lease are left out.
     *
     * @param array $tenantIds sys_pm_clients ids of tenants
     * @return array tenant id => row with property_name, unit_name, unit_number
     */
    protected function openLeaseContext(array $tenantIds): array {
        if (empty($tenantIds)) {
            return [];
        }

        $today = date('Y-m-d');

        return DB::table('sys_pm_lease_agreements as l')
            ->leftJoin('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'l.property_id')
            ->whereIn('l.tenant_id', $tenantIds)
            ->where('l.lease_status', 'Active')
            ->where(function ($q) use ($today) {
                $q->whereNull('l.date_end')->orWhere('l.date_end', '>=', $today);
            })
            ->orderByDesc('l.date_start')
            ->get(['l.tenant_id', 'p.property_name', 'u.unit_name', 'u.unit_number'])
            ->unique('tenant_id')
            ->keyBy('tenant_id')
            ->all();
    }

    /**
     * Specific staff members picked by id: one person or several. Picked explicitly, so their
     * status is not filtered the way "all staff" is.
     *
     * @param array $ids sys_staff ids
     */
    protected function staffByIds(array $ids): Collection {
        $ids = array_values(array_filter($ids));
        if (empty($ids)) {
            return collect();
        }

        return DB::table('sys_staff')
            ->whereIn('id', $ids)
            ->get(['id', 'full_names', 'email_address', 'mobile_number', 'staff_number'])
            ->map(fn ($r) => $this->recipient('staff', (int) $r->id, $r->full_names, $r->email_address, $r->mobile_number, $r->staff_number))
            ->values();
    }

    /**
     * All active staff (current_status_id 1).
     */
    protected function activeStaff(): Collection {
        return DB::table('sys_staff')
            ->where('current_status_id', 1)
            ->get(['id', 'full_names', 'email_address', 'mobile_number', 'staff_number'])
            ->map(fn ($r) => $this->recipient('staff', (int) $r->id, $r->full_names, $r->email_address, $r->mobile_number, $r->staff_number))
            ->values();
    }

    /**
     * Normalise one recipient row: trim contacts to null when empty.
     *
     * @param string $type tenant | landlord | staff
     * @param int $id
     * @param string|null $name
     * @param string|null $email
     * @param string|null $mobile
     * @param string|null $reference
     * @param string|null $property
     * @param string|null $unit
     */
    protected function recipient(string $type, int $id, ?string $name, ?string $email, ?string $mobile, ?string $reference = null, ?string $property = null, ?string $unit = null): array {
        return [
            'recipient_type' => $type,
            'recipient_id' => $id,
            'name' => trim((string) $name) ?: 'Unknown',
            'email' => trim((string) $email) ?: null,
            'mobile' => trim((string) $mobile) ?: null,
            'reference' => trim((string) $reference) ?: null,
            'property' => trim((string) $property) ?: null,
            'unit' => trim((string) $unit) ?: null,
        ];
    }
}

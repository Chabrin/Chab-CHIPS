<?php

namespace App\Services\Comms;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns an audience choice (all tenants, tenants of a property, tenants of a zone, landlords,
 * chosen clients, active staff) into a flat recipient list with the contact and placeholder
 * context each message needs. "Tenants" always means tenants on an open lease (date_end null
 * or in the future), so an exited tenant is never messaged by a bulk send.
 *
 * Each recipient is an array: recipient_type, recipient_id, name, email, mobile, reference,
 * property, unit.
 */
class AudienceResolver
{
    const TYPES = ['single_client', 'clients', 'tenants_all', 'landlords_all', 'property', 'zone', 'staff_all'];

    /**
     * Resolve an audience into recipients.
     *
     * @param string $audienceType one of self::TYPES
     * @param array $params property_id | zone_id | client_ids, per type
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
                $names = DB::table('sys_pm_clients')->whereIn('id', $ids)->pluck('names');
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
            case 'staff_all':
                return 'All active staff';
            default:
                return $audienceType;
        }
    }

    /**
     * Tenants on an open lease, optionally narrowed to one property or one zone, each with the
     * property and unit of their most recent open lease for the {property}/{unit} placeholders.
     *
     * @param int|null $propertyId
     * @param int|null $zoneId
     */
    protected function tenantsOnOpenLeases(?int $propertyId = null, ?int $zoneId = null): Collection {
        $today = date('Y-m-d');

        $query = DB::table('sys_pm_lease_agreements as l')
            ->join('sys_pm_clients as c', 'c.id', '=', 'l.tenant_id')
            ->leftJoin('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'l.property_id')
            ->where(function ($q) use ($today) {
                $q->whereNull('l.date_end')->orWhere('l.date_end', '>=', $today);
            });

        if ($propertyId) {
            $query->where('l.property_id', $propertyId);
        }

        if ($zoneId) {
            $query->where('p.zone_id', $zoneId);
        }

        return $query
            ->orderByDesc('l.date_start')
            ->get(['l.tenant_id', 'c.names', 'c.email_address', 'c.mobile_number', 'c.reference_number', 'p.property_name', 'u.unit_name', 'u.unit_number'])
            ->unique('tenant_id')
            ->map(fn ($r) => $this->recipient('tenant', (int) $r->tenant_id, $r->names, $r->email_address, $r->mobile_number, $r->reference_number, $r->property_name, $r->unit_name ?: $r->unit_number))
            ->values();
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

        return DB::table('sys_pm_clients')
            ->whereIn('id', $ids)
            ->get(['id', 'names', 'email_address', 'mobile_number', 'reference_number', 'type_id'])
            ->map(fn ($r) => $this->recipient(((string) $r->type_id) === '2' ? 'tenant' : 'landlord', (int) $r->id, $r->names, $r->email_address, $r->mobile_number, $r->reference_number))
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

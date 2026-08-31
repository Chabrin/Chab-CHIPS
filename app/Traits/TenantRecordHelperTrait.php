<?php

namespace App\Traits;

use App\Models\Client;
use App\Models\ServiceAgreement;
use App\Models\Unit;

/**
 * Shared between TenantsController (interactive create/edit) and
 * BulkUploadController (CSV bulk upload) so both paths derive a tenant's
 * reference number, SLA, and rent the exact same way and can't drift apart.
 */
trait TenantRecordHelperTrait
{
    /**
     * Generate the next tenant reference number (format: "TEN-000001",
     * matching every existing tenant record). Computed in PHP rather than a
     * raw SQL cast — see TenantsController for the history of why.
     *
     * Uses withAll() to bypass Client's user_clients_properties global scope:
     * that scope hides clients outside the current (non-global-role) user's
     * assigned properties, and without bypassing it here a scoped user would
     * only see their own slice of reference numbers and could generate one
     * that collides with a tenant they can't see — most visibly when bulk
     * upload creates several tenants in one request.
     */
    private function generateTenantReferenceNumber()
    {
        $prefix = "TEN";

        $lastNumber = Client::withAll()
            ->where('reference_number', 'LIKE', "{$prefix}-%")
            ->pluck('reference_number')
            ->map(function ($ref) use ($prefix) {
                return preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $ref, $matches)
                    ? (int) $matches[1]
                    : null;
            })
            ->filter(fn ($number) => $number !== null)
            ->max();

        $newNumber = str_pad(($lastNumber ?? 0) + 1, 6, '0', STR_PAD_LEFT);

        return "{$prefix}-{$newNumber}";
    }

    /**
     * A property's current SLA — not picked manually, since a property's SLA
     * is already known once the property itself is selected. Most-recent-by-
     * start-date matches how the (now removed) manual SLA dropdown was
     * always ordered.
     *
     * NULLS LAST: plain `orderBy('date_start', 'desc')` puts NULL first on
     * Postgres (its default DESC behavior), so a property with an
     * undated SLA row would always win over a real, later-dated one —
     * confirmed live via a direct query. Doesn't affect any property in the
     * current dataset (none currently mix a null-dated and a real-dated SLA),
     * but it's the kind of thing that silently breaks the moment a second
     * SLA is added to a property that already has an undated one.
     */
    private function resolveSlaId(int $propertyId): ?int
    {
        return ServiceAgreement::where('property_id', $propertyId)
            ->orderByRaw('date_start DESC NULLS LAST')
            ->value('id');
    }

    /**
     * A tenant's rent is the unit's own configured rent — not re-typed per
     * tenant.
     */
    private function resolveRentAmount(int $unitId): float
    {
        return (float) (Unit::find($unitId)->rent_amount ?? 0);
    }

    /**
     * A deposit's amount is driven by how many months it covers, not typed
     * freely — unit rent × months, computed here so every place that records
     * a deposit (onboarding, the returning-tenant flow) can't disagree on
     * the math.
     */
    private function computeDepositAmount(int $unitId, float $months): float
    {
        return round($this->resolveRentAmount($unitId) * $months, 2);
    }
}

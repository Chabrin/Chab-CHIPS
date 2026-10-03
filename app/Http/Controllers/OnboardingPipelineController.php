<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Models\Unit;
use App\Services\ErrorDispatcher;
use App\Traits\HasWorkflowStatuses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class OnboardingPipelineController extends Controller
{
    use HasWorkflowStatuses;

    private const APPROVED = 5;
    private const REJECTED = 41;
    private const RECENT_LIMIT = 20;

    /**
     * Personal "my onboarding items" tracker: everything the current user created across
     * the Landlord -> Property -> SLA -> Unit -> Tenant -> Lease chain, with its approval
     * status and, once approved, a permission-aware link to the next step.
     */
    public function index()
    {
        try {
            $userId = Auth::id();

            $items = collect()
                ->merge($this->landlordItems($userId))
                ->merge($this->propertyItems($userId))
                ->merge($this->slaItems($userId))
                ->merge($this->unitItems($userId))
                ->merge($this->tenantItems($userId))
                ->merge($this->leaseItems($userId))
                ->sortByDesc('created_at')
                ->values();

            return view('property.onboarding.my-pipeline', compact('items'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading onboarding pipeline: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load onboarding pipeline');
        }
    }

    private function landlordItems(int $userId)
    {
        return Client::landlords()
            ->where('created_by', $userId)
            ->orderBy('date_time', 'desc')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function ($landlord) {
                return $this->buildItem(
                    'Landlord',
                    $landlord->id,
                    $landlord->names ?? '-',
                    $landlord->reference_number ?? '-',
                    $landlord->current_status_id,
                    $landlord->date_time,
                    // Add Property is now a modal on the client details page (no standalone
                    // create page to link to anymore) — send them there, "encryptedId" matches
                    // that route's own parameter name, not the "client" key used elsewhere here.
                    $this->singleNextStep($landlord->current_status_id, 'Add Property', 'create_properties', 'property.clients.show', $landlord->id, 'encryptedId')
                );
            });
    }

    private function propertyItems(int $userId)
    {
        return Property::where('created_by', $userId)
            ->orderBy('date_time', 'desc')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function ($property) {
                $nextStep = $this->isApproved($property->current_status_id)
                    ? [
                        'type' => 'links',
                        'links' => [
                            $this->buildLink('Add SLA', 'create_service_agreements', 'service.agreements.create', $property->client_id),
                            [
                                'label' => 'Add Unit',
                                'has_permission' => Auth::user()->hasPermission('create_units'),
                                // Unit creation is a modal on the property details page (Units tab)
                                'url' => Auth::user()->hasPermission('create_units')
                                    ? route('property.properties.show', Crypt::encrypt($property->id))
                                    : null,
                            ],
                        ],
                    ]
                    : $this->statusStep($property->current_status_id);

                return $this->buildItem(
                    'Property',
                    $property->id,
                    $property->property_name ?? '-',
                    $property->reference_number ?? '-',
                    $property->current_status_id,
                    $property->date_time,
                    $nextStep
                );
            });
    }

    private function slaItems(int $userId)
    {
        return ServiceAgreement::where('created_by_id', $userId)
            ->with('property')
            ->orderBy('date_time', 'desc')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function ($sla) {
                // SLA and Unit are parallel next steps under a Property, not sequential —
                // there's no further single "next step" action from the SLA item itself.
                return $this->buildItem(
                    'Service Agreement',
                    $sla->id,
                    $sla->property->property_name ?? '-',
                    $sla->reference_number ?? '-',
                    $sla->current_status_id,
                    $sla->date_time,
                    $this->statusStep($sla->current_status_id)
                );
            });
    }

    private function unitItems(int $userId)
    {
        return Unit::where('created_by_id', $userId)
            ->orderBy('date_time', 'desc')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function ($unit) {
                return $this->buildItem(
                    'Unit',
                    $unit->id,
                    $unit->unit_name ?? $unit->unit_code ?? '-',
                    $unit->unit_code ?? '-',
                    $unit->current_status_id,
                    $unit->date_time,
                    $this->singleNextStep($unit->current_status_id, 'Add Tenant', 'create_tenants', 'units.tenants.create', $unit->client_id)
                );
            });
    }

    private function tenantItems(int $userId)
    {
        return Client::tenants()
            ->where('created_by', $userId)
            ->with('property')
            ->orderBy('date_time', 'desc')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function ($tenant) {
                $landlordId = $tenant->property->client_id ?? null;
                return $this->buildItem(
                    'Tenant',
                    $tenant->id,
                    $tenant->names ?? '-',
                    $tenant->reference_number ?? '-',
                    $tenant->current_status_id,
                    $tenant->date_time,
                    $this->singleNextStep($tenant->current_status_id, 'Add Lease', 'create_lease_agreements', 'tenant.lease.create', $landlordId)
                );
            });
    }

    private function leaseItems(int $userId)
    {
        return LeaseAgreement::where('created_by_id', $userId)
            ->with('tenant')
            ->orderBy('date_time', 'desc')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function ($lease) {
                $status = $lease->current_status_id;
                // Lease is the last stage — Approved here means onboarding is complete,
                // not "here's another link."
                $nextStep = $this->isApproved($status)
                    ? ['type' => 'complete']
                    : $this->statusStep($status);

                return $this->buildItem(
                    'Lease',
                    $lease->id,
                    $lease->tenant->names ?? '-',
                    $lease->ref_number ?? '-',
                    $status,
                    $lease->date_time,
                    $nextStep
                );
            });
    }

    private function buildItem(string $type, int $id, string $label, string $reference, ?int $statusId, $createdAt, array $nextStep): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'encrypted_id' => Crypt::encrypt($id),
            'label' => $label,
            'reference' => $reference,
            'status_id' => $statusId,
            'status_label' => $this->workflowStatuses[$statusId] ?? 'Unknown',
            'created_at' => $createdAt,
            'created_at_formatted' => $createdAt ? $createdAt->format('d-m-Y H:i') : '-',
            'next_step' => $nextStep,
        ];
    }

    /**
     * Build the next-step value for a stage with exactly one thing to do next once approved:
     * a single link (if approved and permitted), a "no permission" marker (approved but not
     * permitted), or the current pending/rejected status (not approved yet).
     */
    private function singleNextStep(?int $statusId, string $label, string $permission, string $route, ?int $landlordId, string $paramKey = 'client'): array
    {
        if (! $this->isApproved($statusId)) {
            return $this->statusStep($statusId);
        }

        return ['type' => 'link', 'link' => $this->buildLink($label, $permission, $route, $landlordId, $paramKey)];
    }

    private function buildLink(string $label, string $permission, string $route, ?int $landlordId, string $paramKey = 'client'): array
    {
        $hasPermission = Auth::user()->hasPermission($permission);

        return [
            'label' => $label,
            'has_permission' => $hasPermission,
            'url' => ($hasPermission && $landlordId) ? route($route, [$paramKey => Crypt::encrypt($landlordId)]) : null,
        ];
    }

    /** Non-actionable status marker: pending, approved (no further single action), or rejected. */
    private function statusStep(?int $statusId): array
    {
        if ($statusId == self::REJECTED) {
            return ['type' => 'status', 'value' => 'rejected'];
        }

        if ($this->isApproved($statusId)) {
            return ['type' => 'status', 'value' => 'approved'];
        }

        return ['type' => 'status', 'value' => 'pending'];
    }

    private function isApproved(?int $statusId): bool
    {
        return $statusId == self::APPROVED;
    }
}

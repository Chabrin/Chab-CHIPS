<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfirmationLog;
use App\Models\DebtorInvoice;
use App\Traits\HasWorkflowStatuses;
use App\Traits\TenantExitSettlementCalculatorTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class PropertyConfirmationController extends Controller
{
    use HasWorkflowStatuses;
    use TenantExitSettlementCalculatorTrait;

    public function index()
    {
        $approvalSections = [
            ['permission' => 'update_properties', 'label' => 'Landlord Onboarding'],
            ['permission' => 'update_tenants', 'label' => 'Tenants'],
            ['permission' => 'update_recoveries', 'label' => 'Recoveries'],
            ['permission' => 'update_lease_agreements', 'label' => 'Lease Agreements'],
            ['permission' => 'update_inspections', 'label' => 'Inspections'],
            ['permission' => 'update_tenant_movements', 'label' => 'Tenant Movements'],
            ['permission' => 'update_tenant_blacklist', 'label' => 'Tenant Blacklist'],
            ['permission' => 'update_repair_requests', 'label' => 'Repairs & Maintenance'],
            ['permission' => 'update_tenant_exit_settlements', 'label' => 'Tenant Exit Settlements'],
        ];


        return view('property.confirmations.index', compact('approvalSections'));
    }

    private function logConfirmation($section, $action, $ids, $approvalStage = null, $metadata = null)
    {
        ConfirmationLog::create([
            'section' => $section,
            'model' => $this->getModelClass($section),
            'action' => $action,
            'ids' => is_array($ids) ? $ids : [$ids],
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
            'approval_stage' => $approvalStage,
            'metadata' => $metadata,
        ]);
    }

    private function getModelClass($section)
    {
        $map = [
            'Clients' => \App\Models\Client::class,
            'Properties' => \App\Models\Property::class,
            'Units' => \App\Models\Unit::class,
            'Tenants' => \App\Models\Client::class,
            'Service Agreements' => \App\Models\ServiceAgreement::class,
            'Recoveries' => \App\Models\Recovery::class,
            'Lease Agreements' => \App\Models\LeaseAgreement::class,
            'Inspections' => \App\Models\Inspection::class,
            'Tenant Movements' => \App\Models\TenantMovement::class,
            'Tenant Blacklist' => \App\Models\TenantBlacklist::class,
            'Repairs & Maintenance' => \App\Models\RepairRequest::class,
            'Tenant Exit Settlements' => \App\Models\TenantExitSettlement::class,
        ];
        return $map[$section] ?? '';
    }

    /**
     * A checker must hold all four underlying permissions to review a bundle, since
     * approving/rejecting one can flip a landlord, a property, its units, and its
     * service agreements all at once.
     */
    private function canConfirmLandlordOnboarding(): bool
    {
        $user = Auth::user();

        return $user
            && $user->hasPermission('update_clients')
            && $user->hasPermission('update_properties')
            && $user->hasPermission('update_units')
            && $user->hasPermission('update_service_agreements');
    }

    /**
     * Bundle a Landlord with its Property, that property's Units, and its Service
     * Agreement(s) into one reviewable card. A bundle is anchored on a Property
     * whenever the property itself, one of its units, or one of its SLAs is still
     * Pending — so a unit added later to an already-approved property still shows
     * up, with the property/landlord shown read-only for context. A Landlord with
     * no properties yet gets its own standalone bundle so it isn't lost.
     */
    public function landlordOnboardingList(Request $request)
    {
        if (!$this->canConfirmLandlordOnboarding()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $start = max(0, (int) $request->get('start', 0));
        $length = (int) $request->get('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $draw = (int) $request->get('draw', 1);
        $search = trim((string) data_get($request->get('search'), 'value', ''));

        $propertiesQuery = \App\Models\Property::where(function ($q) {
            $q->where('current_status_id', 1)
                ->orWhereHas('units', fn($uq) => $uq->where('current_status_id', 1))
                ->orWhereHas('serviceAgreements', fn($sq) => $sq->where('current_status_id', 1));
        });

        $landlordOnlyQuery = \App\Models\Client::where('current_status_id', 1)
            ->where('reference_number', 'LIKE', 'LAN%')
            ->whereDoesntHave('properties');

        if ($search !== '') {
            $propertiesQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'ILIKE', "%{$search}%")
                    ->orWhere('property_name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"));
            });
            $landlordOnlyQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'ILIKE', "%{$search}%")
                    ->orWhere('names', 'ILIKE', "%{$search}%");
            });
        }

        $bundles = collect();

        foreach ($propertiesQuery->with(['client', 'zone', 'units', 'serviceAgreements'])->get() as $property) {
            $bundles->push($this->buildPropertyOnboardingBundle($property));
        }

        foreach ($landlordOnlyQuery->get() as $landlord) {
            $bundles->push($this->buildLandlordOnlyOnboardingBundle($landlord));
        }

        $bundles = $bundles->sortByDesc('created_at_raw')->values();
        $recordsTotal = $bundles->count();

        $records = $bundles->slice($start, $length)->values()->map(function ($bundle) {
            unset($bundle['created_at_raw']);
            return $bundle;
        });

        return response()->json([
            'records' => $records,
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        ]);
    }

    private function landlordSummary(\App\Models\Client $landlord): array
    {
        return [
            'id' => $landlord->id,
            'encrypted_id' => Crypt::encrypt($landlord->id),
            'name' => $landlord->names ?? '-',
            'reference_number' => $landlord->reference_number ?? '-',
            'status_id' => $landlord->current_status_id,
            'status_label' => $this->workflowStatuses[$landlord->current_status_id] ?? 'Unknown',
        ];
    }

    private function buildPropertyOnboardingBundle(\App\Models\Property $property): array
    {
        return [
            'bundle_key' => 'property:' . $property->id,
            'created_at' => $property->date_time ? $property->date_time->format('d-m-Y H:i') : '-',
            'created_at_raw' => $property->date_time,
            'landlord' => $property->client ? $this->landlordSummary($property->client) : null,
            'property' => [
                'id' => $property->id,
                'encrypted_id' => Crypt::encrypt($property->id),
                'name' => $property->property_name ?? '-',
                'reference_number' => $property->reference_number ?? '-',
                'zone' => $property->zone->zone_name ?? '-',
                'status_id' => $property->current_status_id,
                'status_label' => $this->workflowStatuses[$property->current_status_id] ?? 'Unknown',
            ],
            'units' => $property->units->map(fn($unit) => [
                'id' => $unit->id,
                'encrypted_id' => Crypt::encrypt($unit->id),
                'code' => $unit->unit_code ?? '-',
                'number' => $unit->unit_number ?? '-',
                'status_id' => $unit->current_status_id,
                'status_label' => $this->workflowStatuses[$unit->current_status_id] ?? 'Unknown',
            ])->values(),
            'service_agreements' => $property->serviceAgreements->map(fn($sla) => [
                'id' => $sla->id,
                'encrypted_id' => Crypt::encrypt($sla->id),
                'reference_number' => $sla->reference_number ?? '-',
                'date_start' => $sla->date_start ? \Carbon\Carbon::parse($sla->date_start)->format('d-m-Y') : '-',
                'date_end' => $sla->date_end ? \Carbon\Carbon::parse($sla->date_end)->format('d-m-Y') : '-',
                'status_id' => $sla->current_status_id,
                'status_label' => $this->workflowStatuses[$sla->current_status_id] ?? 'Unknown',
            ])->values(),
        ];
    }

    private function buildLandlordOnlyOnboardingBundle(\App\Models\Client $landlord): array
    {
        return [
            'bundle_key' => 'landlord:' . $landlord->id,
            'created_at' => $landlord->date_time ? $landlord->date_time->format('d-m-Y H:i') : '-',
            'created_at_raw' => $landlord->date_time,
            'landlord' => $this->landlordSummary($landlord),
            'property' => null,
            'units' => [],
            'service_agreements' => [],
        ];
    }

    /**
     * Approve or reject every still-Pending piece of a bundle (landlord, property,
     * units, service agreements) in one transaction. The bundle is re-derived
     * server-side from bundle_key rather than trusting any client-supplied id list.
     * Already-resolved pieces are left untouched; a bundle with nothing left pending
     * (e.g. re-opened after a partial prior action) is a no-op, not an error.
     */
    private function processLandlordOnboardingAction(Request $request, int $newStatus, string $action)
    {
        if (!$this->canConfirmLandlordOnboarding()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $bundleKey = (string) $request->input('bundle_key', '');
        $rejectionReason = $request->input('rejection_reason');

        if ($action === 'reject' && empty($rejectionReason)) {
            return response()->json(['success' => false, 'error' => 'A rejection reason is required.'], 422);
        }

        [$type, $id] = array_pad(explode(':', $bundleKey, 2), 2, null);
        if (!in_array($type, ['property', 'landlord'], true) || !ctype_digit((string) $id)) {
            return response()->json(['success' => false, 'error' => 'Invalid bundle.'], 422);
        }

        try {
            $flipped = [];

            DB::transaction(function () use ($type, $id, $newStatus, &$flipped) {
                if ($type === 'landlord') {
                    $landlord = \App\Models\Client::findOrFail($id);
                    if ((int) $landlord->current_status_id === 1) {
                        $landlord->current_status_id = $newStatus;
                        $landlord->save();
                        $flipped['Clients'] = [$landlord->id];
                    }
                    return;
                }

                $property = \App\Models\Property::with(['client', 'units', 'serviceAgreements'])->findOrFail($id);

                if ((int) $property->current_status_id === 1) {
                    $property->current_status_id = $newStatus;
                    $property->save();
                    $flipped['Properties'] = [$property->id];
                }

                if ($property->client && (int) $property->client->current_status_id === 1) {
                    $landlord = $property->client;
                    $landlord->current_status_id = $newStatus;
                    $landlord->save();
                    $flipped['Clients'] = [$landlord->id];
                }

                $pendingUnits = $property->units->where('current_status_id', 1);
                if ($pendingUnits->isNotEmpty()) {
                    \App\Models\Unit::whereIn('id', $pendingUnits->pluck('id'))->update(['current_status_id' => $newStatus]);
                    $flipped['Units'] = $pendingUnits->pluck('id')->values()->all();
                }

                $pendingSlas = $property->serviceAgreements->where('current_status_id', 1);
                if ($pendingSlas->isNotEmpty()) {
                    \App\Models\ServiceAgreement::whereIn('id', $pendingSlas->pluck('id'))->update(['current_status_id' => $newStatus]);
                    $flipped['Service Agreements'] = $pendingSlas->pluck('id')->values()->all();
                }
            });

            if (empty($flipped)) {
                return response()->json(['success' => true, 'message' => 'Nothing pending in this bundle — already resolved.']);
            }

            foreach ($flipped as $section => $ids) {
                $this->logConfirmation($section, $action, $ids, null, [
                    'new_status_id' => $newStatus,
                    'action_type' => 'bundle_' . $action,
                    'bundle_key' => "{$type}:{$id}",
                    'rejection_reason' => $action === 'reject' ? $rejectionReason : null,
                ]);
            }

            $summary = collect($flipped)->map(fn($ids, $section) => count($ids) . ' ' . $section)->implode(', ');

            return response()->json(['success' => true, 'message' => ucfirst($action) . 'd: ' . $summary]);
        } catch (\Exception $e) {
            Log::error("Error processing landlord onboarding {$action}: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => "Failed to {$action} bundle"], 500);
        }
    }

    public function landlordOnboardingApprove(Request $request)
    {
        return $this->processLandlordOnboardingAction($request, 5, 'approve');
    }

    public function landlordOnboardingReject(Request $request)
    {
        return $this->processLandlordOnboardingAction($request, 41, 'reject');
    }

    public function approvalsList(Request $request)
    {
        $section = $request->get('section');
        $records = [];
        $recordsTotal = 0;

        // Server-side pagination — several of these queues run into the tens of
        // thousands of pending rows, which previously loaded (with eager relations)
        // in full on every request and exhausted PHP's memory limit.
        $start = max(0, (int) $request->get('start', 0));
        $length = (int) $request->get('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $draw = (int) $request->get('draw', 1);
        $search = trim((string) data_get($request->get('search'), 'value', ''));

        switch ($section) {
            case 'Clients':
                $base = \App\Models\Client::where('current_status_id', 1) // Pending
                    ->where('reference_number', 'LIKE', 'LAN%'); // Only landlords/clients
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('reference_number', 'ILIKE', "%{$search}%")
                            ->orWhere('names', 'ILIKE', "%{$search}%")
                            ->orWhere('email_address', 'ILIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'ILIKE', "%{$search}%");
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($client) {
                        return [
                            'id' => $client->id,
                            'encrypted_id' => Crypt::encrypt($client->id),
                            'reference_number' => $client->reference_number ?? '-',
                            'names' => $client->names ?? '-',
                            'email' => $client->email_address ?? '-',
                            'phone_number' => $client->mobile_number ?? '-',
                            'client_type' => 'Landlord',
                            'zone' => '-',
                            'created_at' => $client->date_time ? $client->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $client->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Properties':
                $base = \App\Models\Property::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('reference_number', 'ILIKE', "%{$search}%")
                            ->orWhere('property_name', 'ILIKE', "%{$search}%")
                            ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['client', 'zone'])
                    ->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($property) {
                        return [
                            'id' => $property->id,
                            'encrypted_id' => Crypt::encrypt($property->id),
                            'reference_number' => $property->reference_number ?? '-',
                            'property_name' => $property->property_name ?? '-',
                            'client' => $property->client->names ?? '-',
                            'zone' => $property->zone->zone_name ?? '-',
                            'property_type' => '-',
                            'created_at' => $property->date_time ? $property->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $property->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Units':
                $base = \App\Models\Unit::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('unit_code', 'ILIKE', "%{$search}%")
                            ->orWhere('unit_number', 'ILIKE', "%{$search}%")
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['property', 'client'])
                    ->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($unit) {
                        return [
                            'id' => $unit->id,
                            'encrypted_id' => Crypt::encrypt($unit->id),
                            'unit_code' => $unit->unit_code ?? '-',
                            'unit_number' => $unit->unit_number ?? '-',
                            'property' => $unit->property->property_name ?? '-',
                            'client' => $unit->client->names ?? '-',
                            'unit_type' => '-',
                            'occupancy_status' => $this->workflowStatuses[$unit->occupancy_status_id] ?? '-',
                            'created_at' => $unit->date_time ? $unit->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $unit->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenants':
                $base = \App\Models\Client::where('current_status_id', 1) // Pending
                    ->where('reference_number', 'LIKE', 'TEN%'); // Only tenants
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('reference_number', 'ILIKE', "%{$search}%")
                            ->orWhere('names', 'ILIKE', "%{$search}%")
                            ->orWhere('email_address', 'ILIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'ILIKE', "%{$search}%")
                            ->orWhere('national_id', 'ILIKE', "%{$search}%");
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($tenant) {
                        return [
                            'id' => $tenant->id,
                            'encrypted_id' => Crypt::encrypt($tenant->id),
                            'reference_number' => $tenant->reference_number ?? '-',
                            'names' => $tenant->names ?? '-',
                            'email' => $tenant->email_address ?? '-',
                            'phone_number' => $tenant->mobile_number ?? '-',
                            'id_number' => $tenant->national_id ?? '-',
                            'created_at' => $tenant->date_time ? $tenant->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $tenant->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Service Agreements':
                $base = \App\Models\ServiceAgreement::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('reference_number', 'ILIKE', "%{$search}%")
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['property', 'client'])
                    ->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($sla) {
                        return [
                            'id' => $sla->id,
                            'encrypted_id' => Crypt::encrypt($sla->id),
                            'ref_number' => $sla->reference_number ?? '-',
                            'property' => $sla->property->property_name ?? '-',
                            'client' => $sla->client->names ?? '-',
                            'date_start' => $sla->date_start ? \Carbon\Carbon::parse($sla->date_start)->format('d-m-Y') : '-',
                            'date_end' => $sla->date_end ? \Carbon\Carbon::parse($sla->date_end)->format('d-m-Y') : '-',
                            'duration_years' => $sla->years ?? '-',
                            'created_at' => $sla->date_time ? $sla->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $sla->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Recoveries':
                $base = \App\Models\Recovery::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('ref_number', 'ILIKE', "%{$search}%")
                            ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['client', 'property', 'zone'])
                    ->orderBy('recovery_date', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($recovery) {
                        return [
                            'id' => $recovery->id,
                            'encrypted_id' => Crypt::encrypt($recovery->id),
                            'ref_number' => $recovery->ref_number ?? '-',
                            'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-',
                            'amount' => number_format($recovery->amount ?? 0, 2),
                            'client' => $recovery->client->names ?? '-',
                            'property' => $recovery->property->property_name ?? '-',
                            'zone' => $recovery->zone->zone_name ?? '-',
                            'commisionable' => $recovery->commisionable ?? 'No',
                            'created_at' => $recovery->date_time ? $recovery->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $recovery->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Lease Agreements':
                $base = \App\Models\LeaseAgreement::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('ref_number', 'ILIKE', "%{$search}%")
                            ->orWhereHas('tenant', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('unit', fn($rq) => $rq->where('unit_number', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['tenant', 'property', 'unit'])
                    ->orderBy('date_start', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($lease) {
                        return [
                            'id' => $lease->id,
                            'encrypted_id' => Crypt::encrypt($lease->id),
                            'ref_number' => $lease->ref_number ?? '-',
                            'tenant' => $lease->tenant->names ?? '-',
                            'property' => $lease->property->property_name ?? '-',
                            'unit' => $lease->unit->unit_number ?? '-',
                            'date_start' => $lease->date_start ? \Carbon\Carbon::parse($lease->date_start)->format('d-m-Y') : '-',
                            'date_end' => $lease->date_end ? \Carbon\Carbon::parse($lease->date_end)->format('d-m-Y') : '-',
                            'rent_amount' => '-',
                            'created_at' => $lease->date_time ? $lease->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => '-',
                        ];
                    });
                break;

            case 'Inspections':
                $base = \App\Models\Inspection::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->whereHas('zone', fn($rq) => $rq->where('zone_name', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['zone', 'property', 'createdBy'])
                    ->orderBy('visited_date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($inspection) {
                        return [
                            'id' => $inspection->id,
                            'encrypted_id' => Crypt::encrypt($inspection->id),
                            'inspection_date' => $inspection->visited_date_time ? \Carbon\Carbon::parse($inspection->visited_date_time)->format('d-m-Y') : '-',
                            'zone' => $inspection->zone->zone_name ?? '-',
                            'property' => $inspection->property->property_name ?? '-',
                            'inspection_type' => '-',
                            'general_remarks' => '-',
                            'created_at' => $inspection->date_time ? $inspection->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $inspection->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenant Movements':
                $base = \App\Models\TenantMovement::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('reference_number', 'ILIKE', "%{$search}%")
                            ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['client', 'property', 'unitFrom', 'unitTo', 'lease', 'createdBy'])
                    ->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($movement) {
                        return [
                            'id' => $movement->id,
                            'encrypted_id' => Crypt::encrypt($movement->id),
                            'ref_number' => $movement->ref_number ?? '-',
                            'client' => $movement->client->names ?? '-',
                            'property' => $movement->property->property_name ?? '-',
                            'unit_from' => $movement->unitFrom->unit_code ?? '-',
                            'unit_to' => $movement->unitTo->unit_code ?? '-',
                            'lease' => $movement->lease->ref_number ?? '-',
                            'effective_date' => $movement->effective_date ? \Carbon\Carbon::parse($movement->effective_date)->format('d-m-Y') : '-',
                            'created_at' => $movement->date_time ? $movement->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $movement->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenant Blacklist':
                $base = \App\Models\TenantBlacklist::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('description', 'ILIKE', "%{$search}%")
                            ->orWhereHas('tenant', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['tenant', 'client', 'property', 'unit', 'lease', 'reason', 'zone', 'createdBy'])
                    ->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($blacklist) {
                        return [
                            'id' => $blacklist->id,
                            'encrypted_id' => Crypt::encrypt($blacklist->id),
                            'blacklist_date' => $blacklist->blacklist_date ? \Carbon\Carbon::parse($blacklist->blacklist_date)->format('d-m-Y') : '-',
                            'tenant' => $blacklist->tenant->names ?? '-',
                            'client' => $blacklist->client->names ?? '-',
                            'property' => $blacklist->property->property_name ?? '-',
                            'unit' => $blacklist->unit->unit_code ?? '-',
                            'lease' => $blacklist->lease->ref_number ?? '-',
                            'reason' => $blacklist->reason->reason_name ?? '-',
                            'description' => $blacklist->description ?? '-',
                            'current_balance' => number_format($blacklist->current_balance ?? 0, 2),
                            'zone' => $blacklist->zone->zone_name ?? '-',
                            'created_at' => $blacklist->date_time ? $blacklist->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $blacklist->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Repairs & Maintenance':
                $base = \App\Models\RepairRequest::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->where('description', 'ILIKE', "%{$search}%")
                            ->orWhereHas('property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('client', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['property', 'client', 'zone', 'createdBy'])
                    ->orderBy('date_time', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($repair) {
                        return [
                            'id' => $repair->id,
                            'encrypted_id' => Crypt::encrypt($repair->id),
                            'ref_number' => $repair->ref_number ?? '-',
                            'property' => $repair->property->property_name ?? '-',
                            'client' => $repair->client->names ?? '-',
                            'zone' => $repair->zone->zone_name ?? '-',
                            'visited_date' => $repair->date_time ? \Carbon\Carbon::parse($repair->date_time)->format('d-m-Y H:i') : '-',
                            'description' => $repair->description ?? '-',
                            'created_at' => $repair->date_time ? $repair->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $repair->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenant Exit Settlements':
                $base = \App\Models\TenantExitSettlement::where('current_status_id', 1); // Pending
                if ($search !== '') {
                    $base->where(function ($q) use ($search) {
                        $q->whereHas('exit.tenant', fn($rq) => $rq->where('names', 'ILIKE', "%{$search}%"))
                            ->orWhereHas('exit.property', fn($rq) => $rq->where('property_name', 'ILIKE', "%{$search}%"));
                    });
                }
                $recordsTotal = (clone $base)->count();
                $records = $base->with(['exit.tenant', 'exit.property', 'settledBy'])
                    ->orderBy('settled_at', 'desc')
                    ->skip($start)->take($length)
                    ->get()
                    ->map(function ($settlement) {
                        return [
                            'id' => $settlement->id,
                            'encrypted_id' => Crypt::encrypt($settlement->id),
                            'tenant' => $settlement->exit->tenant->names ?? '-',
                            'property' => $settlement->exit->property->property_name ?? '-',
                            'settlement_type' => $settlement->settlement_type ?? '-',
                            'settlement_balance' => number_format($settlement->settlement_balance ?? 0, 2),
                            'submitted_by' => $settlement->settledBy->name ?? '-',
                            'submitted_at' => $settlement->settled_at ? $settlement->settled_at->format('d-m-Y H:i') : '-',
                        ];
                    });
                break;
        }

        return response()->json([
            'records' => $records,
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $section = $request->input('section');
        
        try {
            $previousStatus = null;
            $newStatus = null;
            
            switch ($section) {
                case 'Clients':
                    $record = \App\Models\Client::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Properties':
                    $record = \App\Models\Property::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Units':
                    $record = \App\Models\Unit::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Tenants':
                    $record = \App\Models\Client::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Service Agreements':
                    $record = \App\Models\ServiceAgreement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Recoveries':
                    $record = \App\Models\Recovery::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Lease Agreements':
                    $record = \App\Models\LeaseAgreement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Inspections':
                    $record = \App\Models\Inspection::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Tenant Movements':
                    $record = \App\Models\TenantMovement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $newStatus = 5;
                    $this->applyTenantMovementApproval($record);
                    break;

                case 'Tenant Blacklist':
                    $record = \App\Models\TenantBlacklist::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 17; // Processed
                    $newStatus = 17;
                    $record->save();
                    break;

                case 'Repairs & Maintenance':
                    $record = \App\Models\RepairRequest::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Tenant Exit Settlements':
                    $record = \App\Models\TenantExitSettlement::findOrFail($id);
                    if ((int) $record->settled_by === (int) Auth::id()) {
                        return response()->json(['success' => false, 'error' => 'A different user must approve this settlement — you cannot approve your own submission.'], 422);
                    }
                    $previousStatus = $record->current_status_id;
                    $newStatus = 5;
                    $this->applyTenantExitSettlementApproval($record);
                    break;

                default:
                    return response()->json(['success' => false, 'error' => 'Unknown confirmation section.'], 422);
            }

            $this->logConfirmation($section, 'approve', $id, null, [
                'previous_status_id' => $previousStatus,
                'new_status_id' => $newStatus,
                'action_type' => 'approval'
            ]);

            return response()->json(['success' => true, 'message' => 'Record approved successfully']);
        } catch (\Exception $e) {
            Log::error('Error approving record: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to approve record'], 500);
        }
    }

    /**
     * Apply a Tenant Movement approval: mark the movement approved, terminate the old
     * lease, free the old unit, activate the new lease, occupy the new unit, and — if
     * flagged and the tenant is in arrears — raise a carry-forward invoice. Wrapped in
     * a transaction so a failure partway through can't leave a half-applied movement.
     * Shared by both single and bulk approval so the two can never drift apart.
     */
    private function applyTenantMovementApproval(\App\Models\TenantMovement $record): void
    {
        DB::transaction(function () use ($record) {
            $record->current_status_id = 5; // Approved
            $record->save();

            // Terminate old lease
            if ($record->lease_id) {
                $oldLease = \App\Models\LeaseAgreement::find($record->lease_id);
                if ($oldLease) {
                    $oldLease->current_status_id = 28; // Terminated
                    $oldLease->save();
                }
            }

            // Release old unit — vacant
            if ($record->unit_from_id) {
                $oldUnit = \App\Models\Unit::find($record->unit_from_id);
                if ($oldUnit) {
                    $oldUnit->occupancy_status_id = 31;
                    $oldUnit->current_status_id   = 31;
                    $oldUnit->save();
                }
            }

            // Activate new lease
            if ($record->new_lease_id) {
                $newLease = \App\Models\LeaseAgreement::find($record->new_lease_id);
                if ($newLease) {
                    $newLease->current_status_id = 5; // Active
                    $newLease->save();
                }
            }

            // Mark new unit as occupied
            if ($record->unit_to_id) {
                $newUnit = \App\Models\Unit::find($record->unit_to_id);
                if ($newUnit) {
                    $newUnit->occupancy_status_id = 38; // Occupied
                    $newUnit->save();
                }
            }

            // Carry forward the balance — only if flagged. Re-link the tenant's own
            // not-yet-cleared invoices from the old lease onto the new one, rather than
            // fabricating a brand-new invoice on top of them: TenantBalanceService sums
            // a tenant's invoices tenant-wide (not lease-scoped), so a new invoice for
            // the same arrears amount would double-count it while the old invoices sat
            // there untouched. Already-Cleared (9) invoices are left on the old lease so
            // historical reporting for that lease stays accurate.
            if ($record->move_with_balance && $record->tenant_id && $record->lease_id && $record->new_lease_id) {
                DebtorInvoice::where('tenant_id', $record->tenant_id)
                    ->where('lease_id', $record->lease_id)
                    ->where('current_status_id', '!=', 9)
                    ->update([
                        'lease_id'    => $record->new_lease_id,
                        'unit_id'     => $record->unit_to_id,
                        'property_id' => $record->property_id,
                        'zone_id'     => $record->zone_id,
                        'sla_id'      => $record->sla_id,
                    ]);
            }

            // Move the tenant's deposit(s) with them — unconditional on move_with_balance,
            // since the deposit is a separate concern from arrears. Deposit rows are tied
            // to a specific lease_id/unit_id, so without this they'd stay attached to the
            // old, now-terminated lease forever.
            if ($record->tenant_id && $record->lease_id && $record->new_lease_id) {
                \App\Models\Deposit::where('tenant_id', $record->tenant_id)
                    ->where('lease_id', $record->lease_id)
                    ->update([
                        'lease_id'    => $record->new_lease_id,
                        'unit_id'     => $record->unit_to_id,
                        'property_id' => $record->property_id,
                        'zone_id'     => $record->zone_id,
                        'sla_id'      => $record->sla_id,
                    ]);
            }
        });
    }

    /**
     * Apply a Tenant Exit Settlement approval: post the ledger entries/refund/invoice
     * implied by the settlement type, split the deposit into ARREARS/REPAIRS records,
     * terminate the lease, unlink the tenant from their unit/property, and mark the
     * exit fully completed. This is exactly the side-effect work that used to run
     * unconditionally inside TenantExitController::saveSettlement() — it now only runs
     * once a *different* user approves the settlement (self-approval is blocked in
     * approve()/bulkApprove() before this is ever called). Wrapped in a transaction so
     * a failure partway through can't leave a half-applied settlement. Shared by both
     * single and bulk approval so the two can never drift apart.
     */
    private function applyTenantExitSettlementApproval(\App\Models\TenantExitSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement) {
            $exit = \App\Models\TenantExit::findOrFail($settlement->exit_id);
            $refNum = 'EXIT-' . date('Ymd') . '-' . str_pad($settlement->id, 5, '0', STR_PAD_LEFT);

            // Recompute fresh from the tenant's current ledger/deposit/inspection data rather
            // than trusting the figures stored at submission time — the tenant's balance could
            // have changed in the time between submission and this approval (a payment posted,
            // an invoice raised, etc.), and this is the point where money actually moves, so
            // it must reflect reality now, not a possibly-stale snapshot. other_deductions is
            // the one manual figure carried through unchanged, since it's not derivable from
            // any table.
            $fresh = $this->computeTenantExitSettlement($exit, (float) $settlement->other_deductions);
            $settlement->fill($fresh);

            if ($settlement->settlement_type === 'Refund to Tenant' && $settlement->settlement_balance > 0.005) {
                \App\Models\TenantAccountTransaction::create([
                    'date_time'         => now(),
                    'narration'         => 'Exit deposit settlement — refund to tenant',
                    'dr_cr'             => 'CR',
                    'debit_amount'      => 0,
                    'credit_amount'     => $settlement->settlement_balance,
                    // receipt_number on sys_pm_tenants_accounts_trans is numeric, not text
                    // (confirmed against live schema — same issue already fixed once this
                    // session for the deposit-capture ledger write); entry_ref is the
                    // correct free-text reference column.
                    'entry_ref'         => $refNum,
                    'tenant_id'         => $settlement->tenant_id,
                    'property_id'       => $exit->property_id,
                    'unit_id'           => $exit->unit_id,
                    'current_status_id' => 1,
                    'entry_by'          => Auth::id(),
                ]);

                \App\Models\TenantExitDepositRefund::create([
                    'exit_id'       => $settlement->exit_id,
                    'tenant_id'     => $settlement->tenant_id,
                    'refund_amount' => $settlement->settlement_balance,
                    'refund_notes'  => $settlement->notes ?? 'Exit settlement refund',
                    'refunded_by'   => Auth::id(),
                    'refunded_at'   => now(),
                ]);

            } elseif ($settlement->settlement_type === 'Invoice to Tenant' && $settlement->settlement_balance > 0.005) {
                $invoice = DebtorInvoice::create([
                    'date_time'         => now(),
                    'date_due'          => now()->addDays(30),
                    'debtor_type_id'    => 2,
                    'tenant_id'         => $settlement->tenant_id,
                    'property_id'       => $exit->property_id,
                    'unit_id'           => $exit->unit_id,
                    'reference_number'  => $refNum,
                    'narration'         => 'Exit settlement — balance due after deposit deductions',
                    'total_amount'      => $settlement->settlement_balance,
                    'current_balance'   => $settlement->settlement_balance,
                    'current_status_id' => 1,
                    'created_by'        => Auth::id(),
                    'created_date'      => now(),
                ]);

                \App\Models\TenantAccountTransaction::create([
                    'date_time'         => now(),
                    'narration'         => 'Exit settlement — amount due from tenant after deposit deductions',
                    'dr_cr'             => 'DR',
                    'debit_amount'      => $settlement->settlement_balance,
                    'credit_amount'     => 0,
                    'invoice_number'    => $refNum,
                    'invoice_id'        => $invoice->id,
                    'tenant_id'         => $settlement->tenant_id,
                    'property_id'       => $exit->property_id,
                    'unit_id'           => $exit->unit_id,
                    'current_status_id' => 1,
                    'entry_by'          => Auth::id(),
                ]);
            }

            // Shortfall invoice for deposit-as-rent
            if ($exit->deposit_as_rent && ($exit->deposit_as_rent_amount ?? 0) > 0) {
                $unit         = \App\Models\Unit::find($exit->unit_id);
                $monthlyRent  = (float) ($unit?->rent_amount ?? 0);
                $depositTotal = (float) \App\Models\Deposit::where('tenant_id', $settlement->tenant_id)->sum('amount');
                $shortfall    = max(0, $monthlyRent - $depositTotal);

                if ($shortfall > 0.005) {
                    $sfRefNum = 'EXIT-SF-' . date('Ymd') . '-' . str_pad($settlement->id, 5, '0', STR_PAD_LEFT);
                    $sfInvoice = DebtorInvoice::create([
                        'date_time'         => now(),
                        'date_due'          => now()->addDays(14),
                        'debtor_type_id'    => 2,
                        'tenant_id'         => $settlement->tenant_id,
                        'property_id'       => $exit->property_id,
                        'unit_id'           => $exit->unit_id,
                        'reference_number'  => $sfRefNum,
                        'narration'         => 'Final rent shortfall — deposit partially applied, balance due',
                        'total_amount'      => $shortfall,
                        'current_balance'   => $shortfall,
                        'current_status_id' => 1,
                        'created_by'        => Auth::id(),
                        'created_date'      => now(),
                    ]);

                    \App\Models\TenantAccountTransaction::create([
                        'date_time'         => now(),
                        'narration'         => 'Exit — final rent shortfall (deposit applied to rent, balance owed)',
                        'dr_cr'             => 'DR',
                        'debit_amount'      => $shortfall,
                        'credit_amount'     => 0,
                        'invoice_number'    => $sfRefNum,
                        'invoice_id'        => $sfInvoice->id,
                        'tenant_id'         => $settlement->tenant_id,
                        'property_id'       => $exit->property_id,
                        'unit_id'           => $exit->unit_id,
                        'current_status_id' => 1,
                        'entry_by'          => Auth::id(),
                    ]);
                }
            }

            // Record how the deposit was split (ARREARS and REPAIRS portions)
            $arrearsAmt = (float) $settlement->arrears_amount;
            $repairsAmt = (float) $settlement->inspection_damage + (float) $settlement->cleaning_cost;
            if ($arrearsAmt > 0.005) {
                \App\Models\DepositApplication::updateOrCreate(
                    ['exit_id' => $exit->id, 'type' => 'ARREARS'],
                    ['tenant_id' => $settlement->tenant_id, 'amount' => $arrearsAmt]
                );
            }
            if ($repairsAmt > 0.005) {
                \App\Models\DepositApplication::updateOrCreate(
                    ['exit_id' => $exit->id, 'type' => 'REPAIRS'],
                    ['tenant_id' => $settlement->tenant_id, 'amount' => $repairsAmt]
                );
            }

            // Finalise the lease (Vacating → Terminated) and clear tenant links
            \App\Models\LeaseAgreement::where('tenant_id', $settlement->tenant_id)
                ->where('lease_status', 'Vacating')
                ->update([
                    'lease_status'      => 'Terminated',
                    'current_status_id' => 2,
                ]);

            $tenant = \App\Models\Client::find($settlement->tenant_id);
            if ($tenant) {
                $tenant->update(['unit_id' => null, 'property_id' => null]);
            }

            // Mark exit fully completed
            $exit->update([
                'current_status_id' => 2,
                'final_notes'       => $settlement->notes ?? null,
                'finalised_by'      => Auth::id(),
                'finalised_at'      => now(),
                'updated_by'        => Auth::id(),
            ]);

            // Mark the settlement itself Approved
            $settlement->current_status_id = 5;
            $settlement->approved_by = Auth::id();
            $settlement->approved_at = now();
            $settlement->save();
        });
    }

    public function reject(Request $request, $id)
    {
        $section = $request->input('section');
        $rejectionReason = $request->input('rejection_reason', null);
        
        try {
            $previousStatus = null;
            $newStatus = null;
            
            switch ($section) {
                case 'Clients':
                    $record = \App\Models\Client::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Properties':
                    $record = \App\Models\Property::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Units':
                    $record = \App\Models\Unit::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Tenants':
                    $record = \App\Models\Client::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Service Agreements':
                    $record = \App\Models\ServiceAgreement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Recoveries':
                    $record = \App\Models\Recovery::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Lease Agreements':
                    $record = \App\Models\LeaseAgreement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Inspections':
                    $record = \App\Models\Inspection::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Tenant Movements':
                    $record = \App\Models\TenantMovement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Tenant Blacklist':
                    $record = \App\Models\TenantBlacklist::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Repairs & Maintenance':
                    $record = \App\Models\RepairRequest::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Tenant Exit Settlements':
                    $record = \App\Models\TenantExitSettlement::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $record->rejection_reason = $rejectionReason;
                    $newStatus = 41;
                    $record->save();
                    break;

                default:
                    return response()->json(['success' => false, 'error' => 'Unknown confirmation section.'], 422);
            }

            $this->logConfirmation($section, 'reject', $id, null, [
                'rejection_reason' => $rejectionReason,
                'previous_status_id' => $previousStatus,
                'new_status_id' => $newStatus,
                'action_type' => 'rejection'
            ]);

            return response()->json(['success' => true, 'message' => 'Record rejected successfully']);
        } catch (\Exception $e) {
            Log::error('Error rejecting record: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to reject record'], 500);
        }
    }

    public function bulkApprove(Request $request)
    {
        $ids = $request->input('ids', []);
        $section = $request->input('section');
        $skipped = [];

        try {
            switch ($section) {
                case 'Clients':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Properties':
                    \App\Models\Property::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Units':
                    \App\Models\Unit::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Tenants':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Service Agreements':
                    \App\Models\ServiceAgreement::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Recoveries':
                    \App\Models\Recovery::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Lease Agreements':
                    \App\Models\LeaseAgreement::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Inspections':
                    \App\Models\Inspection::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Tenant Movements':
                    $movements = \App\Models\TenantMovement::whereIn('id', $ids)->get();
                    foreach ($movements as $movement) {
                        $this->applyTenantMovementApproval($movement);
                    }
                    break;

                case 'Tenant Blacklist':
                    \App\Models\TenantBlacklist::whereIn('id', $ids)->update(['current_status_id' => 17]);
                    break;

                case 'Repairs & Maintenance':
                    \App\Models\RepairRequest::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Tenant Exit Settlements':
                    $settlements = \App\Models\TenantExitSettlement::whereIn('id', $ids)->get();
                    foreach ($settlements as $settlement) {
                        if ((int) $settlement->settled_by === (int) Auth::id()) {
                            $skipped[] = $settlement->id . ' (same user submitted and approved)';
                            continue;
                        }
                        $this->applyTenantExitSettlementApproval($settlement);
                    }
                    break;

                default:
                    return response()->json(['success' => false, 'error' => 'Unknown confirmation section.'], 422);
            }

            $this->logConfirmation($section, 'bulk approve', $ids, null, [
                'records_count' => count($ids),
                'new_status_id' => 5,
                'action_type' => 'bulk_approval',
                'skipped' => $skipped ?: null,
            ]);

            $message = 'Records approved successfully';
            if (!empty($skipped)) {
                $message .= '. Skipped (self-approval not allowed): ' . implode(', ', $skipped);
            }

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            Log::error('Error bulk approving records: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to approve records'], 500);
        }
    }

    public function bulkReject(Request $request)
    {
        $ids = $request->input('ids', []);
        $section = $request->input('section');
        $rejectionReason = $request->input('rejection_reason', null);
        
        try {
            switch ($section) {
                case 'Clients':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Properties':
                    \App\Models\Property::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Units':
                    \App\Models\Unit::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenants':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Service Agreements':
                    \App\Models\ServiceAgreement::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Recoveries':
                    \App\Models\Recovery::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Lease Agreements':
                    \App\Models\LeaseAgreement::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Inspections':
                    \App\Models\Inspection::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenant Movements':
                    \App\Models\TenantMovement::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenant Blacklist':
                    \App\Models\TenantBlacklist::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Repairs & Maintenance':
                    \App\Models\RepairRequest::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenant Exit Settlements':
                    \App\Models\TenantExitSettlement::whereIn('id', $ids)->update([
                        'current_status_id' => 41,
                        'rejection_reason' => $rejectionReason,
                    ]);
                    break;

                default:
                    return response()->json(['success' => false, 'error' => 'Unknown confirmation section.'], 422);
            }

            $this->logConfirmation($section, 'bulk reject', $ids, null, [
                'rejection_reason' => $rejectionReason,
                'records_count' => count($ids),
                'new_status_id' => 41,
                'action_type' => 'bulk_rejection'
            ]);

            return response()->json(['success' => true, 'message' => 'Records rejected successfully']);
        } catch (\Exception $e) {
            Log::error('Error bulk rejecting records: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to reject records'], 500);
        }
    }
}

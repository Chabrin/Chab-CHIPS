<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Client;
use App\Models\TenantRecord;
use App\Models\Property;
use App\Traits\TenantRecordHelperTrait;
use App\Traits\LeaseCreationTrait;
use Illuminate\Http\Request;
use App\Models\LeaseAgreement;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\LeaseAgreementInvoiceableItem;
use App\Services\LeaseContractService;

class LeasesController extends Controller
{
    use TenantRecordHelperTrait;
    use LeaseCreationTrait;

    /**
     * Display a listing of lease agreements
     */
    public function index()
    {
        try {
            return view('property.properties.lease.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading lease index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load leases');
        }
    }

    /**
     * Stream the signed lease contract PDF for viewing.
     */
    public function viewSignedContract($encryptedId)
    {
        try {
            $id    = Crypt::decrypt($encryptedId);
            $lease = LeaseAgreement::findOrFail($id);

            if (empty($lease->signed_pdf_path)) {
                abort(404, 'No signed contract available for this lease.');
            }

            $filename = basename($lease->signed_pdf_path);

            if (Storage::disk('local')->exists($lease->signed_pdf_path)) {
                return Storage::disk('local')->response($lease->signed_pdf_path, $filename, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }

            if (Storage::disk('public')->exists($lease->signed_pdf_path)) {
                return Storage::disk('public')->response($lease->signed_pdf_path, $filename, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }

            abort(404, 'Contract file not found.');
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            abort(403);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error viewing signed contract: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load contract'], 500);
        }
    }

    /**
     * Urgent leases: terminated, or ending within the next 3 days (includes
     * already-passed end dates) — the "Expiring Leases (Urgent)" tab.
     */
    public function getUrgentLeases()
    {
        try {
            $cutoff = now()->addDays(3)->endOfDay();

            $leases = LeaseAgreement::with(['tenant:id,names', 'tenant.tenantRecord:id,names', 'property:id,property_name', 'unit:id,unit_code'])
                ->where(function ($q) use ($cutoff) {
                    $q->where('lease_status', 'Terminated')
                        ->orWhere(function ($q2) use ($cutoff) {
                            $q2->whereNotNull('date_end')->where('date_end', '<=', $cutoff);
                        });
                })
                ->select(['id', 'tenant_id', 'property_id', 'unit_id', 'ref_number', 'date_end', 'lease_status'])
                // Every matching row has date_end <= today+3 (or is Terminated with no
                // date at all), so DESC surfaces the ones closest to "now" — the most
                // actionable — first, instead of burying them under leases years overdue.
                // NULLS LAST keeps undated Terminated rows out of the way at the bottom.
                ->orderByRaw('date_end DESC NULLS LAST')
                ->limit(500)
                ->get()
                ->map(function ($lease) {
                    return [
                        'id' => $lease->id,
                        'encrypted_id' => Crypt::encrypt($lease->id),
                        'ref_number' => $lease->ref_number,
                        'tenant_name' => $lease->tenant->names ?? 'N/A',
                        'encrypted_tenant_id' => $lease->tenant_id ? Crypt::encrypt($lease->tenant_id) : null,
                        'property_name' => $lease->property->property_name ?? 'N/A',
                        'unit_code' => $lease->unit->unit_code ?? 'N/A',
                        'date_end' => $lease->date_end,
                        'days_to_expiry' => $lease->date_end ? round(now()->diffInDays($lease->date_end, false)) : null,
                        'lease_status' => $lease->lease_status,
                    ];
                });

            return response()->json(['data' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading urgent leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load urgent leases'], 500);
        }
    }

    /**
     * Get properties with units for bulk renewal dropdown
     */
    public function getPropertiesWithUnits(Request $request)
    {
        try {
            $query = Property::with(['units' => function($query) {
                $query->where('occupancy_status_id', 38) // Only occupied units
                      ->with(['leaseAgreements' => function($q) {
                          $q->where('current_status_id', 5) // Only active leases
                            ->orderBy('date_end', 'desc')
                            ->limit(1);
                      }]);
            }])
            ->select(['id', 'property_name', 'reference_number', 'client_id']);
            
            // Filter by client if provided
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }
            
            $properties = $query->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) {
                    // Filter units to only include those with active leases
                    $unitsWithActiveLeases = $property->units->filter(function ($unit) {
                        return $unit->leaseAgreements->isNotEmpty();
                    });
                    
                    return [
                        'id' => $property->id,
                        'property_name' => $property->property_name,
                        'reference_number' => $property->reference_number,
                        'client_id' => $property->client_id,
                        'units' => $unitsWithActiveLeases->map(function ($unit) {
                            $activeLease = $unit->leaseAgreements->first();
                            return [
                                'id' => $unit->id,
                                'unit_code' => $unit->unit_code,
                                'lease_id' => $activeLease->id,
                                'lease_ref' => $activeLease->ref_number,
                                'lease_end_date' => $activeLease->date_end,
                            ];
                        })->values() // Reset array keys after filtering
                    ];
                });

            return response()->json(['properties' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties with units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Renew leases in bulk (property-wide or unit selection)
     */
    public function bulkRenew(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'renewal_type' => 'required|in:property,units',
                'property_id' => 'required_if:renewal_type,property|exists:sys_pm_properties,id',
                'unit_ids' => 'required_if:renewal_type,units|array',
                'unit_ids.*' => 'exists:sys_pm_units,id',
                'years' => 'required|integer|min:1|max:10',
                'escalation_rate' => 'required|numeric|min:0|max:100',
                'expiring_within_days' => 'nullable|integer|min:0|max:365',
                'expired_within_days' => 'nullable|integer|min:0|max:365',
            ]);

            $renewedCount = 0;
            $leaseIds = [];
            
            // Calculate date range for expiration criteria
            $today = now();
            $expiringByDate = now()->addDays($validated['expiring_within_days'] ?? 90);
            $expiredSinceDate = now()->subDays($validated['expired_within_days'] ?? 0);

            if ($validated['renewal_type'] === 'property') {
                // Get all active leases for all units in the property that meet expiration criteria
                $leaseIds = LeaseAgreement::whereHas('unit', function($query) use ($validated) {
                    $query->where('property_id', $validated['property_id']);
                })
                ->where('current_status_id', 5)
                ->where(function($query) use ($expiringByDate, $expiredSinceDate) {
                    // Leases expiring within specified days OR already expired within specified days
                    $query->whereBetween('date_end', [$expiredSinceDate, $expiringByDate]);
                })
                ->pluck('id');
            } else {
                // Get active leases for selected units that meet expiration criteria
                $leaseIds = LeaseAgreement::whereIn('unit_id', $validated['unit_ids'])
                    ->where('current_status_id', 5)
                    ->where(function($query) use ($expiringByDate, $expiredSinceDate) {
                        $query->whereBetween('date_end', [$expiredSinceDate, $expiringByDate]);
                    })
                    ->pluck('id');
            }
            
            if ($leaseIds->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No leases found matching the expiration criteria (expiring within ' . 
                                ($validated['expiring_within_days'] ?? 90) . ' days or expired within ' . 
                                ($validated['expired_within_days'] ?? 0) . ' days)'
                ], 400);
            }

            foreach ($leaseIds as $leaseId) {
                $oldLease = LeaseAgreement::with(['invoiceableItems'])->findOrFail($leaseId);
                $this->renewSingleLeaseRecord($oldLease, $validated['years'], $validated['escalation_rate']);
                $renewedCount++;
            }

            DB::commit();

            Log::info('Leases renewed successfully', [
                'renewal_type' => $validated['renewal_type'],
                'renewed_count' => $renewedCount,
                'renewed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully renewed {$renewedCount} lease(s)",
                'renewed_count' => $renewedCount
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error renewing leases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to renew leases: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew a single lease from the "Expiring Leases (Urgent)" tab's Renew
     * action — same lease-creation logic as bulkRenew(), just for one lease
     * chosen directly rather than a batch matched by expiration criteria.
     */
    public function renewLease(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'years' => 'required|integer|min:1|max:10',
                'escalation_rate' => 'required|numeric|min:0|max:100',
            ]);

            $oldLease = LeaseAgreement::with(['invoiceableItems'])->findOrFail($id);
            $newLease = $this->renewSingleLeaseRecord($oldLease, $validated['years'], $validated['escalation_rate']);

            DB::commit();

            Log::info('Lease renewed successfully', [
                'old_lease_id' => $oldLease->id,
                'new_lease_id' => $newLease->id,
                'renewed_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lease renewed successfully',
                'new_ref_number' => $newLease->ref_number,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error renewing lease: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to renew lease: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Creates the renewed lease record (dates, terms, escalated invoiceable
     * items) and marks $oldLease as renewed. Shared by bulkRenew() and
     * renewLease() — assumes it's already running inside a DB transaction.
     */
    private function renewSingleLeaseRecord(LeaseAgreement $oldLease, int $years, float $escalationRate): LeaseAgreement
    {
        // Create new lease with updated dates and terms
        $startDate = new \DateTime($oldLease->date_end);
        $startDate->modify('+1 day'); // Start the day after old lease ends

        $endDate = clone $startDate;
        $endDate->modify('+' . $years . ' years');

        $newLease = LeaseAgreement::create([
            'date_time' => now(),
            'client_id' => $oldLease->client_id,
            'property_id' => $oldLease->property_id,
            'unit_id' => $oldLease->unit_id,
            'tenant_id' => $oldLease->tenant_id,
            'date_start' => $startDate->format('Y-m-d'),
            'date_end' => $endDate->format('Y-m-d'),
            'years' => $years,
            'sla_id' => $oldLease->sla_id,
            'zone_id' => $oldLease->zone_id,
            'current_status_id' => 1,
            'auto_renewal' => $oldLease->auto_renewal,
            'initial_water_meter_reading' => $oldLease->initial_water_meter_reading,
            'escalation_rate' => $escalationRate,
            'escalation_frequency' => $oldLease->escalation_frequency,
            'linked_lease_id' => $oldLease->id,
            'created_by_id' => Auth::id(),
            'ref_number' => null,
        ]);

        // Generate reference number
        $unit = Unit::find($oldLease->unit_id);
        $newLease->ref_number = sprintf('%s-L%05d', $unit->unit_code, $newLease->id);
        $newLease->save();

        // Copy invoiceable items with escalation applied
        foreach ($oldLease->invoiceableItems as $item) {
            $escalatedAmount = $item->amount * (1 + ($escalationRate / 100));

            LeaseAgreementInvoiceableItem::create([
                'parent_id' => $newLease->id,
                'item_id' => $item->item_id,
                'amount' => $escalatedAmount,
                'cycle_id' => $item->cycle_id,
                'commissionable' => $item->commissionable,
                'comm_per' => $item->comm_per,
                'vatable' => $item->vatable,
                'prepay_client' => $item->prepay_client,
            ]);
        }

        // Mark old lease as renewed (status 2 or whatever your status is)
        $oldLease->current_status_id = 2; // Renewed
        $oldLease->save();

        return $newLease;
    }

    /**
     * Show the form for creating a new lease agreement
     */
    public function create(Request $request)
    {
        try {
            $clientId = null;
            $client = null;

            // If client parameter is provided, decrypt it
            if ($request->has('client')) {
                $clientId = Crypt::decrypt($request->client);
                $client = Client::findOrFail($clientId);
            }

            
            return view('property.properties.lease.create', compact('client'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading lease create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Store a newly created lease agreement.
     *
     * Tenant is no longer picked via a separate search — by the time a lease
     * is created, a tenant has typically already been created and assigned to
     * a unit (interactively, via bulk upload, or via TenantsController::
     * assignUnit()). The Unit dropdown on the create form now shows that
     * assigned tenant directly and submits tenant_id in a hidden field, so
     * this validation rule is unchanged in shape, just no longer filled in by
     * a manual search widget. sla_ref is similarly gone — resolved below from
     * the property's own SLA via resolveSlaId() (TenantRecordHelperTrait),
     * the same helper the interactive tenant form already uses, so a lease
     * and a tenant created for the same property can't disagree on SLA.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'unit_id' => 'required|exists:sys_pm_units,id',
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'linked_lease' => 'nullable|exists:sys_pm_lease_agreements,id',
                'date_start' => 'required|date',
                'years' => 'required|integer|min:1|max:10',
                'auto_renewal' => 'required|in:Yes,No',
                'escalation_rate' => 'required|numeric|min:0|max:100',
                'escalation_frequency' => 'required|in:Annually,Semi-Annually,Quarterly,Monthly',
                'service_charges_amount' => 'nullable|numeric|min:0',
                'documents.*' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                // No longer required|min:1 — Invoiceable Items is now driven by the
                // property's SLA billable items (see lease/create.blade.php), and a
                // property whose SLA only bills rent (handled automatically,
                // separately, from the unit's rent_amount) legitimately has none.
                'invoicable_items' => 'nullable|array',
                'invoicable_items.*.item' => 'required|exists:sys_set_invoiceable_items,id',
                'invoicable_items.*.amount' => 'required|numeric|min:0',
                'invoicable_items.*.cycle' => 'required|exists:sys_set_invoiceable_items_cycles,id',
                'invoicable_items.*.prepay_client' => 'nullable|in:Yes',
            ]);

            // Confirm the property exists up front (createLeaseAgreementRecord()
            // re-fetches it internally for zone_id, but failing fast here keeps
            // the same "property not found" behavior this method always had).
            Property::findOrFail($validated['property_id']);

            // A lease always needs an SLA — same guarantee the old required "SLA Ref"
            // dropdown gave, just enforced here now that it's derived, not picked.
            $slaId = $this->resolveSlaId($validated['property_id']);
            if (!$slaId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'property_id' => ['This property has no service agreement (SLA) configured — a lease cannot be created until one exists.'],
                ]);
            }

            // Get unit's initial water meter reading
            $unit = Unit::findOrFail($validated['unit_id']);

            // Handle file uploads
            // Documents will be stored in separate table
            if ($request->hasFile('documents')) {
                // We'll save documents after creating the lease agreement
                $hasDocuments = true;
            } else {
                $hasDocuments = false;
            }

            // Create the Lease Agreement (+ ref number, invoiceable items, unit
            // occupancy flip) — shared with the returning-tenant reassignment
            // flow via LeaseCreationTrait, so both build a lease identically.
            $leaseAgreement = $this->createLeaseAgreementRecord([
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_id'],
                'tenant_id' => $validated['tenant_id'],
                'sla_id' => $slaId,
                'date_start' => $validated['date_start'],
                'years' => $validated['years'],
                'auto_renewal' => $validated['auto_renewal'],
                'escalation_rate' => $validated['escalation_rate'],
                'escalation_frequency' => $validated['escalation_frequency'],
                'service_charges_amount' => $validated['service_charges_amount'] ?? null,
                'linked_lease_id' => $validated['linked_lease'] ?? null,
            ], $request->has('invoicable_items') && is_array($request->invoicable_items) ? $request->invoicable_items : []);

            // Store documents in separate table
            if ($hasDocuments && $request->hasFile('documents')) {
                $this->storeLeaseDocuments($leaseAgreement->id, $request->file('documents'));
            }

            DB::commit();

            Log::info('Lease Agreement created successfully', [
                'lease_id' => $leaseAgreement->id,
                'ref_number' => $leaseAgreement->ref_number,
                'created_by' => Auth::id()
            ]);

            // Send contract to tenant for digital signing (after commit so all data is persisted)
            try {
                (new LeaseContractService())->sendToTenant($leaseAgreement);
            } catch (\Exception $contractEx) {
                // Non-fatal — lease is created, but log the failure
                Log::error('Failed to send lease contract to tenant: ' . $contractEx->getMessage(), [
                    'lease_id' => $leaseAgreement->id,
                ]);
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lease Agreement created successfully with reference: ' . $leaseAgreement->ref_number,
                    'redirect_url' => route('property.properties.show', Crypt::encrypt($validated['property_id'])),
                ]);
            }

            return redirect()
                ->route('property.properties.show', Crypt::encrypt($validated['property_id']))
                ->with('success', 'Lease Agreement created successfully with reference: ' . $leaseAgreement->ref_number);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating lease agreement: ' . $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to create lease agreement: ' . $e->getMessage()], 500);
            }
            return redirect()
                ->back()
                ->with('error', 'Failed to create lease agreement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing an existing lease agreement's terms.
     */
    /**
     * Return the full set of details for a single lease (AJAX) — powers the
     * "view details" modal on both the tenant's Leases tab and the
     * property's Leases tab, so both stay in sync with one source of truth.
     */
    public function showDetails($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $lease = LeaseAgreement::with([
                'tenant:id,names,mobile_number,email_address',
                'property:id,property_name,reference_number',
                'unit:id,unit_code,unit_name,rent_amount,vat_able',
                'client:id,names',
            ])->findOrFail($id);

            return response()->json([
                'ref_number' => $lease->ref_number ?? '-',
                'lease_status' => $lease->lease_status ?? '-',
                'date_start' => $lease->date_start ? $lease->date_start->format('Y-m-d') : '-',
                'date_end' => $lease->date_end ? $lease->date_end->format('Y-m-d') : '-',
                'years' => $lease->years ?? '-',
                'auto_renewal' => $lease->auto_renewal ?? 'No',
                'escalation_rate' => $lease->escalation_rate,
                'escalation_frequency' => $lease->escalation_frequency ?? '-',
                'service_charges_amount' => $lease->service_charges_amount,
                'initial_water_meter_reading' => $lease->initial_water_meter_reading,
                'tenant_name' => $lease->tenant->names ?? '-',
                'tenant_mobile' => $lease->tenant->mobile_number ?? '-',
                'tenant_email' => $lease->tenant->email_address ?? '-',
                'landlord_name' => $lease->client->names ?? '-',
                'property_name' => $lease->property->property_name ?? '-',
                'property_reference' => $lease->property->reference_number ?? '-',
                'unit_code' => $lease->unit->unit_code ?? '-',
                'unit_name' => $lease->unit->unit_name ?? '-',
                'rent_amount' => $lease->unit->rent_amount ?? null,
                'vat_able' => $lease->unit->vat_able ?? 'No',
                'contract_status' => $lease->contract_signed_at ? 'signed' : ($lease->contract_sent_at ? 'sent' : 'not_sent'),
                'documents_count' => $lease->documents()->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading lease details: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load lease details'], 500);
        }
    }

    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $lease = LeaseAgreement::with([
                'tenant:id,names',
                'property:id,property_name',
                'unit:id,unit_code,unit_number',
                'client:id,names',
            ])->findOrFail($id);

            // This lease's current invoiceable items — shown editable-in-place on the
            // edit page, seeding the same row UI store()/getSLAForProperty()'s
            // SLA-driven rows use (see create.blade.php), rather than the free-choice
            // picker these older leases were actually created with. Shaped here to
            // exactly what the view's JS needs ({item_id, name, amount, cycle_id,
            // prepay_client}) so the Blade template can @json() it directly — a
            // ->map(function...) closure inline inside @json() confuses Blade's
            // directive-argument parser (nested [] inside a multi-line closure), so
            // that transformation belongs here, not in the view.
            $existingItems = LeaseAgreementInvoiceableItem::where('parent_id', $lease->id)
                ->with('item:id,item_name')
                ->get(['id', 'item_id', 'amount', 'cycle_id', 'prepay_client']);

            $invoiceableItems = $existingItems->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'name' => $row->item->item_name ?? 'Unknown item',
                    'amount' => $row->amount,
                    'cycle_id' => $row->cycle_id,
                    'prepay_client' => $row->prepay_client === 'Yes',
                ];
            })->values();

            return view('property.properties.lease.edit', compact('lease', 'invoiceableItems'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading lease edit form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load lease for editing');
        }
    }

    /**
     * Update an existing lease agreement's terms. Structural fields (tenant, property,
     * unit, client, SLA) are intentionally not editable here — reassigning those goes
     * through the Tenant Movement flow instead, not a plain edit.
     *
     * Invoiceable items ARE editable here (unlike the fields above) — either in place
     * (amount/cycle/prepay/remove) or wholesale via the edit page's "Switch to
     * SLA-driven Billable Items" action. Whatever's submitted is treated as the
     * complete, authoritative set: existing rows are deleted and replaced with it,
     * rather than diffed — simplest correct behaviour for "what's on screen when you
     * hit Save is the truth", and reuses storeInvoiceableItems() so this can't drift
     * from how store() writes the exact same rows.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $lease = LeaseAgreement::findOrFail($id);

            $validated = $request->validate([
                'date_start' => 'required|date',
                'years' => 'required|integer|min:1|max:10',
                'auto_renewal' => 'required|in:Yes,No',
                'escalation_rate' => 'required|numeric|min:0|max:100',
                'escalation_frequency' => 'required|in:Annually,Semi-Annually,Quarterly,Monthly',
                // Matches store()'s already-relaxed rule — a lease can legitimately end
                // up with zero items (e.g. every row unchecked, or switched to an SLA
                // with nothing beyond rent enabled).
                'invoicable_items' => 'nullable|array',
                'invoicable_items.*.item' => 'required|exists:sys_set_invoiceable_items,id',
                'invoicable_items.*.amount' => 'required|numeric|min:0',
                'invoicable_items.*.cycle' => 'required|exists:sys_set_invoiceable_items_cycles,id',
                'invoicable_items.*.prepay_client' => 'nullable|in:Yes',
            ]);

            $startDate = new \DateTime($validated['date_start']);
            $endDate = clone $startDate;
            $endDate->modify('+' . $validated['years'] . ' years');

            $lease->update([
                'date_start' => $validated['date_start'],
                'date_end' => $endDate->format('Y-m-d'),
                'years' => $validated['years'],
                'auto_renewal' => $validated['auto_renewal'],
                'escalation_rate' => $validated['escalation_rate'],
                'escalation_frequency' => $validated['escalation_frequency'],
            ]);

            LeaseAgreementInvoiceableItem::where('parent_id', $lease->id)->delete();
            if ($request->has('invoicable_items') && is_array($request->invoicable_items)) {
                $this->storeInvoiceableItems($lease->id, $request->invoicable_items);
            }

            DB::commit();

            Log::info('Lease Agreement updated', ['lease_id' => $lease->id, 'updated_by' => Auth::id()]);

            return redirect()
                ->route('property.leases.index')
                ->with('success', 'Lease Agreement updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating lease agreement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update lease agreement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Cancel a single lease agreement — ends it before its natural expiry and frees
     * the unit. Reuses the current_status_id/lease_status convention already used by
     * TenantExitController's lease termination, with a distinct lease_status string
     * ("Cancelled") so it stays distinguishable from an exit-driven termination.
     */
    public function cancel(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $lease = LeaseAgreement::findOrFail($id);

            if ($lease->lease_status !== 'Active') {
                DB::rollBack();
                return response()->json(['success' => false, 'error' => 'Only an active lease can be cancelled.'], 422);
            }

            $lease->current_status_id = 2; // matches TenantExitController's "lease no longer active" convention
            $lease->lease_status = 'Cancelled';
            $lease->save();

            if ($lease->unit_id) {
                Unit::where('id', $lease->unit_id)->update(['occupancy_status_id' => 31]); // Vacant
            }

            DB::commit();

            Log::info('Lease Agreement cancelled', ['lease_id' => $lease->id, 'cancelled_by' => Auth::id()]);

            return response()->json(['success' => true, 'message' => 'Lease cancelled successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error cancelling lease agreement: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to cancel lease'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Lease Setup — cleaning up and activating the 73k+ migrated leases.
    //
    // Every migrated lease landed at current_status_id = 1 (Pending) with no
    // sla_id, and most with no date_end — none have ever gone through the
    // maker-checker approval step (5, Approved) a normally-created lease
    // would. Worse: for a large share of them, LeaseAgreement.tenant_id
    // disagrees with whoever Client.unit_id currently says occupies that
    // unit — could mean the lease is stale (tenant moved out) or the unit
    // record is stale; not safe to resolve automatically. This section is a
    // dedicated backlog-clearing workspace: bulk-fill what's mechanical (SLA,
    // computed end dates), surface tenant conflicts for manual review, then
    // gate activation (Pending -> Approved) on the lease actually being
    // complete — same requirement LeasesController::store() already enforces
    // for a lease created normally.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Applies (or excludes) the "has an unresolved tenant mismatch" condition
     * to a query already aliasing sys_pm_lease_agreements as `l`. A mismatch
     * is a differing type_id=2 Client whose unit_id points at the same unit
     * as the lease, that hasn't been reviewed yet (tenant_verified_at null).
     * Shared by getSetupStats(), getSetupLeases()'s 'ready'/'tenant_mismatch'
     * filters, and bulkActivate() so the definition can't drift between them.
     */
    private function scopeUnresolvedMismatch($query, bool $wantMismatched)
    {
        $mismatchExists = function ($q) {
            $q->select(DB::raw(1))
                ->from('sys_pm_clients as ut')
                ->whereColumn('ut.unit_id', 'l.unit_id')
                ->where('ut.type_id', 2)
                ->whereColumn('ut.id', '!=', 'l.tenant_id');
        };

        if ($wantMismatched) {
            return $query->whereExists($mismatchExists)->whereNull('l.tenant_verified_at');
        }

        return $query->where(function ($q) use ($mismatchExists) {
            $q->whereNotExists($mismatchExists)->orWhereNotNull('l.tenant_verified_at');
        });
    }

    /**
     * Render the Lease Setup page.
     */
    public function setupIndex()
    {
        try {
            return view('property.properties.lease.setup');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading lease setup page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load lease setup page');
        }
    }

    /**
     * Counts for the Lease Setup page's stat cards. Scoped to Pending leases
     * only — Terminated ones are already resolved/historical and aren't part
     * of this "finish setting up, then activate" workflow.
     */
    public function getSetupStats()
    {
        try {
            $base = fn () => DB::table('sys_pm_lease_agreements as l')->where('l.current_status_id', 1);

            $missingSla = $base()->whereNull('l.sla_id')->count();
            $missingEndDate = $base()->whereNull('l.date_end')->count();
            $tenantMismatch = $this->scopeUnresolvedMismatch($base(), true)->count();
            $ready = $this->scopeUnresolvedMismatch(
                $base()->whereNotNull('l.sla_id')->whereNotNull('l.date_end'),
                false
            )->count();

            return response()->json([
                'missing_sla' => $missingSla,
                'missing_end_date' => $missingEndDate,
                'tenant_mismatch' => $tenantMismatch,
                'ready' => $ready,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading lease setup stats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Listing for the Lease Setup page's table (AJAX). `filter`: missing_sla |
     * missing_end_date | tenant_mismatch | ready | all.
     */
    public function getSetupLeases(Request $request)
    {
        try {
            $filter = $request->input('filter', 'all');

            $query = DB::table('sys_pm_lease_agreements as l')
                ->where('l.current_status_id', 1)
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'l.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'l.unit_id')
                ->leftJoin('sys_pm_clients as ut', function ($join) {
                    $join->on('ut.unit_id', '=', 'l.unit_id')->where('ut.type_id', 2);
                })
                ->select([
                    'l.id', 'l.ref_number', 'l.tenant_id', 'l.property_id', 'l.unit_id',
                    'l.sla_id', 'l.date_start', 'l.date_end', 'l.years', 'l.tenant_verified_at',
                    'p.property_name',
                    'u.unit_code',
                    DB::raw('ut.id as unit_tenant_id'),
                ]);

            switch ($filter) {
                case 'missing_sla':
                    $query->whereNull('l.sla_id');
                    break;
                case 'missing_end_date':
                    $query->whereNull('l.date_end');
                    break;
                case 'tenant_mismatch':
                    $this->scopeUnresolvedMismatch($query, true);
                    break;
                case 'ready':
                    $query->whereNotNull('l.sla_id')->whereNotNull('l.date_end');
                    $this->scopeUnresolvedMismatch($query, false);
                    break;
                // 'all': no extra filter
            }

            $leases = $query->orderBy('l.date_start', 'desc')->limit(500)->get();

            // withAll(): plain Client lookups get silently filtered by
            // user_clients_properties, hiding real tenants outside the current
            // user's assigned properties — same fix applied everywhere else
            // this session (e.g. UnitsController::getAvailableUnits()).
            $tenantIds = $leases->pluck('tenant_id')->merge($leases->pluck('unit_tenant_id'))->filter()->unique();
            // pluck() bypasses the Client::getNamesAttribute() accessor, so read straight
            // from cb_tenant_records; fall back to sys_pm_clients only for ids with no
            // tenant record yet (shouldn't happen for real tenants, but keeps this safe).
            // union() (not merge()) — merge() uses array_merge(), which re-indexes
            // integer keys like these tenant ids instead of overwriting by key.
            $tenantNames = TenantRecord::whereIn('id', $tenantIds)->pluck('names', 'id')
                ->union(Client::withAll()->whereIn('id', $tenantIds)->pluck('names', 'id'));

            $data = $leases->map(function ($l) use ($tenantNames) {
                $hasMismatch = $l->unit_tenant_id && $l->unit_tenant_id != $l->tenant_id;
                $suggestedEndDate = null;
                if (!$l->date_end && $l->date_start && (float) $l->years > 0) {
                    $suggestedEndDate = \Carbon\Carbon::parse($l->date_start)->addYears((int) $l->years)->toDateString();
                }

                return [
                    'id' => $l->id,
                    'ref_number' => $l->ref_number ?? '-',
                    'property_name' => $l->property_name ?? '-',
                    'unit_code' => $l->unit_code ?? '-',
                    'tenant_id' => $l->tenant_id,
                    'tenant_name' => $tenantNames->get($l->tenant_id, 'N/A'),
                    'unit_tenant_id' => $l->unit_tenant_id,
                    'unit_tenant_name' => $l->unit_tenant_id ? $tenantNames->get($l->unit_tenant_id, 'N/A') : null,
                    'has_mismatch' => (bool) $hasMismatch,
                    'tenant_verified' => (bool) $l->tenant_verified_at,
                    'sla_id' => $l->sla_id,
                    'date_start' => $l->date_start ? \Carbon\Carbon::parse($l->date_start)->format('d M Y') : '-',
                    'date_end' => $l->date_end ? \Carbon\Carbon::parse($l->date_end)->format('d M Y') : null,
                    'suggested_end_date' => $suggestedEndDate,
                    'years' => $l->years,
                ];
            });

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading setup leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * Auto-fill sla_id for every Pending lease still missing one, via the
     * same resolveSlaId() every other creation path already uses — one
     * button, acts on the whole matching set server-side since it's
     * mechanical/deterministic (every property already has an SLA).
     * Optionally scoped to specific lease_ids.
     */
    public function bulkFillSla(Request $request)
    {
        try {
            $query = DB::table('sys_pm_lease_agreements as l')
                ->where('l.current_status_id', 1)
                ->whereNull('l.sla_id');

            $leaseIds = $request->input('lease_ids');
            if (is_array($leaseIds) && count($leaseIds)) {
                $query->whereIn('l.id', $leaseIds);
            }

            $leases = $query->select('l.id', 'l.property_id')->get();
            $updated = 0;
            foreach ($leases as $lease) {
                $slaId = $this->resolveSlaId($lease->property_id);
                if ($slaId) {
                    DB::table('sys_pm_lease_agreements')->where('id', $lease->id)->update(['sla_id' => $slaId]);
                    $updated++;
                }
            }

            return response()->json(['success' => true, 'updated' => $updated, 'skipped' => $leases->count() - $updated]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk-filling lease SLA: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to auto-fill SLA'], 500);
        }
    }

    /**
     * Compute date_end = date_start + years for every Pending lease still
     * missing one, where both date_start and a valid years figure exist.
     * Leases that don't qualify are left for manual entry — reported back so
     * the UI can say how many still need a date picked by hand.
     */
    public function bulkComputeEndDate(Request $request)
    {
        try {
            $query = DB::table('sys_pm_lease_agreements as l')
                ->where('l.current_status_id', 1)
                ->whereNull('l.date_end')
                ->whereNotNull('l.date_start');

            $leaseIds = $request->input('lease_ids');
            if (is_array($leaseIds) && count($leaseIds)) {
                $query->whereIn('l.id', $leaseIds);
            }

            $leases = $query->select('l.id', 'l.date_start', 'l.years')->get();
            $updated = 0;
            $skipped = 0;
            foreach ($leases as $lease) {
                $years = (float) $lease->years;
                if ($years <= 0) {
                    $skipped++;
                    continue;
                }
                $endDate = \Carbon\Carbon::parse($lease->date_start)->addYears((int) $years)->toDateString();
                DB::table('sys_pm_lease_agreements')->where('id', $lease->id)->update(['date_end' => $endDate]);
                $updated++;
            }

            return response()->json(['success' => true, 'updated' => $updated, 'skipped' => $skipped]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk-computing lease end dates: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to compute end dates'], 500);
        }
    }

    /**
     * General-purpose "update lease details" endpoint for the Setup page.
     * Backs the three tenant-mismatch resolution actions (Keep — pass the
     * lease's own current tenant_id back; Use Unit's Tenant — pass the
     * unit-assigned tenant's id; Search a different tenant — pass whichever
     * id was picked) as well as manual SLA/end-date edits. Any tenant_id
     * submitted here stamps tenant_verified_at, since reaching this endpoint
     * at all means the team looked at and confirmed it.
     */
    public function updateSetupLease(Request $request, $id)
    {
        try {
            $lease = LeaseAgreement::findOrFail($id);

            $validated = $request->validate([
                'tenant_id' => 'nullable|integer|exists:sys_pm_clients,id',
                'sla_id' => 'nullable|integer|exists:sys_pm_service_agreements,id',
                'date_end' => 'nullable|date',
            ]);

            $update = [];
            if (!empty($validated['tenant_id'])) {
                $update['tenant_id'] = $validated['tenant_id'];
                $update['tenant_verified_at'] = now();
            }
            if (!empty($validated['sla_id'])) {
                $update['sla_id'] = $validated['sla_id'];
            }
            if (!empty($validated['date_end'])) {
                $update['date_end'] = $validated['date_end'];
            }

            if (empty($update)) {
                return response()->json(['success' => false, 'message' => 'Nothing to update.'], 422);
            }

            $lease->update($update);

            return response()->json(['success' => true, 'message' => 'Lease updated successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating setup lease: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update lease'], 500);
        }
    }

    /**
     * Activate a single Pending lease (-> Approved, current_status_id 5) —
     * gated on SLA and end date already being set, the same requirement
     * store() enforces for a lease created normally. No occupancy-flip
     * needed: Unit::activeLeaseAgreement() already treats current_status_id
     * 1 and 5 as equally occupying the unit.
     */
    public function activateSetupLease($id)
    {
        try {
            $lease = LeaseAgreement::findOrFail($id);

            if (!$lease->sla_id || !$lease->date_end) {
                return response()->json(['success' => false, 'message' => 'This lease needs an SLA and an end date before it can be activated.'], 422);
            }

            $lease->update(['current_status_id' => 5]);

            return response()->json(['success' => true, 'message' => 'Lease activated.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error activating lease: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to activate lease'], 500);
        }
    }

    /**
     * Bulk-activate every lease that's actually ready — SLA set, end date
     * set, no unresolved tenant mismatch — safe as a single "Activate all"
     * button specifically because the "ready" definition already excludes
     * anything that still needs a human decision.
     */
    public function bulkActivate(Request $request)
    {
        try {
            $query = DB::table('sys_pm_lease_agreements as l')
                ->where('l.current_status_id', 1)
                ->whereNotNull('l.sla_id')
                ->whereNotNull('l.date_end');
            $this->scopeUnresolvedMismatch($query, false);

            $leaseIds = $request->input('lease_ids');
            if (is_array($leaseIds) && count($leaseIds)) {
                $query->whereIn('l.id', $leaseIds);
            }

            $count = $query->update(['current_status_id' => 5]);

            return response()->json(['success' => true, 'updated' => $count]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk-activating leases: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to activate leases'], 500);
        }
    }

    // storeInvoiceableItems() and generateLeaseReferenceNumber() now live on
    // LeaseCreationTrait (shared with the returning-tenant reassignment flow)
    // — see that trait for both.

    /**
     * Store documents for the lease agreement
     * 
     * @param int $leaseAgreementId
     * @param array $documents
     */
    protected function storeLeaseDocuments($leaseAgreementId, $documents)
    {
        foreach ($documents as $document) {
            if ($document->isValid()) {
                // Store file in public disk
                $path = $document->store('lease_documents', 'public');
                
                // Create LeaseAgreementDocument record
                \App\Models\LeaseAgreementDocument::create([
                    'parent_id' => $leaseAgreementId,
                    'documents' => $path,
                    'params' => json_encode([
                        'original_name' => $document->getClientOriginalName(),
                        'mime_type' => $document->getMimeType(),
                        'size' => $document->getSize(),
                        'uploaded_at' => now()->toDateTimeString(),
                    ]),
                ]);
            }
        }
        
        Log::info('Lease documents stored successfully', [
            'lease_agreement_id' => $leaseAgreementId,
            'documents_count' => count($documents)
        ]);
    }

    /**
     * Get leases for a specific unit (AJAX)
     * Returns all leases associated with the unit's tenant
     */
    public function getLeasesByUnit($unitId)
    {
        try {
            // First, find the tenant for this unit
            $tenant = Client::where('unit_id', $unitId)
                ->first();

            if (!$tenant) {
                return response()->json(['leases' => []]);
            }

            // Then get all leases for this tenant
            $leases = LeaseAgreement::where('tenant_id', $tenant->id)
                ->with(['property', 'unit'])
                ->orderBy('date_start', 'desc')
                ->get()
                ->map(function ($lease) {
                    return [
                        'id' => $lease->id,
                        'reference_number' => $lease->ref_number,
                        'tenant_name' => $lease->tenant->names ?? 'N/A',
                        'date_start' => $lease->date_start,
                        'date_end' => $lease->date_end,
                        'current_status_id' => $lease->current_status_id,
                        'lease_status' => $lease->lease_status,
                    ];
                });

            return response()->json(['leases' => $leases]);
            
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading leases for unit: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * (Admin) Manually send / resend contract to tenant.
     */
    public function sendContract($id)
    {
        try {
            Log::info('Admin requested to send lease contract', [
                'lease_ref_number' => $id,
                'admin_id' => Auth::id(),
            ]);
            $lease = LeaseAgreement::with(['tenant', 'property', 'unit', 'invoiceableItems', 'contractTemplate'])->where('ref_number', $id)->first();
            Log::info('Attempting to send lease contract', [
                'lease_id' => $lease->id ?? null,
                'ref_number' => $id,
                'email' => $lease->tenant->email_address ?? null,
            ]);

            (new LeaseContractService())->sendToTenant($lease);

            return response()->json([
                'success' => true,
                'message' => 'Contract sent to ' . ($lease->tenant->names ?? 'tenant') . ' successfully.',
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error sending lease contract: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send contract: ' . $e->getMessage()], 500);
        }
    }

    /**
     * (Public) Show the digital signing page for a tenant.
     */
    public function showSigningPage(string $token)
    {
        $hashedToken = hash('sha256', $token);

        $lease = LeaseAgreement::with(['tenant', 'property', 'unit'])
            ->where('contract_sign_token', $hashedToken)
            ->first();

        if (!$lease) {
            return view('lease.sign-error', [
                'title'   => 'Invalid Signing Link',
                'message' => 'This link is not valid or has already been used. Please contact your property manager.',
            ]);
        }

        if ($lease->contract_signed_at) {
            return view('lease.sign-error', [
                'title'   => 'Already Signed',
                'message' => 'This lease agreement has already been signed on ' . $lease->contract_signed_at->format('d M Y') . '.',
            ]);
        }

        return view('lease.sign', compact('lease', 'token'));
    }

    /**
     * (Public) Handle tenant signature submission.
     */
    public function submitSignature(\Illuminate\Http\Request $request, string $token)
    {
        $request->validate([
            'signer_name' => 'required|string|max:200',
            'consent'     => 'accepted',
        ]);

        $hashedToken = hash('sha256', $token);

        $lease = LeaseAgreement::with(['tenant', 'property', 'unit', 'invoiceableItems', 'contractTemplate'])
            ->where('contract_sign_token', $hashedToken)
            ->first();

        if (!$lease) {
            return view('lease.sign-error', [
                'title'   => 'Invalid Signing Link',
                'message' => 'This link is not valid or has already been used.',
            ]);
        }

        if ($lease->contract_signed_at) {
            return view('lease.sign-error', [
                'title'   => 'Already Signed',
                'message' => 'This lease agreement has already been signed.',
            ]);
        }

        try {
            $signerName = strip_tags($request->signer_name);

            // Generate signed PDF
            $signedPath = (new LeaseContractService())->generateSignedPdf($lease, $signerName);

            // Persist signing data and clear token
            $lease->signer_name       = $signerName;
            $lease->signed_pdf_path   = $signedPath;
            $lease->contract_signed_at = now();
            $lease->contract_sign_token = null; // invalidate link
            $lease->save();

            Log::info('Lease contract signed by tenant', [
                'lease_id'    => $lease->id,
                'ref_number'  => $lease->ref_number,
                'signer_name' => $signerName,
            ]);

            return view('lease.sign-success', [
                'signerName' => $signerName,
                'refNumber'  => $lease->ref_number,
            ]);

        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error processing lease signature: ' . $e->getMessage());
            return back()->with('error', 'Failed to process signature. Please try again or contact your property manager.');
        }
    }
}
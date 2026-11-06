<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Deposit;
use App\Models\Property;
use App\Models\TenantHistory;
use App\Models\Unit;
use App\Traits\TenantRecordHelperTrait;
use App\Traits\LeaseCreationTrait;
use Illuminate\Http\Request;
use App\Models\SystemCountry;
use App\Models\MoveInInspection;
use App\Models\TenantPaymentAllocated;
use App\Models\ClientDocument;
use App\Models\LeaseAgreement;
use App\Models\Gender;
use App\Models\Salutation;
use App\Models\TenantRecord;
use App\Models\TenantBankDetail;
use App\Models\TenantOtherContact;
use App\Models\TenantNextOfKin;
use App\Services\ErrorDispatcher;
use App\Services\InvoiceJournalService;
use App\Services\TenantBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class TenantsController extends Controller
{
    use TenantRecordHelperTrait;
    use LeaseCreationTrait;
    use \App\Traits\InvoiceCreationTrait;

    /**
     * Inject the service that posts invoices to the GL.
     */
    public function __construct(InvoiceJournalService $invoiceJournals) {
        $this->invoiceJournals = $invoiceJournals;
    }

    public function getTenantBalance(Request $request, $tenantId = null)
    {
        $id = $tenantId ?? $request->get('tenant_id');
        if (!$id) {
            return response()->json(['error' => 'tenant_id is required'], 422);
        }
        $balance = TenantBalanceService::getBalance((int) $id);
        return response()->json(['balance' => $balance]);
    }


    /**
     * Return payments for a tenant (AJAX) for DataTables
     */
    public function payments($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);

            $payments = TenantPaymentAllocated::with(['tenant', 'property', 'unit'])
                ->where('tenant_id', $id)
                ->orderBy('payment_date', 'desc')
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'payment_date' => $p->payment_date ? $p->payment_date->format('d-m-Y') : '-',
                        'reference' => $p->reference_number ?? '-',
                        'narration' => $p->narration ?? '-',
                        'tenant' => $p->tenant->names ?? '-',
                        'property' => $p->property->property_name ?? '-',
                        'unit' => $p->unit->unit_name ?? '-',
                        'amount' => $p->amount ? number_format($p->amount, 2) : '0.00'
                    ];
                });

            return response()->json(['data' => $payments]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant payments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load payments'], 500);
        }
    }

    /**
     * Get tenant for a specific unit by unit ID only (AJAX)
     */
    public function getTenantByUnit($unitId)
    {
        try {
            // Find the tenant directly from Client model where unit_id matches
            $tenant = Client::where('unit_id', $unitId)
                ->first();

            if ($tenant) {
                return response()->json([
                    'tenant' => [
                        'id' => $tenant->id,
                        'reference_number' => $tenant->reference_number,
                        'names' => $tenant->names,
                        'mobile_number' => $tenant->mobile_number,
                        'email_address' => $tenant->email_address,
                    ]
                ]);
            }

            return response()->json(['tenant' => null]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant for unit: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tenant'], 500);
        }
    }

    /**
     * Get tenant for a specific unit (AJAX)
     */
    public function getTenantForUnit($propertyId, $unitId)
    {
        try {
            // Find the active lease agreement for this unit
            $lease = LeaseAgreement::where('property_id', $propertyId)
                ->where('unit_id', $unitId)
                ->where('current_status_id', 5) // Active lease
                ->whereDate('date_start', '<=', now())
                ->where(fn ($q) => $q->where('open_ended', true)->orWhereDate('date_end', '>=', now()))
                ->with('tenant:id,names,reference_number,mobile_number,email_address')
                ->first();

            if ($lease && $lease->tenant) {
                return response()->json([
                    'tenant' => [
                        'id' => $lease->tenant->id,
                        'full_name' => $lease->tenant->names,
                        'name' => $lease->tenant->names,
                        'reference_number' => $lease->tenant->reference_number,
                        'mobile' => $lease->tenant->mobile_number,
                        'email' => $lease->tenant->email_address
                    ]
                ]);
            }


            // Alternative: Check if tenant is directly linked to unit
            $tenant = Client::where('unit_id', $unitId)
                ->where('property_id', $propertyId)
                ->first(['id', 'names', 'reference_number', 'mobile_number', 'email_address']);


            if ($tenant) {
                return response()->json([
                    'tenant' => [
                        'id' => $tenant->id,
                        'full_name' => $tenant->names,
                        'name' => $tenant->names,
                        'reference_number' => $tenant->reference_number,
                        'mobile' => $tenant->mobile_number,
                        'email' => $tenant->email_address
                    ]
                ]);
            }

            return response()->json(['tenant' => null]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant for unit: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tenant'], 500);
        }
    }

    /**
     * Return leases for a tenant (AJAX) for DataTables
     */
    public function leases($encryptedId, Request $request)
    {
        try {
            $id = Crypt::decrypt($encryptedId);

            $query = LeaseAgreement::with(['property', 'unit'])
                ->select(['id', 'ref_number', 'tenant_id', 'property_id', 'unit_id', 'date_start', 'date_end', 'contract_sent_at', 'contract_signed_at', 'signed_pdf_path', 'service_charges_amount'])
                ->where('tenant_id', $id);

            // optional property filter
            if ($request->has('property_id') && $request->property_id) {
                $query->where('property_id', $request->property_id);
            }

            $leases = $query->orderBy('date_start', 'desc')->get()->map(function ($lease) {
                return [
                    'id'             => $lease->id,
                    'encrypted_id'   => Crypt::encrypt($lease->id),
                    'ref_number'     => $lease->ref_number ?? '-',
                    'property'       => $lease->property->property_name ?? '-',
                    'unit'           => $lease->unit->unit_name ?? '-',
                    // return ISO date so client can format to dd/mm/yy
                    'date_start'     => $lease->date_start ? $lease->date_start->toDateString() : null,
                    'date_end'       => $lease->date_end ? $lease->date_end->toDateString() : null,
                    'contract_status' => $lease->contract_signed_at ? 'signed' : ($lease->contract_sent_at ? 'sent' : 'not_sent'),
                    'has_signed_pdf' => !empty($lease->signed_pdf_path),
                    'service_charges_amount' => $lease->service_charges_amount,
                ];
            });

            return response()->json(['data' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * Show the form for creating a new tenant
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

            $countries = SystemCountry::orderBy('country_name')->get();

            return view('property.properties.tenants.create', compact('client', 'countries'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Check whether a tenant already exists with the given National ID or
     * Passport Number (Individual) or Registration Number (Company). Used for
     * the live on-blur "duplicate — continue anyway?" confirmation on the create/edit
     * forms — this is advisory only, not enforced server-side beyond
     * presence (see validateIndividualOrCompanyFields()).
     */
    public function checkIdentifierExists(Request $request)
    {
        $tenantType = (int) $request->input('tenant_type');
        $value = trim((string) $request->input('value', ''));

        if ($value === '') {
            return response()->json(['exists' => false]);
        }

        // When checking from the edit form, exclude the record being edited so it
        // doesn't get flagged as a duplicate of itself.
        $excludeId = null;
        if ($request->filled('exclude_id')) {
            try {
                $excludeId = Crypt::decrypt($request->input('exclude_id'));
            } catch (\Exception $e) {
                $excludeId = null;
            }
        }

        if ($tenantType === 3) {
            // Company registration number has no cb_tenant_records equivalent —
            // sys_pm_clients is still the only place it lives.
            $match = Client::tenants()
                ->where('company_registration_number', $value)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->select(['id', 'names', 'reference_number'])
                ->first();
            $referenceNumber = $match->reference_number ?? null;
        } else {
            // Individuals are identified by National ID or Passport Number (either is enough).
            $column = $request->input('field') === 'passport_number' ? 'passport_number' : 'national_id';
            $match = TenantRecord::where($column, $value)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->with('legacyClient:id,reference_number')
                ->select(['id', 'names', $column])
                ->first();
            $referenceNumber = $match->legacyClient->reference_number ?? null;
        }

        return response()->json([
            'exists' => (bool) $match,
            'name' => $match->names ?? null,
            'reference_number' => $referenceNumber,
        ]);
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request)
    {
        if (config('tenant_onboarding.enabled')) {
            return $this->submitOnboarding($request);
        }
        DB::beginTransaction();
        try {
            $validated = $this->validateTenantRequest($request);
            $this->validateIndividualOrCompanyFields($validated);
            $validated['sla_ref'] = $this->resolveSlaId($validated['property_id']);
            $validated['rent_amount'] = $this->resolveRentAmount($validated['unit_id']);
            Log::info('Validated tenant data: ' . json_encode($validated));

            // A lease always needs an SLA — same guarantee reassignReturningTenant()
            // and LeasesController::store() enforce, now that this creates a
            // LeaseAgreement too (see below).
            if (!$validated['sla_ref']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'property_id' => ['This property has no service agreement (SLA) configured — a lease cannot be created until one exists.'],
                ]);
            }

            // The create form only offers vacant units (see InspectionsController::
            // getUnitsByProperty's ?vacant_only=1), but that's client-side — re-check
            // here so a unit that picked up an active lease between page load and
            // submit (or a direct POST) can't be double-assigned.
            $unit = Unit::findOrFail($validated['unit_id']);
            if (!$unit->isVacant()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'unit_id' => ['Selected unit is no longer vacant — it already has an active lease.'],
                ]);
            }

            $referenceNumber = $this->generateTenantReferenceNumber();
            Log::info('Generated reference number: ' . $referenceNumber);

            $photoPath = $this->handlePhotoUpload($request);

            $tenant = $this->createTenant($validated, $referenceNumber, $photoPath);
            Log::info('Tenant created with ID: ' . $tenant->id . ' by User ID: ' . Auth::id());

            // A tenant is only ever created here against a selected unit, so a
            // LeaseAgreement is created in the same step — shared core with
            // LeasesController::store() and reassignReturningTenant() (via
            // LeaseCreationTrait), so all three build a lease identically.
            $property = Property::findOrFail($validated['property_id']);
            $leaseAgreement = $this->createLeaseAgreementRecord([
                'client_id' => $property->client_id,
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_id'],
                'tenant_id' => $tenant->id,
                'sla_id' => $validated['sla_ref'],
                'date_start' => $validated['lease_start_date'],
                'years' => $validated['lease_years'],
                'escalation_rate' => $validated['escalation_rate'] ?? 0,
            ]);
            Log::info('Lease agreement created with ID: ' . $leaseAgreement->id . ' for tenant ID: ' . $tenant->id);

            $this->saveDeposit($validated, $tenant, $leaseAgreement->id);
            Log::info('Deposit processed for tenant ID: ' . $tenant->id);

            $this->saveBankAccounts($request, $tenant->id);
            Log::info('Bank accounts saved for tenant ID: ' . $tenant->id);

            $this->saveOtherContacts($request, $tenant->id);
            Log::info('Other contacts saved for tenant ID: ' . $tenant->id);

            $this->saveDocuments($request, $tenant->id);
            Log::info('Documents saved for tenant ID: ' . $tenant->id);

            $this->saveNextOfKin($request, $tenant->id);
            Log::info('Next of kin saved for tenant ID: ' . $tenant->id);

            $this->saveMoveInInspection($request, $tenant, $validated);
            Log::info('Move-in inspection processed for tenant ID: ' . $tenant->id);

            DB::commit();

            return redirect()->route('property.tenants.show', Crypt::encrypt($tenant->id))
                ->with('success', 'Tenant created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating tenant: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create tenant: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Search landlords for the "Assign Unit" picker's optional landlord-first
     * narrowing step. Same query/response shape as
     * LeaseEscalationController::searchLandlords() (landlord-typed clients,
     * reused here rather than duplicated with different behavior).
     */
    public function searchLandlordsForUnitPicker(Request $request)
    {
        try {
            $term = $request->get('q', '');

            $landlords = Client::where('reference_number', 'LIKE', 'LAN%')
                ->where(function ($q) use ($term) {
                    $q->where('names', 'ILIKE', "%{$term}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$term}%");
                })
                ->select('id', 'names', 'reference_number')
                ->limit(30)
                ->get()
                ->map(fn($c) => ['id' => $c->id, 'text' => $c->names . ' (' . $c->reference_number . ')']);

            return response()->json(['results' => $landlords]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching landlords for unit picker: ' . $e->getMessage());
            return response()->json(['results' => []], 500);
        }
    }

    /**
     * Search properties for the "Assign Unit" picker, optionally narrowed to
     * one landlord's properties (client_id, from searchLandlordsForUnitPicker()
     * above). Property ids are plain (not encrypted) throughout this cascade,
     * matching every other landlord→property→unit picker in the codebase
     * (e.g. LeaseEscalationController::getLandlordProperties()/getPropertyUnits()).
     *
     * Once a landlord is chosen, the client-side field switches to listing
     * their full property set on open rather than requiring a search term
     * (a landlord rarely owns enough properties to need one) — so the limit
     * only applies to the unscoped, type-to-search-everything case.
     */
    public function searchPropertiesForUnitPicker(Request $request)
    {
        try {
            $query = Property::select(['id', 'property_name', 'reference_number']);

            $scopedToLandlord = $request->filled('client_id');
            if ($scopedToLandlord) {
                $query->where('client_id', $request->input('client_id'));
            }

            if ($request->filled('term')) {
                $term = $request->input('term');
                $query->where(function ($q) use ($term) {
                    $q->where('property_name', 'ILIKE', "%{$term}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$term}%");
                });
            }

            $query->orderBy('property_name');
            if (!$scopedToLandlord) {
                $query->limit(30);
            }

            $properties = $query->get()
                ->map(fn($p) => ['id' => $p->id, 'text' => $p->property_name . ' (' . $p->reference_number . ')']);

            return response()->json(['results' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching properties for unit picker: ' . $e->getMessage());
            return response()->json(['results' => []], 500);
        }
    }

    /**
     * Vacant units for a chosen property — the final step of the "Assign
     * Unit" picker cascade (Landlord → Property → Unit). Property-scoped and
     * loads immediately (minimumInputLength: 0 client-side) rather than
     * requiring a search term, since a single property's vacant units are
     * few enough to list in full.
     *
     * "Vacant" here is Unit::scopeVacant() — no Pending/Approved lease — not
     * occupancy_status_id, which turned out to carry conflicting numbering
     * schemes across the codebase and couldn't be trusted (see the docblock
     * on Unit::activeLeaseAgreement() for the full finding).
     */
    public function searchVacantUnits(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer']);

            $units = Unit::where('property_id', $request->input('property_id'))
                ->vacant()
                ->select(['id', 'unit_code', 'unit_number', 'unit_name'])
                ->orderBy('unit_code')
                ->limit(100)
                ->get()
                ->map(function ($unit) {
                    $label = $unit->unit_code;
                    if ($unit->unit_name) {
                        $label .= ' (' . $unit->unit_name . ')';
                    }

                    return ['id' => $unit->id, 'text' => $label];
                });

            return response()->json(['results' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching vacant units: ' . $e->getMessage());
            return response()->json(['results' => []], 500);
        }
    }

    /**
     * Manually assign a unit to a tenant created without one — the second
     * step of the bulk-upload flow (rows uploaded with a blank Unit Code land
     * in the "Pending Unit" queue on the tenant listing until this runs).
     * Sets property_id from the unit's own property and re-derives
     * sla_id/rent_amount exactly like the interactive create-tenant form.
     * Deliberately leaves the unit's occupancy_status_id untouched, matching
     * store()/createTenant(), which never flip it either.
     *
     * The re-check below (isVacant()) is lease-based for the same reason
     * searchVacantUnits() above is — occupancy_status_id is not a reliable
     * signal in this codebase (see Unit::activeLeaseAgreement()).
     */
    public function assignUnit(Request $request, $encryptedId)
    {
        try {
            $tenantId = Crypt::decrypt($encryptedId);
            // withAll(): a pending-unit tenant has no property yet, so the model's
            // user_clients_properties global scope (which requires an existing
            // property assignment to grant visibility) would never match it —
            // bypass it here the same way generateTenantReferenceNumber() does.
            $tenant = Client::withAll()->tenants()->findOrFail($tenantId);

            $validated = $request->validate([
                'unit_id' => 'required|integer|exists:sys_pm_units,id',
            ]);

            $unit = Unit::findOrFail($validated['unit_id']);

            if (!$unit->isVacant()) {
                return response()->json(['success' => false, 'message' => 'Selected unit is no longer vacant.'], 422);
            }

            $tenant->update([
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'sla_id' => $this->resolveSlaId($unit->property_id),
                'rent_amount' => $this->resolveRentAmount($unit->id),
            ]);

            return response()->json(['success' => true, 'message' => 'Unit assigned to tenant successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error assigning unit to tenant: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign unit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show the "Returning Tenant" page — search any tenant (not just an
     * exited one — see searchTenantsForReassignment()), assign them a new
     * unit, and create their lease, all as one combined action.
     *
     * Optional ?tenant={encrypted_id} pre-selects a tenant — used by the
     * Tenant History page's "Reassign This Tenant" link. Resolved server-side
     * (rather than making the page do an extra AJAX round-trip) since the
     * display name/reference is needed to seed the select2 option before it
     * can be selected.
     */
    public function returningTenantCreate(Request $request)
    {
        $preselectedTenant = null;

        if ($request->filled('tenant')) {
            try {
                $tenantId = Crypt::decrypt($request->input('tenant'));
                $tenant = Client::withAll()->tenants()->find($tenantId);
                if ($tenant) {
                    $preselectedTenant = [
                        'encrypted_id' => $request->input('tenant'),
                        'names' => $tenant->names,
                        'reference_number' => $tenant->reference_number,
                        'mobile_number' => $tenant->mobile_number,
                    ];
                }
            } catch (\Exception $e) {
                // Invalid/undecryptable id — ignore, page just loads with no pre-selection.
            }
        }

        return view('property.tenants.returning.create', compact('preselectedTenant'));
    }

    /**
     * Search among ANY tenant — the first step of the Returning Tenant flow.
     * Mirrors InvoicingController::searchTenantsDirectly()'s
     * name/reference/mobile/email/national_id matching.
     *
     * Deliberately NOT scoped to exited tenants: this app's tenant data can
     * originate from another system, and may never have gone through this
     * app's formal exit workflow — a tenant whose Client row still shows a
     * unit/lease might, in reality, already be gone. Restricting this search
     * to "exited" tenants would hide exactly the tenants most likely to have
     * that stale-data problem. `is_exited` is still flagged (informational
     * badge only, via a subquery rather than a join — a join risks duplicate
     * rows if a tenant somehow has more than one completed exit record).
     *
     * withAll(): a tenant with a cleared/never-set property_id (either a
     * properly exited tenant, or migrated data with a blank assignment)
     * would otherwise be hidden from a non-global-role user by the model's
     * user_clients_properties global scope — same reasoning as assignUnit()
     * above.
     */
    public function searchTenantsForReassignment(Request $request)
    {
        try {
            $search = $request->input('search', '');
            if (strlen($search) < 2) {
                return response()->json(['success' => false, 'message' => 'Enter at least 2 characters'], 400);
            }

            $tenants = Client::withAll()->tenants()
                ->where(function ($q) use ($search) {
                    $q->where('sys_pm_clients.names', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.reference_number', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.mobile_number', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.email_address', 'ILIKE', "%{$search}%")
                        ->orWhere('sys_pm_clients.national_id', 'ILIKE', "%{$search}%");
                })
                ->select('sys_pm_clients.id', 'sys_pm_clients.names', 'sys_pm_clients.mobile_number', 'sys_pm_clients.email_address', 'sys_pm_clients.national_id', 'sys_pm_clients.reference_number')
                ->selectRaw('EXISTS(SELECT 1 FROM cb_tenant_exits WHERE cb_tenant_exits.tenant_id = sys_pm_clients.id AND cb_tenant_exits.current_status_id = 2) as is_exited')
                ->orderBy('sys_pm_clients.names')
                ->limit(50)
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'encrypted_id' => Crypt::encrypt($t->id),
                    'names' => $t->names ?? 'N/A',
                    'mobile_number' => $t->mobile_number,
                    'email_address' => $t->email_address,
                    'national_id' => $t->national_id,
                    'reference_number' => $t->reference_number,
                    'is_exited' => (bool) $t->is_exited,
                ]);

            return response()->json(['success' => true, 'tenants' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching tenants for reassignment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to search tenants'], 500);
        }
    }

    /**
     * A tenant's previous tenancy record(s) — read-only context, most recent
     * first. Backed by cb_tenant_history (TenantHistory), written at exit
     * finalization (see TenantExitController::finaliseExit()) and at
     * clearCurrentTenancy() below. Shared by the Returning Tenant flow and
     * the standalone Tenant History page (historyPage()).
     */
    public function tenantHistory($encryptedId)
    {
        try {
            $tenantId = Crypt::decrypt($encryptedId);

            $history = TenantHistory::where('tenant_id', $tenantId)
                ->with([
                    'property:id,property_name',
                    'unit:id,unit_code,unit_name',
                    'landlord:id,names',
                    'sla:id,reference_number',
                ])
                ->orderByDesc('exit_date')
                ->get()
                ->map(fn($h) => [
                    'id' => $h->id,
                    'property_name' => $h->property->property_name ?? '-',
                    'unit_code' => $h->unit->unit_code ?? $h->unit->unit_name ?? '-',
                    'landlord_name' => $h->landlord->names ?? '-',
                    'lease_start_date' => $h->lease_start_date ? $h->lease_start_date->format('Y-m-d') : '-',
                    'exit_date' => $h->exit_date ? $h->exit_date->format('Y-m-d') : '-',
                    'duration_label' => $h->duration_label,
                    'rent_amount' => $h->rent_amount,
                    'sla_reference' => $h->sla->reference_number ?? '-',
                    'exit_reason' => $h->exit_reason ?? '-',
                ]);

            return response()->json(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant history: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load tenant history'], 500);
        }
    }
    /**
     * A tenant's CURRENT on-record assignment, if any — distinct from
     * tenantHistory() above (which is what they've EVER had). Read from the
     * Client row directly plus any Active LeaseAgreement. Shared by the
     * Returning Tenant flow (to decide whether a "clear current assignment"
     * confirmation is required — see reassignReturningTenant()) and the
     * standalone Tenant History page.
     */
    public function currentTenancy($encryptedId)
    {
        try {
            $tenantId = Crypt::decrypt($encryptedId);

            $tenant = Client::withAll()->tenants()
                ->with(['property:id,property_name', 'unit:id,unit_code,unit_name'])
                ->findOrFail($tenantId);

            $slaReference = $tenant->sla_id
                ? \App\Models\ServiceAgreement::where('id', $tenant->sla_id)->value('reference_number')
                : null;

            $activeLease = LeaseAgreement::where('tenant_id', $tenant->id)
                ->where('lease_status', 'Active')
                ->latest('date_start')
                ->first();

            return response()->json([
                'success' => true,
                'has_current' => (bool) $tenant->unit_id,
                'property_name' => $tenant->property->property_name ?? null,
                'unit_code' => $tenant->unit->unit_code ?? $tenant->unit->unit_name ?? null,
                'sla_reference' => $slaReference,
                'rent_amount' => $tenant->rent_amount,
                'lease_start_date' => $tenant->lease_start_date ? $tenant->lease_start_date->format('Y-m-d') : null,
                'lease_years' => $tenant->lease_years,
                'frequency' => $tenant->frequency,
                'active_lease' => $activeLease ? [
                    'ref_number' => $activeLease->ref_number,
                    'lease_status' => $activeLease->lease_status,
                    'date_start' => $activeLease->date_start ? $activeLease->date_start->format('Y-m-d') : null,
                    'date_end' => $activeLease->date_end ? $activeLease->date_end->format('Y-m-d') : null,
                ] : null,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading current tenancy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load current tenancy'], 500);
        }
    }

    /**
     * The standalone "Tenant History" page — reachable from tenant details.
     * Read-only: current assignment (if any) + previous tenancies, no
     * clear/reassign actions here (those are exclusive to the Returning
     * Tenant flow, to avoid this general info page having side effects).
     */
    public function historyPage($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $tenant = Client::tenants()->findOrFail($id);

            return view('property.tenants.history', compact('tenant'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant history page: ' . $e->getMessage());
            return redirect()->route('property.tenants.listing')->with('error', 'Tenant not found');
        }
    }

    /**
     * Reassign an exited tenant to a new unit and create their lease — one
     * combined action, one transaction: the Client row is updated to the new
     * tenancy, a LeaseAgreement is created for it (via LeaseCreationTrait,
     * shared with LeasesController::store() so both build a lease
     * identically), and — if months were entered — a Deposit is recorded
     * (via saveDeposit(), shared with onboarding's own deposit capture).
     */
    public function reassignReturningTenant(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|string',
                'unit_id' => 'required|integer|exists:sys_pm_units,id',
                'lease_start_date' => 'required|date',
                'lease_years' => 'required|integer|min:1|max:10',
                'auto_renewal' => 'required|in:Yes,No',
                'escalation_rate' => 'required|numeric|min:0|max:100',
                'escalation_frequency' => 'required|in:Annually,Semi-Annually,Quarterly,Monthly',
                'frequency' => 'required|in:Annually,Monthly,Quarterly,Semi-Annually,Biennially',
                'service_charges_amount' => 'nullable|numeric|min:0',
                'deposit_months' => 'nullable|numeric|min:0.1',
                'deposit_held_by' => 'required_with:deposit_months|nullable|in:Company,Landlord',
                'deposit_reference' => 'nullable|string|max:100',
                'clear_current_assignment' => 'nullable|in:1',
                'clear_reason' => 'nullable|string|max:500',
                'invoicable_items' => 'nullable|array',
                'invoicable_items.*.item' => 'required|exists:sys_set_invoiceable_items,id',
                'invoicable_items.*.amount' => 'required|numeric|min:0',
                'invoicable_items.*.cycle' => 'required|exists:sys_set_invoiceable_items_cycles,id',
                'invoicable_items.*.prepay_client' => 'nullable|in:Yes',
            ]);

            try {
                $tenantId = Crypt::decrypt($validated['tenant_id']);
            } catch (\Exception $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tenant_id' => ['Invalid tenant selected.'],
                ]);
            }

            // withAll(): same reasoning as searchTenantsForReassignment() above —
            // a tenant with no property_id yet would otherwise be hidden from a
            // non-global-role user.
            $tenant = Client::withAll()->tenants()->findOrFail($tenantId);
            $unit = Unit::findOrFail($validated['unit_id']);

            if (!$unit->isVacant()) {
                return response()->json(['success' => false, 'message' => 'Selected unit is no longer vacant.'], 422);
            }

            // 0. If this tenant's Client row still shows a current unit — real
            // data (an actual active tenancy) or stale/migrated data that was
            // never properly cleared — it must be explicitly cleared first.
            // Not automatic: this is a deliberate, staff-confirmed action (see
            // clearCurrentTenancy() below), since the "current" data might
            // genuinely still be correct.
            if ($tenant->unit_id) {
                if ($request->input('clear_current_assignment') !== '1') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'clear_current_assignment' => ['This tenant currently has a unit/lease on record — confirm clearing it before reassigning.'],
                    ]);
                }

                $this->clearCurrentTenancy($tenant, $validated['clear_reason'] ?? null);
            }

            $property = Property::findOrFail($unit->property_id);

            // A lease always needs an SLA — same guarantee LeasesController::store() enforces.
            $slaId = $this->resolveSlaId($unit->property_id);
            if (!$slaId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'unit_id' => ['This property has no service agreement (SLA) configured — a lease cannot be created until one exists.'],
                ]);
            }

            // 1. Update the Client row to the new tenancy
            $tenant->update([
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'sla_id' => $slaId,
                'rent_amount' => $this->resolveRentAmount($unit->id),
                'lease_start_date' => $validated['lease_start_date'],
                'lease_years' => $validated['lease_years'],
                'frequency' => $validated['frequency'],
            ]);

            // 2. Create the LeaseAgreement (+ ref number, invoiceable items, unit
            // occupancy flip) — shared core with LeasesController::store().
            $leaseAgreement = $this->createLeaseAgreementRecord([
                'client_id' => $property->client_id,
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'sla_id' => $slaId,
                'date_start' => $validated['lease_start_date'],
                'years' => $validated['lease_years'],
                'auto_renewal' => $validated['auto_renewal'],
                'escalation_rate' => $validated['escalation_rate'],
                'escalation_frequency' => $validated['escalation_frequency'],
                'service_charges_amount' => $validated['service_charges_amount'] ?? null,
            ], $validated['invoicable_items'] ?? []);

            // 3. Deposit — same computation/creation as onboarding, linked to
            // the lease just created.
            $this->saveDeposit([
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'sla_ref' => $slaId,
                'deposit_months' => $validated['deposit_months'] ?? null,
                'deposit_held_by' => $validated['deposit_held_by'] ?? null,
                'deposit_reference' => $validated['deposit_reference'] ?? null,
            ], $tenant, $leaseAgreement->id);

            DB::commit();

            Log::info('Returning tenant reassigned', [
                'tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
                'lease_id' => $leaseAgreement->id,
                'reassigned_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant reassigned and lease created successfully with reference: ' . $leaseAgreement->ref_number,
                'redirect_url' => route('property.tenants.show', $tenant->encrypted_id),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error reassigning returning tenant: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to reassign tenant: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Clear a tenant's current on-record unit/lease before reassigning them
     * elsewhere — for a tenant whose Client row still shows an assignment
     * that turned out to be stale/incorrect (data from another system, never
     * properly exited through this app). Mirrors
     * TenantExitController::finaliseExit()'s clear/archive logic, but
     * standalone: no TenantExit row backs this, and no deposit-refund step
     * runs (existing deposits from the old tenancy are left untouched —
     * out of scope here, unlike a formal exit).
     *
     * occupancy_status_id is set to 31 (not finaliseExit()'s 3) — this
     * mirrors LeasesController's own standalone lease-cancel action instead,
     * since that's the same situation this is: a standalone vacate outside
     * the formal exit workflow. (The two values are a pre-existing
     * inconsistency across this codebase; Unit::isVacant()/scopeVacant() —
     * the actual vacancy check used everywhere — is lease-based and doesn't
     * consult occupancy_status_id at all, so this choice only affects
     * display, e.g. TenantMovementController's occupied/vacant unit lists.)
     */
    private function clearCurrentTenancy(Client $tenant, ?string $reason): void
    {
        LeaseAgreement::where('tenant_id', $tenant->id)
            ->where('lease_status', 'Active')
            ->update([
                'lease_status' => 'Terminated',
                'current_status_id' => 2,
                'date_end' => now(),
                'open_ended' => false,
            ]);

        if ($tenant->unit_id) {
            Unit::where('id', $tenant->unit_id)->update(['occupancy_status_id' => 31]);
        }

        $oldUnitId = $tenant->unit_id;
        // cb_tenant_history.property_id is NOT NULL — fall back to the unit's
        // own property_id if the Client row's own property_id is inconsistent
        // with its unit_id (exactly the kind of bad/migrated data this method
        // exists to clean up).
        $oldPropertyId = $tenant->property_id ?: Unit::where('id', $oldUnitId)->value('property_id');
        $oldSlaId = $tenant->sla_id;
        $oldRentAmount = $tenant->rent_amount;
        $oldLeaseStart = $tenant->lease_start_date;

        $tenant->update([
            'unit_id' => null,
            'property_id' => null,
            'sla_id' => null,
            'rent_amount' => null,
            'lease_start_date' => null,
            'lease_years' => null,
            'frequency' => null,
        ]);

        $landlordId = $oldPropertyId ? Property::where('id', $oldPropertyId)->value('client_id') : null;
        $exitDate = now();
        $durationDays = $oldLeaseStart
            ? (int) \Carbon\Carbon::parse($oldLeaseStart)->diffInDays($exitDate)
            : null;

        TenantHistory::create([
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlordId,
            'property_id' => $oldPropertyId,
            'unit_id' => $oldUnitId,
            'exit_id' => null,
            'lease_start_date' => $oldLeaseStart,
            'rent_amount' => $oldRentAmount,
            'sla_id' => $oldSlaId,
            'exit_date' => $exitDate,
            'duration_days' => $durationDays,
            'exit_reason' => $reason ?: 'Reassigned via Returning Tenant flow — cleared stale/incorrect prior assignment',
            'recorded_by' => Auth::id(),
        ]);
    }

    /**
     * Validate tenant request
     */
    public function validateTenantOnboarding(Request $request): array
    {
        $validated = $this->validateTenantRequest($request);
        $this->validateIndividualOrCompanyFields($validated);
        $property = Property::findOrFail($validated['property_id']);
        $unit = Unit::where('property_id', $property->id)->findOrFail($validated['unit_id']);
        $validated['sla_ref'] = $this->resolveSlaId($property->id);
        $validated['rent_amount'] = (float) $unit->rent_amount;
        if (!$validated['sla_ref'] || $validated['rent_amount'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['unit_id' => 'Select a unit with rent configured and a property service agreement.']);
        }
        $extra = $request->validate([
            'effective_month' => 'nullable|date_format:Y-m',
            'previous_tenant_vacated' => 'nullable|date',
            'inspection_date' => 'nullable|date', 'inspector_name' => 'nullable|string|max:255',
            'inspection_notes' => 'nullable|string|max:5000',
            'walls_condition' => 'nullable|string|max:255', 'floors_condition' => 'nullable|string|max:255',
            'ceiling_condition' => 'nullable|string|max:255', 'doors_condition' => 'nullable|string|max:255',
            'windows_condition' => 'nullable|string|max:255', 'plumbing_condition' => 'nullable|string|max:255',
            'electrical_condition' => 'nullable|string|max:255', 'overall_condition' => 'nullable|string|max:255',
            'inspection_photos.*' => 'image|max:5120', 'inspection_videos.*' => 'file|mimes:mp4,mov,webm|max:51200',
            'tenant_files.*.file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);
        $requiredInspection = DB::table('sys_pm_service_agreements')->where('id', $validated['sla_ref'])->value('move_in_inspection_required');
        if ($requiredInspection && empty($extra['inspection_date'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['inspection_date' => 'This property requires a move-in inspection.']);
        }
        return $validated + $extra;
    }

    private function submitOnboarding(Request $request)
    {
        abort_unless($request->user()?->hasPermission('create_tenants'), 403);
        $workflow = app(\App\Services\TenantOnboardingService::class);
        $existing = null;
        $revision = null;
        if ($request->filled('onboarding_id')) {
            $request->validate(['onboarding_id' => 'required|integer', 'onboarding_revision' => 'required|integer']);
            $existing = \App\Models\TenantOnboarding::findOrFail($request->input('onboarding_id'));
            abort_unless($existing->prepared_by === (int) $request->user()->id && $existing->state === 'changes_requested', 403);
            $revision = (int) $request->input('onboarding_revision');
        }
        $payload = $this->validateTenantOnboarding($request);
        if ($existing?->batch_tenant_id) {
            $payload['lease_open_ended'] = $existing->payload['lease_open_ended'] ?? false;
            $payload['lease_term_days'] = $existing->payload['lease_term_days'] ?? null;
        }
        // Uploaded files are held privately; the signed payload stores only form values.
        foreach (array_keys(\Illuminate\Support\Arr::dot($request->allFiles())) as $field) {
            \Illuminate\Support\Arr::forget($payload, $field);
        }
        $files = app(\App\Services\TenantOnboardingFiles::class)->stage($request, $existing?->documents ?? []);
        try {
            $submission = $workflow->submit($payload, $files, $request->user(), $existing, $revision);
        } catch (\Throwable $e) {
            $oldPaths = array_column($existing?->documents ?? [], 'path');
            Storage::disk('local')->delete(array_values(array_diff(array_column($files, 'path'), $oldPaths)));
            throw $e;
        }
        return redirect()->route('tenant-onboarding.show', $submission)->with('success', 'Tenant submitted for approval.');
    }

    public function effectOnboarding(Request $request, \App\Models\TenantOnboarding $submission)
    {
        $request->validate(['revision' => 'required|integer|min:1']);
        $published = [];
        try {
            $submission = app(\App\Services\TenantOnboardingService::class)->effect(
                $submission, $request->user(), (int) $request->input('revision'),
                function ($approved, $effectedAt) use (&$published) {
                    // The workflow has authorised this specific property for the Assistant Auditor.
                    $property = Property::withoutGlobalScope('user_properties')->findOrFail($approved->property_id);
                    $data = $approved->payload;
                    $data['lease_start_date'] = $effectedAt->toDateString();
                    if (DB::getDriverName() === 'pgsql') {
                        DB::select('SELECT pg_advisory_xact_lock(?)', [7240041]);
                    }
                    $published = app(\App\Services\TenantOnboardingFiles::class)->publish($approved);
                    $tenant = $this->createTenant($data, $this->generateTenantReferenceNumber(), $published['tenant_photo'] ?? null);
                    $tenant->update(['current_status_id' => 5, 'created_by' => $approved->prepared_by]);
                    $leaseData = [
                        'client_id' => $property->client_id, 'property_id' => $property->id,
                        'unit_id' => $approved->unit_id, 'tenant_id' => $tenant->id, 'sla_id' => $data['sla_ref'],
                        'date_start' => $data['lease_start_date'], 'years' => $data['lease_years'],
                        'escalation_rate' => $data['escalation_rate'] ?? 0,
                        'escalation_frequency' => $data['frequency'],
                    ];
                    if (!empty($data['lease_open_ended'])) {
                        $leaseData['open_ended'] = true;
                    } elseif (!empty($data['lease_term_days'])) {
                        $leaseData['date_end'] = $effectedAt->copy()->addDays($data['lease_term_days'])->toDateString();
                    }
                    $lease = $this->createLeaseAgreementRecord($leaseData, [], $property, $approved);
                    $lease->update(['current_status_id' => 5, 'created_by_id' => $approved->prepared_by]);
                    $tenant->update(['lease_years' => $lease->years]);
                    $form = new Request($data);
                    $this->saveBankAccounts($form, $tenant->id);
                    $this->saveOtherContacts($form, $tenant->id);
                    $this->saveNextOfKin($form, $tenant->id);
                    foreach ($data['tenant_files'] ?? [] as $index => $document) {
                        ClientDocument::create(['parent_id' => $tenant->id, 'file_type' => $document['type'] ?? null,
                            'documents' => $published['tenant_files.'.$index.'.file'] ?? null]);
                    }
                    $this->saveMoveInInspection($form, $tenant, $data);
                    if (!empty($data['inspection_date'])) {
                        $photos = array_values(array_filter($published, fn ($field) => str_starts_with($field, 'inspection_photos.'), ARRAY_FILTER_USE_KEY));
                        $videos = array_values(array_filter($published, fn ($field) => str_starts_with($field, 'inspection_videos.'), ARRAY_FILTER_USE_KEY));
                        MoveInInspection::where('tenant_id', $tenant->id)->update(['photo_paths' => json_encode($photos), 'video_paths' => json_encode($videos), 'conducted_by' => $approved->prepared_by]);
                    }
                    $rentItem = \App\Models\InvoiceableItem::where('billing_code', \App\Services\InvoiceBillingService::RENT_CODE)->firstOrFail();
                    $invoice = $this->createSingleDebtorInvoice([
                        'tenant_id' => $tenant->id, 'property_id' => $property->id, 'unit_id' => $approved->unit_id,
                        'lease_id' => $lease->id, 'item_type_id' => $rentItem->id,
                        'date_due' => $data['lease_start_date'], 'returns_period' => $data['lease_start_date'],
                        'total_amount' => $data['rent_amount'],
                    ], $property);
                    if ($approved->batch_tenant_id) {
                        DB::table('temp_batch_tenants')->where('id', $approved->batch_tenant_id)->update(['tenant_id' => $tenant->id, 'lease_id' => $lease->id]);
                    }
                    return ['tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'invoice_id' => $invoice->id];
                }
            );
        } catch (\Throwable $e) {
            Storage::disk('public')->delete(array_values($published));
            if ($e instanceof \Illuminate\Validation\ValidationException || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }
            report($e);
            return back()->with('error', 'Effecting failed. The submission remains pending. Please check the billing setup and try again.');
        }
        return redirect()->route('tenant-onboarding.show', $submission)->with('success', 'Tenant confirmed and effected. The lease is active and the first rent bill has been raised.');
    }

    private function validateTenantRequest(Request $request)
    {
        return $request->validate([
            // Unit Details
            // sla_ref/rent_amount are no longer submitted — resolved server-side in store()
            // from the property/unit (see resolveSlaId()/resolveRentAmount()).
            'property_id' => 'required|integer|exists:sys_pm_properties,id',
            'unit_id' => 'required|integer|exists:sys_pm_units,id',
            'lease_start_date' => 'required|date',
            'lease_years' => 'required|integer|min:1',
            'escalation_rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:Annually,Monthly,Quarterly,Semi-Annually,Biennially',
            'address_2' => 'nullable|string|max:255',
            'address_3' => 'nullable|string|max:255',
            'deposit_months' => 'nullable|numeric|min:0.1',
            'deposit_held_by' => 'required_with:deposit_months|nullable|in:Company,Landlord',

            // Tenant Information
            // Individual-only fields: not required here since a Company tenant doesn't fill
            // these in. store() applies its own conditional requirement via
            // validateIndividualOrCompanyFields().
            'title' => 'nullable|string|max:10',
            'names' => 'nullable|string|max:255',
            'gender' => 'nullable|in:Male,Female,Other',
            'tenant_type' => 'required|integer|in:1,3',
            'phone' => 'nullable|string|max:20',
            'mobile_number' => 'required',
            'email_address' => 'nullable|email|max:255',
            'pin_number' => 'nullable|string|max:50',
            'registered_date' => 'required|date',
            'nationality_id' => 'nullable|integer',
            'tenant_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'passport_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'national_id' => 'nullable|string|max:50',

            // Company-only fields (Tenant Type "Company")
            'company_name' => 'nullable|string|max:255',
            'company_registration_number' => 'nullable|string|max:255',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_title' => 'nullable|string|max:255',
            'contact_person_mobile' => 'nullable|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',

            // Bank Accounts (repeater)
            'bank_accounts.*.bank_id' => 'nullable|integer',
            'bank_accounts.*.branch_id' => 'nullable|integer',
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_number' => 'nullable|string|max:100',

            // Other Contacts (repeater)
            'other_contacts.*.name' => 'nullable|string|max:255',
            'other_contacts.*.email' => 'nullable|email|max:255',
            'other_contacts.*.mobile' => 'nullable|string|max:20',

            // Tenant Files (repeater)
            'tenant_files.*.type' => 'nullable|string|max:100',
            'tenant_files.*.file' => 'nullable|file|max:5120',

            // Next of Kin (repeater)
            'next_of_kin.*.names' => 'nullable|string|max:255',
            'next_of_kin.*.mobile' => 'nullable|string|max:20',
            'next_of_kin.*.email' => 'nullable|email|max:255',
            'next_of_kin.*.relationship' => 'nullable|string|max:50',
            'next_of_kin.*.national_id' => 'nullable|string|max:50',
            'next_of_kin.*.second_name' => 'nullable|string|max:255',
            'next_of_kin.*.last_name' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Individual vs Company are mutually exclusive on the create-tenant form
     * (tenant_type 1 = Individual, 3 = Company). Their required fields can't
     * live in validateTenantRequest()'s rule array since Company doesn't
     * fill in Individual-only fields and vice versa.
     */
    private function validateIndividualOrCompanyFields(array $validated): void
    {
        if ((int) $validated['tenant_type'] === 3) {
            if (empty($validated['company_name'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'company_name' => ['Company name is required for a Company tenant.'],
                ]);
            }
        } else {
            $missing = [];
            if (empty($validated['names'])) {
                $missing['names'] = ['Name is required for an Individual tenant.'];
            }
            if (empty($validated['nationality_id'])) {
                $missing['nationality_id'] = ['Nationality is required for an Individual tenant.'];
            }
            if (empty($validated['national_id']) && empty($validated['passport_number'])) {
                $missing['national_id'] = ['National ID or Passport Number is required for an Individual tenant.'];
            }
            if (!empty($missing)) {
                throw \Illuminate\Validation\ValidationException::withMessages($missing);
            }
        }
    }

    /**
     * Validate the tenant edit form request (single "Names" field, no property/unit/SLA/rent editing)
     */
    private function validateTenantEditRequest(Request $request)
    {
        return $request->validate([
            // Unit Details (read-only on the edit form; carried through as hidden fields)
            // sla_ref/rent_amount are no longer submitted — resolved server-side in update()
            // from the property/unit (see resolveSlaId()/resolveRentAmount()).
            'property_id' => 'required|integer|exists:sys_pm_properties,id',
            'unit_id' => 'required|integer|exists:sys_pm_units,id',
            'lease_start_date' => 'required|date',
            'lease_years' => 'required|integer|min:1',
            'escalation_rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:Annually,Monthly,Quarterly,Semi-Annually,Biennially',
            'address_2' => 'nullable|string|max:255',
            'address_3' => 'nullable|string|max:255',

            // Tenant Information
            // Individual-only fields: not required here since a Company tenant doesn't fill
            // these in. update() applies its own conditional requirement via
            // validateIndividualOrCompanyFields().
            'title' => 'nullable|string|max:10',
            'names' => 'nullable|string|max:255',
            'gender' => 'nullable|in:Male,Female,Other',
            'preferred_language' => 'nullable|string|max:50',
            'tenant_type' => 'required|integer|in:1,3',
            'phone' => 'nullable|string|max:20',
            'mobile_number' => 'required',
            'email_address' => 'nullable|email|max:255',
            'pin_number' => 'nullable|string|max:50',
            'registered_date' => 'required|date',
            'nationality_id' => 'nullable|integer',
            'passport_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'national_id' => 'nullable|string|max:50',

            // Company-only fields (Tenant Type "Company")
            'company_name' => 'nullable|string|max:255',
            'company_registration_number' => 'nullable|string|max:255',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_title' => 'nullable|string|max:255',
            'contact_person_mobile' => 'nullable|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',

            // Bank Accounts (single account only)
            'bank_accounts.*.bank_id' => 'nullable|integer',
            'bank_accounts.*.branch_id' => 'nullable|integer',
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_number' => 'nullable|string|max:100',

            // Other Contacts (repeater)
            'other_contacts.*.name' => 'nullable|string|max:255',
            'other_contacts.*.email' => 'nullable|email|max:255',
            'other_contacts.*.mobile' => 'nullable|string|max:20',

            // Tenant Files (repeater)
            'tenant_files.*.type' => 'nullable|string|max:100',
            'tenant_files.*.file' => 'nullable|file|max:5120',

            // Next of Kin (repeater)
            'next_of_kin.*.names' => 'nullable|string|max:255',
            'next_of_kin.*.mobile' => 'nullable|string|max:20',
            'next_of_kin.*.email' => 'nullable|email|max:255',
            'next_of_kin.*.relationship' => 'nullable|string|max:50',
            'next_of_kin.*.national_id' => 'nullable|string|max:50',
            'next_of_kin.*.second_name' => 'nullable|string|max:255',
            'next_of_kin.*.last_name' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Handle photo upload
     */
    private function handlePhotoUpload(Request $request)
    {
        if ($request->hasFile('tenant_photo') && $request->file('tenant_photo')->isValid()) {
            return $request->file('tenant_photo')->store('tenants/photos', 'public');
        }
        return null;
    }

    /**
     * Create the main tenant record
     */
    private function createTenant(array $validated, string $referenceNumber, ?string $photoPath)
    {
        $isCompany = (int) $validated['tenant_type'] === 3;

        // Combine title + names for Individual; Company has no title, just its own name.
        $names = $isCompany
            ? trim($validated['company_name'])
            : trim(($validated['title'] ?? '') . ' ' . ($validated['names'] ?? ''));

        $tenantData = [
            'reference_number' => $referenceNumber,
            'names' => $names,
            'title' => $isCompany ? null : ($validated['title'] ?? null),
            'gender' => $isCompany ? null : ($validated['gender'] ?? null),
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'pin_number' => $validated['pin_number'] ?? null,
            'national_id' => $isCompany ? null : ($validated['national_id'] ?? null),
            'passport_number' => $isCompany ? null : ($validated['passport_number'] ?? null),
            'address' => $validated['address'] ?? null,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'registered_date' => $validated['registered_date'],
            'nationality_id' => $isCompany ? null : ($validated['nationality_id'] ?? null),
            'prefered_messages_language_id' => 'English',
            'client_type_id' => $validated['tenant_type'],
            // 2 = Tenant, per Client::tenants() scope used throughout the app — previously
            // never set here, so every tenant created through this form got type_id = NULL
            // and was invisible to that scope (mirrors the same bug already fixed for
            // landlords in ClientsController::createClient()).
            'type_id' => 2,
            'company_name' => $isCompany ? $validated['company_name'] : null,
            'company_registration_number' => $isCompany ? ($validated['company_registration_number'] ?? null) : null,
            'contact_person_name' => $isCompany ? ($validated['contact_person_name'] ?? null) : null,
            'contact_person_title' => $isCompany ? ($validated['contact_person_title'] ?? null) : null,
            'contact_person_mobile' => $isCompany ? ($validated['contact_person_mobile'] ?? null) : null,
            'contact_person_email' => $isCompany ? ($validated['contact_person_email'] ?? null) : null,
            'property_id' => $validated['property_id'],
            'sla_id' => $validated['sla_ref'] ?? null,
            'unit_id' => $validated['unit_id'],
            'lease_start_date' => $validated['lease_start_date'],
            'lease_years' => $validated['lease_years'],
            'rent_amount' => $validated['rent_amount'],
            'escalation_rate' => $validated['escalation_rate'] ?? 0,
            'frequency' => $validated['frequency'],
            'overdraft_penalty' => 5, // No longer user-entered — company-standard default
            'photo' => $photoPath,
            'created_by' => Auth::id(),
            'date_time' => now(),
            'current_status_id' => 1, // Pending — awaiting maker-checker approval
        ];

        $tenant = Client::create($tenantData);

        // sys_pm_clients stays the record leases/payments/invoicing key off (tenant_id
        // FKs everywhere still point here), but cb_tenant_records — same id, shared
        // primary key — is now the normalized source for the tenant's own profile data.
        $this->syncTenantRecord($tenant, $validated);

        return $tenant;
    }

    /**
     * Mirrors a tenant's profile fields into cb_tenant_records (+ Gender/Salutation
     * lookups), keyed by the same id as the sys_pm_clients row it belongs to.
     */
    private function syncTenantRecord(Client $tenant, array $validated): void
    {
        $isCompany = (int) $validated['tenant_type'] === 3;

        // Computed independently from $validated, not read off $tenant — Client's own
        // 'names' column is no longer kept in sync on update() (see updateTenant()),
        // so it can't be trusted as the fresh value here.
        $names = $isCompany
            ? trim($validated['company_name'] ?? '')
            : trim(($validated['title'] ?? '') . ' ' . ($validated['names'] ?? ''));

        $genderId = (!$isCompany && !empty($validated['gender']))
            ? Gender::where('name', strtoupper($validated['gender']))->value('id')
            : null;

        $salutationId = (!$isCompany && !empty($validated['title']))
            ? Salutation::where('name', $validated['title'])->value('id')
            : null;

        $nationalId = $isCompany ? null : ($validated['national_id'] ?? null);
        $passportNumber = $isCompany ? null : ($validated['passport_number'] ?? null);
        $idDocumentType = !empty($nationalId) ? 'NATIONAL_ID' : (!empty($passportNumber) ? 'PASSPORT' : null);

        $addressParts = array_filter([
            trim((string) ($validated['address'] ?? '')),
            trim((string) ($validated['address_2'] ?? '')),
            trim((string) ($validated['address_3'] ?? '')),
        ], fn($part) => $part !== '');
        $physicalAddress = !empty($addressParts) ? mb_substr(implode(', ', $addressParts), 0, 500) : null;

        TenantRecord::updateOrCreate(
            ['id' => $tenant->id],
            [
                'tenant_type' => $isCompany ? 'COMPANY' : 'INDIVIDUAL',
                'salutation_id' => $salutationId,
                'names' => $names,
                'gender_id' => $genderId,
                'phone' => $validated['mobile_number'] ?? null,
                'email' => $validated['email_address'] ?? null,
                'physical_address' => $physicalAddress,
                'nationality' => null,
                'id_document_type' => $idDocumentType,
                'national_id' => $nationalId,
                'passport_number' => $passportNumber,
                'kra_pin' => $validated['pin_number'] ?? null,
                'registered_date' => $validated['registered_date'] ?? null,
            ]
        );
    }

    /**
     * Bill the move-in deposit, if one was entered: a House Deposit invoice raised through the same shared
     * invoice code the One Off tab uses (invoice, tenant-ledger debit and, when posting is on, the GL journal),
     * linked from a row in the dedicated deposits register. The deposit is owed until the tenant pays it, so it
     * is no longer written as a direct credit on the ledger.
     *
     * $leaseId: optional — both the onboarding flow (store(), once
     * createLeaseAgreementRecord() has run) and the returning-tenant flow (see
     * reassignReturningTenant()) always have a LeaseAgreement by the time they
     * call this, and link the deposit to it.
     */
    private function saveDeposit(array $validated, Client $tenant, ?int $leaseId = null): void
    {
        $months = (float) ($validated['deposit_months'] ?? 0);

        if ($months <= 0) {
            return;
        }

        $amount = $this->computeDepositAmount((int) $validated['unit_id'], $months);

        $property = Property::find($validated['property_id']);
        $narration = 'Move-in security deposit';
        $now = now();

        $depositItem = \App\Models\InvoiceableItem::where('billing_code', '2002')->first();

        if (!$depositItem) {
            throw new \RuntimeException('The House Deposit item is not configured, so the deposit cannot be billed.');
        }

        // Raised today, due today, in this month's return period. A tenant who already has a House Deposit invoice
        // for the month is refused by the shared code's duplicate guard.
        $invoice = $this->createSingleDebtorInvoice([
            'tenant_id' => $tenant->id,
            'property_id' => $validated['property_id'],
            'unit_id' => $validated['unit_id'],
            'lease_id' => $leaseId,
            'item_type_id' => $depositItem->id,
            'date_due' => $now->toDateString(),
            'returns_period' => $now->toDateString(),
            'total_amount' => $amount,
            'deposit_held_by' => $validated['deposit_held_by'] ?? null,
        ]);

        Deposit::create([
            'date_time' => $now,
            'deposit_date' => $now,
            'tenant_id' => $tenant->id,
            'client_id' => $property->client_id ?? null,
            'property_id' => $validated['property_id'],
            'sla_id' => $validated['sla_ref'] ?? null,
            'unit_id' => $validated['unit_id'],
            'lease_id' => $leaseId,
            'invoice_id' => $invoice->id,
            'item_id' => $depositItem->id,
            'amount' => $amount,
            'invoice_balance' => $amount,
            'months' => $months,
            'held_by' => $validated['deposit_held_by'] ?? null,
            'created_by_id' => Auth::id(),
            'current_status_id' => 1,
            'reference_number' => $validated['deposit_reference'] ?? null,
            'narration' => $narration,
        ]);
    }

    /**
     * Save bank accounts to tenants_bank_details
     */
    private function saveBankAccounts(Request $request, int $tenantId)
    {
        if (!$request->has('bank_accounts')) {
            return;
        }

        $isFirst = true;

        foreach ($request->input('bank_accounts') as $bankData) {
            $bankId = $bankData['bank_id'] ?? null;
            $branchId = $bankData['branch_id'] ?? null;
            $accountName = $bankData['account_name'] ?? null;
            $accountNumber = $bankData['account_number'] ?? null;

            // Skip only if the entire row is blank
            if (empty($bankId) && empty($branchId) && empty($accountName) && empty($accountNumber)) {
                continue;
            }

            TenantBankDetail::create([
                'tenant_id' => $tenantId,
                'bank_id' => $bankId ?: null,
                'branch_id' => $branchId ?: null,
                'account_name' => $accountName ?: null,
                'account_number' => $accountNumber ?: null,
                'is_default' => $isFirst,
            ]);

            $isFirst = false;
        }
    }

    /**
     * Save other contacts to tenants_other_contacts
     */
    private function saveOtherContacts(Request $request, int $tenantId)
    {
        if (!$request->has('other_contacts')) {
            return;
        }

        foreach ($request->input('other_contacts') as $contactData) {
            if (empty($contactData['name'])) {
                continue;
            }

            TenantOtherContact::create([
                'tenant_id' => $tenantId,
                'names' => $contactData['name'],
                'email' => $contactData['email'] ?? null,
                'mobile_number' => $contactData['mobile'] ?? null,
            ]);
        }
    }

    /**
     * Save documents to sys_pm_clients_65_repeat
     */
    private function saveDocuments(Request $request, int $tenantId)
    {
        if (!$request->has('tenant_files')) {
            return;
        }

        foreach ($request->input('tenant_files') as $index => $documentData) {
            $documentPath = null;

            if ($request->hasFile("tenant_files.{$index}.file")) {
                $file = $request->file("tenant_files.{$index}.file");
                if ($file->isValid()) {
                    $documentPath = $file->store('tenants/documents', 'public');
                }
            }

            if ($documentPath || !empty($documentData['type'])) {
                ClientDocument::create([
                    'parent_id' => $tenantId,
                    'file_type' => $documentData['type'] ?? null,
                    'documents' => $documentPath,
                ]);
            }
        }
    }

    /**
     * Save next of kin to sys_pm_clients_66_repeat
     */
    private function saveNextOfKin(Request $request, int $tenantId)
    {
        if (!$request->has('next_of_kin')) {
            return;
        }

        foreach ($request->input('next_of_kin') as $kinData) {
            if (empty($kinData['names'])) {
                continue;
            }

            // tenants_nextof_kin only has kin_names + second_name (no separate
            // last_name column) — fold the form's last_name into kin_names so the
            // form itself doesn't need to change.
            $kinNames = trim(($kinData['names'] ?? '') . ' ' . ($kinData['last_name'] ?? ''));

            TenantNextOfKin::create([
                'tenant_id' => $tenantId,
                'kin_names' => $kinNames,
                'second_name' => $kinData['second_name'] ?? null,
                'mobile_number' => $kinData['mobile'] ?? null,
                'email_address' => $kinData['email'] ?? null,
                'relationship' => $kinData['relationship'] ?? null,
                'national_id' => $kinData['national_id'] ?? null,
            ]);
        }
    }

    /**
     * Show the form for editing a tenant
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $tenant = Client::with([
                'property',
                'sla',
                'unit',
                'tenantBankDetails.bank',
                'tenantOtherContacts',
                'documents',
                'tenantNextOfKin'
            ])->findOrFail($id);

            $countries = SystemCountry::orderBy('country_name')->get();

            // Profile fields (names, title, gender, contact info, identification,
            // registered_date) are now sourced from here, not from $tenant — see
            // updateTenant()/syncTenantRecord().
            $tenantRecord = TenantRecord::with(['salutation', 'gender'])->find($id);

            // Get client/landlord for this tenant\
            $client_id = $tenant->property->client_id ?? null;
            if ($client_id) {
                $client = Client::find($client_id);
            } else {
                $client = null;
            }

            // dd($tenant, $client);

            return view('property.properties.tenants.edit', compact('tenant', 'tenantRecord', 'client', 'countries'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant edit form: ' . $e->getMessage());
            return redirect()->route('property.tenants.listing')->with('error', 'Failed to load tenant');
        }
    }

    /**
     * Update the specified tenant
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $tenant = Client::findOrFail($id);

            // Validate the request
            $validated = $this->validateTenantEditRequest($request);
            $this->validateIndividualOrCompanyFields($validated);
            $validated['sla_ref'] = $this->resolveSlaId($validated['property_id']);
            $validated['rent_amount'] = $this->resolveRentAmount($validated['unit_id']);

            // Handle photo upload (if new photo provided)
            $photoPath = $tenant->photo;
            if ($request->hasFile('tenant_photo') && $request->file('tenant_photo')->isValid()) {
                // Delete old photo if exists
                if ($tenant->photo && Storage::disk('public')->exists($tenant->photo)) {
                    Storage::disk('public')->delete($tenant->photo);
                }
                $photoPath = $request->file('tenant_photo')->store('tenants/photos', 'public');
            }

            // Update the main tenant record
            $this->updateTenant($tenant, $validated, $photoPath);

            // Update related data (delete-recreate pattern)
            $this->updateBankAccounts($request, $tenant->id);
            $this->updateOtherContacts($request, $tenant->id);
            $this->updateDocuments($request, $tenant->id);
            $this->updateNextOfKin($request, $tenant->id);

            DB::commit();

            return redirect()->route('property.tenants.show', Crypt::encrypt($tenant->id))
                ->with('success', 'Tenant updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating tenant: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update tenant: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update the main tenant record
     */
    private function updateTenant(Client $tenant, array $validated, ?string $photoPath)
    {
        $isCompany = (int) $validated['tenant_type'] === 3;

        // Per-tenant profile fields that round-trip cleanly to a single cb_tenant_records
        // column (names, title, gender, mobile, email, pin/kra, national_id,
        // passport_number, registered_date) are no longer written to sys_pm_clients here
        // — syncTenantRecord() below is now the only thing that updates those.
        //
        // Two fields stay dual-maintained, by necessity of cb_tenant_records' own shape
        // (both as specified): address/address_2/address_3 collapse into ONE
        // physical_address column there, so they can't be split back out losslessly on
        // a later edit; nationality_id is a real FK (drives the country <select>) but
        // cb_tenant_records only has a plain nullable 'nationality' string with no
        // resolvable source today. sys_pm_clients also still gets lease/unit assignment
        // and company-contact fields, which cb_tenant_records has no columns for at all.
        $tenant->update([
            'address' => $validated['address'] ?? null,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'nationality_id' => $isCompany ? null : ($validated['nationality_id'] ?? null),
            'prefered_messages_language_id' => $validated['preferred_language'] ?? 'English',
            'client_type_id' => $validated['tenant_type'],
            'company_name' => $isCompany ? $validated['company_name'] : null,
            'company_registration_number' => $isCompany ? ($validated['company_registration_number'] ?? null) : null,
            'contact_person_name' => $isCompany ? ($validated['contact_person_name'] ?? null) : null,
            'contact_person_title' => $isCompany ? ($validated['contact_person_title'] ?? null) : null,
            'contact_person_mobile' => $isCompany ? ($validated['contact_person_mobile'] ?? null) : null,
            'contact_person_email' => $isCompany ? ($validated['contact_person_email'] ?? null) : null,
            'property_id' => $validated['property_id'],
            'sla_id' => $validated['sla_ref'] ?? null,
            'unit_id' => $validated['unit_id'],
            'lease_start_date' => $validated['lease_start_date'],
            'lease_years' => $validated['lease_years'],
            'rent_amount' => $validated['rent_amount'],
            'escalation_rate' => $validated['escalation_rate'] ?? 0,
            'frequency' => $validated['frequency'],
            // overdraft_penalty no longer user-editable — leave whatever value is already there.
            'photo' => $photoPath,
            'updated_by' => Auth::id(),
        ]);

        $this->syncTenantRecord($tenant, $validated);
    }

    /**
     * Update bank accounts (delete-recreate pattern)
     */
    private function updateBankAccounts(Request $request, int $tenantId)
    {
        // Delete existing bank details
        TenantBankDetail::where('tenant_id', $tenantId)->delete();

        // Save new bank details
        if ($request->has('bank_accounts')) {
            $isFirst = true;

            foreach ($request->input('bank_accounts') as $bankData) {
                $bankId = $bankData['bank_id'] ?? null;
                $branchId = $bankData['branch_id'] ?? null;
                $accountName = $bankData['account_name'] ?? null;
                $accountNumber = $bankData['account_number'] ?? null;

                // Skip only if the entire row is blank
                if (empty($bankId) && empty($branchId) && empty($accountName) && empty($accountNumber)) {
                    continue;
                }

                TenantBankDetail::create([
                    'tenant_id' => $tenantId,
                    // Cast empty strings to null - bank_id/branch_id are integer columns
                    'bank_id' => $bankId ?: null,
                    'branch_id' => $branchId ?: null,
                    'account_name' => $accountName ?: null,
                    'account_number' => $accountNumber ?: null,
                    'is_default' => $isFirst,
                ]);

                $isFirst = false;
            }
        }
    }

    /**
     * Update other contacts (delete-recreate pattern)
     */
    private function updateOtherContacts(Request $request, int $tenantId)
    {
        // Delete existing contacts
        TenantOtherContact::where('tenant_id', $tenantId)->delete();

        // Save new contacts
        if ($request->has('other_contacts')) {
            foreach ($request->input('other_contacts') as $contactData) {
                if (empty($contactData['name'])) {
                    continue;
                }

                TenantOtherContact::create([
                    'tenant_id' => $tenantId,
                    'names' => $contactData['name'],
                    'email' => $contactData['email'] ?? null,
                    'mobile_number' => $contactData['mobile'] ?? null,
                ]);
            }
        }
    }

    /**
     * Update documents (only add new, don't delete existing)
     */
    private function updateDocuments(Request $request, int $tenantId)
    {
        if ($request->has('tenant_files')) {
            foreach ($request->input('tenant_files') as $index => $documentData) {
                $documentPath = null;

                if ($request->hasFile("tenant_files.{$index}.file")) {
                    $file = $request->file("tenant_files.{$index}.file");
                    if ($file->isValid()) {
                        $documentPath = $file->store('tenants/documents', 'public');
                    }
                }

                if ($documentPath || !empty($documentData['type'])) {
                    ClientDocument::create([
                        'parent_id' => $tenantId,
                        'file_type' => $documentData['type'] ?? null,
                        'documents' => $documentPath,
                    ]);
                }
            }
        }
    }

    /**
     * Update next of kin (delete-recreate pattern)
     */
    private function updateNextOfKin(Request $request, int $tenantId)
    {
        // Delete existing next of kin
        TenantNextOfKin::where('tenant_id', $tenantId)->delete();

        // Save new next of kin
        if ($request->has('next_of_kin')) {
            foreach ($request->input('next_of_kin') as $kinData) {
                if (empty($kinData['names'])) {
                    continue;
                }

                // tenants_nextof_kin only has kin_names + second_name (no separate
                // last_name column) — fold the form's last_name into kin_names so the
                // form itself doesn't need to change.
                $kinNames = trim(($kinData['names'] ?? '') . ' ' . ($kinData['last_name'] ?? ''));

                TenantNextOfKin::create([
                    'tenant_id' => $tenantId,
                    'kin_names' => $kinNames,
                    'second_name' => $kinData['second_name'] ?? null,
                    'mobile_number' => $kinData['mobile'] ?? null,
                    'email_address' => $kinData['email'] ?? null,
                    'relationship' => $kinData['relationship'] ?? null,
                    'national_id' => $kinData['national_id'] ?? null,
                ]);
            }
        }
    }

    /**
     * Save move-in inspection if inspection_date is provided.
     */
    private function saveMoveInInspection(Request $request, Client $tenant, array $validated)
    {
        if (!$request->filled('inspection_date')) {
            return;
        }

        $photoPaths = [];
        if ($request->hasFile('inspection_photos')) {
            foreach ($request->file('inspection_photos') as $file) {
                if ($file->isValid()) {
                    $photoPaths[] = $file->store('move-in-inspections/photos', 'public');
                }
            }
        }

        $videoPaths = [];
        if ($request->hasFile('inspection_videos')) {
            foreach ($request->file('inspection_videos') as $file) {
                if ($file->isValid()) {
                    $videoPaths[] = $file->store('move-in-inspections/videos', 'public');
                }
            }
        }

        MoveInInspection::create([
            'tenant_id'            => $tenant->id,
            'property_id'          => $validated['property_id'],
            'unit_id'              => $validated['unit_id'],
            'sla_id'               => $validated['sla_ref'] ?? null,
            'inspection_date'      => $request->input('inspection_date'),
            'inspector_name'       => $request->input('inspector_name'),
            'walls_condition'      => $request->input('walls_condition')      ?: null,
            'floors_condition'     => $request->input('floors_condition')     ?: null,
            'ceiling_condition'    => $request->input('ceiling_condition')    ?: null,
            'doors_condition'      => $request->input('doors_condition')      ?: null,
            'windows_condition'    => $request->input('windows_condition')    ?: null,
            'plumbing_condition'   => $request->input('plumbing_condition')   ?: null,
            'electrical_condition' => $request->input('electrical_condition') ?: null,
            'overall_condition'    => $request->input('overall_condition')    ?: null,
            'notes'                => $request->input('inspection_notes'),
            'photo_paths'          => $photoPaths ?: null,
            'video_paths'          => $videoPaths ?: null,
            'conducted_by'         => Auth::id(),
        ]);
    }

    /**
     * Delete a tenant document via AJAX
     */
    public function deleteDocument($id)
    {
        try {
            $document = ClientDocument::findOrFail($id);

            // Delete file from storage
            if ($document->documents && Storage::disk('public')->exists($document->documents)) {
                Storage::disk('public')->delete($document->documents);
            }

            $document->delete();

            return response()->json(['success' => true, 'message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting tenant document: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete document'], 500);
        }
    }

    /**
     * Tenant Arrears page — every invoice still carrying a balance
     * (sys_pm_debtors_invoices.current_balance > 0), with tenant/property/unit
     * names joined in. Data itself loads via arrearsData() (AJAX); this just
     * renders the shell.
     */
    public function arrears()
    {
        try {
            return view('property.tenants.arrears');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant arrears page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenant arrears page');
        }
    }

    /**
     * AJAX data for the Arrears table. Same shape/conventions as
     * ClientsController::getTenantsListingData(): per-column ILIKE filters
     * (3+ chars), capped at 1000 rows. Extra here: an optional date_due
     * range, capped at 60 days — this table has 1M+ rows, so an unbounded
     * range on a page with no default filter would be a real cost.
     */
    public function arrearsData(Request $request)
    {
        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if (($startDate && !$endDate) || ($endDate && !$startDate)) {
                return response()->json(['success' => false, 'message' => 'Provide both a start and end date, or neither.'], 422);
            }

            if ($startDate && $endDate) {
                $start = \Carbon\Carbon::parse($startDate)->startOfDay();
                $end = \Carbon\Carbon::parse($endDate)->endOfDay();

                if ($end->lt($start)) {
                    return response()->json(['success' => false, 'message' => 'End date must be on or after the start date.'], 422);
                }
                if ($start->diffInDays($end) > 60) {
                    return response()->json(['success' => false, 'message' => 'Date range is limited to 60 days — please narrow it.'], 422);
                }
            }

            $query = DB::table('sys_pm_debtors_invoices as inv')
                ->join('sys_pm_clients as t', 't.id', '=', 'inv.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'inv.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'inv.unit_id')
                ->where('inv.current_balance', '>', 0);

            // Scope to the logged-in user's assigned properties/zones, unless their role
            // is global — mirrors Client::boot()'s "user_clients_properties" global scope
            // (field_officer/zone_manager/zone_supervisor by staff, or zone assignment by
            // user) so this portfolio-wide report can't leak arrears outside a staff
            // member's own patch. The invoice row already carries its own copies of
            // field_officer_id/zone_manager_id/zone_supervisor_id/zone_id, so this scopes
            // directly off inv.* without an extra join through leases.
            $userId = Auth::id();
            $isGlobal = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.is_global', true)
                ->exists();

            if (!$isGlobal) {
                $staffId = DB::table('sys_user_staff_links')->where('user_id', $userId)->value('staff_id');

                if (!$staffId) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->leftJoin('sys_set_zones as z', 'z.id', '=', 'inv.zone_id')
                        ->where(function ($q) use ($staffId, $userId) {
                            $q->where('inv.field_officer_id', $staffId)
                                ->orWhere('inv.zone_manager_id', $staffId)
                                ->orWhere('inv.zone_supervisor_id', $staffId)
                                ->orWhere('z.zone_manager_id', $userId)
                                ->orWhere('z.zone_supervisor_id', $userId);
                        });
                }
            }

            if ($startDate && $endDate) {
                $query->whereBetween('inv.date_due', [$start, $end]);
            }

            // Column filters (only apply when 3+ characters) — search by name, not raw id.
            if ($request->filled('filter_tenant') && strlen($request->filter_tenant) >= 3) {
                $query->where('t.names', 'ILIKE', '%' . $request->filter_tenant . '%');
            }
            if ($request->filled('filter_property') && strlen($request->filter_property) >= 3) {
                $query->where('p.property_name', 'ILIKE', '%' . $request->filter_property . '%');
            }
            if ($request->filled('filter_unit') && strlen($request->filter_unit) >= 3) {
                $query->where('u.unit_code', 'ILIKE', '%' . $request->filter_unit . '%');
            }
            if ($request->filled('filter_reference') && strlen($request->filter_reference) >= 3) {
                $query->where('inv.reference_number', 'ILIKE', '%' . $request->filter_reference . '%');
            }

            // True total across the whole filtered set, not just the 1000 rows returned —
            // computed before the limit so it reflects everything matching the filters.
            $totalBalance = (clone $query)->sum('inv.current_balance');

            $rows = $query->select(
                'inv.id',
                'inv.tenant_id',
                'inv.property_id',
                'inv.unit_id',
                'inv.total_amount',
                'inv.current_balance',
                'inv.reference_number',
                'inv.return_type_id',
                'inv.date_due',
                't.names as tenant_name',
                't.mobile_number',
                'p.property_name',
                'u.unit_code'
            )
                ->orderByDesc('inv.current_balance')
                ->limit(1000)
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'tenant_id' => $row->tenant_id,
                        'property_id' => $row->property_id,
                        'unit_id' => $row->unit_id,
                        'tenant_name' => $row->tenant_name ?? '-',
                        'mobile_number' => $row->mobile_number ?? '-',
                        'property_name' => $row->property_name ?? '-',
                        'unit_code' => $row->unit_code ?? '-',
                        'reference_number' => $row->reference_number ?? '-',
                        'return_type_id' => $row->return_type_id ?? '-',
                        'date_due' => $row->date_due ? \Carbon\Carbon::parse($row->date_due)->format('d M Y') : '-',
                        'total_amount' => number_format($row->total_amount ?? 0, 2),
                        'current_balance' => number_format($row->current_balance ?? 0, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'count' => $rows->count(),
                'total_balance' => number_format($totalBalance ?? 0, 2),
                'data' => $rows,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching tenant arrears data: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch arrears'], 500);
        }
    }
}

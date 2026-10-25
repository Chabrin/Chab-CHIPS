<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\InvoiceableItem;
use App\Models\ServiceAgreement;
use App\Models\SlaInvoiceableItem;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\ServiceAgreementDocument;
use App\Traits\ClientSearchTrait;

class SLAController extends Controller
{
    use ClientSearchTrait;
    /**
     * Display a listing of service agreements
     */
    public function index()
    {
        try {
            return view('property.properties.sla.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load service agreements');
        }
    }

    /**
     * Get all service agreements (AJAX)
     */
    public function getAllSLA()
    {
        try {
            $can_view_property = Auth::user()->isAbleTo('read_properties');
            $can_view_client = Auth::user()->isAbleTo('read_clients');
            $can_edit_sla = Auth::user()->isAbleTo('update_service_agreements');
            $can_view_sla = Auth::user()->isAbleTo('read_service_agreements');

            $slas = ServiceAgreement::with(['property', 'client'])
                ->select([
                    'id',
                    'client_d',
                    'property_id',
                    'reference_number',
                    'date_start',
                    'date_end',
                    'commission_percentage',
                    'current_status_id',
                    'vat',
                    'vat_per',
                    'flat_rate_vat',
                    'auto_renewal'
                ])
                ->orderBy('date_start', 'desc')
                ->get()
                ->map(function ($sla) use ($can_view_property, $can_view_client, $can_edit_sla, $can_view_sla) {
                    return [
                        'id' => $sla->id,
                        'encrypted_id' => Crypt::encrypt($sla->id),
                        'encrypted_property_id' => Crypt::encrypt($sla->property_id),
                        'encrypted_client_id' => Crypt::encrypt($sla->client_d),
                        'reference_number' => $sla->reference_number ?? '-',
                        'property' => $sla->property,
                        'client' => $sla->client,
                        'date_start' => $sla->date_start,
                        'date_end' => $sla->date_end,
                        'commission_percentage' => $sla->commission_percentage,
                        'current_status_id' => $sla->current_status_id,
                        'vat' => $sla->vat,
                        'vat_per' => $sla->vat_per,
                        'flat_rate_vat' => $sla->flat_rate_vat,
                        'auto_renewal' => $sla->auto_renewal,
                        'can_view_property' => $can_view_property,
                        'can_view_client' => $can_view_client,
                        'can_edit_sla' => $can_edit_sla,
                        'can_view_sla' => $can_view_sla,
                    ];
                });
    
            return response()->json(['data' => $slas]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading all SLAs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load service agreements'], 500);
        }
    }


   /**
     * Show the form for creating a new service agreement
     */
    public function create(Request $request)
    {
        try {
            $clientId = null;
            $client = null;
            $properties = [];

            // If client parameter is provided, decrypt it and load related data
            if ($request->has('client')) {
                $clientId = Crypt::decrypt($request->client);
                $client = Client::findOrFail($clientId);
                $properties = Property::where('client_id', $client->id)->get();
            }

            return view('property.properties.sla.create', compact('properties', 'client'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Get SLA for a property (AJAX)
     */
    public function getSLAForProperty($propertyId)
    {
        try {
            // orderByRaw NULLS LAST: plain orderBy() puts a NULL date_start first on
            // Postgres, which would make an undated SLA outrank a real, later-dated
            // one — must match resolveSlaId()'s ordering exactly (TenantRecordHelperTrait)
            // so "the SLA whose items we show" and "the SLA the lease actually gets"
            // can't disagree.
            $sla = ServiceAgreement::where('property_id', $propertyId)
                // ->where('current_status_id', 2)
                ->orderByRaw('date_start DESC NULLS LAST')
                ->get([
                    'id', 'reference_number', 'date_start', 'date_end', 'commission_percentage', 'move_in_inspection_required',
                    'rent_selected',
                    'electricity_bill_selected', 'electricity_bill_cost',
                    'water_bill_selected', 'water_bill_cost',
                    'service_charges_selected', 'service_charges_cost',
                ]);

            // billable_items: only the additional (non-Rent) items the property's
            // most-recent SLA has enabled — same ordering resolveSlaId() uses, so
            // "the SLA whose items we show" and "the SLA the lease actually gets"
            // can't disagree. Rent is deliberately excluded: it's already billed
            // automatically from the unit's own rent_amount elsewhere
            // (InvoicingController), not through the lease's invoiceable items.
            $billableItems = [];
            $mostRecent = $sla->first();
            if ($mostRecent) {
                $candidates = [
                    'electricity_bill_selected' => ['name' => 'Electricity Bill', 'cost' => $mostRecent->electricity_bill_cost],
                    'water_bill_selected' => ['name' => 'Water Bill', 'cost' => $mostRecent->water_bill_cost],
                    'service_charges_selected' => ['name' => 'Service Charges', 'cost' => $mostRecent->service_charges_cost],
                ];

                $enabledNames = collect($candidates)
                    ->filter(fn ($c, $flag) => (bool) $mostRecent->{$flag})
                    ->pluck('name');

                if ($enabledNames->isNotEmpty()) {
                    $itemIds = InvoiceableItem::whereIn('item_name', $enabledNames)->pluck('id', 'item_name');

                    foreach ($candidates as $flag => $c) {
                        if ($mostRecent->{$flag} && $itemIds->has($c['name'])) {
                            $billableItems[$itemIds->get($c['name'])] = [
                                'item_id' => $itemIds->get($c['name']),
                                'name' => $c['name'],
                                'cost' => $c['cost'],
                                'percentage_of_rent' => null,
                            ];
                        }
                    }
                }

                // Items picked via the "Tenant Invoiceable Items" section
                // (cb_sla_invoiceable_items) — keyed by item_id so these take
                // precedence over a same-item legacy flag above, if both exist.
                $pickedItems = SlaInvoiceableItem::where('sla_id', $mostRecent->id)
                    ->with('item:id,item_name')
                    ->get();

                foreach ($pickedItems as $picked) {
                    $billableItems[$picked->item_id] = [
                        'item_id' => $picked->item_id,
                        'name' => $picked->item->item_name ?? null,
                        'cost' => $picked->amount,
                        'percentage_of_rent' => $picked->percentage_of_rent,
                    ];
                }
            }

            return response()->json(['sla' => $sla, 'billable_items' => array_values($billableItems)]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA for property: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load SLA'], 500);
        }
    }

    /**
     * Get invoiceable items (AJAX)
     */
    public function getInvoiceableItems()
    {
        try {
            $items = InvoiceableItem::orderBy('item_name', 'asc')
            ->get();

            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading invoiceable items: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load items'], 500);
        }
    }

    /**
     * Tenant-facing invoiceable items (applies_to BOTH or TENANT) for the SLA
     * create/edit form's "Tenant Invoiceable Items" picker — item name,
     * billing code, and the item's default billing cycle name.
     */
    public function getTenantInvoiceableItems()
    {
        try {
            $items = InvoiceableItem::leftJoin('sys_set_invoiceable_items_cycles as c', 'c.id', '=', 'sys_set_invoiceable_items.default_cycle_id')
                ->whereIn('applies_to', ['BOTH', 'TENANT'])
                ->orderBy('item_name')
                ->get([
                    'sys_set_invoiceable_items.id',
                    'sys_set_invoiceable_items.item_name',
                    'sys_set_invoiceable_items.billing_code',
                    'c.cycle_name as default_cycle_name',
                ]);

            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant invoiceable items: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load items'], 500);
        }
    }

    /**
     * The property's SLA that's still blocking a new one from being created for
     * it, if any. "Current" is date-based: date_end null or >= today. SLAs
     * always get a computed date_end (date_start + duration_years) through the
     * normal create/edit forms, so null is defensive-only — treated as still
     * current rather than assuming an undated SLA has ended. Deliberately not
     * current_status_id-based: unlike leases, its meaning for SLAs hasn't been
     * verified, and "once the old one's date_end has passed" is unambiguous on
     * its own. Shared by store()/update()/checkCurrentSla() so the definition
     * can't drift between the live check and the actual server-side block.
     */
    private function findCurrentSlaForProperty(int $propertyId, ?int $excludeId = null): ?ServiceAgreement
    {
        return ServiceAgreement::where('property_id', $propertyId)
            ->where(function ($q) {
                $q->whereNull('date_end')->orWhereDate('date_end', '>=', now());
            })
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    /**
     * date_end on ServiceAgreement isn't cast to a Carbon instance (unlike
     * LeaseAgreement's date_end) — it comes back from Eloquent as a plain
     * string, so it needs parsing before it can be formatted. Shared so this
     * doesn't get re-broken in more than one place.
     */
    private function formatSlaDateEnd(?string $dateEnd): ?string
    {
        return $dateEnd ? \Carbon\Carbon::parse($dateEnd)->format('d M Y') : null;
    }

    /**
     * Live check (AJAX) for the create/edit forms — warns as soon as a property
     * is picked, before the team fills out the rest of the form only to be
     * rejected on submit. Mirrors the shape of TenantsController::
     * checkIdentifierExists() (the National ID duplicate check built earlier),
     * though this one is a hard block, not an overridable warning.
     */
    public function checkCurrentSla(Request $request)
    {
        $request->validate([
            'property_id' => 'required|integer',
            'exclude_id' => 'nullable|integer',
        ]);

        $existing = $this->findCurrentSlaForProperty(
            (int) $request->input('property_id'),
            $request->filled('exclude_id') ? (int) $request->input('exclude_id') : null
        );

        // A renewal is any new SLA on a property that already had one that was not rejected.
        // The create form uses this to leave the registration fee off by default.
        $isRenewal = ServiceAgreement::where('property_id', (int) $request->input('property_id'))
            ->where(fn ($q) => $q->whereNull('current_status_id')->orWhere('current_status_id', '<>', 41))
            ->when($request->filled('exclude_id'), fn ($q) => $q->where('id', '!=', (int) $request->input('exclude_id')))
            ->exists();

        return response()->json([
            'exists' => (bool) $existing,
            'is_renewal' => $isRenewal,
            'reference_number' => $existing->reference_number ?? null,
            'date_end' => $existing ? $this->formatSlaDateEnd($existing->date_end) : null,
        ]);
    }

    /**
     * Store a newly created service agreement
     */
    public function store(Request $request)
    {
        $documentPaths = [];

        try {
            // Validate the request
            $validated = $request->validate([
                'client_id'                    => 'required|exists:sys_pm_clients,id',
                'property_id'                  => 'required|exists:sys_pm_properties,id',
                'commission_percentage'         => 'required|numeric|min:0|max:100',
                'vat_able'                     => 'required|in:Yes,No,Flat Rate',
                'vat_percentage'               => 'nullable|numeric|min:0|max:100',
                'flat_rate_vat'                => 'nullable|numeric|min:0',
                'auto_renewal'                 => 'required',
                'payment_date'                 => 'required|integer|min:1|max:31',
                'date_start'                   => 'nullable|date',
                'duration_years'               => 'nullable|integer|min:1',
                'move_in_inspection_required'  => 'nullable|boolean',
                'charge_registration_fee'      => 'nullable|boolean',
                'returns_combination'          => 'nullable|in:rent_separate_rest_combined,each_separate,all_combined',
                'sla_documents.*'              => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'rent_selected'                => 'nullable|boolean',
                'electricity_bill_selected'    => 'nullable|boolean',
                'electricity_bill_cost'        => 'nullable|numeric|min:0',
                'water_bill_selected'          => 'nullable|boolean',
                'water_bill_cost'              => 'nullable|numeric|min:0',
                'service_charges_selected'     => 'nullable|boolean',
                'service_charges_cost'         => 'nullable|numeric|min:0',
                'items'                        => 'nullable|array',
                'items.*.selected'             => 'nullable|boolean',
                'items.*.choice'               => 'nullable|in:amount,percentage',
                'items.*.amount'               => 'nullable|numeric|min:0',
                'items.*.percentage_of_rent'   => 'nullable|numeric|min:0',
            ]);

            $currentSla = $this->findCurrentSlaForProperty((int) $validated['property_id']);
            if ($currentSla) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'property_id' => ["This property already has a current SLA ({$currentSla->reference_number}, ends " . ($this->formatSlaDateEnd($currentSla->date_end) ?? 'never') . ") — edit it instead, or wait until it ends to create a new one."],
                ]);
            }

            // Calculate end date — only when both a start date and a duration were given
            $endDate = null;
            if (!empty($validated['date_start']) && !empty($validated['duration_years'])) {
                $startDate = new \DateTime($validated['date_start']);
                $endDate = clone $startDate;
                $endDate->modify('+' . $validated['duration_years'] . ' years');
            }

            // Handle file uploads
            if ($request->hasFile('sla_documents')) {
                foreach ($request->file('sla_documents') as $document) {
                    $path = $document->store('sla_documents', 'public');
                    $documentPaths[] = $path;
                }
            }

            // Get property to retrieve zone_id. Bypass the zone-assignment scope here —
            // property_id was already validated as existing above (unscoped `exists` rule),
            // so re-fetching with the restrictive scope would wrongly 404 a property that
            // has no sys_pm_zones_assignments row (a known data gap on some properties).
            $property = Property::withoutGlobalScope('user_properties')->findOrFail($validated['property_id']);
            Log::info('Property found for SLA creation: ' . $property->id);

            // Determine VAT value based on vat_able selection
            $vatValue = 'No';
            if ($validated['vat_able'] === 'Yes') {
                $vatValue = 'Yes';
            } elseif ($validated['vat_able'] === 'Flat Rate') {
                $vatValue = 'Flat Rate';
            }

            Log::info('Creating Service Agreement for Client ID: ' . $validated['client_id'] . ', Property ID: ' . $validated['property_id']);

            // The SLA, its documents and its tenant billing items are saved together or not at all,
            // so a failure part-way cannot leave a half-created SLA behind.
            $serviceAgreement = DB::transaction(function () use ($validated, $property, $vatValue, $endDate, $documentPaths) {
                // Create the Service Agreement
                $serviceAgreement = ServiceAgreement::create([
                    'date_time'                   => now(),
                    'client_d'                    => $validated['client_id'],
                    'property_id'                 => $validated['property_id'],
                    'zone_id'                     => $property->zone_id ?? null,
                    'commission_percentage'        => $validated['commission_percentage'],
                    'vat'                         => $vatValue,
                    'vat_per'                     => $validated['vat_percentage'] ?? 0,
                    'flat_rate_vat'               => $validated['flat_rate_vat'] ?? 0,
                    'date_start'                  => $validated['date_start'] ?? null,
                    'date_end'                    => $endDate ? $endDate->format('Y-m-d') : null,
                    'years'                       => $validated['duration_years'] ?? null,
                    'landlord_payment_date'       => $validated['payment_date'],
                    'auto_renewal'                => $validated['auto_renewal'],
                    'created_by_id'               => Auth::id(),
                    'current_status_id'           => 1, // Pending — awaiting maker-checker approval
                    'reference_number'            => null,
                    'move_in_inspection_required' => !empty($validated['move_in_inspection_required']),
                    'charge_registration_fee'     => !empty($validated['charge_registration_fee']),
                    'returns_combination'         => $validated['returns_combination'] ?? 'each_separate',
                    'rent_selected'               => !empty($validated['rent_selected']),
                    'electricity_bill_selected'   => !empty($validated['electricity_bill_selected']),
                    'electricity_bill_cost'       => !empty($validated['electricity_bill_selected']) ? ($validated['electricity_bill_cost'] ?? 0) : null,
                    'water_bill_selected'         => !empty($validated['water_bill_selected']),
                    'water_bill_cost'             => !empty($validated['water_bill_selected']) ? ($validated['water_bill_cost'] ?? 0) : null,
                    'service_charges_selected'    => !empty($validated['service_charges_selected']),
                    'service_charges_cost'        => !empty($validated['service_charges_selected']) ? ($validated['service_charges_cost'] ?? 0) : null,
                ]);

                Log::info('Service Agreement created with ID: ' . $serviceAgreement->id);

                // Generate and update reference number after getting the ID
                $serviceAgreement->reference_number = $this->generateReferenceNumber(
                    $property->property_name,
                    $serviceAgreement->id
                );
                $serviceAgreement->save();

                //store uploaded documents info
                if (!empty($documentPaths)) {
                    foreach ($documentPaths as $docPath) {
                        $serviceAgreement->serviceAgreementDocuments()->create([
                            'sla_documents' => $docPath,
                            'params' => null,
                        ]);
                    }
                }

                Log::info('SLA Documents stored for SLA ID: ' . $serviceAgreement->id);

                // Store selected Tenant Invoiceable Items — amount and
                // percentage_of_rent are both optional and mutually exclusive
                // (driven by the row's "Amount" / "% of Rent" radio on the form).
                foreach ($validated['items'] ?? [] as $itemId => $itemData) {
                    if (empty($itemData['selected'])) {
                        continue;
                    }

                    $choice = $itemData['choice'] ?? null;

                    SlaInvoiceableItem::create([
                        'sla_id' => $serviceAgreement->id,
                        'item_id' => (int) $itemId,
                        'amount' => $choice === 'amount' ? ($itemData['amount'] ?? null) : null,
                        'percentage_of_rent' => $choice === 'percentage' ? ($itemData['percentage_of_rent'] ?? null) : null,
                    ]);
                }

                Log::info('Tenant Invoiceable Items stored for SLA ID: ' . $serviceAgreement->id);

                return $serviceAgreement;
            });

            Log::info('Service Agreement created successfully', ['sla_id' => $serviceAgreement->id]);

            return redirect()
                ->route('property.clients.show', Crypt::encrypt($validated['client_id']))
                ->with('success', 'Service Agreement created successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            if ($documentPaths) {
                Storage::disk('public')->delete($documentPaths);
            }
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating service agreement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create service agreement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Generate a unique reference number for the SLA
     * Format: {PropertyDescription}-{5-digit-sequence}
     * Example: Villa Mara-00025
     * 
     * @param string $propertyDescription
     * @param int $serviceAgreementId
     * @return string
     */
    protected function generateReferenceNumber($propertyDescription, $serviceAgreementId)
    {
        // Format: PropertyDescription-00025 (5 digits, zero-padded)
        return sprintf('%s-%05d', $propertyDescription, $serviceAgreementId);
    }

    /**
     * Get service agreements for a specific client (AJAX)
     */
    public function getSLA($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $slas = ServiceAgreement::with(['property'])
                ->select([
                    'id',
                    'client_d',
                    'property_id',
                    'reference_number',
                    'date_start',
                    'date_end',
                    'commission_percentage',
                    'current_status_id',
                    'vat',
                    'vat_per',
                    'auto_renewal'
                ])
                ->where('client_d', $clientId)
                ->orderBy('date_start', 'desc')
                ->get()
                ->map(function ($sla) {
                    return [
                        'id' => $sla->id,
                        'encrypted_id' => Crypt::encrypt($sla->id),
                        'reference_number' => $sla->reference_number ?? '-',
                        'property_name' => $sla->property->property_name ?? '-',
                        'date_start' => $sla->date_start,
                        'date_end' => $sla->date_end,
                        'commission_percentage' => $sla->commission_percentage,
                        'current_status_id' => $sla->current_status_id,
                        'vat' => $sla->vat,
                        'auto_renewal' => $sla->auto_renewal,
                    ];
                });
    
            return response()->json(['data' => $slas]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load service agreements'], 500);
        }
    }

    /**
     * Display the specified service agreement
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $sla = ServiceAgreement::with([
                'client',
                'property',
                'zone',
                'createdBy',
                'serviceAgreementDocuments',
            ])->findOrFail($id);

            $returnsCombinationLabels = [
                'rent_separate_rest_combined' => 'Rent (Electricity Bill, Water Bill, Service Charges)',
                'each_separate' => 'Rent, Electricity Bill, Water Bill, Service Charges (each separate)',
                'all_combined' => 'Rent, Electricity Bill, Water Bill, Service Charges (all combined)',
            ];

            $documents = $sla->serviceAgreementDocuments->map(function ($document) {
                $exists = $document->sla_documents && Storage::disk('public')->exists($document->sla_documents);
                return [
                    'name' => $document->sla_documents ? basename($document->sla_documents) : '-',
                    'url' => $exists ? Storage::url($document->sla_documents) : null,
                ];
            })->values();

            // Tenant Invoiceable Items for the view modal — the legacy fixed
            // flags (if any are still set on older SLAs) plus everything
            // picked via the dynamic items table (cb_sla_invoiceable_items).
            $tenantInvoiceableItems = [];
            if ($sla->electricity_bill_selected) {
                $tenantInvoiceableItems[] = ['name' => 'Electricity Bill', 'amount' => $sla->electricity_bill_cost, 'percentage_of_rent' => null];
            }
            if ($sla->water_bill_selected) {
                $tenantInvoiceableItems[] = ['name' => 'Water Bill', 'amount' => $sla->water_bill_cost, 'percentage_of_rent' => null];
            }
            if ($sla->service_charges_selected) {
                $tenantInvoiceableItems[] = ['name' => 'Service Charges', 'amount' => $sla->service_charges_cost, 'percentage_of_rent' => null];
            }
            foreach (SlaInvoiceableItem::where('sla_id', $sla->id)->with('item:id,item_name')->get() as $picked) {
                $tenantInvoiceableItems[] = [
                    'name' => $picked->item->item_name ?? '-',
                    'amount' => $picked->amount,
                    'percentage_of_rent' => $picked->percentage_of_rent,
                ];
            }

            return response()->json([
                'reference_number' => $sla->reference_number ?? '-',
                'date_start' => $sla->date_start ? \Carbon\Carbon::parse($sla->date_start)->format('Y-m-d') : '-',
                'date_end' => $sla->date_end ? \Carbon\Carbon::parse($sla->date_end)->format('Y-m-d') : '-',
                'is_expired' => $sla->date_end ? \Carbon\Carbon::parse($sla->date_end)->isPast() : false,
                'current_status_id' => $sla->current_status_id,
                'years' => $sla->years ?? '-',
                'auto_renewal' => $sla->auto_renewal ?? 'No',
                'client_name' => $sla->client->names ?? '-',
                'client_reference' => $sla->client->reference_number ?? '-',
                'property_name' => $sla->property->property_name ?? '-',
                'property_reference' => $sla->property->reference_number ?? '-',
                'zone_name' => $sla->zone->zone_name ?? '-',
                'commission_percentage' => $sla->commission_percentage ?? 0,
                'vat' => $sla->vat ?? 'No',
                'vat_per' => $sla->vat_per ?? 0,
                'flat_rate_vat' => $sla->flat_rate_vat ?? 0,
                'charge_registration_fee' => (bool) $sla->charge_registration_fee,
                'returns_combination_label' => $returnsCombinationLabels[$sla->returns_combination] ?? '-',
                'billable_items' => [
                    ['name' => 'Rent', 'selected' => (bool) $sla->rent_selected, 'cost' => null],
                    ['name' => 'Electricity Bill', 'selected' => (bool) $sla->electricity_bill_selected, 'cost' => $sla->electricity_bill_cost],
                    ['name' => 'Water Bill', 'selected' => (bool) $sla->water_bill_selected, 'cost' => $sla->water_bill_cost],
                    ['name' => 'Service Charges', 'selected' => (bool) $sla->service_charges_selected, 'cost' => $sla->service_charges_cost],
                ],
                'tenant_invoiceable_items' => $tenantInvoiceableItems,
                'documents' => $documents,
                'created_by' => $sla->createdBy->name ?? '-',
                'date_time' => $sla->date_time ? $sla->date_time->format('Y-m-d H:i') : '-',
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA details: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load service agreement details'], 500);
        }
    }

    /**
     * Show the form for editing a service agreement
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $sla = ServiceAgreement::with([
                'property',
                'client',
                'serviceAgreementDocuments',
            ])->findOrFail($id);

            $client = $sla->client;
            $properties = Property::where('client_id', $client->id)->get();

            $existingInvoiceableItems = SlaInvoiceableItem::where('sla_id', $sla->id)
                ->with('item.cycle')
                ->get()
                ->map(function ($row) {
                    return [
                        'item_id' => $row->item_id,
                        'item_name' => $row->item->item_name ?? '',
                        'billing_code' => $row->item->billing_code ?? '',
                        'default_cycle_name' => $row->item->cycle->cycle_name ?? null,
                        'choice' => $row->amount !== null ? 'amount' : ($row->percentage_of_rent !== null ? 'percentage' : null),
                        'amount' => $row->amount,
                        'percentage_of_rent' => $row->percentage_of_rent,
                    ];
                })
                ->values();

            return view('property.properties.sla.edit', compact('sla', 'properties', 'client', 'existingInvoiceableItems'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA edit form: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Failed to load service agreement');
        }
    }
    
    /**
     * Update the specified service agreement
     */
    public function update(Request $request, $encryptedId)
    {
        $storedPaths = [];

        try {
            $id = Crypt::decrypt($encryptedId);
            $serviceAgreement = ServiceAgreement::findOrFail($id);
    
            // Validate the request
            $validated = $request->validate([
                'client_id'                   => 'required|exists:sys_pm_clients,id',
                'property_id'                 => 'required|exists:sys_pm_properties,id',
                'commission_percentage'        => 'required|numeric|min:0|max:100',
                'vat_able'                    => 'required|in:Yes,No,Flat Rate',
                'vat_percentage'              => 'nullable|numeric|min:0|max:100',
                'flat_rate_vat'               => 'nullable|numeric|min:0',
                'auto_renewal'                => 'required|in:Yes,No',
                'payment_date'                => 'required|integer|min:1|max:31',
                'date_start'                  => 'nullable|date',
                'duration_years'              => 'nullable|integer|min:1',
                'move_in_inspection_required' => 'nullable|boolean',
                'charge_registration_fee'     => 'nullable|boolean',
                'returns_combination'         => 'nullable|in:rent_separate_rest_combined,each_separate,all_combined',
                'sla_documents.*'             => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'rent_selected'               => 'nullable|boolean',
                'electricity_bill_selected'   => 'nullable|boolean',
                'electricity_bill_cost'       => 'nullable|numeric|min:0',
                'water_bill_selected'         => 'nullable|boolean',
                'water_bill_cost'             => 'nullable|numeric|min:0',
                'service_charges_selected'    => 'nullable|boolean',
                'service_charges_cost'        => 'nullable|numeric|min:0',
                'items'                       => 'nullable|array',
                'items.*.selected'            => 'nullable|boolean',
                'items.*.choice'              => 'nullable|in:amount,percentage',
                'items.*.amount'              => 'nullable|numeric|min:0',
                'items.*.percentage_of_rent'  => 'nullable|numeric|min:0',
            ]);

            $currentSla = $this->findCurrentSlaForProperty((int) $validated['property_id'], $serviceAgreement->id);
            if ($currentSla) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'property_id' => ["This property already has a current SLA ({$currentSla->reference_number}, ends " . ($this->formatSlaDateEnd($currentSla->date_end) ?? 'never') . ") — edit it instead, or wait until it ends to create a new one."],
                ]);
            }

            // Calculate end date — only when both a start date and a duration were given
            $endDate = null;
            if (!empty($validated['date_start']) && !empty($validated['duration_years'])) {
                $startDate = new \DateTime($validated['date_start']);
                $endDate = clone $startDate;
                $endDate->modify('+' . $validated['duration_years'] . ' years');
            }

            // Get property to retrieve zone_id. Bypass the zone-assignment scope here —
            // property_id was already validated as existing above (unscoped `exists` rule),
            // so re-fetching with the restrictive scope would wrongly 404 a property that
            // has no sys_pm_zones_assignments row (a known data gap on some properties).
            $property = Property::withoutGlobalScope('user_properties')->findOrFail($validated['property_id']);
    
            // Determine VAT value based on vat_able selection
            $vatValue = 'No';
            if ($validated['vat_able'] === 'Yes') {
                $vatValue = 'Yes';
            } elseif ($validated['vat_able'] === 'Flat Rate') {
                $vatValue = 'Flat Rate';
            }
    
            // New documents, the SLA fields and the tenant billing items are saved together or not at all,
            // so a failure part-way cannot drop the existing billing items or leave a half-edited SLA.
            DB::transaction(function () use ($request, $serviceAgreement, $validated, $property, $vatValue, $endDate, &$storedPaths) {
                // Handle file uploads (new documents)
                if ($request->hasFile('sla_documents')) {
                    foreach ($request->file('sla_documents') as $document) {
                        $path = $document->store('sla_documents', 'public');
                        $storedPaths[] = $path;
                        $serviceAgreement->serviceAgreementDocuments()->create([
                            'sla_documents' => $path,
                            'params' => null,
                        ]);
                    }
                }

                // Update the Service Agreement
                $serviceAgreement->update([
                    'client_d'                    => $validated['client_id'],
                    'property_id'                 => $validated['property_id'],
                    'zone_id'                     => $property->zone_id ?? null,
                    'commission_percentage'        => $validated['commission_percentage'],
                    'vat'                         => $vatValue,
                    'vat_per'                     => $validated['vat_percentage'] ?? 0,
                    'flat_rate_vat'               => $validated['flat_rate_vat'] ?? 0,
                    'date_start'                  => $validated['date_start'] ?? null,
                    'date_end'                    => $endDate ? $endDate->format('Y-m-d') : null,
                    'years'                       => $validated['duration_years'] ?? null,
                    'landlord_payment_date'       => $validated['payment_date'],
                    'auto_renewal'                => $validated['auto_renewal'],
                    'move_in_inspection_required' => !empty($validated['move_in_inspection_required']),
                    'charge_registration_fee'     => !empty($validated['charge_registration_fee']),
                    'returns_combination'         => $validated['returns_combination'] ?? $serviceAgreement->returns_combination,
                    'rent_selected'               => !empty($validated['rent_selected']),
                    'electricity_bill_selected'   => !empty($validated['electricity_bill_selected']),
                    'electricity_bill_cost'       => !empty($validated['electricity_bill_selected']) ? ($validated['electricity_bill_cost'] ?? 0) : null,
                    'water_bill_selected'         => !empty($validated['water_bill_selected']),
                    'water_bill_cost'             => !empty($validated['water_bill_selected']) ? ($validated['water_bill_cost'] ?? 0) : null,
                    'service_charges_selected'    => !empty($validated['service_charges_selected']),
                    'service_charges_cost'        => !empty($validated['service_charges_selected']) ? ($validated['service_charges_cost'] ?? 0) : null,
                ]);

                // Replace the Tenant Invoiceable Items selection with whatever the
                // form submitted — the staged table on the edit page is the full,
                // authoritative list each time (same shape store() writes).
                SlaInvoiceableItem::where('sla_id', $serviceAgreement->id)->delete();
                foreach ($validated['items'] ?? [] as $itemId => $itemData) {
                    if (empty($itemData['selected'])) {
                        continue;
                    }

                    $choice = $itemData['choice'] ?? null;

                    SlaInvoiceableItem::create([
                        'sla_id' => $serviceAgreement->id,
                        'item_id' => (int) $itemId,
                        'amount' => $choice === 'amount' ? ($itemData['amount'] ?? null) : null,
                        'percentage_of_rent' => $choice === 'percentage' ? ($itemData['percentage_of_rent'] ?? null) : null,
                    ]);
                }
            });

            Log::info('Service Agreement updated successfully', [
                'sla_id' => $serviceAgreement->id,
                'updated_by' => Auth::id()
            ]);
    
            return redirect()
                ->route('property.clients.show', Crypt::encrypt($validated['client_id']))
                ->with('success', 'Service Agreement updated successfully');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            if ($storedPaths) {
                Storage::disk('public')->delete($storedPaths);
            }
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating service agreement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update service agreement: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Mass management page for move-in inspection settings.
     * GET /service-agreements/move-in-inspection-settings
     */
    public function moveInInspectionSettings()
    {
        try {
            $slas = ServiceAgreement::with(['property', 'client'])
                ->select(['id', 'reference_number', 'property_id', 'client_d', 'current_status_id', 'date_start', 'date_end', 'move_in_inspection_required'])
                ->orderByRaw('current_status_id = 5 DESC')
                ->orderBy('date_start', 'desc')
                ->get();

            $stats = [
                'total'    => $slas->count(),
                'required' => $slas->where('move_in_inspection_required', true)->count(),
                'optional' => $slas->where('move_in_inspection_required', false)->count(),
            ];

            return view('property.properties.sla.move-in-inspection-settings', compact('slas', 'stats'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading move-in inspection settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load settings');
        }
    }

    /**
     * Toggle move-in inspection flag for a single SLA via AJAX.
     * POST /service-agreements/move-in-inspection-flag
     */
    public function updateMoveInInspectionFlag(Request $request)
    {
        try {
            $request->validate([
                'sla_id'   => 'required|exists:sys_pm_service_agreements,id',
                'required' => 'required|boolean',
            ]);

            ServiceAgreement::where('id', $request->sla_id)
                ->update(['move_in_inspection_required' => (bool) $request->required]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating move-in inspection flag: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update'], 500);
        }
    }

    /**
     * Delete a service agreement document (AJAX)
     */
    public function deleteDocument($id)
    {
        try {
            $document = ServiceAgreementDocument::findOrFail($id);
            
            // Delete file from storage
            if ($document->sla_documents && Storage::disk('public')->exists($document->sla_documents)) {
                Storage::disk('public')->delete($document->sla_documents);
            }
            
            $document->delete();
            
            return response()->json(['success' => true, 'message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting SLA document: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete document'], 500);
        }
    }

    
}
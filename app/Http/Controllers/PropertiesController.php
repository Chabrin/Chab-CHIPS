<?php

namespace App\Http\Controllers;

use Error;
use App\Models\Zone;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\LeaseAgreement;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class PropertiesController extends Controller
{
    /**
     * Display a listing of properties
     */
    public function index()
    {
        return view('property.properties.index');
    }

    /**
     * Display a listing of inactive properties
     */
    public function inactiveIndex()
    {
        return view('property.properties.inactive-index');
    }

    /**
     * Get properties data for DataTables (AJAX)
     */
    public function getProperties(Request $request)
    {
        try {
            $can_read = Auth::user()->isAbleTo('read_properties');
            $can_update = Auth::user()->isAbleTo('update_properties');
            $can_read_client = Auth::user()->isAbleTo('read_clients');

            $query = Property::with(['zone', 'client'])
                ->select([
                    'id',
                    'reference_number',
                    'description',
                    'property_name',
                    'zone_id',
                    'client_id'
                ]);
            
            // Filter by client if provided
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }
            
            $properties = $query->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property)  use ($can_read, $can_update, $can_read_client) {
                    return [
                        'id' => $property->id,
                        'encrypted_id' => Crypt::encrypt($property->id),
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'description' => $property->description ?? '-',
                        'zone_name' => $property->zone->zone_name ?? '-',
                        'client_name' => $property->client->names ?? '-',
                        'client_id' => $property->client_id,
                        'can_read_client' => $can_read_client,
                        'can_read' => $can_read,
                        'can_update' => $can_update,
                        'client_encrypted_id' => $property->client ? Crypt::encrypt($property->client_id) : null,
                    ];
                });

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get inactive properties data for DataTables (AJAX)
     */
    public function getInactiveProperties(Request $request)
    {
        try {
            $can_read = Auth::user()->isAbleTo('read_properties');
            $can_read_client = Auth::user()->isAbleTo('read_clients');

            $query = Property::withInactive()
                ->with(['zone', 'client'])
                ->where('active', 0)
                ->select([
                    'id',
                    'reference_number',
                    'description',
                    'property_name',
                    'zone_id',
                    'client_id'
                ]);

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            $properties = $query->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) use ($can_read, $can_read_client) {
                    return [
                        'id' => $property->id,
                        'encrypted_id' => Crypt::encrypt($property->id),
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'description' => $property->description ?? '-',
                        'zone_name' => $property->zone->zone_name ?? '-',
                        'client_name' => $property->client->names ?? '-',
                        'client_id' => $property->client_id,
                        'can_read_client' => $can_read_client,
                        'can_read' => $can_read,
                        'client_encrypted_id' => $property->client ? Crypt::encrypt($property->client_id) : null,
                    ];
                });

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inactive properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    public function getPropertiesByClient($ClientId)
    {
        try {

            // Get IDs of properties that are targets of completed merges for this client
            $mergedTargetIds = DB::table('cb_property_merges')
                ->where('client_id', $ClientId)
                ->pluck('target_property_id')
                ->toArray();

            $properties = Property::withoutGlobalScope('user_properties')
                ->where('client_id', $ClientId)
                ->select(['id', 'property_name', 'reference_number'])
                ->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) use ($mergedTargetIds) {
                    return [
                        'id' => $property->id,
                        'property_name' => $property->property_name ?? '-',
                        'reference_number' => $property->reference_number ?? '-',
                        'is_merged' => in_array($property->id, $mergedTargetIds),
                    ];
                });

            return response()->json(['properties' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties by client: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Display the specified property
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $property = Property::withInactive()
            ->with([
                'zone',
                'zoneArea',
                'client',
                'fieldOfficer',
                'zoneSupervisor',
                'zoneManager',
                'units',
                'leaseAgreements'
            ])
            ->findOrFail($id);


            $unitTopologies = \App\Models\UnitTopology::orderBy('topology_name')->get();
            $unitCategories = \App\Models\UnitCategory::orderBy('category_name')->get();

            return view('property.properties.details', compact('property', 'unitTopologies', 'unitCategories'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property details: ' . $e->getMessage());
            return redirect()->route('property.properties.index')->with('error', 'Property not found');
        }
    }

    /**
     * Get leases for a specific property (AJAX)
     */
    public function getLeases($encryptedId, Request $request)
    {
        try {
            $propertyId = Crypt::decrypt($encryptedId);

            $leases = LeaseAgreement::with(['tenant', 'unit'])
                ->select(['id', 'ref_number', 'tenant_id', 'unit_id', 'date_start', 'date_end'])
                ->where('property_id', $propertyId)
                ->orderBy('date_start', 'desc')
                ->get()
                ->map(function ($lease) {
                    return [
                        'id' => $lease->id,
                        'ref_number' => $lease->ref_number ?? '-',
                        'tenant' => $lease->tenant->names ?? '-',
                        'unit' => $lease->unit->unit_name ?? '-',
                        'date_start' => $lease->date_start ? $lease->date_start->toDateString() : null,
                        'date_end' => $lease->date_end ? $lease->date_end->toDateString() : null,
                    ];
                });

            return response()->json(['data' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * Get tenants for a specific property (AJAX)
     */
    public function getTenants($encryptedId)
    {
        try {
            $propertyId = Crypt::decrypt($encryptedId);

            // Load leases for this property and derive tenants from tenant_id on leases.
            // Use unique tenant_id to avoid duplicates if multiple leases exist for same tenant.
            $leases = LeaseAgreement::with(['tenant', 'unit'])
                ->select(['id', 'tenant_id', 'unit_id', 'ref_number', 'date_start', 'date_end'])
                ->where('property_id', $propertyId)
                ->orderBy('date_start', 'desc')
                ->get();

            // Map unique tenants based on tenant_id
            $tenants = $leases->unique('tenant_id')->map(function ($lease) {
                $tenant = $lease->tenant;
                return [
                    'id' => $tenant ? $tenant->id : null,
                    'encrypted_id' => $tenant ? Crypt::encrypt($tenant->id) : null,
                    'names' => $tenant->names ?? '-',
                    'mobile_number' => $tenant->mobile_number ?? '-',
                    'national_id' => $tenant->national_id ?? '-',
                    'unit_name' => $lease->unit->unit_name ?? '-',
                    'lease_ref' => $lease->ref_number ?? '-',
                ];
            })->values();

            return response()->json(['data' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property tenants: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tenants'], 500);
        }
    }

    /**
     * Get repairs & maintenance for a specific property (AJAX)
     */
    public function getRepairs($encryptedId)
    {
        try {
            $propertyId = Crypt::decrypt($encryptedId);

            $repairs = \App\Models\RepairRequest::with(['createdBy'])
                ->where('property_id', $propertyId)
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($repair) {
                    return [
                        'id' => $repair->id,
                        'encrypted_id' => Crypt::encrypt($repair->id),
                        'ref_number' => $repair->ref_number ?? '-',
                        'visited_date_time' => $repair->visited_date_time ? \Carbon\Carbon::parse($repair->visited_date_time)->format('d-m-Y H:i') : '-',
                        'created_by_name' => $repair->createdBy->name ?? '-',
                        'current_status_id' => $repair->current_status_id,
                    ];
                });

            return response()->json(['data' => $repairs]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property repairs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load repairs'], 500);
        }
    }

    /**
     * Show the form for creating a new property
     */
    public function create(Request $request)
    {
        try {
            $clientId = null;
            $client = null;
            $isClientPreSelected = false;
    
            Log::info('Create property request parameters: ', $request->all());
            
            // If client parameter is provided, decrypt it
            if ($request->has('client')) {
                $clientId = Crypt::decrypt($request->client);
                // Load client with bank accounts and bank relationship
                $client = Client::with(['bankAccounts.bank'])->findOrFail($clientId);
                $isClientPreSelected = true;
            }
            
            return view('property.properties.create', compact('client', 'isClientPreSelected'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Search clients for property form (AJAX)
     */
    public function searchClients(Request $request)
    {
        try {
            $search = $request->get('search', '');
            
            if (strlen($search) < 2) {
                return response()->json(['clients' => []]);
            }
    
            $searchLower = strtolower($search);
    
            $clients = Client::with(['bankAccounts.bank'])
                ->where(function($query) use ($searchLower) {
                    $query->whereRaw('LOWER(names) LIKE ?', ["%{$searchLower}%"])
                          ->orWhereRaw('LOWER(email_address) LIKE ?', ["%{$searchLower}%"])
                          ->orWhere('mobile_number', 'LIKE', "%{$searchLower}%")
                          ->orWhere('national_id', 'LIKE', "%{$searchLower}%");
                })
                ->select(['id', 'names', 'email_address', 'mobile_number'])
                ->orderBy('names', 'asc')
                ->limit(20) // Limit to 20 results for performance
                ->get()
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'names' => $client->names,
                        'email' => $client->email_address ?? '-',
                        'mobile_number' => $client->mobile_number ?? '-',
                        'bank_accounts' => $client->bankAccounts->map(function ($account) {
                            return [
                                'id' => $account->id,
                                'account_name' => $account->account_name,
                                'account_number' => $account->account_number,
                                'bank_name' => $account->bank->bank_name ?? '-',
                            ];
                        })
                    ];
                });
    
            return response()->json(['clients' => $clients]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching clients: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to search clients'], 500);
        }
    }
   
    /**
     * Get zones with their details for property form (AJAX)
     */
    public function getZonesWithDetails()
    {
        try {
            // zone_manager_id/zone_supervisor_id are sys_staff ids, same space as
            // Property.zone_manager_id/zone_supervisor_id and this form's own selects
            // — no translation needed, just read them straight through.
            $zones = Zone::with(['zone_areas', 'zoneManager:id,full_names', 'zoneSupervisor:id,full_names'])
                ->select(['id', 'zone_name', 'zone_manager_id', 'zone_supervisor_id'])
                ->orderBy('zone_name', 'asc')
                ->get()
                ->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'zone_name' => $zone->zone_name,
                        'manager_id' => $zone->zone_manager_id,
                        'manager_name' => $zone->zoneManager->full_names ?? null,
                        'supervisor_id' => $zone->zone_supervisor_id,
                        'supervisor_name' => $zone->zoneSupervisor->full_names ?? null,
                        'areas' => $zone->zone_areas->map(function ($area) {
                            return [
                                'id' => $area->id,
                                'area_name' => $area->area_name,
                                'zone_id' => $area->zone_id,
                            ];
                        })
                    ];
                });

            return response()->json(['zones' => $zones]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading zones with details: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to load zones'], 500);
        }
    }
    
    /**
     * Store a newly created property
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'property_name' => 'required|string|max:255',
                'zone_id' => 'required|integer',
                'zone_area_id' => 'required|integer',
                'lr_number' => 'nullable|string|max:100',
                'property_type_id' => 'required|integer',
                'field_officer_id' => 'nullable|integer',
                'acquisition_date' => 'nullable|date',
                'description' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'settlement_account_id' => 'nullable|integer|exists:sys_pm_clients_17_repeat,id',
            ]);

            // Generate reference number with zone suffix (e.g., 145A, 146A)
            $zone = Zone::findOrFail($validated['zone_id']);
            $referenceNumber = Property::generateReferenceNumber($zone);

            // Format lat_long as (latitude,longitude):zoom_level
            $latLong = null;
            if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
                $latLong = '(' . $validated['latitude'] . ',' . $validated['longitude'] . '):12';
            }
    
            $property = Property::create([
                'reference_number' => $referenceNumber,
                'client_id' => $validated['client_id'],
                'property_name' => $validated['property_name'],
                'zone_id' => $validated['zone_id'],
                'zone_area_id' => $validated['zone_area_id'],
                'lr_number' => $validated['lr_number'] ?? null,
                'zone_manager_id' => $zone->zone_manager_id,
                'zone_supervisor_id' => $zone->zone_supervisor_id,
                'usage_type_id' => $validated['property_type_id'],
                'field_officer_id' => $validated['field_officer_id'] ?? null,
                'acquisition_date' => $validated['acquisition_date'] ?? now(),
                'description' => $validated['description'] ?? null,
                'lat_long' => $latLong,
                'bank_account_id' => $validated['settlement_account_id'] ?? null,
                'current_status_id' => 1, // Assuming 1 is the default status
                'date_time' => now(),
                'created_by' => Auth::id(),
            ]);
    
            DB::commit();
    
            return redirect()->route('property.clients.show', Crypt::encrypt($validated['client_id']))
                ->with('success', 'Property created successfully with reference number: ' . $referenceNumber);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating property: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create property: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing a property
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $property = Property::with([
                'zone.zone_areas',
                'zoneArea',
                'client.bankAccounts.bank',
                'fieldOfficer',
                'zoneSupervisor',
                'zoneManager'
            ])->findOrFail($id);
    
            $client = $property->client;
            $isClientPreSelected = true;
    
            return view('property.properties.edit', compact('property', 'client', 'isClientPreSelected'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property edit form: ' . $e->getMessage());
            return redirect()->route('property.properties.index')->with('error', 'Failed to load property');
        }
    }
    
    /**
     * Update the specified property
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $property = Property::findOrFail($id);
    
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'property_name' => 'required|string|max:255',
                'zone_id' => 'required|integer',
                'zone_area_id' => 'required|integer',
                'lr_number' => 'nullable|string|max:100',
                'property_type_id' => 'required|integer',
                'field_officer_id' => 'nullable|integer',
                'acquisition_date' => 'nullable|date',
                'description' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'settlement_account_id' => 'nullable|integer|exists:sys_pm_clients_17_repeat,id',
            ]);

            $zone = Zone::findOrFail($validated['zone_id']);

            // Format lat_long as (latitude,longitude):zoom_level
            $latLong = null;
            if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
                $latLong = '(' . $validated['latitude'] . ',' . $validated['longitude'] . '):12';
            }

            $property->update([
                'client_id' => $validated['client_id'],
                'property_name' => $validated['property_name'],
                'zone_id' => $validated['zone_id'],
                'zone_area_id' => $validated['zone_area_id'],
                'lr_number' => $validated['lr_number'] ?? null,
                'zone_manager_id' => $zone->zone_manager_id,
                'zone_supervisor_id' => $zone->zone_supervisor_id,
                'usage_type_id' => $validated['property_type_id'],
                'field_officer_id' => $validated['field_officer_id'] ?? null,
                'acquisition_date' => $validated['acquisition_date'] ?? $property->acquisition_date,
                'description' => $validated['description'] ?? null,
                'lat_long' => $latLong,
                'bank_account_id' => $validated['settlement_account_id'] ?? null,
            ]);
    
            DB::commit();
    
            Log::info('Property updated successfully', [
                'property_id' => $property->id,
                'reference_number' => $property->reference_number,
                'updated_by' => Auth::id()
            ]);
    
            return redirect()->route('property.properties.show', Crypt::encrypt($property->id))
                ->with('success', 'Property updated successfully');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating property: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update property: ' . $e->getMessage())->withInput();
        }
    }
}

<?php

namespace App\Http\Controllers;

use Error;
use App\Models\Bank;
use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use App\Models\BankBranch;
use Illuminate\Http\Request;
use App\Models\ClientDocument;
use App\Models\LeaseAgreement;
use App\Models\ClientNextOfKin;
use App\Models\ClientBankAccount;
use App\Services\ErrorDispatcher;
use App\Models\ClientOtherContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class ClientsController extends Controller
{
    /**
     * Display a listing of clients
     */
    public function index()
    {
        return view('property.clients.index');
    }

    public function getCLients(Request $request)
    {   
        try {

            $can_read = Auth::user()->isAbleTo('read_clients');
            $can_update = Auth::user()->isAbleTo('update_clients');

            $query = Client::select(['id', 'names', 'mobile_number', 'national_id', 'reference_number', 'email_address', 'registered_date'])
                ->typeOne();
            
            // Filter by client_type if provided (Landlord or Tenant)
            if ($request->filled('client_type')) {
                if ($request->client_type === 'Landlord') {
                    $query->where('reference_number', 'NOT LIKE', 'TEN-%');
                } elseif ($request->client_type === 'Tenant') {
                    $query->where('reference_number', 'LIKE', 'TEN-%');
                }
            }
            
            // Search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('names', 'LIKE', "%{$search}%")
                      ->orWhere('reference_number', 'LIKE', "%{$search}%")
                      ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                      ->orWhere('email_address', 'LIKE', "%{$search}%");
                });
            }
            
            $clients = $query->orderBy('registered_date', 'desc')
                ->limit(50) // Limit results for performance
                ->get()
                ->map(function ($client) use ($can_read, $can_update) {
                    return [
                        'id' => $client->id,
                        'encrypted_id' => Crypt::encrypt($client->id),
                        'names' => $client->names ?? '-',
                        'mobile_number' => $client->mobile_number ?? '-',
                        'national_id' => $client->national_id ?? '-',
                        'reference_number' => $client->reference_number ?? '-',
                        'email_address' => $client->email_address ?? '-',
                        'registered_date' => $client->registered_date ? $client->registered_date->format('d-m-Y') : '-',
                        'can_read' => $can_read,
                        'can_update' => $can_update,
                    ];
                });
            return response()->json(['clients' => $clients]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading clients: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load clients'], 500);
        }
    }

    /**
     * Display the specified client
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($id);
            return view('property.clients.details', compact('client'));
        } catch (\Exception $e) {
            Log::error('Error loading client details: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Client not found');
        }
    }


    /**
     * Get properties for a specific client (AJAX)
     */
    public function getProperties($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $properties = Property::with(['zone', 'zoneArea', 'fieldOfficer'])
                ->select([
                    'id',
                    'client_id',
                    'reference_number',
                    'description',
                    'zone_area_id',
                    'zone_id',
                    'property_name',
                    'field_officer_id'
                ])
                ->where('client_id', $clientId)
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'encrypted_id' => Crypt::encrypt($property->id),
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'description' => $property->description ?? '-',
                        'zone' => $property->zone->zone_name ?? '-',
                        'zone_area' => $property->zoneArea->area_name ?? '-',
                        'field_officer' => $property->fieldOfficer ? strtoupper($property->fieldOfficer->full_names) : '-',
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
     * Get properties dropdown for a specific client (AJAX)
     */
    public function getClientProperties($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $properties = Property::select(['id', 'property_name', 'reference_number'])
                ->where('client_id', $clientId)
                ->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'text' => $property->property_name . ' (' . ($property->reference_number ?? 'N/A') . ')'
                    ];
                });

            return response()->json($properties);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get units for a specific property (AJAX)
     */
    public function getUnits(Request $request)
    {
        try {
            $propertyId = $request->property_id;

            $units = Unit::with(['property', 'zone'])
                ->select([
                    'id',
                    'unit_code',
                    'unit_name',
                    'unit_number',
                    'description',
                    'rent_amount',
                    'property_id',
                    'zone_id',
                    'occupancy_status_id'
                ])
                ->where('property_id', $propertyId)
                ->get()
                ->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'encrypted_id' => Crypt::encrypt($unit->id),
                        'unit_code' => $unit->unit_code ?? '-',
                        'unit_name' => $unit->unit_name ?? '-',
                        'unit_number' => $unit->unit_number ?? '-',
                        'description' => $unit->description ?? '-',
                        'rent_amount' => $unit->rent_amount ? number_format($unit->rent_amount, 2) : '0.00',
                        'property' => $unit->property->property_name ?? '-',
                        'zone' => $unit->zone->zone_name ?? '-',
                        'occupancy_status' => $unit->occupancy_status_id == 1 ? 'Occupied' : 'Vacant',
                    ];
                });

            return response()->json(['data' => $units]);
        } catch (\Exception $e) {
            Log::error('Error loading units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Get tenants for a specific property (AJAX)
     */
    public function getTenants(Request $request)
    {
        try {
            $propertyId = $request->property_id;

            $tenants = Client::with(['unit'])
                ->select([
                    'id',
                    'names',
                    'mobile_number',
                    'email_address',
                    'account_name',
                    'account_number',
                    'reference_number',
                    'address',
                    'registered_date',
                    'property_id',
                    'unit_id'
                ])
                ->tenants()
                ->where('property_id', $propertyId)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'encrypted_id' => Crypt::encrypt($tenant->id),
                        'names' => $tenant->names ?? '-',
                        'mobile_number' => $tenant->mobile_number ?? '-',
                        'email_address' => $tenant->email_address ?? '-',
                        'account_name' => $tenant->account_name ?? '-',
                        'account_number' => $tenant->account_number ?? '-',
                        'reference_number' => $tenant->reference_number ?? '-',
                        'address' => $tenant->address ?? '-',
                        'registered_date' => $tenant->registered_date ? $tenant->registered_date->format('d-m-Y') : '-',
                        'unit_name' => $tenant->unit->unit_name ?? '-',
                    ];
                });

            return response()->json(['data' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tenants'], 500);
        }
    }

    /**
     * Get leases for a specific client, optional property filter (AJAX)
     */
    public function getLeases($encryptedId, Request $request)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);
            $propertyId = $request->property_id;

            $query = LeaseAgreement::with(['tenant', 'property', 'unit'])
                ->select(['id', 'ref_number', 'tenant_id', 'property_id', 'unit_id', 'date_start', 'date_end'])
                ->where('client_id', $clientId);

            if ($propertyId) {
                $query->where('property_id', $propertyId);
            }

            $leases = $query->orderBy('date_start', 'desc')->get()->map(function ($lease) {
                return [
                    'id' => $lease->id,
                    'ref_number' => $lease->ref_number ?? '-',
                    'tenant' => $lease->tenant->names ?? '-',
                    'property' => $lease->property->property_name ?? '-',
                    'unit' => $lease->unit->unit_name ?? '-',
                    'date_start' => $lease->date_start ? $lease->date_start->format('d-m-Y') : '-',
                    'date_end' => $lease->date_end ? $lease->date_end->format('d-m-Y') : '-',
                ];
            });

            return response()->json(['data' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * Get recoveries for a specific client (AJAX)
     */
    public function getRecoveries($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $recoveries = \App\Models\Recovery::with(['property', 'zone', 'transactionType', 'createdBy'])
                ->where('client_id', $clientId)
                ->orderBy('recovery_date', 'desc')
                ->get()
                ->map(function ($recovery) {
                    return [
                        'id' => $recovery->id,
                        'encrypted_id' => Crypt::encrypt($recovery->id),
                        'ref_number' => $recovery->ref_number ?? '-',
                        'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d/m/y') : '-',
                        'amount' => 'KES ' . number_format($recovery->amount ?? 0, 2),
                        'property' => $recovery->property->property_name ?? '-',
                        'zone' => $recovery->zone->zone_name ?? '-',
                        'transaction_type' => $recovery->transactionType->type_name ?? '-',
                        'commisionable' => $recovery->commisionable ?? 'No',
                        'created_by' => $recovery->createdBy->name ?? '-',
                    ];
                });

            return response()->json(['data' => $recoveries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recoveries: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load recoveries'], 500);
        }
    }

    /**
     * Get inspections for a specific client (AJAX)
     */
    public function getInspections($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $inspections = \App\Models\Inspection::with(['property', 'zone', 'createdBy', 'units'])
                ->where('client_id', $clientId)
                ->orderBy('visited_date_time', 'desc')
                ->get()
                ->map(function ($inspection) {
                    return [
                        'id' => $inspection->id,
                        'encrypted_id' => Crypt::encrypt($inspection->id),
                        'property' => $inspection->property->property_name ?? '-',
                        'zone' => $inspection->zone->zone_name ?? '-',
                        'visited_date' => $inspection->visited_date_time ? \Carbon\Carbon::parse($inspection->visited_date_time)->format('d/m/y H:i') : '-',
                        'left_date' => $inspection->left_date_time ? \Carbon\Carbon::parse($inspection->left_date_time)->format('d/m/y H:i') : '-',
                        'duration' => $inspection->duration ? $inspection->duration . ' mins' : '-',
                        'units_count' => $inspection->units->count(),
                        'created_by' => $inspection->createdBy->name ?? '-',
                    ];
                });

            return response()->json(['data' => $inspections]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inspections: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load inspections'], 500);
        }
    }

    /**
     * Get repairs & maintenance requests for a client (AJAX)
     */
    public function getRepairs($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $repairs = \App\Models\RepairRequest::with(['property', 'createdBy'])
                ->where('client_id', $clientId)
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($repair) {
                    return [
                        'id' => $repair->id,
                        'encrypted_id' => Crypt::encrypt($repair->id),
                        'ref_number' => $repair->ref_number ?? '-',
                        'property_name' => $repair->property->property_name ?? '-',
                        'visited_date_time' => $repair->visited_date_time ? \Carbon\Carbon::parse($repair->visited_date_time)->format('d-m-Y H:i') : '-',
                        'created_by_name' => $repair->createdBy->name ?? '-',
                        'current_status_id' => $repair->current_status_id,
                    ];
                });

            return response()->json(['data' => $repairs]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repairs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load repairs'], 500);
        }
    }

    /**
     * Display tenant listing page
     */
    public function tenantsListing()
    {
        try {
            return view('property.tenants.listing');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants listing: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenants listing');
        }
    }

    /**
     * Search tenants (AJAX)
     */
    public function searchTenants(Request $request)
    {
        try {
            $searchTerm = $request->search;

            $tenants = Client::select(['id', 'names', 'mobile_number', 'national_id', 'reference_number'])
                ->tenants()
                ->where(function ($query) use ($searchTerm) {
                    $query->where('names', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('mobile_number', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('national_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('reference_number', 'LIKE', "%{$searchTerm}%");
                })
                ->get()
                ->map(function ($tenant) {
                    return [
                        'encrypted_id' => $tenant->encrypted_id,
                        'names' => $tenant->names,
                        'mobile_number' => $tenant->mobile_number,
                        'national_id' => $tenant->national_id,
                        'reference_number' => $tenant->reference_number,
                    ];
                });

            return response()->json(['tenants' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching tenants: ' . $e->getMessage());
            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    /**
     * Display tenant details
     */
    public function showTenant($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $tenant = Client::with(['property', 'unit.zone'])
                ->tenants()
                ->findOrFail($id);
            $tenantBalance = \App\Services\TenantBalanceService::getBalance((int) $id);
            return view('property.tenants.details', compact('tenant', 'tenantBalance'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant details: ' . $e->getMessage());
            return redirect()->route('property.tenants.listing')->with('error', 'Tenant not found');
        }
    }

    /**
     * Get banks and branches (AJAX)
     */
    public function getBanksAndBranches()
    {
        try {
            $banks = Bank::select(['id', 'bank_name'])->orderBy('bank_name', 'asc')->get();

            $branches = BankBranch::select(['id', 'bank_id', 'branch_name'])
                ->orderBy('branch_name', 'asc')
                ->get()
                ->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'bank_id' => $branch->bank_id,
                        'branch_name' => $branch->branch_name,
                    ];
                });

            return response()->json([
                'banks' => $banks,
                'branches' => $branches,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading banks and branches: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load banks and branches'], 500);
        }
    }

    /**
     * Show the form for creating a new client
     */
    public function create()
    {
        try {
            return view('property.clients.create');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client create form: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Failed to load form');
        }
    }
    
    /**
     * Store a newly created client
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $this->validateClientRequest($request);
            Log::info('Validated client data: ' . json_encode($validated));
            
            $referenceNumber = $this->generateClientReferenceNumber();
            Log::info('Generated reference number: ' . $referenceNumber);
            
            $photoPath = $this->handlePhotoUpload($request);
            
            $client = $this->createClient($validated, $referenceNumber, $photoPath);
            //Log::info('Client created with ID: ' . $client->id . ' by User ID: ' . Auth::id());
            
            $this->saveBankAccounts($request, $client->id);
            Log::info('Bank accounts saved for client ID: ' . $client->id);
            
            $this->saveOtherContacts($request, $client->id);
            Log::info('Other contacts saved for client ID: ' . $client->id);
            
            $this->saveDocuments($request, $client->id);
            Log::info('Documents saved for client ID: ' . $client->id);
            
            $this->saveNextOfKin($request, $client->id);
            Log::info('Next of kin saved for client ID: ' . $client->id);
    
            DB::commit();
    
            return redirect()->route('property.clients.show', Crypt::encrypt($client->id))
                ->with('success', 'Client created successfully with reference number: ' . $referenceNumber);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating client: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create client: ' . $e->getMessage())->withInput();
        }
    }
    
    /**
     * Validate client request
     */
    private function validateClientRequest(Request $request)
    {
        return $request->validate([
            'select_time' => 'nullable|date',
            'client_id' => 'nullable|string|max:255',
            'title' => 'required|string|max:10',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'surname2' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:50',
            'prefix' => 'nullable|string|max:20',
            'gender' => 'required|in:Male,Female,Other',
            'pin_number' => 'nullable|string|max:50',
            'preferred_language_id' => 'nullable|integer',
            'passport_number' => 'nullable|string|max:50',
            'mobile_number' => 'required|string|size:12|regex:/^254[0-9]{9}$/',
            'email_address' => 'nullable|email|max:255',
            'nationality_id' => 'nullable|integer',
            'client_type_id' => 'required|integer|in:1,2,3',
            'vat_number' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'overdraft_penalty' => 'nullable|numeric',
            'address' => 'nullable|string',
            'address_2' => 'nullable|string',
            'address_3' => 'nullable|string',
            
            // Bank accounts validation
            'banks' => 'nullable|array',
            'banks.*.bank_id' => 'nullable|integer',
            'banks.*.branch_id' => 'nullable|integer',
            'banks.*.account_name' => 'nullable|string|max:255',
            'banks.*.account_number' => 'nullable|string|max:50',
            'banks.*.return_percentage' => 'nullable|numeric',
            
            // Other contacts validation
            'contacts' => 'nullable|array',
            'contacts.*.names' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.mobile' => 'nullable|string|max:20',
            
            // Documents validation
            'documents' => 'nullable|array',
            'documents.*.file_type' => 'nullable|string',
            'documents.*.other_file_type' => 'nullable|string|max:255',
            'documents.*.file' => 'nullable|file|max:10240',
            
            // Next of Kin validation
            'next_of_kin' => 'nullable|array',
            'next_of_kin.*.first_name' => 'nullable|string|max:255',
            'next_of_kin.*.second_name' => 'nullable|string|max:255',
            'next_of_kin.*.last_name' => 'nullable|string|max:255',
            'next_of_kin.*.mobile' => 'nullable|string|max:20',
            'next_of_kin.*.email' => 'nullable|email|max:255',
            'next_of_kin.*.relation' => 'nullable|string|max:100',
            'next_of_kin.*.national_id' => 'nullable|string|max:50',
        ]);
    }
    
    /**
     * Generate client reference number based on client type
     */
    private function generateClientReferenceNumber()
    {
        $prefix = "LAN";
        
        // Query for the last client with this specific prefix
        $lastClient = Client::where('reference_number', 'LIKE', "{$prefix}-%")
            ->orderBy('reference_number', 'desc')
            ->first();
    
        if ($lastClient) {
            // Extract the number part after the last hyphen
            $lastNumber = intval(substr($lastClient->reference_number, strrpos($lastClient->reference_number, '-') + 1));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
    
        return "{$prefix}-{$newNumber}";
    }
    
    /**
     * Handle photo upload
     */
    private function handlePhotoUpload(Request $request)
    {
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            return $request->file('photo')->store('clients/photos', 'public');
        }
        return null;
    }
    
    /**
     * Create the main client record
     */
    private function createClient(array $validated, string $referenceNumber, ?string $photoPath)
    {
        $clientData = [
            'reference_number' => $referenceNumber,
            'client_type_id' => $validated['client_type_id'],
            'type_id' => $validated['client_type_id'], // For compatibility
            'title' => $validated['title'],
            'names' => trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']),
            'second_name' => $validated['middle_name'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'last_name' => $validated['last_name'],
            'national_id' => $validated['national_id'] ?? null,
            'gender' => $validated['gender'],
            'pin_number' => $validated['pin_number'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'nationality_id' => $validated['nationality_id'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'overdraft_penalty' => $validated['overdraft_penalty'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo' => $photoPath,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'prefered_messages_language_id' =>  $validated['preferred_language_id'], // Client type
            'registered_date' => $validated['select_time'] ?? now(),
            'date_time' => $validated['select_time'] ?? now(),
            'current_status_id' => '1',
            'created_by' => Auth::id(),
        ];
    
        return Client::create($clientData);
    }
    
    /**
     * Save bank accounts to sys_pm_clients_17_repeat
     */
    private function saveBankAccounts(Request $request, int $clientId)
    {
        if (!$request->has('banks')) {
            return;
        }
    
        foreach ($request->input('banks') as $bankData) {
            // Skip empty entries
            if (empty($bankData['bank_id']) && empty($bankData['account_number'])) {
                continue;
            }
    
            ClientBankAccount::create([
                'parent_id' => $clientId,
                'bank_id' => $bankData['bank_id'] ?? null,
                'branch' => $bankData['branch_id'] ?? null,
                'account_name' => $bankData['account_name'] ?? null,
                'account_number' => $bankData['account_number'] ?? null,
                'return_percentage' => $bankData['return_percentage'] ?? null,
            ]);
        }
    }
    
    /**
     * Save other contacts to sys_pm_clients_19_repeat
     */
    private function saveOtherContacts(Request $request, int $clientId)
    {
        if (!$request->has('contacts')) {
            return;
        }
    
        foreach ($request->input('contacts') as $contactData) {
            // Skip empty entries
            if (empty($contactData['names']) && empty($contactData['email']) && empty($contactData['mobile'])) {
                continue;
            }
    
            ClientOtherContact::create([
                'parent_id' => $clientId,
                'contact_names' => $contactData['names'] ?? null,
                'other_email' => $contactData['email'] ?? null,
                'other_mobile_number' => $contactData['mobile'] ?? null,
            ]);
        }
    }
    
    /**
     * Save documents to sys_pm_clients_65_repeat
     */
    private function saveDocuments(Request $request, int $clientId)
    {
        if (!$request->has('documents')) {
            return;
        }
    
        foreach ($request->input('documents') as $index => $documentData) {
            // Skip if no file uploaded
            if (!$request->hasFile("documents.{$index}.file")) {
                continue;
            }
    
            $file = $request->file("documents.{$index}.file");
            if ($file && $file->isValid()) {
                // Store the file
                $filePath = $file->store('clients/' . $clientId . '/documents', 'public');
                
                // Determine file type
                $fileType = $documentData['file_type'] ?? 'Other';
                if ($fileType === 'Other' && !empty($documentData['other_file_type'])) {
                    $fileType = $documentData['other_file_type'];
                }
    
                ClientDocument::create([
                    'parent_id' => $clientId,
                    'documents' => $filePath,
                    'file_type' => $fileType,
                ]);
            }
        }
    }
    
    /**
     * Save next of kin to sys_pm_clients_66_repeat
     */
    private function saveNextOfKin(Request $request, int $clientId)
    {
        if (!$request->has('next_of_kin')) {
            return;
        }
    
        foreach ($request->input('next_of_kin') as $kinData) {
            // Skip empty entries
            if (empty($kinData['first_name']) && empty($kinData['last_name']) && empty($kinData['mobile'])) {
                continue;
            }
    
            // Construct full name
            $fullName = trim(
                ($kinData['first_name'] ?? '') . ' ' . 
                ($kinData['second_name'] ?? '') . ' ' . 
                ($kinData['last_name'] ?? '')
            );
    
            ClientNextOfKin::create([
                'parent_id' => $clientId,
                'kin_first_name' => $kinData['first_name'] ?? null,
                'kin_second_name' => $kinData['second_name'] ?? null,
                'kin_last_name' => $kinData['last_name'] ?? null,
                'names' => $fullName,
                'mobile_number' => $kinData['mobile'] ?? null,
                'email_address' => $kinData['email'] ?? null,
                'relationship' => $kinData['relation'] ?? null,
                'national_id' => $kinData['national_id'] ?? null,
            ]);
        }
    }


    /**
     * Show the form for editing a client
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);

            $client = Client::with([
                'bankAccounts.bank',
                'bankAccounts.branch',
                'otherContacts',
                'documents',
                'nextOfKins'
            ])->findOrFail($id);

            // dd($client);

            
            return view('property.clients.edit', compact('client'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client edit form: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Client not found');
        }
    }
    
    /**
     * Update the specified client
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($id);
            
            $validated = $this->validateClientRequest($request);
            Log::info('Validated client update data for ID: ' . $id);
            
            // Handle photo upload
            $photoPath = $this->handlePhotoUpload($request);
            if ($photoPath) {
                // Delete old photo if exists
                if ($client->photo && Storage::disk('public')->exists($client->photo)) {
                    Storage::disk('public')->delete($client->photo);
                }
            } else {
                $photoPath = $client->photo; // Keep existing photo
            }
            
            // Update client
            $this->updateClient($client, $validated, $photoPath);
            Log::info('Client updated with ID: ' . $id . ' by User ID: ' . Auth::id());
            
            // Update related records
            $this->updateBankAccounts($request, $client->id);
            Log::info('Bank accounts updated for client ID: ' . $client->id);
            
            $this->updateOtherContacts($request, $client->id);
            Log::info('Other contacts updated for client ID: ' . $client->id);
            
            $this->updateDocuments($request, $client->id);
            Log::info('Documents updated for client ID: ' . $client->id);
            
            $this->updateNextOfKin($request, $client->id);
            Log::info('Next of kin updated for client ID: ' . $client->id);
    
            DB::commit();
    
            return redirect()->route('property.clients.show', $encryptedId)
                ->with('success', 'Client updated successfully');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating client: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update client: ' . $e->getMessage())->withInput();
        }
    }
    
    /**
     * Update the main client record
     */
    private function updateClient(Client $client, array $validated, ?string $photoPath)
    {
        $client->update([
            'client_type_id' => $validated['client_type_id'],
            'type_id' => $validated['client_type_id'],
            'title' => $validated['title'],
            'names' => trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']),
            'second_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'national_id' => $validated['national_id'] ?? null,
            'gender' => $validated['gender'],
            'pin_number' => $validated['pin_number'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'nationality_id' => $validated['nationality_id'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'overdraft_penalty' => $validated['overdraft_penalty'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo' => $photoPath,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'prefered_messages_language_id' => $validated['preferred_language_id'],
            'registered_date' => $validated['select_time'] ?? $client->registered_date,
            'date_time' => $validated['select_time'] ?? $client->date_time,
        ]);
    }
    
    /**
     * Update bank accounts
     */
    private function updateBankAccounts(Request $request, int $clientId)
    {
        // Delete existing bank accounts
        ClientBankAccount::where('parent_id', $clientId)->delete();
        
        // Save new bank accounts
        $this->saveBankAccounts($request, $clientId);
    }
    
    /**
     * Update other contacts
     */
    private function updateOtherContacts(Request $request, int $clientId)
    {
        // Delete existing contacts
        ClientOtherContact::where('parent_id', $clientId)->delete();
        
        // Save new contacts
        $this->saveOtherContacts($request, $clientId);
    }
    
    /**
     * Update documents
     */
    private function updateDocuments(Request $request, int $clientId)
    {
        // Only add new documents, don't delete existing ones
        $this->saveDocuments($request, $clientId);
    }
    
    /**
     * Update next of kin
     */
    private function updateNextOfKin(Request $request, int $clientId)
    {
        // Delete existing next of kin
        ClientNextOfKin::where('parent_id', $clientId)->delete();
        
        // Save new next of kin
        $this->saveNextOfKin($request, $clientId);
    }

    
}

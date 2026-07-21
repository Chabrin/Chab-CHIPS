<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Deposit;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\SystemCountry;
use App\Models\MoveInInspection;
use App\Models\TenantPaymentAllocated;
use App\Models\ClientDocument;
use App\Models\LeaseAgreement;
use App\Models\ClientNextOfKin;
use App\Models\ClientBankDetail;
use App\Services\ErrorDispatcher;
use App\Models\ClientOtherContact;
use App\Services\TenantBalanceService;
use App\Models\TenantAccountTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class TenantsController extends Controller
{

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
                ->whereDate('date_end', '>=', now())
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
                ->select(['id', 'ref_number', 'tenant_id', 'property_id', 'unit_id', 'date_start', 'date_end', 'contract_sent_at', 'contract_signed_at', 'signed_pdf_path'])
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
     * Store a newly created tenant
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $this->validateTenantRequest($request);
            Log::info('Validated tenant data: ' . json_encode($validated));

            $referenceNumber = $this->generateTenantReferenceNumber();
            Log::info('Generated reference number: ' . $referenceNumber);

            $photoPath = $this->handlePhotoUpload($request);

            $tenant = $this->createTenant($validated, $referenceNumber, $photoPath);
            Log::info('Tenant created with ID: ' . $tenant->id . ' by User ID: ' . Auth::id());

            $this->saveDeposit($validated, $tenant);
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
     * Validate tenant request
     */
    private function validateTenantRequest(Request $request)
    {
        return $request->validate([
            // Unit Details
            'property_id' => 'required|integer|exists:sys_pm_properties,id',
            'unit_id' => 'required|integer|exists:sys_pm_units,id',
            'sla_ref' => 'nullable|integer|exists:sys_pm_service_agreements,id',
            'lease_start_date' => 'required|date',
            'lease_years' => 'required|integer|min:1',
            'rent_amount' => 'required|numeric|min:0',
            'escalation_rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:Annually,Monthly,Quarterly,Semi-Annually',
            'address_2' => 'nullable|string|max:255',
            'address_3' => 'nullable|string|max:255',
            'overcraft_penalty' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'deposit_reference' => 'nullable|string|max:255',
            'deposit_held_by' => 'required_with:deposit_amount|nullable|in:Company,Landlord',

            // Tenant Information
            'title' => 'required|string|max:10',
            'names' => 'required|string|max:255',
            'gender' => 'nullable|in:Male,Female,Other',
            'tenant_type' => 'required',
            'phone' => 'nullable|string|max:20',
            'mobile_number' => 'required',
            'email_address' => 'nullable|email|max:255',
            'pin_number' => 'nullable|string|max:50',
            'registered_date' => 'required|date',
            'nationality_id' => 'required',
            'tenant_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'passport_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'national_id' => 'nullable|string|max:50',

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
     * Validate the tenant edit form request (single "Names" field, no property/unit/SLA/rent editing)
     */
    private function validateTenantEditRequest(Request $request)
    {
        return $request->validate([
            // Unit Details (read-only on the edit form; carried through as hidden fields)
            'property_id' => 'required|integer|exists:sys_pm_properties,id',
            'unit_id' => 'required|integer|exists:sys_pm_units,id',
            'sla_ref' => 'nullable|integer|exists:sys_pm_service_agreements,id',
            'lease_start_date' => 'required|date',
            'lease_years' => 'required|integer|min:1',
            'rent_amount' => 'required|numeric|min:0',
            'escalation_rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:Annually,Monthly,Quarterly,Semi-Annually',
            'address_2' => 'nullable|string|max:255',
            'address_3' => 'nullable|string|max:255',
            'overcraft_penalty' => 'nullable|numeric|min:0',

            // Tenant Information
            'title' => 'required|string|max:10',
            'names' => 'required|string|max:255',
            'gender' => 'nullable|in:Male,Female,Other',
            'preferred_language' => 'nullable|string|max:50',
            'tenant_type' => 'required',
            'phone' => 'nullable|string|max:20',
            'mobile_number' => 'required',
            'email_address' => 'nullable|email|max:255',
            'pin_number' => 'nullable|string|max:50',
            'registered_date' => 'required|date',
            'nationality_id' => 'required',
            'passport_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'national_id' => 'nullable|string|max:50',

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
     * Generate tenant reference number with TEN prefix
     */
    private function generateTenantReferenceNumber()
    {
        $prefix = "TEN - ";

        // Query for the last tenant with TEN prefix, ordered by the numeric part
        $lastTenant = Client::where('reference_number', 'LIKE', "TEN -%")
            ->orderByRaw('CAST(SUBSTRING(reference_number, 7) AS UNSIGNED) DESC')
            ->first();

        if ($lastTenant) {
            // Extract number after "TEN - " (6 characters)
            $lastNumber = (int) substr($lastTenant->reference_number, 6);
            $newNumber = $lastNumber + 2;
        } else {
            // Start from 1 if no tenants exist
            $newNumber = 1;
        }

        return "{$prefix}{$newNumber}";
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
        // Combine names
        $names = trim(($validated['title'] ?? '') . ' ' . ($validated['names'] ?? ''));

        $tenantData = [
            'reference_number' => $referenceNumber,
            'names' => $names,
            'title' => $validated['title'],
            'gender' => $validated['gender'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'],
            'pin_number' => $validated['pin_number'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'registered_date' => $validated['registered_date'],
            'nationality_id' => $validated['nationality_id'] ?? null,
            'prefered_messages_language_id' => 'English',
            'client_type_id' => $validated['tenant_type'] ?? 'Individual',
            'property_id' => $validated['property_id'],
            'sla_id' => $validated['sla_ref'],
            'unit_id' => $validated['unit_id'],
            'lease_start_date' => $validated['lease_start_date'],
            'lease_years' => $validated['lease_years'],
            'rent_amount' => $validated['rent_amount'],
            'escalation_rate' => $validated['escalation_rate'] ?? 0,
            'frequency' => $validated['frequency'],
            'overdraft_penalty' => $validated['overcraft_penalty'] ?? 5,
            'photo' => $photoPath,
            'created_by' => Auth::id(),
            'date_time' => now(),
            'current_status_id' => 1, // Pending — awaiting maker-checker approval
        ];

        return Client::create($tenantData);
    }

    /**
     * Record the move-in deposit, if one was entered, in the dedicated deposits
     * register and mirror it as a credit entry on the tenant's ledger so it
     * shows up consistently on the tenant statement and exit workflow alike.
     */
    private function saveDeposit(array $validated, Client $tenant): void
    {
        $amount = (float) ($validated['deposit_amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        $property = Property::find($validated['property_id']);
        $narration = 'Move-in security deposit';
        $now = now();

        Deposit::create([
            'date_time' => $now,
            'deposit_date' => $now,
            'tenant_id' => $tenant->id,
            'client_id' => $property->client_id ?? null,
            'property_id' => $validated['property_id'],
            'sla_id' => $validated['sla_ref'] ?? null,
            'unit_id' => $validated['unit_id'],
            'amount' => $amount,
            'held_by' => $validated['deposit_held_by'] ?? null,
            'created_by_id' => Auth::id(),
            'current_status_id' => 1,
            'reference_number' => $validated['deposit_reference'] ?? null,
            'narration' => $narration,
        ]);

        TenantAccountTransaction::create([
            'date_time' => $now,
            'narration' => $narration,
            'pay_for' => 'Security Deposit',
            'dr_cr' => 'CR',
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'entry_ref' => $validated['deposit_reference'] ?? null,
            'tenant_id' => $tenant->id,
            'property_id' => $validated['property_id'],
            'unit_id' => $validated['unit_id'],
            'client_id' => $property->client_id ?? null,
            'sla_id' => $validated['sla_ref'] ?? null,
            'current_status_id' => 1,
            'entry_by' => Auth::id(),
        ]);
    }

    /**
     * Save bank accounts to sys_pm_clients_17_repeat
     */
    private function saveBankAccounts(Request $request, int $tenantId)
    {
        if (!$request->has('bank_accounts')) {
            return;
        }

        $isFirst = ClientBankDetail::where('client_id', $tenantId)->count() === 0;

        foreach ($request->input('bank_accounts') as $bankData) {
            $bankId = $bankData['bank_id'] ?? null;
            $branchId = $bankData['branch_id'] ?? null;
            $accountName = $bankData['account_name'] ?? null;
            $accountNumber = $bankData['account_number'] ?? null;

            // Skip only if the entire row is blank
            if (empty($bankId) && empty($branchId) && empty($accountName) && empty($accountNumber)) {
                continue;
            }

            ClientBankDetail::create([
                'client_id' => $tenantId,
                'bank_id' => $bankId ?: null,
                'branch_id' => $branchId ?: null,
                'account_name' => $accountName ?: null,
                'account_number' => $accountNumber ?: null,
                'is_default' => $isFirst ? 1 : 0,
                'added_by' => Auth::id(),
                'added_on' => now(),
            ]);

            $isFirst = false;
        }
    }

    /**
     * Save other contacts to sys_pm_clients_19_repeat
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

            ClientOtherContact::create([
                'parent_id' => $tenantId,
                'contact_names' => $contactData['name'],
                'other_email' => $contactData['email'] ?? null,
                'other_mobile_number' => $contactData['mobile'] ?? null,
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

            // Combine names for full name
            $fullNames = trim(
                ($kinData['names'] ?? '') . ' ' .
                    ($kinData['second_name'] ?? '') . ' ' .
                    ($kinData['last_name'] ?? '')
            );

            ClientNextOfKin::create([
                'parent_id' => $tenantId,
                'names' => $fullNames,
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
                'bankAccounts.bank',
                'otherContacts',
                'documents',
                'nextOfKins'
            ])->findOrFail($id);

            $countries = SystemCountry::orderBy('country_name')->get();

            // Get client/landlord for this tenant\
            $client_id = $tenant->property->client_id ?? null;
            if ($client_id) {
                $client = Client::find($client_id);
            } else {
                $client = null;
            }

            // dd($tenant, $client);

            return view('property.properties.tenants.edit', compact('tenant', 'client', 'countries'));
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
        // Combine names
        $names = trim(($validated['title'] ?? '') . ' ' . ($validated['names'] ?? ''));

        $tenant->update([
            'names' => $names,
            'title' => $validated['title'],
            'gender' => $validated['gender'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'],
            'pin_number' => $validated['pin_number'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'registered_date' => $validated['registered_date'],
            'nationality_id' => $validated['nationality_id'] ?? null,
            'prefered_messages_language_id' => $validated['preferred_language'] ?? 'English',
            'client_type_id' => $validated['tenant_type'] ?? 'Individual',
            'property_id' => $validated['property_id'],
            'sla_id' => $validated['sla_ref'],
            'unit_id' => $validated['unit_id'],
            'lease_start_date' => $validated['lease_start_date'],
            'lease_years' => $validated['lease_years'],
            'rent_amount' => $validated['rent_amount'],
            'escalation_rate' => $validated['escalation_rate'] ?? 0,
            'frequency' => $validated['frequency'],
            'overdraft_penalty' => $validated['overcraft_penalty'] ?? 5,
            'photo' => $photoPath,
            'updated_by' => Auth::id(),
        ]);
    }

    /**
     * Update bank accounts (delete-recreate pattern)
     */
    private function updateBankAccounts(Request $request, int $tenantId)
    {
        // Delete existing bank details
        ClientBankDetail::where('client_id', $tenantId)->delete();

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

                ClientBankDetail::create([
                    'client_id' => $tenantId,
                    // Cast empty strings to null - bank_id/branch_id are integer columns
                    'bank_id' => $bankId ?: null,
                    'branch_id' => $branchId ?: null,
                    'account_name' => $accountName ?: null,
                    'account_number' => $accountNumber ?: null,
                    'is_default' => $isFirst ? 1 : 0,
                    'added_by' => Auth::id(),
                    'added_on' => now(),
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
        ClientOtherContact::where('parent_id', $tenantId)->delete();

        // Save new contacts
        if ($request->has('other_contacts')) {
            foreach ($request->input('other_contacts') as $contactData) {
                if (empty($contactData['name'])) {
                    continue;
                }

                ClientOtherContact::create([
                    'parent_id' => $tenantId,
                    'contact_names' => $contactData['name'],
                    'other_email' => $contactData['email'] ?? null,
                    'other_mobile_number' => $contactData['mobile'] ?? null,
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
        ClientNextOfKin::where('parent_id', $tenantId)->delete();

        // Save new next of kin
        if ($request->has('next_of_kin')) {
            foreach ($request->input('next_of_kin') as $kinData) {
                if (empty($kinData['names'])) {
                    continue;
                }

                // Combine names for full name
                $fullNames = trim(
                    ($kinData['names'] ?? '') . ' ' .
                        ($kinData['second_name'] ?? '') . ' ' .
                        ($kinData['last_name'] ?? '')
                );

                ClientNextOfKin::create([
                    'parent_id' => $tenantId,
                    'names' => $fullNames,
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\LeaseAgreement;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\LeaseAgreementInvoiceableItem;
use App\Services\LeaseContractService;

class LeasesController extends Controller
{
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
     * Get leases with filters (AJAX)
     */
    public function getAllLeases(Request $request)
    {
        try {
            $query = LeaseAgreement::with(['tenant:id,names', 'property:id,property_name', 'unit:id,unit_code', 'client:id,names']);
            
            // Apply filters
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }
            
            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }
            
            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }
            
            $leases = $query->select([
                    'id',
                    'tenant_id',
                    'property_id',
                    'unit_id',
                    'client_id',
                    'ref_number',
                    'date_start',
                    'date_end',
                    'current_status_id',
                    'years',
                    'auto_renewal',
                    'escalation_rate',
                    'contract_sent_at',
                    'contract_signed_at',
                ])
                ->orderBy('date_end', 'asc')
                ->limit(500) // Limit results for performance
                ->get()
                ->map(function ($lease) {
                    $daysToExpiry = now()->diffInDays($lease->date_end, false);
                    $isExpiring = $daysToExpiry >= 0 && $daysToExpiry <= 90;
                    $isExpired = $daysToExpiry < 0;
                    
                    return [
                        'id' => $lease->id,
                        'encrypted_id' => Crypt::encrypt($lease->id),
                        'ref_number' => $lease->ref_number,
                        'tenant_name' => $lease->tenant->names ?? 'N/A',
                        'tenant_id' => $lease->tenant_id,
                        'encrypted_tenant_id' => $lease->tenant_id ? Crypt::encrypt($lease->tenant_id) : null,
                        'property_name' => $lease->property->property_name ?? 'N/A',
                        'unit_code' => $lease->unit->unit_code ?? 'N/A',
                        'unit_id' => $lease->unit_id,
                        'client_name' => $lease->client->names ?? 'N/A',
                        'date_start' => $lease->date_start,
                        'date_end' => $lease->date_end,
                        'days_to_expiry' => round($daysToExpiry),
                        'is_expiring' => $isExpiring,
                        'is_expired' => $isExpired,
                        'current_status_id' => $lease->current_status_id,
                        'years' => $lease->years,
                        'auto_renewal' => $lease->auto_renewal,
                        'contract_sent_at' => $lease->contract_sent_at,
                        'contract_signed_at' => $lease->contract_signed_at,
                        'contract_status' => $lease->contract_signed_at
                            ? 'signed'
                            : ($lease->contract_sent_at ? 'sent' : 'not_sent'),
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
     * Get only expiring leases (within 90 days) - optimized query
     */
    public function getExpiringLeases()
    {
        try {
            $today = now();
            $in90Days = now()->addDays(90);
            
            $leases = LeaseAgreement::with(['tenant:id,names', 'property:id,property_name', 'unit:id,unit_code'])
                ->where('current_status_id', 5) // Only active leases
                ->whereBetween('date_end', [$today, $in90Days])
                ->select([
                    'id',
                    'tenant_id',
                    'property_id',
                    'unit_id',
                    'ref_number',
                    'date_end',
                    'current_status_id'
                ])
                ->orderBy('date_end', 'asc')
                ->get()
                ->map(function ($lease) {
                    $daysToExpiry = now()->diffInDays($lease->date_end, false);
                    
                    return [
                        'id' => $lease->id,
                        'encrypted_id' => Crypt::encrypt($lease->id),
                        'ref_number' => $lease->ref_number,
                        'tenant_name' => $lease->tenant->names ?? 'N/A',
                        'encrypted_tenant_id' => $lease->tenant_id ? Crypt::encrypt($lease->tenant_id) : null,
                        'property_name' => $lease->property->property_name ?? 'N/A',
                        'unit_code' => $lease->unit->unit_code ?? 'N/A',
                        'date_end' => $lease->date_end,
                        'days_to_expiry' => round($daysToExpiry),
                        'urgency' => $daysToExpiry <= 7 ? 'CRITICAL' : ($daysToExpiry <= 30 ? 'URGENT' : ($daysToExpiry <= 60 ? 'MODERATE' : 'LOW'))
                    ];
                });

            return response()->json(['data' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading expiring leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load expiring leases'], 500);
        }
    }

    /**
     * Get tenants without leases (AJAX)
     */
    public function getTenantsWithoutLeases()
    {
        try {
            // Get all tenant IDs that have leases
            $tenantsWithLeases = LeaseAgreement::pluck('tenant_id')->unique();
            Log::info('Tenants with leases: ' . $tenantsWithLeases->implode(', '));
    
            // Get all tenants (reference_number starts with TEN-) that are NOT in the leases table
            $tenantsWithoutLeases = Client::where('reference_number', 'LIKE', 'TEN-%')
                ->whereNotIn('id', $tenantsWithLeases)
                ->select(['id', 'names', 'reference_number', 'mobile_number', 'email_address'])
                ->orderBy('names', 'asc')
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'encrypted_id' => Crypt::encrypt($tenant->id),
                        'names' => $tenant->names,
                        'reference_number' => $tenant->reference_number,
                        'mobile_number' => $tenant->mobile_number ?? 'N/A',
                        'email_address' => $tenant->email_address ?? 'N/A',
                    ];
                });
            Log::info('Tenants without leases: ' . $tenantsWithoutLeases->pluck('id')->implode(', '));
    
            return response()->json(['data' => $tenantsWithoutLeases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants without leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tenants'], 500);
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
                
                // Create new lease with updated dates and terms
                $startDate = new \DateTime($oldLease->date_end);
                $startDate->modify('+1 day'); // Start the day after old lease ends
                
                $endDate = clone $startDate;
                $endDate->modify('+' . $validated['years'] . ' years');

                $newLease = LeaseAgreement::create([
                    'date_time' => now(),
                    'client_id' => $oldLease->client_id,
                    'property_id' => $oldLease->property_id,
                    'unit_id' => $oldLease->unit_id,
                    'tenant_id' => $oldLease->tenant_id,
                    'date_start' => $startDate->format('Y-m-d'),
                    'date_end' => $endDate->format('Y-m-d'),
                    'years' => $validated['years'],
                    'sla_id' => $oldLease->sla_id,
                    'zone_id' => $oldLease->zone_id,
                    'current_status_id' => 1,
                    'auto_renewal' => $oldLease->auto_renewal,
                    'initial_water_meter_reading' => $oldLease->initial_water_meter_reading,
                    'escalation_rate' => $validated['escalation_rate'],
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
                    $escalatedAmount = $item->amount * (1 + ($validated['escalation_rate'] / 100));
                    
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
     * Store a newly created lease agreement
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'sla_ref' => 'required|exists:sys_pm_service_agreements,id',
                'unit_id' => 'required|exists:sys_pm_units,id',
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'linked_lease' => 'nullable|exists:sys_pm_lease_agreements,id',
                'date_start' => 'required|date',
                'years' => 'required|integer|min:1|max:10',
                'auto_renewal' => 'required|in:Yes,No',
                'escalation_rate' => 'required|numeric|min:0|max:100',
                'escalation_frequency' => 'required|in:Annually,Semi-Annually,Quarterly,Monthly',
                'documents.*' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'invoicable_items' => 'required|array|min:1',
                'invoicable_items.*.item' => 'required|exists:sys_pm_invoiceable_items,id',
                'invoicable_items.*.amount' => 'required|numeric|min:0',
                'invoicable_items.*.cycle' => 'required|in:Monthly,Quarterly,Semi-Annually,Annually,One-Time',
                'invoicable_items.*.prepay_client' => 'nullable|in:Yes',
            ]);

            // Calculate end date
            $startDate = new \DateTime($validated['date_start']);
            $endDate = clone $startDate;
            $endDate->modify('+' . $validated['years'] . ' years');

            // Get property to retrieve zone_id
            $property = Property::findOrFail($validated['property_id']);

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

            // Create the Lease Agreement
            $leaseAgreement = LeaseAgreement::create([
                'date_time' => now(),
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_id'],
                'tenant_id' => $validated['tenant_id'],
                'sla_id' => $validated['sla_ref'],
                'zone_id' => $property->zone_id ?? null,
                'date_start' => $validated['date_start'],
                'date_end' => $endDate->format('Y-m-d'),
                'years' => $validated['years'],
                'auto_renewal' => $validated['auto_renewal'],
                'escalation_rate' => $validated['escalation_rate'],
                'escalation_frequency' => $validated['escalation_frequency'],
                'linked_lease_id' => $validated['linked_lease'] ?? null,
                'initial_water_meter_reading' => $unit->initial_water_meter_reading ?? 0,
                'created_by_id' => Auth::id(),
                'current_status_id' => 1, // Active status
                'lease_status' => 'Active',
                'ref_number' => null, // Will be updated below
            ]);

            // Generate and update reference number after getting the ID
            $leaseAgreement->ref_number = $this->generateLeaseReferenceNumber(
                $property->reference_number,
                $validated['unit_id'],
                $leaseAgreement->id
            );
            $leaseAgreement->save();

            // Store documents in separate table
            if ($hasDocuments && $request->hasFile('documents')) {
                $this->storeLeaseDocuments($leaseAgreement->id, $request->file('documents'));
            }

            // Store Invoiceable Items (repeater data)
            if ($request->has('invoicable_items') && is_array($request->invoicable_items)) {
                $this->storeInvoiceableItems($leaseAgreement->id, $request->invoicable_items);
            }

            // Update unit occupancy status to "Occupied"
            $unit->occupancy_status_id = 38; // Occupied status
            $unit->save();

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

            return redirect()
                ->route('property.properties.show', Crypt::encrypt($validated['property_id']))
                ->with('success', 'Lease Agreement created successfully with reference: ' . $leaseAgreement->ref_number);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating lease agreement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create lease agreement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Store Invoiceable Items for the lease
     * 
     * @param int $leaseAgreementId
     * @param array $invoiceableItems
     */
    protected function storeInvoiceableItems($leaseAgreementId, $invoiceableItems)
    {
        foreach ($invoiceableItems as $item) {
            if (isset($item['item']) && isset($item['amount']) && $item['item'] !== '') {
                // Get the invoiceable item to retrieve commissionable and vatable info
                $invoiceableItem = \App\Models\InvoiceableItem::find($item['item']);
                
                LeaseAgreementInvoiceableItem::create([
                    'parent_id' => $leaseAgreementId,
                    'item_id' => $item['item'],
                    'amount' => $item['amount'],
                    'cycle_id' => $item['cycle'],
                    'commissionable' => $invoiceableItem->commissionable ?? 'No',
                    'comm_per' => $invoiceableItem->commissionable === 'Yes' ? ($invoiceableItem->commission_percentage ?? 0) : 0,
                    'vatable' => $invoiceableItem->vatable ?? 'No',
                    'prepay_client' => isset($item['prepay_client']) && $item['prepay_client'] === 'Yes' ? 'Yes' : 'No',
                ]);
            }
        }
    
        Log::info('Invoiceable items stored successfully', [
            'lease_agreement_id' => $leaseAgreementId,
            'items_count' => count($invoiceableItems)
        ]);
    }

    /**
     * Generate a unique reference number for the lease
     * Format: {PropertyRef}-{UnitNumber}-L{sequence}
     * Example: PROP-001-U01-L00025
     * 
     * @param string $propertyReference
     * @param string $unitNumber
     * @param int $leaseAgreementId
     * @return string
     */
    protected function generateLeaseReferenceNumber($propertyReference, $unitNumber, $leaseAgreementId)
    {
        // Get the unit to access its unit_code
        $unit = Unit::find($unitNumber);
        
        // Format: UnitCode-L00025 (5 digits, zero-padded)
        return sprintf('%s-L%05d', $unit->unit_code, $leaseAgreementId);
    }

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
                    'parans' => json_encode([
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
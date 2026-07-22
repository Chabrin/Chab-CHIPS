<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Property;
use App\Models\ReturnType;
use Illuminate\Http\Request;
use App\Models\InvoiceableItem;
use App\Models\ServiceAgreement;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\ServiceAgreementDocument;
use App\Models\ServiceAgreementBillCharge;
use App\Models\ServiceAgreementReturnItem;
use App\Models\ServiceAgreementReturnType;
use App\Models\ServiceAgreementInvoiceClient;
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
            $returnTypes = ReturnType::all();

            // If client parameter is provided, decrypt it and load related data
            if ($request->has('client')) {
                $clientId = Crypt::decrypt($request->client);
                $client = Client::findOrFail($clientId);
                $properties = Property::where('client_id', $client->id)->get();
            }
            
            return view('property.properties.sla.create', compact('properties', 'client', 'returnTypes'));
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
            $sla = ServiceAgreement::where('property_id', $propertyId)
                // ->where('current_status_id', 2)
                ->orderBy('date_start', 'desc')
                ->get(['id', 'reference_number', 'date_start', 'date_end', 'commission_percentage', 'move_in_inspection_required']);
            
            return response()->json(['sla' => $sla]);
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
     * Store a newly created service agreement
     */
    public function store(Request $request)
    {
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
                'date_start'                   => 'required|date',
                'duration_years'               => 'required|integer|min:1',
                'move_in_inspection_required'  => 'nullable|boolean',
                'sla_documents.*'              => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'invoice_charges'              => 'nullable|array',
                'billing_charges'              => 'nullable|array',
                'return_groups'                => 'nullable|array',
            ]);

            // Calculate end date
            $startDate = new \DateTime($validated['date_start']);
            $endDate = clone $startDate;
            $endDate->modify('+' . $validated['duration_years'] . ' years');

            // Handle file uploads
            $documentPaths = [];
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
                'date_start'                  => $validated['date_start'],
                'date_end'                    => $endDate->format('Y-m-d'),
                'years'                       => $validated['duration_years'],
                'landlord_payment_date'       => $validated['payment_date'],
                'auto_renewal'                => $validated['auto_renewal'],
                'created_by_id'               => Auth::id(),
                'current_status_id'           => 1, // Pending — awaiting maker-checker approval
                'reference_number'            => null,
                'move_in_inspection_required' => !empty($validated['move_in_inspection_required']),
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
            // Store Invoice Charges (repeater data)
            if ($request->has('invoice_charges') && is_array($request->invoice_charges)) {
                $this->storeInvoiceCharges($serviceAgreement->id, $request->invoice_charges);
                Log::info('Invoice charges stored for SLA ID: ' . $serviceAgreement->id);
            }

            Log::info('Storing additional SLA data for SLA ID: ' . $serviceAgreement->id);
            // Store Billing Charges (repeater data)
            if ($request->has('billing_charges') && is_array($request->billing_charges)) {
                $this->storeBillingCharges($serviceAgreement->id, $request->billing_charges);
                Log::info('Billing charges stored for SLA ID: ' . $serviceAgreement->id);
            }

            Log::info('Storing return groups for SLA ID: ' . $serviceAgreement->id);
            // Store Return Groups (repeater data)
            if ($request->has('return_groups') && is_array($request->return_groups)) {
                $this->storeReturnGroups($serviceAgreement->id, $request->return_groups);
                Log::info('Return groups stored for SLA ID: ' . $serviceAgreement->id);
            }

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
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating service agreement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create service agreement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Store Invoice Charges (form repeater data)
     * 
     * @param int $serviceAgreementId
     * @param array $invoiceCharges
     */
    protected function storeInvoiceCharges($serviceAgreementId, $invoiceCharges)
    {
        foreach ($invoiceCharges as $charge) {
            if (isset($charge['type']) && isset($charge['amount']) && $charge['type'] !== '') {
                ServiceAgreementInvoiceClient::create([
                    'parent_id' => $serviceAgreementId,
                    'item_id' => $charge['type'], // This is the invoiceable item ID
                    'amount' => $charge['amount'],
                ]);
            }
        }
        
        Log::info('Invoice charges stored successfully', [
            'service_agreement_id' => $serviceAgreementId,
            'charges_count' => count($invoiceCharges)
        ]);
    }

    /**
     * Store Billing Charges (form repeater data)
     * 
     * @param int $serviceAgreementId
     * @param array $billingCharges
     */
    protected function storeBillingCharges($serviceAgreementId, $billingCharges)
    {
        foreach ($billingCharges as $charge) {
            if (isset($charge['item']) && $charge['item'] !== '') {
                // Get the invoiceable item to retrieve commission percentage
                $invoiceableItem = InvoiceableItem::find($charge['item']);
                
                ServiceAgreementBillCharge::create([
                    'parent_id' => $serviceAgreementId,
                    'item_id' => $charge['item'],
                    'cost_per_unit' => $charge['cost'] ?? 0,
                    'commissionable' => $charge['commissionable'] ?? 'No',
                    'comm_percentage' => $invoiceableItem->commissionable ?? 0,
                ]);
            }
        }
        
        Log::info('Billing charges stored successfully', [
            'service_agreement_id' => $serviceAgreementId,
            'charges_count' => count($billingCharges)
        ]);
    }

    /**
     * Store Return Groups (form repeater data)
     * 
     * @param int $serviceAgreementId
     * @param array $returnGroups
     */
    protected function storeReturnGroups($serviceAgreementId, $returnGroups)
    {
        foreach ($returnGroups as $group) {
            if (isset($group['return_type']) && $group['return_type'] !== '') {
                // First, create the return type record
                $returnTypeRecord = ServiceAgreementReturnType::create([
                    'parent_id' => $serviceAgreementId,
                    'return_type_id' => $group['return_type'],
                    'params' => null,
                ]);
                
                // Then, store selected items for this return group
                if (isset($group['items']) && is_array($group['items'])) {
                    foreach ($group['items'] as $itemId) {
                        ServiceAgreementReturnItem::create([
                            'parent_id' => $returnTypeRecord->id, // Link to the return type record
                            'return_items' => $itemId,
                            'params' => null,
                        ]);
                    }
                }
            }
        }
        
        Log::info('Return groups stored successfully', [
            'service_agreement_id' => $serviceAgreementId,
            'groups_count' => count($returnGroups)
        ]);
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
                'invoiceClients.item',
                'billCharges.item',
                'returnTypes.returnType',
                'returnTypes.invoiceableItems.invoiceableItem',
            ])->findOrFail($id);

            return view('property.properties.sla.details', compact('sla'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLA details: ' . $e->getMessage());
            return redirect()->route('service.agreements.index')->with('error', 'Failed to load service agreement details');
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
                'invoiceClients.item',
                'billCharges.item',
                'returnTypes.returnType',
                'returnTypes.invoiceableItems.invoiceableItem',
            ])->findOrFail($id);

            // dd( $sla );

            $client = $sla->client;
            $properties = Property::where('client_id', $client->id)->get();
            $returnTypes = ReturnType::all();
    
            return view('property.properties.sla.edit', compact('sla', 'properties', 'client', 'returnTypes'));
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
                'date_start'                  => 'required|date',
                'duration_years'              => 'required|integer|min:1',
                'move_in_inspection_required' => 'nullable|boolean',
                'sla_documents.*'             => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'invoice_charges'             => 'nullable|array',
                'billing_charges'             => 'nullable|array',
                'return_groups'               => 'nullable|array',
            ]);
    
            // Calculate end date
            $startDate = new \DateTime($validated['date_start']);
            $endDate = clone $startDate;
            $endDate->modify('+' . $validated['duration_years'] . ' years');
    
            // Handle file uploads (new documents)
            if ($request->hasFile('sla_documents')) {
                foreach ($request->file('sla_documents') as $document) {
                    $path = $document->store('sla_documents', 'public');
                    $serviceAgreement->serviceAgreementDocuments()->create([
                        'sla_documents' => $path,
                        'params' => null,
                    ]);
                }
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
    
            // Update the Service Agreement
            $serviceAgreement->update([
                'client_d'                    => $validated['client_id'],
                'property_id'                 => $validated['property_id'],
                'zone_id'                     => $property->zone_id ?? null,
                'commission_percentage'        => $validated['commission_percentage'],
                'vat'                         => $vatValue,
                'vat_per'                     => $validated['vat_percentage'] ?? 0,
                'flat_rate_vat'               => $validated['flat_rate_vat'] ?? 0,
                'date_start'                  => $validated['date_start'],
                'date_end'                    => $endDate->format('Y-m-d'),
                'years'                       => $validated['duration_years'],
                'landlord_payment_date'       => $validated['payment_date'],
                'auto_renewal'                => $validated['auto_renewal'],
                'move_in_inspection_required' => !empty($validated['move_in_inspection_required']),
            ]);
    
            // Delete and recreate Invoice Charges
            ServiceAgreementInvoiceClient::where('parent_id', $serviceAgreement->id)->delete();
            if ($request->has('invoice_charges') && is_array($request->invoice_charges)) {
                $this->storeInvoiceCharges($serviceAgreement->id, $request->invoice_charges);
            }
    
            // Delete and recreate Billing Charges
            ServiceAgreementBillCharge::where('parent_id', $serviceAgreement->id)->delete();
            if ($request->has('billing_charges') && is_array($request->billing_charges)) {
                $this->storeBillingCharges($serviceAgreement->id, $request->billing_charges);
            }
    
            // Delete and recreate Return Groups
            $existingReturnTypes = ServiceAgreementReturnType::where('parent_id', $serviceAgreement->id)->get();
            foreach ($existingReturnTypes as $returnType) {
                ServiceAgreementReturnItem::where('parent_id', $returnType->id)->delete();
            }
            ServiceAgreementReturnType::where('parent_id', $serviceAgreement->id)->delete();
            if ($request->has('return_groups') && is_array($request->return_groups)) {
                $this->storeReturnGroups($serviceAgreement->id, $request->return_groups);
            }
    
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
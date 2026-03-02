<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\LeaseAgreement;
use App\Models\TenantMovement;
use App\Models\InvoiceableItem;
use App\Models\ServiceAgreement;
use App\Services\ErrorDispatcher;
use App\Models\TenantMovementItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\LeaseAgreementInvoiceableItem;

class TenantMovementController extends Controller
{
    /**
     * Display a listing of tenant movements
     */
    public function index()
    {
        try {
            return view('property.properties.movement.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading movements index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load movements');
        }
    }

    /**
     * Get all tenant movements (AJAX)
     */
    public function getData()
    {
        try {
            $canViewDetails = Auth::user()->isAbleTo('read_tenant_movements');
            $canEditMovement = Auth::user()->isAbleTo('update_tenant_movements');

            $movements = TenantMovement::with([
                'tenant:id,names',
                'property:id,property_name',
                'unitFrom:id,unit_code',
                'unitTo:id,unit_code',
                'lease:id,ref_number',
                'createdBy:id,name'
            ])
            ->select([
                'id',
                'reference_number',
                'date_time',
                'tenant_id',
                'zone_id',
                'client_id',
                'property_id',
                'unit_from_id',
                'lease_id',
                'new_lease_id',
                'unit_to_id',
                'new_lease_end_date',
                'new_lease_start_date',
                'created_by',
                'current_status_id',
                'current_balance'
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($movement) use ($canViewDetails, $canEditMovement) {
                $newLease = null;
                if ($movement->new_lease_id) {
                    $newLease = LeaseAgreement::select('ref_number')->find($movement->new_lease_id);
                }
                
                return [
                    'id' => $movement->id,
                    'reference_number' => $movement->reference_number ?? 'N/A',
                    'date_time' => $movement->date_time,
                    'tenant_name' => $movement->tenant->names ?? 'N/A',
                    'zone_name' => $movement->zone_id ?? 'N/A',
                    'client_name' => $movement->client_id ?? 'N/A',
                    'property_name' => $movement->property->property_name ?? 'N/A',
                    'unit_from_code' => $movement->unitFrom->unit_code ?? 'N/A',
                    'lease_ref' => $movement->lease->ref_number ?? 'N/A',
                    'new_lease_ref' => $newLease ? $newLease->ref_number : 'Pending',
                    'unit_to_code' => $movement->unitTo->unit_code ?? 'N/A',
                    'new_lease_end_date' => $movement->new_lease_end_date,
                    'new_lease_start_date' => $movement->new_lease_start_date,
                    'created_by_name' => $movement->createdBy->name ?? 'N/A',
                    'current_status_id' => $movement->current_status_id,
                    'current_balance' => $movement->current_balance,
                    'can_view_details' => $canViewDetails,
                    'can_edit_movement' => $canEditMovement,
                ];
            });

            return response()->json(['data' => $movements]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading movements data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load movements'], 500);
        }
    }

    /**
     * Show the form for creating a new movement
     */
    public function create()
    {
        try {
            return view('property.properties.movement.create');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading movement create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Show the form for editing a movement
     */
    public function edit($id)
    {
        try {
            $movement = TenantMovement::with([
                'tenant:id,names',
                'client:id,names,reference_number',
                'sla:id,reference_number',
                'property:id,property_name,reference_number,zone_id',
                'zone:id,zone_name',
                'unitFrom:id,unit_code,unit_name',
                'unitTo:id,unit_code,unit_name',
                'lease:id,ref_number',
                'newLease:id,ref_number',
                'items.item:id,item_name',
                'items.cycle:id,cycle_name',
                'createdBy:id,name'
            ])->findOrFail($id);


            return view('property.properties.movement.edit', compact('movement'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading movement edit form: ' . $e->getMessage());
            return redirect()->route('property.movements.index')->with('error', 'Failed to load movement');
        }
    }

    /**
     * Display the specified movement details
     */
    public function show($id)
    {
        try {
            $movement = TenantMovement::with([
                'tenant:id,names',
                'client:id,names,reference_number',
                'sla:id,reference_number',
                'property:id,property_name,reference_number,zone_id',
                'zone:id,zone_name',
                'unitFrom:id,unit_code,unit_name',
                'unitTo:id,unit_code,unit_name',
                'lease:id,ref_number',
                'newLease:id,ref_number',
                'items.item:id,item_name',
                'createdBy:id,name'
            ])->findOrFail($id);

            $tenantBalance = $movement->tenant_id
                ? \App\Services\TenantBalanceService::getBalance((int) $movement->tenant_id)
                : null;

            return view('property.properties.movement.details', compact('movement', 'tenantBalance'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading movement details: ' . $e->getMessage());
            return redirect()->route('property.movements.index')->with('error', 'Failed to load movement details');
        }
    }

    /**
     * Get SLAs for a client
     */
    public function getSLAsByClient($clientId)
    {
        try {
            $slas = ServiceAgreement::where('client_d', $clientId)
                ->select(['id', 'reference_number'])
                ->get()
                ->map(function ($sla) {
                    return [
                        'id' => $sla->id,
                        'reference_number' => $sla->reference_number
                    ];
                });

            return response()->json(['slas' => $slas]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading SLAs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load SLAs'], 500);
        }
    }

    /**
     * Get properties for an SLA
     */
    public function getPropertiesBySLA($slaId)
    {
        try {
            $sla = ServiceAgreement::findOrFail($slaId);

            $properties = Property::where('id', $sla->property_id)
                ->select(['id', 'property_name', 'reference_number', 'zone_id'])
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'property_name' => $property->property_name,
                        'reference_number' => $property->reference_number,
                        'zone_id' => $property->zone_id,
                    ];
                });

            return response()->json(['properties' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get occupied units with active leases for a property
     */
    public function getOccupiedUnits($propertyId)
    {
        try {
            // Get occupied units (occupancy_status_id = 38) with their active leases
            $units = Unit::where('property_id', $propertyId)
                ->where('occupancy_status_id', 38) // Occupied
                ->with(['leaseAgreements' => function($query) {
                    $query->where('current_status_id', 5) // Active leases
                        ->with('tenant:id,names')
                        ->select('id', 'ref_number', 'unit_id', 'tenant_id');
                }])
                ->select(['id', 'unit_code', 'unit_name'])
                ->get()
                ->filter(function($unit) {
                    return $unit->leaseAgreements->isNotEmpty();
                })
                ->map(function ($unit) {
                    $activeLease = $unit->leaseAgreements->first();
                    return [
                        'id' => $unit->id,
                        'unit_code' => $unit->unit_code ?? $unit->unit_name,
                        'unit_name' => $unit->unit_name,
                        'lease_id' => $activeLease->id,
                        'lease_ref' => $activeLease->ref_number,
                        'tenant_id' => $activeLease->tenant_id,
                        'tenant_name' => $activeLease->tenant->names ?? 'N/A',
                    ];
                });

            return response()->json(['units' => $units->values()]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading occupied units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Get vacant units for a property
     */
    public function getVacantUnits($propertyId)
    {
        try {
            // Get units that are either vacant (occupancy_status_id != 38) or occupied but available for movement
            $units = Unit::where('property_id', $propertyId)
                ->where('occupancy_status_id', 31) // Not occupied
                ->select(['id', 'unit_code', 'unit_name'])
                ->get()
                ->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'unit_code' => $unit->unit_code ?? $unit->unit_name,
                        'unit_name' => $unit->unit_name,
                    ];
                });

            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading vacant units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Store a newly created movement
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'lease_id' => 'required|exists:sys_pm_lease_agreements,id',
                'sla_id' => 'required|exists:sys_pm_service_agreements,id',
                'unit_from_id' => 'required|exists:sys_pm_units,id',
                'unit_to_id' => 'required|exists:sys_pm_units,id',
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'zone_id' => 'nullable',
                'current_balance' => 'required|numeric|min:0',
                'new_lease_end_date' => 'required|date',
                'new_lease_start_date' => 'required|date',
                'invoicable_items' => 'required|array|min:1',
                'invoicable_items.*.item' => 'required|exists:sys_pm_invoiceable_items,id',
                'invoicable_items.*.amount' => 'required|numeric|min:0',
                'invoicable_items.*.cycle' => 'required|in:Monthly,Quarterly,Semi-Annually,Annually,One-Time',
            ]);

            // Get the old lease
            $oldLease = LeaseAgreement::findOrFail($validated['lease_id']);

            // Create tenant movement record
            $movement = TenantMovement::create([
                'date_time' => now(),
                'movement_date' => now(),
                'created_by' => Auth::id(),
                'zone_id' => $validated['zone_id'],
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'unit_from_id' => $validated['unit_from_id'],
                'lease_id' => $validated['lease_id'],
                'unit_to_id' => $validated['unit_to_id'],
                'tenant_id' => $validated['tenant_id'],
                'current_status_id' => 1, // Pending
                'current_balance' => $validated['current_balance'],
                'new_lease_start_date' => $validated['new_lease_start_date'],
                'new_lease_end_date' => $validated['new_lease_end_date'],
                'sla_id' => $validated['sla_id'],
                'reference_number' => $this->generateReferenceNumber(),
            ]);

            // Create new lease agreement
            $property = Property::findOrFail($validated['property_id']);
            $newUnit = Unit::findOrFail($validated['unit_to_id']);

            $startDate = new \DateTime($validated['new_lease_start_date']);
            $endDate = new \DateTime($validated['new_lease_end_date']);
            $years = $endDate->diff($startDate)->y;

            $newLease = LeaseAgreement::create([
                'date_time' => now(),
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_to_id'],
                'tenant_id' => $validated['tenant_id'],
                'date_start' => $validated['new_lease_start_date'],
                'date_end' => $validated['new_lease_end_date'],
                'years' => $years > 0 ? $years : 1,
                'sla_id' => $validated['sla_id'],
                'zone_id' => $validated['zone_id'] ?? $property->zone_id,
                'current_status_id' => 5, // Active
                'auto_renewal' => $oldLease->auto_renewal,
                'initial_water_meter_reading' => $newUnit->initial_water_meter_reading ?? 0,
                'escalation_rate' => $oldLease->escalation_rate ?? 0,
                'escalation_frequency' => $oldLease->escalation_frequency ?? 'Annually',
                'linked_lease_id' => $oldLease->id,
                'created_by_id' => Auth::id(),
                'ref_number' => null,
            ]);

            // Generate and update reference number
            $newLease->ref_number = sprintf('%s-L%05d', $newUnit->unit_code, $newLease->id);
            $newLease->save();

            // Update movement with new lease ID
            $movement->new_lease_id = $newLease->id;
            $movement->save();

            // Store invoiceable items for the movement
            foreach ($request->invoicable_items as $item) {
                if (isset($item['item']) && isset($item['amount']) && $item['item'] !== '') {
                    TenantMovementItem::create([
                        'parent_id' => $movement->id,
                        'item_id' => $item['item'],
                        'amount' => $item['amount'],
                        'cycle_id' => $item['cycle'],
                    ]);

                    // Also add to new lease invoiceable items
                    $invoiceableItem = InvoiceableItem::find($item['item']);
                    LeaseAgreementInvoiceableItem::create([
                        'parent_id' => $newLease->id,
                        'item_id' => $item['item'],
                        'amount' => $item['amount'],
                        'cycle_id' => $item['cycle'],
                        'commissionable' => $invoiceableItem->commissionable ?? 'No',
                        'comm_per' => $invoiceableItem->commissionable === 'Yes' ? ($invoiceableItem->commission_percentage ?? 0) : 0,
                        'vatable' => $invoiceableItem->vatable ?? 'No',
                        'prepay_client' => 'No',
                    ]);
                }
            }

            // Update old lease status to "Moved" or "Terminated"
            $oldLease->current_status_id = 6; // Assuming 6 is terminated/moved status
            $oldLease->save();

            // Update unit statuses
            $oldUnit = Unit::findOrFail($validated['unit_from_id']);
            $oldUnit->occupancy_status_id = 37; // Vacant
            $oldUnit->save();

            $newUnit->occupancy_status_id = 38; // Occupied
            $newUnit->save();

            DB::commit();

            Log::info('Tenant movement created successfully', [
                'movement_id' => $movement->id,
                'reference' => $movement->reference_number,
                'new_lease_id' => $newLease->id,
                'created_by' => Auth::id()
            ]);

            return redirect()
                ->route('property.movements.index')
                ->with('success', 'Tenant movement created successfully. Reference: ' . $movement->reference_number);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating tenant movement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create movement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update an existing tenant movement
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'lease_id' => 'required|exists:sys_pm_lease_agreements,id',
                'sla_id' => 'required|exists:sys_pm_service_agreements,id',
                'unit_from_id' => 'required|exists:sys_pm_units,id',
                'unit_to_id' => 'required|exists:sys_pm_units,id',
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'zone_id' => 'nullable',
                'current_balance' => 'required|numeric|min:0',
                'new_lease_end_date' => 'required|date',
                'new_lease_start_date' => 'required|date',
                'invoicable_items' => 'required|array|min:1',
                'invoicable_items.*.item' => 'required|exists:sys_pm_invoiceable_items,id',
                'invoicable_items.*.amount' => 'required|numeric|min:0',
                'invoicable_items.*.cycle' => 'required|in:Monthly,Quarterly,Semi-Annually,Annually,One-Time',
            ]);

            // Get the movement
            $movement = TenantMovement::findOrFail($id);

            // Update movement record
            $movement->update([
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'sla_id' => $validated['sla_id'],
                'unit_from_id' => $validated['unit_from_id'],
                'unit_to_id' => $validated['unit_to_id'],
                'lease_id' => $validated['lease_id'],
                'tenant_id' => $validated['tenant_id'],
                'zone_id' => $validated['zone_id'],
                'current_balance' => $validated['current_balance'],
                'new_lease_end_date' => $validated['new_lease_end_date'],
                'new_lease_start_date' => $validated['new_lease_start_date'],
            ]);

            // Update invoiceable items - delete old ones and create new ones
            TenantMovementItem::where('parent_id', $movement->id)->delete();
            
            foreach ($request->invoicable_items as $item) {
                TenantMovementItem::create([
                    'parent_id' => $movement->id,
                    'item_id' => $item['item'],
                    'amount' => $item['amount'],
                    'cycle_id' => $item['cycle'],
                ]);
            }

            // If there's an existing new lease, update it with new dates and items
            if ($movement->new_lease_id) {
                $newLease = LeaseAgreement::find($movement->new_lease_id);
                if ($newLease) {
                    $newLease->update([
                        'lease_end_date' => $validated['new_lease_end_date'],
                        'lease_start_date' => $validated['new_lease_start_date'],
                        'unit_id' => $validated['unit_to_id'],
                    ]);

                    // Update lease invoiceable items
                    LeaseAgreementInvoiceableItem::where('lease_id', $newLease->id)->delete();
                    
                    foreach ($request->invoicable_items as $item) {
                        LeaseAgreementInvoiceableItem::create([
                            'lease_id' => $newLease->id,
                            'item_id' => $item['item'],
                            'amount' => $item['amount'],
                            'cycle' => $item['cycle'],
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Tenant movement updated successfully', [
                'movement_id' => $movement->id,
                'reference' => $movement->reference_number,
                'updated_by' => Auth::id()
            ]);

            return redirect()
                ->route('property.movements.index')
                ->with('success', 'Tenant movement updated successfully. Reference: ' . $movement->reference_number);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating tenant movement: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update movement: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Generate a unique reference number
     */
    private function generateReferenceNumber()
    {
        $lastMovement = TenantMovement::orderBy('id', 'desc')->first();
        $nextId = $lastMovement ? $lastMovement->id + 1 : 1;
        return sprintf('%010d', $nextId);
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Inspection;
use App\Models\InspectionUnit;
use App\Models\InspectionPicture;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class InspectionsController extends Controller
{

    public function index()
    {
        return view('property.properties.inspection.index');
    }


    /**
     * Show the form for creating a new property inspection
     */
    public function create(Request $request)
    {
        try {
            // Get all zones for the dropdown
            $zones = Zone::orderBy('zone_name')->get(['id', 'zone_name']);
            
            return view('property.properties.inspection.create', compact('zones'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inspection create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Get properties for a specific zone (AJAX)
     */
    public function getPropertiesByZone($zoneId)
    {
        try {
            $properties = Property::where('zone_id', $zoneId)
                ->where('current_status_id', 2) // Only active properties
                ->orderBy('property_name')
                ->get(['id', 'property_name', 'reference_number', 'description']);
            
            return response()->json(['properties' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties for zone: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get units for a specific property (AJAX)
     */
    // public function getUnitsByProperty($propertyId)
    // {
    //     try {
    //         $units = Unit::where('property_id', $propertyId)
    //             ->where('occupancy_status_id', 31) // Only vacant units
    //             ->orderBy('unit_number')
    //             ->get(['id', 'unit_name', 'unit_number', 'unit_code', 'occupancy_status_id']);
            
    //         // Add status name to response
    //         $units = $units->map(function($unit) {
    //             return [
    //                 'id' => $unit->id,
    //                 'unit_name' => $unit->unit_name,
    //                 'unit_number' => $unit->unit_number,
    //                 'unit_code' => $unit->unit_code,
    //                 'occupancy_status_id' => $unit->occupancy_status_id,
    //                 'status' => $this->getOccupancyStatusName($unit->occupancy_status_id),
    //             ];
    //         });
            
    //         return response()->json(['units' => $units]);
    //     } catch (\Exception $e) {
    //         ErrorDispatcher::dispatch($e);
    //         Log::error('Error loading units for property: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to load units'], 500);
    //     }
    // }

    /**
     * Powers the Unit dropdown on the tenant-create form (property/properties/
     * tenants/create.blade.php), tagging each unit Occupied/Vacant.
     *
     * That tag used to be `occupancy_status_id == 1 ? 'Occupied' : 'Vacant'`,
     * which only recognized the legacy-migration numbering scheme (1 =
     * Occupied) and silently mislabeled every app-native occupied unit (which
     * uses 38) as "Vacant". occupancy_status_id carries two overlapping,
     * conflicting numbering schemes across this codebase and isn't a reliable
     * signal at all — switched to the lease-based check instead (see
     * Unit::activeLeaseAgreement()'s docblock for the full finding, including
     * why current_status_id 1 *and* 5 both count and why there's no date
     * window).
     *
     * Pass ?vacant_only=1 (used by the tenant-create unit picker, which must
     * only ever offer units with no active lease) to apply Unit::scopeVacant()
     * server-side rather than just labeling occupied units client-side — the
     * other callers of this endpoint (lease creation, property details, etc.)
     * still need to see occupied units, so this stays opt-in.
     */
    public function getUnitsByProperty(Request $request, $propertyId)
    {
        try {

            $units = Unit::with(['property', 'zone', 'activeLeaseAgreement'])
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
                ->when($request->boolean('vacant_only'), fn ($query) => $query->vacant())
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
                        'status' => $unit->activeLeaseAgreement ? 'Occupied' : 'Vacant',
                    ];
                });
                

            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            Log::error('Error loading units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Get units for a specific property (AJAX)
     */
    public function getAllUnitsByProperty($propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->orderBy('unit_number')
                ->get(['id', 'unit_name', 'unit_number', 'unit_code', 'occupancy_status_id']);
            
            // Add status name to response
            $units = $units->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'unit_number' => $unit->unit_number,
                    'unit_code' => $unit->unit_code,
                    'occupancy_status_id' => $unit->occupancy_status_id,
                    'status' => $this->getOccupancyStatusName($unit->occupancy_status_id),
                ];
            });
            
            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading units for property: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Helper function to get occupancy status name
     */
    private function getOccupancyStatusName($statusId)
    {
        $statuses = [
            31 => 'Vacant',
            32 => 'Occupied',
            33 => 'Under Maintenance',
            34 => 'Reserved',
        ];
        
        return $statuses[$statusId] ?? 'Unknown';
    }

    /**
     * Helper function to get status ID from status name
     */
    private function getStatusIdFromName($statusName)
    {
        $statuses = [
            'Vacant' => 31,
            'Occupied' => 32,
            'Under Maintenance' => 33,
            'Reserved' => 34,
        ];
        
        return $statuses[$statusName] ?? null;
    }

    /**
     * Store a newly created property inspection
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $validated = $request->validate([
                'zone' => 'nullable|exists:sys_pm_zones,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'visited_datetime' => 'required|date',
                'left_datetime' => 'required|date|after:visited_datetime',
                'unit_statuses' => 'required|array|min:1',
                'unit_statuses.*.unit_id' => 'required|exists:sys_pm_units,id',
                'unit_statuses.*.previous_status' => 'nullable|string',
                'unit_statuses.*.new_status' => 'required|string',
                'unit_statuses.*.date' => 'nullable|date',
                'unit_statuses.*.remarks' => 'required|string',
                'unit_statuses.*.photos.*' => 'nullable|image|max:5120',
            ]);
    
            // Get property to retrieve zone_id and client_id
            $property = Property::findOrFail($validated['property_id']);
            
            // Calculate duration in minutes
            $visitedTime = new \DateTime($validated['visited_datetime']);
            $leftTime = new \DateTime($validated['left_datetime']);
            $duration = $leftTime->diff($visitedTime)->i + ($leftTime->diff($visitedTime)->h * 60);
    
            // Create the Inspection record
            $inspection = Inspection::create([
                'property_id' => $validated['property_id'],
                'zone_id' => $validated['zone'] ?? $property->zone_id,
                'client_id' => $property->client_id ?? null,
                'visited_date_time' => $validated['visited_datetime'],
                'left_date_time' => $validated['left_datetime'],
                'date_time' => now(),
                'duration' => $duration,
                'created_by_id' => Auth::id(),
                'current_status_id' => 1, // Draft/Pending
            ]);
    
            // Store Unit Statuses
            if ($request->has('unit_statuses') && is_array($request->unit_statuses)) {
                $this->storeUnitStatuses($inspection->id, $request->unit_statuses, $request);
            }
    
            DB::commit();
    
            Log::info('Property Inspection created successfully', [
                'inspection_id' => $inspection->id,
                'created_by' => Auth::id()
            ]);
    
            return redirect()
                ->route('property.inspections.index')
                ->with('success', 'Property Inspection created successfully');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating property inspection: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create property inspection: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Store Unit Statuses for the inspection
     * 
     * @param int $inspectionId
     * @param array $unitStatuses
     * @param Request $request
     */
    protected function storeUnitStatuses($inspectionId, $unitStatuses, $request)
    {
        foreach ($unitStatuses as $index => $unitStatus) {
            if (isset($unitStatus['unit_id']) && $unitStatus['unit_id'] !== '') {
                
                // Get status IDs from status names
                $previousStatusId = $this->getStatusIdFromName($unitStatus['previous_status'] ?? '');
                $newStatusId = $this->getStatusIdFromName($unitStatus['new_status']);
                
                // Create unit status record
                $inspectionUnit = InspectionUnit::create([
                    'parent_id' => $inspectionId,
                    'unit_id' => $unitStatus['unit_id'],
                    'previous_status_id' => $previousStatusId,
                    'unit_status_id' => $newStatusId,
                    'current_status_id' => $newStatusId,
                    'event_date' => $unitStatus['date'] ?? now(),
                    'remarks' => $unitStatus['remarks'],
                ]);
                
                // Handle photo uploads for this unit
                if ($request->hasFile("unit_statuses.{$index}.photos")) {
                    foreach ($request->file("unit_statuses.{$index}.photos") as $photo) {
                        if ($photo->isValid()) {
                            $path = $photo->store('inspection_photos', 'public');
                            
                            // Store each photo in the InspectionPicture table
                            InspectionPicture::create([
                                'parent_id' => $inspectionUnit->id,
                                'unit_photos' => $path,
                                'params' => json_encode([
                                    'original_name' => $photo->getClientOriginalName(),
                                    'size' => $photo->getSize(),
                                    'uploaded_at' => now(),
                                ]),
                            ]);
                        }
                    }
                }
                
                // Update the unit's occupancy status to the new status
                $unit = Unit::find($unitStatus['unit_id']);
                if ($unit && $newStatusId) {
                    $unit->occupancy_status_id = $newStatusId;
                    $unit->save();
                }
            }
        }
    
        Log::info('Unit statuses stored successfully', [
            'inspection_id' => $inspectionId,
            'units_count' => count($unitStatuses)
        ]);
    }


    /**
     * Get inspections list for DataTables (AJAX)
     */
    public function list(Request $request)
    {
        try {
            $inspections = Inspection::with([
                'property:id,property_name,reference_number',
                'property.zone:id,zone_name',
                'createdBy:id,name',
                'units'
            ])
            ->orderBy('visited_date_time', 'desc')
            ->get();
    
            $records = $inspections->map(function($inspection) {
                return [
                    'id' => $inspection->id,
                    'encrypted_id' => Crypt::encrypt($inspection->id),
                    'zone' => [
                        'zone_name' => $inspection->property->zone->zone_name ?? '-'
                    ],
                    'property' => [
                        'property_name' => $inspection->property->property_name ?? '-',
                        'reference_number' => $inspection->property->reference_number ?? '-'
                    ],
                    'visited_date_time' => $inspection->visited_date_time,
                    'units_count' => $inspection->units->count(),
                    'created_by' => [
                        'name' => $inspection->createdBy->name ?? '-'
                    ],
                    'current_status_id' => $inspection->current_status_id,
                    'can_read' => true, // Add permission check if needed
                    'can_update' => true, // Add permission check if needed
                    'can_delete' => true, // Add permission check if needed
                ];
            });
    
            return response()->json(['records' => $records]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inspections list: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load inspections'], 500);
        }
    }
    
    /**
     * Display the specified inspection
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $inspection = Inspection::with([
                'property:id,property_name,reference_number,description,client_id',
                'property.zone:id,zone_name',
                'property.client:id,names',
                'zone:id,zone_name',
                'createdBy:id,name',
                'units.unit:id,unit_name,unit_number,unit_code',
                'units.pictures'
            ])->findOrFail($id);
            
            return view('property.properties.inspection.details', compact('inspection'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inspection details: ' . $e->getMessage());
            return redirect()->route('property.inspections.index')->with('error', 'Inspection not found');
        }
    }

    /**
     * Show the form for editing the specified inspection
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $inspection = Inspection::with([
                'property:id,property_name,reference_number,zone_id,client_id',
                'property.zone:id,zone_name',
                'zone:id,zone_name',
                'units.unit:id,unit_name,unit_number,unit_code,occupancy_status_id',
                'units.pictures'
            ])->findOrFail($id);
            
            // Get all zones for the dropdown
            $zones = Zone::orderBy('zone_name')->get(['id', 'zone_name']);
            
            return view('property.properties.inspection.edit', compact('inspection', 'zones'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inspection edit form: ' . $e->getMessage());
            return redirect()->route('property.inspections.index')->with('error', 'Inspection not found');
        }
    }

    /**
     * Update the specified inspection
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $inspection = Inspection::findOrFail($id);
            
            // Validate the request
            $validated = $request->validate([
                'zone' => 'nullable|exists:sys_pm_zones,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'visited_datetime' => 'required|date',
                'left_datetime' => 'required|date|after:visited_datetime',
                'unit_statuses' => 'required|array|min:1',
                'unit_statuses.*.unit_id' => 'required|exists:sys_pm_units,id',
                'unit_statuses.*.previous_status' => 'nullable|string',
                'unit_statuses.*.new_status' => 'required|string',
                'unit_statuses.*.date' => 'nullable|date',
                'unit_statuses.*.remarks' => 'required|string',
                'unit_statuses.*.photos.*' => 'nullable|image|max:5120',
            ]);
    
            // Get property to retrieve zone_id and client_id
            $property = Property::findOrFail($validated['property_id']);
            
            // Calculate duration in minutes
            $visitedTime = new \DateTime($validated['visited_datetime']);
            $leftTime = new \DateTime($validated['left_datetime']);
            $duration = $leftTime->diff($visitedTime)->i + ($leftTime->diff($visitedTime)->h * 60);
    
            // Update the Inspection record
            $inspection->update([
                'property_id' => $validated['property_id'],
                'zone_id' => $validated['zone'] ?? $property->zone_id,
                'client_id' => $property->client_id ?? null,
                'visited_date_time' => $validated['visited_datetime'],
                'left_date_time' => $validated['left_datetime'],
                'duration' => $duration,
            ]);
    
            // Delete existing unit statuses and their pictures
            foreach ($inspection->units as $unit) {
                InspectionPicture::where('parent_id', $unit->id)->delete();
            }
            InspectionUnit::where('parent_id', $inspection->id)->delete();
            
            // Store new Unit Statuses
            if ($request->has('unit_statuses') && is_array($request->unit_statuses)) {
                $this->storeUnitStatuses($inspection->id, $request->unit_statuses, $request);
            }
    
            DB::commit();
    
            Log::info('Property Inspection updated successfully', [
                'inspection_id' => $inspection->id,
                'updated_by' => Auth::id()
            ]);
    
            return redirect()
                ->route('property.inspection.show', Crypt::encrypt($inspection->id))
                ->with('success', 'Property Inspection updated successfully');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating property inspection: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update property inspection: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Delete an inspection
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $inspection = Inspection::findOrFail($id);
            
            // Delete related records
            foreach ($inspection->units as $unit) {
                // Delete pictures for each unit
                InspectionPicture::where('parent_id', $unit->id)->delete();
            }
            
            // Delete inspection units
            InspectionUnit::where('parent_id', $inspection->id)->delete();
            
            // Delete inspection
            $inspection->delete();
            
            DB::commit();
            
            Log::info('Property Inspection deleted successfully', [
                'inspection_id' => $id,
                'deleted_by' => Auth::id()
            ]);
            
            return response()->json(['success' => true, 'message' => 'Inspection deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting inspection: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete inspection'], 500);
        }
    }
}
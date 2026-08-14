<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Property;
use App\Models\UnitDocument;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class UnitsController extends Controller
{
    // In PropertyController or appropriate controller
    public function getUnits($propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->where('occupancy_status_id', 31) // Only vacant units
                ->whereDoesntHave('leaseAgreements') // No leases at all
                ->orderBy('unit_number')
                ->get(['id', 'unit_name', 'unit_number', 'current_status_id']);

            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Get non-occupied units for a property, for lease creation (AJAX).
     *
     * occupancy_status_id carries two overlapping numbering schemes in the data:
     *   - Legacy-migrated units: 1 = Occupied, 2 = Vacant, 3 = unclassified (from
     *     the migration's `CASE WHEN USTATUS=1 THEN 1 WHEN USTATUS=2 THEN 2 ELSE 3 END`)
     *   - Units created directly in this app: 31 = Vacant, 38 = Occupied
     * Excluding both "Occupied" values covers units from either origin.
     */
    public function getAvailableUnits($propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->whereNotIn('occupancy_status_id', [1, 38])
                ->orderBy('unit_number')
                ->get(['id', 'unit_name', 'unit_number', 'unit_code', 'occupancy_status_id']);

            $units = $units->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'unit_number' => $unit->unit_number,
                    'unit_code' => $unit->unit_code,
                    'occupancy_status_id' => $unit->occupancy_status_id,
                    'status' => $this->occupancyStatusLabel($unit->occupancy_status_id),
                ];
            });

            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading available units for property: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    private function occupancyStatusLabel($statusId)
    {
        if (in_array((int) $statusId, [1, 38], true)) {
            return 'Occupied';
        }
        if (in_array((int) $statusId, [2, 31], true)) {
            return 'Vacant';
        }
        return 'Available';
    }

    /**
     * Update the specified unit (AJAX only — the edit form is a modal)
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->hasPermission('update_units')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $unitId = Crypt::decrypt($id);
            $unit = Unit::findOrFail($unitId);

            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'occupancy_status_id' => 'nullable|integer',
                'unit_number' => 'required|string|max:255',
                'unit_type_id' => 'required|integer',
                'usage_type_id' => 'required|integer',
                'unit_condition_id' => 'required|string|max:255',
                'unit_name' => 'nullable|string|max:255',
                'topology_id' => 'nullable|integer',
                'category_id' => 'nullable|integer',
                'vat_able' => 'nullable',
                'rent_amount' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'initial_water_meter_reading' => 'nullable|numeric|min:0',
            ]);

            // Get zone_id from property
            $property = Property::findOrFail($validated['property_id']);
            $zone_id = $property->zone_id;

            // Update the unit record
            $unit->update([
                'property_id' => $validated['property_id'],
                'zone_id' => $zone_id,
                'unit_number' => $validated['unit_number'],
                'unit_type_id' => $validated['unit_type_id'],
                'usage_type_id' => $validated['usage_type_id'],
                'unit_condition_id' => $validated['unit_condition_id'],
                'unit_name' => $validated['unit_name'] ?? null,
                'topology_id' => $validated['topology_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'vat_able' => $validated['vat_able'] ?? 0,
                'rent_amount' => $validated['rent_amount'] ?? 0.00,
                'description' => $validated['description'] ?? null,
                'initial_water_meter_reading' => $validated['initial_water_meter_reading'] ?? 0,
                'occupancy_status_id' => $validated['occupancy_status_id'] ?? $unit->occupancy_status_id,
                'current_status_id' => $validated['occupancy_status_id'] ?? $unit->current_status_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Unit updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating unit: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update unit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created unit
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasPermission('create_units')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'occupancy_status_id' => 'nullable|integer',
                'unit_number' => 'required|string|max:255',
                'unit_type_id' => 'required|integer',
                'usage_type_id' => 'required|integer',
                'unit_condition_id' => 'required|string|max:255',
                'unit_name' => 'nullable|string|max:255',
                'topology_id' => 'nullable|integer',
                'category_id' => 'nullable|integer',
                'vat_able' => 'nullable',
                'rent_amount' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'initial_water_meter_reading' => 'nullable|numeric|min:0',
            ]);

            // Generate unit code
            $property = Property::findOrFail($validated['property_id']);
            $unitCode = Unit::generateUnitCode($property, $validated['unit_number']);
            $zone_id = $property->zone_id;

            // Create the unit record
            $unit = Unit::create([
                'property_id' => $validated['property_id'],
                'client_id' => $validated['client_id'],
                'zone_id' => $zone_id,
                'unit_code' => $unitCode,
                'unit_number' => $validated['unit_number'],
                'unit_type_id' => $validated['unit_type_id'],
                'usage_type_id' => $validated['usage_type_id'],
                'unit_condition_id' => $validated['unit_condition_id'],
                'unit_name' => $validated['unit_name'] ?? null,
                'topology_id' => $validated['topology_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'vat_able' => $validated['vat_able'] ?? 0,
                'rent_amount' => $validated['rent_amount'] ?? 0.00,
                'description' => $validated['description'] ?? null,
                'initial_water_meter_reading' => $validated['initial_water_meter_reading'] ?? 0,
                'date_time' => now(),
                'created_by_id' => Auth::id(),
                'occupancy_status_id' => $validated['occupancy_status_id'] ?? 31, // Default to vacant
                'current_status_id' => 1, // Pending — awaiting maker-checker approval
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Unit created successfully with code: ' . $unitCode,
                'unit' => [
                    'id' => $unit->id,
                    'unit_code' => $unit->unit_code,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating unit: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to create unit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a unit document via AJAX
     */
    public function deleteDocument($id)
    {
        try {
            $document = UnitDocument::findOrFail($id);
            
            // Delete physical file
            if (Storage::disk('public')->exists($document->unit_uploads)) {
                Storage::disk('public')->delete($document->unit_uploads);
            }
            
            $document->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting unit document: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document'
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Client;
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
     * Get units for a property, for lease creation (AJAX). Returns every unit
     * (not just vacant ones) so an already-assigned-but-not-yet-leased unit
     * still shows up with its tenant attached — the Lease creation page picks
     * the tenant from the chosen unit rather than a separate tenant search.
     *
     * Used to exclude occupied units via
     * `whereNotIn('occupancy_status_id', [1, 38])`, covering the two
     * conflicting numbering schemes that column carries in this codebase
     * (legacy-migrated units: 1=Occupied/2=Vacant/3=unclassified; app-native
     * units: 31=Vacant/38=Occupied). Replaced with the lease-based check
     * (Unit::activeLeaseAgreement()) after finding occupancy_status_id isn't
     * reliable for this at all — different controllers already disagreed on
     * which value meant what.
     *
     * Status per unit (see Unit::activeLeaseAgreement()/assignedTenant()):
     *   - 'Taken': has a Pending or Approved lease already — tenant shown is
     *     the lease's own tenant (LeaseAgreement.tenant_id), NOT
     *     assignedTenant()/Client.unit_id — lease creation never syncs that
     *     column back onto the tenant record, so it's usually empty here.
     *   - 'Pending Lease': a tenant is assigned (Client.unit_id) but has no
     *     lease yet — exactly the units this page exists to create leases for
     *   - 'Vacant': no tenant assigned, nothing to attach a lease to
     *
     * Tenant names are looked up via two bulk Client::withAll() queries rather
     * than eager-loaded relations — Client's user_clients_properties global
     * scope would otherwise silently hide tenants outside the current (non-
     * global-role) user's assigned properties, showing a real tenant as blank.
     */
    public function getAvailableUnits($propertyId)
    {
        try {
            $units = Unit::with('activeLeaseAgreement')
                ->where('property_id', $propertyId)
                ->orderBy('unit_number')
                ->get(['id', 'unit_name', 'unit_number', 'unit_code']);

            $leaseTenantIds = $units->pluck('activeLeaseAgreement.tenant_id')->filter()->unique();
            $leaseTenants = Client::withAll()->whereIn('id', $leaseTenantIds)->pluck('names', 'id');

            $unitIdsWithNoLease = $units->reject(fn ($u) => $u->activeLeaseAgreement)->pluck('id');
            $assignedTenants = Client::withAll()->tenants()
                ->whereIn('unit_id', $unitIdsWithNoLease)
                ->get(['id', 'unit_id', 'names'])
                ->keyBy('unit_id');

            $units = $units->map(function ($unit) use ($leaseTenants, $assignedTenants) {
                $lease = $unit->activeLeaseAgreement;
                $tenantId = null;
                $tenantName = null;
                $status = 'Vacant';

                if ($lease) {
                    $status = 'Taken';
                    $tenantId = $lease->tenant_id;
                    $tenantName = $leaseTenants->get($lease->tenant_id);
                } elseif ($assignedTenants->has($unit->id)) {
                    $status = 'Pending Lease';
                    $tenant = $assignedTenants->get($unit->id);
                    $tenantId = $tenant->id;
                    $tenantName = $tenant->names;
                }

                return [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'unit_number' => $unit->unit_number,
                    'unit_code' => $unit->unit_code,
                    'is_vacant' => $status === 'Vacant',
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenantName,
                    'status' => $status,
                ];
            });

            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading available units for property: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
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
                'unit_name' => $unitCode,
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
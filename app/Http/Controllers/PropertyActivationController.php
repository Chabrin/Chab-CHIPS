<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Unit;
use App\Models\LeaseAgreement;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class PropertyActivationController extends Controller
{
    /**
     * Show the property activation management page.
     */
    public function index()
    {
        if (!Auth::user()->isAbleTo('update_properties')) {
            abort(403);
        }

        return view('property.activation.index');
    }

    /**
     * List all properties (active and inactive) for the activation table (AJAX).
     */
    public function getData(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('read_properties')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $properties = Property::withInactive()
                ->with(['zone', 'client'])
                ->select(['id', 'reference_number', 'property_name', 'zone_id', 'client_id', 'active'])
                ->withCount('units')
                ->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'encrypted_id' => Crypt::encrypt($property->id),
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'zone_name' => $property->zone->zone_name ?? '-',
                        'client_name' => $property->client->names ?? '-',
                        'active' => (bool) $property->active,
                        'units_count' => $property->units_count,
                    ];
                });

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties for activation management: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Preview the units and tenants a set of properties would affect, before
     * committing a bulk activate/deactivate (AJAX).
     */
    public function preview(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('read_properties')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No properties selected'], 422);
            }

            $properties = Property::withInactive()
                ->whereIn('id', $ids)
                ->get(['id', 'property_name', 'reference_number', 'active']);

            $result = $properties->map(function ($property) {
                $units = Unit::where('property_id', $property->id)
                    ->get(['id', 'unit_name', 'unit_code'])
                    ->map(fn ($unit) => $unit->unit_name ?? $unit->unit_code ?? ('Unit #' . $unit->id));

                // Same tenant lookup as PropertiesController::getTenants() —
                // via LeaseAgreement, not Client.property_id, since the latter
                // is a static snapshot set once at tenant creation and can go
                // stale after a tenant movement.
                $tenants = LeaseAgreement::with('tenant')
                    ->where('property_id', $property->id)
                    ->get(['id', 'tenant_id'])
                    ->unique('tenant_id')
                    ->map(fn ($lease) => $lease->tenant->names ?? '-')
                    ->values();

                return [
                    'id' => $property->id,
                    'property_name' => $property->property_name ?? '-',
                    'reference_number' => $property->reference_number ?? '-',
                    'active' => (bool) $property->active,
                    'units_count' => $units->count(),
                    'units' => $units->values(),
                    'tenants_count' => $tenants->count(),
                    'tenants' => $tenants,
                ];
            })->values();

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error previewing property activation impact: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load preview'], 500);
        }
    }

    /**
     * Bulk-activate the selected properties.
     */
    public function activate(Request $request)
    {
        return $this->setActiveState($request, true);
    }

    /**
     * Bulk-deactivate the selected properties.
     */
    public function deactivate(Request $request)
    {
        return $this->setActiveState($request, false);
    }

    /**
     * Shared implementation for activate()/deactivate() — saves each property
     * individually (rather than a single whereIn()->update()) so
     * AuditLogsObserver fires per property and this previously-uncontrolled
     * flag gets a real audit trail.
     */
    private function setActiveState(Request $request, bool $active)
    {
        if (!Auth::user()->isAbleTo('update_properties')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No properties selected'], 422);
        }

        try {
            $properties = Property::withInactive()->whereIn('id', $ids)->get();

            foreach ($properties as $property) {
                $property->active = $active ? 1 : 0;
                $property->save();
            }

            Log::info(($active ? 'Activated' : 'Deactivated') . ' properties by user ' . Auth::id(), ['ids' => $ids]);

            $count = $properties->count();
            return response()->json([
                'success' => true,
                'message' => $count . ' propert' . ($count === 1 ? 'y' : 'ies') . ' updated successfully',
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating property active status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update properties'], 500);
        }
    }
}

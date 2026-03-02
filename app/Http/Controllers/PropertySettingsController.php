<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReturnType;
use App\Models\InvoiceableItem;
use App\Models\Zone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Services\ErrorDispatcher;

class PropertySettingsController extends Controller
{
    /**
     * Display settings page
     */
    public function index()
    {
        return view('property.settings.leave_types.index');
    }

    /**
     * Display zones settings page
     */
    public function zonesIndex()
    {
        return view('property.settings.zones.index');
    }

    /**
     * Display repairs settings page
     */
    public function repairsIndex()
    {
        return view('property.settings.repairs.index');
    }

    /**
     * Get all cycles for dropdown (AJAX)
     */
    public function getCycles()
    {
        try {
            $cycles = \App\Models\InvoiceableItemCycle::select(['id', 'cycle_name'])
                ->orderBy('cycle_name', 'asc')
                ->get()
                ->map(function ($cycle) {
                    return [
                        'id' => $cycle->id,
                        'text' => $cycle->cycle_name,
                    ];
                });
            return response()->json($cycles);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading cycles: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load cycles'], 500);
        }
    }

    // ============ RETURN TYPES METHODS ============

    /**
     * Get all return types (AJAX)
     */
    public function getReturnTypes()
    {
        try {
            $returnTypes = ReturnType::select(['id', 'type_name'])
                ->orderBy('type_name', 'asc')
                ->get()
                ->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'encrypted_id' => Crypt::encrypt($type->id),
                        'type_name' => $type->type_name ?? '-',
                        'can_update' => Auth::user()->isAbleTo('update_property_settings_items'),
                        'can_delete' => Auth::user()->isAbleTo('delete_property_settings_items'),
                    ];
                });
            return response()->json(['data' => $returnTypes]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading return types: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load return types'], 500);
        }
    }

    /**
     * Store a new return type
     */
    public function storeReturnType(Request $request)
    {
        try {
            $validated = $request->validate([
                'type_name' => 'required|string|max:255|unique:sys_pm_clients_returns_types,type_name',
            ]);

            ReturnType::create($validated);

            return response()->json(['success' => true, 'message' => 'Return type created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating return type: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create return type'], 500);
        }
    }

    /**
     * Update a return type
     */
    public function updateReturnType(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $returnType = ReturnType::findOrFail($id);

            $validated = $request->validate([
                'type_name' => 'required|string|max:255|unique:sys_pm_clients_returns_types,type_name,' . $id,
            ]);

            $returnType->update($validated);

            return response()->json(['success' => true, 'message' => 'Return type updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating return type: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update return type'], 500);
        }
    }

    /**
     * Delete a return type
     */
    public function deleteReturnType($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $returnType = ReturnType::findOrFail($id);
            $returnType->delete();

            return response()->json(['success' => true, 'message' => 'Return type deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting return type: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete return type'], 500);
        }
    }

    // ============ INVOICEABLE ITEMS METHODS ============

    /**
     * Get all invoiceable items (AJAX)
     */
    public function getInvoiceableItems()
    {
        try {
            $items = InvoiceableItem::with('cycle')
                ->select([
                    'id', 'item_name', 'billing_code',
                    'commissionable', 'advance_payable', 'is_bill', 'fixed_amount', 'amount', 'default_cycle_id'
                ])
                ->orderBy('item_name', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'encrypted_id' => Crypt::encrypt($item->id),
                        'item_name' => $item->item_name ?? '-',
                        'billing_code' => $item->billing_code ?? '-',
                        'commissionable' => $item->commissionable ?? 'No',
                        'advance_payable' => $item->advance_payable ?? 'No',
                        'is_bill' => $item->is_bill ?? 'No',
                        'fixed_amount' => $item->fixed_amount ?? 'No',
                        'amount' => $item->amount ? number_format($item->amount, 2) : '0.00',
                        'default_cycle_id' => $item->default_cycle_id ?? null,
                        'cycle_name' => $item->cycle->cycle_name ?? '-',
                        'can_update' => Auth::user()->isAbleTo('update_property_settings_items'),
                        'can_delete' => Auth::user()->isAbleTo('delete_property_settings_items'),
                    ];
                });
            return response()->json(['data' => $items]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading invoiceable items: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load invoiceable items'], 500);
        }
    }

    /**
     * Store a new invoiceable item
     */
    public function storeInvoiceableItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'item_name' => 'required|string|max:255',
                'billing_code' => 'nullable|string|max:255',
                'commissionable' => 'nullable|string|max:10',
                'advance_payable' => 'nullable|string|max:10',
                'is_bill' => 'nullable|string|max:10',
                'fixed_amount' => 'nullable|string|max:10',
                'amount' => 'nullable|numeric',
                'default_cycle_id' => 'nullable|integer|exists:sys_set_invoiceable_items_cycles,id',
            ]);

            $validated['date_time'] = now();
            $validated['current_status_id'] = 1;

            InvoiceableItem::create($validated);

            return response()->json(['success' => true, 'message' => 'Invoiceable item created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating invoiceable item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create invoiceable item'], 500);
        }
    }

    /**
     * Update an invoiceable item
     */
    public function updateInvoiceableItem(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $item = InvoiceableItem::findOrFail($id);

            $validated = $request->validate([
                'item_name' => 'required|string|max:255',
                'billing_code' => 'nullable|string|max:255',
                'commissionable' => 'nullable|string|max:10',
                'advance_payable' => 'nullable|string|max:10',
                'is_bill' => 'nullable|string|max:10',
                'fixed_amount' => 'nullable|string|max:10',
                'amount' => 'nullable|numeric',
                'default_cycle_id' => 'nullable|integer|exists:sys_set_invoiceable_items_cycles,id',
            ]);

            $item->update($validated);

            return response()->json(['success' => true, 'message' => 'Invoiceable item updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating invoiceable item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update invoiceable item'], 500);
        }
    }

    /**
     * Delete an invoiceable item
     */
    public function deleteInvoiceableItem($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $item = InvoiceableItem::findOrFail($id);
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Invoiceable item deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting invoiceable item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete invoiceable item'], 500);
        }
    }

    // ============ ZONES METHODS ============

    /**
     * Get all zones (AJAX)
     */
    public function getZones()
    {
        try {
            $zones = Zone::select(['id', 'zone_name', 'estates_id'])
                ->orderBy('zone_name', 'asc')
                ->get()
                ->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'encrypted_id' => Crypt::encrypt($zone->id),
                        'zone_name' => $zone->zone_name ?? '-',
                        'estates_id' => $zone->estates_id ?? '-',
                        'can_update' => Auth::user()->isAbleTo('update_property_settings_items'),
                        'can_delete' => Auth::user()->isAbleTo('delete_property_settings_items'),
                    ];
                });
            return response()->json(['data' => $zones]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading zones: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load zones'], 500);
        }
    }

    /**
     * Store a new zone
     */
    public function storeZone(Request $request)
    {
        try {
            $validated = $request->validate([
                'zone_name' => 'required|string|max:255',
                'estates_id' => 'nullable|integer',
            ]);

            $validated['date_time'] = now();
            $validated['current_status_id'] = 1;

            Zone::create($validated);

            return response()->json(['success' => true, 'message' => 'Zone created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating zone: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create zone'], 500);
        }
    }

    /**
     * Update a zone
     */
    public function updateZone(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $zone = Zone::findOrFail($id);

            $validated = $request->validate([
                'zone_name' => 'required|string|max:255',
                'estates_id' => 'nullable|integer',
            ]);

            $zone->update($validated);

            return response()->json(['success' => true, 'message' => 'Zone updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating zone: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update zone'], 500);
        }
    }

    /**
     * Delete a zone
     */
    public function deleteZone($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $zone = Zone::findOrFail($id);
            $zone->delete();

            return response()->json(['success' => true, 'message' => 'Zone deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting zone: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete zone'], 500);
        }
    }

    // ============ PROPERTY ELEMENTS METHODS ============

    /**
     * Get all property elements (AJAX)
     */
    public function getPropertyElements()
    {
        try {
            $elements = \App\Models\PropertyElement::select(['id', 'element_name', 'current_status_id'])
                ->orderBy('element_name', 'asc')
                ->get()
                ->map(function ($element) {
                    return [
                        'id' => $element->id,
                        'encrypted_id' => Crypt::encrypt($element->id),
                        'element_name' => $element->element_name ?? '-',
                        'status' => $element->current_status_id == 1 ? 'Active' : 'Inactive',
                        'can_update' => Auth::user()->isAbleTo('update_property_settings_items'),
                        'can_delete' => Auth::user()->isAbleTo('delete_property_settings_items'),
                    ];
                });
            return response()->json(['data' => $elements]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load property elements'], 500);
        }
    }

    /**
     * Store a new property element
     */
    public function storePropertyElement(Request $request)
    {
        try {
            $validated = $request->validate([
                'element_name' => 'required|string|max:255',
            ]);

            $validated['current_status_id'] = 1;
            $validated['created_by_id'] = Auth::id();

            \App\Models\PropertyElement::create($validated);

            return response()->json(['success' => true, 'message' => 'Property element created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating property element: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create property element'], 500);
        }
    }

    /**
     * Update a property element
     */
    public function updatePropertyElement(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $element = \App\Models\PropertyElement::findOrFail($id);

            $validated = $request->validate([
                'element_name' => 'required|string|max:255',
            ]);

            $element->update($validated);

            return response()->json(['success' => true, 'message' => 'Property element updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating property element: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update property element'], 500);
        }
    }

    /**
     * Delete a property element
     */
    public function deletePropertyElement($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $element = \App\Models\PropertyElement::findOrFail($id);
            $element->delete();

            return response()->json(['success' => true, 'message' => 'Property element deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting property element: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete property element'], 500);
        }
    }

    // ============ REPAIR TYPES METHODS ============

    /**
     * Get all repair types (AJAX)
     */
    public function getRepairTypes()
    {
        try {
            $repairTypes = \App\Models\RepairType::with('propertyElement:id,element_name')
                ->select(['id', 'element_name', 'element_id', 'current_status_id'])
                ->orderBy('element_id', 'asc')
                ->orderBy('element_name', 'asc')
                ->get()
                ->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'encrypted_id' => Crypt::encrypt($type->id),
                        'repair_type_name' => $type->element_name ?? '-',
                        'property_element_name' => $type->propertyElement->element_name ?? '-',
                        'element_id' => $type->element_id,
                        'status' => $type->current_status_id == 1 ? 'Active' : 'Inactive',
                        'can_update' => Auth::user()->isAbleTo('update_property_settings_items'),
                        'can_delete' => Auth::user()->isAbleTo('delete_property_settings_items'),
                    ];
                });
            return response()->json(['data' => $repairTypes]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair types: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load repair types'], 500);
        }
    }

    /**
     * Store a new repair type
     */
    public function storeRepairType(Request $request)
    {
        try {
            $validated = $request->validate([
                'element_id' => 'required|integer|exists:sys_pm_properties_elements,id',
                'element_name' => 'required|string|max:255',
            ]);

            $validated['current_status_id'] = 1;
            $validated['created_by_id'] = Auth::id();

            \App\Models\RepairType::create($validated);

            return response()->json(['success' => true, 'message' => 'Repair type created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating repair type: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create repair type'], 500);
        }
    }

    /**
     * Update a repair type
     */
    public function updateRepairType(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $repairType = \App\Models\RepairType::findOrFail($id);

            $validated = $request->validate([
                'element_id' => 'required|integer|exists:sys_pm_properties_elements,id',
                'element_name' => 'required|string|max:255',
            ]);

            $repairType->update($validated);

            return response()->json(['success' => true, 'message' => 'Repair type updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating repair type: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update repair type'], 500);
        }
    }

    /**
     * Delete a repair type
     */
    public function deleteRepairType($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $repairType = \App\Models\RepairType::findOrFail($id);
            $repairType->delete();

            return response()->json(['success' => true, 'message' => 'Repair type deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting repair type: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete repair type'], 500);
        }
    }

    /**
     * Get property elements for dropdown (AJAX)
     */
    public function getPropertyElementsDropdown()
    {
        try {
            $elements = \App\Models\PropertyElement::where('current_status_id', 1)
                ->select(['id', 'element_name'])
                ->orderBy('element_name', 'asc')
                ->get()
                ->map(function ($element) {
                    return [
                        'id' => $element->id,
                        'text' => $element->element_name,
                    ];
                });
            return response()->json($elements);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property elements dropdown: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load property elements'], 500);
        }
    }
}

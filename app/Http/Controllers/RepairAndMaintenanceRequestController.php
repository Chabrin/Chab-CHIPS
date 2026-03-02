<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RepairRequest;
use App\Models\RepairRequestElement;
use App\Models\RepairRequestItem;
use App\Models\PropertyElement;
use App\Models\RepairType;
use App\Models\Property;
use App\Models\Client;
use App\Models\Zone;
use App\Models\Unit;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class RepairAndMaintenanceRequestController extends Controller
{
    /**
     * Display listing of repair requests
     */
    public function index()
    {
        try {
            return view('property.repairs.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair requests index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load repair requests');
        }
    }

    /**
     * Get repair requests data (AJAX)
     */
    public function getData()
    {
        try {
            $canView = Auth::user()->isAbleTo('read_repair_requests');
            $canEdit = Auth::user()->isAbleTo('update_repair_requests');
            $canViewclient = Auth::user()->isAbleTo('read_clients');
            $canViewproperty = Auth::user()->isAbleTo('read_properties');

            $requests = RepairRequest::with([
                'property:id,property_name',
                'client:id,names',
                'zone:id,zone_name',
                'createdBy:id,name'
            ])
            ->orderBy('date_time', 'desc')
            ->get()
            ->map(function ($request) use ($canView, $canEdit, $canViewclient, $canViewproperty) {
                return [
                    'id' => $request->id,
                    'encrypted_id' => Crypt::encrypt($request->id),
                    'ref_number' => $request->ref_number ?? '-',
                    'client_id' => $request->client_id,
                    'encrypted_client_id' => $request->client_id ? Crypt::encrypt($request->client_id) : null,
                    'client_name' => $request->client->names ?? '-',
                    'property_id' => $request->property_id,
                    'encrypted_property_id' => $request->property_id ? Crypt::encrypt($request->property_id) : null,
                    'property_name' => $request->property->property_name ?? '-',
                    'zone_name' => $request->zone->zone_name ?? '-',
                    'visited_date_time' => $request->visited_date_time ? Carbon::parse($request->visited_date_time)->format('d-m-Y H:i') : '-',
                    'created_by_name' => $request->createdBy->name ?? '-',
                    'current_status_id' => $request->current_status_id,
                    'can_view' => $canView,
                    'can_edit' => $canEdit,
                    'can_view_client' => $canViewclient,
                    'can_view_property' => $canViewproperty,
                ];
            });

            return response()->json(['data' => $requests]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair requests data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load repair requests'], 500);
        }
    }

    /**
     * Show create form
     */
    public function create()
    {
        try {
            $elements = PropertyElement::where('current_status_id', 1)
                ->orderBy('element_name', 'asc')
                ->get();
            
            return view('property.repairs.create', compact('elements'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair request create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Store new repair request
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'visited_date_time' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.unit_id' => 'nullable|exists:sys_pm_units,id',
                'items.*.element_id' => 'required|exists:sys_pm_properties_elements,id',
                'items.*.repair_type_id' => 'required|exists:sys_pm_properties_repair_types,id',
                'items.*.amount' => 'required|numeric|min:0',
                'items.*.remarks' => 'nullable|string',
            ]);

            // Generate reference number
            $lastRequest = RepairRequest::orderBy('id', 'desc')->first();
            $nextNumber = $lastRequest ? $lastRequest->id + 1 : 1;
            $refNumber = 'RPR-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $zoneId = Property::find($validated['property_id'])->zone_id ?? null;

            // Create main repair request
            $repairRequest = RepairRequest::create([
                'date_time' => now(),
                'visited_date_time' => $validated['visited_date_time'],
                'created_by_id' => Auth::id(),
                'zone_id' => $zoneId,
                'property_id' => $validated['property_id'],
                'client_id' => $validated['client_id'],
                'current_status_id' => 1, // Pending
                'ref_number' => $refNumber,
            ]);

            // Get unique elements from items
            $uniqueElements = collect($validated['items'])->pluck('element_id')->unique();

            // Save selected elements
            foreach ($uniqueElements as $elementId) {
                RepairRequestElement::create([
                    'parent_id' => $repairRequest->id,
                    'element_id' => $elementId,
                ]);
            }

            // Save repair items
            foreach ($validated['items'] as $item) {
                RepairRequestItem::create([
                    'parent_id' => $repairRequest->id,
                    'unit_id' => $item['unit_id'] ?? null,
                    'property_element_id' => $item['element_id'],
                    'repair_type_id' => $item['repair_type_id'],
                    'amount' => $item['amount'],
                    'remarks' => $item['remarks'] ?? null,
                    'current_status_id' => 1, // Pending
                ]);
            }

            DB::commit();

            Log::info('Repair request created successfully', [
                'request_id' => $repairRequest->id,
                'ref_number' => $refNumber,
                'created_by' => Auth::id()
            ]);

            return redirect()
                ->route('property.repairs.index')
                ->with('success', 'Repair request created successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating repair request: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create repair request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show details
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            
            $repairRequest = RepairRequest::with([
                'property:id,property_name,reference_number',
                'client:id,names,reference_number',
                'zone:id,zone_name',
                'createdBy:id,name'
            ])->findOrFail($id);

            $items = RepairRequestItem::where('parent_id', $id)
                ->with([
                    'repairType:id,element_name',
                    'repairRequest'
                ])
                ->get();

            return view('property.repairs.details', compact('repairRequest', 'items'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair request details: ' . $e->getMessage());
            return redirect()->route('property.repairs.index')->with('error', 'Failed to load details');
        }
    }

    /**
     * Show edit form
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            
            $repairRequest = RepairRequest::with([
                'property:id,property_name,zone_id',
                'client:id,names'
            ])->findOrFail($id);

            $items = RepairRequestItem::where('parent_id', $id)
                ->with('repairType:id,element_name,element_id')
                ->get();

            $elements = PropertyElement::where('current_status_id', 1)
                ->orderBy('element_name', 'asc')
                ->get();

            return view('property.repairs.edit', compact('repairRequest', 'items', 'elements'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair request edit form: ' . $e->getMessage());
            return redirect()->route('property.repairs.index')->with('error', 'Failed to load repair request');
        }
    }

    /**
     * Update repair request
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $repairRequest = RepairRequest::findOrFail($id);

            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'visited_date_time' => 'required|date',
                'zone_id' => 'nullable|integer',
                'items' => 'required|array|min:1',
                'items.*.unit_id' => 'nullable|exists:sys_pm_units,id',
                'items.*.element_id' => 'required|exists:sys_pm_properties_elements,id',
                'items.*.repair_type_id' => 'required|exists:sys_pm_properties_repair_types,id',
                'items.*.amount' => 'required|numeric|min:0',
                'items.*.remarks' => 'nullable|string',
            ]);

            // Update main request
            $repairRequest->update([
                'visited_date_time' => $validated['visited_date_time'],
                'zone_id' => $validated['zone_id'],
                'property_id' => $validated['property_id'],
                'client_id' => $validated['client_id'],
            ]);

            // Delete existing elements and items
            RepairRequestElement::where('parent_id', $id)->delete();
            RepairRequestItem::where('parent_id', $id)->delete();

            // Get unique elements from items
            $uniqueElements = collect($validated['items'])->pluck('element_id')->unique();

            // Save elements
            foreach ($uniqueElements as $elementId) {
                RepairRequestElement::create([
                    'parent_id' => $repairRequest->id,
                    'element_id' => $elementId,
                ]);
            }

            // Save items
            foreach ($validated['items'] as $item) {
                RepairRequestItem::create([
                    'parent_id' => $repairRequest->id,
                    'unit_id' => $item['unit_id'] ?? null,
                    'property_element_id' => $item['element_id'],
                    'repair_type_id' => $item['repair_type_id'],
                    'amount' => $item['amount'],
                    'remarks' => $item['remarks'] ?? null,
                    'current_status_id' => 1,
                ]);
            }

            DB::commit();

            Log::info('Repair request updated successfully', [
                'request_id' => $repairRequest->id,
                'updated_by' => Auth::id()
            ]);

            return redirect()
                ->route('property.repairs.index')
                ->with('success', 'Repair request updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating repair request: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update repair request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get repair types by element (AJAX)
     */
    public function getRepairTypesByElement($elementId)
    {
        try {
            $repairTypes = RepairType::where('element_id', $elementId)
                ->where('current_status_id', 1)
                ->orderBy('element_name', 'asc')
                ->get()
                ->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->element_name,
                    ];
                });

            return response()->json(['repair_types' => $repairTypes]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repair types: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load repair types'], 500);
        }
    }
}

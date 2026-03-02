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
    /**
     * Show the form for creating a new unit
     */
    public function create(Request $request)
    {
        try {
            $clientId = null;
            $client = null;

            // If property parameter is provided, decrypt it
            if ($request->has('client')) {
                $clientId = Crypt::decrypt($request->client);
                $client = Client::findOrFail($clientId);
                $properties = Property::where('client_id', $client->id)->get();
            }
            
            return view('property.properties.units.create', compact('properties', 'client'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading unit create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    // In PropertyController or appropriate controller
    public function getUnits($propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->where('current_status_id', 31) // Only active units
                ->whereDoesntHave('leaseAgreements') // No leases at all
                ->orderBy('unit_number')
                ->get(['id', 'unit_name', 'unit_number', 'current_status_id']);
            
            return response()->json(['units' => $units]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Show the form for editing a unit
     */
    public function edit($id)
    {
        // try {
            $unitId = Crypt::decrypt($id);
            $unit = Unit::with(['property', 'client', 'unit_documents'])->findOrFail($unitId);
            // dd($unit);
            $client = $unit->client;
            $properties = Property::where('client_id', $client->id)->get();
            
            return view('property.properties.units.edit', compact('unit', 'properties', 'client'));
        // } catch (\Exception $e) {
        //     ErrorDispatcher::dispatch($e);
        //     Log::error('Error loading unit edit form: ' . $e->getMessage());
        //     return redirect()->back()->with('error', 'Failed to load form');
        // }
    }

    /**
     * Update the specified unit
     */
    public function update(Request $request, $id)
    {
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
                'unit_uploads.*' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx',
                'delete_documents' => 'nullable|array',
                'delete_documents.*' => 'integer',
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

            // Handle document deletions
            if (!empty($validated['delete_documents'])) {
                foreach ($validated['delete_documents'] as $docId) {
                    $document = UnitDocument::where('id', $docId)
                        ->where('parent_id', $unit->id)
                        ->first();
                    if ($document) {
                        // Delete physical file
                        if (Storage::disk('public')->exists($document->unit_uploads)) {
                            Storage::disk('public')->delete($document->unit_uploads);
                        }
                        $document->delete();
                    }
                }
            }

            // Handle new file uploads
            if ($request->hasFile('unit_uploads')) {
                foreach ($request->file('unit_uploads') as $file) {
                    if ($file->isValid()) {
                        $path = $file->store('units/uploads', 'public');
                        
                        UnitDocument::create([
                            'parent_id' => $unit->id,
                            'unit_uploads' => $path,
                            'params' => json_encode([
                                'original_name' => $file->getClientOriginalName(),
                                'mime_type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                                'uploaded_at' => now()->toDateTimeString(),
                            ]),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('property.properties.show', Crypt::encrypt($validated['property_id']))
                ->with('success', 'Unit updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating unit: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update unit: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Store a newly created unit
     */
    public function store(Request $request)
    {
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
                'unit_uploads.*' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx',
            ]);

            // Generate unit code
            $property = Property::findOrFail($validated['property_id']);
            $lastUnit = Unit::where('property_id', $validated['property_id'])
                ->orderBy('unit_code', 'desc')
                ->first();

            if ($lastUnit && preg_match('/(\d+)$/', $lastUnit->unit_code, $matches)) {
                $lastNumber = intval($matches[1]);
                $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '001';
            }
            
            $unitCode = $property->reference_number . '-U' . $newNumber;

            $property = Property::findOrFail($validated['property_id']);
            $zone_id = $property->zone_id;


            // Create the unit record
            $unit = Unit::create([
                'property_id' => $validated['property_id'],
                'client_id' => $validated['client_id'],
                'zone_id' => $zone_id,
                'unit_code' => $unitCode,
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
                'current_status_id' => $validated['occupancy_status_id'] ?? 31, // Default to active
            ]);

            // Handle file uploads and save to UnitDocument model
            if ($request->hasFile('unit_uploads')) {
                foreach ($request->file('unit_uploads') as $file) {
                    if ($file->isValid()) {
                        // Store file in public disk
                        $path = $file->store('units/uploads', 'public');
                        
                        // Create UnitDocument record
                        UnitDocument::create([
                            'parent_id' => $unit->id,
                            'unit_uploads' => $path,
                            'params' => json_encode([
                                'original_name' => $file->getClientOriginalName(),
                                'mime_type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                                'uploaded_at' => now()->toDateTimeString(),
                            ]),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('property.properties.show', Crypt::encrypt($validated['property_id']))
                ->with('success', 'Unit created successfully with code: ' . $unitCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating unit: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create unit: ' . $e->getMessage())->withInput();
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
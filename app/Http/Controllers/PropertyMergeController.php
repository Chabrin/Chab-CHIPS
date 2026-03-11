<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use App\Models\PropertyMerge;
use App\Models\PropertyMergeItem;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class PropertyMergeController extends Controller
{
    /**
     * Display the property merge listing page
     */
    public function index()
    {
        return view('property.merge.index');
    }

    /**
     * Display the create merge form
     */
    public function create()
    {
        return view('property.merge.create');
    }

    /**
     * Show merge details
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $merge = PropertyMerge::with([
                'client',
                'targetProperty',
                'bankAccount.bank',
                'items.sourceProperty',
                'items.unit',
                'createdBy',
                'approvedBy',
            ])->findOrFail($id);

            return view('property.merge.show', compact('merge'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->route('property.merge.index')->with('error', 'Merge record not found.');
        }
    }

    /**
     * AJAX: Get list of all merges for DataTable
     */
    public function list(Request $request)
    {
        try {
            $query = PropertyMerge::with(['client', 'targetProperty', 'createdBy'])
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $query->where('current_status_id', $request->status);
            }

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            $merges = $query->get()->map(function ($merge) {
                return [
                    'id' => $merge->id,
                    'encrypted_id' => Crypt::encrypt($merge->id),
                    'merge_reference' => $merge->merge_reference,
                    'client_name' => $merge->client->names ?? 'N/A',
                    'merge_type' => ucfirst($merge->merge_type),
                    'merged_property_name' => $merge->merged_property_name,
                    'target_property' => $merge->targetProperty->property_name ?? 'N/A',
                    'items_count' => $merge->items()->count(),
                    'status' => $merge->status_label,
                    'current_status_id' => $merge->current_status_id,
                    'created_by' => $merge->createdBy->name ?? 'N/A',
                    'created_at' => $merge->created_at ? $merge->created_at->format('Y-m-d H:i') : ($merge->date_time ? $merge->date_time->format('Y-m-d H:i') : 'N/A'),
                ];
            });

            return response()->json(['data' => $merges]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Property merge list error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch merge records.'], 500);
        }
    }

    /**
     * AJAX: Get properties belonging to a client (for merge selection)
     */
    public function getClientProperties($clientId)
    {
        try {
            $properties = Property::where('client_id', $clientId)
                ->whereNotIn('current_status_id', [0]) // exclude inactive/deleted
                ->select('id', 'property_name', 'reference_number', 'description', 'bank_account_id')
                ->withCount('units')
                ->get();

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to fetch properties.'], 500);
        }
    }

    /**
     * AJAX: Get units for a specific property (for unit-level merge)
     */
    public function getPropertyUnits($propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->select('id', 'unit_name', 'unit_code', 'unit_number', 'rent_amount', 'occupancy_status_id', 'current_status_id')
                ->get();

            return response()->json(['data' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to fetch units.'], 500);
        }
    }

    /**
     * AJAX: Get bank accounts for a client (created during client registration)
     */
    public function getClientBankAccounts($clientId)
    {
        try {
            $bankAccounts = DB::table('sys_pm_clients_17_repeat')
                ->where('parent_id', $clientId)
                ->select('id', 'bank_id', 'account_name', 'account_number', 'branch')
                ->get();

            // Enrich with bank name
            $bankIds = $bankAccounts->pluck('bank_id')->unique()->filter()->toArray();
            $banks = [];
            if (!empty($bankIds)) {
                $banks = DB::table('sys_banks')->whereIn('id', $bankIds)->pluck('bank_name', 'id')->toArray();
            }

            $bankAccounts = $bankAccounts->map(function ($account) use ($banks) {
                $account->bank_name = $banks[$account->bank_id] ?? 'N/A';
                return $account;
            });

            return response()->json(['data' => $bankAccounts]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Get client bank accounts error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch bank accounts.'], 500);
        }
    }

    /**
     * AJAX: Preview what the merge will look like before executing
     */
    public function preview(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'merge_type' => 'required|in:property,unit',
            'target_property_id' => 'required|integer',
            'source_items' => 'required|array|min:1',
        ]);

        try {
            $targetProperty = Property::with('units')->findOrFail($request->target_property_id);

            $previewData = [
                'target_property' => [
                    'id' => $targetProperty->id,
                    'property_name' => $targetProperty->property_name,
                    'reference_number' => $targetProperty->reference_number,
                    'units_count' => $targetProperty->units->count(),
                ],
                'source_items' => [],
                'total_units_after_merge' => $targetProperty->units->count(),
            ];

            if ($request->merge_type === 'property') {
                foreach ($request->source_items as $propertyId) {
                    $property = Property::with('units')->find($propertyId);
                    if ($property && $property->id !== $targetProperty->id) {
                        $unitsCount = $property->units->count();
                        $previewData['source_items'][] = [
                            'id' => $property->id,
                            'property_name' => $property->property_name,
                            'reference_number' => $property->reference_number,
                            'units_count' => $unitsCount,
                        ];
                        $previewData['total_units_after_merge'] += $unitsCount;
                    }
                }
            } else {
                foreach ($request->source_items as $unitId) {
                    $unit = Unit::with('property')->find($unitId);
                    if ($unit && $unit->property_id !== $targetProperty->id) {
                        $previewData['source_items'][] = [
                            'id' => $unit->id,
                            'unit_name' => $unit->unit_name,
                            'unit_code' => $unit->unit_code,
                            'rent_amount' => $unit->rent_amount,
                            'source_property_name' => $unit->property->property_name ?? 'N/A',
                            'source_property_id' => $unit->property_id,
                        ];
                        $previewData['total_units_after_merge'] += 1;
                    }
                }
            }

            return response()->json(['data' => $previewData]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to generate preview.'], 500);
        }
    }

    /**
     * Store/Execute the property merge
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'merge_type' => 'required|in:property,unit',
            'merged_property_name' => 'required|string|max:255',
            'target_property_id' => 'required|integer',
            'bank_account_id' => 'required|integer',
            'source_items' => 'required|array|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            // Validate that target property belongs to the client
            $targetProperty = Property::where('id', $request->target_property_id)
                ->where('client_id', $request->client_id)
                ->firstOrFail();

            // Validate bank account belongs to the client
            $bankAccount = DB::table('sys_pm_clients_17_repeat')
                ->where('id', $request->bank_account_id)
                ->where('parent_id', $request->client_id)
                ->first();

            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected bank account does not belong to this client.',
                ], 422);
            }

            // Create the merge record
            $merge = PropertyMerge::create([
                'merge_reference' => PropertyMerge::generateReference(),
                'client_id' => $request->client_id,
                'merge_type' => $request->merge_type,
                'merged_property_name' => $request->merged_property_name,
                'target_property_id' => $request->target_property_id,
                'bank_account_id' => $request->bank_account_id,
                'notes' => $request->notes,
                'current_status_id' => 1, // Pending
                'created_by' => Auth::id(),
                'date_time' => now(),
            ]);

            if ($request->merge_type === 'property') {
                // Merge whole properties: move all units from source properties into target
                foreach ($request->source_items as $sourcePropertyId) {
                    $sourceProperty = Property::with('units')
                        ->where('client_id', $request->client_id)
                        ->findOrFail($sourcePropertyId);

                    if ($sourceProperty->id === $targetProperty->id) {
                        continue; // Skip target property
                    }

                    // Snapshot original data for reversal
                    $originalData = [
                        'property' => $sourceProperty->toArray(),
                        'units' => $sourceProperty->units->toArray(),
                        'original_bank_account_id' => $sourceProperty->bank_account_id,
                    ];

                    PropertyMergeItem::create([
                        'property_merge_id' => $merge->id,
                        'source_property_id' => $sourcePropertyId,
                        'unit_id' => null,
                        'original_data' => $originalData,
                        'date_time' => now(),
                    ]);

                    // Move all units from source to target property
                    Unit::where('property_id', $sourcePropertyId)
                        ->update(['property_id' => $request->target_property_id]);

                    // Mark source property as merged (inactive)
                    $sourceProperty->update(['current_status_id' => 0]);
                }
            } else {
                // Merge individual units: move selected units to target property
                foreach ($request->source_items as $unitId) {
                    $unit = Unit::findOrFail($unitId);

                    if ($unit->property_id === $targetProperty->id) {
                        continue; // Already on target property
                    }

                    // Validate unit belongs to same client
                    if ($unit->client_id != $request->client_id) {
                        continue;
                    }

                    $originalData = [
                        'unit' => $unit->toArray(),
                        'original_property_id' => $unit->property_id,
                    ];

                    PropertyMergeItem::create([
                        'property_merge_id' => $merge->id,
                        'source_property_id' => $unit->property_id,
                        'unit_id' => $unitId,
                        'original_data' => $originalData,
                        'date_time' => now(),
                    ]);

                    // Move unit to target property
                    $unit->update(['property_id' => $request->target_property_id]);
                }
            }

            // Update target property name
            $targetProperty->update([
                'property_name' => $request->merged_property_name,
                'bank_account_id' => $request->bank_account_id,
            ]);

            // Mark merge as completed
            $merge->update([
                'current_status_id' => 2, // Completed
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Properties merged successfully.',
                'merge_reference' => $merge->merge_reference,
                'encrypted_id' => Crypt::encrypt($merge->id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Property merge error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to merge properties: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reverse a completed merge
     */
    public function reverse($encryptedId, Request $request)
    {
        $request->validate([
            'reversal_reason' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $id = Crypt::decrypt($encryptedId);
            $merge = PropertyMerge::with('items')->findOrFail($id);

            if ((int) $merge->current_status_id !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only completed merges can be reversed.',
                ], 422);
            }

            foreach ($merge->items as $item) {
                $originalData = $item->original_data;

                if ($merge->merge_type === 'property') {
                    // Restore units back to original property
                    $originalUnits = $originalData['units'] ?? [];
                    $unitIds = collect($originalUnits)->pluck('id')->toArray();

                    Unit::whereIn('id', $unitIds)
                        ->update(['property_id' => $item->source_property_id]);

                    // Reactivate the source property
                    Property::where('id', $item->source_property_id)
                        ->update([
                            'current_status_id' => 1,
                            'bank_account_id' => $originalData['original_bank_account_id'] ?? null,
                        ]);
                } else {
                    // Restore individual unit to original property
                    $originalPropertyId = $originalData['original_property_id'];
                    Unit::where('id', $item->unit_id)
                        ->update(['property_id' => $originalPropertyId]);
                }
            }

            // Restore target property's original bank account if we have snapshots
            // The first item's original data can tell us what the target property's bank was
            $merge->update([
                'current_status_id' => 3, // Reversed
                'reversed_by' => Auth::id(),
                'reversed_at' => now(),
                'reversal_reason' => $request->reversal_reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Merge has been reversed successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Property merge reversal error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reverse merge: ' . $e->getMessage(),
            ], 500);
        }
    }
}

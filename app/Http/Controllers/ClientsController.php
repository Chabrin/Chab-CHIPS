<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use App\Models\BankBranch;
use Illuminate\Http\Request;
use App\Models\ClientDocument;
use App\Models\LeaseAgreement;
use App\Models\ClientNextOfKin;
use App\Models\ClientBankAccount;
use App\Models\ReturnsMergeBatch;
use App\Models\ReturnsMergeBatchItem;
use App\Services\ErrorDispatcher;
use App\Models\ClientOtherContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class ClientsController extends Controller
{
    /**
     * Display a listing of clients
     */
    public function index()
    {
        try {
            return view('property.clients.index');
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading clients index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            abort(500, 'Failed to load clients page');
        }
    }


    public function getCLients(Request $request)
    {
        try {
            $baseQuery = Client::clients();
            $query = Client::clients()->select(['id', 'names', 'mobile_number', 'national_id', 'reference_number', 'email_address', 'registered_date']);
            $user = Auth::user();

            if (!$user) {
                Log::warning('Unauthenticated request to clients data', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $can_read = Gate::forUser($user)->check('read_clients');
            $can_update = Gate::forUser($user)->check('update_clients');

            // DataTables global search: search[value]
            $rawSearch = data_get($request->all(), 'search.value', null);
            if (is_string($rawSearch) || is_numeric($rawSearch)) {
                $searchValue = trim((string) $rawSearch);
            } else {
                $searchValue = '';
            }

            // Backward-compatible plain search param support
            if ($searchValue === '' && $request->has('search')) {
                $plainSearch = $request->input('search');
                if (is_string($plainSearch) || is_numeric($plainSearch)) {
                    $searchValue = trim((string) $plainSearch);
                }
            }

            if ($searchValue !== '') {
                $tokens = array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($searchValue))));
                foreach ($tokens as $token) {
                    $query->where(function ($q) use ($token) {
                        $q->where('names', 'ILIKE', "%{$token}%")
                            ->orWhere('id', $token)
                            ->orWhere('reference_number', 'ILIKE', "%{$token}%")
                            ->orWhere('mobile_number', 'ILIKE', "%{$token}%")
                            ->orWhere('email_address', 'ILIKE', "%{$token}%");
                            
                    });
                }
            }

            $columns = [
                1 => 'id',
                2 => 'names',
                3 => 'mobile_number',
                4 => 'national_id',
                5 => 'reference_number',
                6 => 'registered_date',
            ];

            // DataTables per-column search: columns[i][search][value]
            $requestColumns = data_get($request->all(), 'columns', []);
            if (is_array($requestColumns)) {
                foreach ($requestColumns as $index => $column) {
                    $field = $columns[(int) $index] ?? null;
                    if ($field === null) {
                        continue;
                    }
                    $colSearch = data_get($column, 'search.value');
                    if (!is_string($colSearch) && !is_numeric($colSearch)) {
                        continue;
                    }
                    $colSearch = trim((string) $colSearch);
                    if ($colSearch === '') {
                        continue;
                    }
                    if ($field === 'registered_date') {
                        $query->whereRaw("CAST({$field} AS TEXT) ILIKE ?", ["%{$colSearch}%"]);
                    } elseif ($field === 'id') {
                        $query->where($field, $colSearch);
                    } else {
                        $query->where($field, 'ILIKE', "%{$colSearch}%");
                    }
                }
            }

            $recordsTotal = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();

            $orderIndex = (int) data_get($request->all(), 'order.0.column', 2);
            $orderBy = $columns[$orderIndex] ?? 'names';
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $start = max((int) $request->input('start', 0), 0);
            $length = (int) $request->input('length', 25);
            if ($length <= 0) {
                $length = 25;
            }
            $length = min($length, 100);

            $clients = $query->orderBy($orderBy, $orderDir)
                ->skip($start)
                ->take($length)
                ->get()
                ->map(function ($client) use ($can_read, $can_update) {
                    $registeredDate = '-';
                    if (!empty($client->registered_date)) {
                        try {
                            $registeredDate = \Carbon\Carbon::parse($client->registered_date)->format('d-m-Y');
                        } catch (\Throwable $e) {
                            $registeredDate = (string) $client->registered_date;
                        }
                    }

                    return [
                        'id' => $client->id,
                        'encrypted_id' => Crypt::encrypt($client->id),
                        'names' => $client->names ?? '-',
                        'mobile_number' => $client->mobile_number ?? '-',
                        'national_id' => $client->national_id ?? '-',
                        'reference_number' => $client->reference_number ?? '-',
                        'email_address' => $client->email_address ?? '-',
                        'registered_date' => $registeredDate,
                        'can_read' => $can_read,
                        'can_update' => $can_update,
                    ];
                });
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $clients,
            ]);
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading clients data', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'path' => $request->path(),
                'user_id' => optional(Auth::user())->id,
            ]);
            return response()->json(['error' => 'Failed to load clients'], 500);
        }
    }

    /**
     * Display the tenants listing page
     */
    public function tenantIndex()
    {
        try {
            return view('property.tenants.index');
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants index', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            abort(500, 'Failed to load tenants page');
        }
    }

    /**
     * AJAX DataTable data for tenants (type_id = 2) — fully independent from getCLients
     */
    public function getTenantsData(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                Log::warning('Unauthenticated request to tenants data', [
                    'path' => $request->path(),
                    'ip'   => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $can_read   = Gate::forUser($user)->check('read_clients');
            $can_update = Gate::forUser($user)->check('update_clients');

            $baseQuery = Client::tenants();
            $query     = Client::tenants()->select(['id', 'names', 'mobile_number', 'national_id', 'reference_number', 'email_address', 'registered_date']);

            // DataTables global search
            $rawSearch = data_get($request->all(), 'search.value', null);
            if (is_string($rawSearch) || is_numeric($rawSearch)) {
                $searchValue = trim((string) $rawSearch);
            } else {
                $searchValue = '';
            }
            if ($searchValue === '' && $request->has('search')) {
                $plainSearch = $request->input('search');
                if (is_string($plainSearch) || is_numeric($plainSearch)) {
                    $searchValue = trim((string) $plainSearch);
                }
            }

            if ($searchValue !== '') {
                $tokens = array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($searchValue))));
                foreach ($tokens as $token) {
                    $query->where(function ($q) use ($token) {
                        $q->where('names', 'ILIKE', "%{$token}%")
                            ->orWhere('reference_number', 'ILIKE', "%{$token}%")
                            ->orWhere('mobile_number', 'ILIKE', "%{$token}%")
                            ->orWhere('email_address', 'ILIKE', "%{$token}%");
                    });
                }
            }

            $columns = [
                1 => 'id',
                2 => 'names',
                3 => 'mobile_number',
                4 => 'national_id',
                5 => 'reference_number',
                6 => 'registered_date',
            ];

            // DataTables per-column search: columns[i][search][value]
            $requestColumns = data_get($request->all(), 'columns', []);
            if (is_array($requestColumns)) {
                foreach ($requestColumns as $index => $column) {
                    $field = $columns[(int) $index] ?? null;
                    if ($field === null) {
                        continue;
                    }
                    $colSearch = data_get($column, 'search.value');
                    if (!is_string($colSearch) && !is_numeric($colSearch)) {
                        continue;
                    }
                    $colSearch = trim((string) $colSearch);
                    if ($colSearch === '') {
                        continue;
                    }
                    if ($field === 'registered_date') {
                        $query->whereRaw("CAST({$field} AS TEXT) ILIKE ?", ["%{$colSearch}%"]);
                    } elseif ($field === 'id') {
                        $query->where($field, $colSearch);
                    } else {
                        $query->where($field, 'ILIKE', "%{$colSearch}%");
                    }
                }
            }

            $recordsTotal    = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();

            $orderIndex = (int) data_get($request->all(), 'order.0.column', 2);
            $orderBy    = $columns[$orderIndex] ?? 'names';
            $orderDir   = strtolower((string) data_get($request->all(), 'order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $start  = max((int) $request->input('start', 0), 0);
            $length = (int) $request->input('length', 25);
            if ($length <= 0) {
                $length = 25;
            }
            $length = min($length, 100);

            $tenants = $query->orderBy($orderBy, $orderDir)
                ->skip($start)
                ->take($length)
                ->get()
                ->map(function ($tenant) use ($can_read, $can_update) {
                    $registeredDate = '-';
                    if (!empty($tenant->registered_date)) {
                        try {
                            $registeredDate = \Carbon\Carbon::parse($tenant->registered_date)->format('d-m-Y');
                        } catch (\Throwable $e) {
                            $registeredDate = (string) $tenant->registered_date;
                        }
                    }
                    return [
                        'id'               => $tenant->id,
                        'encrypted_id'     => Crypt::encrypt($tenant->id),
                        'names'            => $tenant->names ?? '-',
                        'mobile_number'    => $tenant->mobile_number ?? '-',
                        'national_id'      => $tenant->national_id ?? '-',
                        'reference_number' => $tenant->reference_number ?? '-',
                        'email_address'    => $tenant->email_address ?? '-',
                        'registered_date'  => $registeredDate,
                        'can_read'         => $can_read,
                        'can_update'       => $can_update,
                    ];
                });

            Log::info('Loaded tenants page', [
                'rows'     => $tenants->count(),
                'start'    => $start,
                'length'   => $length,
                'filtered' => $recordsFiltered,
                'total'    => $recordsTotal,
            ]);

            return response()->json([
                'draw'            => (int) $request->input('draw', 1),
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $tenants,
            ]);
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants data', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
                'path'    => $request->path(),
                'user_id' => optional(Auth::user())->id,
            ]);
            return response()->json(['error' => 'Failed to load tenants'], 500);
        }
    }

    /**
     * Display the specified client
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($id);
            return view('property.clients.details', compact('client'));
        } catch (\Exception $e) {
            Log::error('Error loading client details: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Client not found');
        }
    }


    /**
     * Get properties for a specific client (AJAX)
     */
    public function getProperties($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $properties = Property::withInactive()
                ->with(['zone', 'zoneArea', 'fieldOfficer'])
                ->select([
                    'id',
                    'client_id',
                    'reference_number',
                    'description',
                    'zone_area_id',
                    'zone_id',
                    'property_name',
                    'field_officer_id'
                ])
                ->where('client_id', $clientId)
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'encrypted_id' => Crypt::encrypt($property->id),
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'description' => $property->description ?? '-',
                        'zone' => $property->zone->zone_name ?? '-',
                        'zone_area' => $property->zoneArea->area_name ?? '-',
                        'field_officer' => $property->fieldOfficer ? strtoupper($property->fieldOfficer->full_names) : '-',
                    ];
                });

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get related properties for a client based on current client id and matching landlord names (AJAX)
     */
    public function getRelatedProperties($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($clientId);
            $normalizedClientName = preg_replace('/\s+/', ' ', trim((string) $client->names));

            // Find OTHER landlord records that share the same name — never include the current client
            $relatedClientIds = [];
            if (!empty($normalizedClientName)) {
                $relatedClientIds = Client::clients()
                    ->where('id', '!=', $client->id)
                    ->whereRaw(
                        "REGEXP_REPLACE(TRIM(COALESCE(names, '')), '\\s+', ' ', 'g') = ?",
                        [$normalizedClientName]
                    )
                    ->pluck('id');
            }

            $properties = Property::withInactive()
                ->select([
                    'id',
                    'client_id',
                    'reference_number',
                    'description',
                    'property_name',
                    'active',
                ])
                ->with('client')
                ->whereIn('client_id', $relatedClientIds)
                ->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) {
                    $landlord = $property->client;
                    return [
                        'id'              => $property->id,
                        'property_url'    => route('property.properties.show', Crypt::encrypt($property->id)),
                        'client_url'      => $property->client_id ? route('property.clients.show', Crypt::encrypt($property->client_id)) : null,
                        'reference_number'    => $property->reference_number ?? '-',
                        'description'         => $property->description ?? '-',
                        'property_name'       => $property->property_name ?? '-',
                        'status'              => (int) $property->active === 1 ? 'Active' : 'Inactive',
                        'active'              => (int) $property->active,
                        'landlord_name'  => $landlord ? ($landlord->names ?? '-') : '-',
                        'landlord_ref'   => $landlord ? ($landlord->reference_number ?? '-') : '-',
                        'landlord_phone' => $landlord ? ($landlord->mobile_number ?? '-') : '-',
                        'landlord_email' => $landlord ? ($landlord->email_address ?? '-') : '-',
                    ];
                });

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading related properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load related properties'], 500);
        }
    }

    /**
     * Returns Config: list merge batches for this landlord profile
     */
    public function getReturnsConfig($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $batches = ReturnsMergeBatch::with('items')
                ->where('anchor_client_id', $clientId)
                ->orderByDesc('id')
                ->get()
                ->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_reference' => $batch->batch_reference,
                        'landlord_name' => $batch->landlord_name,
                        'landlord_ref' => $batch->landlord_ref,
                        'items_count' => $batch->items->count(),
                        'notes' => $batch->notes ?? '-',
                        'items' => $batch->items->map(function ($item) {
                            return [
                                'property_id' => $item->property_id,
                                'property_ref' => $item->property_ref ?? '-',
                                'property_name' => $item->property_name ?? '-',
                                'landlord_ref' => $item->landlord_ref ?? '-',
                            ];
                        })->values(),
                        'created_at' => optional($batch->created_at)->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json(['data' => $batches]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns config list: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load returns configuration'], 500);
        }
    }

    /**
     * Returns Config: fetch available active properties for merge setup.
     */
    public function getReturnsConfigOptions($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($clientId);

            $eligibleProperties = $this->getEligibleReturnsMergeProperties($client);
            $assignedMap = ReturnsMergeBatchItem::whereIn('property_id', $eligibleProperties->pluck('id'))
                ->pluck('batch_id', 'property_id');

            $allEligible = $eligibleProperties
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'client_id' => $property->client_id,
                        'landlord_name' => optional($property->client)->names ?? '-',
                        'landlord_ref' => optional($property->client)->reference_number ?? '-',
                        'label' => ($property->reference_number ?? '-') . ' - ' . ($property->property_name ?? '-'),
                    ];
                })
                ->values();

            $allEligible = $allEligible->map(function ($row) use ($assignedMap) {
                $row['assigned_batch_id'] = $assignedMap->get($row['id']);
                return $row;
            })->values();

            $unbatched = $allEligible->filter(function ($row) {
                return empty($row['assigned_batch_id']);
            })->values();

            return response()->json([
                'all_eligible' => $allEligible,
                'unbatched' => $unbatched,
                'landlord' => [
                    'id' => $client->id,
                    'name' => $client->names,
                    'reference_number' => $client->reference_number,
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns config options: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load returns config options'], 500);
        }
    }

    /**
     * Returns Config: get one batch with item ids for edit.
     */
    public function getReturnsConfigBatch($encryptedId, $batchId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $batch = ReturnsMergeBatch::with('items')
                ->where('anchor_client_id', $clientId)
                ->findOrFail($batchId);

            return response()->json([
                'data' => [
                    'id' => $batch->id,
                    'batch_reference' => $batch->batch_reference,
                    'property_ids' => $batch->items->pluck('property_id')->values(),
                    'notes' => $batch->notes,
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns config batch: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load configuration details'], 500);
        }
    }

    /**
     * Returns Config: create a merge batch.
     */
    public function storeReturnsConfig(Request $request, $encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($clientId);

            $validated = $request->validate([
                'property_ids' => 'required|array|min:2',
                'property_ids.*' => 'required|integer|distinct',
                'notes' => 'nullable|string|max:500',
            ]);

            $propertyIds = array_values(array_unique(array_map('intval', $validated['property_ids'])));

            $eligibleProperties = $this->getEligibleReturnsMergeProperties($client)->keyBy('id');

            foreach ($propertyIds as $propertyId) {
                if (!$eligibleProperties->has($propertyId)) {
                    return response()->json([
                        'error' => 'One or more selected properties are invalid, inactive, or not under the same landlord name.'
                    ], 422);
                }
            }

            $conflictPropertyIds = ReturnsMergeBatchItem::whereIn('property_id', $propertyIds)
                ->pluck('property_id')
                ->map(fn($id) => (int) $id)
                ->all();

            if (!empty($conflictPropertyIds)) {
                return response()->json([
                    'error' => 'Some properties are already assigned to another merge batch. Remove overlaps and try again.'
                ], 422);
            }

            DB::beginTransaction();

            $batch = ReturnsMergeBatch::create([
                'batch_reference' => $this->generateReturnsMergeReference(),
                'anchor_client_id' => $client->id,
                'landlord_name' => $client->names ?? '-',
                'landlord_name_normalized' => $this->normalizeLandlordName((string) $client->names),
                'landlord_id' => $client->id,
                'landlord_ref' => $client->reference_number,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'date_time' => now(),
            ]);

            foreach ($propertyIds as $propertyId) {
                $property = $eligibleProperties->get($propertyId);
                $landlord = $property->client;

                ReturnsMergeBatchItem::create([
                    'batch_id' => $batch->id,
                    'property_id' => $property->id,
                    'property_ref' => $property->reference_number,
                    'property_name' => $property->property_name,
                    'landlord_id' => $property->client_id,
                    'landlord_ref' => optional($landlord)->reference_number,
                    'landlord_name' => optional($landlord)->names,
                    'date_time' => now(),
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Returns merge configuration created successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating returns config: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create returns config'], 500);
        }
    }

    /**
     * Returns Config: update an existing merge batch.
     */
    public function updateReturnsConfig(Request $request, $encryptedId, $batchId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($clientId);

            $batch = ReturnsMergeBatch::where('anchor_client_id', $client->id)->findOrFail($batchId);

            $validated = $request->validate([
                'property_ids' => 'required|array|min:2',
                'property_ids.*' => 'required|integer|distinct',
                'notes' => 'nullable|string|max:500',
            ]);

            $propertyIds = array_values(array_unique(array_map('intval', $validated['property_ids'])));

            $eligibleProperties = $this->getEligibleReturnsMergeProperties($client)->keyBy('id');
            foreach ($propertyIds as $propertyId) {
                if (!$eligibleProperties->has($propertyId)) {
                    return response()->json([
                        'error' => 'One or more selected properties are invalid, inactive, or not under the same landlord name.'
                    ], 422);
                }
            }

            $conflictExists = ReturnsMergeBatchItem::whereIn('property_id', $propertyIds)
                ->where('batch_id', '!=', $batch->id)
                ->exists();

            if ($conflictExists) {
                return response()->json([
                    'error' => 'Some selected properties already belong to another merge batch.'
                ], 422);
            }

            DB::beginTransaction();

            $batch->update([
                'notes' => $validated['notes'] ?? null,
                'updated_by' => Auth::id(),
                'landlord_name' => $client->names ?? '-',
                'landlord_name_normalized' => $this->normalizeLandlordName((string) $client->names),
                'landlord_ref' => $client->reference_number,
            ]);

            ReturnsMergeBatchItem::where('batch_id', $batch->id)->delete();

            foreach ($propertyIds as $propertyId) {
                $property = $eligibleProperties->get($propertyId);
                $landlord = $property->client;

                ReturnsMergeBatchItem::create([
                    'batch_id' => $batch->id,
                    'property_id' => $property->id,
                    'property_ref' => $property->reference_number,
                    'property_name' => $property->property_name,
                    'landlord_id' => $property->client_id,
                    'landlord_ref' => optional($landlord)->reference_number,
                    'landlord_name' => optional($landlord)->names,
                    'date_time' => now(),
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Returns merge configuration updated successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating returns config: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update returns config'], 500);
        }
    }

    /**
     * Returns Config: delete a merge batch.
     */
    public function deleteReturnsConfig($encryptedId, $batchId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $batch = ReturnsMergeBatch::where('anchor_client_id', $clientId)->findOrFail($batchId);
            $batch->delete();

            return response()->json(['message' => 'Returns merge configuration deleted successfully.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting returns config: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete returns config'], 500);
        }
    }

    /**
     * Returns Config: disband all batches for current landlord profile.
     */
    public function disbandAllReturnsConfig($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            ReturnsMergeBatch::where('anchor_client_id', $clientId)->delete();

            return response()->json(['message' => 'All merge batches have been disbanded.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error disbanding all returns config: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to disband all merge batches'], 500);
        }
    }

    /**
     * Get properties dropdown for a specific client (AJAX)
     */
    public function getClientProperties($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $properties = Property::select(['id', 'property_name', 'reference_number'])
                ->where('client_id', $clientId)
                ->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'text' => $property->property_name . ' (' . ($property->reference_number ?? 'N/A') . ')'
                    ];
                });

            return response()->json($properties);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get units for a specific property (AJAX)
     */
    public function getUnits(Request $request)
    {
        try {
            $propertyId = $request->property_id;

            $units = Unit::with(['property', 'zone'])
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
                        'occupancy_status' => $unit->occupancy_status_id == 1 ? 'Occupied' : 'Vacant',
                    ];
                });

            return response()->json(['data' => $units]);
        } catch (\Exception $e) {
            Log::error('Error loading units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    /**
     * Get tenants for a specific property (AJAX)
     */
    public function getTenants(Request $request)
    {
        try {
            $propertyId = $request->property_id;

            $tenants = Client::with(['unit'])
                ->select([
                    'id',
                    'names',
                    'mobile_number',
                    'email_address',
                    'account_name',
                    'account_number',
                    'reference_number',
                    'address',
                    'registered_date',
                    'property_id',
                    'unit_id'
                ])
                ->tenants()
                ->where('property_id', $propertyId)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'encrypted_id' => Crypt::encrypt($tenant->id),
                        'names' => $tenant->names ?? '-',
                        'mobile_number' => $tenant->mobile_number ?? '-',
                        'email_address' => $tenant->email_address ?? '-',
                        'account_name' => $tenant->account_name ?? '-',
                        'account_number' => $tenant->account_number ?? '-',
                        'reference_number' => $tenant->reference_number ?? '-',
                        'address' => $tenant->address ?? '-',
                        'registered_date' => $tenant->registered_date ? $tenant->registered_date->format('d-m-Y') : '-',
                        'unit_name' => $tenant->unit->unit_name ?? '-',
                    ];
                });

            return response()->json(['data' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tenants'], 500);
        }
    }

    /**
     * Get leases for a specific client, optional property filter (AJAX)
     */
    public function getLeases($encryptedId, Request $request)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);
            $propertyId = $request->property_id;

            $query = LeaseAgreement::with(['tenant', 'property', 'unit'])
                ->select(['id', 'ref_number', 'tenant_id', 'property_id', 'unit_id', 'date_start', 'date_end'])
                ->where('client_id', $clientId);

            if ($propertyId) {
                $query->where('property_id', $propertyId);
            }

            $leases = $query->orderBy('date_start', 'desc')->get()->map(function ($lease) {
                return [
                    'id' => $lease->id,
                    'ref_number' => $lease->ref_number ?? '-',
                    'tenant' => $lease->tenant->names ?? '-',
                    'property' => $lease->property->property_name ?? '-',
                    'unit' => $lease->unit->unit_name ?? '-',
                    'date_start' => $lease->date_start ? $lease->date_start->format('d-m-Y') : '-',
                    'date_end' => $lease->date_end ? $lease->date_end->format('d-m-Y') : '-',
                ];
            });

            return response()->json(['data' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * Get recoveries for a specific client (AJAX)
     */
    public function getRecoveries($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $recoveries = \App\Models\Recovery::with(['property', 'zone', 'transactionType', 'createdBy'])
                ->where('client_id', $clientId)
                ->orderBy('recovery_date', 'desc')
                ->get()
                ->map(function ($recovery) {
                    return [
                        'id' => $recovery->id,
                        'encrypted_id' => Crypt::encrypt($recovery->id),
                        'ref_number' => $recovery->ref_number ?? '-',
                        'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d/m/y') : '-',
                        'amount' => 'KES ' . number_format($recovery->amount ?? 0, 2),
                        'property' => $recovery->property->property_name ?? '-',
                        'zone' => $recovery->zone->zone_name ?? '-',
                        'transaction_type' => $recovery->transactionType->type_name ?? '-',
                        'commisionable' => $recovery->commisionable ?? 'No',
                        'created_by' => $recovery->createdBy->name ?? '-',
                    ];
                });

            return response()->json(['data' => $recoveries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recoveries: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load recoveries'], 500);
        }
    }

    /**
     * Get inspections for a specific client (AJAX)
     */
    public function getInspections($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $inspections = \App\Models\Inspection::with(['property', 'zone', 'createdBy', 'units'])
                ->where('client_id', $clientId)
                ->orderBy('visited_date_time', 'desc')
                ->get()
                ->map(function ($inspection) {
                    return [
                        'id' => $inspection->id,
                        'encrypted_id' => Crypt::encrypt($inspection->id),
                        'property' => $inspection->property->property_name ?? '-',
                        'zone' => $inspection->zone->zone_name ?? '-',
                        'visited_date' => $inspection->visited_date_time ? \Carbon\Carbon::parse($inspection->visited_date_time)->format('d/m/y H:i') : '-',
                        'left_date' => $inspection->left_date_time ? \Carbon\Carbon::parse($inspection->left_date_time)->format('d/m/y H:i') : '-',
                        'duration' => $inspection->duration ? $inspection->duration . ' mins' : '-',
                        'units_count' => $inspection->units->count(),
                        'created_by' => $inspection->createdBy->name ?? '-',
                    ];
                });

            return response()->json(['data' => $inspections]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading inspections: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load inspections'], 500);
        }
    }

    /**
     * Get repairs & maintenance requests for a client (AJAX)
     */
    public function getRepairs($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $repairs = \App\Models\RepairRequest::with(['property', 'createdBy'])
                ->where('client_id', $clientId)
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($repair) {
                    return [
                        'id' => $repair->id,
                        'encrypted_id' => Crypt::encrypt($repair->id),
                        'ref_number' => $repair->ref_number ?? '-',
                        'property_name' => $repair->property->property_name ?? '-',
                        'visited_date_time' => $repair->visited_date_time ? \Carbon\Carbon::parse($repair->visited_date_time)->format('d-m-Y H:i') : '-',
                        'created_by_name' => $repair->createdBy->name ?? '-',
                        'current_status_id' => $repair->current_status_id,
                    ];
                });

            return response()->json(['data' => $repairs]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading repairs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load repairs'], 500);
        }
    }

    /**
     * Display tenant listing page
     */
    public function tenantsListing()
    {
        try {
            return view('property.tenants.listing');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenants listing: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenants listing');
        }
    }

    /**
     * Get tenants listing data for DataTable (server-side filtering)
     */
    public function getTenantsListingData(Request $request)
    {
        try {
            $query = Client::withoutGlobalScope('user_clients_properties')
                ->where('sys_pm_clients.reference_number', 'LIKE', 'TEN%')
                ->leftJoin('sys_pm_properties', 'sys_pm_properties.id', '=', 'sys_pm_clients.property_id')
                ->leftJoin('sys_pm_clients as landlord', 'landlord.id', '=', 'sys_pm_properties.client_id')
                ->select(
                    'sys_pm_clients.id',
                    'sys_pm_clients.names',
                    'sys_pm_clients.mobile_number',
                    'sys_pm_clients.national_id',
                    'sys_pm_properties.property_name',
                    'landlord.names as landlord_name'
                );

            // Scope tenants to properties assigned to the logged-in user (unless role is_global)
            $userId = Auth::id();
            $isGlobal = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.is_global', true)
                ->exists();

            if (!$isGlobal) {
                $staffId = DB::table('sys_user_staff_links')
                    ->where('user_id', $userId)
                    ->value('staff_id');

                if (!$staffId) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereExists(function ($sub) use ($staffId) {
                        $sub->selectRaw('1')
                            ->from('sys_pm_lease_agreements')
                            ->join('sys_pm_zones_assignments', 'sys_pm_zones_assignments.property_id', '=', 'sys_pm_lease_agreements.property_id')
                            ->whereColumn('sys_pm_lease_agreements.tenant_id', 'sys_pm_clients.id')
                            ->where('sys_pm_zones_assignments.staff_id', $staffId)
                            ->where('sys_pm_zones_assignments.current_status_id', 1);
                    });
                }
            }

            // Column filters (only apply when 3+ characters)
            if ($request->filled('filter_names') && strlen($request->filter_names) >= 3) {
                $query->where('sys_pm_clients.names', 'ILIKE', '%' . $request->filter_names . '%');
            }
            if ($request->filled('filter_phone') && strlen($request->filter_phone) >= 3) {
                $query->where('sys_pm_clients.mobile_number', 'ILIKE', '%' . $request->filter_phone . '%');
            }
            if ($request->filled('filter_id_number') && strlen($request->filter_id_number) >= 3) {
                $query->where('sys_pm_clients.national_id', 'ILIKE', '%' . $request->filter_id_number . '%');
            }
            if ($request->filled('filter_property') && strlen($request->filter_property) >= 3) {
                $query->where('sys_pm_properties.property_name', 'ILIKE', '%' . $request->filter_property . '%');
            }
            if ($request->filled('filter_landlord') && strlen($request->filter_landlord) >= 3) {
                $query->where('landlord.names', 'ILIKE', '%' . $request->filter_landlord . '%');
            }

            $tenants = $query->orderBy('sys_pm_clients.names')
                ->limit(1000)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'encrypted_id' => Crypt::encrypt($t->id),
                        'names' => $t->names ?? '-',
                        'mobile_number' => $t->mobile_number ?? '-',
                        'national_id' => $t->national_id ?? '-',
                        'property_name' => $t->property_name ?? '-',
                        'landlord_name' => $t->landlord_name ?? '-',
                    ];
                });

            return response()->json([
                'success' => true,
                'count' => $tenants->count(),
                'data' => $tenants,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching tenants listing data: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch tenants'], 500);
        }
    }




    /**
     * Search tenants (AJAX)
     */
    public function searchTenants(Request $request)
    {
        try {
            $searchTerm = $request->search;

            $query = Client::withoutGlobalScope('user_clients_properties')
                ->select(['id', 'names', 'mobile_number', 'national_id', 'reference_number'])
                ->tenants()
                ->where(function ($query) use ($searchTerm) {
                    $query->where('names', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('mobile_number', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('national_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('reference_number', 'LIKE', "%{$searchTerm}%");
                });

            // Scope tenants to properties assigned to the logged-in user (unless role is_global)
            $userId = Auth::id();
            $isGlobal = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.is_global', true)
                ->exists();

            if (!$isGlobal) {
                $staffId = DB::table('sys_user_staff_links')
                    ->where('user_id', $userId)
                    ->value('staff_id');

                if (!$staffId) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereExists(function ($sub) use ($staffId) {
                        $sub->selectRaw('1')
                            ->from('sys_pm_lease_agreements')
                            ->join('sys_pm_zones_assignments', 'sys_pm_zones_assignments.property_id', '=', 'sys_pm_lease_agreements.property_id')
                            ->whereColumn('sys_pm_lease_agreements.tenant_id', 'sys_pm_clients.id')
                            ->where('sys_pm_zones_assignments.staff_id', $staffId)
                            ->where('sys_pm_zones_assignments.current_status_id', 1);
                    });
                }
            }

            $tenants = $query->get()
                ->map(function ($tenant) {
                    return [
                        'encrypted_id' => $tenant->encrypted_id,
                        'names' => $tenant->names,
                        'mobile_number' => $tenant->mobile_number,
                        'national_id' => $tenant->national_id,
                        'reference_number' => $tenant->reference_number,
                    ];
                });

            return response()->json(['tenants' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching tenants: ' . $e->getMessage());
            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    /**
     * Display tenant details
     */
    public function showTenant($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $tenant = Client::with(['property', 'unit.zone'])
                ->tenants()
                ->findOrFail($id);
            $tenantBalance = \App\Services\TenantBalanceService::getBalance((int) $id);
            return view('property.tenants.details', compact('tenant', 'tenantBalance'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant details: ' . $e->getMessage());
            return redirect()->route('property.tenants.listing')->with('error', 'Tenant not found');
        }
    }

    /**
     * Get banks and branches (AJAX)
     */
    public function getBanksAndBranches()
    {
        try {
            $banks = Bank::select(['id', 'bank_name'])->orderBy('bank_name', 'asc')->get();

            $branches = BankBranch::select(['id', 'bank_id', 'branch_name'])
                ->orderBy('branch_name', 'asc')
                ->get()
                ->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'bank_id' => $branch->bank_id,
                        'branch_name' => $branch->branch_name,
                    ];
                });

            return response()->json([
                'banks' => $banks,
                'branches' => $branches,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading banks and branches: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load banks and branches'], 500);
        }
    }

    /**
     * Show the form for creating a new client
     */
    public function create()
    {
        try {
            return view('property.clients.create');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client create form: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Failed to load form');
        }
    }

    /**
     * Store a newly created client
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $this->validateClientRequest($request);
            Log::info('Validated client data: ' . json_encode($validated));

            $referenceNumber = $this->generateClientReferenceNumber();
            Log::info('Generated reference number: ' . $referenceNumber);

            $photoPath = $this->handlePhotoUpload($request);

            $client = $this->createClient($validated, $referenceNumber, $photoPath);
            //Log::info('Client created with ID: ' . $client->id . ' by User ID: ' . Auth::id());

            $this->saveBankAccounts($request, $client->id);
            Log::info('Bank accounts saved for client ID: ' . $client->id);

            $this->saveOtherContacts($request, $client->id);
            Log::info('Other contacts saved for client ID: ' . $client->id);

            $this->saveDocuments($request, $client->id);
            Log::info('Documents saved for client ID: ' . $client->id);

            $this->saveNextOfKin($request, $client->id);
            Log::info('Next of kin saved for client ID: ' . $client->id);

            DB::commit();

            return redirect()->route('property.clients.show', Crypt::encrypt($client->id))
                ->with('success', 'Client created successfully with reference number: ' . $referenceNumber);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating client: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create client: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Validate client request
     */
    private function validateClientRequest(Request $request)
    {
        return $request->validate([
            'select_time' => 'nullable|date',
            'client_id' => 'nullable|string|max:255',
            'title' => 'required|string|max:10',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'surname2' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:50',
            'prefix' => 'nullable|string|max:20',
            'gender' => 'required|in:Male,Female,Other',
            'pin_number' => 'nullable|string|max:50',
            'preferred_language_id' => 'nullable|integer',
            'passport_number' => 'nullable|string|max:50',
            'mobile_number' => 'required|string|size:12|regex:/^254[0-9]{9}$/',
            'email_address' => 'nullable|email|max:255',
            'nationality_id' => 'nullable|integer',
            'client_type_id' => 'required|integer|in:1,2,3',
            'vat_number' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'overdraft_penalty' => 'nullable|numeric',
            'address' => 'nullable|string',
            'address_2' => 'nullable|string',
            'address_3' => 'nullable|string',

            // Bank accounts validation
            'banks' => 'nullable|array',
            'banks.*.bank_id' => 'nullable|integer',
            'banks.*.branch_id' => 'nullable|integer',
            'banks.*.account_name' => 'nullable|string|max:255',
            'banks.*.account_number' => 'nullable|string|max:50',
            'banks.*.return_percentage' => 'nullable|numeric',

            // Other contacts validation
            'contacts' => 'nullable|array',
            'contacts.*.names' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.mobile' => 'nullable|string|max:20',

            // Documents validation
            'documents' => 'nullable|array',
            'documents.*.file_type' => 'nullable|string',
            'documents.*.other_file_type' => 'nullable|string|max:255',
            'documents.*.file' => 'nullable|file|max:10240',

            // Next of Kin validation
            'next_of_kin' => 'nullable|array',
            'next_of_kin.*.first_name' => 'nullable|string|max:255',
            'next_of_kin.*.second_name' => 'nullable|string|max:255',
            'next_of_kin.*.last_name' => 'nullable|string|max:255',
            'next_of_kin.*.mobile' => 'nullable|string|max:20',
            'next_of_kin.*.email' => 'nullable|email|max:255',
            'next_of_kin.*.relation' => 'nullable|string|max:100',
            'next_of_kin.*.national_id' => 'nullable|string|max:50',
        ]);
    }

    private function normalizeLandlordName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name));
    }

    private function getEligibleReturnsMergeProperties(Client $client)
    {
        $normalizedClientName = $this->normalizeLandlordName((string) $client->names);

        $relatedClientIds = collect([$client->id]);
        if (!empty($normalizedClientName)) {
            $relatedClientIds = Client::clients()
                ->whereRaw(
                    "REGEXP_REPLACE(TRIM(COALESCE(names, '')), '\\s+', ' ', 'g') = ?",
                    [$normalizedClientName]
                )
                ->pluck('id');
        }

        return Property::withInactive()
            ->with('client:id,names,reference_number')
            ->select(['id', 'client_id', 'reference_number', 'property_name', 'active'])
            ->where('active', 1)
            ->whereIn('client_id', $relatedClientIds)
            ->orderBy('property_name', 'asc')
            ->get();
    }

    private function generateReturnsMergeReference(): string
    {
        do {
            $reference = 'RMB-' . now()->format('Ymd-His') . '-' . random_int(100, 999);
        } while (ReturnsMergeBatch::where('batch_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Generate client reference number based on client type
     */
    private function generateClientReferenceNumber()
    {
        $prefix = "LAN";

        // Query for the last client with this specific prefix
        $lastClient = Client::where('reference_number', 'LIKE', "{$prefix}-%")
            ->orderBy('reference_number', 'desc')
            ->first();

        if ($lastClient) {
            // Extract the number part after the last hyphen
            $lastNumber = intval(substr($lastClient->reference_number, strrpos($lastClient->reference_number, '-') + 1));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}-{$newNumber}";
    }

    /**
     * Handle photo upload
     */
    private function handlePhotoUpload(Request $request)
    {
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            return $request->file('photo')->store('clients/photos', 'public');
        }
        return null;
    }

    /**
     * Create the main client record
     */
    private function createClient(array $validated, string $referenceNumber, ?string $photoPath)
    {
        $clientData = [
            'reference_number' => $referenceNumber,
            'client_type_id' => $validated['client_type_id'],
            'type_id' => $validated['client_type_id'], // For compatibility
            'title' => $validated['title'],
            'names' => trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']),
            'second_name' => $validated['middle_name'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'last_name' => $validated['last_name'],
            'national_id' => $validated['national_id'] ?? null,
            'gender' => $validated['gender'],
            'pin_number' => $validated['pin_number'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'nationality_id' => $validated['nationality_id'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'overdraft_penalty' => $validated['overdraft_penalty'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo' => $photoPath,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'prefered_messages_language_id' =>  $validated['preferred_language_id'], // Client type
            'registered_date' => $validated['select_time'] ?? now(),
            'date_time' => $validated['select_time'] ?? now(),
            'current_status_id' => '1',
            'created_by' => Auth::id(),
        ];

        return Client::create($clientData);
    }

    /**
     * Save bank accounts to sys_pm_clients_17_repeat
     */
    private function saveBankAccounts(Request $request, int $clientId)
    {
        if (!$request->has('banks')) {
            return;
        }

        foreach ($request->input('banks') as $bankData) {
            // Skip empty entries
            if (empty($bankData['bank_id']) && empty($bankData['account_number'])) {
                continue;
            }

            ClientBankAccount::create([
                'parent_id' => $clientId,
                'bank_id' => $bankData['bank_id'] ?? null,
                'branch' => $bankData['branch_id'] ?? null,
                'account_name' => $bankData['account_name'] ?? null,
                'account_number' => $bankData['account_number'] ?? null,
                'return_percentage' => $bankData['return_percentage'] ?? null,
            ]);
        }
    }

    /**
     * Save other contacts to sys_pm_clients_19_repeat
     */
    private function saveOtherContacts(Request $request, int $clientId)
    {
        if (!$request->has('contacts')) {
            return;
        }

        foreach ($request->input('contacts') as $contactData) {
            // Skip empty entries
            if (empty($contactData['names']) && empty($contactData['email']) && empty($contactData['mobile'])) {
                continue;
            }

            ClientOtherContact::create([
                'parent_id' => $clientId,
                'contact_names' => $contactData['names'] ?? null,
                'other_email' => $contactData['email'] ?? null,
                'other_mobile_number' => $contactData['mobile'] ?? null,
            ]);
        }
    }

    /**
     * Save documents to sys_pm_clients_65_repeat
     */
    private function saveDocuments(Request $request, int $clientId)
    {
        if (!$request->has('documents')) {
            return;
        }

        foreach ($request->input('documents') as $index => $documentData) {
            // Skip if no file uploaded
            if (!$request->hasFile("documents.{$index}.file")) {
                continue;
            }

            $file = $request->file("documents.{$index}.file");
            if ($file && $file->isValid()) {
                // Store the file
                $filePath = $file->store('clients/' . $clientId . '/documents', 'public');

                // Determine file type
                $fileType = $documentData['file_type'] ?? 'Other';
                if ($fileType === 'Other' && !empty($documentData['other_file_type'])) {
                    $fileType = $documentData['other_file_type'];
                }

                ClientDocument::create([
                    'parent_id' => $clientId,
                    'documents' => $filePath,
                    'file_type' => $fileType,
                ]);
            }
        }
    }

    /**
     * Save next of kin to sys_pm_clients_66_repeat
     */
    private function saveNextOfKin(Request $request, int $clientId)
    {
        if (!$request->has('next_of_kin')) {
            return;
        }

        foreach ($request->input('next_of_kin') as $kinData) {
            // Skip empty entries
            if (empty($kinData['first_name']) && empty($kinData['last_name']) && empty($kinData['mobile'])) {
                continue;
            }

            // Construct full name
            $fullName = trim(
                ($kinData['first_name'] ?? '') . ' ' .
                    ($kinData['second_name'] ?? '') . ' ' .
                    ($kinData['last_name'] ?? '')
            );

            ClientNextOfKin::create([
                'parent_id' => $clientId,
                'kin_first_name' => $kinData['first_name'] ?? null,
                'kin_second_name' => $kinData['second_name'] ?? null,
                'kin_last_name' => $kinData['last_name'] ?? null,
                'names' => $fullName,
                'mobile_number' => $kinData['mobile'] ?? null,
                'email_address' => $kinData['email'] ?? null,
                'relationship' => $kinData['relation'] ?? null,
                'national_id' => $kinData['national_id'] ?? null,
            ]);
        }
    }


    /**
     * Show the form for editing a client
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);

            $client = Client::with([
                'bankAccounts.bank',
                'bankAccounts.branch',
                'otherContacts',
                'documents',
                'nextOfKins'
            ])->findOrFail($id);

            // dd($client);


            return view('property.clients.edit', compact('client'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client edit form: ' . $e->getMessage());
            return redirect()->route('property.clients.index')->with('error', 'Client not found');
        }
    }

    /**
     * Update the specified client
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $client = Client::findOrFail($id);

            $validated = $this->validateClientRequest($request);
            Log::info('Validated client update data for ID: ' . $id);

            // Handle photo upload
            $photoPath = $this->handlePhotoUpload($request);
            if ($photoPath) {
                // Delete old photo if exists
                if ($client->photo && Storage::disk('public')->exists($client->photo)) {
                    Storage::disk('public')->delete($client->photo);
                }
            } else {
                $photoPath = $client->photo; // Keep existing photo
            }

            // Update client
            $this->updateClient($client, $validated, $photoPath);
            Log::info('Client updated with ID: ' . $id . ' by User ID: ' . Auth::id());

            // Update related records
            $this->updateBankAccounts($request, $client->id);
            Log::info('Bank accounts updated for client ID: ' . $client->id);

            $this->updateOtherContacts($request, $client->id);
            Log::info('Other contacts updated for client ID: ' . $client->id);

            $this->updateDocuments($request, $client->id);
            Log::info('Documents updated for client ID: ' . $client->id);

            $this->updateNextOfKin($request, $client->id);
            Log::info('Next of kin updated for client ID: ' . $client->id);

            DB::commit();

            return redirect()->route('property.clients.show', $encryptedId)
                ->with('success', 'Client updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating client: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update client: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update the main client record
     */
    private function updateClient(Client $client, array $validated, ?string $photoPath)
    {
        $client->update([
            'client_type_id' => $validated['client_type_id'],
            'type_id' => $validated['client_type_id'],
            'title' => $validated['title'],
            'names' => trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']),
            'second_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'national_id' => $validated['national_id'] ?? null,
            'gender' => $validated['gender'],
            'pin_number' => $validated['pin_number'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'nationality_id' => $validated['nationality_id'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'overdraft_penalty' => $validated['overdraft_penalty'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo' => $photoPath,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'prefered_messages_language_id' => $validated['preferred_language_id'],
            'registered_date' => $validated['select_time'] ?? $client->registered_date,
            'date_time' => $validated['select_time'] ?? $client->date_time,
        ]);
    }

    /**
     * Update bank accounts
     */
    private function updateBankAccounts(Request $request, int $clientId)
    {
        // Delete existing bank accounts
        ClientBankAccount::where('parent_id', $clientId)->delete();

        // Save new bank accounts
        $this->saveBankAccounts($request, $clientId);
    }

    /**
     * Update other contacts
     */
    private function updateOtherContacts(Request $request, int $clientId)
    {
        // Delete existing contacts
        ClientOtherContact::where('parent_id', $clientId)->delete();

        // Save new contacts
        $this->saveOtherContacts($request, $clientId);
    }

    /**
     * Update documents
     */
    private function updateDocuments(Request $request, int $clientId)
    {
        // Only add new documents, don't delete existing ones
        $this->saveDocuments($request, $clientId);
    }

    /**
     * Update next of kin
     */
    private function updateNextOfKin(Request $request, int $clientId)
    {
        // Delete existing next of kin
        ClientNextOfKin::where('parent_id', $clientId)->delete();

        // Save new next of kin
        $this->saveNextOfKin($request, $clientId);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use App\Models\BankBranch;
use Illuminate\Http\Request;
use App\Models\ClientDocument;
use App\Models\ClientBankDetail;
use App\Models\LeaseAgreement;
use App\Models\ClientNextOfKin;
use App\Models\ClientBankAccount;
use App\Models\ReturnsMergeBatch;
use App\Models\ReturnsMergeBatchItem;
use App\Services\ErrorDispatcher;
use App\Models\ClientOtherContact;
use App\Models\PropertyBankDetail;
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
            // Honor the client_type param (Landlord vs Tenant) instead of always returning landlords.
            $clientType = $request->input('client_type');
            $typeScope = strcasecmp((string) $clientType, 'Tenant') === 0 ? 'tenants' : 'clients';

            $baseQuery = Client::{$typeScope}();
            $query = Client::{$typeScope}()->select(['id', 'names', 'mobile_number', 'national_id', 'reference_number', 'email_address', 'registered_date']);
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
                            ->orWhere('reference_number', 'ILIKE', "%{$token}%")
                            ->orWhere('mobile_number', 'ILIKE', "%{$token}%")
                            ->orWhere('email_address', 'ILIKE', "%{$token}%");

                        // "id" is an integer column — comparing it against a non-numeric
                        // search term (e.g. any name search) throws a fatal type error on
                        // Postgres, which was silently swallowing every text search here.
                        if (is_numeric($token)) {
                            $q->orWhere('id', $token);
                        }
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
                        // "id" is an integer column — comparing it against non-numeric input
                        // throws a fatal type error on Postgres instead of just matching nothing.
                        if (is_numeric($colSearch)) {
                            $query->where($field, $colSearch);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
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
                        // "id" is an integer column — comparing it against non-numeric input
                        // throws a fatal type error on Postgres instead of just matching nothing.
                        if (is_numeric($colSearch)) {
                            $query->where($field, $colSearch);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
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
     * Get portfolio & financial stats for the Stats tab (AJAX).
     */
    public function getStats($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            // Property IDs owned by this client
            $propertyIds = DB::table('sys_pm_properties')
                ->where('client_id', $clientId)
                ->pluck('id');

            $totalProperties = $propertyIds->count();

            // Unit counts — was occupancy_status_id = 1/3, the legacy-migration
            // numbering scheme (and wrong even under it: legacy 3 is
            // "unclassified", not vacant; app-native units aren't counted at all).
            // Lease-based instead, matching Unit::activeLeaseAgreement()'s
            // docblock/scopeVacant() — the single source of truth for this
            // established repeatedly this session.
            $totalUnits    = Unit::whereIn('property_id', $propertyIds)->count();
            $occupiedUnits = Unit::whereIn('property_id', $propertyIds)->whereHas('activeLeaseAgreement')->count();
            $vacantUnits   = $totalUnits - $occupiedUnits;
            $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0;

            // Active leases / total tenants. Was scoped by client_id (the
            // landlord column on the lease itself) — checked live data:
            // client_id is NULL on 44,909 of 73,619 leases (61%), including
            // every single lease for the landlord this was tested against, so
            // that scoping alone zeroed these two cards regardless of the
            // status filter. property_id is reliably populated (confirmed:
            // getTenantHistory() below already relies on it), so scope through
            // this landlord's properties instead. Also was current_status_id
            // = 5 AND date_end >= now() — virtually every migrated lease sits
            // at 1 (Pending) or 2 (Terminated-equivalent), never 5, and
            // 56,000+ have a null date_end — matches the current_status_id IN
            // (1, 5), no-date-filter convention established this session.
            $activeLeases = DB::table('sys_pm_lease_agreements')
                ->whereIn('property_id', $propertyIds)
                ->whereIn('current_status_id', [1, 5])
                ->count();

            $totalTenants = DB::table('sys_pm_lease_agreements')
                ->whereIn('property_id', $propertyIds)
                ->whereIn('current_status_id', [1, 5])
                ->distinct('tenant_id')
                ->count('tenant_id');

            // SLA coverage — same "current" definition as
            // SLAController::findCurrentSlaForProperty(): date_end null or
            // still in the future.
            $propertiesWithCurrentSla = DB::table('sys_pm_service_agreements')
                ->whereIn('property_id', $propertyIds)
                ->where(function ($q) {
                    $q->whereNull('date_end')->orWhereDate('date_end', '>=', now());
                })
                ->distinct()
                ->pluck('property_id');

            $propertiesMissingSla = Property::whereIn('id', $propertyIds)
                ->whereNotIn('id', $propertiesWithCurrentSla)
                ->orderBy('property_name')
                ->pluck('property_name');

            // Financial — from debtors invoices
            $fin = DB::table('sys_pm_debtors_invoices')
                ->where('client_id', $clientId)
                ->selectRaw('
                    COALESCE(SUM(total_amount), 0)     as total_invoiced,
                    COALESCE(SUM(current_balance), 0)  as total_arrears
                ')
                ->first();

            $invoiced  = (float) ($fin->total_invoiced ?? 0);
            $arrears   = (float) ($fin->total_arrears  ?? 0);
            $collected = max(0, $invoiced - $arrears);
            $collectionRate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0;

            return response()->json([
                'success'              => true,
                'totalProperties'      => $totalProperties,
                'totalUnits'           => $totalUnits,
                'vacantUnits'          => $vacantUnits,
                'occupiedUnits'        => $occupiedUnits,
                'occupancyRate'        => $occupancyRate,
                'activeLeases'         => $activeLeases,
                'totalTenants'         => $totalTenants,
                'propertiesWithSla'    => $propertiesWithCurrentSla->count(),
                'propertiesMissingSla' => $propertiesMissingSla->values(),
                'invoiced'             => $invoiced,
                'collected'            => $collected,
                'arrears'              => $arrears,
                'collectionRate'       => $collectionRate,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading client stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Every tenant (current or past) across this landlord's entire portfolio —
     * one flat table, not nested per unit (see getUnits() for the per-unit
     * version, built last session for the property/landlord show pages' Units
     * tabs). Excludes Withdrawn (29) leases — same convention as getUnits().
     */
    public function getTenantHistory($encryptedId)
    {
        try {
            $clientId = Crypt::decrypt($encryptedId);

            $propertyIds = DB::table('sys_pm_properties')
                ->where('client_id', $clientId)
                ->pluck('id');

            $leases = DB::table('sys_pm_lease_agreements')
                ->whereIn('property_id', $propertyIds)
                ->whereIn('current_status_id', [1, 5, 2, 28])
                ->orderBy('date_start', 'desc')
                ->limit(500)
                ->get(['id', 'ref_number', 'tenant_id', 'property_id', 'unit_id', 'date_start', 'date_end', 'current_status_id']);

            $tenantNames = Client::withAll()->whereIn('id', $leases->pluck('tenant_id')->filter()->unique())->pluck('names', 'id');
            $propertyNames = DB::table('sys_pm_properties')->whereIn('id', $leases->pluck('property_id')->filter()->unique())->pluck('property_name', 'id');
            $unitCodes = DB::table('sys_pm_units')->whereIn('id', $leases->pluck('unit_id')->filter()->unique())->pluck('unit_code', 'id');

            $statusLabels = [1 => 'Pending Approval', 5 => 'Active', 2 => 'Renewed', 28 => 'Terminated'];

            $data = $leases->map(function ($lease) use ($tenantNames, $propertyNames, $unitCodes, $statusLabels) {
                return [
                    'ref_number' => $lease->ref_number ?? '-',
                    'tenant_name' => $tenantNames->get($lease->tenant_id, 'N/A'),
                    'property_name' => $propertyNames->get($lease->property_id, 'N/A'),
                    'unit_code' => $unitCodes->get($lease->unit_id, 'N/A'),
                    'date_start' => $lease->date_start ? \Carbon\Carbon::parse($lease->date_start)->format('d M Y') : '-',
                    'date_end' => $lease->date_end ? \Carbon\Carbon::parse($lease->date_end)->format('d M Y') : 'Ongoing',
                    'status' => $statusLabels[$lease->current_status_id] ?? 'Unknown',
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tenant history: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load tenant history'], 500);
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
                    'field_officer_id',
                    'active'
                ])
                ->where('client_id', $clientId)
                ->get()
                ->map(function ($property) {
                    $propertyBankDetail = PropertyBankDetail::with(['clientBankDetail.bank', 'clientBankDetail.branch'])
                        ->where('property_id', $property->id)
                        ->first();

                    $bankName = '-';
                    $accountNumber = '-';
                    $propertyBankId = null;

                    if ($propertyBankDetail && $propertyBankDetail->clientBankDetail) {
                        $bankName = $propertyBankDetail->clientBankDetail->bank->bank_name ?? '-';
                        $accountNumber = $propertyBankDetail->clientBankDetail->account_number ?? '-';
                        $propertyBankId = $propertyBankDetail->id;
                    }

                    return [
                        'id' => $property->id,
                        'encrypted_id' => Crypt::encrypt($property->id),
                        'reference_number' => $property->reference_number ?? '-',
                        'property_name' => $property->property_name ?? '-',
                        'description' => $property->description ?? '-',
                        'zone' => $property->zone->zone_name ?? '-',
                        'zone_area' => $property->zoneArea->area_name ?? '-',
                        'field_officer' => $property->fieldOfficer ? strtoupper($property->fieldOfficer->full_names) : '-',
                        'bank_name' => $bankName,
                        'account_number' => $accountNumber,
                        'property_bank_id' => $propertyBankId,
                        'client_id' => $property->client_id,
                        'active' => (bool) $property->active,
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
     * Get units for a property, or every unit across a landlord's properties
     * (AJAX). Shared by the property show page (always passes property_id)
     * and the landlord show page's Units tab (passes property_id when a
     * specific property is picked, or client_id alone for its "All
     * Properties" view).
     *
     * occupancy_status used to be `occupancy_status_id == 38 ? 'Occupied' :
     * 'Vacant'` — the same unreliable signal replaced elsewhere this session
     * (occupancy_status_id carries conflicting numbering schemes across the
     * codebase; see Unit::activeLeaseAgreement()'s docblock for the full
     * finding). Now lease-based, consistent with every other vacancy check.
     */
    public function getUnits(Request $request)
    {
        try {
            $propertyId = $request->property_id;
            $clientId = $request->client_id;

            $canEdit = Auth::user()->hasPermission('update_units');

            $query = Unit::with(['property', 'zone', 'activeLeaseAgreement'])
                ->select([
                    'id',
                    'unit_code',
                    'unit_name',
                    'unit_number',
                    'description',
                    'rent_amount',
                    'property_id',
                    'zone_id',
                    'occupancy_status_id',
                    'unit_type_id',
                    'usage_type_id',
                    'unit_condition_id',
                    'topology_id',
                    'category_id',
                    'vat_able',
                    'initial_water_meter_reading',
                ]);

            if ($propertyId) {
                $query->where('property_id', $propertyId);
            } elseif ($clientId) {
                $query->whereIn('property_id', Property::where('client_id', $clientId)->pluck('id'));
            } else {
                return response()->json(['data' => []]);
            }

            $units = $query->get();

            // Tenant history per unit — every lease except Withdrawn (29): a real
            // tenant was, or is, associated with the unit for Pending/Approved/
            // Renewed-out/Terminated. Bulk-fetched, not per-unit, to avoid N+1.
            $unitIds = $units->pluck('id');
            $leasesByUnit = LeaseAgreement::whereIn('unit_id', $unitIds)
                ->whereIn('current_status_id', [1, 5, 2, 28])
                ->orderBy('date_start', 'desc')
                ->get(['id', 'unit_id', 'tenant_id', 'ref_number', 'date_start', 'date_end', 'current_status_id'])
                ->groupBy('unit_id');

            // withAll(): plain Client::whereIn()/eager-loaded tenant relations get
            // silently filtered by user_clients_properties, hiding real tenants
            // outside the current user's assigned properties — same gotcha already
            // fixed in UnitsController::getAvailableUnits() this session.
            $tenantIds = $leasesByUnit->flatten()->pluck('tenant_id')->filter()->unique();
            $tenantNames = Client::withAll()->whereIn('id', $tenantIds)->pluck('names', 'id');

            $leaseStatusLabels = [1 => 'Pending Approval', 5 => 'Active', 2 => 'Renewed', 28 => 'Terminated'];

            $units = $units->map(function ($unit) use ($canEdit, $leasesByUnit, $tenantNames, $leaseStatusLabels) {
                $tenants = ($leasesByUnit->get($unit->id) ?? collect())->map(function ($lease) use ($tenantNames, $leaseStatusLabels) {
                    return [
                        'ref_number' => $lease->ref_number ?? '-',
                        'tenant_name' => $tenantNames->get($lease->tenant_id, 'N/A'),
                        'date_start' => $lease->date_start ? $lease->date_start->format('d M Y') : '-',
                        'date_end' => $lease->date_end ? $lease->date_end->format('d M Y') : 'Ongoing',
                        'status' => $leaseStatusLabels[$lease->current_status_id] ?? 'Unknown',
                    ];
                })->values();

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
                    'occupancy_status' => $unit->activeLeaseAgreement ? 'Occupied' : 'Vacant',
                    'can_edit' => $canEdit,
                    'tenants' => $tenants,
                    // Raw values for pre-filling the edit modal
                    'unit_name_raw' => $unit->unit_name,
                    'unit_number_raw' => $unit->unit_number,
                    'description_raw' => $unit->description,
                    'rent_amount_raw' => $unit->rent_amount,
                    'unit_type_id' => $unit->unit_type_id,
                    'usage_type_id' => $unit->usage_type_id,
                    'unit_condition_id' => $unit->unit_condition_id,
                    'topology_id' => $unit->topology_id,
                    'category_id' => $unit->category_id,
                    'vat_able' => $unit->vat_able,
                    'initial_water_meter_reading' => $unit->initial_water_meter_reading,
                    'occupancy_status_id' => $unit->occupancy_status_id,
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
                ->select(['id', 'ref_number', 'tenant_id', 'property_id', 'unit_id', 'date_start', 'date_end', 'service_charges_amount'])
                ->where('client_id', $clientId);

            if ($propertyId) {
                $query->where('property_id', $propertyId);
            }

            $leases = $query->orderBy('date_start', 'desc')->get()->map(function ($lease) {
                return [
                    'id' => $lease->id,
                    'encrypted_id' => Crypt::encrypt($lease->id),
                    'ref_number' => $lease->ref_number ?? '-',
                    'tenant' => $lease->tenant->names ?? '-',
                    'property' => $lease->property->property_name ?? '-',
                    'unit' => $lease->unit->unit_name ?? '-',
                    'date_start' => $lease->date_start ? $lease->date_start->format('d-m-Y') : '-',
                    'date_end' => $lease->date_end ? $lease->date_end->format('d-m-Y') : '-',
                    'service_charges_amount' => $lease->service_charges_amount,
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
            $status = $request->input('status', 'active'); // active | exited | pending_unit | all

            $query = Client::withoutGlobalScope('user_clients_properties')
                ->where('sys_pm_clients.reference_number', 'LIKE', 'TEN%')
                ->leftJoin('sys_pm_properties', 'sys_pm_properties.id', '=', 'sys_pm_clients.property_id')
                ->leftJoin('sys_pm_clients as landlord', 'landlord.id', '=', 'sys_pm_properties.client_id')
                ->leftJoin('cb_tenant_exits', function ($join) {
                    $join->on('cb_tenant_exits.tenant_id', '=', 'sys_pm_clients.id')
                         ->where('cb_tenant_exits.current_status_id', 2);
                })
                ->select(
                    'sys_pm_clients.id',
                    'sys_pm_clients.names',
                    'sys_pm_clients.mobile_number',
                    'sys_pm_clients.national_id',
                    'sys_pm_clients.property_id',
                    'sys_pm_properties.property_name',
                    'landlord.names as landlord_name',
                    'cb_tenant_exits.actual_exit_date',
                    'cb_tenant_exits.exit_reason',
                    DB::raw('CASE WHEN cb_tenant_exits.id IS NOT NULL THEN 1 ELSE 0 END as is_exited')
                );

            // Exclude tenants whose assigned property has been explicitly deactivated,
            // but keep tenants with no property_id at all visible — a left-joined-but
            // unmatched row has sys_pm_properties.active as NULL, so the whereNull half
            // of this orWhere is what re-includes those rows correctly.
            $query->where(function ($q) {
                $q->whereNull('sys_pm_clients.property_id')
                  ->orWhere('sys_pm_properties.active', 1);
            });

            if ($status === 'active') {
                $query->whereNull('cb_tenant_exits.id');
            } elseif ($status === 'exited') {
                $query->whereNotNull('cb_tenant_exits.id');
            } elseif ($status === 'pending_unit') {
                // Bulk-uploaded tenants whose row had no Unit Code — awaiting manual
                // assignment via TenantsController::assignUnit().
                $query->whereNull('sys_pm_clients.property_id');
            }


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
                        'id'             => $t->id,
                        'encrypted_id'   => Crypt::encrypt($t->id),
                        'names'          => $t->names ?? '-',
                        'mobile_number'  => $t->mobile_number ?? '-',
                        'national_id'    => $t->national_id ?? '-',
                        'property_name'  => $t->property_name ?? '-',
                        'landlord_name'  => $t->landlord_name ?? '-',
                        'pending_unit'   => is_null($t->property_id),
                        'is_exited'      => (bool) $t->is_exited,
                        'exit_date'      => $t->actual_exit_date
                            ? \Carbon\Carbon::parse($t->actual_exit_date)->format('d M Y')
                            : null,
                        'exit_reason'    => $t->exit_reason ?? null,
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
            $tenant = Client::with([
                    // Bypass the zone-assignment scope on this eager load — the tenant record
                    // was already confirmed accessible above, so their own assigned property
                    // shouldn't disappear just because sys_pm_zones_assignments has no row for
                    // it (a real data gap that otherwise silently nulls this relation).
                    'property' => fn ($query) => $query->withoutGlobalScope('user_properties'),
                    'unit.zone',
                    'deposits',
                ])
                ->tenants()
                ->findOrFail($id);

            return view('property.tenants.details', compact('tenant'));
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
     * Check whether a landlord already exists with the given National ID
     * (Individual) or Registration Number (Company). Used both for the
     * live on-blur checker on the create form and as a data source for the
     * server-side uniqueness check in validateIndividualOrCompanyFields().
     */
    public function checkIdentifierExists(Request $request)
    {
        $clientTypeId = (int) $request->input('client_type_id');
        $value = trim((string) $request->input('value', ''));

        if ($value === '') {
            return response()->json(['exists' => false]);
        }

        $column = $clientTypeId === 3 ? 'company_registration_number' : 'national_id';

        // When checking from the edit form, exclude the record being edited so it
        // doesn't get flagged as a duplicate of itself.
        $excludeId = null;
        if ($request->filled('exclude_id')) {
            try {
                $excludeId = Crypt::decrypt($request->input('exclude_id'));
            } catch (\Exception $e) {
                $excludeId = null;
            }
        }

        $match = Client::landlords()
            ->where($column, $value)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->select(['id', 'names', 'reference_number'])
            ->first();

        return response()->json([
            'exists' => (bool) $match,
            'name' => $match->names ?? null,
            'reference_number' => $match->reference_number ?? null,
        ]);
    }

    /**
     * Store a newly created client
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $this->validateClientRequest($request);
            $this->validateIndividualOrCompanyFields($validated);
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
        } catch (\Throwable $e) {
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
            'title' => 'nullable|string|max:10',
            // Individual-only fields: not required here since a Company client
            // (client_type_id 3) doesn't fill these in. store()/update() apply
            // their own conditional requirement via validateIndividualOrCompanyFields().
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'surname2' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:50',
            'prefix' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Male,Female,Other',
            'pin_number' => 'nullable|string|max:50',

            // Company-only fields (create form's "Company" client type)
            'company_name' => 'nullable|string|max:255',
            'company_registration_number' => 'nullable|string|max:255',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_title' => 'nullable|string|max:255',
            'contact_person_mobile' => 'nullable|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',
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

    /**
     * Individual vs Company are mutually exclusive on both the create and edit
     * client forms (client_type_id 1 = Individual, 3 = Company). Their required
     * fields can't live in validateClientRequest()'s rule array since Company
     * doesn't fill in Individual-only fields and vice versa. Shared by store()
     * and update() — $excludeClientId lets update() skip the record being
     * edited when checking for duplicates of itself.
     */
    private function validateIndividualOrCompanyFields(array $validated, ?int $excludeClientId = null): void
    {
        if ((int) $validated['client_type_id'] === 3) {
            if (empty($validated['company_name'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'company_name' => ['Company name is required for a Company client.'],
                ]);
            }

            if (!empty($validated['company_registration_number'])
                && Client::landlords()
                    ->where('company_registration_number', trim($validated['company_registration_number']))
                    ->when($excludeClientId, fn($q) => $q->where('id', '!=', $excludeClientId))
                    ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'company_registration_number' => ['A landlord with this registration number already exists.'],
                ]);
            }
        } else {
            $missing = [];
            if (empty($validated['first_name'])) {
                $missing['first_name'] = ['First name is required for an Individual client.'];
            }
            if (empty($validated['last_name'])) {
                $missing['last_name'] = ['Last name is required for an Individual client.'];
            }
            if (empty($validated['gender'])) {
                $missing['gender'] = ['Gender is required for an Individual client.'];
            }
            if (!empty($missing)) {
                throw \Illuminate\Validation\ValidationException::withMessages($missing);
            }

            if (!empty($validated['national_id'])
                && Client::landlords()
                    ->where('national_id', trim($validated['national_id']))
                    ->when($excludeClientId, fn($q) => $q->where('id', '!=', $excludeClientId))
                    ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'national_id' => ['A landlord with this National ID already exists.'],
                ]);
            }
        }
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

        // Only consider well-formed "LAN-<digits>" reference numbers when computing the next
        // number. A handful of malformed legacy entries exist (e.g. "LAN-LAN00855", "LAN-A") —
        // a plain string ORDER BY lets one of those win (since "LAN-L..." sorts above "LAN-0...")
        // and intval() of its non-numeric suffix silently resets the sequence back to 0001.
        $lastNumber = Client::where('reference_number', 'LIKE', "{$prefix}-%")
            ->pluck('reference_number')
            ->map(function ($ref) use ($prefix) {
                return preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $ref, $matches)
                    ? (int) $matches[1]
                    : null;
            })
            ->filter(fn ($number) => $number !== null)
            ->max();

        // Pad to at least 5 digits — matches the width already established by existing
        // records (e.g. "LAN-01266"); str_pad leaves it untouched once numbers grow past that.
        $newNumber = str_pad(($lastNumber ?? 0) + 1, 5, '0', STR_PAD_LEFT);

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
        $isCompany = (int) $validated['client_type_id'] === 3;

        $names = $isCompany
            ? trim($validated['company_name'])
            : trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']);

        $clientData = [
            'reference_number' => $referenceNumber,
            'client_type_id' => $validated['client_type_id'],
            // 1 = Landlord, per Client::landlords()/tenants() scopes used throughout the
            // app. This form is landlord-only (tenants go through TenantsController), so
            // this must always be 1 regardless of client_type_id (Individual/Company) —
            // it was previously set to client_type_id itself, which silently made every
            // Company landlord invisible to landlord-scoped reports/lists.
            'type_id' => 1,
            'title' => $isCompany ? null : $validated['title'],
            'names' => $names,
            'second_name' => $isCompany ? null : ($validated['middle_name'] ?? null),
            'last_name' => $isCompany ? null : $validated['last_name'],
            'national_id' => $isCompany ? null : ($validated['national_id'] ?? null),
            'passport_number' => $isCompany ? null : ($validated['passport_number'] ?? null),
            'gender' => $isCompany ? null : $validated['gender'],
            'pin_number' => $validated['pin_number'] ?? null,
            'nationality_id' => $isCompany ? null : ($validated['nationality_id'] ?? null),
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo' => $photoPath,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'prefered_messages_language_id' => $validated['preferred_language_id'] ?? null,
            'registered_date' => $validated['select_time'] ?? now(),
            'date_time' => $validated['select_time'] ?? now(),
            'current_status_id' => '1',
            'created_by' => Auth::id(),
            'company_name' => $isCompany ? $validated['company_name'] : null,
            'company_registration_number' => $isCompany ? ($validated['company_registration_number'] ?? null) : null,
            'contact_person_name' => $isCompany ? ($validated['contact_person_name'] ?? null) : null,
            'contact_person_title' => $isCompany ? ($validated['contact_person_title'] ?? null) : null,
            'contact_person_mobile' => $isCompany ? ($validated['contact_person_mobile'] ?? null) : null,
            'contact_person_email' => $isCompany ? ($validated['contact_person_email'] ?? null) : null,
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

            ClientBankDetail::create([
                'client_id'=> $clientId,
                'bank_id'=> $bankData['bank_id'] ?? null,
                'branch_id' => $bankData['branch_id'] ?? null,
                'account_number' => $bankData['account_number'] ?? null,
                'account_name' => $bankData['account_name'] ?? null,
                'is_default' => $bankData['is_default'] ?? 0,
                'added_by' => Auth::id(),
                'added_on' => now(),
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
            $this->validateIndividualOrCompanyFields($validated, $id);
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
        $isCompany = (int) $validated['client_type_id'] === 3;

        $names = $isCompany
            ? trim($validated['company_name'])
            : trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']);

        $client->update([
            'client_type_id' => $validated['client_type_id'],
            'type_id' => 1, // Landlord — see comment in createClient()
            'title' => $isCompany ? null : $validated['title'],
            'names' => $names,
            'second_name' => $isCompany ? null : ($validated['middle_name'] ?? null),
            'last_name' => $isCompany ? null : $validated['last_name'],
            'national_id' => $isCompany ? null : ($validated['national_id'] ?? null),
            'passport_number' => $isCompany ? null : ($validated['passport_number'] ?? null),
            'gender' => $isCompany ? null : $validated['gender'],
            'pin_number' => $validated['pin_number'] ?? null,
            'nationality_id' => $isCompany ? null : ($validated['nationality_id'] ?? null),
            'mobile_number' => $validated['mobile_number'],
            'email_address' => $validated['email_address'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo' => $photoPath,
            'address_2' => $validated['address_2'] ?? null,
            'address_3' => $validated['address_3'] ?? null,
            'prefered_messages_language_id' => $validated['preferred_language_id'],
            'registered_date' => $validated['select_time'] ?? $client->registered_date,
            'date_time' => $validated['select_time'] ?? $client->date_time,
            'company_name' => $isCompany ? $validated['company_name'] : null,
            'company_registration_number' => $isCompany ? ($validated['company_registration_number'] ?? null) : null,
            'contact_person_name' => $isCompany ? ($validated['contact_person_name'] ?? null) : null,
            'contact_person_title' => $isCompany ? ($validated['contact_person_title'] ?? null) : null,
            'contact_person_mobile' => $isCompany ? ($validated['contact_person_mobile'] ?? null) : null,
            'contact_person_email' => $isCompany ? ($validated['contact_person_email'] ?? null) : null,
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

    /**
     * Get available client bank details for a property
     */
    public function getAvailablePropertyBanks(Request $request)
    {
        try {
            $propertyId = $request->property_id;
            $clientId = $request->client_id;

            $banks = ClientBankDetail::with(['bank', 'branch'])
                ->where('client_id', $clientId)
                ->get()
                ->map(function ($bankDetail) {
                    return [
                        'id' => $bankDetail->id,
                        'bank_name' => $bankDetail->bank->bank_name ?? '-',
                        'branch_name' => $bankDetail->branch->branch_name ?? '-',
                        'account_number' => $bankDetail->account_number ?? '-',
                        'account_name' => $bankDetail->account_name ?? '-',
                    ];
                });

            return response()->json(['success' => true, 'data' => $banks]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading available banks: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load available banks'], 500);
        }
    }

    /**
     * Assign a bank to a property
     */
    public function assignPropertyBank(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_id' => 'required|exists:sys_pm_properties,id',
                'client_id' => 'required|exists:sys_pm_clients,id',
                'client_bank_id' => 'required|exists:clients_bank_details,id',
            ]);

            DB::beginTransaction();

            PropertyBankDetail::where('property_id', $validated['property_id'])->delete();

            $propertyBank = PropertyBankDetail::create([
                'property_id' => $validated['property_id'],
                'client_id' => $validated['client_id'],
                'client_bank_id' => $validated['client_bank_id'],
                'added_by' => Auth::id(),
                'added_on' => now(),
            ]);

            DB::commit();

            $bankDetail = ClientBankDetail::with(['bank', 'branch'])
                ->find($validated['client_bank_id']);

            return response()->json([
                'success' => true,
                'message' => 'Bank details assigned successfully',
                'data' => [
                    'bank_name' => $bankDetail->bank->bank_name ?? '-',
                    'account_number' => $bankDetail->account_number ?? '-',
                    'property_bank_id' => $propertyBank->id,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error assigning property bank: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign bank details'], 500);
        }
    }

    /**
     * Remove a bank from a property
     */
    public function removePropertyBank(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_bank_id' => 'required|exists:property_bank_details,id',
            ]);

            DB::beginTransaction();

            PropertyBankDetail::where('id', $validated['property_bank_id'])->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank details removed successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error removing property bank: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to remove bank details'], 500);
        }
    }

    /**
     * Get property bank details (for returns vouchers)
     */
    public function getPropertyBankDetails(Request $request)
    {
        try {
            $propertyId = $request->property_id;

            $propertyBank = PropertyBankDetail::with(['clientBankDetail.bank', 'clientBankDetail.branch'])
                ->where('property_id', $propertyId)
                ->first();

            if (!$propertyBank || !$propertyBank->clientBankDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'No bank details found for this property'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'bank_name' => $propertyBank->clientBankDetail->bank->bank_name ?? '-',
                    'branch_name' => $propertyBank->clientBankDetail->branch->branch_name ?? '-',
                    'account_name' => $propertyBank->clientBankDetail->account_name ?? '-',
                    'account_number' => $propertyBank->clientBankDetail->account_number ?? '-',
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property bank details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load bank details'], 500);
        }
    }
}

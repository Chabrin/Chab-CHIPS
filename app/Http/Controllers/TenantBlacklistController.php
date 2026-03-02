<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Zone;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\LeaseAgreement;
use App\Models\TenantBlacklist;
use App\Models\ServiceAgreement;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TenantBlacklistReason;
use Illuminate\Support\Facades\Auth;

class TenantBlacklistController extends Controller
{
    /**
     * Display a listing of blacklisted tenants
     */
    public function index()
    {
        try {
            return view('property.properties.blacklist.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading blacklist index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load blacklist');
        }
    }

    /**
     * Get all blacklisted tenants (AJAX)
     */
    public function getData()
    {
        try {
            $canViewDetails = Auth::user()->isAbleTo('read_tenant_blacklist');
            $canEditBlacklist = Auth::user()->isAbleTo('update_tenant_blacklist');
            $canReadTenants = Auth::user()->isAbleTo('read_tenants');
            $canReadClients = Auth::user()->isAbleTo('read_clients');

            $blacklists = TenantBlacklist::with([
                'tenant:id,names',
                'client:id,names',
                'property:id,property_name',
                'unit:id,unit_code',
                'lease:id,ref_number',
                'reason:id,reason_name',
                'zone:id,zone_name',
                'createdBy:id,name'
            ])
            ->select([
                'id',
                'date_time',
                'blacklist_date',
                'zone_id',
                'client_id',
                'property_id',
                'unit_id',
                'lease_id',
                'tenant_id',
                'reason_id',
                'description',
                'created_by',
                'current_balance',
                'current_status_id',
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($blacklist) use ($canViewDetails, $canEditBlacklist, $canReadTenants, $canReadClients) {
                return [
                    'id' => $blacklist->id,
                    'encrypted_id' => \Illuminate\Support\Facades\Crypt::encrypt($blacklist->id),
                    'date_time' => $blacklist->date_time,
                    'blacklist_date' => $blacklist->blacklist_date,
                    'zone_name' => $blacklist->zone->zone_name ?? 'N/A',
                    'client_id' => $blacklist->client_id,
                    'encrypted_client_id' => \Illuminate\Support\Facades\Crypt::encrypt($blacklist->client_id),
                    'client_name' => $blacklist->client->names ?? 'N/A',
                    'property_name' => $blacklist->property->property_name ?? 'N/A',
                    'unit_code' => $blacklist->unit->unit_code ?? 'N/A',
                    'lease_ref' => $blacklist->lease->ref_number ?? 'N/A',
                    'tenant_id' => $blacklist->tenant_id,
                    'encrypted_tenant_id' => \Illuminate\Support\Facades\Crypt::encrypt($blacklist->tenant_id),
                    'tenant_name' => $blacklist->tenant->names ?? 'N/A',
                    'reason' => $blacklist->reason->reason_name ?? 'N/A',
                    'description' => $blacklist->description,
                    'created_by_name' => $blacklist->createdBy->name ?? 'N/A',
                    'current_balance' => $blacklist->current_balance,
                    'current_status_id' => $blacklist->current_status_id,
                    'can_view_details' => $canViewDetails,
                    'can_edit_blacklist' => $canEditBlacklist,
                    'can_read_tenants' => $canReadTenants,
                    'can_read_clients' => $canReadClients,
                ];
            });

            return response()->json(['data' => $blacklists]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading blacklist data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load blacklist'], 500);
        }
    }

    /**
     * Show the form for creating a new blacklist entry
     */
    public function create()
    {
        try {
            $reasons = TenantBlacklistReason::where('is_active', true)->get();
            return view('property.properties.blacklist.create', compact('reasons'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading blacklist create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Store a newly created blacklist entry
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'blacklist_date' => 'required|date',
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'unit_id' => 'required|exists:sys_pm_units,id',
                'lease_id' => 'required|exists:sys_pm_lease_agreements,id',
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'zone_id' => 'nullable',
                'reason_id' => 'required|exists:sys_pm_tenants_blacklist_reasons,id',
                'description' => 'nullable|string',
                'current_balance' => 'required|numeric|min:0',
            ]);

            $blacklist = TenantBlacklist::create([
                'date_time' => now(),
                'blacklist_date' => $validated['blacklist_date'],
                'zone_id' => $validated['zone_id'],
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_id'],
                'lease_id' => $validated['lease_id'],
                'tenant_id' => $validated['tenant_id'],
                'reason_id' => $validated['reason_id'],
                'description' => $validated['description'],
                'created_by' => Auth::id(),
                'current_balance' => $validated['current_balance'],
                'current_status_id' => 1, // Processed
            ]);

            DB::commit();

            Log::info('Tenant blacklisted successfully', [
                'blacklist_id' => $blacklist->id,
                'tenant_id' => $blacklist->tenant_id,
                'created_by' => Auth::id()
            ]);

            return redirect()
                ->route('property.blacklist.index')
                ->with('success', 'Tenant blacklisted successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating blacklist entry: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create blacklist entry: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified blacklist entry
     */
    public function show($encryptedId)
    {
        try {
            $id = \Illuminate\Support\Facades\Crypt::decrypt($encryptedId);
            $blacklist = TenantBlacklist::with([
                'tenant:id,names',
                'client:id,names,reference_number',
                'property:id,property_name,reference_number',
                'unit:id,unit_code,unit_name',
                'lease:id,ref_number',
                'reason:id,reason_name,description',
                'zone:id,zone_name',
                'createdBy:id,name'
            ])->findOrFail($id);

            return view('property.properties.blacklist.details', compact('blacklist'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading blacklist details: ' . $e->getMessage());
            return redirect()->route('property.blacklist.index')->with('error', 'Failed to load blacklist details');
        }
    }

    /**
     * Show the form for editing a blacklist entry
     */
    public function edit($encryptedId)
    {
        try {
            $id = \Illuminate\Support\Facades\Crypt::decrypt($encryptedId);
            $blacklist = TenantBlacklist::with([
                'tenant:id,names',
                'client:id,names,reference_number',
                'property:id,property_name,reference_number,zone_id',
                'unit:id,unit_code,unit_name',
                'lease:id,ref_number',
                'reason:id,reason_name',
                'zone:id,zone_name',
                'createdBy:id,name'
            ])->findOrFail($id);

            $reasons = TenantBlacklistReason::where('is_active', true)->get();

            return view('property.properties.blacklist.edit', compact('blacklist', 'reasons'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading blacklist edit form: ' . $e->getMessage());
            return redirect()->route('property.blacklist.index')->with('error', 'Failed to load blacklist entry');
        }
    }

    /**
     * Update the specified blacklist entry
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'blacklist_date' => 'required|date',
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'unit_id' => 'required|exists:sys_pm_units,id',
                'lease_id' => 'required|exists:sys_pm_lease_agreements,id',
                'tenant_id' => 'required|exists:sys_pm_clients,id',
                'zone_id' => 'nullable',
                'reason_id' => 'required|exists:sys_pm_tenants_blacklist_reasons,id',
                'description' => 'nullable|string',
                'current_balance' => 'required|numeric|min:0',
            ]);

            $blacklist = TenantBlacklist::findOrFail($id);

            $blacklist->update([
                'blacklist_date' => $validated['blacklist_date'],
                'zone_id' => $validated['zone_id'],
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'],
                'unit_id' => $validated['unit_id'],
                'lease_id' => $validated['lease_id'],
                'tenant_id' => $validated['tenant_id'],
                'reason_id' => $validated['reason_id'],
                'description' => $validated['description'],
                'current_balance' => $validated['current_balance'],
            ]);

            DB::commit();

            Log::info('Blacklist entry updated successfully', [
                'blacklist_id' => $blacklist->id,
                'updated_by' => Auth::id()
            ]);

            return redirect()
                ->route('property.blacklist.index')
                ->with('success', 'Blacklist entry updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating blacklist entry: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update blacklist entry: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get active leases for a unit
     */
    public function getActiveLeases($unitId)
    {
        try {
            $leases = LeaseAgreement::where('unit_id', $unitId)
                ->where('current_status_id', 5) // Active
                ->with('tenant:id,names')
                ->select('id', 'ref_number', 'tenant_id')
                ->get()
                ->map(function ($lease) {
                    return [
                        'id' => $lease->id,
                        'ref_number' => $lease->ref_number,
                        'tenant_id' => $lease->tenant_id,
                        'tenant_name' => $lease->tenant->names ?? 'N/A',
                    ];
                });

            return response()->json(['leases' => $leases]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading leases: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load leases'], 500);
        }
    }

    /**
     * Get blacklist reasons (AJAX)
     */
    public function getReasons()
    {
        try {
            $reasons = TenantBlacklistReason::where('is_active', true)
                ->select('id', 'reason_name')
                ->get();

            return response()->json(['reasons' => $reasons]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading reasons: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load reasons'], 500);
        }
    }

    /**
     * Add a new reason (AJAX)
     */
    public function storeReason(Request $request)
    {
        try {
            $validated = $request->validate([
                'reason_name' => 'required|string|max:255|unique:sys_pm_tenants_blacklist_reasons,reason_name',
            ]);

            $reason = TenantBlacklistReason::create([
                'reason_name' => $validated['reason_name'],
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'reason' => [
                    'id' => $reason->id,
                    'reason_name' => $reason->reason_name
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first()
            ], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating reason: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reason'
            ], 500);
        }
    }
}

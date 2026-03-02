<?php

namespace App\Http\Controllers;

use App\Models\Recovery;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class RecoveryController extends Controller
{
    /**
     * Display a listing of recoveries
     */
    public function index()
    {
        try {
            return view('property.properties.recovery.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recoveries index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load recoveries listing');
        }
    }

    /**
     * Get recoveries list for DataTables (AJAX)
     */
    public function list(Request $request)
    {
        try {
            $recoveries = Recovery::with(['client', 'property', 'zone', 'createdBy'])
                ->orderBy('recovery_date', 'desc')
                ->get()
                ->map(function ($recovery) {
                    return [
                        'id' => $recovery->id,
                        'encrypted_id' => Crypt::encrypt($recovery->id),
                        'ref_number' => $recovery->ref_number ?? '-',
                        'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-',
                        'amount' => number_format($recovery->amount ?? 0, 2),
                        'client' => $recovery->client->names ?? '-',
                        'property' => $recovery->property->property_name ?? '-',
                        'zone' => $recovery->zone->zone_name ?? '-',
                        'commisionable' => $recovery->commisionable ?? 'No',
                        'created_by' => $recovery->createdBy->name ?? '-',
                        'narration' => $recovery->narration ?? '-',
                    ];
                });

            return response()->json(['data' => $recoveries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recoveries list: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load recoveries'], 500);
        }
    }

    /**
     * Show the form for creating a new recovery
     */
    public function create(Request $request)
    {
        try {
            
            $transaction_types = \App\Models\TransactionType::where('workflow_status_id', 1)
                ->get();

            return view('property.properties.recovery.create', compact('transaction_types'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recovery create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Display the specified recovery
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            
            $recovery = Recovery::with([
                'client',
                'property',
                'zone',
                'createdBy'
            ])->findOrFail($id);

            return view('property.properties.recovery.details', compact('recovery'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recovery details: ' . $e->getMessage());
            return redirect()->route('property.recoveries.index')->with('error', 'Recovery not found');
        }
    }

    /**
     * Show the form for editing the specified recovery
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            
            $recovery = Recovery::with([
                'client',
                'property',
                'zone'
            ])->findOrFail($id);

            $transaction_types = \App\Models\TransactionType::where('workflow_status_id', 1)
                ->get();

            return view('property.properties.recovery.edit', compact('recovery', 'transaction_types'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recovery edit form: ' . $e->getMessage());
            return redirect()->route('property.recoveries.index')->with('error', 'Recovery not found');
        }
    }

    /**
     * Update the specified recovery
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $recovery = Recovery::findOrFail($id);

            $validated = $request->validate([
                'ref_number' => 'required|string|max:255',
                'type_id' => 'required|exists:sys_pm_transaction_types,id',
                'recovery_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'narration' => 'nullable|string',
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'sla_id' => 'nullable|exists:sys_pm_service_agreements,id',
                'unit_id' => 'nullable|exists:sys_pm_units,id',
                'tenant_id' => 'nullable|exists:sys_pm_clients,id',
                'lease_id' => 'nullable|exists:sys_pm_lease_agreements,id',
                'offset_invoice_id' => 'nullable|exists:sys_pm_invoices,id',
                'commisionable' => 'required|in:Yes,No',
                'return_type_id' => 'nullable|string|max:255',
            ]);

            $recovery->update($validated);

            DB::commit();

            return redirect()
                ->route('property.recoveries.show', $encryptedId)
                ->with('success', 'Recovery updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating recovery: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to update recovery: ' . $e->getMessage())
                ->withInput();
        }
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\RecurrentClientRemittance;
use App\Models\Client;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Models\InvoiceableItem;
use App\Models\Provider;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RecurrentClientRemittancesController extends Controller
{
    public function index()
    {
        return view('finance.recurrent-client-remittances.index');
    }

    public function getData()
    {
        try {
            $canEdit = Auth::user()->isAbleTo('update_recurrent_client_remittances');

            $remittances = RecurrentClientRemittance::with(['client', 'property', 'sla'])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $remittances->map(function ($r) use ($canEdit) {
                    return [
                        'id'           => $r->id,
                        'encrypted_id' => encrypt($r->id),
                        'client'       => optional($r->client)->names,
                        'property'     => optional($r->property)->property_name,
                        'sla'          => optional($r->sla)->reference_number,
                        'type'         => $r->remittance_type_label,
                        'start_date'   => $r->start_date ? Carbon::parse($r->start_date)->format('Y-m-d') : null,
                        'every_date'   => $r->every_date,
                        'amount'       => $r->amount,
                        'narration'    => $r->narration,
                        'can_edit'     => $canEdit,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching recurrent client remittances data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load data'], 500);
        }
    }

    public function create()
    {
        return view('finance.recurrent-client-remittances.create');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'client_id'           => 'required|integer',
                'property_id'         => 'required|integer',
                'sla_id'              => 'nullable|integer',
                'remittance_type_id'  => 'required|integer',
                'start_date'          => 'required|date',
                'every_date'          => 'required|integer|min:1',
                'amount'              => 'required|numeric|min:0',
                'narration'           => 'nullable|string|max:500',
                'begin_interest_date' => 'nullable|date',
                'interest_rate'       => 'nullable|numeric|min:0',
                'item_id'             => 'nullable|integer',
                'supplier_id'         => 'nullable|integer',
                'bank_id'             => 'nullable|integer',
                'bank_branch_id'      => 'nullable|integer',
                'account_number'      => 'nullable|string|max:50',
                'bill_account_number' => 'nullable|string|max:50',
                'caretaker_id'        => 'nullable|integer',
                'current_status_id'   => 'nullable|integer',
            ]);

            $validated['date_time']    = now();
            $validated['created_by_id'] = Auth::id();
            // Default nullable int columns to 0 to match the table convention
            $validated['sla_id']      = $validated['sla_id']      ?? 0;
            $validated['caretaker_id'] = $validated['caretaker_id'] ?? 0;

            RecurrentClientRemittance::create($validated);

            return response()->json(['success' => true, 'message' => 'Recurrent remittance created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating recurrent remittance: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function edit($encryptedId)
    {
        $id = decrypt($encryptedId);
        $remittance = RecurrentClientRemittance::findOrFail($id);
        $client = $remittance->client;
        return view('finance.recurrent-client-remittances.edit', compact('remittance', 'client'));
    }

    public function update(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $remittance = RecurrentClientRemittance::findOrFail($id);

            $validated = $request->validate([
                'client_id'           => 'required|integer',
                'property_id'         => 'required|integer',
                'sla_id'              => 'nullable|integer',
                'remittance_type_id'  => 'required|integer',
                'start_date'          => 'required|date',
                'every_date'          => 'required|integer|min:1',
                'amount'              => 'required|numeric|min:0',
                'narration'           => 'nullable|string|max:500',
                'begin_interest_date' => 'nullable|date',
                'interest_rate'       => 'nullable|numeric|min:0',
                'item_id'             => 'nullable|integer',
                'supplier_id'         => 'nullable|integer',
                'bank_id'             => 'nullable|integer',
                'bank_branch_id'      => 'nullable|integer',
                'account_number'      => 'nullable|string|max:50',
                'bill_account_number' => 'nullable|string|max:50',
                'caretaker_id'        => 'nullable|integer',
                'current_status_id'   => 'nullable|integer',
            ]);

            $validated['sla_id']       = $validated['sla_id']      ?? 0;
            $validated['caretaker_id'] = $validated['caretaker_id'] ?? 0;

            $remittance->update($validated);

            return response()->json(['success' => true, 'message' => 'Recurrent remittance updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error updating recurrent remittance: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $remittance = RecurrentClientRemittance::findOrFail($id);
            $remittance->delete();

            return response()->json(['success' => true, 'message' => 'Recurrent remittance deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting recurrent remittance: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─── AJAX dropdown loaders ────────────────────────────────────────────────

    public function getProperties(Request $request)
    {
        try {
            $properties = Property::where('client_id', $request->query('client_id'))
                ->select('id', 'property_name as label')
                ->orderBy('property_name')
                ->get();
            return response()->json(['success' => true, 'properties' => $properties]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getSlas(Request $request)
    {
        try {
            $slas = ServiceAgreement::where('property_id', $request->query('property_id'))
                ->select('id', 'reference_number as label')
                ->orderBy('reference_number')
                ->get();
            return response()->json(['success' => true, 'slas' => $slas]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getInvoiceableItems()
    {
        try {
            $items = InvoiceableItem::where('is_bill', 'Yes')
                ->select('id', 'item_name as label')
                ->orderBy('item_name')
                ->get();
            return response()->json(['success' => true, 'items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getProviders()
    {
        try {
            $providers = Provider::select('id', 'names as label')->orderBy('names')->get();
            return response()->json(['success' => true, 'providers' => $providers]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getBanks()
    {
        try {
            $banks = Bank::select('id', 'bank_name as label')->orderBy('bank_name')->get();
            return response()->json(['success' => true, 'banks' => $banks]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getBankBranches(Request $request)
    {
        try {
            $branches = BankBranch::where('bank_id', $request->query('bank_id'))
                ->select('id', 'branch_name as label')
                ->orderBy('branch_name')
                ->get();
            return response()->json(['success' => true, 'branches' => $branches]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getTeamMembers()
    {
        try {
            $members = Staff::select('id', 'full_names as label')
                ->orderBy('full_names')
                ->get();
            return response()->json(['success' => true, 'members' => $members]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
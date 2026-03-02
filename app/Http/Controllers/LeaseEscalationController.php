<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Client;
use App\Models\Property;
use App\Models\LeaseAgreement;
use App\Models\LeaseEscalation;
use App\Models\LeaseEscalationApproval;
use App\Models\LeaseAgreementInvoiceableItem;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LeaseEscalationController extends Controller
{
    /**
     * Display the escalations index page.
     */
    public function index()
    {
        try {
            return view('property.escalations.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading escalations index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load escalations');
        }
    }

    /**
     * Display the create escalation form.
     */
    public function create()
    {
        try {
            return view('property.escalations.create');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading escalation create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load escalation form');
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  AJAX DATA ENDPOINTS
    // ────────────────────────────────────────────────────────────────

    /**
     * Get all escalations with filters (AJAX).
     */
    public function getEscalations(Request $request)
    {
        try {
            $query = LeaseEscalation::with([
                'client:id,names',
                'property:id,property_name',
                'unit:id,unit_code',
                'leaseAgreement:id,ref_number',
                'createdBy:id,name',
                'approvals',
            ]);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            $escalations = $query->orderBy('id', 'desc')
                ->limit(500)
                ->get()
                ->map(function ($esc) {
                    return [
                        'id'              => $esc->id,
                        'date_time'       => $esc->date_time,
                        'client_name'     => $esc->client->names ?? 'N/A',
                        'property_name'   => $esc->property->property_name ?? 'N/A',
                        'unit_code'       => $esc->unit->unit_code ?? 'All Units',
                        'lease_ref'       => $esc->leaseAgreement->ref_number ?? 'N/A',
                        'ref_number'      => $esc->reference_number,
                        'scope'           => ucfirst($esc->scope),
                        'escalation_type' => $esc->escalation_type === 'percentage' ? 'Percentage' : 'Flat Rate',
                        'escalation_value'=> $esc->escalation_value,
                        'previous_amount' => $esc->previous_amount,
                        'new_amount'      => $esc->new_amount,
                        'effective_date'  => $esc->effective_date,
                        'status'          => $esc->status,
                        'status_label'    => $esc->status_label,
                        'status_badge'    => $esc->status_badge,
                        'created_by'      => $esc->createdBy->name ?? 'N/A',
                        'approvals'       => $esc->approvals->map(fn ($a) => [
                            'step'     => $a->approval_step,
                            'label'    => $a->step_label,
                            'action'   => $a->action,
                            'user'     => $a->user->name ?? 'N/A',
                            'comments' => $a->comments,
                            'documents'=> $a->documents,
                            'date'     => $a->date_time,
                        ]),
                    ];
                });

            return response()->json(['data' => $escalations]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching escalations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load escalations'], 500);
        }
    }

    /**
     * Search landlords by name (AJAX, for Select2).
     */
    public function searchLandlords(Request $request)
    {
        try {
            $term = $request->get('q', '');

            $landlords = Client::where('reference_number', 'LIKE', 'LAN%')
                ->where(function ($q) use ($term) {
                    $q->where('names', 'LIKE', "%{$term}%")
                      ->orWhere('reference_number', 'LIKE', "%{$term}%");
                })
                ->select('id', 'names', 'reference_number')
                ->limit(30)
                ->get()
                ->map(fn ($c) => [
                    'id'   => $c->id,
                    'text'  => $c->names . ' (' . $c->reference_number . ')',
                ]);

            return response()->json(['results' => $landlords]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    /**
     * Get properties for a given landlord (AJAX).
     */
    public function getLandlordProperties(Request $request)
    {
        try {
            $clientId = $request->get('client_id');

            $properties = Property::where('client_id', $clientId)
                ->select('id', 'property_name', 'reference_number', 'usage_type_id')
                ->get();

            return response()->json(['data' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Get units with active leases for a given property (AJAX).
     */
    public function getPropertyUnits(Request $request)
    {
        try {
            $propertyId = $request->get('property_id');

            $units = Unit::where('property_id', $propertyId)
                ->with(['latestLeaseAgreement' => function ($q) {
                    $q->with(['invoiceableItems', 'tenant:id,names']);
                }])
                ->select('id', 'unit_code', 'unit_name', 'usage_type_id', 'rent_amount', 'occupancy_status_id')
                ->get()
                ->map(function ($unit) {
                    $lease = $unit->latestLeaseAgreement;
                    $totalRent = $lease
                        ? $lease->invoiceableItems->sum('amount')
                        : $unit->rent_amount;

                    return [
                        'id'            => $unit->id,
                        'unit_code'     => $unit->unit_code,
                        'unit_name'     => $unit->unit_name,
                        'usage_type_id' => $unit->usage_type_id,
                        'rent_amount'   => $totalRent,
                        'lease_id'      => $lease->id ?? null,
                        'lease_ref'     => $lease->ref_number ?? null,
                        'tenant_name'   => $lease->tenant->names ?? null,
                        'occupancy'     => $unit->occupancy_status_id,
                    ];
                });

            return response()->json(['data' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  STORE – Field Officer creates the escalation
    // ────────────────────────────────────────────────────────────────

    /**
     * Store a new escalation request (FO submits).
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id'        => 'required|integer',
            'property_id'      => 'required|integer',
            'scope'            => 'required|in:property,unit',
            'unit_ids'         => 'required_if:scope,unit|nullable|array',
            'unit_ids.*'       => 'integer',
            'escalation_type'  => 'required|in:percentage,flat_rate',
            'escalation_value' => 'required|numeric|min:0.01',
            'effective_date'   => 'required|date',
            'notes'            => 'nullable|string|max:2000',
            'documents'        => 'nullable|array',
            'documents.*'      => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $scope      = $request->scope;
            $propertyId = $request->property_id;
            $type       = $request->escalation_type;
            $value      = $request->escalation_value;

            // Determine which units are affected
            if ($scope === 'unit') {
                $units = Unit::whereIn('id', $request->unit_ids)
                    ->where('property_id', $propertyId)
                    ->get();
            } else {
                // Property-level: all units under the property
                $units = Unit::where('property_id', $propertyId)->get();
            }

            if ($units->isEmpty()) {
                return response()->json(['error' => 'No units found for this selection'], 422);
            }

            // Upload FO documents
            $docPaths = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $docPaths[] = $file->store('escalation_documents', 'public');
                }
            }
            $docPathString = !empty($docPaths) ? implode(',', $docPaths) : null;

            $createdIds = [];

            $referenceNumber = 'ESC-' . strtoupper(substr(uniqid(), -6));

            foreach ($units as $unit) {
                // Find the latest lease for this unit
                $lease = LeaseAgreement::where('unit_id', $unit->id)
                    ->where('property_id', $propertyId)
                    ->orderBy('date_start', 'desc')
                    ->first();

                // Calculate previous rent from invoiceable items
                $previousAmount = 0;
                if ($lease) {
                    $previousAmount = LeaseAgreementInvoiceableItem::where('parent_id', $lease->id)
                        ->sum('amount');
                }

                // Calculate new amount
                $newAmount = $type === 'percentage'
                    ? round($previousAmount * (1 + ($value / 100)), 2)
                    : round($previousAmount + $value, 2);

                // Create escalation record
                $escalation = LeaseEscalation::create([
                    'date_time'          => now(),
                    'reference_number'   => $referenceNumber,
                    'client_id'          => $request->client_id,
                    'property_id'        => $propertyId,
                    'unit_id'            => $unit->id,
                    'lease_agreement_id' => $lease->id ?? null,
                    'scope'              => $scope,
                    'escalation_type'    => $type,
                    'escalation_value'   => $value,
                    'previous_amount'    => $previousAmount,
                    'new_amount'         => $newAmount,
                    'effective_date'     => $request->effective_date,
                    'status'             => 'pending_zm',
                    'created_by_id'      => Auth::id(),
                    'notes'              => $request->notes,
                ]);

                // Record FO submission as step 1
                LeaseEscalationApproval::create([
                    'escalation_id' => $escalation->id,
                    'approval_step' => 1,
                    'user_id'       => Auth::id(),
                    'action'        => 'submitted',
                    'comments'      => $request->notes,
                    'documents'     => $docPathString,
                    'date_time'     => now(),
                ]);

                $createdIds[] = $escalation->id;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdIds) . ' escalation(s) created and submitted for ZM approval.',
                'ids'     => $createdIds,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating escalation: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create escalation'], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  APPROVE / REJECT – ZM and PM actions
    // ────────────────────────────────────────────────────────────────

    /**
     * Approve an escalation at the current step (ZM or PM).
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'comments'    => 'nullable|string|max:2000',
            'documents'   => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $escalation = LeaseEscalation::findOrFail($id);

            // Determine which step we are approving
            $step   = null;
            $newStatus = null;

            if ($escalation->status === 'pending_zm') {
                $step      = 2;
                $newStatus = 'pending_pm';
            } elseif ($escalation->status === 'pending_pm') {
                $step      = 3;
                $newStatus = 'approved';
            } else {
                return response()->json(['error' => 'This escalation is not pending approval'], 422);
            }

            // Upload documents (PM proof of authority)
            $docPaths = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $docPaths[] = $file->store('escalation_documents', 'public');
                }
            }
            $docPathString = !empty($docPaths) ? implode(',', $docPaths) : null;

            // Record approval
            LeaseEscalationApproval::create([
                'escalation_id' => $escalation->id,
                'approval_step' => $step,
                'user_id'       => Auth::id(),
                'action'        => 'approved',
                'comments'      => $request->comments,
                'documents'     => $docPathString,
                'date_time'     => now(),
            ]);

            $escalation->status = $newStatus;
            $escalation->save();

            // If final approval (PM), apply the escalation to the lease
            if ($newStatus === 'approved') {
                $this->applyEscalation($escalation);
            }

            DB::commit();

            $stepLabel = $step === 2 ? 'ZM' : 'PM';
            return response()->json([
                'success' => true,
                'message' => "Escalation {$stepLabel}-approved successfully." .
                    ($newStatus === 'approved' ? ' Lease amounts have been updated.' : ''),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error approving escalation: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to approve escalation'], 500);
        }
    }

    /**
     * Reject an escalation at the current step.
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'comments' => 'required|string|max:2000',
        ]);

        DB::beginTransaction();

        try {
            $escalation = LeaseEscalation::findOrFail($id);

            if (!in_array($escalation->status, ['pending_zm', 'pending_pm'])) {
                return response()->json(['error' => 'This escalation is not pending approval'], 422);
            }

            $step = $escalation->status === 'pending_zm' ? 2 : 3;

            LeaseEscalationApproval::create([
                'escalation_id' => $escalation->id,
                'approval_step' => $step,
                'user_id'       => Auth::id(),
                'action'        => 'rejected',
                'comments'      => $request->comments,
                'documents'     => null,
                'date_time'     => now(),
            ]);

            $escalation->status = 'rejected';
            $escalation->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Escalation has been rejected.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error rejecting escalation: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to reject escalation'], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  PRIVATE: Apply the approved escalation to lease records
    // ────────────────────────────────────────────────────────────────

    /**
     * Apply the escalation values to the lease invoiceable items.
     */
    protected function applyEscalation(LeaseEscalation $escalation)
    {
        if (!$escalation->lease_agreement_id) {
            return;
        }

        $lease = LeaseAgreement::find($escalation->lease_agreement_id);
        if (!$lease) {
            return;
        }

        $items = LeaseAgreementInvoiceableItem::where('parent_id', $lease->id)->get();

        foreach ($items as $item) {
            if ($escalation->escalation_type === 'percentage') {
                $item->amount = round($item->amount * (1 + ($escalation->escalation_value / 100)), 2);
            } else {
                // Flat rate: distribute proportionally or add to primary item
                // For simplicity, add the flat amount to each item proportionally
                $totalBefore = $items->sum('amount');
                if ($totalBefore > 0) {
                    $proportion = $item->amount / $totalBefore;
                    $item->amount = round($item->amount + ($escalation->escalation_value * $proportion), 2);
                }
            }
            $item->save();
        }

        // Update the lease's escalation tracking fields
        $lease->escalation_rate = $escalation->escalation_value;
        $lease->escalation_frequency = $escalation->escalation_type;
        $lease->save();
    }
}

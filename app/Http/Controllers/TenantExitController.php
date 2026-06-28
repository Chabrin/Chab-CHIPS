<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Deposit;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\TenantAccountTransaction;
use App\Models\TenantExit;
use App\Models\TenantExitCommunication;
use App\Models\TenantExitDepositRefund;
use App\Models\TenantExitInspection;
use App\Models\TenantHistory;
use App\Models\Unit;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TenantExitController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    //  PAGE ENTRY
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show the tenant exit landing page.
     * Optionally accepts an encrypted tenant_id to pre-load a tenant.
     */
    public function index(Request $request)
    {
        try {
            $tenant = null;

            if ($request->filled('tenant')) {
                try {
                    $tenantId = Crypt::decrypt($request->tenant);
                    $tenant   = Client::tenants()
                        ->with(['property', 'unit', 'lease.property', 'lease.unit'])
                        ->findOrFail($tenantId);
                } catch (\Exception $e) {
                    Log::warning('TenantExit: could not decrypt tenant param — ' . $e->getMessage());
                }
            }

            return view('property.tenants.exit.index', compact('tenant'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: index error — ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load tenant exit page.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — SEARCH HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Search tenants by name / reference / phone / email / national ID.
     * Returns the tenant's current unit automatically.
     */
    public function searchTenants(Request $request)
    {
        try {
            $search = trim($request->input('search', ''));

            if (strlen($search) < 2) {
                return response()->json(['success' => false, 'message' => 'Enter at least 2 characters'], 400);
            }

            $tenants = Client::tenants()
                ->select('id', 'names', 'mobile_number', 'email_address', 'national_id',
                         'reference_number', 'property_id', 'unit_id')
                ->where(function ($q) use ($search) {
                    $q->where('names',            'ILIKE', "%{$search}%")
                      ->orWhere('reference_number','ILIKE', "%{$search}%")
                      ->orWhere('mobile_number',   'ILIKE', "%{$search}%")
                      ->orWhere('email_address',   'ILIKE', "%{$search}%")
                      ->orWhere('national_id',     'ILIKE', "%{$search}%");
                })
                ->with(['unit:id,unit_name,unit_code', 'property:id,property_name'])
                ->orderBy('names')
                ->limit(30)
                ->get()
                ->map(fn ($t) => [
                    'id'               => $t->id,
                    'encrypted_id'     => Crypt::encrypt($t->id),
                    'names'            => $t->names,
                    'reference_number' => $t->reference_number,
                    'mobile_number'    => $t->mobile_number,
                    'email_address'    => $t->email_address,
                    'national_id'      => $t->national_id,
                    'property_name'    => $t->property->property_name ?? '-',
                    'unit_name'        => $t->unit->unit_name ?? $t->unit->unit_code ?? '-',
                    'unit_id'          => $t->unit_id,
                    'property_id'      => $t->property_id,
                ]);

            return response()->json(['success' => true, 'tenants' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: searchTenants — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Search failed'], 500);
        }
    }

    /**
     * Search properties (for the property-first search path).
     */
    public function searchProperties(Request $request)
    {
        try {
            $search = trim($request->input('search', ''));

            if (strlen($search) < 2) {
                return response()->json(['success' => false, 'message' => 'Enter at least 2 characters'], 400);
            }

            $properties = Property::select('id', 'property_name', 'reference_number')
                ->where(function ($q) use ($search) {
                    $q->where('property_name',   'ILIKE', "%{$search}%")
                      ->orWhere('reference_number','ILIKE', "%{$search}%");
                })
                ->orderBy('property_name')
                ->limit(30)
                ->get();

            return response()->json(['success' => true, 'properties' => $properties]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: searchProperties — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Search failed'], 500);
        }
    }

    /**
     * Search landlords (Client records with type_id = 1) for the exited tenants filter.
     *
     * GET /tenant-exit/search/landlords?search=…
     */
    public function searchLandlords(Request $request)
    {
        try {
            $search = trim($request->input('search', ''));

            if (strlen($search) < 2) {
                return response()->json(['success' => false, 'message' => 'Enter at least 2 characters'], 400);
            }

            $landlords = Client::where('type_id', 1)
                ->select('id', 'names', 'reference_number', 'mobile_number')
                ->where(function ($q) use ($search) {
                    $q->where('names',            'ILIKE', "%{$search}%")
                      ->orWhere('reference_number','ILIKE', "%{$search}%")
                      ->orWhere('mobile_number',   'ILIKE', "%{$search}%");
                })
                ->orderBy('names')
                ->limit(30)
                ->get();

            return response()->json(['success' => true, 'landlords' => $landlords]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: searchLandlords — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Search failed'], 500);
        }
    }

    /**
     * Get occupied units for a property (for the property-first path).
     */
    public function getOccupiedUnits($propertyId)
    {
        try {
            $units = Unit::where('property_id', $propertyId)
                ->where('occupancy_status_id', '!=', 3) // 31 = Vacant/Available
                ->whereNotNull('occupancy_status_id')
                ->select('id', 'unit_name', 'unit_code', 'rent_amount', 'occupancy_status_id')
                ->orderBy('unit_name')
                ->get()
                ->map(fn ($u) => [
                    'id'        => $u->id,
                    'unit_name' => $u->unit_name ?? $u->unit_code,
                    'unit_code' => $u->unit_code,
                    'rent'      => number_format($u->rent_amount, 2),
                    'status'    => $u->occupancy_status_id,
                ]);

            return response()->json(['success' => true, 'units' => $units]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: getOccupiedUnits — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load units'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — TENANT FULL EXIT PROFILE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load full exit profile for a tenant:
     *   - Tenant details
     *   - All leases / units ever occupied
     *   - Full account statement (all transactions, running balance)
     *   - Deposits on record
     *   - Outstanding balance summary
     *   - Any pending invoices
     *   - Existing exit record (if already initiated)
     */
    public function getTenantExitProfile(Request $request)
    {
        try {
            $tenantId = (int) Crypt::decrypt($request->input('tenant_id'));

            $tenant = Client::tenants()
                ->with([
                    'property:id,property_name,reference_number',
                    'unit:id,unit_name,unit_code,rent_amount',
                    'lease' => fn ($q) => $q
                        ->with(['property:id,property_name', 'unit:id,unit_name,unit_code'])
                        ->orderBy('date_start', 'desc'),
                    'nextOfKins',
                ])
                ->findOrFail($tenantId);

            // ── Statement ────────────────────────────────────────────────────
            $transactions = TenantAccountTransaction::where('tenant_id', $tenantId)
                ->where('current_status_id', 1)
                ->orderBy('date_time', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $statementEntries = [];
            $balance = 0;
            foreach ($transactions as $txn) {
                $debit  = abs($txn->debit_amount  ?? 0);
                $credit = abs($txn->credit_amount ?? 0);
                $balance += $credit - $debit;

                $statementEntries[] = [
                    'date'             => $txn->date_time,
                    'date_formatted'   => $txn->date_time ? date('d-m-Y', strtotime($txn->date_time)) : '-',
                    'reference'        => $txn->invoice_number ?? '-',
                    'narration'        => $txn->narration ?? ($debit > 0 ? 'Invoice' : 'Payment'),
                    'type'             => $debit > 0 ? 'invoice' : 'payment',
                    'debit'            => $debit,
                    'credit'           => $credit,
                    'balance'          => $balance,
                    'debit_formatted'  => $debit  > 0 ? number_format($debit,  2) : '-',
                    'credit_formatted' => $credit > 0 ? number_format($credit, 2) : '-',
                    'balance_formatted'=> number_format($balance, 2),
                    'property_id'      => $txn->property_id,
                    'unit_id'          => $txn->unit_id,
                ];
            }

            // ── Deposits ─────────────────────────────────────────────────────
            $deposits = [];
            if (class_exists(\App\Models\Deposit::class)) {
                $deposits = \App\Models\Deposit::where('tenant_id', $tenantId)
                    ->with(['property:id,property_name', 'unit:id,unit_name,unit_code'])
                    ->get()
                    ->map(fn ($d) => [
                        'id'            => $d->id,
                        'reference'     => $d->reference_number ?? '-',
                        'property_name' => $d->property->property_name ?? '-',
                        'unit_name'     => $d->unit->unit_name ?? $d->unit->unit_code ?? '-',
                        'amount'        => number_format($d->amount, 2),
                        'date_received' => $d->date_received ? date('d-m-Y', strtotime($d->date_received)) : '-',
                    ])
                    ->toArray();
            }

            // Total deposit refunded via exit workflow (separate from individual deposit records)
            $totalDepositRefunded = TenantExitDepositRefund::where('tenant_id', $tenantId)->sum('refund_amount');

            // ── Lease history ────────────────────────────────────────────────
            $l = $tenant->lease;

            $leaseHistory = [
                'id'          => $l->id,
                'ref_number'  => $l->ref_number ?? '-',
                'property'    => $l->property->property_name ?? '-',
                'unit'        => $l->unit->unit_name ?? $l->unit->unit_code ?? '-',
                'date_start'  => $l->date_start  ? $l->date_start->format('d M Y')  : '-',
                'date_end'    => $l->date_end    ? $l->date_end->format('d M Y')    : 'Present',
                'is_active'   => $l->lease_status === 'Active',
                'lease_status'=> $l->lease_status ?? '-',
            ];

            // ── Existing exit record (if any) ────────────────────────────────
            $exitRecord = TenantExit::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->first();

            // ── Prior stay history ────────────────────────────────────────────
            $stayHistory = TenantHistory::where('tenant_id', $tenantId)
                ->with([
                    'property:id,property_name',
                    'unit:id,unit_name,unit_code',
                    'landlord:id,names',
                ])
                ->orderBy('exit_date', 'desc')
                ->get()
                ->map(fn ($h) => [
                    'property_name'    => $h->property->property_name ?? '-',
                    'unit_name'        => $h->unit->unit_name ?? $h->unit->unit_code ?? '-',
                    'landlord_name'    => $h->landlord->names ?? '-',
                    'lease_start_date' => $h->lease_start_date ? $h->lease_start_date->format('d M Y') : '-',
                    'exit_date'        => $h->exit_date->format('d M Y'),
                    'duration'         => $h->duration_label,
                    'exit_reason'      => $h->exit_reason ?? '-',
                ]);

            return response()->json([
                'success' => true,
                'tenant'  => [
                    'id'               => $tenant->id,
                    'encrypted_id'     => Crypt::encrypt($tenant->id),
                    'names'            => $tenant->names,
                    'reference_number' => $tenant->reference_number,
                    'mobile_number'    => $tenant->mobile_number,
                    'email_address'    => $tenant->email_address,
                    'national_id'      => $tenant->national_id ?? '-',
                    'property_name'    => $tenant->property->property_name ?? '-',
                    'unit_name'        => $tenant->unit->unit_name ?? $tenant->unit->unit_code ?? '-',
                    'rent_amount'      => $tenant->rent_amount ? number_format($tenant->rent_amount, 2) : '-',
                    'lease_start_date' => $tenant->lease_start_date ? date('d M Y', strtotime($tenant->lease_start_date)) : '-',
                    'next_of_kins'     => $tenant->nextOfKins->map(fn ($n) => [
                        'names'       => $n->names,
                        'mobile'      => $n->mobile_number,
                        'email'       => $n->email_address,
                        'relationship'=> $n->relationship,
                    ]),
                ],
                'lease_history'  => $leaseHistory,
                'statement'      => $statementEntries,
                'closing_balance'=> $balance,
                'closing_balance_formatted' => number_format($balance, 2),
                'deposits'                => $deposits,
                'total_deposit_refunded'  => number_format($totalDepositRefunded, 2),
                'stay_history'            => $stayHistory,
                'exit_record'             => $exitRecord ? $exitRecord->toArray() : null,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: getTenantExitProfile — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load tenant profile'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — INITIATE / UPDATE EXIT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initiate a new tenant exit record or update an existing one.
     */
    public function initiateExit(Request $request)
    {
        $validated = $request->validate([
            'tenant_id'           => 'required|integer|exists:sys_pm_clients,id',
            'property_id'         => 'required|integer|exists:sys_pm_properties,id',
            'unit_id'             => 'required|integer|exists:sys_pm_units,id',
            'vacate_notice_date'  => 'required|date',
            'intended_exit_date'  => 'required|date|after_or_equal:vacate_notice_date',
            'actual_exit_date'    => 'nullable|date',
            'exit_reason'         => 'required|string|max:50',
            'exit_notes'          => 'nullable|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            $existing = TenantExit::where('tenant_id', $validated['tenant_id'])
                ->orderBy('created_at', 'desc')
                ->first();

            $data = [
                'tenant_id'          => $validated['tenant_id'],
                'property_id'        => $validated['property_id'],
                'unit_id'            => $validated['unit_id'],
                'vacate_notice_date' => $validated['vacate_notice_date'],
                'intended_exit_date' => $validated['intended_exit_date'],
                'actual_exit_date'   => $validated['actual_exit_date'] ?? null,
                'exit_reason'        => $validated['exit_reason'],
                'exit_notes'         => $validated['exit_notes'] ?? null,
                'current_status_id'  => 1, // In Progress
                'updated_by'         => Auth::id(),
            ];

            if ($existing) {
                $existing->update($data);
                $exitId  = $existing->id;
                $message = 'Exit record updated successfully.';
            } else {
                $data['created_by'] = Auth::id();
                $exit   = TenantExit::create($data);
                $exitId = $exit->id;
                $message = 'Exit initiated successfully.';
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => $message, 'exit_id' => $exitId]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: initiateExit — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save exit record'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — UNIT CONDITION INSPECTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save unit condition inspection report.
     */
    public function saveInspection(Request $request)
    {
        $validated = $request->validate([
            'exit_id'             => 'required|integer',
            'tenant_id'           => 'required|integer',
            'unit_id'             => 'required|integer',
            'inspection_date'     => 'required|date',
            'overall_condition'   => 'required|in:Excellent,Good,Fair,Poor,Very Poor',
            'walls_condition'     => 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'floors_condition'    => 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'ceiling_condition'   => 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'doors_condition'     => 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'windows_condition'   => 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'plumbing_condition'  => 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'electrical_condition'=> 'nullable|in:Excellent,Good,Fair,Poor,Very Poor',
            'keys_returned'       => 'required|in:Yes,No,Partial',
            'keys_notes'          => 'nullable|string|max:500',
            'damage_description'  => 'nullable|string|max:2000',
            'damage_amount'       => 'nullable|numeric|min:0',
            'cleaning_required'   => 'required|in:Yes,No',
            'cleaning_cost'       => 'nullable|numeric|min:0',
            'general_notes'       => 'nullable|string|max:2000',
            'inspected_by_name'   => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Handle photo uploads
            $photoPaths = [];
            if ($request->hasFile('inspection_photos')) {
                foreach ($request->file('inspection_photos') as $photo) {
                    if ($photo->isValid()) {
                        $photoPaths[] = $photo->store('tenant-exits/inspections', 'public');
                    }
                }
            }

            $existing = TenantExitInspection::where('exit_id', $validated['exit_id'])->first();

            $data = array_merge($validated, [
                'updated_by' => Auth::id(),
            ]);

            if ($existing) {
                // Only overwrite photos if new ones were uploaded
                if (!empty($photoPaths)) {
                    $data['photo_paths'] = $photoPaths;
                }
                $existing->update($data);
            } else {
                $data['photo_paths'] = $photoPaths;
                $data['created_by']  = Auth::id();
                TenantExitInspection::create($data);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Inspection saved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: saveInspection — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save inspection'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — COMMUNICATIONS (email / SMS)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a communication to the tenant (email and/or SMS).
     */
    public function sendCommunication(Request $request)
    {
        $validated = $request->validate([
            'exit_id'        => 'required|integer',
            'tenant_id'      => 'required|integer',
            'comm_type'      => 'required|in:Email,SMS,Both',
            'subject'        => 'required_if:comm_type,Email,Both|nullable|string|max:255',
            'message_body'   => 'required|string|max:5000',
            'email_address'  => 'required_if:comm_type,Email,Both|nullable|email',
            'mobile_number'  => 'required_if:comm_type,SMS,Both|nullable|string|max:20',
            'template_used'  => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $sentEmail = false;
            $sentSms   = false;
            $errors    = [];

            // ── Email ──────────────────────────────────────────────────────
            if (in_array($validated['comm_type'], ['Email', 'Both']) && $validated['email_address']) {
                try {
                    Mail::raw($validated['message_body'], function ($mail) use ($validated) {
                        $mail->to($validated['email_address'])
                             ->subject($validated['subject'] ?? 'Notice from Property Management');
                    });
                    $sentEmail = true;
                } catch (\Exception $e) {
                    $errors[] = 'Email failed: ' . $e->getMessage();
                    Log::error('TenantExit: email send failed — ' . $e->getMessage());
                }
            }

            // ── SMS — plug in your SMS provider here ──────────────────────
            if (in_array($validated['comm_type'], ['SMS', 'Both']) && $validated['mobile_number']) {
                try {
                    // TODO: Integrate your SMS gateway. Example:
                    // SmsService::send($validated['mobile_number'], $validated['message_body']);
                    $sentSms = true; // Mark true once real gateway is wired
                } catch (\Exception $e) {
                    $errors[] = 'SMS failed: ' . $e->getMessage();
                    Log::error('TenantExit: SMS send failed — ' . $e->getMessage());
                }
            }

            // ── Log communication ─────────────────────────────────────────
            TenantExitCommunication::create([
                'exit_id'       => $validated['exit_id'],
                'tenant_id'     => $validated['tenant_id'],
                'comm_type'     => $validated['comm_type'],
                'subject'       => $validated['subject'] ?? null,
                'message_body'  => $validated['message_body'],
                'email_address' => $validated['email_address'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'template_used' => $validated['template_used'] ?? null,
                'sent_email'    => $sentEmail,
                'sent_sms'      => $sentSms,
                'send_errors'   => !empty($errors) ? $errors : null,
                'sent_by'       => Auth::id(),
                'sent_at'       => now(),
            ]);

            DB::commit();

            $message = 'Communication logged.';
            if ($sentEmail && $sentSms)  $message = 'Email and SMS sent successfully.';
            elseif ($sentEmail)          $message = 'Email sent successfully.';
            elseif ($sentSms)            $message = 'SMS sent successfully.';
            if (!empty($errors))         $message .= ' Some errors occurred: ' . implode('; ', $errors);

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: sendCommunication — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send communication'], 500);
        }
    }

    /**
     * Get communication history for an exit record.
     */
    public function getCommunications($exitId)
    {
        try {
            $comms = TenantExitCommunication::with('sentBy:id,name')
                ->where('exit_id', $exitId)
                ->orderBy('sent_at', 'desc')
                ->get()
                ->map(fn ($c) => array_merge($c->toArray(), [
                    'sent_by_name' => $c->sentBy->name ?? null,
                ]));

            return response()->json(['success' => true, 'communications' => $comms]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: getCommunications — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load communications'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — FINALISE EXIT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Finalise the exit:
     *   - Mark exit record as Completed
     *   - Mark lease as ended
     *   - Vacate the unit
     *   - Optionally record deposit refund
     */
    public function finaliseExit(Request $request)
    {
        $validated = $request->validate([
            'exit_id'              => 'required|integer',
            'tenant_id'            => 'required|integer|exists:sys_pm_clients,id',
            'actual_exit_date'     => 'required|date',
            'deposit_refund_amount'=> 'nullable|numeric|min:0',
            'deposit_refund_notes' => 'nullable|string|max:1000',
            'final_notes'          => 'nullable|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            $tenant = Client::findOrFail($validated['tenant_id']);

            // 1. Update exit record to Completed
            TenantExit::where('id', $validated['exit_id'])->update([
                'actual_exit_date'  => $validated['actual_exit_date'],
                'current_status_id' => 2, // Completed
                'final_notes'       => $validated['final_notes'] ?? null,
                'finalised_by'      => Auth::id(),
                'finalised_at'      => now(),
            ]);

            // 2. End any active lease agreements for this tenant
            LeaseAgreement::where('tenant_id', $validated['tenant_id'])
                ->where('lease_status', 'Active')
                ->update([
                    'lease_status'      => 'Terminated',
                    'current_status_id' => 2, // Terminated
                    'date_end'          => $validated['actual_exit_date'],
                    'updated_at'        => now(),
                ]);

            // 3. Vacate the unit (set occupancy to Vacant)
            if ($tenant->unit_id) {
                Unit::where('id', $tenant->unit_id)->update([
                    'occupancy_status_id' => 3, // 31 = Vacant/Available (system-wide convention)
                    'current_status_id'   => 2,
                    'updated_at'          => now(),
                ]);
            }

            // 4. Clear tenant's current unit & property links
            $tenant->update([
                'unit_id'     => null,
                'property_id' => null,
                'updated_at'  => now(),
            ]);

            // 5. Record deposit refund if provided
            if (!empty($validated['deposit_refund_amount']) && $validated['deposit_refund_amount'] > 0) {
                TenantExitDepositRefund::create([
                    'exit_id'       => $validated['exit_id'],
                    'tenant_id'     => $validated['tenant_id'],
                    'refund_amount' => $validated['deposit_refund_amount'],
                    'refund_notes'  => $validated['deposit_refund_notes'] ?? null,
                    'refunded_by'   => Auth::id(),
                    'refunded_at'   => now(),
                ]);
            }

            // 6. Write permanent tenant stay history
            $exitRecord   = TenantExit::find($validated['exit_id']);
            $property     = Property::select('id', 'client_id')->find($exitRecord?->property_id);
            $activeLease  = LeaseAgreement::where('tenant_id', $validated['tenant_id'])
                ->whereIn('lease_status', ['Ended', 'Terminated'])
                ->orderBy('date_end', 'desc')
                ->first();

            $leaseStart  = $activeLease?->date_start;
            $exitDate    = \Carbon\Carbon::parse($validated['actual_exit_date']);
            $durationDays = $leaseStart ? (int) \Carbon\Carbon::parse($leaseStart)->diffInDays($exitDate) : null;

            TenantHistory::updateOrCreate(
                ['exit_id' => $validated['exit_id']],
                [
                    'tenant_id'        => $validated['tenant_id'],
                    'landlord_id'      => $property?->client_id,
                    'property_id'      => $exitRecord?->property_id,
                    'unit_id'          => $exitRecord?->unit_id,
                    'lease_start_date' => $leaseStart,
                    'exit_date'        => $validated['actual_exit_date'],
                    'duration_days'    => $durationDays,
                    'exit_reason'      => $exitRecord?->exit_reason,
                    'recorded_by'      => Auth::id(),
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant exit finalised successfully. Unit has been vacated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: finaliseExit — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to finalise exit'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — TENANT BY UNIT (Property → Unit search path)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the active tenant occupying a given unit.
     * Called by the Property → Unit search path after a unit is selected.
     * Returns the tenant's encrypted ID so the frontend can call loadTenantProfile()
     * exactly as the direct tenant-search path does.
     *
     * GET /tenant-exit/tenant-by-unit/{unitId}
     */
    public function getTenantByUnit(int $unitId)
    {
        try {
            $tenant = Client::tenants()
                ->where('unit_id', $unitId)
                ->select(
                    'id', 'names', 'mobile_number', 'email_address',
                    'national_id', 'reference_number', 'property_id', 'unit_id'
                )
                ->with([
                    'unit:id,unit_name,unit_code',
                    'property:id,property_name',
                ])
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active tenant found for this unit.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'tenant'  => [
                    'id'               => $tenant->id,
                    'encrypted_id'     => Crypt::encrypt($tenant->id),
                    'names'            => $tenant->names,
                    'reference_number' => $tenant->reference_number,
                    'mobile_number'    => $tenant->mobile_number,
                    'email_address'    => $tenant->email_address,
                    'national_id'      => $tenant->national_id ?? '-',
                    'property_name'    => $tenant->property->property_name ?? '-',
                    'unit_name'        => $tenant->unit->unit_name ?? $tenant->unit->unit_code ?? '-',
                    'unit_id'          => $tenant->unit_id,
                    'property_id'      => $tenant->property_id,
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: getTenantByUnit — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to resolve tenant for unit'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  COMMUNICATION TEMPLATES (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return pre-built communication templates for the compose drawer.
     */
    public function getTemplates(Request $request)
    {
        $tenantName  = $request->input('tenant_name', 'Tenant');
        $propertyName= $request->input('property_name', 'the property');
        $exitDate    = $request->input('exit_date', 'the agreed date');

        $templates = [
            [
                'key'     => 'vacate_reminder',
                'label'   => 'Vacate Reminder',
                'subject' => 'Reminder: Upcoming Vacate Date',
                'body'    => "Dear {$tenantName},\n\nThis is a friendly reminder that your vacate date is scheduled for {$exitDate}.\n\nPlease ensure the unit at {$propertyName} is vacated and all keys returned by this date.\n\nShould you have any queries, do not hesitate to contact our office.\n\nKind regards,\nProperty Management",
            ],
            [
                'key'     => 'inspection_notice',
                'label'   => 'Inspection Notice',
                'subject' => 'Unit Inspection – Exit Notice',
                'body'    => "Dear {$tenantName},\n\nWe will be conducting a unit inspection at {$propertyName} as part of your exit process.\n\nPlease ensure the unit is accessible on the agreed inspection date. Any damages or outstanding items noted will be communicated to you in writing.\n\nKind regards,\nProperty Management",
            ],
            [
                'key'     => 'deposit_info',
                'label'   => 'Deposit Refund Information',
                'subject' => 'Security Deposit – Exit Process',
                'body'    => "Dear {$tenantName},\n\nFollowing your exit from {$propertyName}, we wish to inform you that your security deposit refund is being processed.\n\nThe refund will be calculated after deductions for any outstanding rent, damages, or cleaning charges identified during the exit inspection.\n\nYou will be notified of the final amount within the statutory period.\n\nKind regards,\nProperty Management",
            ],
            [
                'key'     => 'exit_confirmation',
                'label'   => 'Exit Confirmation',
                'subject' => 'Tenancy Exit Confirmation',
                'body'    => "Dear {$tenantName},\n\nThis letter confirms that your tenancy at {$propertyName} has been formally concluded as of {$exitDate}.\n\nThank you for being a tenant with us. We wish you all the best in your future endeavours.\n\nKind regards,\nProperty Management",
            ],
            [
                'key'     => 'outstanding_balance',
                'label'   => 'Outstanding Balance Notice',
                'subject' => 'Outstanding Balance on Your Account',
                'body'    => "Dear {$tenantName},\n\nOur records indicate that there is an outstanding balance on your account as of your exit date from {$propertyName}.\n\nKindly arrange for the settlement of this amount at your earliest convenience to avoid further action.\n\nPlease contact our accounts office for payment details.\n\nKind regards,\nProperty Management",
            ],
        ];

        return response()->json(['success' => true, 'templates' => $templates]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  EXITED TENANTS — LIST PAGE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Display a paginated, filterable list of all completed tenant exits.
     *
     * GET /tenant-exit/exited
     */
    public function exitedTenantsIndex(Request $request)
    {
        try {
            // ── Build query ──────────────────────────────────────────────────
            $query = TenantExit::query()
                ->where('cb_tenant_exits.current_status_id', 2) // Completed only
                ->join('sys_pm_clients as c',       'cb_tenant_exits.tenant_id',    '=', 'c.id')
                ->join('sys_pm_properties as pr',   'cb_tenant_exits.property_id',  '=', 'pr.id')
                ->join('sys_pm_units as u',        'cb_tenant_exits.unit_id',      '=', 'u.id')
                ->leftJoin('sys_pm_clients as ll', 'pr.client_id',                 '=', 'll.id') // landlord = client record on the property (type_id=1)
                ->leftJoin('josya_users as fu',    'cb_tenant_exits.finalised_by', '=', 'fu.id')
                ->leftJoin('cb_tenant_exit_deposit_refunds as dr', function ($join) {
                    $join->on('dr.exit_id', '=', 'cb_tenant_exits.id');
                })
                ->select([
                    'cb_tenant_exits.id',
                    'cb_tenant_exits.tenant_id',
                    'cb_tenant_exits.exit_reason',
                    'cb_tenant_exits.exit_notes',
                    'cb_tenant_exits.vacate_notice_date',
                    'cb_tenant_exits.intended_exit_date',
                    'cb_tenant_exits.actual_exit_date',
                    'cb_tenant_exits.finalised_at',
                    'c.names as tenant_name',
                    'c.reference_number as tenant_ref',
                    'c.mobile_number as tenant_mobile',
                    'c.email_address as tenant_email',
                    'c.national_id as tenant_national_id',
                    'pr.property_name',
                    'pr.client_id as landlord_id',
                    'll.names as landlord_name',
                    DB::raw("COALESCE(u.unit_name, u.unit_code) as unit_name"),
                    'fu.name as finalised_by_name',
                    DB::raw('SUM(dr.refund_amount) as total_refunded'),
                ])
                ->groupBy(
                    'cb_tenant_exits.id',
                    'cb_tenant_exits.tenant_id',
                    'cb_tenant_exits.exit_reason',
                    'cb_tenant_exits.exit_notes',
                    'cb_tenant_exits.vacate_notice_date',
                    'cb_tenant_exits.intended_exit_date',
                    'cb_tenant_exits.actual_exit_date',
                    'cb_tenant_exits.finalised_at',
                    'c.names',
                    'c.reference_number',
                    'c.mobile_number',
                    'c.email_address',
                    'c.national_id',
                    'pr.property_name',
                    'pr.client_id',
                    'll.names',
                    'u.unit_name',
                    'u.unit_code',
                    'fu.name'
                );

            // ── Filters ──────────────────────────────────────────────────────
            if ($search = trim($request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('c.names',             'ILIKE', "%{$search}%")
                      ->orWhere('c.reference_number', 'ILIKE', "%{$search}%")
                      ->orWhere('c.mobile_number',    'ILIKE', "%{$search}%")
                      ->orWhere('c.national_id',      'ILIKE', "%{$search}%");
                });
            }

            if ($propertyId = $request->input('property_id')) {
                $query->where('cb_tenant_exits.property_id', $propertyId);
            }

            if ($landlordId = $request->input('landlord_id')) {
                $query->where('pr.client_id', $landlordId);
            }

            if ($reason = $request->input('exit_reason')) {
                $query->where('cb_tenant_exits.exit_reason', $reason);
            }

            if ($dateFrom = $request->input('date_from')) {
                $query->whereDate('cb_tenant_exits.actual_exit_date', '>=', $dateFrom);
            }

            if ($dateTo = $request->input('date_to')) {
                $query->whereDate('cb_tenant_exits.actual_exit_date', '<=', $dateTo);
            }

            $exits = $query->orderBy('cb_tenant_exits.actual_exit_date', 'desc')
                           ->paginate(25)
                           ->withQueryString();

            // ── Stats (unfiltered totals for the strip) ──────────────────────
            $stats = [
                'total'       => TenantExit::where('current_status_id', 2)->count(),
                'this_month'  => TenantExit::where('current_status_id', 2)
                                    ->whereMonth('actual_exit_date', now()->month)
                                    ->whereYear('actual_exit_date',  now()->year)
                                    ->count(),
                'with_refund' => TenantExitDepositRefund::distinct('exit_id')->count('exit_id'),
            ];

            // ── Filter dropdowns ─────────────────────────────────────────────
            // No full loads — both use typeahead. We only need the current filter
            // labels so the inputs can be pre-filled on page load.

            $activePropertyName = '';
            if ($propertyId = $request->input('property_id')) {
                $activePropertyName = Property::where('id', $propertyId)->value('property_name') ?? '';
            }

            $activeLandlordName = '';
            if ($landlordId = $request->input('landlord_id')) {
                $activeLandlordName = Client::where('id', $landlordId)->where('type_id', 1)->value('names') ?? '';
            }

            return view('property.tenants.exit.exited', compact(
                'exits', 'stats', 'activePropertyName', 'activeLandlordName'
            ));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: exitedTenantsIndex — ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load exited tenants.');
        }
    }
 
    // ─────────────────────────────────────────────────────────────────────────
    //  EXITED TENANTS — DETAIL (AJAX for offcanvas + inline statement)
    // ─────────────────────────────────────────────────────────────────────────
 
    /**
     * Return full detail for a single exit record.
     * Powers both the offcanvas detail panel and the inline accordion statement
     * on the exited tenants page.
     *
     * GET /tenant-exit/exited-detail/{exitId}
     */
    public function getExitedTenantDetail(int $exitId)
    {
        try {
            $exit = TenantExit::with([
                'inspection',
                'communications.sentBy:id,name',
                'depositRefunds',
            ])->findOrFail($exitId);
 
            // Tenant info — read identity fields directly since tenant may be
            // unlinked from unit/property after exit
            $tenant = Client::select('id', 'names', 'reference_number', 'mobile_number',
                                     'email_address', 'national_id')
                            ->find($exit->tenant_id);
 
            // Property & unit names from the exit record's stored IDs
            $property = Property::select('property_name')->find($exit->property_id);
            $unit     = Unit::selectRaw("COALESCE(unit_name, unit_code) as unit_name")->find($exit->unit_id);
 
            // Finalised by user
            $finalisedBy = \App\Models\User::select('name')->find($exit->finalised_by);
 
            // Account statement — same logic as the index profile loader
            $transactions = TenantAccountTransaction::where('tenant_id', $exit->tenant_id)
                ->where('current_status_id', 1)
                ->orderBy('date_time', 'asc')
                ->orderBy('id', 'asc')
                ->get();
 
            $statementEntries = [];
            $balance = 0;
            foreach ($transactions as $txn) {
                $debit  = abs($txn->debit_amount  ?? 0);
                $credit = abs($txn->credit_amount ?? 0);
                $balance += $credit - $debit;
 
                $statementEntries[] = [
                    'date_formatted'   => $txn->date_time ? date('d-m-Y', strtotime($txn->date_time)) : '-',
                    'reference'        => $txn->invoice_number ?? '-',
                    'narration'        => $txn->narration ?? ($debit > 0 ? 'Invoice' : 'Payment'),
                    'debit_formatted'  => $debit  > 0 ? number_format($debit,  2) : '-',
                    'credit_formatted' => $credit > 0 ? number_format($credit, 2) : '-',
                    'balance'          => $balance,
                    'balance_formatted'=> number_format($balance, 2),
                ];
            }
 
            // Shape inspection
            $insp = null;
            if ($exit->inspection) {
                $insp = $exit->inspection->toArray();
                $insp['inspection_date'] = $exit->inspection->inspection_date
                    ? $exit->inspection->inspection_date->format('d M Y') : '-';
            }
 
            // Shape deposit refunds
            $refunds = $exit->depositRefunds->map(fn ($r) => [
                'refund_amount'         => $r->refund_amount,
                'refund_notes'          => $r->refund_notes,
                'refunded_by_name'      => $r->refundedBy->name ?? '-',
                'refunded_at_formatted' => $r->refunded_at ? $r->refunded_at->format('d M Y') : '-',
            ])->toArray();
 
            // Shape communications
            $comms = $exit->communications->map(fn ($c) => [
                'comm_type'    => $c->comm_type,
                'subject'      => $c->subject,
                'message_body' => $c->message_body,
                'sent_by_name' => $c->sentBy->name ?? '-',
                'sent_at'      => $c->sent_at,
            ])->toArray();

            // Full stay history for this tenant (all past stays)
            $stayHistory = TenantHistory::where('tenant_id', $exit->tenant_id)
                ->with([
                    'property:id,property_name',
                    'unit:id,unit_name,unit_code',
                    'landlord:id,names',
                ])
                ->orderBy('exit_date', 'desc')
                ->get()
                ->map(fn ($h) => [
                    'property_name'    => $h->property->property_name ?? '-',
                    'unit_name'        => $h->unit->unit_name ?? $h->unit->unit_code ?? '-',
                    'landlord_name'    => $h->landlord->names ?? '-',
                    'lease_start_date' => $h->lease_start_date ? $h->lease_start_date->format('d M Y') : '-',
                    'exit_date'        => $h->exit_date->format('d M Y'),
                    'duration'         => $h->duration_label,
                    'exit_reason'      => $h->exit_reason ?? '-',
                ])->toArray();

            return response()->json([
                'success' => true,
                'tenant'  => [
                    'names'            => $tenant->names            ?? '-',
                    'reference_number' => $tenant->reference_number ?? '-',
                    'mobile_number'    => $tenant->mobile_number    ?? '-',
                    'email_address'    => $tenant->email_address    ?? '-',
                ],
                'exit' => [
                    'property_name'      => $property->property_name ?? '-',
                    'unit_name'          => $unit->unit_name         ?? '-',
                    'exit_reason'        => $exit->exit_reason,
                    'exit_notes'         => $exit->exit_notes,
                    'vacate_notice_date' => $exit->vacate_notice_date ? $exit->vacate_notice_date->format('d M Y') : '-',
                    'intended_exit_date' => $exit->intended_exit_date ? $exit->intended_exit_date->format('d M Y') : '-',
                    'actual_exit_date'   => $exit->actual_exit_date   ? $exit->actual_exit_date->format('d M Y')   : '-',
                    'final_notes'        => $exit->final_notes,
                    'finalised_by_name'  => $finalisedBy->name        ?? '-',
                    'finalised_at'       => $exit->finalised_at        ? $exit->finalised_at->format('d M Y H:i')  : '-',
                ],
                'inspection'      => $insp,
                'statement'       => $statementEntries,
                'closing_balance' => $balance,
                'refunds'         => $refunds,
                'communications'  => $comms,
                'stay_history'    => $stayHistory,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: getExitedTenantDetail — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load exit detail'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PDF DOWNLOADS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Download exit clearance letter PDF.
     * GET /tenant-exit/pdf/exit-letter/{exitId}
     */
    public function downloadExitLetter(int $exitId)
    {
        try {
            $exit     = TenantExit::with(['inspection', 'depositRefunds'])->findOrFail($exitId);
            $tenant   = Client::select('id', 'names', 'reference_number', 'mobile_number', 'email_address', 'national_id')->find($exit->tenant_id);
            $property = Property::select('id', 'property_name', 'reference_number', 'client_id')->find($exit->property_id);
            $unit     = Unit::selectRaw("id, COALESCE(unit_name, unit_code) as unit_name")->find($exit->unit_id);
            $landlord = $property?->client_id ? Client::select('names', 'mobile_number')->find($property->client_id) : null;
            $totalRefunded = $exit->depositRefunds->sum('refund_amount');

            $pdf = Pdf::loadView('property.tenants.exit.pdfs.exit-letter', compact(
                'exit', 'tenant', 'property', 'unit', 'landlord', 'totalRefunded'
            ));
            $pdf->setPaper('A4', 'portrait');
            $filename = 'ExitLetter_' . ($tenant->reference_number ?? $exitId) . '_' . now()->format('Ymd') . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: downloadExitLetter — ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate exit letter.');
        }
    }

    /**
     * Download tenant account statement PDF.
     * GET /tenant-exit/pdf/statement/{exitId}
     */
    public function downloadStatement(int $exitId)
    {
        try {
            $exit   = TenantExit::findOrFail($exitId);
            $tenant = Client::select('id', 'names', 'reference_number', 'mobile_number', 'email_address')->find($exit->tenant_id);

            $transactions = TenantAccountTransaction::where('tenant_id', $exit->tenant_id)
                ->where('current_status_id', 1)
                ->orderBy('date_time', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $entries = [];
            $balance = 0;
            foreach ($transactions as $txn) {
                $debit   = abs($txn->debit_amount  ?? 0);
                $credit  = abs($txn->credit_amount ?? 0);
                $balance += $credit - $debit;
                $entries[] = [
                    'date'      => $txn->date_time ? date('d-m-Y', strtotime($txn->date_time)) : '-',
                    'reference' => $txn->invoice_number ?? '-',
                    'narration' => $txn->narration ?? ($debit > 0 ? 'Invoice' : 'Payment'),
                    'debit'     => $debit  > 0 ? number_format($debit,  2) : '-',
                    'credit'    => $credit > 0 ? number_format($credit, 2) : '-',
                    'balance'   => number_format($balance, 2),
                    'is_neg'    => $balance < 0,
                ];
            }

            $property = Property::select('property_name')->find($exit->property_id);
            $unit     = Unit::selectRaw("COALESCE(unit_name, unit_code) as unit_name")->find($exit->unit_id);

            $pdf = Pdf::loadView('property.tenants.exit.pdfs.statement', compact(
                'exit', 'tenant', 'property', 'unit', 'entries', 'balance'
            ));
            $pdf->setPaper('A4', 'portrait');
            $filename = 'Statement_' . ($tenant->reference_number ?? $exitId) . '_' . now()->format('Ymd') . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: downloadStatement — ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate statement.');
        }
    }

    /**
     * Download unit inspection report PDF.
     * GET /tenant-exit/pdf/inspection/{exitId}
     */
    public function downloadInspectionReport(int $exitId)
    {
        try {
            $exit   = TenantExit::with('inspection')->findOrFail($exitId);
            $insp   = $exit->inspection;

            if (!$insp) {
                return redirect()->back()->with('error', 'No inspection record found for this exit.');
            }

            $tenant   = Client::select('id', 'names', 'reference_number')->find($exit->tenant_id);
            $property = Property::select('property_name')->find($exit->property_id);
            $unit     = Unit::selectRaw("COALESCE(unit_name, unit_code) as unit_name")->find($exit->unit_id);

            $pdf = Pdf::loadView('property.tenants.exit.pdfs.inspection-report', compact(
                'exit', 'insp', 'tenant', 'property', 'unit'
            ));
            $pdf->setPaper('A4', 'portrait');
            $filename = 'InspectionReport_' . ($tenant->reference_number ?? $exitId) . '_' . now()->format('Ymd') . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('TenantExit: downloadInspectionReport — ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate inspection report.');
        }
    }
}
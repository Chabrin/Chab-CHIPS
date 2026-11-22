<?php

namespace App\Http\Controllers;

use App\Exceptions\ClientAccountException;
use App\Models\Recovery;
use App\Models\TransactionType;
use App\Services\RecoveryService;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class RecoveryController extends Controller
{
    protected RecoveryService $recoveries;

    /**
     * Inject the recovery service.
     */
    public function __construct(RecoveryService $recoveries) {
        $this->recoveries = $recoveries;
    }

    /**
     * The transaction types a recovery can be recorded as: the ones the form always offered plus the five reasons (Paid
     * Owner, Sat Deposit, Overpaid, Absconded, Paid Vacant).
     */
    protected function transactionTypes() {
        return TransactionType::where(fn ($query) => $query->where('workflow_status_id', 1)->orWhereIn('id', $this->recoveries->reasonTypeIds()))->orderBy('type_name')->get();
    }

    /**
     * Why the signed-in user may not record or change a recovery against a property, or null when they may. A user limited
     * to a zone can only use the properties of that zone, as when generating returns.
     *
     * @param int|null $propertyId
     */
    protected function propertyRefusal(?int $propertyId): ?string {
        if (\App\Models\Client::zoneAccessState() === null) {
            return null;
        }

        if (!$propertyId || !\App\Models\Property::whereKey($propertyId)->exists()) {
            return 'That property is not in your zone, so you cannot record a recovery against it.';
        }

        return null;
    }

    /**
     * Why the signed-in user may not edit a recovery, or null when they may. A recovery of one of the five reasons can only
     * be changed by the person who recorded it: otherwise someone who holds the confirmer seat could rewrite another
     * person's recovery and confirm it alone, which would defeat the rule that the maker never confirms.
     *
     * @param Recovery $recovery
     */
    protected function editRefusal(Recovery $recovery): ?string {
        if ($this->recoveries->isInvoiceRecovery($recovery) && (int) $recovery->created_by !== (int) Auth::id()) {
            return 'Only the person who recorded this recovery can change it.';
        }

        return null;
    }

    /**
     * The checks every save of a recovery makes after the form is validated: the property must be one the user may use,
     * a recovery of one of the five reasons cannot be dated in the future (its date sets the dates of the tenant's receipt
     * and ledger credit), and the commissionable flag is set (see withCommission).
     *
     * @param array $validated the validated form data
     */
    protected function checkRecovery(array $validated): array {
        if ($message = $this->propertyRefusal((int) $validated['property_id'])) {
            throw new ClientAccountException($message);
        }

        if ($this->recoveries->reasonOfType((int) $validated['type_id']) && \Carbon\Carbon::parse($validated['recovery_date'])->gt(today())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['recovery_date' => 'The recovery date cannot be in the future.']);
        }

        return $this->withCommission($validated);
    }

    /**
     * Set the recovery's commissionable flag: for the five reasons it comes from the reason, for any other type the person
     * chooses it on the form.
     *
     * @param array $validated the validated form data, with type_id
     */
    protected function withCommission(array $validated): array {
        $flag = $this->recoveries->commissionFlag((int) $validated['type_id']);

        if ($flag === null && empty($validated['commisionable'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['commisionable' => 'Choose whether it is commissionable.']);
        }

        $validated['commisionable'] = $flag ?? $validated['commisionable'];

        return $validated;
    }

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
            $userId = (int) Auth::id();
            $userHoldsSeat = $this->recoveries->canConfirm($userId);

            $recoveries = Recovery::with(['client', 'property', 'zone', 'createdBy', 'transactionType'])
                ->orderBy('recovery_date', 'desc')
                ->get()
                ->map(function ($recovery) use ($userId, $userHoldsSeat) {
                    $isInvoiceRecovery = $this->recoveries->isInvoiceRecovery($recovery);
                    $statusId = (int) $recovery->current_status_id;

                    return [
                        'id' => $recovery->id,
                        'encrypted_id' => Crypt::encrypt($recovery->id),
                        'ref_number' => $recovery->ref_number ?? '-',
                        'type' => $recovery->transactionType->type_name ?? '-',
                        'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-',
                        'amount' => number_format($recovery->amount ?? 0, 2),
                        'client' => $recovery->client->names ?? '-',
                        'property' => $recovery->property->property_name ?? '-',
                        'zone' => $recovery->zone->zone_name ?? '-',
                        'commisionable' => $recovery->commisionable ?? 'No',
                        'created_by' => $recovery->createdBy->name ?? '-',
                        'narration' => $recovery->narration ?? '-',
                        'status_id' => $statusId,
                        'status' => $this->statusLabel($statusId, $isInvoiceRecovery),
                        'can_confirm' => $isInvoiceRecovery && $statusId === RecoveryService::PENDING && $userHoldsSeat && (int) $recovery->created_by !== $userId,
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
            
            $transaction_types = $this->transactionTypes();
            $reasonOptions = $this->recoveries->reasonOptions();

            // After a failed save the form comes back with the client the user had picked
            $oldClient = old('client_id') ? \App\Models\Client::find(old('client_id')) : null;

            return view('property.properties.recovery.create', compact('transaction_types', 'reasonOptions', 'oldClient'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading recovery create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load form');
        }
    }

    /**
     * Store a newly created recovery
     */
    public function store(Request $request)
    {
        $storedProof = null;
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'type_id' => 'required|exists:sys_set_transactions_types,id',
                'recovery_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'narration' => 'nullable|string',
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'sla_id' => 'nullable|exists:sys_pm_service_agreements,id',
                'unit_id' => 'nullable|exists:sys_pm_units,id',
                'tenant_id' => 'nullable|exists:sys_pm_clients,id',
                'lease_id' => 'nullable|exists:sys_pm_lease_agreements,id',
                'offset_invoice_id' => 'nullable|exists:sys_pm_debtors_invoices,id',
                'commisionable' => 'nullable|in:Yes,No',
                'proof_reference' => 'nullable|string|max:100',
                'proof_file' => 'nullable|file|mimes:pdf|mimetypes:application/pdf|max:10240',
            ]);
            $proofFile = $request->file('proof_file');
            unset($validated['proof_file']);

            $validated = $this->checkRecovery($validated);
            if ($this->recoveries->reasonOfType((int) $validated['type_id'])) {
                $this->recoveries->assertRecordable($validated);
            }
            $this->recoveries->assertProofGiven($validated, null, $proofFile);

            // The reference is made here, inside the open transaction, under the sequence lock
            $recovery = Recovery::create(array_merge($validated, [
                'ref_number' => $this->recoveries->nextReference(),
                'date_time' => now(),
                'created_by' => Auth::id(),
                'zone_id' => DB::table('sys_pm_properties')->where('id', $validated['property_id'])->value('zone_id'),
                'current_status_id' => RecoveryService::PENDING, // Pending — awaiting confirmation
            ]));

            if ($proofFile) {
                $storedProof = $this->recoveries->attachProof($recovery, $proofFile, (int) Auth::id());
            }

            DB::commit();

            return redirect()
                ->route('property.recoveries.show', Crypt::encrypt($recovery->id))
                ->with('success', 'Recovery ' . $recovery->ref_number . ' created successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (ClientAccountException $e) {
            DB::rollBack();
            $this->discardProof($storedProof);
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->discardProof($storedProof);
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating recovery: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create recovery: ' . $e->getMessage())
                ->withInput();
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
                'createdBy',
                'unit',
                'tenant',
                'lease',
                'sla'
            ])->findOrFail($id);

            $reason = $this->recoveries->reasonOf($recovery);
            $isInvoiceRecovery = $reason !== null;
            $refusal = $isInvoiceRecovery ? $this->recoveries->refusal($recovery, (int) Auth::id()) : 'This recovery is approved on the Property Confirmations page.';
            $confirmer = $isInvoiceRecovery ? $this->recoveries->confirmerLabel() : null;
            $statusLabel = $this->statusLabel((int) $recovery->current_status_id, $isInvoiceRecovery);
            $invoice = $recovery->offset_invoice_id ? DB::table('sys_pm_debtors_invoices')->where('id', $recovery->offset_invoice_id)->first() : null;
            $actionedBy = $recovery->actioned_by ? DB::table('josya_users')->where('id', $recovery->actioned_by)->value('name') : null;

            $canViewProof = $recovery->proof_path && $this->recoveries->canViewProof($recovery, (int) Auth::id(), (bool) Auth::user()->isAbleTo('read_recoveries'));
            $proofBy = $recovery->proof_uploaded_by ? DB::table('josya_users')->where('id', $recovery->proof_uploaded_by)->value('name') : null;

            return view('property.properties.recovery.details', compact('recovery', 'reason', 'isInvoiceRecovery', 'refusal', 'confirmer', 'statusLabel', 'invoice', 'actionedBy', 'canViewProof', 'proofBy'));
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

            if ((int) $recovery->current_status_id !== RecoveryService::PENDING) {
                return redirect()->route('property.recoveries.show', $encryptedId)->with('error', 'Only a recovery that is still Pending can be edited.');
            }
            if ($message = $this->editRefusal($recovery)) {
                return redirect()->route('property.recoveries.show', $encryptedId)->with('error', $message);
            }

            $transaction_types = $this->transactionTypes();
            $reasonOptions = $this->recoveries->reasonOptions();

            return view('property.properties.recovery.edit', compact('recovery', 'transaction_types', 'reasonOptions'));
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
            $recovery = Recovery::whereKey($id)->lockForUpdate()->firstOrFail(); // locked, so a confirm cannot slip in while it is edited

            if ((int) $recovery->current_status_id !== RecoveryService::PENDING) {
                throw new ClientAccountException('Only a recovery that is still Pending can be edited.');
            }
            if ($message = $this->editRefusal($recovery)) {
                throw new ClientAccountException($message);
            }

            $validated = $request->validate([
                'type_id' => 'required|exists:sys_set_transactions_types,id',
                'recovery_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'narration' => 'nullable|string',
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'required|exists:sys_pm_properties,id',
                'sla_id' => 'nullable|exists:sys_pm_service_agreements,id',
                'unit_id' => 'nullable|exists:sys_pm_units,id',
                'tenant_id' => 'nullable|exists:sys_pm_clients,id',
                'lease_id' => 'nullable|exists:sys_pm_lease_agreements,id',
                'offset_invoice_id' => 'nullable|exists:sys_pm_debtors_invoices,id',
                'commisionable' => 'nullable|in:Yes,No',
                'proof_reference' => 'nullable|string|max:100',
                'proof_file' => 'nullable|file|mimes:pdf|mimetypes:application/pdf|max:10240',
            ]);
            $proofFile = $request->file('proof_file');
            unset($validated['proof_file']);

            $validated = $this->checkRecovery($validated);
            if ($this->recoveries->reasonOfType((int) $validated['type_id'])) {
                $this->recoveries->assertRecordable($validated, $recovery->id);
            }
            $this->recoveries->assertProofGiven($validated, $recovery, $proofFile);

            $recovery->update(array_merge($validated, [
                'zone_id' => DB::table('sys_pm_properties')->where('id', $validated['property_id'])->value('zone_id'),
            ]));

            if ($proofFile) {
                $this->recoveries->attachProof($recovery, $proofFile, (int) Auth::id());
            }

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
        } catch (ClientAccountException $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage())->withInput();
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
    /**
     * The label of a recovery's status. A recovery of one of the five reasons that is Approved reads Confirmed.
     */
    protected function statusLabel(int $statusId, bool $isInvoiceRecovery): string {
        return match ($statusId) {
            RecoveryService::PENDING => $isInvoiceRecovery ? 'Pending confirmation' : 'Pending',
            RecoveryService::CONFIRMED => $isInvoiceRecovery ? 'Confirmed' : 'Approved',
            RecoveryService::REJECTED => 'Rejected',
            default => 'Status ' . $statusId,
        };
    }

    /**
     * Open tenant invoices of a unit (of one tenant when tenant_id is given), for the Offset Invoice dropdown (AJAX).
     */
    public function unitInvoices(Request $request, $unitId) {
        $invoices = DB::table('sys_pm_debtors_invoices')
            ->where('unit_id', $unitId)
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', (int) $request->input('tenant_id')))
            ->where('debtor_type_id', '2')
            ->whereIn('current_status_id', [1, RecoveryService::INVOICE_PARTIAL])
            ->whereRaw('COALESCE(current_balance, total_amount) > 0')
            ->orderByDesc('date_due')
            ->limit(100)
            ->get(['id', 'reference_number', 'narration', 'total_amount', 'current_balance', 'date_due'])
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->reference_number,
                'narration' => $invoice->narration,
                'amount' => (float) $invoice->total_amount,
                'outstanding_amount' => $this->recoveries->openBalance($invoice),
            ]);

        return response()->json(['invoices' => $invoices]);
    }

    /**
     * One invoice's number and what is left on it, to fill the amount (AJAX).
     */
    public function invoiceDetails($invoiceId) {
        $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $invoiceId)->first();

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        return response()->json([
            'invoice_number' => $invoice->reference_number,
            'amount' => (float) $invoice->total_amount,
            'outstanding_amount' => $this->recoveries->openBalance($invoice),
            'invoice_date' => $invoice->date_due,
        ]);
    }

    /**
     * Whether an amount fits what is left on an invoice (AJAX).
     */
    public function validateAmount(Request $request, $invoiceId) {
        $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $invoiceId)->first();

        if (!$invoice) {
            return response()->json(['valid' => false, 'error' => 'Invoice not found'], 404);
        }

        $outstanding = $this->recoveries->openBalance($invoice);

        return response()->json(['valid' => round((float) $request->input('amount'), 2) <= $outstanding + 0.005, 'outstanding' => $outstanding]);
    }

    /**
     * Confirm a recovery (AJAX): the tenant's invoice is paid down, the tenant ledger credited, the tenant given a receipt,
     * the client ledger debited and the GL posted, all or nothing.
     */
    public function confirm($encryptedId) {
        try {
            $recovery = Recovery::findOrFail(Crypt::decrypt($encryptedId));
            $result = $this->recoveries->confirm($recovery, (int) Auth::id());

            return response()->json(['success' => true, 'message' => 'Confirmed. The invoice now has ' . number_format($result['invoice_balance'], 2) . ' left to pay, the tenant has receipt ' . $result['receipt'] . ', the client ledger is debited ' . number_format($result['amount'], 2) . ' and the next return will deduct it.']);
        } catch (ClientAccountException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error confirming recovery: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to confirm the recovery'], 500);
        }
    }

    /**
     * Reject a recovery with a reason (AJAX). Nothing is posted.
     */
    public function reject(Request $request, $encryptedId) {
        try {
            $validated = $request->validate(['reason' => 'required|string|max:500']);
            $recovery = Recovery::findOrFail(Crypt::decrypt($encryptedId));
            $this->recoveries->reject($recovery, (int) Auth::id(), $validated['reason']);

            return response()->json(['success' => true, 'message' => 'Rejected.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Give the reason for rejecting.'], 422);
        } catch (ClientAccountException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error rejecting recovery: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to reject the recovery'], 500);
        }
    }
    /**
     * Who confirms recoveries now, the roles that can be chosen, and whether this user may change it (AJAX).
     */
    public function confirmerSetting() {
        $seats = $this->recoveries->confirmerSeats();

        return response()->json([
            'label' => $this->recoveries->confirmerLabel(),
            'role_id' => $seats['roles'][0] ?? null,
            'has_named_users' => !empty($seats['users']),
            'roles' => $this->recoveries->confirmerRoleOptions(),
            'can_change' => (bool) Auth::user()->isAbleTo('update_recoveries'),
        ]);
    }

    /**
     * Choose the role that confirms recoveries (AJAX). Needs update_recoveries.
     */
    public function saveConfirmer(Request $request) {
        if (!Auth::user()->isAbleTo('update_recoveries')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to change who confirms.'], 403);
        }

        try {
            $validated = $request->validate(['role_id' => 'required|integer']);
            $this->recoveries->setConfirmerRole((int) $validated['role_id'], (int) Auth::id());

            return response()->json(['success' => true, 'message' => 'Saved. Recoveries are now confirmed by ' . $this->recoveries->confirmerLabel() . '.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Choose a role.'], 422);
        } catch (ClientAccountException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error saving the recovery confirmer: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to save the setting'], 500);
        }
    }
    /**
     * Search landlords for the Client dropdown (select2 AJAX): by name, reference number or mobile, 25 at a time. A user
     * limited to a zone only finds the clients the Client model lets them see.
     */
    public function searchClients(Request $request) {
        $page = max((int) $request->input('page', 1), 1);
        $query = \App\Models\Client::clients()->select(['id', 'names', 'reference_number']);

        foreach (array_filter(explode(' ', preg_replace('/\s+/', ' ', trim((string) $request->input('q', ''))))) as $token) {
            $like = '%' . mb_strtolower($token) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(names) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(reference_number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(mobile_number) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('names')->orderBy('id')->skip(($page - 1) * 25)->take(26)->toBase()->get(); // raw columns: the Client name accessor would add a lookup per row

        return response()->json([
            'results' => $rows->take(25)->map(fn ($client) => ['id' => $client->id, 'text' => trim(($client->reference_number ?? '') . ' - ' . ($client->names ?? ''), ' -')])->values(),
            'pagination' => ['more' => $rows->count() > 25],
        ]);
    }
    /**
     * The tenants and leases of a unit, for the form to fill in for the user (AJAX). The unit's active lease comes first
     * and is the default, and its tenant is the current tenant. Taking the tenant from the unit's lease and not from the
     * client rows that name the unit is deliberate: a unit keeps a row for every tenant it ever had.
     */
    public function unitParties($unitId) {
        $leases = DB::table('sys_pm_lease_agreements as l')
            ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'l.tenant_id')
            ->leftJoin('cb_tenant_records as tr', 'tr.id', '=', 'c.id')
            ->where('l.unit_id', $unitId)
            ->orderByDesc('l.id')
            ->limit(100)
            ->get(['l.id', 'l.ref_number', 'l.lease_status', 'l.current_status_id', 'l.date_start', 'l.date_end', 'l.tenant_id', 'c.reference_number as tenant_ref', 'c.names as client_names', 'tr.names as record_names'])
            ->map(fn ($lease) => [
                'id' => (int) $lease->id,
                'reference_number' => $lease->ref_number,
                'status' => $lease->lease_status,
                'is_active' => $lease->lease_status === 'Active' && in_array((int) $lease->current_status_id, [1, 5], true),
                'date_start' => $lease->date_start,
                'date_end' => $lease->date_end,
                'tenant_id' => $lease->tenant_id ? (int) $lease->tenant_id : null,
                'tenant_ref' => $lease->tenant_ref,
                // the tenant record's name when there is one, as the rest of the app shows it
                'tenant_name' => $lease->record_names ?: $lease->client_names,
            ])
            // active leases first, then the newest start date (a lease with no start date last), then the newest lease
            ->sort(fn ($a, $b) => [$b['is_active'], $b['date_start'] !== null, (string) $b['date_start'], $b['id']] <=> [$a['is_active'], $a['date_start'] !== null, (string) $a['date_start'], $a['id']])
            ->take(30)
            ->values();

        $tenants = $leases->filter(fn ($lease) => $lease['tenant_id'])->unique('tenant_id')
            ->map(fn ($lease) => ['id' => $lease['tenant_id'], 'reference_number' => $lease['tenant_ref'], 'names' => $lease['tenant_name']])
            ->values();

        return response()->json(['leases' => $leases, 'tenants' => $tenants, 'default_lease_id' => $leases->first()['id'] ?? null]);
    }
    /**
     * Remove a proof file that was stored for a save that then failed, so no orphan is left on disk.
     */
    protected function discardProof(?string $path): void {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Open (or download with ?download=1) the proof document of a recovery. Whoever may read recoveries, the person who
     * recorded it and the confirmer may open it; the file sits on the private disk, never on a public URL.
     */
    public function proof(Request $request, $encryptedId) {
        try {
            $recovery = Recovery::findOrFail(Crypt::decrypt($encryptedId));
        } catch (\Exception $e) {
            abort(404);
        }

        abort_unless($this->recoveries->canViewProof($recovery, (int) Auth::id(), (bool) Auth::user()->isAbleTo('read_recoveries')), 403);
        abort_unless($recovery->proof_path && Storage::disk('local')->exists($recovery->proof_path), 404);

        return Storage::disk('local')->response(
            $recovery->proof_path,
            $recovery->proof_name ?: 'proof.pdf',
            ['Content-Type' => 'application/pdf'],
            $request->boolean('download') ? 'attachment' : 'inline'
        );
    }
}

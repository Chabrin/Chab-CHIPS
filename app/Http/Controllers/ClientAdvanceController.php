<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientAdvance;
use App\Models\Client;
use App\Models\Property;
use App\Models\InvoiceableItem;
use App\Models\LandlordAccount;
use App\Exceptions\ClientAccountException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Services\ClientAdvanceService;
use App\Services\ErrorDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ClientAdvanceController extends Controller
{
    protected ClientAdvanceService $advances;

    /**
     * Inject the advance posting service.
     */
    public function __construct(ClientAdvanceService $advances)
    {
        $this->advances = $advances;
    }

    /**
     * Display advances page
     */
    public function index()
    {
        return view('property.advances.index');
    }

    /**
     * Display create advance page
     */
    public function create()
    {
        return view('property.advances.create');
    }

    /**
     * Display advance details page
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'invoiceableitems', 'createdBy', 'schedule', 'previousAdvance', 'rescheduleHistory.fromAdvance', 'rescheduleHistory.toAdvance', 'rescheduleHistory.rescheduledBy', 'landlordAccountEntries'])
                ->findOrFail($id);

            $schedule = $this->buildScheduleArray($advance);
            $canRestructure = $advance->canBeRestructured();

            // Already-recovered installments, for display alongside the live restructure
            // preview so the modal shows the full picture (repaid + newly-restructured rows).
            $recoveredIds = $advance->recoveredScheduleIds();
            $recoveredScheduleRows = $advance->schedule->whereIn('id', $recoveredIds)->values()->map(function ($s) {
                return [
                    'payment_date'     => $s->payment_date ? $s->payment_date->format('Y-m-d') : '—',
                    'principal_amount' => (float) $s->principal_amount,
                    'interest'         => (float) $s->interest,
                    'total_amount'     => round((float) $s->principal_amount + (float) $s->interest, 2),
                ];
            });

            // The payment voucher that pays the advance out, unless it was rejected (then a new one can be made).
            $activeVoucher = $advance->voucher_id ? \App\Models\PaymentVoucherHeader::find($advance->voucher_id) : null;
            if ($activeVoucher && $activeVoucher->voucher_status === 'Rejected') {
                $activeVoucher = null;
            }
            $bankAccounts = \App\Models\CompanyBankAccount::with('bank')->where('current_status_id', 1)->orderBy('account_name')->get(['id', 'account_name', 'account_number', 'bank_id']);

            $canPayOut = $this->advances->hasPrincipalJournal($advance);

            return view('property.advances.show', compact('advance', 'schedule', 'canRestructure', 'recoveredScheduleRows', 'activeVoucher', 'bankAccounts', 'canPayOut'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading advance details: ' . $e->getMessage());
            return redirect()->route('client.advances.index')->with('error', 'Failed to load advance details');
        }
    }

    /**
     * Get all client advances (AJAX)
     */
    public function getAdvances(Request $request)
    {
        try {
            $canUpdate = Auth::user()->isAbleTo('update_client_advances');
            $userId = Auth::id();

            $query = ClientAdvance::with(['client', 'property', 'zone', 'invoiceableitems', 'createdBy']);

            if ($request->filled('status_id')) {
                $query->where('current_status_id', $request->input('status_id'));
            }

            $advances = $query->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($advance) use ($canUpdate, $userId) {
                    // Map status IDs to names
                    $statusMap = [
                        1 => 'Pending',
                        2 => 'Cleared',
                        3 => 'Cancelled',
                        4 => 'Suspended',
                        5 => 'Approved',
                        6 => 'Posted',
                        8 => 'Rescheduled'
                    ];

                    // Map advance type IDs to names
                    $typeMap = [
                        2 => 'Client Payment Advance',
                        3 => 'Overpayment Advance',
                    ];

                    $isOwnRequisition = (int) $advance->created_by === (int) $userId;

                    return [
                        'id' => $advance->id,
                        'encrypted_id' => Crypt::encrypt($advance->id),
                        'requisition_date' => $advance->requisition_date ? $advance->requisition_date->format('Y-m-d') : '-',
                        'reference_number' => $advance->reference_number ?? '-',
                        'client_name' => $advance->client ? $advance->client->names : '-',
                        'property_name' => $advance->property ? $advance->property->property_name : '-',
                        'zone_name' => $advance->zone ? $advance->zone->zone_name : '-',
                        'advance_type' => $typeMap[$advance->advance_type_id] ?? 'Unknown',
                        'charge_amount' => $advance->charge_amount ? number_format($advance->charge_amount, 2) : '0.00',
                        'amount' => $advance->amount ? number_format($advance->amount, 2) : '0.00',
                        'current_balance' => $advance->current_balance ? number_format($advance->current_balance, 2) : '0.00',
                        'status' => $statusMap[$advance->current_status_id] ?? 'Unknown',
                        'status_id' => $advance->current_status_id,
                        'narration' => $advance->narration ?? '-',
                        'created_by_name' => $advance->createdBy->name ?? '-',
                        'is_own_requisition' => $isOwnRequisition,
                        'can_update' => $canUpdate,
                        'can_delete' => Auth::user()->isAbleTo('delete_client_advances'),
                        'can_confirm' => $canUpdate && !$isOwnRequisition && (int) $advance->current_status_id === 1,
                        'can_post' => $canUpdate && (int) $advance->current_status_id === 5,
                    ];
                });
            return response()->json(['data' => $advances]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client advances: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load client advances', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get clients with reference starting with 'LAN' (AJAX)
     */
    public function getClients()
    {
        try {
            $clients = Client::where('reference_number', 'LIKE', 'LAN%')
                ->select(['id', 'client_name', 'reference_number'])
                ->orderBy('client_name', 'asc')
                ->get()
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'text' => $client->client_name . ' (' . $client->reference_number . ')',
                    ];
                });
            return response()->json($clients);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading clients: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load clients'], 500);
        }
    }

    /**
     * Get properties for a specific client (AJAX)
     */
    public function getClientProperties($clientId)
    {
        try {
            $properties = Property::where('client_id', $clientId)
                ->select(['id', 'property_name'])
                ->orderBy('property_name', 'asc')
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'text' => $property->property_name,
                    ];
                });
            return response()->json($properties);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load client properties'], 500);
        }
    }

    /**
     * Get previous advances for a specific client (AJAX)
     */
    public function getClientPreviousAdvances(Request $request)
    {
        try {
            $clientId = $request->input('client_id');

            $advances = ClientAdvance::where('client_id', $clientId)
                ->select(['id', 'reference_number'])
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($advance) {
                    return [
                        'id' => $advance->id,
                        'reference_number' => $advance->reference_number,
                    ];
                });
            return response()->json($advances);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading client previous advances: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load previous advances'], 500);
        }
    }

    /**
     * Get invoiceable items for miscellaneous charges (AJAX)
     */
    public function getMiscellaneousCharges()
    {
        try {
            $items = InvoiceableItem::select(['id', 'item_name'])
                ->orderBy('item_name', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'text' => $item->item_name,
                    ];
                });
            return response()->json($items);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading miscellaneous charges: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load miscellaneous charges'], 500);
        }
    }

    /**
     * Store a new client advance with comprehensive validation
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $advanceTypeId = $request->input('advance_type_id');

            // Base validation rules
            $rules = [
                'advance_type_id' => 'required|integer|in:2,3',
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'property_id' => 'nullable|integer|exists:sys_pm_properties,id',
                'requisition_date' => 'required|date|before_or_equal:today',
                'disbursement_date' => 'required|date|after_or_equal:requisition_date',
                'amount' => 'required|numeric|min:1',
                'narration' => 'nullable|string|max:500',
            ];

            // Type-specific rules
            if ($advanceTypeId == 2) { // Client Payment Advance — one-off payment, no repayment schedule
                $rules['charge_amount'] = 'nullable|numeric|min:0';
                $rules['interest_rate'] = 'nullable|numeric|min:0|max:100';
                $rules['principal_start_date'] = 'required|date';
            } elseif ($advanceTypeId == 3) { // Overpayment Advance
                $rules['repayment_months'] = 'required|integer|min:1|max:360';
                $rules['principal_start_date'] = 'required|date|after:disbursement_date';
                $rules['interest_rate'] = 'required|numeric|min:0|max:100';
                $rules['charge_amount'] = 'nullable|numeric|min:0';
            }

            $validated = $request->validate($rules);

            Log::info('Processing advance requisition', [
                'client_id' => $validated['client_id'],
                'amount' => $validated['amount'],
                'advance_type' => $advanceTypeId,
                'user_id' => Auth::id()
            ]);

            $clientId = $validated['client_id'];
            $amount = $validated['amount'];

            // Business logic validations
            $errors = [];

            // Check client eligibility
            $maxAmount = $this->getMaxAdvanceAmount($clientId);
            if ($amount > $maxAmount) {
                $errors['amount'] = [$maxAmount > 0
                    ? 'Amount exceeds available limit of KSh ' . number_format($maxAmount, 2)
                    : 'No advance limit available: this client has no returns in the last 6 months, or the advances already out use up the limit'];
            }

            // Check active advances count
            $activeCount = $this->getClientActiveAdvancesCount($clientId);
            if ($activeCount >= 2) {
                $errors['client_id'] = ['Client already has maximum (2) active advances'];
            }

            // Date validations
            $disbursement = \Carbon\Carbon::parse($validated['disbursement_date']);
            $requisition = \Carbon\Carbon::parse($validated['requisition_date']);
            if ($disbursement->gt($requisition->copy()->addDays(30))) {
                $errors['disbursement_date'] = ['Disbursement date must be within 30 days of requisition'];
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json(['success' => false, 'errors' => $errors], 422);
            }

            // Get zone_id from the selected property
            $property = !empty($validated['property_id']) ? \App\Models\Property::find($validated['property_id']) : null;

            // Auto-populate fields
            $validated['date_time'] = now();
            $validated['created_by'] = Auth::id();
            $validated['current_status_id'] = 1; // Pending
            $validated['zone_id'] = $property ? $property->zone_id : null;

            // Generate reference number: ADV-YYYY-###
            $year = date('Y');
            $lastAdvance = ClientAdvance::whereYear('date_time', $year)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();
            $nextNumber = $lastAdvance ? (intval(substr($lastAdvance->reference_number, -3)) + 1) : 1;
            $validated['reference_number'] = 'ADV-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // ===== INTEREST & CHARGE CALCULATIONS BY TYPE =====

            $interestAmount = 0;
            $totalAmount = $amount;
            $schedule = [];

            if ($advanceTypeId == 2) {
                // CLIENT PAYMENT ADVANCE — one-off payment, no repayment schedule.
                // Interest is a single day-of-month charge; nothing to amortize.
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;
                $rate = floatval($validated['interest_rate'] ?? 0);
                $chargeAmount = floatval($validated['charge_amount'] ?? 100);

                if ($day < 20) {
                    $interestAmount = $amount * $rate / 100;
                } elseif ($day <= 25) {
                    $interestAmount = ($amount * $rate / 100) * ($remainingDays / $daysInMonth);
                } else {
                    $interestAmount = 0;
                    $validated['interest_rate'] = 0;
                }
                $interestAmount = round($interestAmount, 2);

                $validated['repayment_months'] = null;
                $validated['charge_amount']   = $chargeAmount;
                $validated['interest_amount'] = $interestAmount;
                $validated['total_amount']    = round($amount + $interestAmount + $chargeAmount, 2);
                $validated['current_balance'] = $validated['total_amount'];

            } elseif ($advanceTypeId == 3) {
                // OVERPAYMENT ADVANCE: Reducing balance
                $rate = floatval($validated['interest_rate'] ?? 3);
                $repaymentMonths = intval($validated['repayment_months'] ?? 3);
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;

                $schedule = $this->calculateAmortizationSchedule(
                    $amount, $rate, $repaymentMonths,
                    $validated['disbursement_date'], $validated['principal_start_date']
                );

                if ($day < 25 && !empty($schedule)) {
                    $proratedFirst = round($schedule[0]['interest'] * ($remainingDays / $daysInMonth), 2);
                    $schedule[0]['interest']     = $proratedFirst;
                    $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                }

                $totalInterest  = array_sum(array_column($schedule, 'interest'));
                $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
                $chargeAmount   = floatval($validated['charge_amount'] ?? 100);
                $validated['charge_amount']   = $chargeAmount;
                $validated['interest_amount'] = round($totalInterest, 2);
                $validated['total_amount']    = round($totalPrincipal + $totalInterest + $chargeAmount, 2);
                $validated['current_balance'] = $validated['total_amount'];
            }

            // Create advance record
            $advance = ClientAdvance::create($validated);

            if (!$advance) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Failed to create advance record'], 500);
            }

            Log::info('Advance record created', [
                'advance_id' => $advance->id,
                'reference' => $advance->reference_number,
                'type' => $advanceTypeId
            ]);

            // Create amortization schedule entries (Type 3 / Overpayment Advance only)
            if (!empty($schedule)) {
                foreach ($schedule as $index => $entry) {
                    try {
                        \App\Models\AdvanceSchedule::create([
                            'parent_id' => $advance->id,
                            'payment_date' => $entry['payment_date'],
                            'principal_amount' => $entry['principal_amount'],
                            'interest' => $entry['interest'],
                            'loan_balance' => $entry['loan_balance'],
                            'current_status_id' => 1
                        ]);
                    } catch (\Exception $scheduleException) {
                        DB::rollBack();
                        Log::error('Failed to create schedule entry', [
                            'advance_id' => $advance->id,
                            'entry_index' => $index,
                            'error' => $scheduleException->getMessage()
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to create repayment schedule: ' . $scheduleException->getMessage()
                        ], 500);
                    }
                }
            }

            DB::commit();

            Log::info('Advance requisition completed successfully', [
                'advance_id' => $advance->id,
                'reference' => $advance->reference_number,
                'client_id' => $clientId,
                'type' => $advanceTypeId
            ]);

            // Notify landlord — approval email with repayment schedule attached
            try {
                $encryptedAdvanceId = Crypt::encrypt($advance->id);
                $notifySchedule = $this->buildScheduleArray($advance->load('schedule'));
                $advance->client->notify(new \App\Notifications\AdvanceApprovalNotification(
                    $advance,
                    $encryptedAdvanceId,
                    $notifySchedule
                ));
            } catch (\Exception $notifyEx) {
                Log::warning('Failed to send advance approval notification', [
                    'advance_id' => $advance->id,
                    'error'      => $notifyEx->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Client advance created successfully',
                'reference' => $validated['reference_number'],
                'advance_id' => encrypt($advance->id)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::warning('Validation failed for advance requisition', [
                'errors' => $e->errors(),
                'user_id' => Auth::id()
            ]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Database error creating client advance', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred. Please contact system administrator.'
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating client advance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick-edit a client advance. Only the start dates, percentage, and amount can
     * change — client, property, advance type, requisition date, narration, charges,
     * and repayment months are fixed at creation. Posted advances must go through
     * Reschedule instead, since their schedule may already have cleared payments.
     */
    public function update(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::findOrFail($id);

            if ($advance->current_status_id == 6) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Posted advances cannot be edited directly — use Reschedule instead.'], 422);
            }

            $validated = $request->validate([
                'disbursement_date'    => 'required|date',
                'principal_start_date' => 'required|date',
                'interest_rate'        => 'required|numeric|min:0|max:100',
                'amount'               => 'required|numeric|min:1',
                'repayment_months'     => $advance->advance_type_id == 3 ? 'required|integer|min:1|max:360' : 'nullable',
            ]);

            $disbursement  = \Carbon\Carbon::parse($validated['disbursement_date']);
            $amount        = floatval($validated['amount']);

            // The limit applies to an edit that raises the amount, the same as when the advance was requested.
            // The advance's own balance is left out of what is outstanding, since it is the one being changed.
            if ($amount > (float) $advance->amount) {
                $maxAmount = $this->getMaxAdvanceAmount($advance->client_id, $advance->id);

                if ($amount > $maxAmount) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => $maxAmount > 0
                        ? 'Amount exceeds available limit of KSh ' . number_format($maxAmount, 2)
                        : 'No advance limit available for this client'], 422);
                }
            }

            $rate          = floatval($validated['interest_rate']);
            $day           = $disbursement->day;
            $daysInMonth   = $disbursement->daysInMonth;
            $remainingDays = $daysInMonth - $day;
            $chargeAmount  = floatval($advance->charge_amount ?? 0);
            $schedule      = [];

            if ($advance->advance_type_id == 2) {
                // CLIENT PAYMENT ADVANCE — one-off payment, no repayment schedule
                if ($day < 20) {
                    $interestAmount = round($amount * $rate / 100, 2);
                } elseif ($day <= 25) {
                    $interestAmount = round(($amount * $rate / 100) * ($remainingDays / $daysInMonth), 2);
                } else {
                    $interestAmount = 0;
                    $rate = 0;
                }
                $totalAmount = round($amount + $interestAmount + $chargeAmount, 2);

            } else {
                // OVERPAYMENT ADVANCE — schedule is rebuilt around the (possibly changed)
                // amount, rate, repayment months, and dates.
                $repaymentMonths = (int) $validated['repayment_months'];

                $schedule = $this->calculateAmortizationSchedule(
                    $amount, $rate, $repaymentMonths,
                    $validated['disbursement_date'], $validated['principal_start_date']
                );

                if ($day < 25 && !empty($schedule)) {
                    $proratedFirst = round($schedule[0]['interest'] * ($remainingDays / $daysInMonth), 2);
                    $schedule[0]['interest']     = $proratedFirst;
                    $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                }

                $totalInterest  = array_sum(array_column($schedule, 'interest'));
                $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
                $interestAmount = round($totalInterest, 2);
                $totalAmount    = round($totalPrincipal + $totalInterest + $chargeAmount, 2);
            }

            // Preserve any amount already paid against the old total
            $amountPaid = (float) $advance->total_amount - (float) $advance->current_balance;

            $advance->update([
                'disbursement_date'    => $validated['disbursement_date'],
                'principal_start_date' => $validated['principal_start_date'],
                'interest_rate'        => $rate,
                'amount'               => $amount,
                'repayment_months'     => $advance->advance_type_id == 3 ? (int) $validated['repayment_months'] : $advance->repayment_months,
                'interest_amount'      => $interestAmount,
                'total_amount'         => $totalAmount,
                'current_balance'      => round($totalAmount - $amountPaid, 2),
            ]);

            // Rebuild the pending schedule rows (Overpayment Advance only) to match
            if ($advance->advance_type_id == 3) {
                $advance->schedule()->where('current_status_id', 1)->delete();
                foreach ($schedule as $entry) {
                    \App\Models\AdvanceSchedule::create([
                        'parent_id' => $advance->id,
                        'payment_date' => $entry['payment_date'],
                        'principal_amount' => $entry['principal_amount'],
                        'interest' => $entry['interest'],
                        'loan_balance' => $entry['loan_balance'],
                        'current_status_id' => 1,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Client advance updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating client advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update client advance'], 500);
        }
    }

    /**
     * Restructure an advance's dates (and, for Overpayment Advances, its repayment
     * months / interest rate). Unlike the quick-edit modal, this is allowed on Posted
     * advances. For Overpayment Advances (installments), already-recovered installments
     * — and their matching cb_landlord_account rows — are left completely untouched;
     * only the still-pending ones are rebuilt, and the principal being carried forward
     * can never be reduced below what was still outstanding. A one-off Client Payment
     * Advance has no partial concept, so it's blocked entirely once recovered. The
     * interest rate may only be held or lowered, never raised. The only GL entry is the
     * change in interest, if any (the principal does not move, so this is not a new
     * financial transaction). A cb_advance_reschedule_history row is written purely as an
     * audit trail (from/to/original all point at this same advance, since nothing new is
     * created).
     */
    public function restructure(Request $request, $encryptedId)
    {
        if (!Auth::user()->isAbleTo('update_client_advances')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to restructure advances'], 403);
        }

        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'schedule', 'landlordAccountEntries'])->findOrFail($id);
            $oldTotal = (float) $advance->total_amount;

            if (!$advance->canBeRestructured()) {
                DB::rollBack();
                $message = $advance->advance_type_id == 3
                    ? 'This advance can no longer be restructured — every installment has already been recovered.'
                    : 'This advance can no longer be restructured — an amount has already been recovered against it.';
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            $rules = [
                'disbursement_date'    => 'required|date',
                'principal_start_date' => 'required|date',
                'interest_rate'        => 'required|numeric|min:0|max:' . (float) ($advance->interest_rate ?? 0),
                'narration'            => 'nullable|string|max:500',
            ];
            if ($advance->advance_type_id == 3) {
                $rules['repayment_months'] = 'required|integer|min:1|max:360';
            }
            $validated = $request->validate($rules);

            $disbursement  = \Carbon\Carbon::parse($validated['disbursement_date']);
            $rate          = (float) $validated['interest_rate'];
            $day           = $disbursement->day;
            $daysInMonth   = $disbursement->daysInMonth;
            $remainingDays = $daysInMonth - $day;
            $chargeAmount  = (float) ($advance->charge_amount ?? 0);
            $schedule      = [];

            if ($advance->advance_type_id == 2) {
                // One-off payment — no partial concept, the whole amount is locked.
                $amount = (float) $advance->amount;
                if ($day < 20) {
                    $interestAmount = round($amount * $rate / 100, 2);
                } elseif ($day <= 25) {
                    $interestAmount = round(($amount * $rate / 100) * ($remainingDays / $daysInMonth), 2);
                } else {
                    $interestAmount = 0;
                    $rate = 0;
                }
                $totalAmount = round($amount + $interestAmount + $chargeAmount, 2);
                $amountPaid  = 0;

                $advance->update([
                    'disbursement_date'    => $validated['disbursement_date'],
                    'principal_start_date' => $validated['principal_start_date'],
                    'interest_rate'        => $rate,
                    'interest_amount'      => $interestAmount,
                    'total_amount'         => $totalAmount,
                    'current_balance'      => $totalAmount,
                ]);
            } else {
                // OVERPAYMENT ADVANCE — only the still-pending (unrecovered) installments
                // are rebuilt. Already-recovered installments are historical fact and are
                // left exactly as they are. The principal carried into the new schedule is
                // locked to what was still outstanding — it is never reduced.
                //
                // "Recovered" is determined via cb_landlord_account.is_recovered, joined by
                // schedule_id — NOT AdvanceSchedule.current_status_id, which the
                // merge-batch/MASTERRETURN returns flow never syncs back after a real
                // recovery, so it can sit at 1 (Pending) forever even once money has
                // actually been recovered against that installment.
                $recoveredIds       = $advance->recoveredScheduleIds();
                $recoveredRows      = $advance->schedule->whereIn('id', $recoveredIds);
                $pendingRowsBefore  = $advance->schedule->whereNotIn('id', $recoveredIds);
                $recoveredPrincipal = (float) $recoveredRows->sum('principal_amount');
                $recoveredInterest  = (float) $recoveredRows->sum('interest');
                $pendingPrincipal   = (float) $pendingRowsBefore->sum('principal_amount');
                // The charge row has no schedule row, but once it has been recovered it counts as paid.
                $recoveredCharge    = (float) $advance->landlordAccountEntries->where('is_recovered', 1)->whereNull('schedule_id')->sum('debit_amount');
                $repaymentMonths    = (int) $validated['repayment_months'];

                $schedule = $this->calculateAmortizationSchedule(
                    $pendingPrincipal, $rate, $repaymentMonths,
                    $validated['disbursement_date'], $validated['principal_start_date']
                );

                if ($day < 25 && !empty($schedule)) {
                    $proratedFirst = round($schedule[0]['interest'] * ($remainingDays / $daysInMonth), 2);
                    $schedule[0]['interest']     = $proratedFirst;
                    $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                }

                $newPendingInterest = round(array_sum(array_column($schedule, 'interest')), 2);
                $interestAmount     = round($recoveredInterest + $newPendingInterest, 2);
                $totalAmount        = round($recoveredPrincipal + $pendingPrincipal + $interestAmount + $chargeAmount, 2);
                $amountPaid         = round($recoveredPrincipal + $recoveredInterest + $recoveredCharge, 2);

                $advance->update([
                    'disbursement_date'    => $validated['disbursement_date'],
                    'principal_start_date' => $validated['principal_start_date'],
                    'interest_rate'        => $rate,
                    'repayment_months'     => $repaymentMonths,
                    'interest_amount'      => $interestAmount,
                    'total_amount'         => $totalAmount,
                    'current_balance'      => round($totalAmount - $amountPaid, 2),
                ]);

                // Only the pending installments are replaced — recovered ones stay untouched
                \App\Models\AdvanceSchedule::whereIn('id', $pendingRowsBefore->pluck('id'))->delete();
                foreach ($schedule as $entry) {
                    \App\Models\AdvanceSchedule::create([
                        'parent_id' => $advance->id,
                        'payment_date' => $entry['payment_date'],
                        'principal_amount' => $entry['principal_amount'],
                        'interest' => $entry['interest'],
                        'loan_balance' => $entry['loan_balance'],
                        'current_status_id' => 1,
                        'is_restructured' => true,
                    ]);
                }
            }

            // Resync only the UNRECOVERED ledger rows for this advance (if it was already
            // posted) — recovered rows are historical fact and are left completely alone.
            $unrecoveredEntries = $advance->landlordAccountEntries->where('is_recovered', 0);
            if ($unrecoveredEntries->isNotEmpty()) {
                $codes = DB::table('sys_pm_clients as c')
                    ->crossJoin('sys_pm_properties as p')
                    ->where('c.id', $advance->client_id)
                    ->where('p.id', $advance->property_id)
                    ->select('c.reference_number as landlord_code', 'p.reference_number as property_code')
                    ->first();
                $ref = $advance->reference_number ?? 'N/A';

                $glCode = $this->advances->receivableCode();

                // An instalment advance keeps its charge row (it has no schedule row): only the instalment rows are rebuilt.
                LandlordAccount::where('advance_id', $advance->id)->where('is_recovered', 0)
                    ->when($advance->advance_type_id == 3, fn ($query) => $query->whereNotNull('schedule_id'))
                    ->delete();

                if ($advance->advance_type_id == 3) {
                    // Old recovered rows keep their original (pre-rebuild) IDs, which are
                    // exactly $recoveredIds; freshly-created rows have new IDs and were
                    // never in that set — so this reliably isolates the new pending rows
                    // without depending on current_status_id.
                    $allRows     = $advance->schedule()->orderBy('payment_date')->get();
                    $totalCount  = $allRows->count();
                    $pendingRows = $allRows->whereNotIn('id', $recoveredIds)->values();
                    $startIndex  = $totalCount - $pendingRows->count();

                    foreach ($pendingRows as $i => $entry) {
                        $position    = $startIndex + $i + 1;
                        $payDate     = \Carbon\Carbon::parse($entry->payment_date);
                        $installment = round((float) $entry->principal_amount + (float) $entry->interest, 2);
                        $label = 'Advance ' . $ref . ' Instalment ' . $position . ' of ' . $totalCount . ' (restructured)';

                        LandlordAccount::create([
                            'year' => $payDate->year, 'month' => $payDate->month,
                            'landlord_code' => $codes->landlord_code ?? null, 'landlord_name' => $advance->client->names ?? null,
                            'property_code' => $codes->property_code ?? null,
                            'transaction_number' => $ref . '/' . $position, 'entry_type_code' => '0009',
                            'entry_description' => 'ADVANCE', 'entry_particulars' => $label, 'entry_date' => $payDate,
                            'debit_amount' => $installment, 'credit_amount' => 0, 'total_amount' => $installment,
                            'gl_account' => $glCode, 'vendor_id' => $codes->landlord_code ?? null,
                            'audit_user' => substr(Auth::user()->name ?? (string) Auth::id(), 0, 20),
                            'is_recovered' => 0, 'client_id' => $advance->client_id, 'property_id' => $advance->property_id,
                            'advance_id' => $advance->id, 'schedule_id' => $entry->id,
                        ]);
                    }
                } else {
                    LandlordAccount::create([
                        'year' => $disbursement->year, 'month' => $disbursement->month,
                        'landlord_code' => $codes->landlord_code ?? null, 'landlord_name' => $advance->client->names ?? null,
                        'property_code' => $codes->property_code ?? null,
                        'transaction_number' => $ref, 'entry_type_code' => '0009',
                        'entry_description' => 'ADVANCE', 'entry_particulars' => 'Advance ' . $ref . ' (restructured)',
                        'entry_date' => $disbursement, 'debit_amount' => $totalAmount, 'credit_amount' => 0, 'total_amount' => $totalAmount,
                        'gl_account' => $glCode, 'vendor_id' => $codes->landlord_code ?? null,
                        'audit_user' => substr(Auth::user()->name ?? (string) Auth::id(), 0, 20),
                        'is_recovered' => 0, 'client_id' => $advance->client_id, 'property_id' => $advance->property_id,
                        'advance_id' => $advance->id, 'schedule_id' => null,
                    ]);
                }
            }

            // The GL follows the ledger: book any change in the interest. An advance posted before the GL
            // leg existed has no journal, so adjustInterest leaves it alone.
            if (LandlordAccount::where('advance_id', $advance->id)->exists()) {
                $this->advances->adjustInterest($advance->fresh(), $oldTotal, Auth::id());
            }

            // Audit trail — no new advance is created, so from/to/original all point at this one
            \App\Models\AdvanceRescheduleHistory::create([
                'original_advance_id'               => $advance->id,
                'from_advance_id'                   => $advance->id,
                'to_advance_id'                      => $advance->id,
                'amount_paid_at_reschedule'         => $amountPaid,
                'outstanding_balance_at_reschedule' => $totalAmount - $amountPaid,
                'narration'                         => $validated['narration'] ?? 'Restructured',
                'rescheduled_by'                    => Auth::id(),
                'rescheduled_at'                    => now(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Advance restructured successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error restructuring client advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to restructure advance'], 500);
        }
    }

    /**
     * Delete a client advance
     */
    public function destroy($encryptedId)
    {
        try {
            if (!Auth::user()->isAbleTo('delete_client_advances')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to delete advances'], 403);
            }

            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::findOrFail($id);

            // Once an advance is approved or posted it is on the client account and in the GL, so only a Pending one can go.
            if ((int) $advance->current_status_id !== 1) {
                return response()->json(['success' => false, 'message' => 'Only a Pending advance can be deleted. Once approved or posted, an advance is on the client account.'], 422);
            }

            $advance->delete();

            return response()->json(['success' => true, 'message' => 'Client advance deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting client advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete client advance'], 500);
        }
    }

    /**
     * Check client eligibility (AJAX)
     */
    public function checkClientEligibility(Request $request)
    {
        try {
            $clientId = $request->input('client_id');
            if (!$clientId) {
                return response()->json(['success' => false, 'message' => 'Client ID is required'], 400);
            }

            $avgReturns = $this->getClientAverageReturns($clientId, 6);
            $outstandingBalance = $this->getClientOutstandingAdvances($clientId);
            $maxAmount = $this->getMaxAdvanceAmount($clientId);
            $activeAdvancesCount = $this->getClientActiveAdvancesCount($clientId);

            return response()->json([
                'success' => true,
                'data' => [
                    'average_monthly_returns' => $avgReturns,
                    'average_monthly_returns_formatted' => 'KSh ' . number_format($avgReturns, 2),
                    'outstanding_balance' => $outstandingBalance,
                    'outstanding_balance_formatted' => 'KSh ' . number_format($outstandingBalance, 2),
                    'maximum_advance_amount' => $maxAmount,
                    'maximum_advance_amount_formatted' => 'KSh ' . number_format($maxAmount, 2),
                    'active_advances_count' => $activeAdvancesCount,
                    'eligible' => $maxAmount > 0 && $activeAdvancesCount < 2,
                    'message' => $maxAmount > 0 && $activeAdvancesCount < 2
                        ? 'Client is eligible for advance'
                        : ($activeAdvancesCount >= 2 ? 'Client has maximum (2) active advances' : 'No available advance limit')
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error checking client eligibility: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to check eligibility'], 500);
        }
    }

    /**
     * Calculate and preview amortization schedule (AJAX)
     */
    public function calculateAmortization(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:10000',
                'interest_rate' => 'required|numeric|min:0.5|max:5',
                'repayment_months' => 'required|integer|min:3|max:36',
                'disbursement_date' => 'required|date',
                'principal_start_date' => 'required|date|after:disbursement_date',
                'client_id' => 'required|integer|exists:sys_pm_clients,id'
            ]);

            // Calculate amortization schedule
            $schedule = $this->calculateAmortizationSchedule(
                $validated['amount'],
                $validated['interest_rate'],
                $validated['repayment_months'],
                $validated['disbursement_date'],
                $validated['principal_start_date']
            );

            // Calculate totals
            $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
            $totalInterest = array_sum(array_column($schedule, 'interest'));
            $grandTotal = $totalPrincipal + $totalInterest;

            // Calculate monthly payment (average)
            $avgMonthlyPayment = $grandTotal / $validated['repayment_months'];

            // Check DSCR
            $avgReturns = $this->getClientAverageReturns($validated['client_id'], 6);
            $dscr = $avgReturns > 0 ? $avgReturns / $avgMonthlyPayment : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => $schedule,
                    'summary' => [
                        'total_principal' => $totalPrincipal,
                        'total_interest' => $totalInterest,
                        'grand_total' => $grandTotal,
                        'average_monthly_payment' => $avgMonthlyPayment,
                        'dscr' => $dscr,
                        'dscr_rating' => $dscr >= 2 ? 'Excellent' : ($dscr >= 1.5 ? 'Good' : ($dscr >= 1.2 ? 'Fair' : 'Poor')),
                        'dscr_approved' => $dscr >= 1.5
                    ]
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error calculating amortization: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to calculate amortization'], 500);
        }
    }

    // ================== HELPER METHODS ==================

    /**
     * Build a schedule array for display, PDF, and email.
     * For Type 3 advances uses stored AdvanceSchedule rows;
     * for Types 1 & 2 synthesises a single-row summary.
     */
    private function buildScheduleArray(ClientAdvance $advance): array
    {
        // Use stored schedule rows for Type 3, or Type 2 with amortization months
        $hasStoredSchedule = $advance->schedule->isNotEmpty() &&
            ($advance->advance_type_id == 3 ||
             ($advance->advance_type_id == 2 && !empty($advance->repayment_months)));

        if ($hasStoredSchedule) {
            // "Repaid" is determined via cb_landlord_account.is_recovered (joined by
            // schedule_id) — the true source of truth — not AdvanceSchedule.current_status_id,
            // which is not reliably synced for merge-batch/MASTERRETURN landlords.
            $recoveredIds = $advance->recoveredScheduleIds();

            return $advance->schedule->map(function ($s) use ($recoveredIds) {
                $isRepaid = $recoveredIds->contains($s->id);
                return [
                    'payment_date'     => $s->payment_date ? $s->payment_date->format('Y-m-d') : '—',
                    'principal_amount' => (float) $s->principal_amount,
                    'interest'         => (float) $s->interest,
                    'total_amount'     => round((float) $s->principal_amount + (float) $s->interest, 2),
                    'repaid_amount'    => $isRepaid ? round((float) $s->principal_amount + (float) $s->interest, 2) : 0.0,
                    'loan_balance'     => (float) $s->loan_balance,
                    'status_id'        => $isRepaid ? 2 : $s->current_status_id,
                    'restructured'     => (bool) $s->is_restructured,
                ];
            })->toArray();
        }

        // Synthetic single-row for Types 1 & 2 (no amortization)
        // BUT: if Type 2/3 has repayment_months set and no stored rows, compute on the fly
        if (!empty($advance->repayment_months) && !empty($advance->principal_start_date) && in_array($advance->advance_type_id, [2, 3])) {
            $computed = $this->calculateAmortizationSchedule(
                (float) $advance->amount,
                (float) ($advance->interest_rate ?? 0),
                (int) $advance->repayment_months,
                $advance->disbursement_date->format('Y-m-d'),
                $advance->principal_start_date instanceof \Carbon\Carbon
                    ? $advance->principal_start_date->format('Y-m-d')
                    : $advance->principal_start_date
            );
            return array_map(function ($row) use ($advance) {
                return array_merge($row, ['repaid_amount' => 0.0, 'status_id' => $advance->current_status_id, 'restructured' => false]);
            }, $computed);
        }

        // One-off Client Payment Advance — all-or-nothing, checked via the ledger directly
        $isRepaid = $advance->hasRecoveredLandlordEntries();

        return [[
            'payment_date'     => $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '—',
            'principal_amount' => (float) ($advance->amount ?? 0),
            'interest'         => (float) ($advance->interest_amount ?? 0),
            'total_amount'     => (float) ($advance->total_amount ?? 0),
            'repaid_amount'    => $isRepaid ? (float) ($advance->total_amount ?? 0) : 0.0,
            'loan_balance'     => 0.00,
            'status_id'        => $isRepaid ? 2 : $advance->current_status_id,
            'restructured'     => false,
        ]];
    }

    /**
     * Download repayment schedule as PDF
     */
    public function downloadPdf($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'schedule'])->findOrFail($id);
            $schedule = $this->buildScheduleArray($advance);

            $pdf = Pdf::loadView('property.advances.schedule-pdf', compact('advance', 'schedule'));
            return $pdf->download('AdvanceSchedule_' . ($advance->reference_number ?? $id) . '.pdf');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating advance PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate PDF');
        }
    }

    /**
     * Download repayment schedule as Excel
     */
    public function downloadExcel($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'schedule'])->findOrFail($id);
            $schedule = $this->buildScheduleArray($advance);

            return Excel::download(
                new \App\Exports\AdvanceScheduleExport($advance, $schedule),
                'AdvanceSchedule_' . ($advance->reference_number ?? $id) . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating advance Excel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate Excel file');
        }
    }

    /**
     * Email repayment schedule to landlord (AJAX)
     */
    public function emailSchedule($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'schedule'])->findOrFail($id);

            if (!$advance->client || !$advance->client->email_address) {
                return response()->json(['success' => false, 'message' => 'Landlord email address not found'], 422);
            }

            $schedule = $this->buildScheduleArray($advance);
            $advance->client->notify(new \App\Notifications\AdvanceScheduleNotification($advance, $schedule));

            Log::info('Advance schedule emailed to landlord', [
                'advance_id'   => $advance->id,
                'client_email' => $advance->client->email_address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Schedule emailed to ' . $advance->client->email_address,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error emailing advance schedule: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show the approval confirmation page — public route, landlord clicks link from email.
     * GET performs no writes (so email link-scanners/prefetchers can't trigger a payout);
     * the form on this page POSTs to confirmApproveAdvance() to actually approve.
     */
    public function approveAdvance(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property'])->findOrFail($id);

            if ($advance->current_status_id !== 1) {
                return view('property.advances.approved', compact('advance'))
                    ->with('alreadyProcessed', true)
                    ->with('statusLabel', $this->advanceStatusLabel($advance->current_status_id));
            }

            return view('property.advances.approve-confirm', [
                'advance'     => $advance,
                'confirmUrl'  => $request->fullUrl(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading advance approval confirmation: ' . $e->getMessage());
            abort(404);
        }
    }

    /**
     * Confirm advance approval — public route, POSTed from the confirmation page above.
     * The status check-and-update is a single atomic conditional query, so a double
     * submission (double-click, replay, two tabs) can only ever succeed once.
     */
    public function confirmApproveAdvance($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);

            $advance = DB::transaction(function () use ($id) {
                $updated = ClientAdvance::where('id', $id)
                    ->where('current_status_id', 1)
                    ->update(['current_status_id' => 6]);

                if ($updated === 0) {
                    return null;
                }

                $advance = ClientAdvance::with(['client', 'property', 'schedule'])->findOrFail($id);
                $this->advances->post($advance, $advance->created_by ?? 0);

                return $advance;
            });

            if (!$advance) {
                $advance = ClientAdvance::with(['client', 'property'])->findOrFail($id);
                return view('property.advances.approved', compact('advance'))
                    ->with('alreadyProcessed', true)
                    ->with('statusLabel', $this->advanceStatusLabel($advance->current_status_id));
            }

            Log::info('Advance approved by landlord', [
                'advance_id' => $advance->id,
                'client_id'  => $advance->client_id,
            ]);

            return view('property.advances.approved', compact('advance'))
                ->with('alreadyProcessed', false);
        } catch (ClientAccountException $e) {
            // Nothing was posted: the status change rolled back with it. The reason is for the office, not the landlord.
            Log::error('Advance approval could not be posted: ' . $e->getMessage(), ['advance_id' => $id ?? null]);
            abort(500, 'We could not complete this approval right now. Please contact the office.');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error approving advance: ' . $e->getMessage());
            abort(404);
        }
    }

    private function advanceStatusLabel(int $statusId): string
    {
        $statusLabels = [
            1 => 'Pending', 2 => 'Cleared', 3 => 'Cancelled', 4 => 'Suspended',
            5 => 'Already Approved', 6 => 'Already Posted', 8 => 'Rescheduled',
        ];

        return $statusLabels[$statusId] ?? 'Unknown';
    }

    /**
     * Confirm an advance on behalf of the landlord (internal action: Pending → Approved).
     * Used when the landlord hasn't responded to the emailed approval link - a staff member
     * with update_client_advances confirms it instead. Maker-checker: whoever created the
     * requisition cannot also confirm it. Money doesn't move here - a separate Post action
     * (postAdvance() below) is required afterward to actually push it to the landlord ledger.
     */
    public function confirmAdvance($encryptedId)
    {
        try {
            if (!Auth::user()->isAbleTo('update_client_advances')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to confirm advances'], 403);
            }

            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::findOrFail($id);

            if ((int) $advance->created_by === (int) Auth::id()) {
                return response()->json(['success' => false, 'message' => 'You cannot confirm an advance you created yourself - a different user must confirm it.'], 422);
            }

            $updated = ClientAdvance::where('id', $id)
                ->where('current_status_id', 1)
                ->update([
                    'current_status_id' => 5,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

            if ($updated === 0) {
                $advance->refresh();
                return response()->json(['success' => false, 'message' => 'This advance is no longer Pending (' . $this->advanceStatusLabel($advance->current_status_id) . ') - it may have already been confirmed or approved by the landlord.'], 422);
            }

            Log::info('Advance confirmed internally in lieu of landlord', [
                'advance_id' => $id,
                'confirmed_by' => Auth::id(),
            ]);

            return response()->json(['success' => true, 'message' => 'Advance confirmed. It can now be posted.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error confirming advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to confirm advance'], 500);
        }
    }

    /**
     * Post an advance (admin action: Approved → Posted).
     * The status check-and-update is a single atomic conditional query — closes the
     * same double-submission race as the public approval link above.
     */
    public function postAdvance($encryptedId)
    {
        try {
            if (!Auth::user()->isAbleTo('update_client_advances')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to post advances'], 403);
            }

            $id = Crypt::decrypt($encryptedId);

            $advance = DB::transaction(function () use ($id) {
                $updated = ClientAdvance::where('id', $id)
                    ->where('current_status_id', 5)
                    ->update(['current_status_id' => 6]);

                if ($updated === 0) {
                    return null;
                }

                $advance = ClientAdvance::with('schedule')->findOrFail($id);
                $this->advances->post($advance, Auth::id());

                return $advance;
            });

            if (!$advance) {
                return response()->json(['success' => false, 'message' => 'Advance must be Approved before posting'], 422);
            }

            Log::info('Advance posted by admin', [
                'advance_id' => $advance->id,
                'user_id'    => Auth::id(),
            ]);

            return response()->json(['success' => true, 'message' => 'Advance posted successfully']);
        } catch (ClientAccountException $e) {
            // The status change rolled back with the failed posting, so the advance is still Approved.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error posting advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to post advance'], 500);
        }
    }

    /**
     * Create the payment voucher that pays out a posted advance's principal. The voucher then goes through
     * cheque printing and approval; approving it posts the bank side of the payment to the GL.
     */
    public function createVoucher(Request $request, $encryptedId)
    {
        try {
            if (!Auth::user()->isAbleTo('update_client_advances')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to create advance payment vouchers'], 403);
            }

            $validated = $request->validate(['source_account_id' => 'required|integer']);
            $id = Crypt::decrypt($encryptedId);

            $voucher = DB::transaction(fn () => $this->advances->createPayoutVoucher(ClientAdvance::findOrFail($id), (int) $validated['source_account_id'], Auth::id()));

            Log::info('Advance payment voucher created', ['advance_id' => $id, 'voucher' => $voucher->voucher_number, 'user_id' => Auth::id()]);

            return response()->json([
                'success' => true,
                'message' => 'Payment voucher ' . $voucher->voucher_number . ' created. Print the cheque and submit it for approval.',
                'voucher_number' => $voucher->voucher_number,
                'url' => route('cheque.printing.details', encrypt($voucher->id)),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors(), 'message' => 'Choose the bank account to pay from'], 422);
        } catch (\Illuminate\Contracts\Encryption\DecryptException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Advance not found'], 404);
        } catch (ClientAccountException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating advance payment voucher: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create the payment voucher'], 500);
        }
    }

    /**
     * Get client average returns over specified months
     */
    private function getClientAverageReturns($clientId, $months = 6)
    {
        try {
            // The RETURNS credits (0005) on the client ledger for the last N months. The old
            // sys_reports_landlord_accounts table this used to read is empty, so every client
            // looked as if they had no returns and no limit.
            // The last N complete months, so the sum divides by the months it covers (the current month is left out).
            $startDate = now()->startOfMonth()->subMonths($months);

            $totalReturns = DB::table('cb_landlord_account')
                ->where('client_id', $clientId)
                ->where('entry_type_code', '0005')
                ->where('entry_date', '>=', $startDate)
                ->where('entry_date', '<', now()->startOfMonth())
                ->sum('credit_amount');

            return $totalReturns > 0 ? $totalReturns / $months : 0;
        } catch (\Exception $e) {
            Log::error('Error getting client average returns: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get client total outstanding advances
     */
    private function getClientOutstandingAdvances($clientId, $excludeAdvanceId = null)
    {
        try {
            return ClientAdvance::where('client_id', $clientId)
                ->whereIn('current_status_id', [1, 5, 6]) // Pending, Approved, Posted
                ->when($excludeAdvanceId, fn ($query) => $query->where('id', '!=', $excludeAdvanceId))
                ->sum('current_balance');
        } catch (\Exception $e) {
            Log::error('Error getting client outstanding advances: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of active advances for client
     */
    private function getClientActiveAdvancesCount($clientId)
    {
        try {
            return ClientAdvance::where('client_id', $clientId)
                ->where('current_balance', '>', 0)
                ->whereIn('current_status_id', [1, 5, 6]) // Pending, Approved, Posted
                ->count();
        } catch (\Exception $e) {
            Log::error('Error getting client active advances count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate maximum advance amount for client
     */
    private function getMaxAdvanceAmount($clientId, $excludeAdvanceId = null)
    {
        try {
            // Get last 6 months average returns
            $avgMonthlyReturns = $this->getClientAverageReturns($clientId, 6);

            // Get existing outstanding balance
            $outstandingBalance = $this->getClientOutstandingAdvances($clientId, $excludeAdvanceId);

            // 80% of annual returns, less what is outstanding
            return $this->advances->availableLimit((float) $avgMonthlyReturns, (float) $outstandingBalance);
        } catch (\Exception $e) {
            Log::error('Error calculating max advance amount: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check Debt Service Coverage Ratio
     */
    private function checkDebtServiceCoverage($clientId, $requestedAmount, $repaymentMonths, $interestRate)
    {
        try {
            $avgMonthlyReturns = $this->getClientAverageReturns($clientId, 6);

            if ($avgMonthlyReturns == 0) {
                return [
                    'approved' => false,
                    'dscr' => 0,
                    'message' => 'No returns history found for this client'
                ];
            }

            // Calculate estimated monthly payment (simple calculation)
            $totalInterest = ($requestedAmount * $interestRate * $repaymentMonths) / 100;
            $totalAmount = $requestedAmount + $totalInterest;
            $monthlyPayment = $totalAmount / $repaymentMonths;

            // DSCR = Monthly Returns / Monthly Payment
            $dscr = $avgMonthlyReturns / $monthlyPayment;

            // Minimum acceptable DSCR: 1.5 (returns should be 1.5x payment)
            if ($dscr < 1.5) {
                return [
                    'approved' => false,
                    'dscr' => $dscr,
                    'message' => 'Insufficient returns to support repayment. DSCR: ' . round($dscr, 2) . ' (minimum 1.5 required)'
                ];
            }

            return ['approved' => true, 'dscr' => $dscr];
        } catch (\Exception $e) {
            Log::error('Error checking debt service coverage: ' . $e->getMessage());
            return ['approved' => false, 'dscr' => 0, 'message' => 'Error calculating DSCR'];
        }
    }

    /**
     * Calculate amortization schedule with grace period handling
     */
    private function calculateAmortizationSchedule($amount, $interestRate, $repaymentMonths, $disbursementDate, $principalStartDate)
    {
        try {
            $schedule = [];
            $balance = $amount;
            $principalPerMonth = $amount / $repaymentMonths;

            $disbursement = \Carbon\Carbon::parse($disbursementDate);
            $principalStart = \Carbon\Carbon::parse($principalStartDate);

            // Check if there's a grace period (interest-only months)
            $gracePeriodMonths = 0;
            if ($disbursement->format('Y-m') != $principalStart->format('Y-m')) {
                $gracePeriodMonths = $disbursement->diffInMonths($principalStart);
            }

            // Grace period - interest only.
            // The final grace month (immediately before principal start) is intentionally
            // excluded here: the principal loop's first installment already charges interest
            // for that same month, so looping the full $gracePeriodMonths would double-charge it.
            $currentDate = $disbursement->copy();
            for ($i = 0; $i < $gracePeriodMonths - 1; $i++) {
                $currentDate->addMonth()->startOfMonth();
                $interest = round(($balance * $interestRate) / 100, 2);

                $schedule[] = [
                    'payment_date' => $currentDate->format('Y-m-d'),
                    'principal_amount' => 0,
                    'interest' => $interest,
                    'total_amount' => $interest,
                    'loan_balance' => $balance
                ];
            }

            // Principal repayment schedule — the first installment always falls exactly
            // on the chosen Payment Start Date; only later installments roll to the next month.
            $currentDate = $principalStart->copy();
            for ($month = 1; $month <= $repaymentMonths; $month++) {
                if ($month > 1) {
                    $currentDate->addMonth()->startOfMonth();
                }

                $interest = round(($balance * $interestRate) / 100, 2);
                $principal = round($principalPerMonth, 2);

                // Adjust last payment to clear any rounding differences
                if ($month == $repaymentMonths) {
                    $principal = $balance;
                }

                $balance -= $principal;
                $balance = round($balance, 2);

                $schedule[] = [
                    'payment_date' => $currentDate->format('Y-m-d'),
                    'principal_amount' => $principal,
                    'interest' => $interest,
                    'total_amount' => $principal + $interest,
                    'loan_balance' => max(0, $balance)
                ];
            }

            return $schedule;
        } catch (\Exception $e) {
            Log::error('Error calculating amortization schedule: ' . $e->getMessage());
            return [];
        }
    }
}
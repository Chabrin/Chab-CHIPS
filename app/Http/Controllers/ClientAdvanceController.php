<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientAdvance;
use App\Models\Client;
use App\Models\Property;
use App\Models\InvoiceableItem;
use App\Models\ChartAccount;
use App\Models\LandlordAccount;
use App\Traits\GlPostingTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Services\ErrorDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ClientAdvanceController extends Controller
{
    use GlPostingTrait;

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
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'invoiceableitems', 'createdBy', 'schedule', 'previousAdvance', 'rescheduleHistory.fromAdvance', 'rescheduleHistory.toAdvance', 'rescheduleHistory.rescheduledBy'])
                ->findOrFail($id);

            $schedule = $this->buildScheduleArray($advance);

            return view('property.advances.show', compact('advance', 'schedule'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading advance details: ' . $e->getMessage());
            return redirect()->route('client.advances.index')->with('error', 'Failed to load advance details');
        }
    }

    /**
     * Display edit advance page
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property'])->findOrFail($id);

            return view('property.advances.edit', compact('advance'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading advance for editing: ' . $e->getMessage());
            return redirect()->route('client.advances.index')->with('error', 'Failed to load advance for editing');
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
                'narration' => 'required|string|min:5|max:500',
            ];

            // Type-specific rules
            if ($advanceTypeId == 2) { // Client Payment Advance
                $rules['charge_amount'] = 'nullable|numeric|min:0';
                $rules['interest_rate'] = 'nullable|numeric|min:0|max:100';
                $rules['repayment_months'] = 'nullable|integer|min:1|max:360';
                $rules['principal_start_date'] = 'nullable|date';
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
            if ($amount > $maxAmount && $maxAmount > 0) {
                $errors['amount'] = ['Amount exceeds available limit of KSh ' . number_format($maxAmount, 2)];
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
                // CLIENT PAYMENT ADVANCE
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;
                $rate = floatval($validated['interest_rate'] ?? 0);
                $chargeAmount = floatval($validated['charge_amount'] ?? 100);
                $repaymentMonths = !empty($validated['repayment_months']) ? intval($validated['repayment_months']) : null;
                $principalStartDate = $validated['principal_start_date'] ?? null;

                if ($repaymentMonths && $principalStartDate) {
                    // Reducing balance amortization when months are provided
                    $schedule = $this->calculateAmortizationSchedule(
                        $amount, $rate, $repaymentMonths,
                        $validated['disbursement_date'], $principalStartDate
                    );
                    if ($day < 25 && !empty($schedule)) {
                        $proratedFirst = round($schedule[0]['interest'] * ($remainingDays / $daysInMonth), 2);
                        $schedule[0]['interest'] = $proratedFirst;
                        $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                    }
                    $totalInterest  = array_sum(array_column($schedule, 'interest'));
                    $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
                    $interestAmount = round($totalInterest, 2);
                    $validated['repayment_months']    = $repaymentMonths;
                    $validated['principal_start_date'] = $principalStartDate;
                } else {
                    // Day-of-month simple interest
                    if ($day < 20) {
                        $interestAmount = $amount * $rate / 100;
                    } elseif ($day <= 25) {
                        $interestAmount = ($amount * $rate / 100) * ($remainingDays / $daysInMonth);
                    } else {
                        $interestAmount = 0;
                        $validated['interest_rate'] = 0;
                    }
                    $interestAmount = round($interestAmount, 2);
                    $validated['repayment_months']    = null;
                    $validated['principal_start_date'] = null;
                }

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

            // Create amortization schedule entries (Type 3 always; Type 2 when repayment months provided)
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
     * Update a client advance
     */
    public function update(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::findOrFail($id);

            $advanceTypeId = $request->input('advance_type_id');

            // Base validation rules
            $rules = [
                'advance_type_id' => 'required|integer|in:2,3',
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'property_id' => 'nullable|integer|exists:sys_pm_properties,id',
                'requisition_date' => 'required|date',
                'disbursement_date' => 'required|date',
                'amount' => 'required|numeric|min:1',
                'narration' => 'required|string|min:5|max:500',
            ];

            // Type-specific rules
            if ($advanceTypeId == 2) { // Client Payment Advance
                $rules['charge_amount'] = 'nullable|numeric|min:0';
                $rules['interest_rate'] = 'nullable|numeric|min:0|max:100';
                $rules['repayment_months'] = 'nullable|integer|min:1|max:360';
                $rules['principal_start_date'] = 'nullable|date';
            } elseif ($advanceTypeId == 3) { // Overpayment Advance
                $rules['repayment_months'] = 'required|integer|min:1|max:360';
                $rules['principal_start_date'] = 'required|date|after:disbursement_date';
                $rules['interest_rate'] = 'required|numeric|min:0|max:100';
                $rules['charge_amount'] = 'nullable|numeric|min:0';
            }

            $validated = $request->validate($rules);

            // Get zone_id from the selected property
            $property = !empty($validated['property_id']) ? \App\Models\Property::find($validated['property_id']) : null;
            $validated['zone_id'] = $property ? $property->zone_id : null;

            // ===== INTEREST & CHARGE CALCULATIONS BY TYPE =====
            $disbursement = \Carbon\Carbon::parse($validated['disbursement_date']);
            $amount = floatval($validated['amount']);
            $schedule = [];

            if ($advanceTypeId == 2) {
                // CLIENT PAYMENT ADVANCE
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;
                $rate = floatval($validated['interest_rate'] ?? 0);
                $chargeAmount = floatval($validated['charge_amount'] ?? 100);
                $repaymentMonths = !empty($validated['repayment_months']) ? intval($validated['repayment_months']) : null;
                $principalStartDate = $validated['principal_start_date'] ?? null;

                if ($repaymentMonths && $principalStartDate) {
                    $schedule = $this->calculateAmortizationSchedule(
                        $amount, $rate, $repaymentMonths,
                        $validated['disbursement_date'], $principalStartDate
                    );
                    if ($day < 25 && !empty($schedule)) {
                        $proratedFirst = round($schedule[0]['interest'] * ($remainingDays / $daysInMonth), 2);
                        $schedule[0]['interest']     = $proratedFirst;
                        $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                    }
                    $totalInterest  = array_sum(array_column($schedule, 'interest'));
                    $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
                    $interestAmount = round($totalInterest, 2);
                    $validated['repayment_months']    = $repaymentMonths;
                    $validated['principal_start_date'] = $principalStartDate;
                } else {
                    if ($day < 20) {
                        $interestAmount = round($amount * $rate / 100, 2);
                    } elseif ($day <= 25) {
                        $interestAmount = round(($amount * $rate / 100) * ($remainingDays / $daysInMonth), 2);
                    } else {
                        $interestAmount = 0;
                        $validated['interest_rate'] = 0;
                    }
                    $validated['repayment_months']    = null;
                    $validated['principal_start_date'] = null;
                }

                $validated['charge_amount']   = $chargeAmount;
                $validated['interest_amount'] = $interestAmount;
                $validated['total_amount']    = round($amount + $interestAmount + $chargeAmount, 2);

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
            }

            // Update current balance (preserve payments already made)
            $oldTotal = $advance->total_amount ?? 0;
            $oldBalance = $advance->current_balance ?? 0;
            $amountPaid = $oldTotal - $oldBalance;
            $validated['current_balance'] = $validated['total_amount'] - $amountPaid;

            $advance->update($validated);

            return response()->json(['success' => true, 'message' => 'Client advance updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating client advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update client advance'], 500);
        }
    }

    /**
     * Delete a client advance
     */
    public function destroy($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::findOrFail($id);
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
            return $advance->schedule->map(function ($s) {
                return [
                    'payment_date'     => $s->payment_date ? $s->payment_date->format('Y-m-d') : '—',
                    'principal_amount' => (float) $s->principal_amount,
                    'interest'         => (float) $s->interest,
                    'total_amount'     => round((float) $s->principal_amount + (float) $s->interest, 2),
                    'loan_balance'     => (float) $s->loan_balance,
                    'status_id'        => $s->current_status_id,
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
                return array_merge($row, ['status_id' => $advance->current_status_id]);
            }, $computed);
        }

        return [[
            'payment_date'     => $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '—',
            'principal_amount' => (float) ($advance->amount ?? 0),
            'interest'         => (float) ($advance->interest_amount ?? 0),
            'total_amount'     => (float) ($advance->total_amount ?? 0),
            'loan_balance'     => 0.00,
            'status_id'        => $advance->current_status_id,
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
                $this->postToLandlordAccount($advance, $advance->created_by ?? 0);

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
                $this->postToLandlordAccount($advance, Auth::id());

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
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error posting advance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to post advance'], 500);
        }
    }

    /**
     * Insert debit entries (entry_type_code 0009) into cb_landlord_account
     * when an advance is posted/approved.
     *
     * For advances with a repayment schedule (Type 2/3 with months):
     *   One row per installment, tagged with that installment's year/month so that
     *   insertAdvancePaymentLines() picks up exactly the right amount each month.
     *
     * For single-payment advances (Type 1, Type 2 no months):
     *   One row for the full total_amount, tagged with the disbursement year/month.
     */
    private function postToLandlordAccount(ClientAdvance $advance, int $userId): void
    {
        $clientId   = $advance->client_id;
        $propertyId = $advance->property_id;
        $ref        = $advance->reference_number ?? 'N/A';
        $auditUser  = substr(optional(\App\Models\User::find($userId))->name ?? (string) $userId, 0, 20);

        // Resolve reference codes once via a single query
        $codes = DB::table('sys_pm_clients as c')
            ->crossJoin('sys_pm_properties as p')
            ->where('c.id', $clientId)
            ->where('p.id', $propertyId)
            ->select('c.reference_number as landlord_code', 'p.reference_number as property_code')
            ->first();

        if (!$codes) {
            throw new \Exception("postToLandlordAccount: could not resolve client/property codes for advance {$advance->id}");
        }

        // Resolve GL accounts for advance posting (1150 Loans & Advances Receivable / 2297 Clients Expense/Advance/Loans Payable)
        $glDebitAccountId  = ChartAccount::where('account_code', '1150')->value('id');
        $glCreditAccountId = ChartAccount::where('account_code', '2297')->value('id');

        // Load schedule if not already eager-loaded
        $schedule = $advance->relationLoaded('schedule')
            ? $advance->schedule
            : $advance->schedule()->orderBy('payment_date')->get();

        $hasSchedule = $schedule->isNotEmpty() && !empty($advance->repayment_months);

        if ($hasSchedule) {
            // --- Multi-installment: one ledger row per schedule entry ---
            $disbDate = $advance->disbursement_date instanceof \Carbon\Carbon
                ? $advance->disbursement_date
                : \Carbon\Carbon::parse($advance->disbursement_date);

            $rows = [];
            foreach ($schedule as $idx => $entry) {
                $payDate     = $entry->payment_date instanceof \Carbon\Carbon
                    ? $entry->payment_date
                    : \Carbon\Carbon::parse($entry->payment_date);
                $installment = round((float) $entry->principal_amount + (float) $entry->interest, 2);

                $label = $idx === 0
                    ? 'Advance ' . $ref . ' Instalment 1 of ' . $schedule->count() . ' disbursed ' . $disbDate->format('d M Y')
                    : 'Advance ' . $ref . ' Instalment ' . ($idx + 1) . ' of ' . $schedule->count();

                $rows[] = [
                    'year'               => $payDate->year,
                    'month'              => $payDate->month,
                    'landlord_code'      => $codes->landlord_code,
                    'landlord_name'      => $advance->client->names ?? null,
                    'property_code'      => $codes->property_code,
                    'transaction_number' => $ref . '/' . ($idx + 1),
                    'entry_type_code'    => '0009',
                    'entry_description'  => 'ADVANCE',
                    'entry_particulars'  => $label,
                    'entry_date'         => now(),
                    'debit_amount'       => $installment,
                    'credit_amount'      => 0,
                    'total_amount'       => $installment,
                    'gl_account'         => '1150',
                    'vendor_id'          => $codes->landlord_code,
                    'audit_user'         => $auditUser,
                    'is_recovered'       => 0,
                    'client_id'          => $clientId,
                    'property_id'        => $propertyId,
                    'advance_id'         => $advance->id,
                    'schedule_id'        => $entry->id,
                ];
            }

            foreach ($rows as $row) {
                LandlordAccount::create($row);

                // Post matching GL journal for this installment
                if ($glDebitAccountId && $glCreditAccountId) {
                    $this->postGlEntry([
                        'type'              => 'generic',
                        'amount'            => $row['debit_amount'],
                        'date'              => $row['entry_date']->toDateString(),
                        'reference'         => $ref,
                        'narration'         => $row['entry_particulars'],
                        'entered_by'        => $userId,
                        'debit_account_id'  => $glDebitAccountId,
                        'credit_account_id' => $glCreditAccountId,
                    ]);
                } else {
                    Log::warning('postToLandlordAccount: GL accounts 1150/2297 not found — GL posting skipped', [
                        'advance_id' => $advance->id,
                    ]);
                }
            }

            Log::info('Advance posted to landlord account (installments)', [
                'advance_id'   => $advance->id,
                'reference'    => $ref,
                'rows'         => count($rows),
                'client_id'    => $clientId,
                'property_id'  => $propertyId,
            ]);

        } else {
            // --- Single row: full total_amount, disbursement month ---
            $disbDate    = $advance->disbursement_date instanceof \Carbon\Carbon
                ? $advance->disbursement_date
                : \Carbon\Carbon::parse($advance->disbursement_date);
            $totalAmount = (float) $advance->total_amount;
            $particulars = 'Advance ' . $ref . ' disbursed ' . $disbDate->format('d M Y');

            LandlordAccount::create([
                'year'               => $disbDate->year,
                'month'              => $disbDate->month,
                'landlord_code'      => $codes->landlord_code,
                'landlord_name'      => $advance->client->names ?? null,
                'property_code'      => $codes->property_code,
                'transaction_number' => $ref,
                'entry_type_code'    => '0009',
                'entry_description'  => 'ADVANCE',
                'entry_particulars'  => $particulars,
                'entry_date'         => now(),
                'debit_amount'       => $totalAmount,
                'credit_amount'      => 0,
                'total_amount'       => $totalAmount,
                'gl_account'         => '1150',
                'vendor_id'          => $codes->landlord_code,
                'audit_user'         => $auditUser,
                'is_recovered'       => 0,
                'client_id'          => $clientId,
                'property_id'        => $propertyId,
                'advance_id'         => $advance->id,
                'schedule_id'        => null,
            ]);

            // Post matching GL journal
            if ($glDebitAccountId && $glCreditAccountId) {
                $this->postGlEntry([
                    'type'              => 'generic',
                    'amount'            => $totalAmount,
                    'date'              => now()->toDateString(),
                    'reference'         => $ref,
                    'narration'         => $particulars,
                    'entered_by'        => $userId,
                    'debit_account_id'  => $glDebitAccountId,
                    'credit_account_id' => $glCreditAccountId,
                ]);
            } else {
                Log::warning('postToLandlordAccount: GL accounts 1150/2297 not found — GL posting skipped', [
                    'advance_id' => $advance->id,
                ]);
            }

            Log::info('Advance posted to landlord account (lump sum)', [
                'advance_id'   => $advance->id,
                'reference'    => $ref,
                'debit_amount' => $totalAmount,
                'period'       => $disbDate->year . '/' . str_pad($disbDate->month, 2, '0', STR_PAD_LEFT),
                'client_id'    => $clientId,
                'property_id'  => $propertyId,
            ]);
        }
    }

    /**
     * Show the reschedule form for a Posted advance (status=6).
     */
    public function showRescheduleForm($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'schedule'])->findOrFail($id);

            if ($advance->current_status_id !== 6) {
                return redirect()->route('property.advances.show', $encryptedId)
                    ->with('error', 'Only Posted advances can be rescheduled.');
            }

            // ⚠️ TODO #2: Confirm source of truth for payments made.
            // Currently using sum of cleared (status=2) schedule rows.
            // May need to cross-check sys_pm_landlord_accounts_transaction instead.
            // See docs/advance_reschedule_missing.md for full context.
            $amountPaid = $advance->schedule()
                ->where('current_status_id', 2)
                ->sum(DB::raw('CAST(principal_amount AS numeric) + COALESCE(CAST(interest AS numeric), 0)'));

            $outstandingBalance = max(0, round((float) $advance->total_amount - (float) $amountPaid, 2));

            return view('property.advances.reschedule', compact('advance', 'amountPaid', 'outstandingBalance', 'encryptedId'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading reschedule form: ' . $e->getMessage());
            return redirect()->route('client.advances.index')->with('error', 'Failed to load reschedule form.');
        }
    }

    /**
     * Process the reschedule: close old advance, open new one, log history.
     */
    public function processReschedule(Request $request, $encryptedId)
    {
        $validated = $request->validate([
            'amount'               => 'required|numeric|min:1',
            'interest_rate'        => 'nullable|numeric|min:0|max:100',
            'repayment_months'     => 'nullable|integer|min:1|max:360',
            'disbursement_date'    => 'required|date',
            'principal_start_date' => 'required|date',
            'narration'            => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($encryptedId);
            $oldAdvance = ClientAdvance::with(['client', 'property', 'zone', 'schedule'])->findOrFail($id);

            if ($oldAdvance->current_status_id !== 6) {
                return response()->json(['success' => false, 'message' => 'Only Posted advances can be rescheduled.'], 422);
            }

            // ⚠️ TODO #2: Confirm source of truth for payments made.
            $amountPaid = $oldAdvance->schedule()
                ->where('current_status_id', 2)
                ->sum(DB::raw('CAST(principal_amount AS numeric) + COALESCE(CAST(interest AS numeric), 0)'));
            $outstandingBalance = max(0, round((float) $oldAdvance->total_amount - (float) $amountPaid, 2));

            // --- Recalculate interest / total for new advance ---
            $newAmount       = (float) $validated['amount'];
            $interestRate    = (float) ($validated['interest_rate'] ?? 0);
            $repaymentMonths = (int) ($validated['repayment_months'] ?? 1);
            $disbursement    = \Carbon\Carbon::parse($validated['disbursement_date']);
            $principalStart  = \Carbon\Carbon::parse($validated['principal_start_date']);

            $interestAmount = 0;
            $newSchedule    = [];

            if ($oldAdvance->advance_type_id == 3 && $interestRate > 0) {
                $newSchedule    = $this->calculateAmortizationSchedule($newAmount, $interestRate, $repaymentMonths, $disbursement, $principalStart);
                $interestAmount = collect($newSchedule)->sum('interest');
            }
            $newTotal = round($newAmount + $interestAmount, 2);

            // 1. Mark old advance as Rescheduled
            $oldAdvance->update(['current_status_id' => 8]);

            // 2. Mark all pending schedule rows of old advance as Rescheduled
            $oldAdvance->schedule()->where('current_status_id', 1)->update(['current_status_id' => 8]);

            // 3. Create new advance record
            $newAdvance = ClientAdvance::create([
                'date_time'            => now(),
                'requisition_date'     => now(),
                'narration'            => $validated['narration'] ?? 'Rescheduled from ' . $oldAdvance->reference_number,
                'created_by'           => Auth::id(),
                'zone_id'              => $oldAdvance->zone_id,
                'client_id'            => $oldAdvance->client_id,
                'property_id'          => $oldAdvance->property_id,
                'current_status_id'    => 1, // Pending — awaits landlord approval
                'amount'               => $newAmount,
                'repayment_months'     => $repaymentMonths,
                'interest_rate'        => $interestRate,
                'principal_start_date' => $principalStart,
                'disbursement_date'    => $disbursement,
                'interest_amount'      => $interestAmount,
                'total_amount'         => $newTotal,
                'current_balance'      => $newTotal,
                'reference_number'     => $oldAdvance->reference_number . '-R' . (substr_count($oldAdvance->reference_number, '-R') + 1),
                'advance_type_id'      => $oldAdvance->advance_type_id,
                'previous_advance_id'  => $oldAdvance->id,
                'miscallenous_charge_id' => $oldAdvance->miscallenous_charge_id,
                'charge_amount'        => $oldAdvance->charge_amount,
            ]);

            // 4. Create new schedule rows (Type 3 / Overpayment advances)
            if (!empty($newSchedule)) {
                foreach ($newSchedule as $row) {
                    \App\Models\AdvanceSchedule::create([
                        'parent_id'        => $newAdvance->id,
                        'payment_date'     => $row['payment_date'],
                        'principal_amount' => $row['principal'],
                        'interest'         => $row['interest'],
                        'loan_balance'     => $row['balance'],
                        'current_status_id'=> 1,
                    ]);
                }
            }

            // 5. Log to reschedule history
            $rootId = $this->getRootAdvanceId($oldAdvance);
            \App\Models\AdvanceRescheduleHistory::create([
                'original_advance_id'               => $rootId,
                'from_advance_id'                   => $oldAdvance->id,
                'to_advance_id'                     => $newAdvance->id,
                'amount_paid_at_reschedule'         => $amountPaid,
                'outstanding_balance_at_reschedule' => $outstandingBalance,
                'narration'                         => $validated['narration'] ?? null,
                'rescheduled_by'                    => Auth::id(),
                'rescheduled_at'                    => now(),
            ]);

            DB::commit();

            Log::info('Advance rescheduled', [
                'old_advance_id' => $oldAdvance->id,
                'new_advance_id' => $newAdvance->id,
                'user_id'        => Auth::id(),
            ]);

            // Notify landlord of new advance pending approval
            try {
                $newEncryptedId  = Crypt::encrypt($newAdvance->id);
                $notifySchedule  = $this->buildScheduleArray($newAdvance->load('schedule'));
                $newAdvance->client->notify(new \App\Notifications\AdvanceApprovalNotification(
                    $newAdvance,
                    $newEncryptedId,
                    $notifySchedule
                ));
            } catch (\Exception $notifyEx) {
                Log::warning('Failed to send reschedule approval notification', [
                    'advance_id' => $newAdvance->id,
                    'error'      => $notifyEx->getMessage(),
                ]);
            }

            return response()->json([
                'success'         => true,
                'message'         => 'Advance rescheduled successfully. Landlord has been notified.',
                'new_advance_id'  => Crypt::encrypt($newAdvance->id),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error processing reschedule: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reschedule advance: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Walk the previous_advance_id chain to find the very first (original) advance.
     */
    private function getRootAdvanceId(ClientAdvance $advance): int
    {
        $current = $advance;
        $visited = [];
        while ($current->previous_advance_id && !in_array($current->previous_advance_id, $visited)) {
            $visited[] = $current->id;
            $parent = ClientAdvance::find($current->previous_advance_id);
            if (!$parent) break;
            $current = $parent;
        }
        return $current->id;
    }

    /**
     * Get client average returns over specified months
     */
    private function getClientAverageReturns($clientId, $months = 6)
    {
        try {
            // Get returns from sys_reports_landlord_accounts for last N months
            $startDate = now()->subMonths($months)->startOfMonth();
            //'2024-09-01';

            $totalReturns = DB::table('sys_reports_landlord_accounts')
                ->where('client_id', $clientId)
                ->where('transaction_date', '>=', $startDate)
                ->whereIn('transaction_type_id', [1, 2]) // Returns types
                ->sum('credit');

            return $totalReturns > 0 ? $totalReturns / $months : 0;
        } catch (\Exception $e) {
            Log::error('Error getting client average returns: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get client total outstanding advances
     */
    private function getClientOutstandingAdvances($clientId)
    {
        try {
            return ClientAdvance::where('client_id', $clientId)
                ->whereIn('current_status_id', [1, 5, 6]) // Pending, Approved, Posted
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
    private function getMaxAdvanceAmount($clientId)
    {
        try {
            // Get last 6 months average returns
            $avgMonthlyReturns = $this->getClientAverageReturns($clientId, 6);

            // Get existing outstanding balance
            $outstandingBalance = $this->getClientOutstandingAdvances($clientId);

            // Calculate max (80% of annual returns)
            $maxAnnualAdvance = $avgMonthlyReturns * 12 * 0.8;

            // Available = Max - Outstanding
            $available = $maxAnnualAdvance - $outstandingBalance;

            return max(0, $available);
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

            // Principal repayment schedule
            $currentDate = $principalStart->copy();
            for ($month = 1; $month <= $repaymentMonths; $month++) {
                if ($month > 1 || $gracePeriodMonths == 0) {
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
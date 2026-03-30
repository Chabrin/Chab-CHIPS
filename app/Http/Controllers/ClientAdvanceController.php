<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientAdvance;
use App\Models\Client;
use App\Models\Property;
use App\Models\InvoiceableItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Services\ErrorDispatcher;

class ClientAdvanceController extends Controller
{
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
            $advance = ClientAdvance::with(['client', 'property', 'zone', 'invoiceableitems', 'createdBy'])
                ->findOrFail($id);

            return view('property.advances.show', compact('advance'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading advance details: ' . $e->getMessage());
            return redirect()->route('property.advances.index')->with('error', 'Failed to load advance details');
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
            return redirect()->route('property.advances.index')->with('error', 'Failed to load advance for editing');
        }
    }

    /**
     * Get all client advances (AJAX)
     */
    public function getAdvances()
    {
        try {
            $advances = ClientAdvance::with(['client', 'property', 'zone', 'invoiceableitems'])
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($advance) {
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
                        1 => 'Expense Advance',
                        2 => 'Client Payment Advance',
                        3 => 'Overpayment Advance',
                    ];

                    return [
                        'id' => $advance->id,
                        'encrypted_id' => Crypt::encrypt($advance->id),
                        'requisition_date' => $advance->requisition_date ? $advance->requisition_date->format('Y-m-d') : '-',
                        'reference_number' => $advance->reference_number ?? '-',
                        'client_name' => $advance->client ? $advance->client->client_name : '-',
                        'property_name' => $advance->property ? $advance->property->property_name : '-',
                        'zone_name' => $advance->zone ? $advance->zone->zone_name : '-',
                        'advance_type' => $typeMap[$advance->advance_type_id] ?? 'Unknown',
                        'charge_amount' => $advance->charge_amount ? number_format($advance->charge_amount, 2) : '0.00',
                        'amount' => $advance->amount ? number_format($advance->amount, 2) : '0.00',
                        'current_balance' => $advance->current_balance ? number_format($advance->current_balance, 2) : '0.00',
                        'status' => $statusMap[$advance->current_status_id] ?? 'Unknown',
                        'narration' => $advance->narration ?? '-',
                        'can_update' => Auth::user()->isAbleTo('update_client_advances'),
                        'can_delete' => Auth::user()->isAbleTo('delete_client_advances'),
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
                'advance_type_id' => 'required|integer|in:1,2,3',
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'property_id' => 'nullable|integer|exists:sys_pm_properties,id',
                'requisition_date' => 'required|date|before_or_equal:today',
                'disbursement_date' => 'required|date|after_or_equal:requisition_date',
                'amount' => 'required|numeric|min:1',
                'narration' => 'required|string|min:5|max:500',
            ];

            // Type-specific rules
            if ($advanceTypeId == 1) { // Expense Advance
                $rules['expense_category'] = 'required|string|in:repairs_maintenance,caretaker_salaries,garbage_collection,electricity_bills,water_bills,printing_photocopying,refund_tenant_deposits,eviction_costs,locking_devices,security_services';
                $rules['charge_amount'] = 'required|numeric|min:0';
                $rules['charge_billed_to'] = 'required|string|in:Client,Tenant';
            } elseif ($advanceTypeId == 2) { // Client Payment Advance
                $rules['payment_method'] = 'required|string|in:bank_transfer,alternative';
                $rules['payment_instructions'] = 'nullable|string|max:500';
                $rules['charge_amount'] = 'nullable|numeric|min:0';
                $rules['interest_rate'] = 'nullable|numeric|min:0|max:10';
            } elseif ($advanceTypeId == 3) { // Overpayment Advance
                $rules['repayment_months'] = 'required|integer|min:1|max:36';
                $rules['principal_start_date'] = 'required|date|after:disbursement_date';
                $rules['interest_rate'] = 'required|numeric|min:0|max:10';
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

            if ($advanceTypeId == 1) {
                // EXPENSE ADVANCE: No interest, cheque charge auto-set by category
                $validated['interest_rate'] = 0;
                $validated['interest_amount'] = 0;
                $chargeAmount = floatval($validated['charge_amount'] ?? 0);
                $validated['total_amount'] = $amount + $chargeAmount;
                $validated['current_balance'] = $validated['total_amount'];
                $validated['repayment_months'] = null;
                $validated['principal_start_date'] = null;

            } elseif ($advanceTypeId == 2) {
                // CLIENT PAYMENT ADVANCE: Interest based on issued date in the month
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;
                $rate = floatval($validated['interest_rate'] ?? 3);

                if ($day < 20) {
                    // Before 20th: full interest
                    $interestAmount = $amount * $rate / 100;
                } elseif ($day >= 20 && $day <= 25) {
                    // 20th-25th: prorated interest
                    $interestAmount = ($amount * $rate / 100) * ($remainingDays / $daysInMonth);
                } else {
                    // After 25th: no interest
                    $interestAmount = 0;
                    $validated['interest_rate'] = 0;
                }

                $chargeAmount = floatval($validated['charge_amount'] ?? 100);
                $validated['charge_amount'] = $chargeAmount;
                $validated['interest_amount'] = round($interestAmount, 2);
                $validated['total_amount'] = round($amount + $interestAmount + $chargeAmount, 2);
                $validated['current_balance'] = $validated['total_amount'];
                $validated['repayment_months'] = null;
                $validated['principal_start_date'] = null;

            } elseif ($advanceTypeId == 3) {
                // OVERPAYMENT ADVANCE: 3% monthly reducing balance, 3 month default repayment
                $rate = floatval($validated['interest_rate'] ?? 3);
                $repaymentMonths = intval($validated['repayment_months'] ?? 3);

                // Calculate first month interest (prorated if before 25th)
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;

                // Generate amortization schedule (reducing balance)
                $schedule = $this->calculateAmortizationSchedule(
                    $amount,
                    $rate,
                    $repaymentMonths,
                    $validated['disbursement_date'],
                    $validated['principal_start_date']
                );

                $totalInterest = array_sum(array_column($schedule, 'interest'));
                $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));

                // If disbursed before 25th, prorate first month interest
                if ($day < 25 && !empty($schedule)) {
                    $firstInterest = $schedule[0]['interest'];
                    $proratedFirst = round($firstInterest * ($remainingDays / $daysInMonth), 2);
                    $schedule[0]['interest'] = $proratedFirst;
                    $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                    $totalInterest = array_sum(array_column($schedule, 'interest'));
                }

                $chargeAmount = floatval($validated['charge_amount'] ?? 100);
                $validated['charge_amount'] = $chargeAmount;
                $validated['interest_amount'] = round($totalInterest, 2);
                $validated['total_amount'] = round($totalPrincipal + $totalInterest + $chargeAmount, 2);
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

            // Create amortization schedule entries (for Overpayment type)
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
                'advance_type_id' => 'required|integer|in:1,2,3',
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'property_id' => 'nullable|integer|exists:sys_pm_properties,id',
                'requisition_date' => 'required|date',
                'disbursement_date' => 'required|date',
                'amount' => 'required|numeric|min:1',
                'narration' => 'required|string|min:5|max:500',
            ];

            // Type-specific rules
            if ($advanceTypeId == 1) { // Expense Advance
                $rules['expense_category'] = 'required|string|in:repairs_maintenance,caretaker_salaries,garbage_collection,electricity_bills,water_bills,printing_photocopying,refund_tenant_deposits,eviction_costs,locking_devices,security_services';
                $rules['charge_amount'] = 'required|numeric|min:0';
                $rules['charge_billed_to'] = 'required|string|in:Client,Tenant';
            } elseif ($advanceTypeId == 2) { // Client Payment Advance
                $rules['payment_method'] = 'required|string|in:bank_transfer,alternative';
                $rules['payment_instructions'] = 'nullable|string|max:500';
                $rules['charge_amount'] = 'nullable|numeric|min:0';
                $rules['interest_rate'] = 'nullable|numeric|min:0|max:10';
            } elseif ($advanceTypeId == 3) { // Overpayment Advance
                $rules['repayment_months'] = 'required|integer|min:1|max:36';
                $rules['principal_start_date'] = 'required|date|after:disbursement_date';
                $rules['interest_rate'] = 'required|numeric|min:0|max:10';
                $rules['charge_amount'] = 'nullable|numeric|min:0';
            }

            $validated = $request->validate($rules);

            // Get zone_id from the selected property
            $property = !empty($validated['property_id']) ? \App\Models\Property::find($validated['property_id']) : null;
            $validated['zone_id'] = $property ? $property->zone_id : null;

            // ===== INTEREST & CHARGE CALCULATIONS BY TYPE =====
            $disbursement = \Carbon\Carbon::parse($validated['disbursement_date']);
            $amount = floatval($validated['amount']);

            if ($advanceTypeId == 1) {
                // EXPENSE ADVANCE: No interest
                $validated['interest_rate'] = 0;
                $validated['interest_amount'] = 0;
                $chargeAmount = floatval($validated['charge_amount'] ?? 0);
                $validated['total_amount'] = $amount + $chargeAmount;
                $validated['repayment_months'] = null;
                $validated['principal_start_date'] = null;

            } elseif ($advanceTypeId == 2) {
                // CLIENT PAYMENT ADVANCE: Interest based on issued date
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;
                $rate = floatval($validated['interest_rate'] ?? 3);

                if ($day < 20) {
                    $interestAmount = $amount * $rate / 100;
                } elseif ($day >= 20 && $day <= 25) {
                    $interestAmount = ($amount * $rate / 100) * ($remainingDays / $daysInMonth);
                } else {
                    $interestAmount = 0;
                    $validated['interest_rate'] = 0;
                }

                $chargeAmount = floatval($validated['charge_amount'] ?? 100);
                $validated['charge_amount'] = $chargeAmount;
                $validated['interest_amount'] = round($interestAmount, 2);
                $validated['total_amount'] = round($amount + $interestAmount + $chargeAmount, 2);
                $validated['repayment_months'] = null;
                $validated['principal_start_date'] = null;

            } elseif ($advanceTypeId == 3) {
                // OVERPAYMENT ADVANCE: Reducing balance
                $rate = floatval($validated['interest_rate'] ?? 3);
                $repaymentMonths = intval($validated['repayment_months'] ?? 3);
                $day = $disbursement->day;
                $daysInMonth = $disbursement->daysInMonth;
                $remainingDays = $daysInMonth - $day;

                $schedule = $this->calculateAmortizationSchedule(
                    $amount,
                    $rate,
                    $repaymentMonths,
                    $validated['disbursement_date'],
                    $validated['principal_start_date']
                );

                if ($day < 25 && !empty($schedule)) {
                    $firstInterest = $schedule[0]['interest'];
                    $proratedFirst = round($firstInterest * ($remainingDays / $daysInMonth), 2);
                    $schedule[0]['interest'] = $proratedFirst;
                    $schedule[0]['total_amount'] = $schedule[0]['principal_amount'] + $proratedFirst;
                }

                $totalInterest = array_sum(array_column($schedule, 'interest'));
                $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
                $chargeAmount = floatval($validated['charge_amount'] ?? 100);
                $validated['charge_amount'] = $chargeAmount;
                $validated['interest_amount'] = round($totalInterest, 2);
                $validated['total_amount'] = round($totalPrincipal + $totalInterest + $chargeAmount, 2);
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

            // Grace period - interest only
            $currentDate = $disbursement->copy();
            for ($i = 0; $i < $gracePeriodMonths; $i++) {
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

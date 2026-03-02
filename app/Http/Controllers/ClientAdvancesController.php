<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\ClientAdvance;
use App\Models\WorkflowStatus;
use App\Models\AdvanceSchedule;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ClientAdvancesController extends Controller
{
    /**
     * Display the advances listing page
     */
    public function index()
    {
        return view('property.advances.index');
    }

    /**
     * Show form to create new advance
     */
    public function create()
    {
        $clients = Client::where('deleted', 0)
            ->where('reference_number', 'LIKE', 'LAN%')
            ->orderBy('names', 'asc')
            ->get();

        return view('property.advances.create', compact('clients'));
    }

    /**
     * Search and filter advances for listing
     */
    public function searchAdvances(Request $request)
    {
        try {
            $query = ClientAdvance::with(['client', 'property', 'status']);

            // Filter by client
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            // Filter by status
            if ($request->filled('status_id')) {
                $query->where('current_status_id', $request->status_id);
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('requisition_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('requisition_date', '<=', $request->date_to);
            }

            // Filter by reference
            if ($request->filled('reference')) {
                $query->where('reference_number', 'like', '%' . $request->reference . '%');
            }

            // Order by most recent
            $query->orderBy('date_time', 'desc')->limit(100);

            $advances = $query->get();

            // Format the data
            $data = $advances->map(function ($advance) {
                return [
                    'id' => $advance->id,
                    'encrypted_id' => encrypt($advance->id),
                    'reference_number' => $advance->reference_number ?? '-',
                    'requisition_date' => $advance->requisition_date ? $advance->requisition_date->format('d-m-Y') : '-',
                    'client_name' => $advance->client->names ?? '-',
                    'client_reference' => $advance->client->reference_number ?? '-',
                    'property_name' => $advance->property->property_name ?? '-',
                    'amount' => number_format($advance->amount, 2),
                    'interest_rate' => $advance->interest_rate . '%',
                    'total_amount' => number_format($advance->total_amount, 2),
                    'current_balance' => number_format($advance->current_balance, 2),
                    'repayment_months' => $advance->repayment_months,
                    'status' => $advance->status->status_name ?? '-',
                    'status_id' => $advance->current_status_id,
                    'disbursement_date' => $advance->disbursement_date ? $advance->disbursement_date->format('d-m-Y') : '-',
                    'principal_start_date' => $advance->principal_start_date ? $advance->principal_start_date->format('d-m-Y') : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'advances' => $data
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching advances: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search advances'
            ], 500);
        }
    }

    /**
     * Show advance details with amortization schedule
     */
    public function show($encryptedId)
    {
        try {
            $advanceId = decrypt($encryptedId);

            $advance = ClientAdvance::with(['client', 'property', 'status', 'createdBy', 'schedule.status'])
                ->find($advanceId);

            if (!$advance) {
                return redirect()->route('client.advances.index')
                    ->with('error', 'Advance not found');
            }

            // Calculate summary statistics
            $totalSchedules = $advance->schedule()->count();
            $clearedSchedules = $advance->schedule()->where('current_status_id', 2)->count();
            $pendingSchedules = $advance->schedule()->where('current_status_id', 1)->count();
            $totalPaid = $advance->schedule()->where('current_status_id', 2)->sum('total_amount');
            $percentagePaid = $advance->total_amount > 0 ? round(($totalPaid / $advance->total_amount) * 100, 2) : 0;

            return view('property.advances.details', compact(
                'advance',
                'totalSchedules',
                'clearedSchedules',
                'pendingSchedules',
                'totalPaid',
                'percentagePaid'
            ));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading advance details', [
                'encrypted_id' => $encryptedId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('client.advances.index')
                ->with('error', 'Failed to load advance details');
        }
    }

    /**
     * Calculate amortization schedule (AJAX)
     */
    public function calculateAmortization(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'interest_rate' => 'required|numeric|min:0',
                'repayment_months' => 'required|integer|min:1',
                'disbursement_date' => 'required|date',
                'principal_start_date' => 'required|date',
            ]);

            $principal = $validated['amount'];
            $interestRate = $validated['interest_rate'];
            $months = $validated['repayment_months'];
            $disbursementDate = Carbon::parse($validated['disbursement_date']);
            $principalStartDate = Carbon::parse($validated['principal_start_date']);

            $schedule = [];
            $balance = $principal;
            $principalPerMonth = $principal / $months;
            $totalInterest = 0;

            // Check for grace period (interest only)
            $hasGracePeriod = false;
            if ($disbursementDate->format('Y-m') !== $principalStartDate->format('Y-m')) {
                if ($disbursementDate->day < 25) {
                    $hasGracePeriod = true;
                    // Add interest-only period(s)
                    $currentDate = $disbursementDate->copy();
                    while ($currentDate->format('Y-m') < $principalStartDate->format('Y-m')) {
                        $interest = round(($balance * $interestRate) / 100, 2);
                        $totalInterest += $interest;

                        $schedule[] = [
                            'payment_date' => $currentDate->copy()->endOfMonth()->format('Y-m-d'),
                            'payment_type_id' => 2, // Interest Only
                            'payment_type' => 'Interest Only',
                            'principal_amount' => 0,
                            'interest' => $interest,
                            'total_amount' => $interest,
                            'loan_balance' => $balance,
                        ];

                        $currentDate->addMonth();
                    }
                }
            }

            // Principal repayment schedule
            $currentDate = $principalStartDate->copy();
            for ($i = 1; $i <= $months; $i++) {
                $interest = round(($balance * $interestRate) / 100, 2);
                $principal_payment = round($principalPerMonth, 2);

                // Adjust last payment for rounding differences
                if ($i == $months) {
                    $principal_payment = $balance;
                }

                $total = $principal_payment + $interest;
                $balance -= $principal_payment;
                $totalInterest += $interest;

                $schedule[] = [
                    'payment_date' => $currentDate->copy()->format('Y-m-d'),
                    'payment_type_id' => 3, // Principal + Interest
                    'payment_type' => 'Principal + Interest',
                    'principal_amount' => $principal_payment,
                    'interest' => $interest,
                    'total_amount' => $total,
                    'loan_balance' => max(0, $balance),
                ];

                $currentDate->addMonth();
            }

            return response()->json([
                'success' => true,
                'schedule' => $schedule,
                'summary' => [
                    'principal' => number_format($validated['amount'], 2),
                    'total_interest' => number_format($totalInterest, 2),
                    'total_amount' => number_format($validated['amount'] + $totalInterest, 2),
                    'has_grace_period' => $hasGracePeriod,
                ]
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error calculating amortization: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate amortization schedule'
            ], 500);
        }
    }

    /**
     * Store new advance requisition
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:sys_pm_clients,id',
                'property_id' => 'nullable|exists:sys_pm_properties,id',
                'amount' => 'required|numeric|min:1',
                'interest_rate' => 'required|numeric|min:0',
                'repayment_months' => 'required|integer|min:1',
                'disbursement_date' => 'required|date',
                'principal_start_date' => 'required|date',
                'narration' => 'required|string',
                'advance_type_id' => 'required|in:1,2,3',
                'previous_advance_id' => 'nullable|exists:sys_pm_clients_advances_requisitions,id',
                'charge_amount' => 'nullable|numeric|min:0',
            ]);

            // Generate reference number
            $year = date('Y');
            $lastAdvance = ClientAdvance::whereYear('date_time', $year)->orderBy('id', 'desc')->first();
            $nextNumber = $lastAdvance ? (intval(substr($lastAdvance->reference_number, -3)) + 1) : 1;
            $referenceNumber = 'ADV-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Calculate amortization
            $amortizationResult = $this->calculateAmortizationSchedule(
                $validated['amount'],
                $validated['interest_rate'],
                $validated['repayment_months'],
                $validated['disbursement_date'],
                $validated['principal_start_date']
            );

            // Create advance
            $advance = ClientAdvance::create([
                'date_time' => now(),
                'reference_number' => $referenceNumber,
                'requisition_date' => now(),
                'disbursement_date' => $validated['disbursement_date'],
                'principal_start_date' => $validated['principal_start_date'],
                'client_id' => $validated['client_id'],
                'property_id' => $validated['property_id'] ?? null,
                'amount' => $validated['amount'],
                'interest_rate' => $validated['interest_rate'],
                'interest_amount' => $amortizationResult['total_interest'],
                'total_amount' => $amortizationResult['total_amount'],
                'repayment_months' => $validated['repayment_months'],
                'charge_amount' => $validated['charge_amount'] ?? 0,
                'current_balance' => $amortizationResult['total_amount'],
                'narration' => $validated['narration'],
                'advance_type_id' => $validated['advance_type_id'],
                'previous_advance_id' => $validated['previous_advance_id'] ?? null,
                'created_by' => Auth::id(),
                'current_status_id' => 1, // Pending
            ]);

            // Create schedule entries
            foreach ($amortizationResult['schedule'] as $scheduleItem) {
                AdvanceSchedule::create([
                    'parent_id' => $advance->id,
                    'payment_date' => $scheduleItem['payment_date'],
                    'payment_type_id' => $scheduleItem['payment_type_id'],
                    'principal_amount' => $scheduleItem['principal_amount'],
                    'interest' => $scheduleItem['interest'],
                    'total_amount' => $scheduleItem['total_amount'],
                    'loan_balance' => $scheduleItem['loan_balance'],
                    'current_status_id' => 1, // Pending
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Advance requisition created successfully',
                'advance_id' => $advance->id,
                'encrypted_id' => encrypt($advance->id),
                'reference_number' => $referenceNumber
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating advance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create advance requisition: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to calculate amortization schedule
     */
    private function calculateAmortizationSchedule($principal, $interestRate, $months, $disbursementDate, $principalStartDate)
    {
        $schedule = [];
        $balance = $principal;
        $principalPerMonth = $principal / $months;
        $totalInterest = 0;

        $disbursement = Carbon::parse($disbursementDate);
        $principalStart = Carbon::parse($principalStartDate);

        // Check for grace period
        if ($disbursement->format('Y-m') !== $principalStart->format('Y-m')) {
            if ($disbursement->day < 25) {
                $currentDate = $disbursement->copy();
                while ($currentDate->format('Y-m') < $principalStart->format('Y-m')) {
                    $interest = round(($balance * $interestRate) / 100, 2);
                    $totalInterest += $interest;

                    $schedule[] = [
                        'payment_date' => $currentDate->copy()->endOfMonth()->format('Y-m-d'),
                        'payment_type_id' => 2,
                        'principal_amount' => 0,
                        'interest' => $interest,
                        'total_amount' => $interest,
                        'loan_balance' => $balance,
                    ];

                    $currentDate->addMonth();
                }
            }
        }

        // Principal repayment
        $currentDate = $principalStart->copy();
        for ($i = 1; $i <= $months; $i++) {
            $interest = round(($balance * $interestRate) / 100, 2);
            $principal_payment = round($principalPerMonth, 2);

            if ($i == $months) {
                $principal_payment = $balance;
            }

            $total = $principal_payment + $interest;
            $balance -= $principal_payment;
            $totalInterest += $interest;

            $schedule[] = [
                'payment_date' => $currentDate->copy()->format('Y-m-d'),
                'payment_type_id' => 3,
                'principal_amount' => $principal_payment,
                'interest' => $interest,
                'total_amount' => $total,
                'loan_balance' => max(0, $balance),
            ];

            $currentDate->addMonth();
        }

        return [
            'schedule' => $schedule,
            'total_interest' => $totalInterest,
            'total_amount' => $principal + $totalInterest,
        ];
    }

    /**
     * Approve advance (workflow integration placeholder)
     */
    public function approve(Request $request, $encryptedId)
    {
        DB::beginTransaction();
        try {
            $advanceId = decrypt($encryptedId);
            $advance = ClientAdvance::find($advanceId);

            if (!$advance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Advance not found'
                ], 404);
            }

            if ($advance->current_status_id != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending advances can be approved'
                ], 400);
            }

            // Update status to approved
            $advance->update([
                'current_status_id' => 5 // Approved
            ]);

            // TODO: Additional approval actions
            // 1. Create GL entries (recordGL)
            // 2. Create client statement entry (debit client account)
            // 3. Generate payment voucher
            // 4. Create workflow task if needed

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Advance approved successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error approving advance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve advance'
            ], 500);
        }
    }

    /**
     * Get advance details for AJAX requests
     */
    public function getAdvanceDetails($encryptedId)
    {
        try {
            $advanceId = decrypt($encryptedId);
            $advance = ClientAdvance::with(['client', 'property', 'status', 'schedule.status'])
                ->find($advanceId);

            if (!$advance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Advance not found'
                ], 404);
            }

            $data = [
                'id' => $advance->id,
                'reference_number' => $advance->reference_number,
                'requisition_date' => $advance->requisition_date->format('d-m-Y'),
                'disbursement_date' => $advance->disbursement_date->format('d-m-Y'),
                'principal_start_date' => $advance->principal_start_date->format('d-m-Y'),
                'client_name' => $advance->client->names,
                'property_name' => $advance->property->property_name ?? '-',
                'amount' => number_format($advance->amount, 2),
                'interest_rate' => $advance->interest_rate,
                'interest_amount' => number_format($advance->interest_amount, 2),
                'total_amount' => number_format($advance->total_amount, 2),
                'current_balance' => number_format($advance->current_balance, 2),
                'repayment_months' => $advance->repayment_months,
                'status' => $advance->status->status_name,
                'narration' => $advance->narration,
                'schedule' => $advance->schedule->map(function ($s) {
                    return [
                        'payment_date' => $s->payment_date->format('d-m-Y'),
                        'payment_type' => $s->payment_type_name,
                        'principal_amount' => number_format($s->principal_amount, 2),
                        'interest' => number_format($s->interest, 2),
                        'total_amount' => number_format($s->total_amount, 2),
                        'loan_balance' => number_format($s->loan_balance, 2),
                        'status' => $s->status->status_name ?? '-',
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'advance' => $data
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error getting advance details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get advance details'
            ], 500);
        }
    }
}

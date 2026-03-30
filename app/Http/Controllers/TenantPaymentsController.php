<?php

namespace App\Http\Controllers;

use App\Models\TenantPayment;
use App\Models\TenantPaymentAllocation;
use App\Models\TenantPaymentAllocated;
use App\Models\DebtorInvoice;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TenantPaymentsAllocatedExport;

class TenantPaymentsController extends Controller
{
    /**
     * Display the tenant payments listing page
     */
    public function index()
    {
        $properties = Property::select('id', 'property_name', 'reference_number')
            ->orderBy('property_name')->get();
        return view('property.payments.tenant-payments', compact('properties'));
    }

    /**
     * Display payment details page with allocation trail
     */
    public function showPaymentDetails($encryptedId)
    {
        try {
            $paymentId = decrypt($encryptedId);

            $payment = TenantPayment::with(['tenant', 'property', 'unit'])
                ->find($paymentId);

            if (!$payment) {
                return redirect()->route('tenant.payments.index')
                    ->with('error', 'Payment not found');
            }

            // Get allocations with invoice details
            $allocations = DB::table('sys_pm_tenants_payments_invoices as api')
                ->select([
                    'api.*',
                    'i.reference_number',
                    'i.total_amount',
                    'i.current_balance'
                ])
                ->leftJoin('sys_pm_debtors_invoices as i', 'api.invoice_id', '=', 'i.id')
                ->where('api.payment_id', $paymentId)
                ->orderBy('api.date_time', 'asc')
                ->get();

            // Add invoice relationship manually
            foreach ($allocations as $allocation) {
                $allocation->invoice = (object)[
                    'id' => $allocation->invoice_id,
                    'reference_number' => $allocation->reference_number,
                    'total_amount' => $allocation->total_amount,
                    'current_balance' => $allocation->current_balance
                ];
            }

            $totalAllocated = $allocations->sum('amount_allocated');
            $unallocated = $payment->amount - $totalAllocated;

            // Determine allocation status
            if ($totalAllocated == 0) {
                $allocationStatus = 'None';
            } elseif ($unallocated > 0) {
                $allocationStatus = 'Partial';
            } else {
                $allocationStatus = 'Full';
            }

            return view('property.payments.payment-details', compact(
                'payment',
                'allocations',
                'totalAllocated',
                'unallocated',
                'allocationStatus'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading payment details', [
                'encrypted_id' => $encryptedId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('tenant.payments.index')
                ->with('error', 'Failed to load payment details');
        }
    }

    /**
     * Search and filter tenant payments
     */
    public function searchPayments(Request $request)
    {
        try {
            Log::info('Tenant payments search started', [
                'allocation_status' => $request->allocation_status,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to
            ]);

            $query = TenantPayment::query();

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }

            // Filter by allocation status
            if ($request->filled('allocation_status')) {
                $status = $request->allocation_status;

                if ($status === 'unallocated') {
                    // Payments with no allocations
                    $query->whereDoesntHave('allocations');
                } elseif ($status === 'partially_allocated') {
                    // Payments where sum of allocations < amount
                    $query->whereHas('allocations')
                        ->whereRaw('amount > (SELECT COALESCE(SUM(amount_allocated), 0) FROM sys_pm_tenants_payments_87_repeat WHERE parent_id = sys_pm_tenants_payments.id)');
                } elseif ($status === 'fully_allocated') {
                    // Payments where sum of allocations >= amount
                    $query->whereRaw('amount <= (SELECT COALESCE(SUM(amount_allocated), 0) FROM sys_pm_tenants_payments_87_repeat WHERE parent_id = sys_pm_tenants_payments.id)');
                }
            }

            // Filter by reference
            if ($request->filled('reference')) {
                $query->where('reference', 'like', '%' . $request->reference . '%');
            }

            // Filter by tenant
            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }

            // Order by payment date descending
            $query->orderBy('payment_date', 'desc')->limit(100);

            Log::info('Query built, executing...');

            $payments = $query->get();

            Log::info('Payments retrieved', ['count' => $payments->count()]);

            // Map data for response
            $mappedPayments = $payments->map(function ($payment) {
                try {
                    // Load relationships individually to catch errors
                    $tenant = $payment->tenant;
                    $property = $payment->property;
                    $unit = $payment->unit;
                    $allocations = $payment->allocations;

                    $totalAllocated = $allocations->sum('amount_allocated') ?? 0;
                    $unallocated = $payment->amount - $totalAllocated;

                    // Determine allocation status
                    if ($totalAllocated == 0) {
                        $allocationStatus = 'Unallocated';
                        $statusBadge = '<span class="badge badge-warning">Unallocated</span>';
                    } elseif ($unallocated > 0) {
                        $allocationStatus = 'Partially Allocated';
                        $statusBadge = '<span class="badge badge-info">Partially Allocated</span>';
                    } else {
                        $allocationStatus = 'Fully Allocated';
                        $statusBadge = '<span class="badge badge-success">Fully Allocated</span>';
                    }

                    return [
                        'id' => $payment->id,
                        'encrypted_id' => encrypt($payment->id),
                        'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : '-',
                        'reference' => $payment->reference ?? '-',
                        'tenant_name' => $tenant ? trim(($tenant->sur_name ?? '') . ' ' . ($tenant->first_name ?? '') . ' ' . ($tenant->other_names ?? '')) : '-',
                        'tenant_encrypted_id' => $tenant ? encrypt($tenant->id) : null,
                        'property_name' => $property ? $property->property_name : '-',
                        'property_encrypted_id' => $property ? encrypt($property->id) : null,
                        'unit_name' => $unit ? $unit->unit_name : '-',
                        'amount' => number_format($payment->amount, 2),
                        'amount_raw' => $payment->amount,
                        'allocated_amount' => number_format($totalAllocated, 2),
                        'allocated_amount_raw' => $totalAllocated,
                        'unallocated_amount' => number_format($unallocated, 2),
                        'unallocated_amount_raw' => $unallocated,
                        'narration' => $payment->narration ?? '-',
                        'channel' => '-',
                        'status_name' => '-',
                        'allocation_status' => $allocationStatus,
                        'allocation_status_badge' => $statusBadge,
                        'receipt_number' => $payment->receipt_number ?? '-'
                    ];
                } catch (\Exception $mapError) {
                    Log::error('Error mapping payment', [
                        'payment_id' => $payment->id,
                        'error' => $mapError->getMessage(),
                        'trace' => $mapError->getTraceAsString()
                    ]);
                    throw $mapError;
                }
            });

            Log::info('Payments mapped successfully');

            return response()->json([
                'success' => true,
                'payments' => $mappedPayments
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching tenant payments', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment details with allocations
     */
    public function getPaymentDetails($id)
    {
        try {
            $paymentId = decrypt($id);

            $payment = TenantPayment::with([
                'tenant',
                'property',
                'unit',
                'lease',
                'status',
                'channel',
                'allocations.invoice'
            ])->findOrFail($paymentId);

            $totalAllocated = $payment->allocations->sum('amount_allocated') ?? 0;
            $unallocated = $payment->amount - $totalAllocated;

            $allocations = $payment->allocations->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_reference' => $allocation->invoice ? $allocation->invoice->reference_number : '-',
                    'amount_allocated' => number_format($allocation->amount_allocated, 2),
                    'amount_allocated_raw' => $allocation->amount_allocated,
                    'payment_priority' => $allocation->payment_priority ?? '-',
                    'allocation_id' => $allocation->allocation_id
                ];
            });

            return response()->json([
                'success' => true,
                'payment' => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : '-',
                    'amount' => number_format($payment->amount, 2),
                    'amount_raw' => $payment->amount,
                    'tenant_name' => $payment->tenant ? ($payment->tenant->sur_name . ' ' . $payment->tenant->first_name . ' ' . $payment->tenant->other_names) : '-',
                    'property_name' => $payment->property ? $payment->property->property_name : '-',
                    'unit_name' => $payment->unit ? $payment->unit->unit_name : '-',
                    'narration' => $payment->narration ?? '-',
                    'channel' => $payment->channel ? $payment->channel->channel_name : '-',
                    'receipt_number' => $payment->receipt_number ?? '-',
                    'status' => $payment->status ? $payment->status->status_name : '-',
                    'total_allocated' => number_format($totalAllocated, 2),
                    'total_allocated_raw' => $totalAllocated,
                    'unallocated_amount' => number_format($unallocated, 2),
                    'unallocated_amount_raw' => $unallocated,
                    'trans_details' => $payment->trans_details ?? '-'
                ],
                'allocations' => $allocations
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching payment details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant invoices for allocation
     */
    public function getTenantInvoices($tenantId)
    {
        try {
            $tenant = Client::findOrFail($tenantId);

            // Get unpaid or partially paid invoices for this tenant
            $invoices = DebtorInvoice::where('debtor_id', $tenantId)
                ->where('debtor_type_id', 2) // Tenant invoices
                ->whereIn('current_status_id', [1, 6]) // Pending or Partially Paid
                ->with(['invoiceItems', 'status'])
                ->orderBy('date_time', 'desc')
                ->get();

            $mappedInvoices = $invoices->map(function ($invoice) {
                $totalAmount = $invoice->invoiceItems->sum('total_amount') ?? 0;
                $paidAmount = $invoice->payments()->sum('amount_allocated') ?? 0;
                $balance = $totalAmount - $paidAmount;

                return [
                    'id' => $invoice->id,
                    'reference_number' => $invoice->reference_number,
                    'date' => $invoice->date_time ? $invoice->date_time->format('Y-m-d') : '-',
                    'total_amount' => number_format($totalAmount, 2),
                    'total_amount_raw' => $totalAmount,
                    'paid_amount' => number_format($paidAmount, 2),
                    'paid_amount_raw' => $paidAmount,
                    'balance' => number_format($balance, 2),
                    'balance_raw' => $balance,
                    'status' => $invoice->status ? $invoice->status->status_name : '-'
                ];
            });

            return response()->json([
                'success' => true,
                'invoices' => $mappedInvoices
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tenant invoices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Allocate payment to invoice
     */
    public function allocatePayment(Request $request, $id)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|integer|exists:sys_pm_debtors_invoices,id',
                'amount_allocated' => 'required|numeric|min:0.01'
            ]);

            $paymentId = decrypt($id);
            $payment = TenantPayment::with('allocations')->findOrFail($paymentId);

            // Check available amount
            $totalAllocated = $payment->allocations->sum('amount_allocated') ?? 0;
            $available = $payment->amount - $totalAllocated;

            if ($request->amount_allocated > $available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation amount exceeds available balance. Available: ' . number_format($available, 2)
                ], 400);
            }

            // Check if invoice exists and is not fully paid
            $invoice = DebtorInvoice::with('invoiceItems')->findOrFail($request->invoice_id);
            $invoiceTotal = $invoice->invoiceItems->sum('total_amount') ?? 0;
            $invoicePaid = TenantPaymentAllocation::where('invoice_id', $invoice->id)->sum('amount_allocated') ?? 0;
            $invoiceBalance = $invoiceTotal - $invoicePaid;

            if ($request->amount_allocated > $invoiceBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation amount exceeds invoice balance. Invoice balance: ' . number_format($invoiceBalance, 2)
                ], 400);
            }

            DB::beginTransaction();

            // Create allocation
            $allocation = TenantPaymentAllocation::create([
                'parent_id' => $payment->id,
                'invoice_id' => $request->invoice_id,
                'amount_allocated' => $request->amount_allocated,
                'payment_priority' => $request->payment_priority ?? null,
                'allocation_id' => time() . rand(1000, 9999) // Generate unique allocation ID
            ]);

            // Update invoice status based on payment
            $newInvoicePaid = $invoicePaid + $request->amount_allocated;
            if ($newInvoicePaid >= $invoiceTotal) {
                $invoice->current_status_id = 9; // Fully Paid
            } else {
                $invoice->current_status_id = 6; // Partially Paid
            }
            $invoice->save();

            // Update payment status if fully allocated
            $newTotalAllocated = $totalAllocated + $request->amount_allocated;
            if ($newTotalAllocated >= $payment->amount) {
                $payment->current_status_id = 9; // Fully Allocated
                $payment->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment allocated successfully',
                'allocation' => [
                    'id' => $allocation->id,
                    'amount_allocated' => number_format($allocation->amount_allocated, 2)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error allocating payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error allocating payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete allocation
     */
    public function deleteAllocation($allocationId)
    {
        try {
            $allocation = TenantPaymentAllocation::findOrFail($allocationId);

            DB::beginTransaction();

            $payment = $allocation->payment;
            $invoice = $allocation->invoice;
            $amountAllocated = $allocation->amount_allocated;

            // Delete the allocation
            $allocation->delete();

            // Update payment status
            $totalAllocated = $payment->allocations()->sum('amount_allocated') ?? 0;
            if ($totalAllocated < $payment->amount) {
                $payment->current_status_id = 6; // Partially Allocated
                $payment->save();
            }

            // Update invoice status
            if ($invoice) {
                $invoiceTotal = $invoice->invoiceItems->sum('total_amount') ?? 0;
                $invoicePaid = TenantPaymentAllocation::where('invoice_id', $invoice->id)->sum('amount_allocated') ?? 0;

                if ($invoicePaid >= $invoiceTotal) {
                    $invoice->current_status_id = 9; // Fully Paid
                } elseif ($invoicePaid > 0) {
                    $invoice->current_status_id = 6; // Partially Paid
                } else {
                    $invoice->current_status_id = 1; // Pending
                }
                $invoice->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Allocation removed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting allocation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting allocation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display payment allocation page
     */
    public function allocatePage()
    {
        return view('property.payments.allocate-payment');
    }

    /**
     * Search payments for allocation (with unallocated balance)
     * If no search term provided, returns top 100 unallocated payments
     */
    public function searchPaymentsForAllocation(Request $request)
    {
        try {
            $searchTerm = $request->search;

            $query = TenantPayment::query();

            // Search by reference or receipt number if search term provided
            if ($searchTerm && strlen($searchTerm) > 0) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('reference', 'like', '%' . $searchTerm . '%')
                        ->orWhere('receipt_number', 'like', '%' . $searchTerm . '%');
                });
            }

            // Only get payments with unallocated balance
            $query->whereRaw('amount > (SELECT COALESCE(SUM(amount_allocated), 0) FROM sys_pm_tenants_payments_87_repeat WHERE parent_id = sys_pm_tenants_payments.id)');

            // Return top 100 for initial load or search results
            $query->orderBy('payment_date', 'desc')->limit(100);

            $payments = $query->get();

            $mappedPayments = $payments->map(function ($payment) {
                $tenant = $payment->tenant;
                $property = $payment->property;
                $allocations = $payment->allocations;

                $totalAllocated = $allocations->sum('amount_allocated') ?? 0;
                $unallocated = $payment->amount - $totalAllocated;

                return [
                    'id' => $payment->id,
                    'encrypted_id' => encrypt($payment->id),
                    'reference' => $payment->reference ?? '-',
                    'receipt_number' => $payment->receipt_number ?? '-',
                    'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : '-',
                    'tenant_id' => $payment->tenant_id,
                    'tenant_name' => $tenant ? trim(($tenant->sur_name ?? '') . ' ' . ($tenant->first_name ?? '') . ' ' . ($tenant->other_names ?? '')) : '-',
                    'property_name' => $property ? $property->property_name : '-',
                    'amount' => number_format($payment->amount, 2),
                    'amount_raw' => $payment->amount,
                    'allocated_amount' => number_format($totalAllocated, 2),
                    'allocated_amount_raw' => $totalAllocated,
                    'unallocated_amount' => number_format($unallocated, 2),
                    'unallocated_amount_raw' => $unallocated
                ];
            });

            return response()->json([
                'success' => true,
                'payments' => $mappedPayments
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching payments for allocation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search tenant invoices for allocation
     * If no search term provided, returns top 100 unpaid invoices for the tenant
     */
    public function searchTenantInvoicesForAllocation(Request $request)
    {
        try {
            $tenantId = $request->tenant_id;
            $searchTerm = $request->search;

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant ID is required'
                ], 400);
            }

            $query = DebtorInvoice::where('tenant_id', $tenantId)
                ->where('debtor_type_id', 2)
                ->whereIn('current_status_id', [1, 6]); // Pending or Partially Paid

            // Search by reference if search term provided
            if ($searchTerm && strlen($searchTerm) > 0) {
                $query->where('reference_number', 'like', '%' . $searchTerm . '%');
            }

            // Return top 100 for initial load or search results
            $query->with(['lineItems'])->orderBy('date_time', 'desc')->limit(100);

            $invoices = $query->get();

            $mappedInvoices = $invoices->map(function ($invoice) {
                $totalAmount = $invoice->lineItems->sum('total_amount') ?? 0;
                $paidAmount = TenantPaymentAllocation::where('invoice_id', $invoice->id)->sum('amount_allocated') ?? 0;
                $balance = $totalAmount - $paidAmount;

                // Only include invoices with balance > 0
                if ($balance <= 0) {
                    return null;
                }

                return [
                    'id' => $invoice->id,
                    'reference_number' => $invoice->reference_number,
                    'date' => $invoice->date_time ? $invoice->date_time->format('Y-m-d') : '-',
                    'due_date' => $invoice->date_due ? $invoice->date_due->format('Y-m-d') : '-',
                    'total_amount' => number_format($totalAmount, 2),
                    'total_amount_raw' => $totalAmount,
                    'paid_amount' => number_format($paidAmount, 2),
                    'paid_amount_raw' => $paidAmount,
                    'balance' => number_format($balance, 2),
                    'balance_raw' => $balance,
                    'status' => $invoice->current_status_id == 1 ? 'Pending' : 'Partially Paid'
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'invoices' => $mappedInvoices
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching tenant invoices', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store allocation from allocation page
     */
    public function storeAllocation(Request $request)
    {
        try {
            $request->validate([
                'payment_id' => 'required',
                'invoice_id' => 'required|integer|exists:sys_pm_debtors_invoices,id',
                'amount_allocated' => 'required|numeric|min:0.01'
            ]);

            $paymentId = decrypt($request->payment_id);
            $payment = TenantPayment::with('allocations')->findOrFail($paymentId);

            // Check available amount
            $totalAllocated = $payment->allocations->sum('amount_allocated') ?? 0;
            $available = $payment->amount - $totalAllocated;

            if ($request->amount_allocated > $available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation amount exceeds available balance. Available: ' . number_format($available, 2)
                ], 400);
            }

            // Check invoice balance
            $invoice = DebtorInvoice::with('lineItems')->findOrFail($request->invoice_id);
            $invoiceTotal = $invoice->lineItems->sum('total_amount') ?? 0;
            $invoicePaid = TenantPaymentAllocation::where('invoice_id', $invoice->id)->sum('amount_allocated') ?? 0;
            $invoiceBalance = $invoiceTotal - $invoicePaid;

            if ($request->amount_allocated > $invoiceBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocation amount exceeds invoice balance. Invoice balance: ' . number_format($invoiceBalance, 2)
                ], 400);
            }

            DB::beginTransaction();

            // Create allocation
            $allocation = TenantPaymentAllocation::create([
                'parent_id' => $payment->id,
                'invoice_id' => $request->invoice_id,
                'amount_allocated' => $request->amount_allocated,
                'payment_priority' => $request->payment_priority ?? null,
                'allocation_id' => time() . rand(1000, 9999)
            ]);

            // Update invoice status
            $newInvoicePaid = $invoicePaid + $request->amount_allocated;
            if ($newInvoicePaid >= $invoiceTotal) {
                $invoice->current_status_id = 9; // Fully Paid
            } else {
                $invoice->current_status_id = 6; // Partially Paid
            }
            $invoice->save();

            // Update payment status if fully allocated
            $newTotalAllocated = $totalAllocated + $request->amount_allocated;
            if ($newTotalAllocated >= $payment->amount) {
                $payment->current_status_id = 9; // Fully Allocated
                $payment->save();
            }

            DB::commit();

            Log::info('Payment allocation created', [
                'allocation_id' => $allocation->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $request->amount_allocated
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment allocated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating allocation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error allocating payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search allocated payments (AJAX)
     */
    public function searchAllocatedPayments(Request $request)
    {
        try {
            if (!$request->filled('property_id')) {
                return response()->json(['success' => false, 'message' => 'Please select a property'], 400);
            }

            if (!$request->filled('date_from') || !$request->filled('date_to')) {
                return response()->json(['success' => false, 'message' => 'Both dates are required'], 400);
            }

            // Validate 32-day limit
            $from = strtotime($request->date_from);
            $to = strtotime($request->date_to);
            if (($to - $from) > (32 * 86400)) {
                return response()->json(['success' => false, 'message' => 'Date range cannot exceed 32 days'], 400);
            }

            $query = TenantPaymentAllocated::with('tenant')
                ->where('property_id', $request->property_id)
                ->where('current_status_id', 1)
                ->whereDate('date_time', '>=', $request->date_from)
                ->whereDate('date_time', '<=', $request->date_to)
                ->orderBy('date_time', 'asc')
                ->orderBy('id', 'asc');

            $payments = $query->get();

            return response()->json([
                'success' => true,
                'payments' => $payments->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'date_time' => $p->date_time ? $p->date_time->format('d-m-Y H:i') : '-',
                        'payment_date' => $p->payment_date ? $p->payment_date->format('d-m-Y') : '-',
                        'tenant_name' => $p->tenant->names ?? '-',
                        'amount' => number_format($p->amount, 2),
                        'amount_raw' => $p->amount,
                        'trans_amount' => number_format($p->trans_amount, 2),
                        'trans_amount_raw' => $p->trans_amount,
                        'narration' => $p->narration ?? '-',
                        'pay_for' => $p->pay_for ?? '-',
                        'payment_mode' => $p->payment_mode ?? '-',
                        'payment_channel' => $p->payment_channel ?? '-',
                        'security_code' => $p->security_code ?? '-',
                        'trans_remarks' => $p->trans_remarks ?? '-',
                        'trans_type' => $p->trans_type ?? '-',
                        'payment_year' => $p->payment_year ?? '-',
                        'payment_month' => $p->payment_month ?? '-',
                    ];
                }),
                'count' => $payments->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching allocated payments: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to search payments'], 500);
        }
    }

    /**
     * Export allocated payments to Excel
     */
    public function exportAllocatedPayments(Request $request)
    {
        try {
            if (!$request->filled('property_id') || !$request->filled('date_from') || !$request->filled('date_to')) {
                return redirect()->back()->with('error', 'Property and date range are required');
            }

            $property = Property::find($request->property_id);
            $propertyName = $property->property_name ?? 'Unknown';

            $payments = TenantPaymentAllocated::with('tenant')
                ->where('property_id', $request->property_id)
                ->where('current_status_id', 1)
                ->whereDate('date_time', '>=', $request->date_from)
                ->whereDate('date_time', '<=', $request->date_to)
                ->orderBy('date_time', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $fileName = 'Allocated_Payments_' . str_replace(' ', '_', $propertyName) . '_' . $request->date_from . '_to_' . $request->date_to . '.xlsx';

            return Excel::download(new TenantPaymentsAllocatedExport($payments, $propertyName, $request->date_from, $request->date_to), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting allocated payments: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export payments');
        }
    }

    /**
     * Get properties for allocated payments Select2 (AJAX)
     */
    public function getPropertiesForPayments(Request $request)
    {
        try {
            $search = $request->input('q', '');

            $query = Property::select('id', 'property_name', 'reference_number');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('property_name', 'ILIKE', "%{$search}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$search}%");
                });
            }

            $properties = $query->orderBy('property_name')->limit(50)->get();

            return response()->json([
                'results' => $properties->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'text' => $p->property_name . ' (' . ($p->reference_number ?? '') . ')',
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading properties for payments: ' . $e->getMessage());
            return response()->json(['results' => []]);
        }
    }
}

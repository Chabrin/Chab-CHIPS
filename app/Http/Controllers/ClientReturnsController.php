<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ClientReturnsBatch;
use App\Jobs\UpdateAbstractThresholdsJob;

class ClientReturnsController extends Controller
{
    /**
     * Display the client returns batches listing page
     */
    public function index()
    {
        try {
            return view('property.returns.list');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns list page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load returns list page');
        }
    }

    /**
     * Get returns batches data for DataTable (AJAX)
     */
    public function getBatchesData(Request $request)
    {
        try {
            // Get logged-in user's ID
            $userId = auth()->id();

            // Check if user is top management
            $isTopManagement = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.name', 'top_management')
                ->exists();

            // Check if user is clerical officer or accountant
            $isRoleBasedApprover = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->whereIn('r.name', ['clerical_officers', 'accountants'])
                ->exists();

            $query = ClientReturnsBatch::with(['property', 'generatedBy'])
                ->active();

            // If user is top management, show all batches (no filtering)
            if (!$isTopManagement) {
                $query->where(function ($q) use ($userId, $isRoleBasedApprover) {
                    // Property-based access (Field Officer, Zone Manager, Zone Supervisor)
                    $q->whereHas('property', function ($propQuery) use ($userId) {
                        $propQuery->where(function ($query) use ($userId) {
                            $query->where('field_officer_id', $userId)
                                ->orWhere('zone_manager_id', $userId)
                                ->orWhere('zone_supervisor_id', $userId);
                        });
                    });

                    // If user is clerical officer or accountant, also show batches they're assigned to approve
                    if ($isRoleBasedApprover) {
                        $q->orWhereHas('approvalItem', function ($approvalQuery) use ($userId) {
                            $approvalQuery->whereHas('itemApprovers', function ($approverQuery) use ($userId) {
                                $approverQuery->where('approver_id', $userId);
                            });
                        });
                    }
                });
            }

            $query->orderByRaw('CASE WHEN approval_status = 101 THEN 0 ELSE 1 END') // In progress (101) first
                ->orderBy('generated_at', 'desc');

            // Apply filters if provided
            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($request->filled('approval_status')) {
                $query->where('approval_status', $request->approval_status);
            }

            if ($request->filled('batch_number')) {
                $query->where('batch_number', 'LIKE', '%' . $request->batch_number . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('generated_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('generated_at', '<=', $request->date_to);
            }

            $batches = $query->get();

            return response()->json([
                'success' => true,
                'data' => $batches->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'encrypted_id' => encrypt($batch->id),
                        'batch_number' => $batch->batch_number,
                        'batch_id' => $batch->batch_id,
                        'property_name' => $batch->property->property_name ?? 'N/A',
                        'property_id' => $batch->property_id,
                        'encrypted_property_id' => $batch->property_id ? encrypt($batch->property_id) : null,
                        'returns_count' => $batch->returns_count,
                        'total_trans_amount' => number_format($batch->total_trans_amount, 2),
                        'total_comm_amount' => number_format($batch->total_comm_amount, 2),
                        'total_payable_amount' => number_format($batch->total_payable_amount, 2),
                        'approval_status' => $batch->approval_status,
                        'approval_status_name' => $batch->approval_status_name,
                        'approval_status_badge' => $batch->approval_status_badge,
                        'generated_by_name' => $batch->generatedBy->name ?? 'N/A',
                        'generated_at' => $batch->generated_at ? $batch->generated_at->format('d-m-Y H:i') : 'N/A',
                        'approved_by_name' => $batch->approvedBy->name ?? '-',
                        'approved_at' => $batch->approved_at ? $batch->approved_at->format('d-m-Y H:i') : '-',
                        'remarks' => $batch->remarks ?? '-'
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching returns batches data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch returns batches data'
            ], 500);
        }
    }

    /**
     * Get batch details (AJAX)
     */
    public function getBatchDetails($id)
    {
        try {
            $batch = ClientReturnsBatch::with(['property', 'generatedBy', 'approvedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'batch' => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'batch_id' => $batch->batch_id,
                    'property_name' => $batch->property->property_name ?? 'N/A',
                    'property_ref' => $batch->property->reference_number ?? 'N/A',
                    'returns_count' => $batch->returns_count,
                    'total_trans_amount' => number_format($batch->total_trans_amount, 2),
                    'total_comm_amount' => number_format($batch->total_comm_amount, 2),
                    'total_payable_amount' => number_format($batch->total_payable_amount, 2),
                    'approval_status' => $batch->approval_status,
                    'approval_status_name' => $batch->approval_status_name,
                    'generated_by_name' => $batch->generatedBy->name ?? 'N/A',
                    'generated_at' => $batch->generated_at ? $batch->generated_at->format('d M Y, H:i') : 'N/A',
                    'approved_by_name' => $batch->approvedBy->name ?? '-',
                    'approved_at' => $batch->approved_at ? $batch->approved_at->format('d M Y, H:i') : '-',
                    'remarks' => $batch->remarks ?? '-'
                ]
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching batch details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch batch details'
            ], 500);
        }
    }

    /**
     * Show batch details page
     */
    public function showBatchDetails($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::with(['property', 'generatedBy'])->findOrFail($id);
            return view('property.returns.details', compact('batch'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading batch details page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load batch details page');
        }
    }

    /**
     * Get batch returns data for DataTable (AJAX)
     */
    public function getBatchReturnsData($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $batch = ClientReturnsBatch::findOrFail($id);

            $returns = DB::table('sys_pm_clients_returns as cr')
                ->join('sys_pm_clients as t', function ($join) {
                    $join->on('t.id', '=', 'cr.tenant_id')
                        ->where('t.type_id', '=', 2);
                })
                ->join('sys_pm_units as u', 'u.id', '=', 'cr.unit_id')
                ->join('sys_pm_debtors_invoices as i', 'i.id', '=', 'cr.invoice_id')
                ->where('cr.batch_id', $batch->batch_id)
                ->where('cr.property_id', $batch->property_id) // Filter by property_id
                ->select([
                    'cr.id',
                    'cr.ref_number',
                    't.names as tenant_name',
                    'u.unit_code',
                    'cr.trans_amount',
                    'cr.comm_amount',
                    'cr.vat_amount',
                    'cr.payable_amount',
                    'cr.abstract_threshold',
                    'cr.approval_status',
                    'cr.current_status_id',
                    'i.lease_id'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $returns->map(function ($return) {
                    return [
                        'id' => $return->id,
                        'ref_number' => $return->ref_number,
                        'tenant_name' => $return->tenant_name,
                        'unit_code' => $return->unit_code,
                        'trans_amount' => number_format($return->trans_amount, 2),
                        'comm_amount' => number_format($return->comm_amount, 2),
                        'vat_amount' => number_format($return->vat_amount, 2),
                        'payable_amount' => number_format($return->payable_amount, 2),
                        'abstract_threshold' => $return->abstract_threshold,
                        'approval_status' => $return->approval_status,
                        'current_status_id' => $return->current_status_id,
                        'lease_id' => $return->lease_id,
                        'id' => $return->id
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching batch returns data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch batch returns data'
            ], 500);
        }
    }

    /**
     * Get invoice history for a lease (AJAX)
     */
    public function getInvoiceHistory(Request $request, $leaseId)
    {
        try {
            $query = DB::table('sys_pm_debtors_invoices as spdi')
                ->join('sys_set_invoiceable_items as ssii', 'spdi.item_type_id', '=', 'ssii.id')
                ->where('spdi.lease_id', $leaseId)
                //->where('spdi.current_status_id', 1)
                ->orderBy('spdi.date_due', 'asc')
                ->select([
                    'spdi.id as invoice_id',
                    'ssii.item_name',
                    'ssii.advance_payable',
                    DB::raw('DATE_FORMAT(spdi.date_due, "%d %b %Y") as date_due'),
                    'spdi.commisionable_amount',
                    'spdi.payable_amount',
                    'spdi.current_balance'
                ]);

            $invoices = $query->get();

            // Log query details
            Log::info('Invoice History Query', [
                'lease_id' => $leaseId,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $invoices->count(),
                'data' => $invoices->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching invoice history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice history'
            ], 500);
        }
    }

    /**
     * Get properties for dropdown (AJAX)
     */
    public function getProperties(Request $request)
    {
        try {
            $search = $request->search;

            $properties = Property::select(['id', 'property_name', 'reference_number', 'description'])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('property_name', 'LIKE', "%{$search}%")
                            ->orWhere('description', 'LIKE', "%{$search}%")
                            ->orWhere('reference_number', 'LIKE', "%{$search}%");
                    });
                })
                ->get();

            return response()->json([
                'success' => true,
                'properties' => $properties
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching properties: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch properties'
            ], 500);
        }
    }

    /**
     * Show the returns generation page
     */
    public function showGenerate()
    {
        try {
            return view('property.returns.generate-returns');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading returns generation page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load returns generation page');
        }
    }
    /**
     * Process returns generation
     */
    public function processGenerate(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'property_selection' => 'required|in:all,one',
                'property_id' => 'required_if:property_selection,one|nullable|integer|exists:sys_pm_properties,id',
                'returns_month' => 'required|date_format:Y-m'
            ]);

            $propertySelection = $request->property_selection;
            $propertyId = $request->property_id;
            $returnsMonth = $request->returns_month; // Format: YYYY-MM
            $batchDate = str_replace('-', '', $returnsMonth); // Format: YYYYMM
            $generationDate = now();
            $generatedBy = Auth::id();

            // Calculate first day of return month for return_date and trans_date_time
            $returnDate = $returnsMonth . '-01'; // Format: YYYY-MM-01

            // Pre-generate batch_id using Unix timestamp for uniqueness
            $batchId = $batchDate . '-' . time(); // Format: YYYYMM-unixTimestamp

            // Start transaction
            DB::beginTransaction();

            // ═══════════════════════════════════════════════════════════════════
            // LOG: Check what invoices exist for the period
            // ═══════════════════════════════════════════════════════════════════
            $invoiceCheckQuery = DB::table('sys_pm_debtors_invoices as i')
                ->where(DB::raw("DATE_FORMAT(i.date_due, '%Y-%m')"), $returnsMonth)
                ->where('i.current_status_id', 1)
                ->where('i.debtor_type_id', 2);

            if ($propertySelection === 'one') {
                $invoiceCheckQuery->where('i.property_id', $propertyId);

                // Debug: Check if property exists and has any invoices at all
                $propertyExists = DB::table('sys_pm_properties')->where('id', $propertyId)->exists();
                $totalInvoicesForProperty = DB::table('sys_pm_debtors_invoices')->where('property_id', $propertyId)->count();

                // Show available months with invoices for this property
                $availableMonths = DB::table('sys_pm_debtors_invoices')
                    ->where('property_id', $propertyId)
                    ->where('current_status_id', 1)
                    ->where('debtor_type_id', 2)
                    ->whereNotNull('date_due')
                    ->selectRaw("DATE_FORMAT(date_due, '%Y-%m') as invoice_month, COUNT(*) as count")
                    ->groupBy('invoice_month')
                    ->orderBy('invoice_month', 'desc')
                    ->limit(12)
                    ->get();

                Log::info("Property Debug", [
                    'property_id' => $propertyId,
                    'property_id_type' => gettype($propertyId),
                    'property_exists' => $propertyExists,
                    'total_invoices_all_time' => $totalInvoicesForProperty,
                    'available_months_with_invoices' => $availableMonths->map(function ($m) {
                        return ['month' => $m->invoice_month, 'invoices' => $m->count];
                    })->toArray(),
                    'sql' => $invoiceCheckQuery->toSql(),
                    'bindings' => $invoiceCheckQuery->getBindings()
                ]);
            }

            $totalInvoices = $invoiceCheckQuery->count();
            Log::info("=== RETURN GENERATION DEBUG ===", [
                'returns_month' => $returnsMonth,
                'property_selection' => $propertySelection,
                'property_id' => $propertyId,
                'total_invoices_for_period' => $totalInvoices
            ]);

            // Check how many already have returns
            $invoicesWithReturnsQuery = DB::table('sys_pm_debtors_invoices as i')
                ->where(DB::raw("DATE_FORMAT(i.date_due, '%Y-%m')"), $returnsMonth)
                ->where('i.current_status_id', 1)
                ->where('i.debtor_type_id', 2)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('sys_pm_clients_returns as cr')
                        ->whereColumn('cr.invoice_id', 'i.id');
                });

            if ($propertySelection === 'one') {
                $invoicesWithReturnsQuery->where('i.property_id', $propertyId);
            }

            $invoicesWithReturns = $invoicesWithReturnsQuery->count();

            Log::info("Invoice Analysis", [
                'invoices_with_returns' => $invoicesWithReturns,
                'invoices_without_returns' => $totalInvoices - $invoicesWithReturns
            ]);

            // Check validation failures
            $validationChecksQuery = DB::table('sys_pm_debtors_invoices as i')
                ->where(DB::raw("DATE_FORMAT(i.date_due, '%Y-%m')"), $returnsMonth)
                ->where('i.current_status_id', 1)
                ->where('i.debtor_type_id', 2);

            if ($propertySelection === 'one') {
                $validationChecksQuery->where('i.property_id', $propertyId);
            }

            $validationChecks = $validationChecksQuery
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN i.property_id IS NULL THEN 1 ELSE 0 END) as null_property'),
                    DB::raw('SUM(CASE WHEN i.unit_id IS NULL THEN 1 ELSE 0 END) as null_unit'),
                    DB::raw('SUM(CASE WHEN i.lease_id IS NULL THEN 1 ELSE 0 END) as null_lease'),
                    DB::raw('SUM(CASE WHEN i.tenant_id IS NULL THEN 1 ELSE 0 END) as null_tenant'),
                    DB::raw('SUM(CASE WHEN EXISTS (SELECT 1 FROM sys_pm_clients_returns cr WHERE cr.invoice_id = i.id) THEN 1 ELSE 0 END) as has_return')
                ])
                ->first();

            Log::info("Validation Check Results", [
                'total_invoices' => $validationChecks->total,
                'missing_property' => $validationChecks->null_property,
                'missing_unit' => $validationChecks->null_unit,
                'missing_lease' => $validationChecks->null_lease,
                'missing_tenant' => $validationChecks->null_tenant,
                'already_have_return' => $validationChecks->has_return
            ]);

            // ═══════════════════════════════════════════════════════════════════
            // DELETE EXISTING RETURNS AND LANDLORD LEDGERS FOR THIS MONTH
            // ═══════════════════════════════════════════════════════════════════
            // This prevents duplicates when regenerating returns for the same month
            // Only deletes returns that are NOT approved (approval_status IS NULL)
            // Does NOT delete if approval_status = 1 (approved) or 101 (approval in progress)

            // Check if approval_status column exists
            $approvalColumnExists = DB::getSchemaBuilder()->hasColumn('sys_pm_clients_returns', 'approval_status');

            Log::info("Checking for existing returns to delete", [
                'return_date' => $returnDate,
                'approval_column_exists' => $approvalColumnExists,
                'property_selection' => $propertySelection,
                'property_id' => $propertyId
            ]);

            // Delete landlord ledger entries first using subquery with JOIN
            $ledgerDeleteSql = "DELETE lal FROM sys_pm_landlord_accounts_transaction lal
                INNER JOIN sys_pm_clients_returns cr ON cr.invoice_id = lal.invoice_id
                WHERE lal.transaction_type_id = 13
                  AND cr.return_date = ?";

            if ($approvalColumnExists) {
                $ledgerDeleteSql .= " AND (cr.approval_status NOT IN (1, 101) OR cr.approval_status IS NULL)";
            }

            if ($propertySelection === 'one') {
                $ledgerDeleteSql .= " AND cr.property_id = ?";
            }

            $ledgerParams = [$returnDate];
            if ($propertySelection === 'one') {
                $ledgerParams[] = $propertyId;
            }

            $deletedLedgers = DB::delete($ledgerDeleteSql, $ledgerParams);

            // Delete returns directly with conditions
            $returnsDeleteSql = "DELETE FROM sys_pm_clients_returns
                WHERE return_date = ?";

            if ($approvalColumnExists) {
                $returnsDeleteSql .= " AND (approval_status NOT IN (1, 101) OR approval_status IS NULL)";
            }

            if ($propertySelection === 'one') {
                $returnsDeleteSql .= " AND property_id = ?";
            }

            $returnsParams = [$returnDate];
            if ($propertySelection === 'one') {
                $returnsParams[] = $propertyId;
            }

            $deletedReturns = DB::delete($returnsDeleteSql, $returnsParams);

            // Delete batch records for this month (batch_number is the return month like '2025-11')
            $batchDeleteSql = "DELETE FROM sys_pm_clients_returns_batches WHERE batch_number = ?";
            $batchParams = [$returnsMonth];

            if ($propertySelection === 'one') {
                $batchDeleteSql .= " AND property_id = ?";
                $batchParams[] = $propertyId;
            }

            $deletedBatches = DB::delete($batchDeleteSql, $batchParams);

            Log::info("✓ Cleared existing returns for regeneration", [
                'deleted_returns' => $deletedReturns,
                'deleted_ledgers' => $deletedLedgers,
                'deleted_batches' => $deletedBatches,
                'return_date' => $returnDate,
                'approval_filter_used' => $approvalColumnExists
            ]);

            // Build the INSERT query
            $query = "INSERT INTO sys_pm_clients_returns (
                date_time,
                ref_number,
                invoice_id,
                return_date,
                narration,
                created_by,
                zone_id,
                client_id,
                property_id,
                unit_id,
                lease_id,
                tenant_id,
                sla_id,
                trans_type_id,
                fo_id,
                zone_supervisor_id,
                zone_manager_id,
                unit_status_id,
                threshold_percentage,
                current_status_id,
                payable_status_id,
                trans_amount,
                commissionable_amount,
                comm_amount,
                comm_vat,
                payable_amount,
                comm_perc,
                vat_amount,
                vat_per,
                commisionable,
                batch_id
            )
            SELECT 
                ? AS date_time,
                i.reference_number AS ref_number,
                i.id AS invoice_id,
                ? AS return_date,
                i.narration AS narration,
                ? AS created_by,
                i.zone_id,
                i.client_id,
                i.property_id,
                i.unit_id,
                i.lease_id,
                i.tenant_id,
                i.sla_id,
                i.item_type_id AS trans_type_id,
                i.field_officer_id AS fo_id,
                i.zone_supervisor_id,
                i.zone_manager_id,
                i.occupancy_status_id AS unit_status_id,
                0 AS threshold_percentage,
                1 AS current_status_id,
                0 AS payable_status_id,
                i.total_amount AS trans_amount,
                i.commisionable_amount AS commissionable_amount,
                i.comm_ammount AS comm_amount,
                i.vat_on_commission AS comm_vat,
                i.payable_amount AS payable_amount,
                i.comm_per AS comm_perc,
                i.vat_ammount AS vat_amount,
                i.vat_per AS vat_per,
                CASE 
                    WHEN i.comm_ammount > 0 THEN 'Yes'
                    ELSE 'No'
                END AS commisionable,
                ? AS batch_id
            FROM sys_pm_debtors_invoices i
            WHERE DATE_FORMAT(i.date_due, '%Y-%m') = ?
              AND i.current_status_id = 1
              AND i.debtor_type_id = 2";

            // Add property filter if specific property is selected
            if ($propertySelection === 'one') {
                $query .= " AND i.property_id = ?";
            }

            $query .= "
              AND i.property_id IS NOT NULL
              AND i.unit_id IS NOT NULL
              AND i.lease_id IS NOT NULL
              AND i.tenant_id IS NOT NULL";

            Log::info("Executing return generation INSERT query", [
                'returns_month' => $returnsMonth,
                'batch_id' => $batchId,
                'property_selection' => $propertySelection
            ]);

            // Execute the insert query with appropriate parameters
            $params = [
                $generationDate,    // date_time
                $returnDate,        // return_date (1st of month)
                $generatedBy,       // created_by
                $batchId,           // batch_id (pre-generated)
                $returnsMonth       // DATE_FORMAT comparison (YYYY-MM format)
            ];

            if ($propertySelection === 'one') {
                $params[] = $propertyId; // property_id filter
            }

            DB::insert($query, $params);

            // Get count of generated returns using exact batch_id
            $affectedRows = DB::table('sys_pm_clients_returns')
                ->where('batch_id', $batchId)
                ->count();

            // ═══════════════════════════════════════════════════════════════════
            // CREATE LANDLORD LEDGER ENTRIES FOR GENERATED RETURNS
            // ═══════════════════════════════════════════════════════════════════
            // Per INVOICE_PAYMENT_TO_LANDLORD_LEDGER_FLOW.md:
            // - Initial state: payable_status = 0 (EXCLUDED from balance)
            // - Will be updated to 1 when invoice payment threshold is met
            // ═══════════════════════════════════════════════════════════════════

            if ($affectedRows > 0) {
                // Use INSERT...SELECT for better performance with large datasets
                $landlordLedgerSql = "INSERT INTO sys_pm_landlord_accounts_transaction (
                    date_time,
                    trans_date_time,
                    client_id,
                    property_id,
                    invoice_id,
                    origin_id,
                    transaction_type_id,
                    narration,
                    amount_type,
                    amount,
                    payable_status,
                    current_status_id,
                    lease_id,
                    tenant_id,
                    unit_id,
                    sla_id,
                    zone_id
                )
                SELECT 
                    ? AS date_time,
                    ? AS trans_date_time,
                    cr.client_id,
                    cr.property_id,
                    cr.invoice_id,
                    cr.id AS origin_id,
                    13 AS transaction_type_id,
                    CONCAT('Client Return - ', cr.ref_number) AS narration,
                    'Credit' AS amount_type,
                    cr.payable_amount AS amount,
                    0 AS payable_status,
                    1 AS current_status_id,
                    cr.lease_id,
                    cr.tenant_id,
                    cr.unit_id,
                    cr.sla_id,
                    cr.zone_id
                FROM sys_pm_clients_returns cr
                WHERE cr.batch_id = ?";

                DB::insert($landlordLedgerSql, [
                    $generationDate,
                    $returnDate,
                    $batchId
                ]);

                Log::info("✓ Created landlord ledger entries via INSERT...SELECT", [
                    'returns_count' => $affectedRows,
                    'batch_id' => $batchId
                ]);
            }

            // Check if any returns were generated
            if ($affectedRows == 0) {
                DB::rollBack();

                // Determine why no returns were generated
                if ($totalInvoices == 0) {
                    $message = 'No approved invoices found for the selected period and property.';

                    // For single property, show available months
                    if ($propertySelection === 'one' && isset($availableMonths) && $availableMonths->isNotEmpty()) {
                        $monthsList = $availableMonths->map(function ($m) {
                            return $m->invoice_month . ' (' . $m->count . ' invoices)';
                        })->join(', ');
                        $message .= ' Available periods: ' . $monthsList;
                    }
                } elseif ($invoicesWithReturns == $totalInvoices) {
                    $message = 'All approved invoices for the selected period already have returns.';
                } else {
                    $message = 'No returns generated. Invoices may be missing required fields (property, unit, lease, or tenant).';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 422);
            }

            // Delete existing batch records for this period (to avoid duplicates on regeneration)
            $deleteBatchQuery = DB::table('sys_pm_clients_returns_batches')
                ->where('batch_number', $returnsMonth);

            if ($propertySelection === 'one') {
                $deleteBatchQuery->where('property_id', $propertyId);
            }

            // Get batch IDs before deletion (for approval cleanup)
            $batchIdsToDelete = (clone $deleteBatchQuery)->pluck('id')->toArray();

            // Delete associated approval records first
            if (!empty($batchIdsToDelete)) {
                // Delete approval actions
                DB::table('cb_approval_actions')
                    ->whereIn('approval_item_id', function ($query) use ($batchIdsToDelete) {
                        $query->select('id')
                            ->from('cb_approval_items')
                            ->where('item_type', 'returns')
                            ->whereIn('item_id', $batchIdsToDelete);
                    })
                    ->delete();

                // Delete item approvers
                DB::table('cb_item_approvers')
                    ->whereIn('approval_item_id', function ($query) use ($batchIdsToDelete) {
                        $query->select('id')
                            ->from('cb_approval_items')
                            ->where('item_type', 'returns')
                            ->whereIn('item_id', $batchIdsToDelete);
                    })
                    ->delete();

                // Delete approval items
                $deletedApprovals = DB::table('cb_approval_items')
                    ->where('item_type', 'returns')
                    ->whereIn('item_id', $batchIdsToDelete)
                    ->delete();

                Log::info("✓ Deleted old approval records for regeneration", [
                    'deleted_approvals' => $deletedApprovals,
                    'batch_ids' => $batchIdsToDelete
                ]);
            }

            $deletedBatches = $deleteBatchQuery->delete();

            Log::info("Deleted existing batch records", [
                'deleted_count' => $deletedBatches,
                'batch_number' => $returnsMonth
            ]);

            // Get summary data grouped by property for batch creation
            $batchSummaries = DB::table('sys_pm_clients_returns')
                ->where('batch_id', $batchId)
                ->selectRaw('
                    property_id,
                    batch_id,
                    COUNT(*) as returns_count,
                    SUM(trans_amount) as total_trans_amount,
                    SUM(comm_amount) as total_comm_amount,
                    SUM(payable_amount) as total_payable_amount
                ')
                ->groupBy('property_id', 'batch_id')
                ->get();

            // Create batch records for each property and initialize approval workflow
            foreach ($batchSummaries as $summary) {
                $batch = ClientReturnsBatch::create([
                    'batch_number' => $returnsMonth,
                    'batch_id' => $summary->batch_id,
                    'property_id' => $summary->property_id,
                    'returns_count' => $summary->returns_count ?? 0,
                    'total_trans_amount' => $summary->total_trans_amount ?? 0,
                    'total_comm_amount' => $summary->total_comm_amount ?? 0,
                    'total_payable_amount' => $summary->total_payable_amount ?? 0,
                    'approval_status' => 0, // Pending
                    'generated_by' => $generatedBy,
                    'generated_at' => $generationDate
                ]);

                // ═══════════════════════════════════════════════════════════════════
                // INITIALIZE APPROVAL WORKFLOW
                // ═══════════════════════════════════════════════════════════════════
                // Get field_officer_id from property
                $property = DB::table('sys_pm_properties')
                    ->where('id', $summary->property_id)
                    ->first(['field_officer_id']);

                if ($property && $property->field_officer_id) {
                    // Create approval item record
                    $approvalItem = DB::table('cb_approval_items')->insertGetId([
                        'item_type' => 'returns',
                        'item_id' => $batch->id,
                        'item_reference' => $summary->batch_id,
                        'status' => 'Pending',
                        'current_level' => 1,
                        'total_levels' => 4,
                        'initiated_by' => $generatedBy,
                        'initiated_at' => $generationDate,
                        'created_at' => $generationDate,
                        'updated_at' => $generationDate
                    ]);

                    // Insert Field Officer as first level approver
                    DB::table('cb_item_approvers')->insert([
                        'approval_item_id' => $approvalItem,
                        'approver_id' => $property->field_officer_id,
                        'approver_level' => 1,
                        'is_optional' => 0,
                        'approval_status' => 'Pending',
                        'assigned_at' => $generationDate,
                        'created_at' => $generationDate,
                        'updated_at' => $generationDate
                    ]);

                    // Log initial approval action
                    DB::table('cb_approval_actions')->insert([
                        'item_approver_id' => DB::getPdo()->lastInsertId(),
                        'approval_item_id' => $approvalItem,
                        'actor_id' => $generatedBy,
                        'action' => 'Initiated',
                        'comments' => 'Returns generated and assigned to Field Officer for approval',
                        'action_timestamp' => $generationDate,
                        'ip_address' => request()->ip(),
                        'sequence_number' => 1,
                        'is_current' => 1,
                        'created_at' => $generationDate,
                        'updated_at' => $generationDate
                    ]);
                } else {
                    Log::warning("Field Officer not found for property", [
                        'property_id' => $summary->property_id,
                        'batch_id' => $batch->id
                    ]);
                }
            }

            DB::commit();

            // ═══════════════════════════════════════════════════════════════════
            // DISPATCH BACKGROUND JOB TO UPDATE ABSTRACT THRESHOLDS
            // ═══════════════════════════════════════════════════════════════════
            // Run as queued job to avoid blocking response and prevent timeouts
            // This complex query can take minutes with 6000+ records
            // ═══════════════════════════════════════════════════════════════════

            UpdateAbstractThresholdsJob::dispatch($batchId, $returnsMonth);

            Log::info("✓ Dispatched abstract threshold update job", [
                'batch_id' => $batchId,
                'returns_month' => $returnsMonth
            ]);

            $message = $propertySelection === 'all'
                ? "Client returns generated successfully for {$batchSummaries->count()} properties"
                : 'Client returns generated successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $affectedRows,
                'properties_count' => $batchSummaries->count()
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating returns: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate returns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display processed returns listing page
     */
    public function processedReturns()
    {
        try {
            return view('property.processed-returns.index');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading processed returns page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load processed returns page');
        }
    }

    /**
     * Get processed returns data for DataTable (AJAX)
     */
    public function getProcessedReturnsData(Request $request)
    {
        try {
            $query = DB::table('sys_reports_returns_properties as rp')
                ->select([
                    'rp.id as report_id',
                    'rp.date_time',
                    'rp.period_date',
                    'rp.batch_id',
                    'rp.master_report_id',
                    DB::raw("DATE_FORMAT(rp.period_date, '%b %Y') as period_name"),
                    DB::raw("DATE_FORMAT(rp.period_date, '%Y-%m') as period_month"),
                    'period.period_start',
                    'period.period_end',
                    'c.id as client_id',
                    DB::raw("CONCAT_WS(' ', c.names, c.second_name, c.last_name) as client_name"),
                    'c.email_address as client_email',
                    'c.mobile_number as client_phone',
                    'c.pin_number as client_kra_pin',
                    'p.id as property_id',
                    DB::raw("CONCAT_WS(' - ', p.reference_number, p.property_name) as property"),
                    'p.reference_number as property_code',
                    'p.property_name',
                    'p.description as property_location',
                    'sla.reference_number as sla_reference',
                    'sla.commission_percentage as commission_rate',
                    'sla.vat_per as vat_rate',
                    'rt.type_name as return_type',
                    'rp.total_gross as total_gross_amount',
                    'rp.total_commissionable',
                    'rp.total_commission',
                    'rp.vat_on_commission',
                    'rp.total_units_vat as total_vat',
                    'rp.total_recoveries',
                    'rp.total_net_amount',
                    'rp.other_incomes',
                    'rp.other_commissionable_income',
                    'rp.total_commissionable_less_vat',
                    'rp.bal_after_comm',
                    'rp.bal_after_vat_on_comm',
                    'rp.total_income',
                    'ws.status_name as current_status',
                    'ws.id as status_id',
                    'staff.full_names as field_officer',
                    'rp.zone_id',
                    'rp.batch_config_id',
                    'rp.parent_batch_config_id',
                    'rp.voucher_id',
                    'rp.voucher_date',
                    'rp.processed_date',
                    'rp.current_status_id'
                ])
                ->join('sys_pm_clients as c', 'c.id', '=', 'rp.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rp.property_id')
                ->leftJoin('sys_gl_periods as period', 'period.id', '=', 'rp.period_id')
                ->leftJoin('sys_set_workflow_statuses as ws', 'ws.id', '=', 'rp.current_status_id')
                ->leftJoin('sys_pm_clients_returns_types as rt', 'rt.id', '=', 'rp.return_type_id')
                ->leftJoin('sys_pm_service_agreements as sla', DB::raw('CAST(sla.id AS CHAR) COLLATE utf8mb4_unicode_ci'), '=', 'rp.sla_id')
                ->leftJoin('sys_staff as staff', 'staff.id', '=', 'rp.field_officer_id')
                ->whereIn('rp.current_status_id', [5, 17])
                ->orderBy('rp.period_date', 'desc')
                ->orderBy('c.names', 'asc')
                ->orderBy(DB::raw("COALESCE(p.property_name, 'ZZZZ')"), 'asc');

            // Apply filters
            if ($request->filled('period_id')) {
                $query->where('rp.period_id', $request->period_id);
            }

            if ($request->filled('client_id')) {
                $query->where('rp.client_id', $request->client_id);
            }

            if ($request->filled('property_id')) {
                $query->where('rp.property_id', $request->property_id);
            }

            if ($request->filled('status')) {
                $query->where('rp.current_status_id', $request->status);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('rp.date_time', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('rp.date_time', '<=', $request->date_to);
            }

            $returns = $query->get();

            // Status mapping
            $statusMap = [
                1 => 'Pending',
                5 => 'Approved',
                17 => 'Processed',
            ];

            return response()->json([
                'success' => true,
                'data' => $returns->map(function ($return) use ($statusMap) {
                    return [
                        'id' => $return->report_id,
                        'encrypted_id' => encrypt($return->report_id),
                        'date' => date('d-M-Y', strtotime($return->date_time)),
                        'period' => $return->period_name,
                        'client_name' => $return->client_name ?? '-',
                        'property' => $return->property ?? '-',
                        'sla_reference' => $return->sla_reference ?? '-',
                        'return_type' => $return->return_type ?? '-',
                        'gross_amount' => number_format($return->total_gross_amount ?? 0, 2),
                        'commission' => number_format($return->total_commission ?? 0, 2),
                        'vat' => number_format($return->vat_on_commission ?? 0, 2),
                        'recoveries' => number_format($return->total_recoveries ?? 0, 2),
                        'net_amount' => number_format($return->total_net_amount ?? 0, 2),
                        'status' => $statusMap[$return->current_status_id] ?? 'Unknown',
                        'batch_reference' => '-',
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching processed returns data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch processed returns data'
            ], 500);
        }
    }

    /**
     * Show return details page
     */
    public function showReturnDetails($id)
    {
        try {
            $id = decrypt($id);

            $return = DB::table('sys_reports_returns_properties as rp')
                ->select([
                    'rp.*',
                    DB::raw("DATE_FORMAT(rp.period_date, '%b %Y') as period_name"),
                    'c.names as client_name',
                    'c.email_address as client_email',
                    'c.mobile_number as client_phone',
                    'c.pin_number as client_kra_pin',
                    DB::raw("CONCAT_WS(' - ', p.reference_number, p.property_name) as property"),
                    'p.reference_number as property_code',
                    'p.property_name',
                    'p.description as property_location',
                    'sla.reference_number as sla_reference',
                    'sla.commission_percentage as commission_rate',
                    'sla.vat_per as vat_rate',
                    'rt.type_name as return_type',
                    'staff.full_names as field_officer',
                    'ws.status_name as current_status',
                ])
                ->join('sys_pm_clients as c', 'c.id', '=', 'rp.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rp.property_id')
                ->leftJoin('sys_pm_service_agreements as sla', DB::raw('CAST(sla.id AS CHAR) COLLATE utf8mb4_unicode_ci'), '=', 'rp.sla_id')
                ->leftJoin('sys_pm_clients_returns_types as rt', 'rt.id', '=', 'rp.return_type_id')
                ->leftJoin('sys_staff as staff', 'staff.id', '=', 'rp.field_officer_id')
                ->leftJoin('sys_set_workflow_statuses as ws', 'ws.id', '=', 'rp.current_status_id')
                ->where('rp.id', $id)
                ->first();

            if (!$return) {
                return redirect()->back()->with('error', 'Return not found');
            }

            return view('property.processed-returns.details', compact('return'));
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading return details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load return details');
        }
    }

    /**
     * Get unit details for a specific return
     */
    public function getReturnUnitsData($id)
    {
        try {
            $id = decrypt($id);

            $units = DB::table('sys_reports_returns_items as ri')
                ->select([
                    'ri.unit_code as room_no',
                    DB::raw("MAX(t.names) as tenant_name"),
                    DB::raw("MAX(l.ref_number) as lease_reference"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Unit Rent' THEN ri.trans_amount ELSE 0 END) as monthly_rent"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Service Charge' THEN ri.trans_amount ELSE 0 END) as service_charge"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Water Bill' THEN ri.trans_amount ELSE 0 END) as water_bill"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Electricity Bill' THEN ri.trans_amount ELSE 0 END) as elec_bill"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Rent Deposit' THEN ri.trans_amount ELSE 0 END) as rent_deposit"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Water Deposit' THEN ri.trans_amount ELSE 0 END) as water_deposit"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Electricity Deposit' THEN ri.trans_amount ELSE 0 END) as elec_deposit"),
                    DB::raw("SUM(ri.trans_amount) as total_charges"),
                    DB::raw("SUM(ri.vat_amount) as total_vat"),
                    DB::raw("SUM(ri.comm_amount) as total_commission"),
                    DB::raw("SUM(ri.comm_vat) as total_commission_vat"),
                    DB::raw("SUM(ri.payable_amount) as net_payable"),
                    DB::raw("COUNT(*) as charge_count"),
                    DB::raw("GROUP_CONCAT(DISTINCT ri.item_name ORDER BY ri.item_name SEPARATOR ', ') as charge_types")
                ])
                ->join('sys_pm_units as u', 'u.id', '=', 'ri.unit_id')
                ->leftJoin('sys_pm_lease_agreements as l', 'l.id', '=', 'ri.lease_id')
                ->leftJoin('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
                ->where('ri.property_report_id', $id)
                ->groupBy('ri.unit_code')
                ->orderBy('ri.unit_code', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $units->map(function ($unit) {
                    return [
                        'room_no' => $unit->room_no ?? '-',
                        'tenant_name' => $unit->tenant_name ?? '-',
                        'lease_reference' => $unit->lease_reference ?? '-',
                        'monthly_rent' => number_format($unit->monthly_rent ?? 0, 2),
                        'service_charge' => number_format($unit->service_charge ?? 0, 2),
                        'water_bill' => number_format($unit->water_bill ?? 0, 2),
                        'elec_bill' => number_format($unit->elec_bill ?? 0, 2),
                        'deposits' => number_format(($unit->rent_deposit ?? 0) + ($unit->water_deposit ?? 0) + ($unit->elec_deposit ?? 0), 2),
                        'total_charges' => number_format($unit->total_charges ?? 0, 2),
                        'commission' => number_format($unit->total_commission ?? 0, 2),
                        'vat' => number_format($unit->total_commission_vat ?? 0, 2),
                        'net_payable' => number_format($unit->net_payable ?? 0, 2),
                    ];
                })
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching return units data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch units data'
            ], 500);
        }
    }

    /**
     * Download PDF for a specific return
     */
    public function downloadPdf($id)
    {
        try {
            $id = decrypt($id);

            // Get return details
            $return = DB::table('sys_reports_returns_properties as rp')
                ->select([
                    'rp.*',
                    DB::raw("DATE_FORMAT(rp.period_date, '%b %Y') as period_name"),
                    'c.names as client_name',
                    'c.email_address as client_email',
                    'c.mobile_number as client_phone',
                    DB::raw("CONCAT_WS(' - ', p.reference_number, p.property_name) as property"),
                ])
                ->join('sys_pm_clients as c', 'c.id', '=', 'rp.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rp.property_id')
                ->where('rp.id', $id)
                ->first();

            if (!$return) {
                return response()->json(['success' => false, 'message' => 'Return not found'], 404);
            }

            // Get unit details
            $units = DB::table('sys_reports_returns_items as ri')
                ->select([
                    'ri.unit_code as room_no',
                    DB::raw("MAX(t.names) as tenant_name"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Unit Rent' THEN ri.trans_amount ELSE 0 END) as monthly_rent"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Service Charge' THEN ri.trans_amount ELSE 0 END) as service_charge"),
                    DB::raw("SUM(ri.trans_amount) as total_charges"),
                    DB::raw("SUM(ri.comm_amount) as total_commission"),
                    DB::raw("SUM(ri.comm_vat) as total_commission_vat"),
                    DB::raw("SUM(ri.payable_amount) as net_payable"),
                ])
                ->join('sys_pm_units as u', 'u.id', '=', 'ri.unit_id')
                ->leftJoin('sys_pm_lease_agreements as l', 'l.id', '=', 'ri.lease_id')
                ->leftJoin('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
                ->where('ri.property_report_id', $id)
                ->groupBy('ri.unit_code')
                ->orderBy('ri.unit_code', 'asc')
                ->get();

            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
            $pdf->SetCreator('CHIPS');
            $pdf->SetAuthor('Property Management System');
            $pdf->SetTitle('Property Return Report');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();

            // Header
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'PROPERTY RETURN REPORT', 0, 1, 'C');
            $pdf->Ln(3);

            // Return Info
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(30, 5, 'Client:', 0, 0);
            $pdf->Cell(100, 5, $return->client_name, 0, 0);
            $pdf->Cell(30, 5, 'Period:', 0, 0);
            $pdf->Cell(0, 5, $return->period_name, 0, 1);
            $pdf->Cell(30, 5, 'Property:', 0, 0);
            $pdf->Cell(0, 5, $return->property, 0, 1);
            $pdf->Ln(5);

            // Units breakdown
            if ($units->count() > 0) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'UNIT BREAKDOWN', 0, 1);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell(50, 6, 'Tenant', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Lease Ref', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Rent', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Service', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Water', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Elec', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Total', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Comm', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Comm VAT', 1, 0, 'C');
                $pdf->Cell(25, 6, 'Net', 1, 1, 'C');

                $pdf->SetFont('helvetica', '', 7);
                $unitStartY = $pdf->GetY();
                foreach ($units as $unit) {
                    $pdf->Cell(50, 5, substr($unit->tenant_name ?? '-', 0, 25), 1, 0);
                    $pdf->Cell(25, 5, substr($unit->lease_reference ?? '-', 0, 12), 1, 0);
                    $pdf->Cell(25, 5, number_format($unit->monthly_rent ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->service_charge ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->water_bill ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->elec_bill ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->total_charges ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->total_commission ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->total_commission_vat ?? 0, 2), 1, 0, 'R');
                    $pdf->Cell(25, 5, number_format($unit->net_payable ?? 0, 2), 1, 1, 'R');
                }
                $unitEndY = $pdf->GetY();

                // Financial Summary - positioned at bottom right
                $summaryX = 180;
                $summaryY = $unitStartY;
                $pdf->SetXY($summaryX, $summaryY);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, 'FINANCIAL SUMMARY', 0, 1);
                $pdf->SetXY($summaryX, $pdf->GetY());
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(50, 5, 'Gross Returns', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->total_gross ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->Cell(50, 5, 'Less: Commission', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->total_commission ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->Cell(50, 5, 'Less: VAT', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->vat_on_commission ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->Cell(50, 5, 'Less: Recoveries', 1, 0);
                $pdf->Cell(35, 5, 'KSh ' . number_format($return->total_recoveries ?? 0, 2), 1, 1, 'R');
                $pdf->SetX($summaryX);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 6, 'NET PAYABLE', 1, 0);
                $pdf->Cell(35, 6, 'KSh ' . number_format($return->total_net_amount ?? 0, 2), 1, 1, 'R');
            }

            return $pdf->Output('return_' . $id . '.pdf', 'D');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download Excel for a specific return
     */
    public function downloadExcel($id)
    {
        try {
            $id = decrypt($id);

            // Get return details
            $return = DB::table('sys_reports_returns_properties as rp')
                ->select([
                    'rp.*',
                    DB::raw("DATE_FORMAT(rp.period_date, '%b %Y') as period_name"),
                    DB::raw("CONCAT_WS(' ', c.names, c.second_name, c.last_name) as client_name"),
                    DB::raw("CONCAT_WS(' - ', p.reference_number, p.property_name) as property"),
                ])
                ->join('sys_pm_clients as c', 'c.id', '=', 'rp.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rp.property_id')
                ->where('rp.id', $id)
                ->first();

            if (!$return) {
                return response()->json(['success' => false, 'message' => 'Return not found'], 404);
            }

            // Get unit details
            $units = DB::table('sys_reports_returns_items as ri')
                ->select([
                    'ri.unit_code as room_no',
                    DB::raw("MAX(t.names) as tenant_name"),
                    DB::raw("MAX(l.ref_number) as lease_reference"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Unit Rent' THEN ri.trans_amount ELSE 0 END) as monthly_rent"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Service Charge' THEN ri.trans_amount ELSE 0 END) as service_charge"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Water Bill' THEN ri.trans_amount ELSE 0 END) as water_bill"),
                    DB::raw("SUM(CASE WHEN ri.item_name = 'Electricity Bill' THEN ri.trans_amount ELSE 0 END) as elec_bill"),
                    DB::raw("SUM(ri.trans_amount) as total_charges"),
                    DB::raw("SUM(ri.vat_amount) as total_vat"),
                    DB::raw("SUM(ri.comm_amount) as total_commission"),
                    DB::raw("SUM(ri.comm_vat) as total_commission_vat"),
                    DB::raw("SUM(ri.payable_amount) as net_payable"),
                ])
                ->join('sys_pm_units as u', 'u.id', '=', 'ri.unit_id')
                ->leftJoin('sys_pm_lease_agreements as l', 'l.id', '=', 'ri.lease_id')
                ->leftJoin('sys_pm_clients as t', 't.id', '=', 'l.tenant_id')
                ->where('ri.property_report_id', $id)
                ->groupBy('ri.unit_code')
                ->orderBy('ri.unit_code', 'asc')
                ->get();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Return Summary');

            // Header
            $sheet->setCellValue('A1', 'PROPERTY RETURN REPORT');
            $sheet->mergeCells('A1:L1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Return Info
            $row = 3;
            $sheet->setCellValue('A' . $row, 'Client:')->setCellValue('B' . $row, $return->client_name);
            $row++;
            $sheet->setCellValue('A' . $row, 'Property:')->setCellValue('B' . $row, $return->property);
            $row++;
            $sheet->setCellValue('A' . $row, 'Period:')->setCellValue('B' . $row, $return->period_name);
            $row += 2;

            // Financial Summary
            $sheet->setCellValue('A' . $row, 'FINANCIAL SUMMARY');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            $sheet->setCellValue('A' . $row, 'Gross Returns')->setCellValue('B' . $row, $return->total_gross ?? 0);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
            $sheet->setCellValue('A' . $row, 'Less: Commission')->setCellValue('B' . $row, $return->total_commission ?? 0);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
            $sheet->setCellValue('A' . $row, 'Less: VAT')->setCellValue('B' . $row, $return->vat_on_commission ?? 0);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
            $sheet->setCellValue('A' . $row, 'Less: Recoveries')->setCellValue('B' . $row, $return->total_recoveries ?? 0);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
            $sheet->setCellValue('A' . $row, 'NET PAYABLE')->setCellValue('B' . $row, $return->total_net_amount ?? 0);
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row += 2;

            // Units breakdown
            if ($units->count() > 0) {
                $sheet->setCellValue('A' . $row, 'UNIT BREAKDOWN');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                $headers = ['Tenant', 'Lease Ref', 'Rent', 'Service', 'Water', 'Elec', 'Total', 'VAT', 'Comm', 'Comm VAT', 'Net'];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . $row, $header);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                    $col++;
                }
                $row++;

                foreach ($units as $unit) {
                    $sheet->setCellValue('A' . $row, $unit->tenant_name ?? '-')
                        ->setCellValue('B' . $row, $unit->lease_reference ?? '-')
                        ->setCellValue('C' . $row, $unit->monthly_rent ?? 0)
                        ->setCellValue('D' . $row, $unit->service_charge ?? 0)
                        ->setCellValue('E' . $row, $unit->water_bill ?? 0)
                        ->setCellValue('F' . $row, $unit->elec_bill ?? 0)
                        ->setCellValue('G' . $row, $unit->total_charges ?? 0)
                        ->setCellValue('H' . $row, $unit->total_vat ?? 0)
                        ->setCellValue('I' . $row, $unit->total_commission ?? 0)
                        ->setCellValue('J' . $row, $unit->total_commission_vat ?? 0)
                        ->setCellValue('K' . $row, $unit->net_payable ?? 0);

                    foreach (range('C', 'K') as $col) {
                        $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    $row++;
                }
            }

            // Auto-size columns
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'return_' . $id . '_' . date('Y-m-d') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

            // This will be implemented with PhpSpreadsheet
            return response()->json([
                'success' => false,
                'message' => 'Excel generation will be implemented'
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating Excel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Excel'
            ], 500);
        }
    }
}

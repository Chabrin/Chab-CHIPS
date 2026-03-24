<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Client;
use App\Models\Property;
use App\Models\LandlordAccount;
use App\Exports\LandlordStatementExport;
use App\Services\ErrorDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class LandlordController extends Controller
{
    /**
     * Display the landlord account statement page
     */
    public function accountStatement()
    {
        try {
            return view('property.landlord.account-statement');
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading landlord account statement page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load landlord account statement page');
        }
    }

    /**
     * Search properties for landlord statements (AJAX)
     */
    public function getProperties(Request $request)
    {
        try {
            $query = Property::select('id', 'property_name', 'reference_number');

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('property_name', 'ILIKE', "%{$search}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$search}%");
                });
            }

            $properties = $query->orderBy('property_name')->limit(50)->get();

            return response()->json(['success' => true, 'properties' => $properties]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching properties: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to search properties'], 500);
        }
    }

    /**
     * Get landlords for a property (AJAX)
     */
    public function getLandlordsByProperty(Request $request)
    {
        try {
            $propertyId = $request->input('property_id');

            $clientQuery = Client::select(
                'sys_pm_clients.id',
                'sys_pm_clients.names',
                'sys_pm_clients.mobile_number',
                'sys_pm_clients.email_address',
                'sys_pm_clients.national_id',
                'sys_pm_clients.reference_number'
            )
                ->join('sys_pm_properties', 'sys_pm_properties.client_id', '=', 'sys_pm_clients.id')
                ->where('sys_pm_properties.id', $propertyId)
                ->orderBy('sys_pm_clients.names');

            // Log::info('[getLandlordsByProperty] raw SQL', [
            //     'sql'         => $clientQuery->toSql(),
            //     'bindings'    => $clientQuery->getBindings(),
            //     'property_id' => $propertyId,
            // ]);

            $landlords = $clientQuery->get()->map(function ($l) {
                return [
                    'encrypted_id'     => encrypt($l->id),
                    'names'            => $l->names ?? 'N/A',
                    'mobile_number'    => $l->mobile_number,
                    'email_address'    => $l->email_address,
                    'national_id'      => $l->national_id,
                    'reference_number' => $l->reference_number,
                ];
            });

            return response()->json(['success' => true, 'landlords' => $landlords]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching landlords by property: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load landlords'], 500);
        }
    }

    /**
     * Search landlord account statement (AJAX)
     */
    public function searchStatement(Request $request)
    {
        try {
            if (!$request->filled('landlord_id')) {
                return response()->json(['success' => false, 'message' => 'Landlord is required'], 400);
            }

            try {
                $landlordId = (int) Crypt::decrypt($request->landlord_id);
            } catch (\Exception $e) {
                $landlordId = (int) $request->landlord_id;
            }

            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $query = LandlordAccount::where('client_id', $landlordId);

            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $query->whereDate('entry_date', '>=', $dateFrom)
                    ->whereDate('entry_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $query->whereDate('entry_date', '<=', $dateTo);
            }

            $transactions = $query->orderBy('entry_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $statementEntries = [];
            $balance = 0;

            foreach ($transactions as $txn) {
                $debit = abs($txn->debit_amount ?? 0);
                $credit = abs($txn->credit_amount ?? 0);
                $balance += $credit - $debit;

                $statementEntries[] = [
                    'date' => $txn->entry_date,
                    'reference' => $txn->transaction_number ?? '-',
                    'narration' => $txn->entry_description ?? '-',
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                    'date_formatted' => $txn->entry_date ? date('d-m-Y', strtotime($txn->entry_date)) : '-',
                    'debit_formatted' => $debit > 0 ? number_format($debit, 2) : '-',
                    'credit_formatted' => $credit > 0 ? number_format($credit, 2) : '-',
                    'balance_formatted' => number_format($balance, 2),
                ];
            }

            return response()->json([
                'success' => true,
                'statement' => $statementEntries
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating landlord statement: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate statement'], 500);
        }
    }

    /**
     * Export landlord statement to Excel
     */
    public function exportStatementExcel(Request $request)
    {
        try {
            try {
                $landlordId = (int) Crypt::decrypt($request->landlord_id);
            } catch (\Exception $e) {
                $landlordId = (int) $request->landlord_id;
            }

            $landlord = Client::find($landlordId);
            if (!$landlord) {
                return redirect()->back()->with('error', 'Landlord not found');
            }

            // Get property name from first transaction
            $firstTxn = LandlordAccount::where('client_id', $landlordId)
                ->whereNotNull('property_id')
                ->first();

            $propertyName = 'N/A';
            if ($firstTxn) {
                $property = Property::find($firstTxn->property_id);
                $propertyName = $property->property_name ?? 'N/A';
            }

            $landlordInfo = [
                'landlord_name' => $landlord->names ?? 'N/A',
                'property_name' => $propertyName,
            ];

            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $query = LandlordAccount::where('client_id', $landlordId);

            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $query->whereDate('entry_date', '>=', $dateFrom)
                    ->whereDate('entry_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $query->whereDate('entry_date', '<=', $dateTo);
            }

            $transactions = $query->orderBy('entry_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $statementEntries = [];
            $balance = 0;

            foreach ($transactions as $txn) {
                $debit = abs($txn->debit_amount ?? 0);
                $credit = abs($txn->credit_amount ?? 0);
                $balance += $credit - $debit;

                $statementEntries[] = [
                    'date' => $txn->entry_date,
                    'reference' => $txn->transaction_number ?? '-',
                    'narration' => $txn->entry_description ?? '-',
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                ];
            }

            $fileName = 'Landlord_Statement_' . ($landlord->reference_number ?? $landlordId) . '_' . date('Y-m-d') . '.xlsx';

            return Excel::download(new LandlordStatementExport($statementEntries, $landlordInfo), $fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting landlord statement Excel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export landlord statement');
        }
    }

    /**
     * Export landlord statement to PDF
     */
    public function exportStatementPdf(Request $request)
    {
        try {
            try {
                $landlordId = (int) Crypt::decrypt($request->landlord_id);
            } catch (\Exception $e) {
                $landlordId = (int) $request->landlord_id;
            }

            $landlord = Client::find($landlordId);
            if (!$landlord) {
                return redirect()->back()->with('error', 'Landlord not found');
            }

            $firstTxn = LandlordAccount::where('client_id', $landlordId)
                ->whereNotNull('property_id')
                ->first();

            $propertyName = 'N/A';
            if ($firstTxn) {
                $property = Property::find($firstTxn->property_id);
                $propertyName = $property->property_name ?? 'N/A';
            }

            $landlordInfo = [
                'landlord_name' => $landlord->names ?? 'N/A',
                'landlord_email' => $landlord->email_address ?? 'N/A',
                'landlord_phone' => $landlord->mobile_number ?? 'N/A',
                'property_name' => $propertyName,
            ];

            $dateType = $request->input('date_type', 'range');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $query = LandlordAccount::where('client_id', $landlordId);

            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($dateType === 'range' && $dateFrom && $dateTo) {
                $query->whereDate('entry_date', '>=', $dateFrom)
                    ->whereDate('entry_date', '<=', $dateTo);
            } elseif ($dateType === 'end' && $dateTo) {
                $query->whereDate('entry_date', '<=', $dateTo);
            }

            $transactions = $query->orderBy('entry_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $statementEntries = [];
            $balance = 0;

            foreach ($transactions as $txn) {
                $debit = abs($txn->debit_amount ?? 0);
                $credit = abs($txn->credit_amount ?? 0);
                $balance += $credit - $debit;

                $statementEntries[] = [
                    'date' => $txn->entry_date,
                    'reference' => $txn->transaction_number ?? '-',
                    'narration' => $txn->entry_description ?? '-',
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                    'date_formatted' => $txn->entry_date ? date('d-m-Y', strtotime($txn->entry_date)) : '-',
                    'debit_formatted' => $debit > 0 ? number_format($debit, 2) : '-',
                    'credit_formatted' => $credit > 0 ? number_format($credit, 2) : '-',
                    'balance_formatted' => number_format($balance, 2),
                ];
            }

            $data = [
                'landlordInfo' => $landlordInfo,
                'statement' => $statementEntries,
                'dateFrom' => $dateFrom ? date('d M Y', strtotime($dateFrom)) : 'Beginning',
                'dateTo' => $dateTo ? date('d M Y', strtotime($dateTo)) : 'Today',
            ];

            $pdf = Pdf::loadView('property.landlord.statement-pdf', $data);
            $pdf->setPaper('A4', 'portrait');

            $fileName = 'Landlord_Statement_' . ($landlord->reference_number ?? $landlordId) . '_' . date('Y-m-d') . '.pdf';

            return $pdf->download($fileName);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting landlord statement PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export landlord statement PDF');
        }
    }
}

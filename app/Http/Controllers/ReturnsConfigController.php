<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientBankAccount;
use App\Models\Property;
use App\Models\ReturnsBatchConfigPropertyMapping;
use App\Models\ReturnsBatchConfiguration;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnsConfigController extends Controller
{
    /**
     * Display the payout accounts listing page
     */
    public function index()
    {
        return view('property.returns.payout-accounts');
    }

    /**
     * Get payout accounts data for DataTable
     */
    public function getPayoutAccountsData(Request $request)
    {
        try {
            // Use the SQL query to get properties linked to bank accounts through batch configs
            $results = DB::select("
                SELECT 
                    ba.id as bank_account_id,
                    ba.parent_id as client_id,
                    ba.bank_id,
                    ba.account_name,
                    ba.account_number,
                    ba.branch,
                    ba.return_percentage,
                    c.names as client_name,
                    c.reference_number as client_reference,
                    bank.bank_name,
                    p.id as property_id,
                    p.property_name,
                    p.reference_number as property_reference
                FROM sys_pm_clients_17_repeat ba
                INNER JOIN sys_pm_clients c ON c.id = ba.parent_id
                LEFT JOIN sys_set_banks bank ON bank.id = ba.bank_id
                LEFT JOIN sys_eports_returns_batches_configurations_298_repeat bc 
                    ON bc.bank_account_id = ba.id
                LEFT JOIN sys_eports_returns_batches_configurations_298_repeat_repeat_prs props 
                    ON props.parent_id = bc.id
                LEFT JOIN sys_pm_properties p 
                    ON p.id = props.prs
                WHERE c.reference_number LIKE 'LAN%'
                ORDER BY c.names, ba.id, p.property_name
            ");

            // Group by client
            $groupedByClient = collect($results)->groupBy('client_id');

            $mappedData = [];

            foreach ($groupedByClient as $clientId => $clientRecords) {
                $firstRecord = $clientRecords->first();

                // Group by bank account within this client
                $groupedByAccount = $clientRecords->groupBy('bank_account_id');

                $accountsList = [];
                $allProperties = [];

                foreach ($groupedByAccount as $accountId => $accountRecords) {
                    $firstAccountRecord = $accountRecords->first();

                    // Get branch name if branch ID exists
                    $branchName = 'N/A';
                    if ($firstAccountRecord->branch && is_numeric($firstAccountRecord->branch)) {
                        $branch = \App\Models\BankBranch::find($firstAccountRecord->branch);
                        $branchName = $branch ? $branch->branch_name : 'N/A';
                    }

                    $accountsList[] = [
                        'bank_name' => $firstAccountRecord->bank_name ?? '-',
                        'branch_name' => $branchName,
                        'account_name' => $firstAccountRecord->account_name ?? '-',
                        'account_number' => $firstAccountRecord->account_number ?? '-',
                        'return_percentage' => $firstAccountRecord->return_percentage ?? '-',
                    ];

                    // Collect properties for this account
                    foreach ($accountRecords as $record) {
                        if ($record->property_id) {
                            $allProperties[] = [
                                'id' => $record->property_id,
                                'encrypted_id' => encrypt($record->property_id),
                                'name' => $record->property_name,
                                'reference' => $record->property_reference
                            ];
                        }
                    }
                }

                // Remove duplicate properties
                $uniqueProperties = collect($allProperties)->unique('id')->values()->toArray();

                $mappedData[] = [
                    'client_id' => $clientId,
                    'client_name' => $firstRecord->client_name ?? 'N/A',
                    'client_reference' => $firstRecord->client_reference ?? 'N/A',
                    'accounts' => $accountsList,
                    'accounts_count' => count($accountsList),
                    'properties' => $uniqueProperties,
                    'properties_count' => count($uniqueProperties),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $mappedData
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payout accounts data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching payout accounts: ' . $e->getMessage()
            ], 500);
        }
    }
}

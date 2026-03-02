<?php

namespace App\Traits\Retirement;

use App\Models\Account;
use App\Models\StandingOrder;
use App\Traits\TaxCalculator;
use Ramsey\Uuid\Type\Integer;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountNumberGenerator;

trait ProcessRetirementBalances
{
    use TaxCalculator;
    public function processRetirement(string $memberNo, $company_id): array
    {
        try {
            DB::beginTransaction();

            // Step 1: Prevent duplicate processing
            $existingBalances = DB::table('member_retirement_balances')
                ->where('member_no', $memberNo)
                ->exists();

            if ($existingBalances) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Retirement balances already exist for this member'
                ];
            }

            // Step 2: Get balances
            $balances = $this->getMemberRetirementBalances($memberNo);
            if (empty($balances)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No balance records found for this member'
                ];
            }

            // Step 3: Close existing accounts
            $closeResult = $this->closeMemberAccounts($memberNo);
            if (!$closeResult['success']) {
                DB::rollBack();
                return $closeResult;
            }

            // Step 4: Create retirement account
            $retirementAccount = $this->createRetirementAccount($memberNo, $company_id);
            if (!$retirementAccount['success']) {
                DB::rollBack();
                return $retirementAccount;
            }

            $newAccountNo = $retirementAccount['account_no'];

            // Step 5: Insert retirement balances
            $insertedCount = 0;
            foreach ($balances as $balance) {
                DB::table('member_retirement_balances')->insert([
                    'member_no' => $memberNo,
                    'account_no' => $balance->account_no,
                    'employee_amount' => $balance->employee_amount ?? 0,
                    'employee_units' => $balance->employee_units ?? 0,
                    'employee_registered_amount' => $balance->employee_registered_amount ?? 0,
                    'employee_unregistered_amount' => $balance->employee_unregistered_amount ?? 0,
                    'employee_registered_units' => $balance->employee_registered_units ?? 0,
                    'employee_unregistered_units' => $balance->employee_unregistered_units ?? 0,
                    'employer_amount' => $balance->employer_amount ?? 0,
                    'employer_units' => $balance->employer_units ?? 0,
                    'employer_registered_amount' => $balance->employer_registered_amount ?? 0,
                    'employer_unregistered_amount' => $balance->employer_unregistered_amount ?? 0,
                    'employer_registered_units' => $balance->employer_registered_units ?? 0,
                    'employer_unregistered_units' => $balance->employer_unregistered_units ?? 0,
                    'server_time' => now(),
                    'added_by' => Auth::id(),
                    'added_on' => now(),
                    'approved' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $insertedCount++;
            }

            // Step 6: Credit the retirement account
            $creditResult = $this->creditRetirementAccount($newAccountNo, $balances, $memberNo, true);
            if (!$creditResult['success']) {
                DB::rollBack();
                return $creditResult;
            }

            // Step 7: Create standing order for retirement
            $standingOrderResult = $this->createStandingOrderForRetirement($memberNo, $newAccountNo, $creditResult['credited_amount'] ?? 0);
            if (!$standingOrderResult['success']) {
                DB::rollBack();
                return $standingOrderResult;
            }

            DB::commit();

            Log::info("Retirement process completed for member: {$memberNo}", [
                'member_no' => $memberNo,
                'retirement_account' => $newAccountNo,
                'balances_inserted' => $insertedCount,
                'processed_by' => Auth::id()
            ]);

            return [
                'success' => true,
                'message' => "Successfully processed retirement for member {$memberNo}",
                'retirement_account' => $newAccountNo,
                'accounts_closed' => $closeResult['closed_accounts'] ?? [],
                'balances_inserted' => $insertedCount
            ];
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            DB::rollBack();
            Log::error("Retirement process failed for member: {$memberNo}", [
                'member_no' => $memberNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process retirement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Step 3: Close member accounts
     */
    private function closeMemberAccounts(string $memberNo): array
    {
        try {
            // TODO: Replace with your actual account-closing logic
            $accounts = DB::table('accounts')
                ->where('member_no', $memberNo)
                ->whereNull('closed_date')
                ->whereNull('closed')
                ->pluck('account_no')
                ->toArray();

            if (empty($accounts)) {
                return [
                    'success' => false,
                    'message' => 'No active accounts found to close'
                ];
            }

            DB::table('accounts')
                ->whereIn('account_no', $accounts)
                ->update([
                    'closed_date' => now(),
                    'closed_by' => Auth::user()->username,
                    'closed' => 1,
                    'closed_reason' => 'Retirement processing'
                ]);

            return [
                'success' => true,
                'message' => count($accounts) . ' accounts closed successfully'
            ];
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error("Error closing accounts for member: {$memberNo}", [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to close accounts: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Step 4: Create a new retirement account
     */
    private function createRetirementAccount(string $memberNo, $company_id): array
    {
        try {
            //check if runoff fund exists for company
            $runoffFund = DB::table('funds')
                ->where('company_id', $company_id)
                ->where('is_runoff', 1)
                ->first();
            if (!$runoffFund) {
                Log::error("No runoff fund found for company: {$company_id} while creating retirement account for member: {$memberNo}");
                return [
                    'success' => false,
                    'message' => 'No runoff fund configured for member\'s company'
                ];
            }
            $securityCode = $runoffFund->fund_code;
            $accountNo = AccountNumberGenerator::generate($memberNo, $securityCode);
            $accountNo = 'RT' . $accountNo;
            $a = new Account();
            $a->member_no = $memberNo;
            $a->security_code = $securityCode;
            $a->account_no = $accountNo;
            $a->description = 'Retirement Account';
            $a->added_by = Auth::user()->username ?? 'system';
            $a->added_on = now();
            $a->save();

            return [
                'success' => true,
                'account_no' => $accountNo,
                'message' => 'Retirement account created successfully'
            ];
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error("Failed to create retirement account for member: {$memberNo}", [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create retirement account: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Step 6: Credit the new retirement account using balances
     */

    private function creditRetirementAccount(string $accountNo, array $balances, string $memberNo, bool $lumpsum = false): array
    {
        try {
            //check if account exists
            $accountExists = DB::table('accounts')
                ->where('account_no', $accountNo)
                ->exists();
            if (!$accountExists) {
                Log::error("Retirement account not found: {$accountNo} for member: {$memberNo}");
                return [
                    'success' => false,
                    'message' => 'Retirement account does not exist'
                ];
            }
            $securityCode = DB::table('accounts')
                ->where('account_no', $accountNo)
                ->value('security_code');


            $employeeTotal = collect($balances)->sum('employee_amount');
            $employerTotal = collect($balances)->sum('employer_amount');
            $totalAmount = $employeeTotal + $employerTotal;

            // Insert purchase (credit) transaction
            DB::table('trans')->insert([
                'account_no' => $accountNo,
                'amount' => $totalAmount,
                'net_amount' => $totalAmount,
                'trans_type' => 'PURCHASE',
                'trans_date' => now()->format('Y-m-d'),
                'member_no' => $memberNo,
                'security_code' => $securityCode,
                'mop' => 'Retirement',
                'transacted_by' => Auth::user()->username ?? 'system'
            ]);

            $result = [
                'success' => true,
                'credited_amount' => $totalAmount,
                'message' => "Retirement account credited with {$totalAmount}"
            ];

            // If lumpsum processing requested, calculate and post a withdrawal with tax
            if ($lumpsum) {

                // Read system controls
                $payoutPercent = DB::table('cb_system_configs')
                    ->where('setting', 'lumpsum_payout_percentage')
                    ->value('value');

                $taxFreeThreshold = DB::table('cb_system_configs')
                    ->where('setting', 'taxfree_idd_amount')
                    ->value('value');

                $payoutPercent = is_numeric($payoutPercent) ? (float) $payoutPercent : 0.0;
                $taxFreeThreshold = is_numeric($taxFreeThreshold) ? (float) $taxFreeThreshold : 0.0;

                // Lumpsum amount is a percentage of the IDD (totalAmount)
                $lumpsumAmount = round(($totalAmount * $payoutPercent) / 100.0, 2);
                $taxablePortion = max(0, $lumpsumAmount - $taxFreeThreshold);
                $taxAmount = $this->calculateTax($taxablePortion);

                $netLumpsum = round($lumpsumAmount - $taxAmount, 2);

                // Insert withdrawal transaction with tax_amt and net_amount
                DB::table('trans')->insert([
                    'account_no' => $accountNo,
                    'amount' => -$lumpsumAmount,
                    'net_amount' => -$lumpsumAmount, // $netLumpsum,
                    'tax_amt' => -$taxAmount,
                    'trans_type' => 'WITHDRAWAL',
                    'trans_date' => now()->format('Y-m-d'),
                    'member_no' => $memberNo,
                    'security_code' => $securityCode,
                    'mop' => 'Lumpsum',
                    'transacted_by' => Auth::user()->username ?? 'system'
                ]);

                $result['lumpsum'] = [
                    'requested' => true,
                    'lumpsum_amount' => $lumpsumAmount,
                    'tax_amt' => $taxAmount,
                    'net_amount' => $netLumpsum,
                    'payout_percent' => $payoutPercent,
                    'tax_free_threshold' => $taxFreeThreshold
                ];
            }

            return $result;
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error("Failed to credit retirement account: {$accountNo}", [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to credit retirement account: ' . $e->getMessage()
            ];
        }
    }


    private function getMemberRetirementBalances(string $memberNo): array
    {
        $query = "
            SELECT 
                ut.account_no,
                SUM(ut.employee_amount) AS employee_amount,
                SUM(ut.employee_units) AS employee_units,
                SUM(ut.employee_registered_amount) AS employee_registered_amount,
                SUM(ut.employee_unregistered_amount) AS employee_unregistered_amount,
                SUM(ut.employee_registered_units) AS employee_registered_units,
                SUM(ut.employee_unregistered_units) AS employee_unregistered_units,
                SUM(ut.employer_amount) AS employer_amount,
                SUM(ut.employer_units) AS employer_units,
                SUM(ut.employer_registered_amount) AS employer_registered_amount,
                SUM(ut.employer_unregistered_amount) AS employer_unregistered_amount,
                SUM(ut.employer_registered_units) AS employer_registered_units,
                SUM(ut.employer_unregistered_units) AS employer_unregistered_units
            FROM um_trans ut
            WHERE ut.deleted IS NULL 
                AND ut.reversed IS NULL 
                AND ut.confirmed = 1
                AND ut.member_no = ?
            GROUP BY ut.account_no
        ";

        try {
            return DB::select($query, [$memberNo]);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve balances for member: {$memberNo}", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create a standing order for a retired member
     */
    public function createStandingOrder($memberNo, $accountNo, $grossAmount)
    {
        try {
            $startDate = now()->addMonth()->format('Y-m-d');
            StandingOrder::create([
                'member_no' => $memberNo,
                'account_no' => $accountNo,
                'gross_amount' => round($grossAmount, 2),
                'member_name' => DB::table('members')->where('member_no', $memberNo)->value('all_names')
                    ?? DB::table('members_host')->where('member_no', $memberNo)->value('all_names'),
                'start_date' => $startDate,
                'is_active' => 1
            ]);
            return [
                'success' => true,
                'message' => 'Standing order created successfully.'
            ];
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return [
                'success' => false,
                'message' => 'Failed to create standing order: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate monthly standing order amount and create order
     */
    public function createStandingOrderForRetirement($memberNo, $accountNo, $totalAmount)
    {
        try {
            $annualRate = DB::table('cb_system_configs')
                ->where('setting', 'annual_drawdown_rate')
                ->value('value') ?? 8;
            $annualRate = is_numeric($annualRate) ? (float)$annualRate : 0.0;
            $monthlyAmount = round(($totalAmount * $annualRate / 100) / 12, 2);
            return $this->createStandingOrder($memberNo, $accountNo, $monthlyAmount);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return [
                'success' => false,
                'message' => 'Failed to calculate and create standing order: ' . $e->getMessage()
            ];
        }
    }
}

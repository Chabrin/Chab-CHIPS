<?php

namespace App\Services;

use App\Exceptions\MiniReturnException;
use App\Traits\ReturnsDeductionsTrait;
use App\Traits\ReturnsRentLinesTrait;
use App\Traits\ReturnsSummaryTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generates a mini return: one property, any time after the month's main return is approved, carrying only the money
 * that came in late and has not been passed to the landlord yet (rent of earlier months, bills paid since). The closed
 * main return is never touched. It is built with the same lines, commission and deduction rules as a main return, goes
 * through the same approval and is paid by its own payment voucher. Advances are not recovered on a mini return: they come off the main return.
 *
 * The return code is R + a sequence digit (2 to 9) + period + landlord + property, as in the legacy mini returns: the
 * main return is R1, the first mini return of the month R2, the next R3, and so on.
 */
class MiniReturnService
{
    use ReturnsRentLinesTrait;
    use ReturnsDeductionsTrait;
    use ReturnsSummaryTrait;

    const FIRST_SEQUENCE = 2;
    const LAST_SEQUENCE = 9;

    protected ReturnApprovalWorkflowService $approvals;

    /**
     * Inject the service that sends a return for approval.
     *
     * @param ReturnApprovalWorkflowService $approvals
     */
    public function __construct(ReturnApprovalWorkflowService $approvals) {
        $this->approvals = $approvals;
    }

    /**
     * The sequence digit of the next mini return of a property and period: the first of 2 to 9 whose return code is
     * not used yet, or null when all eight are.
     *
     * @param array $usedCodes the return codes already used by mini returns of the property and period
     * @param string $period YYYYMM
     * @param string $landlordCode reference number of the landlord
     * @param string $propertyCode reference number of the property
     */
    public static function nextSequence(array $usedCodes, string $period, string $landlordCode, string $propertyCode): ?int {
        for ($sequence = self::FIRST_SEQUENCE; $sequence <= self::LAST_SEQUENCE; $sequence++) {
            if (!in_array(self::returnCode($sequence, $period, $landlordCode, $propertyCode), $usedCodes, true)) {
                return $sequence;
            }
        }

        return null;
    }

    /**
     * The return code of a return: R, the sequence digit, the period, the landlord and the property.
     *
     * @param int $sequence 1 for the main return, 2 to 9 for a mini return
     * @param string $period YYYYMM
     * @param string $landlordCode reference number of the landlord
     * @param string $propertyCode reference number of the property
     */
    public static function returnCode(int $sequence, string $period, string $landlordCode, string $propertyCode): string {
        return 'R' . $sequence . $period . $landlordCode . $propertyCode;
    }

    /**
     * Generate a mini return for a property and month, in one database transaction, and send it for approval. A mini
     * return of the same property and month that is not approved yet is replaced, so the new one carries everything
     * still uncollected by any other return.
     *
     * @param int $propertyId
     * @param string $returnsMonth YYYY-MM
     * @param int $userId the user generating it
     * @param string|null $ipAddress for the approval log
     * @return array return_code, lines (lines inserted before the commission line) and net_balance
     * @throws MiniReturnException when the property is unknown, the month's main return is not approved yet, nothing is left to pass on, or all mini return codes of the month are used
     */
    public function generate(int $propertyId, string $returnsMonth, int $userId, ?string $ipAddress): array {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $returnsMonth)) {
            throw new MiniReturnException('Choose the month the mini return is for.');
        }

        $property = DB::table('sys_pm_properties')->where('id', $propertyId)->first(['id', 'property_name', 'reference_number']);
        if (!$property) {
            throw new MiniReturnException('That property was not found.');
        }

        $period = str_replace('-', '', $returnsMonth);
        [$year, $month] = array_map('intval', explode('-', $returnsMonth));
        $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
        $monthLabel = $monthName . ' ' . $year;

        if (!$this->mainReturnApproved($period, $property->reference_number)) {
            throw new MiniReturnException("The {$monthLabel} return of {$property->property_name} is not approved yet. Money that comes in late goes on the main return until it is approved; a mini return is for what comes in after that.");
        }

        return DB::transaction(function () use ($property, $period, $year, $month, $monthName, $monthLabel, $returnsMonth, $userId, $ipAddress) {
            $this->deleteUnapprovedMinis($period, $property->reference_number);

            $landlordCode = $this->landlordCode((int) $property->id);
            $used = DB::table('cb_returns_summary')
                ->where('return_period', $period)
                ->where('property_code', $property->reference_number)
                ->where('return_type', 'MINIRETURN')
                ->pluck('return_code')
                ->all();
            $sequence = self::nextSequence($used, $period, $landlordCode, $property->reference_number);
            if ($sequence === null) {
                throw new MiniReturnException("{$property->property_name} already has the most mini returns a month can hold ({$this->minis()}) for {$monthLabel}.");
            }
            $code = self::returnCode($sequence, $period, $landlordCode, $property->reference_number);

            $transactionDate = date('d-M-Y', strtotime($returnsMonth . '-05'));
            $preparedBy = DB::table('josya_users')->where('id', $userId)->value('name') ?? 'System';

            $lines = $this->insertRentLines($period, $returnsMonth . '-01', $transactionDate, $this->getNextReturnIdBase(), $userId, $monthName, $year, (int) $property->id, $sequence);
            if ($lines === 0) {
                throw new MiniReturnException("Nothing has come in for {$property->property_name} that is not already on a return, so there is nothing for a mini return.");
            }

            $this->insertAdvancePaymentLines($period, $transactionDate, $userId, $year, $month, (int) $property->id, $code);
            $this->insertCommissionLines($period, $transactionDate, $userId, (int) $property->id, $code);
            $this->insertMainReturnSummariesLegacy($period, $year, $month, $monthName, $preparedBy, (int) $property->id, 'MINIRETURN', $code);
            $this->approvals->createItems($period, $userId, $ipAddress, (int) $property->id);

            $netBalance = (float) DB::table('cb_returns_summary')->where('return_code', $code)->value('net_balance');
            Log::info('MiniReturnService: mini return generated', ['return_code' => $code, 'lines' => $lines, 'net_balance' => $netBalance, 'user_id' => $userId]);

            return ['return_code' => $code, 'lines' => $lines, 'net_balance' => $netBalance];
        });
    }

    /**
     * Whether the main return of a property and period is approved, either by itself or through the master return it
     * was merged into.
     *
     * @param string $period YYYYMM
     * @param string $propertyCode reference number of the property
     */
    protected function mainReturnApproved(string $period, string $propertyCode): bool {
        return DB::table('cb_returns_summary as rs')
            ->where('rs.return_period', $period)
            ->where('rs.property_code', $propertyCode)
            ->where('rs.return_type', 'MAINRETURN')
            ->where(function ($query) {
                $query->where('rs.approved_status', 1)->orWhereExists(function ($master) {
                    $master->select(DB::raw(1))->from('cb_returns_summary as m')
                        ->whereColumn('m.return_code', 'rs.parent_return_code')
                        ->where('m.approved_status', 1);
                });
            })
            ->exists();
    }

    /**
     * The reference number of the landlord a property returns to, from the agreement in force, as the return lines use it.
     *
     * @param int $propertyId
     */
    protected function landlordCode(int $propertyId): string {
        $row = DB::selectOne("
            SELECT COALESCE(c.reference_number, 'UNKNOWN') AS landlord_code
            FROM sys_pm_properties p
            LEFT JOIN (
                SELECT DISTINCT ON (property_id) * FROM sys_pm_service_agreements
                ORDER BY property_id, date_start DESC NULLS LAST, id DESC
            ) s ON s.property_id = p.id
            LEFT JOIN sys_pm_clients c ON c.id = s.client_d
            WHERE p.id = ?
        ", [$propertyId]);

        return $row->landlord_code ?? 'UNKNOWN';
    }

    /**
     * Remove the mini returns of a property and period that are not approved: their approval items, lines and summary,
     * and the client-ledger debits they had reserved go back to unreserved. Approved mini returns stay.
     *
     * @param string $period YYYYMM
     * @param string $propertyCode reference number of the property
     */
    protected function deleteUnapprovedMinis(string $period, string $propertyCode): void {
        $codes = DB::table('cb_returns_summary')
            ->where('return_period', $period)
            ->where('property_code', $propertyCode)
            ->where('return_type', 'MINIRETURN')
            ->where('approved_status', '!=', 1)
            ->pluck('return_code')
            ->all();

        if (empty($codes)) {
            return;
        }

        $this->deleteApprovalItemsForReturns($period, $codes);
        DB::table('cb_returns_clients')->where('return_period', (int) $period)->whereIn('return_code', $codes)->delete();
        DB::table('cb_returns_summary')->where('return_period', $period)->where('approved_status', '!=', 1)->whereIn('return_code', $codes)->delete();
        DB::table('cb_landlord_account')->whereIn('return_code', $codes)->update([
            'is_recovered' => 0,
            'recovery_year' => null,
            'recovery_month' => null,
            'return_code' => null,
            'recovery_server_time' => null,
        ]);
    }

    /**
     * The most mini returns one property can have in a month, for the message when they are all used.
     */
    protected function minis(): int {
        return self::LAST_SEQUENCE - self::FIRST_SEQUENCE + 1;
    }
}

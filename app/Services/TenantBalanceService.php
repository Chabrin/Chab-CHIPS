<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Client;

/**
 * A tenant's balance, read from the tenant ledger (sys_pm_tenants_accounts_trans): debits are what the
 * tenant was invoiced, credits what the tenant paid. This is the same source and the same filter as the
 * tenant statements and the arrears report, so every screen shows the same figure. It used to add up
 * invoices and a payments table whose ids do not match the migrated data.
 */
class TenantBalanceService
{
    /**
     * Get the current balance for a tenant.
     *
     * A NEGATIVE balance means the tenant owes money (debits exceed credits).
     * A POSITIVE balance means the tenant is in credit (overpaid).
     *
     * @param  int  $tenantId  Plain integer tenant ID (not encrypted)
     * @param  string|null  $asOfDate  Optional cut-off date (Y-m-d). Defaults to today (all-time).
     * @return array{
     *     tenant_id: int,
     *     total_invoiced: float,
     *     total_paid: float,
     *     balance: float,
     *     balance_formatted: string,
     *     is_in_arrears: bool,
     *     as_of_date: string
     * }
     */
    public static function getBalance(int $tenantId, ?string $asOfDate = null): array
    {
        $cutoff = $asOfDate ?? now()->toDateString();

        // Ledger debits (invoiced) and credits (paid) up to the cutoff date
        $totals = DB::table('sys_pm_tenants_accounts_trans')
            ->where('tenant_id', $tenantId)
            ->where('current_status_id', 1)
            ->whereDate('date_time', '<=', $cutoff)
            ->selectRaw('COALESCE(SUM(debit_amount), 0) AS invoiced, COALESCE(SUM(credit_amount), 0) AS paid')
            ->first();

        $totalInvoiced = $totals->invoiced;
        $totalPaid = $totals->paid;

        // Positive = in credit, Negative = in arrears (owes money)
        $balance = (float) $totalPaid - (float) $totalInvoiced;

        return [
            'tenant_id'         => $tenantId,
            'total_invoiced'    => round((float) $totalInvoiced, 2),
            'total_paid'        => round((float) $totalPaid, 2),
            'balance'           => round($balance, 2),
            'balance_formatted' => number_format(abs($balance), 2),
            'is_in_arrears'     => $balance < 0,
            'as_of_date'        => $cutoff,
        ];
    }

    /**
     * Get balances for multiple tenants at once (efficient batch lookup).
     *
     * Returns an array keyed by tenant_id.
     *
     * @param  int[]  $tenantIds
     * @param  string|null  $asOfDate
     * @return array<int, array>
     */
    public static function getBalances(array $tenantIds, ?string $asOfDate = null): array
    {
        if (empty($tenantIds)) {
            return [];
        }

        $cutoff = $asOfDate ?? now()->toDateString();

        // Batch-sum the ledger debits (invoiced) and credits (paid)
        $ledgerTotals = DB::table('sys_pm_tenants_accounts_trans')
            ->whereIn('tenant_id', $tenantIds)
            ->where('current_status_id', 1)
            ->whereDate('date_time', '<=', $cutoff)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(debit_amount) AS total_invoiced, SUM(credit_amount) AS total_paid')
            ->get()
            ->keyBy('tenant_id');
        $invoiceTotals = $ledgerTotals->pluck('total_invoiced', 'tenant_id');
        $paymentTotals = $ledgerTotals->pluck('total_paid', 'tenant_id');

        $results = [];

        foreach ($tenantIds as $tenantId) {
            $invoiced = round((float) ($invoiceTotals[$tenantId] ?? 0), 2);
            $paid     = round((float) ($paymentTotals[$tenantId] ?? 0), 2);
            $balance  = round($paid - $invoiced, 2);

            $results[$tenantId] = [
                'tenant_id'         => $tenantId,
                'total_invoiced'    => $invoiced,
                'total_paid'        => $paid,
                'balance'           => $balance,
                'balance_formatted' => number_format(abs($balance), 2),
                'is_in_arrears'     => $balance < 0,
                'as_of_date'        => $cutoff,
            ];
        }

        return $results;
    }

    /**
     * Convenience: just return the numeric balance for a tenant.
     * Negative = owes money, Positive = in credit.
     *
     * @param  int  $tenantId
     * @param  string|null  $asOfDate
     * @return float
     */
    public static function getBalanceAmount(int $tenantId, ?string $asOfDate = null): float
    {
        return self::getBalance($tenantId, $asOfDate)['balance'];
    }

    /**
     * Check whether a tenant is currently in arrears.
     *
     * @param  int  $tenantId
     * @param  string|null  $asOfDate
     * @return bool
     */
    public static function isInArrears(int $tenantId, ?string $asOfDate = null): bool
    {
        return self::getBalance($tenantId, $asOfDate)['is_in_arrears'];
    }
}
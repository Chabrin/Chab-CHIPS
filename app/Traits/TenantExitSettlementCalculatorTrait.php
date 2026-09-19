<?php

namespace App\Traits;

use App\Models\Deposit;
use App\Models\TenantAccountTransaction;
use App\Models\TenantExit;
use App\Models\Unit;

/**
 * The single, authoritative settlement calculation for a tenant exit — used both to
 * preview the figures in the browser (TenantExitController::getSettlementPreview) and,
 * critically, to compute what actually gets submitted/paid (TenantExitController::
 * saveSettlement, PropertyConfirmationController::applyTenantExitSettlementApproval).
 * The server computes these figures itself from the tenant's ledger/deposit/inspection
 * data every time — nothing here is ever taken from client input.
 */
trait TenantExitSettlementCalculatorTrait
{
    /**
     * @param float $otherDeductions Manual, staff-entered adjustment — the one figure
     *   that genuinely can't be derived from stored data (e.g. "damage found after the
     *   inspection was logged"), so it's the only input this method accepts from a caller.
     */
    protected function computeTenantExitSettlement(TenantExit $exit, float $otherDeductions = 0): array
    {
        $exit->loadMissing('inspection');

        $depositOnRecord = (float) Deposit::where('tenant_id', $exit->tenant_id)->sum('amount');

        // Most tenants never went through the dedicated deposit-capture flow added in
        // July 2026 — their deposit was invoiced and paid off like any other charge
        // instead, so sys_pm_deposits has no row for them at all. Fall back to what they
        // actually paid against a deposit-type charge, or a real paid deposit reads as
        // zero on record here and the tenant is refunded nothing for it.
        if ($depositOnRecord <= 0) {
            $depositOnRecord = (float) TenantAccountTransaction::where('tenant_id', $exit->tenant_id)
                ->where('current_status_id', 1)
                ->whereIn('pay_for', ['House Deposit', 'Electricity Deposit', 'Water Deposit'])
                ->sum('credit_amount');
        }

        // Arrears: closing balance from transactions (positive = DR balance = tenant owes us).
        // Excludes the Security Deposit ledger entry itself — it's already counted via
        // $depositOnRecord above, so including it here would double-count it in the
        // settlement formula below (once netted into arrears, once added back directly).
        $transactions = TenantAccountTransaction::where('tenant_id', $exit->tenant_id)
            ->where('current_status_id', 1)
            ->where(fn($q) => $q->whereNull('pay_for')->orWhere('pay_for', '!=', 'Security Deposit'))
            ->get();
        $balance = 0;
        foreach ($transactions as $txn) {
            $balance += abs($txn->credit_amount ?? 0) - abs($txn->debit_amount ?? 0);
        }
        $arrearsAmount = $balance < 0 ? abs($balance) : 0;

        $inspectionDamage = (float) ($exit->inspection?->damage_amount ?? 0);
        $cleaningCost     = (float) ($exit->inspection?->cleaning_cost ?? 0);

        $depositAsRentApplied = $exit->deposit_as_rent ? (float) ($exit->deposit_as_rent_amount ?? 0) : 0;

        $settlementBalance = $depositOnRecord
            - $arrearsAmount
            - $inspectionDamage
            - $cleaningCost
            - $depositAsRentApplied
            - $otherDeductions;

        if ($settlementBalance > 0.005) {
            $settlementType = 'Refund to Tenant';
        } elseif ($settlementBalance < -0.005) {
            $settlementType = 'Invoice to Tenant';
        } else {
            $settlementType    = 'Zero Balance';
            $settlementBalance = 0;
        }

        $unit         = Unit::find($exit->unit_id);
        $monthlyRent  = (float) ($unit?->rent_amount ?? 0);
        $residualHold = $exit->deposit_as_rent ? max(0, $depositOnRecord - $depositAsRentApplied) : 0;

        return [
            'deposit_on_record'       => round($depositOnRecord, 2),
            'monthly_rent'            => round($monthlyRent, 2),
            'residual_hold'           => round($residualHold, 2),
            'arrears_amount'          => round($arrearsAmount, 2),
            'inspection_damage'       => round($inspectionDamage, 2),
            'cleaning_cost'           => round($cleaningCost, 2),
            'deposit_as_rent_applied' => round($depositAsRentApplied, 2),
            'other_deductions'        => round($otherDeductions, 2),
            'settlement_balance'      => round(abs($settlementBalance), 2),
            'settlement_type'         => $settlementType,
        ];
    }
}

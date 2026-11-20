<?php

namespace App\Services;

use App\Models\ReturnSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures of a return statement, worked out once from the return's lines for the screen, the PDF and the Excel
 * file. Every line of a return lands in exactly one place: a tenant column, a summary figure, or the recoveries, opening
 * balance and landlord-account blocks, so the statement foots to the return's stored net amount.
 *
 * A return carries two VATs that are never mixed: the VAT inside the rent of a VAT unit (taken off before commission,
 * then passed back to the landlord) and the VAT on the commission (kept by the company). Only rent and arrears are
 * commissionable; deposits, service charges and bills are added on after commission.
 */
class ReturnStatementService
{
    public const COLUMNS = [
        'unit_code' => 'Room', 'tenant_name' => 'Tenant', 'payable' => 'Payable',
        'rent_arrears' => 'Arrears / Prepayments', 'vat' => 'VAT',
        'house_deposit_amt' => 'House Dep.', 'elec_deposit_amt' => 'Elec Dep.',
        'water_deposit_amt' => 'Water Dep.', 'elec_bill' => 'Elec Bill',
        'water_bill' => 'Water Bill', 'service_charge' => 'Svc Charge',
        'monthly_rent' => 'Monthly Rent', 'remarks' => 'Remarks',
    ];

    // The existing return-line remarks column is varchar(50).
    public const REMARKS_LIMIT = 50;

    // Abbreviations the old system used on its first returns (January and February 2025).
    private const ALIASES = [
        'WABILL' => 'WATER BILL', 'ELBILL' => 'ELECTRICITY BILL', 'HODE' => 'HOUSE DEPOSIT',
        'ELDE' => 'ELECTRICITY DEPOSIT', 'WADE' => 'WATER DEPOSIT',
    ];

    /**
     * Everything the statement shows: the tenant rows and column totals, the summary A to H, the blocks I to O, and the
     * arrears details.
     *
     * @param ReturnSummary $return
     * @param Collection $lines the return's lines
     * @param Collection|null $ledgerGaps ledger debits tagged to the return that have no line; looked up when null
     * @return array
     */
    public function statement(ReturnSummary $return, Collection $lines, ?Collection $ledgerGaps = null): array {
        $data = $this->build($lines);
        $data['summary'] = $this->summary($return, $lines, $data['totals']);
        $data['summaryRows'] = $this->summaryRows($return);
        $data['settlementRows'] = $this->settlement($return, $lines, $data['summary'], $ledgerGaps ?? $this->ledgerGaps($return, $lines));
        $data['arrears'] = $this->arrearsDetails($return, $lines);

        return $data;
    }

    /**
     * Pivot the return lines into one row per room. The rent VAT is worked out per line (rent x 16/116 on the amount
     * collected, current rent and arrears alike, for VAT units only); where the return stored its rent VAT total, that
     * stored figure is the total of the VAT column.
     *
     * @param Collection $lines
     * @return array tenantRows and totals
     */
    public function build(Collection $lines): array {
        $vatableUnits = $this->vatableUnits($lines);
        $perLine = $this->commissionOnRentLines($lines);
        $rows = [];
        $numeric = array_diff(array_keys(self::COLUMNS), ['unit_code', 'tenant_name', 'remarks']);
        $totals = array_fill_keys($numeric, 0.0);
        $totals['other_payments'] = 0.0;
        $storedRentVat = null;

        // An old-system return that charged commission but has no stored rent-VAT deduction deducted none, so it has none
        // to add back (one old mini return is like this: its commission was worked out on the whole rent).
        $noRentVat = !$perLine
            && !$lines->contains(fn($line) => $this->roleOf($line, $perLine) === 'rent_vat')
            && $lines->contains(fn($line) => $this->roleOf($line, $perLine) === 'commission');

        foreach ($lines as $line) {
            $role = $this->roleOf($line, $perLine);
            $amount = (float) ($line->net_amount ?? 0);

            if ($role === 'rent_vat') {
                $storedRentVat = ($storedRentVat ?? 0.0) + $amount;
            } elseif ($role === 'other_income') {
                $totals['other_payments'] += $amount;
            }
            if ($role !== 'column') {
                continue;
            }

            $field = $this->columnFor($line);
            $type = strtoupper(trim($line->entry_type ?? ''));

            // One row per room, as printed. A merged statement must not combine identically named rooms in different properties.
            $key = json_encode([$line->property_code, $line->unit_code]);
            if (!isset($rows[$key])) {
                $rows[$key] = array_merge(array_fill_keys($numeric, 0.0), [
                    'unit_code' => $this->roomLabel($line) ?: '-', 'tenant_name' => '-', 'name_rank' => 3, 'seq' => null,
                    'remarks' => '', 'remarks_line_id' => null, 'remarks_priority' => null,
                ]);
            }

            // The room shows its current tenant, else any named tenant (arrears of a former one), else the vacant label.
            $name = trim((string) ($line->tenant_name ?? ''));
            $nameRank = $name === '' ? 3 : (stripos($name, 'VACANT') !== false ? 2 : ($type === 'RENT CURRENT' ? 0 : 1));
            if ($nameRank < $rows[$key]['name_rank']) {
                $rows[$key]['name_rank'] = $nameRank;
                $rows[$key]['tenant_name'] = $name;
            }

            // The old system's own room order, kept on the line as unit_seq_id.
            if (($line->unit_seq_id ?? null) !== null && ($rows[$key]['seq'] === null || (int) $line->unit_seq_id < $rows[$key]['seq'])) {
                $rows[$key]['seq'] = (int) $line->unit_seq_id;
            }

            $rows[$key][$field] += $amount;
            $totals[$field] += $amount;

            // Payable is what the room was invoiced for the month, collected or not.
            if ($type === 'RENT CURRENT') {
                $due = (float) ($line->amount_payable_landlord ?? $amount);
                $rows[$key]['payable'] += $due;
                $totals['payable'] += $due;
            }

            // VAT inside the rent of a VAT unit, on what was collected. The line's own vat_amount is not read: on an
            // old-system line it is this same VAT (but missing on arrears), on a generated line it is the VAT on commission.
            if (in_array($field, ['monthly_rent', 'rent_arrears'], true)) {
                $vatable = ($line->is_vatable ?? null) !== null ? $line->is_vatable === 'Yes' : ($vatableUnits[$line->unit_id ?? 0] ?? false);
                if ($vatable && !$noRentVat) {
                    $vat = round($amount * InvoiceBillingService::VAT_RATE / (100 + InvoiceBillingService::VAT_RATE), 2);
                    $rows[$key]['vat'] += $vat;
                    $totals['vat'] += $vat;
                }
            }

            // Prefer the current rent line for the editable tenant note; never clear other line notes.
            $priority = [$type === 'RENT CURRENT' ? 0 : 1, (int) $line->id];
            if ($rows[$key]['remarks_priority'] === null || $priority < $rows[$key]['remarks_priority']) {
                $rows[$key]['remarks_priority'] = $priority;
                $rows[$key]['remarks_line_id'] = $line->id;
                $rows[$key]['remarks'] = (string) ($line->remarks ?? '');
            }
        }

        foreach ($rows as &$row) {
            unset($row['remarks_priority'], $row['name_rank']);
        }
        unset($row);

        // Rooms in the old system's order where the lines carry it, then natural order (B2 before B10).
        $rows = collect($rows)->sort(fn($a, $b) => ($a['seq'] ?? PHP_INT_MAX) <=> ($b['seq'] ?? PHP_INT_MAX)
            ?: strnatcasecmp($a['unit_code'], $b['unit_code'])
            ?: strcasecmp($a['tenant_name'], $b['tenant_name']))->values()->map(function ($row) {
                unset($row['seq']);
                return $row;
            })->all();

        // The return's stored rent VAT is whole shillings and is what was deducted; the per-room cells keep their cents.
        if ($storedRentVat !== null) {
            $totals['vat'] = $storedRentVat;
        }

        // Retain aliases consumed by the summary.
        $totals['house_deposit'] = $totals['house_deposit_amt'];
        $totals['elec_deposit'] = $totals['elec_deposit_amt'];
        $totals['water_deposit'] = $totals['water_deposit_amt'];

        return ['tenantRows' => $rows, 'totals' => $totals];
    }

    /**
     * The financial summary A to H of a return.
     *
     * @param ReturnSummary $return
     * @param Collection $lines
     * @param array $totals the totals from build(), where 'vat' is the rent VAT
     * @return array
     */
    public function summary(ReturnSummary $return, Collection $lines, array $totals): array {
        $perLine = $this->commissionOnRentLines($lines);
        $rent = $lines->filter(fn($line) => in_array($line->entry_type, ['RENT CURRENT', 'RENT ARREARS']));

        // Commission: an old-system return has one 'Commission' line and its rent lines carry none; a return generated
        // here carries it on each rent line, and its extra 'COMMISSION' line only repeats that total.
        $commission = (float) $rent->sum('commission_amount') + $this->lineSum($lines, $perLine, 'commission');

        // VAT on commission: an old-system return has one 'VAT' line, and its rent lines hold the rent VAT in vat_amount.
        // A return generated here holds the VAT on commission in vat_amount of the rent lines that were commissioned.
        $vatOnCommission = $this->lineSum($lines, $perLine, 'commission_vat')
            + (float) $rent->filter(fn($line) => (float) ($line->commission_amount ?? 0) > 0)->sum('vat_amount');

        $rentVat = round((float) $totals['vat'], 2);
        $commissionable = round($totals['monthly_rent'] + $totals['rent_arrears'] - $rentVat, 2);
        $balAfterCommission = round($commissionable - $commission, 2);
        $balAfterVat = round($balAfterCommission - $vatOnCommission, 2);
        $utilityDeposits = $totals['elec_deposit'] + $totals['water_deposit'];
        $bills = $totals['elec_bill'] + $totals['water_bill'];
        $otherPayments = (float) ($totals['other_payments'] ?? 0);

        return [
            'rent_current' => $totals['monthly_rent'], 'rent_arrears' => $totals['rent_arrears'],
            'rent_vat' => $rentVat, 'commissionable' => $commissionable,
            'commission' => $commission, 'bal_after_comm' => $balAfterCommission,
            'vat_on_commission' => $vatOnCommission, 'bal_after_vat' => $balAfterVat,
            'house_deposits' => $totals['house_deposit'], 'rent_vat_addback' => $rentVat,
            'utility_deposits' => $utilityDeposits, 'bills' => $bills, 'service_charge' => $totals['service_charge'],
            'other_payments' => $otherPayments,
            'total_income' => round($balAfterVat + $rentVat + $totals['house_deposit'] + $utilityDeposits + $bills + $totals['service_charge'] + $otherPayments, 2),
        ];
    }

    /**
     * The summary lines A to H in print order: key, label and kind (plain, less, strong, add, closing).
     *
     * @param ReturnSummary $return
     * @return array
     */
    public function summaryRows(ReturnSummary $return): array {
        return [
            ['rent_current', 'A. Total rent current', 'plain'],
            ['rent_arrears', 'B. Add total rent arrears', 'plain'],
            ['rent_vat', 'Less VAT on rent (' . InvoiceBillingService::VAT_RATE . '%)', 'less'],
            ['commissionable', 'C. Commissionable amount (A+B - VAT)', 'strong'],
            ['commission', 'D. Less commission (at ' . number_format($return->commission_rate ?? 0, 2) . '% of C)', 'less'],
            ['bal_after_comm', 'E. Balance after commission (C-D)', 'strong'],
            ['vat_on_commission', 'F. Less VAT on commission (' . InvoiceBillingService::VAT_RATE . '% of D)', 'less'],
            ['bal_after_vat', 'G. Balance after commission & VAT on commission (E-F)', 'strong'],
            ['house_deposits', 'Add house deposits', 'add'],
            ['rent_vat_addback', 'Add VAT from commercial houses', 'add'],
            ['utility_deposits', 'Add water and electricity deposits', 'add'],
            ['bills', 'Add water and electricity bills', 'add'],
            ['service_charge', 'Add service charge / other incomes', 'add'],
            ['other_payments', 'Add other payments / deposits', 'add'],
            ['total_income', 'H. Total income', 'closing'],
        ];
    }

    /**
     * The blocks after the summary, in print order, as one list for the screen, the PDF and the Excel file: the
     * recoveries and their total (I), nett income (J), the opening balance and other receipts (K), the landlord-account
     * deductions (L), the amount payable (M), the overdraft charge (N) and the nett balance (O). Each row has a kind
     * (item, strong, plain, closing), a label, a reference, a date and an amount. The last row of a return whose lines
     * do not foot to its stored net is a 'gap' row saying by how much.
     *
     * @param ReturnSummary $return
     * @param Collection $lines
     * @param array $summary the summary from summary()
     * @param Collection $ledgerGaps ledger debits tagged to the return that have no line
     * @return array
     */
    public function settlement(ReturnSummary $return, Collection $lines, array $summary, Collection $ledgerGaps): array {
        $perLine = $this->commissionOnRentLines($lines);
        $recoveries = [];
        $opening = [];
        $deductions = [];

        foreach ($lines as $line) {
            $role = $this->roleOf($line, $perLine);
            $amount = (float) ($line->net_amount ?? 0);
            if ($role === 'recovery') {
                $recoveries[] = ['item', trim(($line->narration ?: $line->entry_description) . ' ' . $this->roomLabel($line)), (string) ($line->transaction_ref ?? ''), '', $amount];
            } elseif ($role === 'opening') {
                $opening[] = ['item', (string) ($line->narration ?: $line->entry_description), (string) ($line->transaction_ref ?? ''), $this->shortDate($line->source_date ?? null), $amount];
            } elseif ($role === 'landlord_deduction') {
                $deductions[] = ['item', (string) ($line->narration ?: $line->entry_description), (string) ($line->transaction_ref ?? ''), $this->shortDate($line->source_date ?? null), $amount, $this->sortStamp($line->source_date ?? null)];
            }
        }

        $recoveriesTotal = round(array_sum(array_column($recoveries, 4)), 2);
        $openingTotal = round(array_sum(array_column($opening, 4)), 2);
        $nettIncome = round($summary['total_income'] - $recoveriesTotal, 2);
        $stored = $return->net_amount ?? null;

        // Ledger debits tagged to this return whose line is missing (lost when the old returns were loaded) are listed
        // only when the lines alone do not reach the stored net and the gaps bring them to it; otherwise they are not
        // deductions of this return and the gap row below says by how much the lines differ.
        $gapRows = [];
        foreach ($ledgerGaps as $gap) {
            $reference = trim((string) ($gap->payment_ref ?? '') . ' ' . (string) ($gap->payment_ref_type ?? ''));
            $gapRows[] = ['item', (string) ($gap->entry_particulars ?: $gap->entry_description), $reference, $this->shortDate($gap->entry_date ?? null), (float) $gap->debit_amount, $this->sortStamp($gap->entry_date ?? null)];
        }
        $linesPayable = round($nettIncome + $openingTotal - array_sum(array_column($deductions, 4)), 2);
        $gapTotal = round(array_sum(array_column($gapRows, 4)), 2);
        if ($gapRows && $stored !== null && abs($linesPayable - (float) $stored) > 1.0 && abs($linesPayable - $gapTotal - (float) $stored) <= 1.0) {
            $deductions = array_merge($deductions, $gapRows);
        }

        // Landlord-account deductions by the date of the ledger entry (the sort is stable, so equal dates keep line order).
        usort($deductions, fn($a, $b) => $a[5] <=> $b[5]);

        $deductionsTotal = round(array_sum(array_column($deductions, 4)), 2);
        $payable = round($nettIncome + $openingTotal - $deductionsTotal, 2);
        $overdraft = round((float) ($return->amount_overdraft ?? 0), 2);

        $rows = $recoveries;
        $rows[] = ['strong', 'I. Total deductions', '', '', $recoveriesTotal];
        $rows[] = ['strong', 'J. Nett income (H - I)', '', '', $nettIncome];
        $rows = array_merge($rows, $opening);
        $rows[] = ['strong', 'K. Add opening balance and other receipts', '', '', $openingTotal];
        $rows = array_merge($rows, $deductions);
        $rows[] = ['strong', 'L. Total deductions from landlord account', '', '', $deductionsTotal];
        $rows[] = ['closing', 'M. Amount payable (J + K - L)', '', '', $payable];
        $rows[] = ['plain', 'N. Less overdraft charge', '', '', $overdraft];
        $rows[] = ['closing', 'O. Nett balance (M - N)', '', '', round($payable - $overdraft, 2)];

        // The stored net is what the voucher and the ledger carry; say so when the lines do not add up to it.
        if ($stored !== null && abs($payable - (float) $stored) > 1.0) {
            $rows[] = ['gap', 'Lines differ from the return\'s stored net amount of ' . number_format((float) $stored, 2) . ' by', '', '', round($payable - (float) $stored, 2)];
        }

        return array_map(fn($row) => array_combine(['kind', 'label', 'ref', 'date', 'amount'], array_slice($row, 0, 5)), $rows);
    }

    /**
     * The arrears paid on the return: the date, what the rent was for, the room and the amount, in room order.
     *
     * @param ReturnSummary $return
     * @param Collection $lines
     * @return array
     */
    public function arrearsDetails(ReturnSummary $return, Collection $lines): array {
        $arrears = $lines->filter(fn($line) => ($line->dr_cr ?? 'CR') !== 'DR' && strtoupper(trim($line->entry_type ?? '')) === 'RENT ARREARS')
            ->sort(fn($a, $b) => ($a->unit_seq_id ?? PHP_INT_MAX) <=> ($b->unit_seq_id ?? PHP_INT_MAX)
                ?: strnatcasecmp($this->roomLabel($a), $this->roomLabel($b))
                ?: $this->rentMonth($a) <=> $this->rentMonth($b))->values();

        return $arrears->map(fn($line) => [
            'date' => $this->shortDate($line->transaction_date ?? null) ?: $this->shortDate($return->prepared_date ?? null),
            'details' => (string) ($line->narration ?: $line->entry_description),
            'room' => $this->roomLabel($line),
            'amount' => (float) ($line->net_amount ?? 0),
        ])->all();
    }

    /**
     * The totals of a return's lines for the processing card on the details pages: credits, debits, rent, commission,
     * the VAT on commission, and the rent VAT deducted and added back. Debits leave out the repeat 'COMMISSION' line, and
     * the commission and VAT on commission carried on generated rent lines are debits. The rent VAT an old-system rent
     * line holds in vat_amount is not a debit.
     *
     * @param string $returnCode
     * @return object
     */
    public function lineTotals(string $returnCode): object {
        return DB::table('cb_returns_clients')->where('return_code', $returnCode)->selectRaw("
            SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END) as total_cr,
            SUM(CASE WHEN dr_cr = 'DR' AND COALESCE(entry_type, '') <> 'COMMISSION' THEN net_amount ELSE 0 END)
              + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END)
              + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') AND COALESCE(commission_amount, 0) > 0 THEN COALESCE(vat_amount, 0) ELSE 0 END) as total_dr,
            SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) as rent_total,
            SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
              + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission,
            SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
              + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') AND COALESCE(commission_amount, 0) > 0 THEN COALESCE(vat_amount, 0) ELSE 0 END) as vat,
            SUM(CASE WHEN entry_type = 'Deductions Before Commission' AND transaction_source = 'COMPUTED' THEN net_amount ELSE 0 END) as deductions,
            SUM(CASE WHEN entry_type = 'Add Back Deductions' THEN net_amount ELSE 0 END) as add_back
        ")->first();
    }

    /**
     * Ledger debits tagged to the return that have no line on it. A line matches a ledger row by the ledger row's own
     * transaction number or by the number the return gave it. Credits are left out: the credit a return posts back to the
     * landlord's ledger (its own nett income) is tagged to it too and is not a missing line. So are the ledger's own balance
     * rows (opening balance B/F, code 0008, and closing balance C/F, code 00014), which carry a balance and are not deductions.
     *
     * @param ReturnSummary $return
     * @param Collection $lines
     * @return Collection
     */
    public function ledgerGaps(ReturnSummary $return, Collection $lines): Collection {
        return DB::table('cb_landlord_account as la')
            ->where('la.return_code', $return->return_code)
            ->where('la.debit_amount', '>', 0)
            ->whereNotIn('la.entry_type_code', ['0008', '00014'])
            ->whereNotExists(fn($query) => $query->select(DB::raw(1))->from('cb_returns_clients as c')
                ->whereColumn('c.return_code', 'la.return_code')
                ->where(fn($match) => $match->whereColumn('c.transaction_number', 'la.return_transaction')->orWhereColumn('c.transaction_number', 'la.transaction_number')))
            ->orderBy('la.id')
            ->get(['la.entry_description', 'la.entry_particulars', 'la.payment_ref', 'la.payment_ref_type', 'la.entry_date', 'la.debit_amount']);
    }

    /**
     * Where a line belongs on the statement. Credits are tenant-column money (column), other income that has no column
     * (other_income), the rent VAT added back (rent_vat_addback), the opening balance and other receipts from the
     * landlord account (opening), or a master return's per-property sum (net_sum). Debits are the rent VAT deducted
     * (rent_vat), the commission (commission), the VAT on it (commission_vat), the repeat of the commission on a
     * generated return (repeat), recoveries (recovery), or landlord-account deductions (landlord_deduction).
     *
     * @param object $line
     * @param bool $perLine whether the return's rent lines carry the commission themselves
     * @return string
     */
    private function roleOf($line, bool $perLine): string {
        $type = strtoupper(trim($line->entry_type ?? ''));

        if (($line->dr_cr ?? 'CR') !== 'DR') {
            return match (true) {
                $type === 'ADD BACK DEDUCTIONS' => 'rent_vat_addback',
                str_starts_with($type, 'OTHER RECEIPTS') => 'opening',
                $type === 'RETURN NET SUM' => 'net_sum',
                $this->columnFor($line) !== null => 'column',
                default => 'other_income',
            };
        }

        $source = strtoupper(trim($line->transaction_source ?? ''));

        return match (true) {
            $type === 'DEDUCTIONS BEFORE COMMISSION' && $source === 'COMPUTED' => 'rent_vat',
            $type === 'VAT' => 'commission_vat',
            $type === 'COMMISSION' => $perLine ? 'repeat' : 'commission',
            $type === 'RECOVERIES' || $type === 'DEDUCTIONS BEFORE COMMISSION' || in_array($type, ['PAID OWNERS', 'RENT'], true) => 'recovery',
            default => 'landlord_deduction',
        };
    }

    /**
     * The tenant column a credit line feeds, or null when it has none. A line of the old system's catch-all type
     * 'OTHER PAYMENTS/DEPOSITS' is named by its description; any other line by its entry type. Only credits feed columns:
     * a debit named 'RENT' (a landlord-account deduction) is not rent income.
     *
     * @param object $line
     * @return string|null
     */
    private function columnFor($line): ?string {
        if (($line->dr_cr ?? 'CR') === 'DR') {
            return null;
        }

        $type = strtoupper(trim($line->entry_type ?? ''));
        $description = strtoupper(trim($line->entry_description ?? ''));
        $description = self::ALIASES[$description] ?? $description;
        $name = $type === 'OTHER PAYMENTS/DEPOSITS' ? $description : (self::ALIASES[$type] ?? $type);

        return match (true) {
            str_contains($name, 'HOUSE') && str_contains($name, 'DEPOSIT') => 'house_deposit_amt',
            str_contains($name, 'ELEC') && str_contains($name, 'DEPOSIT') => 'elec_deposit_amt',
            str_contains($name, 'WATER') && str_contains($name, 'DEPOSIT') => 'water_deposit_amt',
            str_contains($name, 'RENT ARREARS') || str_contains($description, 'RENT ARREARS')
                || str_contains($name, 'PREPAY') || str_contains($description, 'PREPAY') => 'rent_arrears',
            str_contains($name, 'RENT') || str_contains($description, 'RENT') => 'monthly_rent',
            str_contains($name, 'ELEC') || str_contains($description, 'ELEC') => 'elec_bill',
            str_contains($name, 'WATER') || str_contains($description, 'WATER') => 'water_bill',
            str_contains($name, 'SERVICE') || str_contains($description, 'SERVICE') => 'service_charge',
            default => null,
        };
    }

    /**
     * Whether the return's rent lines carry the commission themselves (a return generated here) rather than one
     * 'Commission' debit line (an old-system return).
     *
     * @param Collection $lines
     * @return bool
     */
    private function commissionOnRentLines(Collection $lines): bool {
        return $lines->contains(fn($line) => in_array($line->entry_type, ['RENT CURRENT', 'RENT ARREARS']) && (float) ($line->commission_amount ?? 0) > 0);
    }

    /**
     * The sum of the net amounts of the lines with one role.
     *
     * @param Collection $lines
     * @param bool $perLine
     * @param string $role
     * @return float
     */
    private function lineSum(Collection $lines, bool $perLine, string $role): float {
        return (float) $lines->filter(fn($line) => $this->roleOf($line, $perLine) === $role)->sum(fn($line) => (float) ($line->net_amount ?? 0));
    }

    /**
     * Which units on the lines are VAT units. A line from the old system says so itself (is_vatable); a line generated
     * here does not, so its unit is looked up.
     *
     * @param Collection $lines
     * @return array unit id => bool
     */
    private function vatableUnits(Collection $lines): array {
        $ids = $lines->filter(fn($line) => ($line->is_vatable ?? null) === null && !empty($line->unit_id))->pluck('unit_id')->unique()->values()->all();

        return $ids ? DB::table('sys_pm_units')->whereIn('id', $ids)->pluck('vat_able', 'id')->map(fn($flag) => $flag === 'Yes')->all() : [];
    }

    /**
     * The room as printed: the unit code without the property prefix a unit created here carries ('1048E-001' is room 001),
     * which the old system's lines never had.
     *
     * @param object $line
     * @return string
     */
    private function roomLabel($line): string {
        $room = trim((string) ($line->unit_code ?? ''));
        $property = trim((string) ($line->property_code ?? ''));
        $prefix = $property . '-';

        return $property !== '' && strncasecmp($room, $prefix, strlen($prefix)) === 0
            ? substr($room, strlen($prefix))
            : $room;
    }

    /**
     * The month an arrears line's rent was for, as a timestamp ('RENT FOR May 2026'), or 0 when the narration does not say.
     *
     * @param object $line
     * @return int
     */
    private function rentMonth($line): int {
        $month = trim((string) preg_replace('/^rent for\s+/i', '', (string) ($line->narration ?? '')));

        return $month === '' ? 0 : (int) strtotime('1 ' . $month);
    }

    /**
     * A date or datetime as a timestamp to sort by; lines with no date sort last.
     *
     * @param mixed $value
     * @return int
     */
    private function sortStamp($value): int {
        if ($value === null || $value === '') {
            return PHP_INT_MAX;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable $e) {
            return PHP_INT_MAX;
        }
    }

    /**
     * A date as printed on the statement (13-Jul-2026), or an empty string when there is none or it cannot be read.
     *
     * @param mixed $value
     * @return string
     */
    private function shortDate($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-M-Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}

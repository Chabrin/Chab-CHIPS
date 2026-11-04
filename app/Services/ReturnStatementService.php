<?php

namespace App\Services;

use App\Models\ReturnSummary;
use Illuminate\Support\Collection;

/** The same tenant columns and row remarks for the screen, PDF and Excel statement. */
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

    public function summary(ReturnSummary $return, Collection $lines, array $totals): array
    {
        $rent = $lines->filter(fn($line) => in_array($line->entry_type, ['RENT CURRENT', 'RENT ARREARS']));
        $commission = (float) $lines->where('entry_type', 'Commission')->sum('net_amount') + $rent->sum('commission_amount');
        $vat = (float) $lines->where('entry_type', 'VAT')->sum('net_amount') + $rent->sum('vat_amount');
        $commissionable = (float) ($return->commission_total ?? 0);
        return [
            'total_rent' => (float) $rent->sum('net_amount'), 'comm_total' => $commissionable,
            'commission' => $commission, 'bal_after_comm' => $commissionable - $commission,
            'vat' => $vat, 'bal_less_vat' => $commissionable - $commission - $vat,
            'house_deposits' => $totals['house_deposit'],
            'water_elec_bills' => $totals['elec_bill'] + $totals['water_bill'],
            'net_income' => $commissionable - $commission - $vat + $totals['house_deposit'] + $totals['elec_bill'] + $totals['water_bill'],
        ];
    }

    public function summaryLabels(ReturnSummary $return): array
    {
        return [
            'total_rent' => 'Total rent', 'comm_total' => 'Commissionable amount',
            'commission' => 'Less commission (' . number_format($return->commission_rate ?? 0, 2) . '%)',
            'bal_after_comm' => 'Balance after commission', 'vat' => 'Less VAT on commission',
            'bal_less_vat' => 'Balance less VAT & commission', 'house_deposits' => 'Add house deposits',
            'water_elec_bills' => 'Add water & elec bills', 'net_income' => 'Net income',
        ];
    }

    public function build(Collection $lines): array
    {
        $rows = [];
        $numeric = array_diff(array_keys(self::COLUMNS), ['unit_code', 'tenant_name', 'remarks']);
        $totals = array_fill_keys($numeric, 0.0);
        foreach ($lines as $line) {
            $type = strtoupper(trim($line->entry_type ?? ''));
            $description = strtoupper(trim($line->entry_description ?? ''));
            $field = match (true) {
                str_contains($type, 'HOUSE') && str_contains($type, 'DEPOSIT') => 'house_deposit_amt',
                str_contains($type, 'ELEC') && str_contains($type, 'DEPOSIT') => 'elec_deposit_amt',
                str_contains($type, 'WATER') && str_contains($type, 'DEPOSIT') => 'water_deposit_amt',
                str_contains($type, 'RENT ARREARS') || str_contains($description, 'RENT ARREARS')
                    || str_contains($type, 'PREPAY') || str_contains($description, 'PREPAY') => 'rent_arrears',
                str_contains($type, 'RENT') || str_contains($description, 'RENT') => 'monthly_rent',
                str_contains($type, 'ELEC') || str_contains($description, 'ELEC') => 'elec_bill',
                str_contains($type, 'WATER') || str_contains($description, 'WATER') => 'water_bill',
                str_contains($type, 'SERVICE') || str_contains($description, 'SERVICE') => 'service_charge',
                default => null,
            };
            if ($field === null) {
                continue;
            }

            // A merged statement must not combine identically named rooms in different properties.
            $key = json_encode([$line->property_code, $line->tenant_name, $line->unit_code]);
            if (!isset($rows[$key])) {
                $rows[$key] = array_merge(array_fill_keys($numeric, 0.0), [
                    'unit_code' => $line->unit_code ?? '-', 'tenant_name' => $line->tenant_name ?? '-',
                    'remarks' => '', 'remarks_line_id' => null, 'remarks_priority' => null,
                ]);
            }
            $amount = (float) ($line->net_amount ?? 0);
            $rows[$key][$field] += $amount;
            $totals[$field] += $amount;
            $vat = (float) ($line->vat_amount ?? 0);
            $rows[$key]['vat'] += $vat;
            $totals['vat'] += $vat;

            // Prefer the current rent line for the editable tenant note; never clear other line notes.
            $priority = [$type === 'RENT CURRENT' ? 0 : 1, (int) $line->id];
            if ($rows[$key]['remarks_priority'] === null || $priority < $rows[$key]['remarks_priority']) {
                $rows[$key]['remarks_priority'] = $priority;
                $rows[$key]['remarks_line_id'] = $line->id;
                $rows[$key]['remarks'] = (string) ($line->remarks ?? '');
            }
        }

        foreach ($rows as &$row) {
            // Preserve the statement's existing payable/monthly-rent figures.
            $row['payable'] = $row['monthly_rent'];
            unset($row['remarks_priority']);
        }
        unset($row);
        $totals['payable'] = $totals['monthly_rent'];
        $rows = collect($rows)->sort(fn($a, $b) => strnatcasecmp($a['unit_code'], $b['unit_code'])
            ?: strcasecmp($a['tenant_name'], $b['tenant_name']))->values()->all();

        // Retain aliases consumed by the existing financial summary.
        $totals['house_deposit'] = $totals['house_deposit_amt'];
        $totals['elec_deposit'] = $totals['elec_deposit_amt'];
        $totals['water_deposit'] = $totals['water_deposit_amt'];

        return ['tenantRows' => $rows, 'totals' => $totals];
    }
}

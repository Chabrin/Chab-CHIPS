<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Unprocessed Return - {{ $return->return_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 20px; }
        h2 { text-align: center; font-size: 16px; margin-bottom: 4px; color: #000; }
        .subtitle { text-align: center; font-size: 12px; color: #000; margin-bottom: 20px; }
        .meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-grid td { padding: 4px 8px; font-size: 11px; border: 1px solid #000; }
        .meta-grid .label { width: 140px; }
        .meta-grid .val { font-weight: bold; }
        .summary-box { border: 1px solid #000; padding: 10px 14px; margin-bottom: 16px; }
        .summary-box table { width: 100%; border-collapse: collapse; }
        .summary-box td { padding: 3px 8px; font-size: 11px; }
        .summary-box .lbl { width: 160px; }
        .summary-box .val { font-weight: bold; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.details th { border: 1px solid #000; padding: 6px 8px; text-align: center; font-size: 10px; font-weight: bold; }
        table.details td { padding: 5px 8px; border: 1px solid #000; text-align: center; font-size: 10px; }
        table.details td.text-left { text-align: left; }
        table.details tfoot td { font-weight: bold; }
        .footer { margin-top: 20px; font-size: 9px; color: #000; text-align: center; }
    </style>
</head>
<body>

<h2>Unprocessed Return</h2>
<div class="subtitle">Generated on {{ now()->format('d M Y, H:i') }}</div>

<table class="meta-grid">
    <tr>
        <td class="label">Return Code:</td>
        <td class="val">{{ $return->return_code ?? '-' }}</td>
        <td class="label">Landlord:</td>
        <td class="val">{{ $return->landlord_code ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Property:</td>
        <td class="val">{{ $return->property_code ?? '-' }}</td>
        <td class="label">Period:</td>
        <td class="val">{{ str_pad($return->month, 2, '0', STR_PAD_LEFT) ?? '' }}/{{ $return->year ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">Return Type:</td>
        <td class="val">{{ $return->return_type ?? '-' }}</td>
        <td class="label">Status:</td>
        <td class="val">UNPAID</td>
    </tr>
</table>

@php
    $summaryStats = [
        'rent_total' => 0,
        'commission' => 0,
        'vat' => 0,
        'debits' => 0,
    ];

    foreach ($lines as $line) {
        if ($line->entry_type === 'RENT CURRENT') {
            $summaryStats['rent_total'] += (float)($line->net_amount ?? 0);
            $summaryStats['commission'] += (float)($line->commission_amount ?? 0);
            $summaryStats['vat'] += (float)($line->vat_amount ?? 0);
        } elseif ($line->dr_cr === 'DR') {
            $summaryStats['debits'] += (float)($line->net_amount ?? 0);
        }
    }

    $netAmount = $summaryStats['rent_total'] - $summaryStats['commission'] - $summaryStats['vat'] - $summaryStats['debits'];
@endphp

<div class="summary-box">
    <table>
        <tr>
            <td class="lbl">Total Rent (CR):</td>
            <td class="val">KSh {{ number_format($summaryStats['rent_total'], 2) }}</td>
            <td class="lbl">Commission:</td>
            <td class="val">KSh {{ number_format($summaryStats['commission'], 2) }}</td>
        </tr>
        <tr>
            <td class="lbl">VAT:</td>
            <td class="val">KSh {{ number_format($summaryStats['vat'], 2) }}</td>
            <td class="lbl">Deductions (DR):</td>
            <td class="val">KSh {{ number_format($summaryStats['debits'], 2) }}</td>
        </tr>
        <tr>
            <td class="lbl">Net Amount Payable:</td>
            <td class="val">KSh {{ number_format($netAmount, 2) }}</td>
            <td class="lbl">&nbsp;</td>
            <td class="val">&nbsp;</td>
        </tr>
    </table>
</div>

<table class="details">
    <thead>
        <tr>
            <th>#</th>
            <th>Entry Type</th>
            <th>Tenant</th>
            <th>Unit</th>
            <th>Description</th>
            <th>Dr/Cr</th>
            <th>Landlord Amt</th>
            <th>Net</th>
            <th>Commission</th>
            <th>VAT</th>
            <th>Pay %</th>
            <th>Payable?</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lines as $i => $line)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $line->entry_type ?? '-' }}</td>
            <td class="text-left">{{ $line->tenant_name ?? '-' }}</td>
            <td>{{ $line->unit_code ?? '-' }}</td>
            <td class="text-left">{{ $line->entry_description ?? '-' }}</td>
            <td>{{ $line->dr_cr ?? '-' }}</td>
            <td>{{ number_format($line->amount_payable_landlord ?? 0, 2) }}</td>
            <td>{{ number_format($line->net_amount ?? 0, 2) }}</td>
            <td>{{ number_format($line->commission_amount ?? 0, 2) }}</td>
            <td>{{ number_format($line->vat_amount ?? 0, 2) }}</td>
            <td>{{ ($line->pay_percentage ?? 0) > 0 ? number_format($line->pay_percentage, 2) : '0.00' }}%</td>
            <td>{{ $line->payable_status == 1 ? 'Yes' : 'No' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    This document was generated by the Property Management System. For queries, contact your property manager.
</div>

</body>
</html>

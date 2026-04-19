<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Advance Repayment Schedule - {{ $advance->reference_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 20px; }
        h2 { text-align: center; font-size: 16px; margin-bottom: 4px; color: #2c3e50; }
        .subtitle { text-align: center; font-size: 12px; color: #666; margin-bottom: 20px; }
        .meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-grid td { padding: 4px 8px; font-size: 11px; }
        .meta-grid .label { color: #888; width: 140px; }
        .meta-grid .val { font-weight: bold; }
        .summary-box { background: #f0f4ff; border: 1px solid #c3d0f5; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; }
        .summary-box table { width: 100%; border-collapse: collapse; }
        .summary-box td { padding: 3px 8px; font-size: 11px; }
        .summary-box .lbl { color: #555; width: 160px; }
        .summary-box .val { font-weight: bold; }
        .summary-box .total { color: #1a7a4a; font-size: 13px; }
        table.schedule { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.schedule th { background: #2c3e50; color: #fff; padding: 6px 8px; text-align: center; font-size: 10px; }
        table.schedule td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; text-align: center; font-size: 10px; }
        table.schedule tr:nth-child(even) { background: #f9f9f9; }
        table.schedule tfoot td { background: #eaf0ff; font-weight: bold; border-top: 2px solid #2c3e50; }
        .type-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .type-1 { background: #fff3cd; color: #856404; }
        .type-2 { background: #cfe2ff; color: #084298; }
        .type-3 { background: #d1ecf1; color: #0c5460; }
        .footer { margin-top: 20px; font-size: 9px; color: #aaa; text-align: center; }
        .status-pending { color: #856404; }
        .status-cleared { color: #155724; }
        .status-cancelled { color: #721c24; }
        .status-posted { color: #155724; }
    </style>
</head>
<body>

<h2>Client Advance — Repayment Schedule</h2>
<div class="subtitle">Generated on {{ now()->format('d M Y, H:i') }}</div>

<table class="meta-grid">
    <tr>
        <td class="label">Reference:</td>
        <td class="val">{{ $advance->reference_number ?? '—' }}</td>
        <td class="label">Landlord:</td>
        <td class="val">{{ $advance->client->names ?? '—' }} ({{ $advance->client->reference_number ?? '' }})</td>
    </tr>
    <tr>
        <td class="label">Property:</td>
        <td class="val">{{ $advance->property->property_name ?? '—' }}</td>
        <td class="label">Advance Type:</td>
        <td class="val">
            @if($advance->advance_type_id == 1)
                <span class="type-badge type-1">Expense Advance</span>
            @elseif($advance->advance_type_id == 2)
                <span class="type-badge type-2">Client Payment Advance</span>
            @else
                <span class="type-badge type-3">Overpayment Advance</span>
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">Requisition Date:</td>
        <td class="val">{{ $advance->requisition_date ? $advance->requisition_date->format('d M Y') : '—' }}</td>
        <td class="label">Disbursement Date:</td>
        <td class="val">{{ $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : '—' }}</td>
    </tr>
    @if($advance->advance_type_id == 3 && $advance->principal_start_date)
    <tr>
        <td class="label">Principal Start Date:</td>
        <td class="val">{{ $advance->principal_start_date->format('d M Y') }}</td>
        <td class="label">Repayment Months:</td>
        <td class="val">{{ $advance->repayment_months ?? '—' }}</td>
    </tr>
    @endif
</table>

<div class="summary-box">
    <table>
        <tr>
            <td class="lbl">Principal Amount:</td>
            <td class="val">KSh {{ number_format($advance->amount, 2) }}</td>
            <td class="lbl">Percentage:</td>
            <td class="val">{{ $advance->interest_rate ?? 0 }}%</td>
        </tr>
        <tr>
            <td class="lbl">Interest Amount:</td>
            <td class="val">KSh {{ number_format($advance->interest_amount ?? 0, 2) }}</td>
            <td class="lbl">Processing Charge:</td>
            <td class="val">KSh {{ number_format($advance->charge_amount ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td class="lbl">Total Amount:</td>
            <td class="val total">KSh {{ number_format($advance->total_amount, 2) }}</td>
            <td class="lbl">Current Balance:</td>
            <td class="val total">KSh {{ number_format($advance->current_balance, 2) }}</td>
        </tr>
    </table>
</div>

@php
    $statusLabels = [1=>'Pending',2=>'Cleared',3=>'Cancelled',4=>'Suspended',5=>'Approved',6=>'Posted',8=>'Rescheduled'];
    $statusClasses = [1=>'status-pending',2=>'status-cleared',3=>'status-cancelled',4=>'status-cancelled',5=>'status-cleared',6=>'status-posted'];
    $totalPrincipal = array_sum(array_column($schedule, 'principal_amount'));
    $totalInterest  = array_sum(array_column($schedule, 'interest'));
    $totalPayment   = array_sum(array_column($schedule, 'total_amount'));
@endphp

<table class="schedule">
    <thead>
        <tr>
            <th>#</th>
            <th>Due Date</th>
            <th>Principal (KSh)</th>
            <th>Interest (KSh)</th>
            <th>Payment (KSh)</th>
            <th>Balance (KSh)</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($schedule as $i => $entry)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $entry['payment_date'] }}</td>
            <td>{{ number_format($entry['principal_amount'], 2) }}</td>
            <td>{{ number_format($entry['interest'], 2) }}</td>
            <td>{{ number_format($entry['total_amount'], 2) }}</td>
            <td>{{ number_format($entry['loan_balance'], 2) }}</td>
            <td class="{{ $statusClasses[$entry['status_id'] ?? 1] ?? '' }}">
                {{ $statusLabels[$entry['status_id'] ?? 1] ?? '—' }}
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Totals</td>
            <td>{{ number_format($totalPrincipal, 2) }}</td>
            <td>{{ number_format($totalInterest, 2) }}</td>
            <td>{{ number_format($totalPayment, 2) }}</td>
            <td>—</td>
            <td>—</td>
        </tr>
    </tfoot>
</table>

@if($advance->narration)
<p style="margin-top:14px; font-size:10px; color:#555;"><strong>Narration:</strong> {{ $advance->narration }}</p>
@endif

<div class="footer">
    This document was generated by the Property Management System. For queries, contact your property manager.
</div>

</body>
</html>

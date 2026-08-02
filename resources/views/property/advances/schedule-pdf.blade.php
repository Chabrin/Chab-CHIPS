<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Advance Repayment Schedule - {{ $advance->reference_number }}</title>
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
        table.schedule { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.schedule th { border: 1px solid #000; padding: 6px 8px; text-align: center; font-size: 10px; font-weight: bold; }
        table.schedule td { padding: 5px 8px; border: 1px solid #000; text-align: center; font-size: 10px; }
        table.schedule tfoot td { font-weight: bold; }
        .footer { margin-top: 20px; font-size: 9px; color: #000; text-align: center; }
    </style>
</head>
<body>

<h2>Client Advance &mdash; Repayment Schedule</h2>
<div class="subtitle">Generated on {{ now()->format('d M Y, H:i') }}</div>

<table class="meta-grid">
    <tr>
        <td class="label">Reference:</td>
        <td class="val">{{ $advance->reference_number ?? '-' }}</td>
        <td class="label">Landlord:</td>
        <td class="val">{{ $advance->client->names ?? '-' }} ({{ $advance->client->reference_number ?? '' }})</td>
    </tr>
    <tr>
        <td class="label">Property:</td>
        <td class="val">{{ $advance->property->property_name ?? '-' }}</td>
        <td class="label">Advance Type:</td>
        <td class="val">
            @if($advance->advance_type_id == 2)
                Client Payment Advance
            @else
                Overpayment Advance
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">Requisition Date:</td>
        <td class="val">{{ $advance->requisition_date ? $advance->requisition_date->format('d M Y') : '-' }}</td>
        <td class="label">Disbursement Date:</td>
        <td class="val">{{ $advance->disbursement_date ? $advance->disbursement_date->format('d M Y') : '-' }}</td>
    </tr>
    @if($advance->principal_start_date)
    <tr>
        <td class="label">Payment Start Date:</td>
        <td class="val">{{ $advance->principal_start_date->format('d M Y') }}</td>
        <td class="label">Repayment Months:</td>
        <td class="val">{{ $advance->repayment_months ?? '-' }}</td>
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
            <td class="val">KSh {{ number_format($advance->total_amount, 2) }}</td>
            <td class="lbl">Current Balance:</td>
            <td class="val">KSh {{ number_format($advance->current_balance, 2) }}</td>
        </tr>
    </table>
</div>

@php
    $statusLabels = [1=>'Pending',2=>'Cleared',3=>'Cancelled',4=>'Suspended',5=>'Approved',6=>'Posted',8=>'Rescheduled'];
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
            <td>{{ $statusLabels[$entry['status_id'] ?? 1] ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Totals</td>
            <td>{{ number_format($totalPrincipal, 2) }}</td>
            <td>{{ number_format($totalInterest, 2) }}</td>
            <td>{{ number_format($totalPayment, 2) }}</td>
            <td>-</td>
            <td>-</td>
        </tr>
    </tfoot>
</table>

@if($advance->narration)
<p style="margin-top:14px; font-size:10px;"><strong>Narration:</strong> {{ $advance->narration }}</p>
@endif

<div class="footer">
    This document was generated by the Property Management System. For queries, contact your property manager.
</div>

</body>
</html>

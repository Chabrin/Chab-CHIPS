<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tenant Exit Clearance Letter</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 28px 36px; }
        h2 { font-size: 15px; color: #1a1a2e; margin-bottom: 2px; text-align: center; }
        .subtitle { font-size: 10px; color: #888; text-align: center; margin-bottom: 20px; }
        .section-title { font-size: 11px; font-weight: bold; color: #1a1a2e; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { padding: 4px 6px; font-size: 11px; }
        .meta .lbl { color: #888; width: 160px; }
        .meta .val { font-weight: bold; }
        .box { border: 1px solid #dce3f0; background: #f7f9ff; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; }
        .box .row { display: flex; margin-bottom: 4px; }
        .box .k { color: #888; width: 180px; font-size: 10px; }
        .box .v { font-weight: bold; font-size: 10px; }
        .balance-pos { color: #155724; font-weight: bold; }
        .balance-neg { color: #721c24; font-weight: bold; }
        .sig-area { margin-top: 40px; }
        .sig-line { border-top: 1px solid #666; width: 220px; margin-top: 40px; font-size: 10px; color: #555; padding-top: 4px; }
        .footer { margin-top: 30px; font-size: 9px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; }
        .badge-green { background: #d1ecf1; color: #0c5460; }
        .badge-red   { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<h2>TENANCY EXIT CLEARANCE LETTER</h2>
<div class="subtitle">Issued on {{ now()->format('d M Y') }}</div>

<div class="section-title">Tenant Details</div>
<table class="meta">
    <tr>
        <td class="lbl">Tenant Name:</td>
        <td class="val">{{ $tenant->names ?? '—' }}</td>
        <td class="lbl">Reference No:</td>
        <td class="val">{{ $tenant->reference_number ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">National ID / Passport:</td>
        <td class="val">{{ $tenant->national_id ?? '—' }}</td>
        <td class="lbl">Mobile:</td>
        <td class="val">{{ $tenant->mobile_number ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Email:</td>
        <td class="val">{{ $tenant->email_address ?? '—' }}</td>
        <td></td><td></td>
    </tr>
</table>

<div class="section-title">Property & Unit</div>
<table class="meta">
    <tr>
        <td class="lbl">Property:</td>
        <td class="val">{{ $property->property_name ?? '—' }}</td>
        <td class="lbl">Unit:</td>
        <td class="val">{{ $unit->unit_name ?? '—' }}</td>
    </tr>
    @if($landlord)
    <tr>
        <td class="lbl">Landlord:</td>
        <td class="val">{{ $landlord->names }}</td>
        <td class="lbl">Landlord Contact:</td>
        <td class="val">{{ $landlord->mobile_number ?? '—' }}</td>
    </tr>
    @endif
</table>

<div class="section-title">Exit Details</div>
<table class="meta">
    <tr>
        <td class="lbl">Vacate Notice Date:</td>
        <td class="val">{{ $exit->vacate_notice_date ? $exit->vacate_notice_date->format('d M Y') : '—' }}</td>
        <td class="lbl">Intended Exit Date:</td>
        <td class="val">{{ $exit->intended_exit_date ? $exit->intended_exit_date->format('d M Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Actual Exit Date:</td>
        <td class="val">{{ $exit->actual_exit_date ? $exit->actual_exit_date->format('d M Y') : '—' }}</td>
        <td class="lbl">Reason:</td>
        <td class="val">{{ $exit->exit_reason ?? '—' }}</td>
    </tr>
</table>

@if($exit->inspection)
<div class="section-title">Unit Condition at Exit</div>
<table class="meta">
    <tr>
        <td class="lbl">Overall Condition:</td>
        <td class="val">{{ $exit->inspection->overall_condition ?? '—' }}</td>
        <td class="lbl">Keys Returned:</td>
        <td class="val">{{ $exit->inspection->keys_returned ?? '—' }}</td>
    </tr>
    @if($exit->inspection->damage_amount > 0)
    <tr>
        <td class="lbl">Damage Assessment:</td>
        <td class="val" style="color:#721c24;">KES {{ number_format($exit->inspection->damage_amount, 2) }}</td>
        <td class="lbl">Cleaning Cost:</td>
        <td class="val">{{ $exit->inspection->cleaning_cost > 0 ? 'KES ' . number_format($exit->inspection->cleaning_cost, 2) : 'None' }}</td>
    </tr>
    @endif
</table>
@endif

<div class="section-title">Deposit Refund</div>
<table class="meta">
    <tr>
        <td class="lbl">Total Deposit Refunded:</td>
        <td class="val {{ $totalRefunded > 0 ? 'balance-pos' : '' }}">
            KES {{ number_format($totalRefunded, 2) }}
        </td>
        <td></td><td></td>
    </tr>
    @foreach($exit->depositRefunds as $refund)
    <tr>
        <td class="lbl" style="padding-left:20px;">Refund on {{ $refund->refunded_at ? $refund->refunded_at->format('d M Y') : '—' }}:</td>
        <td class="val">KES {{ number_format($refund->refund_amount, 2) }}</td>
        <td class="lbl">Notes:</td>
        <td>{{ $refund->refund_notes ?? '—' }}</td>
    </tr>
    @endforeach
</table>

@if($exit->final_notes)
<div class="section-title">Closing Notes</div>
<p style="font-size:10px;color:#555;margin:4px 0;">{{ $exit->final_notes }}</p>
@endif

<div class="sig-area">
    <table width="100%">
        <tr>
            <td style="width:50%">
                <div class="sig-line">Authorised Signatory<br><span style="font-size:9px;color:#888;">Property Management</span></div>
            </td>
            <td style="width:50%;text-align:right">
                <div class="sig-line" style="margin-left:auto;">Tenant Signature<br><span style="font-size:9px;color:#888;">{{ $tenant->names ?? '' }}</span></div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    This document confirms the formal conclusion of the above tenancy. — Generated {{ now()->format('d M Y, H:i') }}
</div>

</body>
</html>

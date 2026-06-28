<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Unit Inspection Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 28px 36px; }
        h2 { font-size: 15px; color: #1a1a2e; margin-bottom: 2px; text-align: center; }
        .subtitle { font-size: 10px; color: #888; text-align: center; margin-bottom: 18px; }
        .section-title { font-size: 11px; font-weight: bold; color: #1a1a2e; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin: 14px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { padding: 3px 6px; font-size: 11px; }
        .meta .lbl { color: #888; width: 150px; }
        .meta .val { font-weight: bold; }
        table.cond { width: 100%; border-collapse: collapse; }
        table.cond th { background: #f0f4ff; color: #1a1a2e; padding: 5px 8px; font-size: 10px; text-align: left; border: 1px solid #dce3f0; }
        table.cond td { padding: 5px 8px; font-size: 10px; border: 1px solid #eee; }
        table.cond tr:nth-child(even) td { background: #fafafa; }
        .cond-Excellent { color: #155724; font-weight: bold; }
        .cond-Good      { color: #1e7e34; }
        .cond-Fair      { color: #856404; }
        .cond-Poor      { color: #c45500; font-weight: bold; }
        .cond-VeryPoor  { color: #721c24; font-weight: bold; }
        .notes-box { border: 1px solid #dce3f0; background: #f9f9f9; padding: 8px 10px; border-radius: 3px; font-size: 10px; color: #444; margin-top: 6px; }
        .sig-line { border-top: 1px solid #666; width: 200px; margin-top: 40px; font-size: 10px; color: #555; padding-top: 4px; }
        .footer { margin-top: 24px; font-size: 9px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
        .amount-bad { color: #721c24; font-weight: bold; }
    </style>
</head>
<body>

<h2>UNIT CONDITION INSPECTION REPORT</h2>
<div class="subtitle">Exit Inspection — {{ $exit->actual_exit_date ? $exit->actual_exit_date->format('d M Y') : 'Date not recorded' }}</div>

<div class="section-title">Tenant & Unit</div>
<table class="meta">
    <tr>
        <td class="lbl">Tenant:</td>
        <td class="val">{{ $tenant->names ?? '—' }} ({{ $tenant->reference_number ?? '—' }})</td>
        <td class="lbl">Property:</td>
        <td class="val">{{ $property->property_name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Unit:</td>
        <td class="val">{{ $unit->unit_name ?? '—' }}</td>
        <td class="lbl">Inspection Date:</td>
        <td class="val">{{ $insp->inspection_date ? $insp->inspection_date->format('d M Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Inspected By:</td>
        <td class="val">{{ $insp->inspected_by_name ?? '—' }}</td>
        <td></td><td></td>
    </tr>
</table>

<div class="section-title">Condition Assessment</div>
<table class="cond">
    <thead>
        <tr><th style="width:50%;">Area</th><th>Condition</th></tr>
    </thead>
    <tbody>
        @php
        $condItems = [
            'Overall Condition'      => $insp->overall_condition,
            'Walls'                  => $insp->walls_condition,
            'Floors'                 => $insp->floors_condition,
            'Ceiling'                => $insp->ceiling_condition,
            'Doors & Locks'          => $insp->doors_condition,
            'Windows'                => $insp->windows_condition,
            'Plumbing'               => $insp->plumbing_condition,
            'Electrical / Fittings'  => $insp->electrical_condition,
        ];
        @endphp
        @foreach($condItems as $label => $val)
        @if($val)
        <tr>
            <td>{{ $label }}</td>
            <td class="cond-{{ str_replace(' ', '', $val) }}">{{ $val }}</td>
        </tr>
        @endif
        @endforeach
    </tbody>
</table>

<div class="section-title">Keys & Access</div>
<table class="meta">
    <tr>
        <td class="lbl">Keys Returned:</td>
        <td class="val">{{ $insp->keys_returned ?? '—' }}</td>
        <td class="lbl">Keys Notes:</td>
        <td>{{ $insp->keys_notes ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">Damage & Cleaning</div>
<table class="meta">
    <tr>
        <td class="lbl">Cleaning Required:</td>
        <td class="val">{{ $insp->cleaning_required ?? '—' }}</td>
        <td class="lbl">Cleaning Cost:</td>
        <td class="{{ $insp->cleaning_cost > 0 ? 'amount-bad' : '' }}">
            {{ $insp->cleaning_cost > 0 ? 'KES ' . number_format($insp->cleaning_cost, 2) : 'None' }}
        </td>
    </tr>
    <tr>
        <td class="lbl">Estimated Damage:</td>
        <td class="{{ $insp->damage_amount > 0 ? 'amount-bad val' : 'val' }}">
            {{ $insp->damage_amount > 0 ? 'KES ' . number_format($insp->damage_amount, 2) : 'None' }}
        </td>
        <td></td><td></td>
    </tr>
</table>

@if($insp->damage_description)
<div style="margin-top:6px;">
    <span style="color:#888;font-size:10px;">Damage Description:</span>
    <div class="notes-box">{{ $insp->damage_description }}</div>
</div>
@endif

@if($insp->general_notes)
<div style="margin-top:10px;">
    <span style="color:#888;font-size:10px;">General Notes:</span>
    <div class="notes-box">{{ $insp->general_notes }}</div>
</div>
@endif

<div style="margin-top:28px;">
    <table width="100%">
        <tr>
            <td style="width:50%">
                <div class="sig-line">Inspector Signature<br><span style="font-size:9px;color:#888;">{{ $insp->inspected_by_name ?? '' }}</span></div>
            </td>
            <td style="width:50%;text-align:right">
                <div class="sig-line" style="margin-left:auto;">Tenant Acknowledgement<br><span style="font-size:9px;color:#888;">{{ $tenant->names ?? '' }}</span></div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Unit inspection carried out at time of tenant exit. — Generated {{ now()->format('d M Y, H:i') }}
</div>

</body>
</html>

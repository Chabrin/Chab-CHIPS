<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tenant Account Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 28px 36px; }
        h2 { font-size: 15px; color: #1a1a2e; margin-bottom: 2px; text-align: center; }
        .subtitle { font-size: 10px; color: #888; text-align: center; margin-bottom: 18px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { padding: 3px 6px; font-size: 11px; }
        .meta .lbl { color: #888; width: 140px; }
        .meta .val { font-weight: bold; }
        table.stmt { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.stmt th { background: #1a1a2e; color: #fff; padding: 5px 8px; font-size: 10px; }
        table.stmt td { padding: 4px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        table.stmt tr:nth-child(even) { background: #f9f9f9; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .dr { color: #c0392b; }
        .cr { color: #27ae60; }
        .bal-pos { color: #27ae60; font-weight: bold; }
        .bal-neg { color: #c0392b; font-weight: bold; }
        .closing-row td { background: #eaf0ff; font-weight: bold; border-top: 2px solid #1a1a2e; }
        .footer { margin-top: 20px; font-size: 9px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>

<h2>TENANT ACCOUNT STATEMENT</h2>
<div class="subtitle">All transactions on record as at {{ now()->format('d M Y') }}</div>

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
        <td class="lbl">Exit Date:</td>
        <td class="val">{{ $exit->actual_exit_date ? $exit->actual_exit_date->format('d M Y') : '—' }}</td>
    </tr>
</table>

<table class="stmt">
    <thead>
        <tr>
            <th>Date</th>
            <th>Reference</th>
            <th>Narration</th>
            <th class="text-right">Debit (KES)</th>
            <th class="text-right">Credit (KES)</th>
            <th class="text-right">Balance (KES)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $e)
        <tr>
            <td>{{ $e['date'] }}</td>
            <td>{{ $e['reference'] }}</td>
            <td>{{ $e['narration'] }}</td>
            <td class="text-right dr">{{ $e['debit'] }}</td>
            <td class="text-right cr">{{ $e['credit'] }}</td>
            <td class="text-right {{ $e['is_neg'] ? 'bal-neg' : 'bal-pos' }}">{{ $e['balance'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="text-center" style="color:#888;padding:12px;">No transactions on record.</td>
        </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="closing-row">
            <td colspan="5" class="text-right">Closing Balance:</td>
            <td class="text-right {{ $balance < 0 ? 'bal-neg' : 'bal-pos' }}">
                {{ $balance < 0 ? 'DR ' : 'CR ' }}KES {{ number_format(abs($balance), 2) }}
            </td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    This statement was generated at the time of tenant exit. — {{ now()->format('d M Y, H:i') }}
</div>

</body>
</html>

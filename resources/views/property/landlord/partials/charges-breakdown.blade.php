{{-- The charges in the statement period, by kind (ClientLedgerService::charges). Plain inline styles so the page and the PDF look the same. --}}
@php
    $charges = $st['charges'] ?? null;
    $chargeGroups = [
        'company' => 'Charges by the company',
        'behalf' => 'Paid on your behalf and taken off this account',
    ];
@endphp
@if($charges && (count($charges['company']['rows']) || count($charges['behalf']['rows'])))
<div style="margin-top:16px;">
    <div style="font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:.04em; color:#374151; margin-bottom:6px;">Charges in this period</div>
    @foreach($chargeGroups as $key => $title)
        @if(count($charges[$key]['rows']))
        <table style="width:100%; border-collapse:collapse; font-size:11px; margin-bottom:8px;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:3px 6px; border-bottom:1px solid #d1d5db; color:#6b7486;">{{ $title }}</th>
                    <th style="text-align:right; padding:3px 6px; border-bottom:1px solid #d1d5db; color:#6b7486; width:60px;">Entries</th>
                    <th style="text-align:right; padding:3px 6px; border-bottom:1px solid #d1d5db; color:#6b7486; width:110px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($charges[$key]['rows'] as $item)
                <tr>
                    <td style="padding:3px 6px; border-bottom:1px solid #eef0f3;">{{ $item['label'] }}</td>
                    <td style="padding:3px 6px; border-bottom:1px solid #eef0f3; text-align:right;">{{ $item['count'] }}</td>
                    <td style="padding:3px 6px; border-bottom:1px solid #eef0f3; text-align:right;">{{ number_format($item['amount'], 2) }}</td>
                </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="padding:3px 6px; font-weight:bold;">Total</td>
                    <td style="padding:3px 6px; text-align:right; font-weight:bold;">{{ number_format($charges[$key]['total'], 2) }}</td>
                </tr>
            </tbody>
        </table>
        @endif
    @endforeach
</div>
@endif

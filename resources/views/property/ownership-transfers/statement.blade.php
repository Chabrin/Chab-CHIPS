<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $transfer->reference }} Handover Statement</title>
<style>body{font:13px 'Segoe UI',Arial,sans-serif;color:#172b3a;max-width:1000px;margin:24px auto;padding:0 18px}h1{font-size:18px}h2{font-size:14px;margin-top:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #ddd;padding:7px;vertical-align:top}.number{text-align:right}@media print{button{display:none}body{margin:0;max-width:none}thead{display:table-header-group}tr{break-inside:avoid}}</style></head><body>
<button type="button" onclick="window.print()">Print / save as PDF</button>
<h1>CHABRIN LTD. — Property Ownership Handover</h1>
<p>{{ $transfer->reference }} · {{ ucwords(str_replace('_', ' ', $transfer->status)) }} · Effective {{ $transfer->effective_date->format('d M Y') }}</p>
<p><strong>{{ $snapshot['rows']['property']['property_name'] }} ({{ $snapshot['rows']['property']['reference_number'] }})</strong><br>From {{ $snapshot['rows']['outgoing_client']['names'] }} ({{ $snapshot['rows']['outgoing_client']['reference_number'] }})<br>To {{ $snapshot['rows']['client']['names'] }} ({{ $snapshot['rows']['client']['reference_number'] }})</p>
<p>{{ $transfer->reason }}</p>
<h2>Closing / opening position</h2><p>Outgoing property ledger closing balance: {{ number_format($snapshot['summary']['ledger_balance'], 2) }}. The incoming property ledger starts at zero; existing amounts on the incoming landlord’s other properties remain intact. No bank movement is posted by this handover.</p>
<p>Outstanding tenant invoices: {{ number_format($snapshot['summary']['tenant_arrears'], 2) }}. Collection rights {{ $transfer->arrears_policy === 'transfer' ? 'transfer to the incoming landlord' : 'remain with the outgoing landlord' }}. Tenant accounts and signed lease history continue.</p>
<p>New SLA commission: {{ $transfer->terms['commission_percentage'] }}%; duration {{ $transfer->terms['years'] }} year(s). Other reviewed SLA terms and billing items continue. No repeat registration fee.</p>
<p><strong>Finance reconciliation:</strong> {{ $transfer->finance_notes }}</p>
<p>Receipts not yet allocated at handover: {{ number_format($snapshot['summary']['unallocated_receipts'], 2) }}. Incoming payout account: {{ $snapshot['rows']['bank']['account_name'] }} — ending {{ substr($snapshot['rows']['bank']['account_number'], -4) }}.</p>
<h2>Outstanding tenant invoices at handover</h2><table><thead><tr><th>Invoice</th><th>Tenant ID / unit ID</th><th>Period</th><th class="number">Outstanding</th></tr></thead><tbody>
@foreach($snapshot['rows']['invoices'] as $invoice)
    @if(in_array($invoice['id'], $snapshot['open_invoice_ids']))
    <tr><td>{{ $invoice['reference_number'] }}<br>Collection rights: {{ in_array($invoice['id'], $snapshot['eligible_invoice_ids']) ? ($transfer->arrears_policy === 'transfer' ? $transfer->toClient->names : $transfer->fromClient->names) : 'Earlier owner ID ' . $snapshot['invoice_owners'][$invoice['id']] }}</td><td>{{ $invoice['tenant_id'] }} / {{ $invoice['unit_id'] }}</td><td>{{ $invoice['returns_period'] }}</td><td class="number">{{ number_format($invoice['current_balance'] ?? $invoice['total_amount'], 2) }}</td></tr>
    @endif
@endforeach
</tbody></table>
<h2>Deposit custody schedule</h2><p>These are recorded deposits, not proof of cash held. Finance’s reconciliation identifies outstanding invoices, previous refunds and the agreed transfer of custody; company-held funds remain company-held.</p><table><thead><tr><th>Reference</th><th>Tenant ID / unit ID</th><th>Held by</th><th class="number">Recorded amount</th></tr></thead><tbody>@foreach($snapshot['rows']['deposits'] as $deposit)<tr><td>{{ $deposit['reference_number'] }}</td><td>{{ $deposit['tenant_id'] }} / {{ $deposit['unit_id'] }}</td><td>{{ $deposit['held_by'] }}</td><td class="number">{{ number_format($deposit['amount'], 2) }}</td></tr>@endforeach</tbody></table>
<h2>Authorization history</h2><table><thead><tr><th>When</th><th>By</th><th>Action / notes</th></tr></thead><tbody>@foreach($transfer->events as $event)<tr><td>{{ $event->created_at->format('d M Y H:i') }}</td><td>{{ $event->actor?->name }}</td><td>{{ str_replace('_', ' ', $event->action) }} — {{ $event->notes }}</td></tr>@endforeach</tbody></table>
</body></html>

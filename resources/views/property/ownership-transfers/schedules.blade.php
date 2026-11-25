<details class="border rounded p-3 mb-3">
    <summary>Outstanding invoice schedule ({{ $snapshot['summary']['outstanding_invoices'] }})</summary>
    <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Invoice</th><th>Tenant / unit ID</th><th>Period</th><th>Collection rights at handover</th><th class="text-end">Outstanding</th></tr></thead><tbody>
    @foreach($snapshot['rows']['invoices'] as $invoice)
        @if(in_array($invoice['id'], $snapshot['open_invoice_ids']))
        <tr><td>{{ $invoice['reference_number'] }}</td><td>{{ $invoice['tenant_id'] }} / {{ $invoice['unit_id'] }}</td><td>{{ $invoice['returns_period'] }}</td>
            <td>{{ in_array($invoice['id'], $snapshot['eligible_invoice_ids']) ? ($transfer->arrears_policy === 'transfer' ? $transfer->toClient->names : $transfer->fromClient->names) : 'Earlier landlord ID ' . $snapshot['invoice_owners'][$invoice['id']] }}</td>
            <td class="text-end">{{ number_format($invoice['current_balance'] ?? $invoice['total_amount'], 2) }}</td></tr>
        @endif
    @endforeach
    @if(!$snapshot['summary']['outstanding_invoices'])
    <tr><td colspan="5" class="text-muted">No outstanding tenant invoices recorded.</td></tr>
    @endif
    </tbody></table></div>
</details>
<details class="border rounded p-3 mb-3">
    <summary>Deposit custody schedule ({{ $snapshot['summary']['deposit_records'] }})</summary>
    <p class="text-muted mt-2">Recorded deposits are not proof of cash held. Reconcile allocations and refunds, and document the agreed custody of funds. Company-held deposits remain company-held.</p>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Reference</th><th>Tenant / unit ID</th><th>Held by</th><th class="text-end">Recorded amount</th><th class="text-end">Recorded invoice balance</th></tr></thead><tbody>
    @foreach($snapshot['rows']['deposits'] as $deposit)<tr><td>{{ $deposit['reference_number'] }}</td><td>{{ $deposit['tenant_id'] }} / {{ $deposit['unit_id'] }}</td><td>{{ $deposit['held_by'] }}</td><td class="text-end">{{ number_format($deposit['amount'], 2) }}</td><td class="text-end">{{ number_format($deposit['invoice_balance'] ?? 0, 2) }}</td></tr>@endforeach
    @if(!$snapshot['summary']['deposit_records'])
    <tr><td colspan="5" class="text-muted">No deposit records found.</td></tr>
    @endif
    </tbody></table></div>
</details>
<details class="border rounded p-3 mb-3">
    <summary>Outgoing property ledger</summary>
    <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Date</th><th>Reference / details</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>
    @foreach($snapshot['rows']['ledger'] as $row)
        @if((int) $row['client_id'] === (int) $transfer->from_client_id)
        <tr><td>{{ $row['entry_date'] }}</td><td>{{ $row['transaction_number'] }} — {{ $row['entry_description'] }}@if(!app(\App\Services\ClientLedgerService::class)->counts($row['entry_type_code'], $row['entry_description'])) (snapshot only; excluded from balance)@endif</td><td class="text-end">{{ number_format($row['debit_amount'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($row['credit_amount'] ?? 0, 2) }}</td></tr>
        @endif
    @endforeach
    @if(!collect($snapshot['rows']['ledger'])->contains(fn ($row) => (int) $row['client_id'] === (int) $transfer->from_client_id))
    <tr><td colspan="4" class="text-muted">No outgoing ledger entries recorded for this property.</td></tr>
    @endif
    </tbody></table></div>
</details>
<details class="border rounded p-3 mb-3">
    <summary>Recurring payment instructions ({{ count($snapshot['rows']['remittances']) }})</summary>
    <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Instruction</th><th>Supplier / caretaker</th><th>Account ending</th><th class="text-end">Amount</th></tr></thead><tbody>
    @foreach($snapshot['rows']['remittances'] as $row)<tr><td>{{ $row['narration'] }}</td><td>{{ $row['supplier_id'] ?? '—' }} / {{ $row['caretaker_id'] ?? '—' }}</td><td>{{ substr((string) ($row['account_number'] ?? ''), -4) }}</td><td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td></tr>@endforeach
    @if(!count($snapshot['rows']['remittances']))
    <tr><td colspan="4" class="text-muted">No recurring payment instructions recorded.</td></tr>
    @endif
    </tbody></table></div>
</details>

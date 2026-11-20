@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="mb-1">Invoice details</h4>
                    <div class="text-break">{{ $invoice->reference_number ?: 'No reference recorded' }}</div>
                </div>
                <a href="{{ route('invoices.tenant.items.index') }}" class="btn btn-outline-primary">Back to invoices</a>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    @foreach(['Invoice amount' => $invoice->total_amount, 'Outstanding balance' => $invoice->current_balance, 'Allocated payments' => $invoice->paymentAllocations->sum('amount_allocated')] as $label => $amount)
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">{{ $label }}</div><strong class="fs-5">{{ number_format((float) $amount, 2) }}</strong></div></div>
                    @endforeach
                </div>
                <h5>Tenant and billing</h5>
                <dl class="row mb-4">
                    @foreach([
                        'Tenant' => $invoice->tenant?->names,
                        'Property' => $invoice->property?->property_name,
                        'Unit' => $invoice->unit?->unit_code ?: $invoice->unit?->unit_name,
                        'Lease ID' => $invoice->lease_id,
                        'Invoice date' => $invoice->date_time?->format('d M Y H:i'),
                        'Due date' => $invoice->date_due?->format('d M Y'),
                        'Return period' => $invoice->returns_period,
                        'Status' => $invoice->status_name,
                        'Saved narration' => $invoice->narration,
                        'Saved item label' => $invoice->return_type_id,
                    ] as $label => $value)
                        <dt class="col-sm-3 mb-2">{{ $label }}</dt><dd class="col-sm-9 text-break">{{ filled($value) ? $value : 'Not recorded' }}</dd>
                    @endforeach
                </dl>
                <div class="border rounded p-3 mb-4">
                    <h5>Creation and item details</h5>
                    <dl class="row mb-0">
                        @foreach([
                            'Invoice ID' => $invoice->id,
                            'Created by' => $creator ? $creator.' (user '.$invoice->created_by.')' : ($invoice->created_by ? 'User '.$invoice->created_by.' — name unavailable' : null),
                            'Created timestamp' => ($invoice->created_at ?? $invoice->created_date)?->format('d M Y H:i:s'),
                            'Batch number' => $invoice->batch_number,
                            'Transaction identifier' => $invoice->trans_details,
                            'Item ID' => $invoice->item_type_id,
                            'Current item name' => $item?->item_name,
                            'Current billing code' => $item?->billing_code,
                            'Current setup amount' => $item ? number_format((float) $item->amount, 2) : null,
                            'Fixed amount in setup' => $item?->fixed_amount,
                        ] as $label => $value)
                            <dt class="col-sm-3 mb-2">{{ $label }}</dt><dd class="col-sm-9 text-break">{{ filled($value) ? $value : 'Not recorded' }}</dd>
                        @endforeach
                    </dl>
                    <p class="text-muted mb-0">Item name and billing code above reflect today's item setup. The saved reference and narration may differ if that setup changed. A batch number alone does not identify which screen created a charge.</p>
                </div>

                <h5>Linked deposit records</h5>
                <div class="table-responsive mb-4"><table class="table">
                    <thead><tr><th>Reference / ID</th><th>Amount</th><th>Months</th><th>Held by</th><th>Narration</th></tr></thead>
                    <tbody>@forelse($deposits as $deposit)
                        <tr><td>{{ $deposit->reference_number ?: $deposit->id }}</td><td>{{ number_format((float) $deposit->amount, 2) }}</td><td>{{ $deposit->months ?? 'Not recorded' }}</td><td>{{ $deposit->held_by ?: 'Not recorded' }}</td><td>{{ $deposit->narration }}</td></tr>
                    @empty<tr><td colspan="5">No deposit record is linked to this invoice. This does not by itself prove how the invoice was created.</td></tr>@endforelse</tbody>
                </table></div>

                <h5>Payment allocations</h5>
                <div class="table-responsive mb-4"><table class="table">
                    <thead><tr><th>Payment ID</th><th>Receipt / reference</th><th class="text-end">Allocated amount</th></tr></thead>
                    <tbody>@forelse($invoice->paymentAllocations as $allocation)
                        <tr><td>{{ $allocation->payment_id }}</td><td>{{ $allocation->payment?->receipt_number ?: $allocation->payment?->reference ?: 'Not recorded' }}</td><td class="text-end">{{ number_format((float) $allocation->amount_allocated, 2) }}</td></tr>
                    @empty<tr><td colspan="3">No payments allocated.</td></tr>@endforelse</tbody>
                </table></div>

                <h5>Linked tenant statement entries</h5>
                <div class="table-responsive mb-4"><table class="table">
                    <thead><tr><th>Date</th><th>Narration</th><th>Status ID</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                    <tbody>@forelse($transactions as $transaction)
                        <tr><td>{{ $transaction->date_time?->format('d M Y') }}</td><td>{{ $transaction->narration }}</td><td>{{ $transaction->current_status_id }}</td><td class="text-end">{{ number_format((float) $transaction->debit_amount, 2) }}</td><td class="text-end">{{ number_format((float) $transaction->credit_amount, 2) }}</td></tr>
                    @empty<tr><td colspan="5">No statement entries are linked by invoice ID.</td></tr>@endforelse</tbody>
                </table></div>

                <h5>Same batch at this property</h5>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Reference</th><th>Lease ID</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>@forelse($batchInvoices->take(20) as $batchInvoice)
                        <tr><td class="text-break"><a href="{{ route('invoices.tenant.items.show', Crypt::encryptString((string) $batchInvoice->id)) }}">{{ $batchInvoice->reference_number ?: 'Invoice '.$batchInvoice->id }}</a></td><td>{{ $batchInvoice->lease_id }}</td><td class="text-end">{{ number_format((float) $batchInvoice->total_amount, 2) }}</td></tr>
                    @empty<tr><td colspan="3">No batch recorded.</td></tr>@endforelse</tbody>
                </table></div>
                @if($batchInvoices->count() > 20)<p class="text-muted">Showing the first 20 invoices in this batch at this property.</p>@endif
            </div>
        </div>
    </div>
</div>
@endsection

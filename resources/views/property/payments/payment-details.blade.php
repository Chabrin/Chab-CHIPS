@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Payment Summary Card -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center pb-2">
                        <h5 class="mb-0">Payment Details</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('tenant.payments.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-2 pb-2">
                        @if($payment)
                        <p class="mb-1" style="font-size: 0.75rem; line-height: 1.5;">
                            <span>Reference:</span> <strong class="text-primary">{{ $payment->reference ?? 'N/A' }}</strong>
                            <span class="mx-2">|</span>
                            <span>Date:</span> <strong>{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : 'N/A' }}</strong>
                            <span class="mx-2">|</span>
                            <span>Tenant:</span> <strong>{{ $payment->tenant ? trim(($payment->tenant->sur_name ?? '') . ' ' . ($payment->tenant->first_name ?? '')) : 'N/A' }}</strong>
                            <span class="mx-2">|</span>
                            <span>Unit:</span> <strong>{{ $payment->unit->unit_name ?? 'N/A' }}</strong>
                            <span class="mx-2">|</span>
                            <span>Receipt:</span> <strong>{{ $payment->receipt_number ?? 'N/A' }}</strong>
                        </p>
                        <p class="mb-0" style="font-size: 0.75rem; line-height: 1.5;">
                            <span>Total Amount:</span> <strong>KES {{ number_format($payment->amount, 2) }}</strong>
                            <span class="mx-2">|</span>
                            <span>Allocated:</span> <strong>KES {{ number_format($totalAllocated, 2) }}</strong>
                            <span class="mx-2">|</span>
                            <span>Unallocated:</span> <strong>KES {{ number_format($unallocated, 2) }}</strong>
                            <span class="mx-2">|</span>
                            <span>Status:</span> <strong>{{ $allocationStatus }}</strong>
                            @if($payment->narration)
                            <span class="mx-2">|</span>
                            <span>Narration:</span> <em>{{ $payment->narration }}</em>
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Allocation Trail Card -->
                <div class="card mt-3">
                    <div class="card-header card-no-border">
                        <h5 class="mb-0">Allocation Trail</h5>
                    </div>
                    <div class="card-body">

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="allocations-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date & Time</th>
                                        <th>Invoice Reference</th>
                                        <th class="text-end">Inv Amt</th>
                                        <th class="text-end">Allocated</th>
                                        <th class="text-end">Inv Bal</th>
                                        <th class="text-end">New Bal</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-danger">
                            Payment not found.
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var allocationsData = @json($allocations);

        $('#allocations-table').DataTable({
            data: allocationsData,
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'date_time',
                    render: function(data, type, row) {
                        if (data) {
                            var date = new Date(data);
                            return date.toLocaleDateString('en-GB', {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                        }
                        return 'N/A';
                    }
                },
                {
                    data: 'invoice',
                    render: function(data, type, row) {
                        if (data && data.reference_number) {
                            var encryptedId = btoa(row.invoice_id);
                            return '<a href="/property/invoices/' + encryptedId + '/view" class="text-primary" target="_blank">' + data.reference_number + '</a>';
                        }
                        return 'Invoice #' + row.invoice_id;
                    }
                },
                {
                    data: 'invoice_amount',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return Number(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                },
                {
                    data: 'amount_allocated',
                    className: 'text-end fw-bold',
                    render: function(data, type, row) {
                        return Number(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                },
                {
                    data: 'new_invoice_balance',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return Number(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                },
                {
                    data: 'new_unallocated_balance',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return Number(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
            ],
            order: [
                [1, 'asc']
            ],
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            language: {
                emptyTable: "No allocations found for this payment"
            }
        });
    });
</script>
@endsection
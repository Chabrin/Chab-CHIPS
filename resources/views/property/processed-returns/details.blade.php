@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Return Details — {{ $return->return_code }}</h5>
                        <a href="{{ route('processed.returns.index') }}" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">arrow_back</span> Back
                        </a>
                    </div>
                    <div class="card-body py-2 px-3" style="font-size:0.80rem;">
                        <!-- Return Header Info -->
                        <div class="row mb-1 gx-2">
                            <div class="col-6 col-sm-4 col-md-2 mb-1">
                                <label class="text-muted" style="font-size:0.80rem;">Return Code</label>
                                <div class="fw-bold">{{ $return->return_code }}</div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 mb-1">
                                <label class="text-muted" style="font-size:0.80rem;">Property</label>
                                <div class="fw-bold">{{ $return->property_code ?? '-' }}</div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 mb-1">
                                <label class="text-muted" style="font-size:0.80rem;">Landlord</label>
                                <div class="fw-bold">{{ $return->landlord_code }}</div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 mb-1">
                                <label class="text-muted" style="font-size:0.80rem;">Period</label>
                                <div class="fw-bold">{{ str_pad($return->month, 2, '0', STR_PAD_LEFT) }}/{{ $return->year }}</div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 mb-1">
                                <label class="text-muted" style="font-size:0.80rem;">Type</label>
                                <div class="fw-bold">{{ $return->return_type ?? '-' }}</div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 mb-1">
                                <label class="text-muted" style="font-size:0.80rem;">Commission %</label>
                                <div class="fw-bold">{{ number_format($return->commission_rate ?? 0, 2) }}%</div>
                            </div>
                        </div>

                        <!-- Financial Summary -->
                        <style>
                            .summary-table tbody tr:nth-child(odd) {
                                background-color: #f8f9fa;
                            }

                            .summary-table tbody tr:nth-child(even) {
                                background-color: #fff;
                            }
                        </style>
                        <div class="row mb-1 gx-2">
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0 summary-table" style="font-size:0.72rem;">
                                    <tbody>
                                        <tr>
                                            <td class="py-1 fw-bold" width="50%" colspan="2">Credits</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Rent</td>
                                            <td class="text-end py-1">{{ number_format($lineTotals->rent_total ?? 0, 2) }}</td>
                                        </tr>
                                        @if(($lineTotals->add_back ?? 0) > 0)
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Add Back Deductions</td>
                                            <td class="text-end py-1">{{ number_format($lineTotals->add_back ?? 0, 2) }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td class="py-1 fw-bold ps-3">Total Credits</td>
                                            <td class="text-end py-1 fw-bold text-primary">{{ number_format($lineTotals->total_cr ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 fw-bold" colspan="2">Debits</td>
                                        </tr>
                                        @if(($lineTotals->deductions ?? 0) > 0)
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Deductions Before Commission</td>
                                            <td class="text-end py-1">{{ number_format($lineTotals->deductions ?? 0, 2) }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Commissionable Total</td>
                                            <td class="text-end py-1">{{ number_format($return->commission_total ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Commission @ {{ number_format($return->commission_rate ?? 0, 2) }}%</td>
                                            <td class="text-end py-1">{{ number_format($lineTotals->commission ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3">VAT on Commission</td>
                                            <td class="text-end py-1">{{ number_format($lineTotals->vat ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 fw-bold ps-3">Total Debits</td>
                                            <td class="text-end py-1 fw-bold text-danger">{{ number_format($lineTotals->total_dr ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold py-1">Net Amount (Credits − Debits)</td>
                                            <td class="text-end py-1 fw-bold text-primary">{{ number_format(($lineTotals->total_cr ?? 0) - ($lineTotals->total_dr ?? 0), 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0 summary-table" style="font-size:0.72rem;">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted py-1" width="50%">Amount Paid</td>
                                            <td class="text-end py-1 fw-bold">{{ number_format($return->amount_paid ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Voucher</td>
                                            <td class="text-end py-1">{{ $return->voucher_number ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Cheque</td>
                                            <td class="text-end py-1">{{ $return->cheque_number ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Payment Date</td>
                                            <td class="text-end py-1">{{ $return->payment_date ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if($return->remarks || $return->remarks_2)
                        <div class="row mb-1">
                            <div class="col-12">
                                <div class="p-1 bg-info rounded" style="font-size:0.7rem;">Remarks: {{ $return->remarks }} {{ $return->remarks_2 }}</div>
                            </div>
                        </div>
                        @endif

                        <!-- Detail Lines -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-1">
                            <h6 class="mb-0" style="font-size:0.8rem;">Detail Lines</h6>
                            <a href="{{ route('processed.returns.excel', encrypt($return->id)) }}" class="btn btn-success btn-sm py-0 px-2 d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size:14px;">download</span>
                                <span class="ms-1" style="font-size:0.72rem;">Excel</span>
                            </a>
                        </div>
                        <style>
                            #details-table td,
                            #details-table th {
                                padding: 2px 4px !important;
                                font-size: 0.78rem !important;
                                line-height: 1.2;
                            }
                        </style>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="details-table" style="width:100%;font-size:0.66rem;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Entry Type</th>
                                        <th>Tenant</th>
                                        <th>Unit</th>
                                        <th>Description</th>
                                        <th>Dr/Cr</th>
                                        <th class="text-end">Landlord Amt</th>
                                        <th class="text-end">Net</th>
                                        <th class="text-end">VAT</th>
                                        <th>Ref</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
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
        var tableColumns = [{
                data: null,
                orderable: false,
                searchable: false,
                render: function(d, t, r, m) {
                    return m.row + 1;
                }
            },
            {
                data: 'entry_type'
            },
            {
                data: 'tenant_name'
            },
            {
                data: 'unit_code'
            },
            {
                data: 'entry_description',
                render: function(d) {
                    return '<small>' + (d || '-') + '</small>';
                }
            },
            {
                data: 'dr_cr',
                render: function(d) {
                    if (d === 'CR') return '<span class="badge badge-light-success">CR</span>';
                    if (d === 'DR') return '<span class="badge badge-light-danger">DR</span>';
                    return d;
                }
            },
            {
                data: 'amount_payable_landlord',
                className: 'text-end'
            },
            {
                data: 'net_amount',
                className: 'text-end',
                render: function(d) {
                    return '<strong>' + d + '</strong>';
                }
            },
            {
                data: 'vat_amount',
                className: 'text-end'
            },
            {
                data: 'transaction_ref'
            }
        ];

        var table = $('#details-table').DataTable({
            data: [],
            columns: tableColumns,
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [1, 'asc']
            ],
            pageLength: 50
        });

        // Load detail lines
        $.ajax({
            url: '{{ route("ajax.processed.returns.units", encrypt($return->id)) }}',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    table.clear();
                    table.rows.add(response.data);
                    table.draw();
                }
            }
        });
    });
</script>
@endsection
@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header pb-3 d-flex justify-content-between align-items-center">
                        <h5>Property Return Details</h5>
                        <div>
                            <a href="{{ route('processed.returns.pdf', encrypt($return->id)) }}" class="btn btn-primary btn-sm me-2" target="_blank">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">picture_as_pdf</span> Download PDF
                            </a>
                            <a href="{{ route('processed.returns.excel', encrypt($return->id)) }}" class="btn btn-primary btn-sm me-2">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">description</span> Download Excel
                            </a>
                            <a href="{{ route('processed.returns.index') }}" class="btn btn-primary btn-sm">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">arrow_back</span> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body custom-scrollbar">
                        <!-- Return Header -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Return Information</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Reference Number</label>
                                <div class="fw-bold">{{ $return->id ?? '-' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Date</label>
                                <div class="fw-bold">{{ date('d-M-Y H:i', strtotime($return->date_time)) }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Period</label>
                                <div class="fw-bold">{{ $return->period_name ?? date('M Y', strtotime($return->period_date)) }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Status</label>
                                <div class="fw-bold">
                                    @if($return->current_status_id == 1)
                                    <span class="badge badge-light-info">Pending</span>
                                    @elseif($return->current_status_id == 5)
                                    <span class="badge badge-light-success">Approved</span>
                                    @elseif($return->current_status_id == 17)
                                    <span class="badge badge-light-success">Processed</span>
                                    @else
                                    <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Client & Property -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Client & Property Information</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Client Name</label>
                                <div class="fw-bold">{{ $return->client_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Email</label>
                                <div class="fw-bold">{{ $return->client_email ?? '-' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Phone</label>
                                <div class="fw-bold">{{ $return->client_phone ?? '-' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">KRA PIN</label>
                                <div class="fw-bold">{{ $return->client_kra_pin ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Property</label>
                                <div class="fw-bold">{{ $return->property ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">SLA Reference</label>
                                <div class="fw-bold">{{ $return->sla_id ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Return Type</label>
                                <div class="fw-bold">{{ $return->return_type_id == 1 ? 'Normal' : 'Other' }}</div>
                            </div>
                        </div>

                        <!-- Financial Summary -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Financial Summary</h6>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" width="40%">Gross Returns</td>
                                                <td class="text-end fw-bold">KSh {{ number_format($return->total_gross ?? 0, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Commissionable Amount</td>
                                                <td class="text-end">KSh {{ number_format($return->total_commissionable ?? 0, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Less: Management Commission ({{ $return->comm_per ?? '0' }}%)</td>
                                                <td class="text-end text-danger">KSh {{ number_format($return->total_commission ?? 0, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Less: VAT on Commission ({{ $return->comm_vat_per ?? '16' }}%)</td>
                                                <td class="text-end text-danger">KSh {{ number_format($return->vat_on_commission ?? 0, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Less: Total Recoveries/Deductions</td>
                                                <td class="text-end text-danger">KSh {{ number_format($return->total_recoveries ?? 0, 2) }}</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td class="fw-bold">NET AMOUNT PAYABLE</td>
                                                <td class="text-end fw-bold fs-5">KSh {{ number_format($return->total_net_amount ?? 0, 2) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Details -->
                        <div class="row mb-2">
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Properties Net</label>
                                <div class="fw-bold">KSh {{ number_format($return->properties_net ?? 0, 2) }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Other Transactions</label>
                                <div class="fw-bold">KSh {{ number_format($return->other_transactions ?? 0, 2) }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Other Incomes</label>
                                <div class="fw-bold">KSh {{ number_format($return->other_incomes ?? 0, 2) }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Field Officer ID</label>
                                <div class="fw-bold">{{ $return->field_officer_id ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Batch & Approval -->
                        @if($return->batch_id)
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Batch Information</h6>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="text-muted small">Batch ID</label>
                                <div class="fw-bold">{{ $return->batch_id }}</div>
                            </div>
                        </div>
                        @endif

                        @if($return->processed_date)
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Processing Information</h6>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="text-muted small">Processed Date</label>
                                <div class="fw-bold">{{ date('d-M-Y H:i', strtotime($return->processed_date)) }}</div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="text-muted small">Days Late</label>
                                <div class="fw-bold">{{ $return->days_late ?? 0 }} days</div>
                            </div>
                        </div>
                        @endif

                        <!-- Narration -->
                        @if(isset($return->narration) && $return->narration)
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Narration</h6>
                            </div>
                            <div class="col-12">
                                <div class="p-2 bg-light text-dark rounded">
                                    {{ $return->narration }}
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Unit Breakdown</h6>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-sm" id="units-table" style="font-size: 0.72rem;">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Lease Ref</th>
                                                <th class="text-end">Rent</th>
                                                <th class="text-end">Service</th>
                                                <th class="text-end">Water</th>
                                                <th class="text-end">Elec</th>
                                                <th class="text-end">Deposits</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Comm</th>
                                                <th class="text-end">VAT</th>
                                                <th class="text-end">Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Loaded via Ajax -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex gap-2">
                                <a href="{{ route('processed.returns.pdf', encrypt($return->id)) }}" class="btn btn-primary btn-sm" target="_blank">
                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">picture_as_pdf</span> Download PDF
                                </a>
                                <a href="{{ route('processed.returns.excel', encrypt($return->id)) }}" class="btn btn-primary btn-sm">
                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">description</span> Download Excel
                                </a>
                            </div>
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
        let unitsTable = $('#units-table').DataTable({
            processing: true,
            ajax: {
                url: '{{ route("ajax.processed.returns.units", encrypt($return->id)) }}',
                dataSrc: 'data'
            },
            columns: [{
                    data: 'tenant_name'
                },
                {
                    data: 'lease_reference'
                },
                {
                    data: 'monthly_rent',
                    className: 'text-end'
                },
                {
                    data: 'service_charge',
                    className: 'text-end'
                },
                {
                    data: 'water_bill',
                    className: 'text-end'
                },
                {
                    data: 'elec_bill',
                    className: 'text-end'
                },
                {
                    data: 'deposits',
                    className: 'text-end'
                },
                {
                    data: 'total_charges',
                    className: 'text-end',
                    render: function(data) {
                        return '<strong>' + data + '</strong>';
                    }
                },
                {
                    data: 'commission',
                    className: 'text-end'
                },
                {
                    data: 'vat',
                    className: 'text-end'
                },
                {
                    data: 'net_payable',
                    className: 'text-end',
                    render: function(data) {
                        return '<strong>' + data + '</strong>';
                    }
                }
            ],
            pageLength: 25,
            lengthMenu: [
                [25, 50, 100, -1],
                [25, 50, 100, "All"]
            ],
            order: [
                [0, 'asc']
            ]
        });
    });
</script>
@endsection
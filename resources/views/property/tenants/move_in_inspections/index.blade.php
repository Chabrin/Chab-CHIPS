@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Move-In Inspections</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="{{ route('property.tenants.listing') }}" class="btn btn-sm btn-light">
                                <span class="material-symbols-outlined">arrow_back</span> Back to Tenants
                            </a>
                            <a href="{{ route('move.in.inspections.create') }}" class="btn btn-sm btn-primary">
                                <span class="material-symbols-outlined">add_circle</span> Record Inspection
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="inspections-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Inspection Date</th>
                                        <th>Inspector</th>
                                        <th>Overall Condition</th>
                                        <th>Conducted By</th>
                                        <th>Action</th>
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
    $(document).ready(function () {
        var table = $('#inspections-table').DataTable({
            processing: true,
            ajax: {
                url: '{{ route("ajax.move.in.inspections") }}',
                type: 'GET',
                dataSrc: 'data',
                error: function (xhr) {
                    console.error('Error loading move-in inspections:', xhr);
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r, m) { return m.row + 1; }
                },
                { data: 'tenant_name' },
                { data: 'property_name' },
                { data: 'unit_name' },
                { data: 'inspection_date' },
                { data: 'inspector_name' },
                {
                    data: 'overall_condition',
                    render: function (data) {
                        if (!data || data === '-') return '<span class="text-muted">-</span>';
                        var colors = { Excellent: 'success', Good: 'success', Fair: 'warning', Poor: 'danger' };
                        var color = colors[data] || 'secondary';
                        return '<span class="badge badge-light-' + color + '">' + data + '</span>';
                    }
                },
                { data: 'conducted_by' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        return '<a href="' + '{{ route("move.in.inspections.show", "PLACEHOLDER") }}'.replace('PLACEHOLDER', r.encrypted_id) + '" title="View">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>';
                    }
                }
            ],
            order: [[4, 'desc']],
            pageLength: 25,
            responsive: true
        });
    });
</script>
@endsection

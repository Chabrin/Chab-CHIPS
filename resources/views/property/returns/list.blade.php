@extends('layouts.main')

@section('styles')
<style>
    /* Property dropdown (select2) sized like the small Year / Month controls beside it; the theme forces 47px with !important */
    #property_select + .select2-container {
        display: block;
        height: 31px;
    }

    #property_select + .select2-container .select2-selection--single {
        height: 31px !important;
        padding: 0 !important;
        border: 1px solid #dee2e6;
        border-radius: 4px !important;
    }

    #property_select + .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 29px !important;
        padding-left: 8px;
        font-size: 14px;
    }

    #property_select + .select2-container .select2-selection--single .select2-selection__arrow {
        height: 29px;
        top: 0 !important;
        right: 6px;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Returns List (Unpaid)</h5>
                    </div>
                    <div class="card-body">
                        @if(request()->filled('generation'))
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <span>Showing returns from this generation.</span>
                            <a href="{{ route('returns.batches.index', ['year' => $currentYear, 'month' => $currentMonth]) }}">View all returns for this month</a>
                        </div>
                        @endif
                        <!-- Filters -->
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-2">
                                <label for="year_select" class="form-label mb-1">Year</label>
                                <select class="form-control form-control-sm" id="year_select">
                                    @foreach($years as $yr)
                                    <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="month_select" class="form-label mb-1">Month</label>
                                <select class="form-control form-control-sm" id="month_select">
                                    <option value="1" {{ $currentMonth == 1 ? 'selected' : '' }}>January</option>
                                    <option value="2" {{ $currentMonth == 2 ? 'selected' : '' }}>February</option>
                                    <option value="3" {{ $currentMonth == 3 ? 'selected' : '' }}>March</option>
                                    <option value="4" {{ $currentMonth == 4 ? 'selected' : '' }}>April</option>
                                    <option value="5" {{ $currentMonth == 5 ? 'selected' : '' }}>May</option>
                                    <option value="6" {{ $currentMonth == 6 ? 'selected' : '' }}>June</option>
                                    <option value="7" {{ $currentMonth == 7 ? 'selected' : '' }}>July</option>
                                    <option value="8" {{ $currentMonth == 8 ? 'selected' : '' }}>August</option>
                                    <option value="9" {{ $currentMonth == 9 ? 'selected' : '' }}>September</option>
                                    <option value="10" {{ $currentMonth == 10 ? 'selected' : '' }}>October</option>
                                    <option value="11" {{ $currentMonth == 11 ? 'selected' : '' }}>November</option>
                                    <option value="12" {{ $currentMonth == 12 ? 'selected' : '' }}>December</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="property_select" class="form-label mb-1">Property</label>
                                <select class="form-control form-control-sm" id="property_select">
                                    <option value="">All properties</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex gap-2 align-items-end">
                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_btn">
                                    <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display:none;"></span>
                                    <span class="material-symbols-outlined" id="search_icon">search</span>
                                    <span class="ms-1">Search</span>
                                </button>
                            </div>
                            <div class="col d-flex justify-content-end align-items-end">
                                <small class="text-muted me-3" id="result_info" style="display:none;">Found <strong id="result_count">0</strong> records</small>
                                <button type="button" class="btn btn-primary btn-sm me-3 align-self-end" id="forward_btn" style="display:none;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">forward_to_inbox</span> Forward drafts to manager (<span id="draft_count">0</span>)
                                </button>
                                <div>
                                    <label for="status_filter" class="form-label mb-1">Approval Status</label>
                                    <select class="form-control form-control-sm" id="status_filter">
                                        <option value="">All</option>
                                        <option value="Draft">Draft</option>
                                        <option value="Waiting verification">Waiting verification</option>
                                        <option value="Waiting approval">Waiting approval</option>
                                        <option value="Approved">Approved</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="returns-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client Name</th>
                                        <th>Property</th>
                                        <th>Type</th>
                                        <th class="text-end">Net</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
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
        var table = $('#returns-table').DataTable({
            data: [],
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, t, r, m) {
                        return m.row + 1;
                    }
                },
                {
                    data: 'client_name'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'return_type'
                },
                {
                    data: 'net_amount',
                    className: 'text-end',
                    searchable: false
                },
                {
                    data: 'payable_units',
                    render: function(d, t, r) {
                        if (!d) return '-';
                        var parts = d.split('/');
                        var why = '';
                        if (r.not_payable_reason) {
                            var held = parseFloat(String(r.withheld || '0').replace(/,/g, '')) > 0 ? 'Withheld ' + r.withheld + ': ' : '';
                            why = '<br><small class="text-muted" style="font-size:0.68rem;">' + $('<div>').text(held + r.not_payable_reason).html() + '</small>';
                        }
                        if (parts.length === 2 && parts[0] !== parts[1]) {
                            return '<span class="text-warning" title="Some units are not payable: open the return to see each one">' + d + '</span>' + why;
                        }
                        return '<span class="text-success">' + d + '</span>' + why;
                    }
                },
                {
                    data: 'approved_status',
                    render: function(d, t, r) {
                        if (r.stage === 'Approved') return '<span class="badge badge-light-success">Approved</span>';
                        if (r.stage === 'Draft') return '<span class="badge badge-light-secondary" title="Not forwarded to the manager yet">Draft</span>';
                        if (r.stage === 'Waiting verification') return '<span class="badge badge-light-warning">Waiting verification</span>';
                        return '<span class="badge badge-light-info">Waiting approval</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, t, r) {
                        return '<a href="/returns/' + r.encrypted_id + '/details" title="View Details">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;color:var(--accent);">folder_eye</span></a>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            orderCellsTop: true,
            order: [
                [6, 'desc'],
                [1, 'asc']
            ],
            pageLength: 50
        });

        // Per-column filtering
        $('#returns-table thead .column-filters th').each(function(i) {
            $('input', this).on('keyup change', function() {
                var col = table.column(i);
                if (col.search() !== this.value) {
                    col.search(this.value).draw();
                }
            });
        });

        // Property dropdown: only the properties that have a return in the loaded month; keeps the pick when it is still there
        $('#property_select').select2({
            width: '100%'
        });

        function fillProperties(properties) {
            var $select = $('#property_select');
            var current = $select.val();

            $select.empty().append($('<option>').val('').text('All properties'));
            properties.forEach(function(p) {
                $select.append($('<option>').val(p.code).text(p.name + ' (' + p.code + ')'));
            });

            var stillThere = properties.some(function(p) {
                return p.code === current;
            });
            $select.val(stillThere ? current : '').trigger('change.select2');
        }

        function loadData() {
            var year = $('#year_select').val();
            var month = $('#month_select').val();

            $('#search_spinner').show();
            $('#search_icon').hide();
            $('#search_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("ajax.returns.batches.data") }}',
                type: 'GET',
                data: {
                    year: year,
                    month: month,
                    generation: @json(request()->query('generation'))
                },
                success: function(response) {
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        fillProperties(response.properties || []);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                        refreshForwardButton();
                    }
                },
                error: function(xhr) {
                    var msg = 'Failed to load data.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                },
                complete: function() {
                    $('#search_spinner').hide();
                    $('#search_icon').show();
                    $('#search_btn').prop('disabled', false);
                }
            });
        }

        // Drafts shown now (after the property and status filters) can be forwarded to the manager in one go
        function draftRows() {
            return table.rows({ search: 'applied' }).data().toArray().filter(function(r) { return r.is_draft; });
        }
        function refreshForwardButton() {
            var n = draftRows().length;
            $('#draft_count').text(n);
            $('#forward_btn').toggle(n > 0);
        }
        table.on('draw', refreshForwardButton);

        $('#forward_btn').on('click', function() {
            var rows = draftRows();
            if (!rows.length || !confirm('Forward ' + rows.length + ' draft return(s) to the manager for verification?')) { return; }
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ route("ajax.returns.forward.many") }}',
                type: 'POST',
                data: { _token: $('meta[name="csrf-token"]').attr('content'), ids: rows.map(function(r) { return r.encrypted_id; }) },
                success: function(r) { alert(r.message); loadData(); },
                error: function(xhr) { alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not forward the returns.'); },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        // Custom search for status filter on column 9
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            var selected = $('#status_filter').val();
            if (!selected) return true;
            var cellText = $(data[6]).text() || data[6];
            return cellText === selected;
        });

        $('#status_filter').on('change', function() {
            table.draw();
        });

        // Property filter: a return matches when the chosen property is one it covers (a master return covers its children's)
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            var selected = $('#property_select').val();
            if (!selected || settings.nTable.id !== 'returns-table') return true;
            return (table.row(dataIndex).data().property_codes || []).indexOf(selected) !== -1;
        });

        $('#property_select').on('change', function() {
            table.draw();
        });

        $('#search_btn').on('click', loadData);
        loadData();
    });
</script>
@endsection

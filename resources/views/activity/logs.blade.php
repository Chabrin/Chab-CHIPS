@extends('layouts.main')

@section('content')
<style>
    .log-json-cell {
        white-space: pre-wrap;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 40rem;
    }

    .logs-filters .form-label {
        font-size: .85rem;
        margin-bottom: .25rem;
    }

    .logs-filters .form-control {
        height: calc(1.6em + .5rem);
        padding: .25rem .5rem;
        font-size: .85rem;
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
    }

    .btn-icon .material-symbols-outlined {
        font-size: 18px;
    }

    ul.action {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        gap: .5rem;
    }

    ul.action li {
        display: inline-block;
    }
</style>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Activity Logs</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="logsFilter" class="row g-1 mb-3 logs-filters">
                            <div class="col-auto">
                                <label class="form-label">From</label>
                                <input type="date" name="from" class="form-control form-control-sm">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">To</label>
                                <input type="date" name="to" class="form-control form-control-sm">
                            </div>
                            <div class="col-auto d-flex align-items-end">
                                @permission('read_activity_logs')
                                <button id="searchLogs" type="button" class="btn btn-primary btn-sm btn-icon me-2">
                                    <span id="searchSpinner" class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                    <span class="material-symbols-outlined">search_activity</span>
                                    <span class="ms-1">Search</span>
                                </button>
                                @endpermission
                                <button id="clearLogs" type="button" class="btn btn-secondary btn-sm btn-icon">
                                    <span class="material-symbols-outlined">clear_all</span>
                                    <span class="ms-1">Clear</span>
                                </button>
                            </div>
                        </form>

                        <div id="logsEmpty" class="alert alert-info d-none"></div>

                        <div class="table-responsive custom-scrollbar">
                            <table id="logsTable" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Operation</th>
                                        <th>Table</th>
                                        <th>Old</th>
                                        <th>New</th>
                                        <th>Causer</th>
                                        <th>Time</th>
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
    (function($) {
        'use strict';

        function escapeHtml(str) {
            return $('<div>').text(str).html();
        }

        function toggleSpinner(on) {
            if (on) {
                $('#searchSpinner').removeClass('d-none');
                $('#searchText').text('Searching...');
                $('#searchLogs').prop('disabled', true);
            } else {
                $('#searchSpinner').addClass('d-none');
                $('#searchText').text('Search');
                $('#searchLogs').prop('disabled', false);
            }
        }

        var table = null;

        function initTable() {
            if (table) table.destroy();
            table = $('#logsTable').DataTable({
                data: [],
                columns: [{
                        data: 'id'
                    },
                    {
                        data: 'operation'
                    },
                    {
                        data: 'table_name'
                    },
                    {
                        data: 'old_data',
                        render: function(data) {
                            var str = (data && typeof data === 'object') ? JSON.stringify(data, null, 2) : (data || '');
                            var txt = (str.length > 50) ? escapeHtml(str.substring(0, 50)) + '...' : escapeHtml(str);
                            return '<div class="log-json-cell">' + txt + '</div>';
                        }
                    },
                    {
                        data: 'new_data',
                        render: function(data) {
                            var str = (data && typeof data === 'object') ? JSON.stringify(data, null, 2) : (data || '');
                            var txt = (str.length > 50) ? escapeHtml(str.substring(0, 50)) + '...' : escapeHtml(str);
                            return '<div class="log-json-cell">' + txt + '</div>';
                        }
                    },
                    {
                        data: 'causer'
                    },
                    {
                        data: 'created_at'
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            var oldFull = (row.old_data && typeof row.old_data === 'object') ? JSON.stringify(row.old_data) : (row.old_data || '');
                            var newFull = (row.new_data && typeof row.new_data === 'object') ? JSON.stringify(row.new_data) : (row.new_data || '');
                            var out = '<ul class="action">';
                            out += '<li class="view">';
                            out += '<a href="#" class="view-details" data-old="' + encodeURIComponent(oldFull) + '" data-new="' + encodeURIComponent(newFull) + '" title="View">';
                            out += '<i class="icon-eye"></i>';
                            out += '</a>';
                            out += '</li>';
                            out += '</ul>';
                            return out;
                        }
                    }
                ],
                pageLength: 25,
                lengthChange: true,
                lengthMenu: [
                    [10, 25, 100, 250],
                    [10, 25, 100, 250]
                ],
                autoWidth: false,
                responsive: true,
                ordering: true
            });
        }

        function populateTable(rows) {
            if (!table) initTable();
            table.clear();
            table.rows.add(rows).draw();
        }

        $(function() {
            initTable();
            $(document).on('click', '#searchLogs', function() {
                // require at least From and To to be provided
                var from = $('input[name="from"]').val();
                var to = $('input[name="to"]').val();
                // require both From and To
                if (!from || !to) {
                    if (typeof toastr !== 'undefined') toastr.error('Please provide both From and To dates before searching');
                    return;
                }

                toggleSpinner(true);
                $('#logsEmpty').addClass('d-none').text('');
                var data = $('#logsFilter').serialize();
                $.ajax({
                    url: "{{ route('activity.logs.list') }}",
                    type: 'GET',
                    data: data,
                    success: function(json) {
                        toggleSpinner(false);
                        if (!json || !json.success) {
                            var msg = (json && json.error) ? json.error : 'Error loading logs';
                            if (typeof toastr !== 'undefined') toastr.error(msg);
                            $('#logsEmpty').removeClass('d-none').text(msg);
                            if (table) table.clear().draw();
                            return;
                        }
                        if (!json.data || json.data.length === 0) {
                            $('#logsEmpty').removeClass('d-none').text('No results');
                            if (table) table.clear().draw();
                            return;
                        }
                        $('#logsEmpty').addClass('d-none').text('');
                        populateTable(json.data);
                    },
                    error: function(xhr) {
                        toggleSpinner(false);
                        var msg = 'Error loading logs';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                        if (typeof toastr !== 'undefined') toastr.error(msg);
                        $('#logsEmpty').removeClass('d-none').text(msg);
                        if (table) table.clear().draw();
                    }
                });
            });

            $(document).on('click', '#clearLogs', function() {
                $('#logsFilter')[0].reset();
                if (table) table.clear().draw();
                $('#logsEmpty').addClass('d-none').text('');
            });

            $(document).on('click', '.view-details', function(e) {
                e.preventDefault();
                var oldv = $(this).attr('data-old') || '';
                var newv = $(this).attr('data-new') || '';
                try {
                    oldv = decodeURIComponent(oldv);
                } catch (e) {}
                try {
                    newv = decodeURIComponent(newv);
                } catch (e) {}
                try {
                    oldv = JSON.stringify(JSON.parse(oldv), null, 2);
                } catch (e) {
                    oldv = String(oldv);
                }
                try {
                    newv = JSON.stringify(JSON.parse(newv), null, 2);
                } catch (e) {
                    newv = String(newv);
                }
                $('#detailsOld').text(oldv);
                $('#detailsNew').text(newv);
                var modal = new bootstrap.Modal(document.getElementById('detailsModal'));
                modal.show();
            });
        });
    })(jQuery);
</script>
@endsection

@section('modals')
<!-- Details modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Log details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Before</h6>
                        <pre id="detailsOld" class="small" style="max-height:60vh; overflow:auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>After</h6>
                        <pre id="detailsNew" class="small" style="max-height:60vh; overflow:auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.main')

@section('content')


<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">EOD Reports</h4>
                        <div class="ms-auto d-flex gap-2 align-items-center">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="eodFilter" class="row g-2 mb-3 eod-filters">
                            <div class="col-lg-2">
                                <label class="form-label">Company</label>
                                <select id="company_id" name="company_id" class="form-select form-select-sm" required>
                                    <option value="">--- select Company ---</option>
                                    @if(isset($companies) && $companies->count())
                                    @foreach($companies as $c)
                                    <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label">Fund</label>
                                <select id="fund_id" name="fund_id" class="form-select form-select-sm" required>
                                    <option value="">--- select Fund ---</option>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label">From</label>
                                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label">To</label>
                                <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" required>
                            </div>
                            <!-- action buttons moved to second row to avoid squeezing -->
                            <div class="w-100"></div>
                        </form>

                        <div class="row mb-3">
                            <div class="col-12 d-flex justify-content-end">
                                <div class="d-flex align-items-center" style="gap:8px;">
                                    <button id="searchReports" type="button" class="btn btn-primary btn-sm d-flex align-items-center">
                                        <span id="searchSpinner" class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                        <span class="material-symbols-outlined">search_activity</span>
                                        <span class="ms-1">Search</span>
                                    </button>
                                    <button id="clearReports" type="button" class="btn btn-primary btn-sm d-flex align-items-center">
                                        <span class="material-symbols-outlined">clear_all</span>
                                        <span class="ms-1">Clear</span>
                                    </button>
                                    <button id="exportExcel" type="button" class="btn btn-primary btn-sm d-flex align-items-center">
                                        <span id="exportExcelSpinner" class="spinner-border spinner-border-sm d-none ms-0 me-1" role="status" aria-hidden="true"></span>
                                        <span class="material-symbols-outlined">file_download</span>
                                        <span class="ms-1">Excel</span>
                                    </button>
                                    <button id="exportPdf" type="button" class="btn btn-primary btn-sm d-flex align-items-center">
                                        <span id="exportPdfSpinner" class="spinner-border spinner-border-sm d-none ms-0 me-1" role="status" aria-hidden="true"></span>
                                        <span class="material-symbols-outlined">picture_as_pdf</span>
                                        <span class="ms-1">PDF</span>
                                    </button>
                                </div>
                            </div>
                        </div>



                        <div class="table-responsive custom-scrollbar">
                            <table id="eodReportsTable" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Company</th>
                                        <th>Fund</th>
                                        <th>Member</th>
                                        <th class="text-end">Units</th>
                                        <th class="text-end">Amount</th>
                                        <th>MOP</th>

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
                $('#searchReports').prop('disabled', true);
            } else {
                $('#searchSpinner').addClass('d-none');
                $('#searchReports').prop('disabled', false);
            }
        }

        var table = null;

        function initTable() {
            if (table) table.destroy();
            table = $('#eodReportsTable').DataTable({
                data: [],
                columns: [{
                        data: 'trans_date'
                    },
                    {
                        data: 'company_name'
                    },
                    {
                        data: 'fund_name'
                    },
                    {
                        data: 'member_no'
                    },
                    {
                        data: 'total_no_of_units',
                        className: 'text-end'
                    },
                    {
                        data: 'total_amount',
                        className: 'text-end'
                    },
                    {
                        data: 'mop'
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

            var companySelect = $('#company_id');
            var fundSelect = $('#fund_id');

            // companies are server-rendered into the select; only load funds dynamically

            companySelect.on('change', function() {
                var id = this.value;
                fundSelect.empty().append(new Option('All', ''));
                if (!id) return;
                // insert a temporary disabled 'Loading...' option inside the select
                var loadingOpt = new Option('Loading...', '');
                loadingOpt.disabled = true;
                loadingOpt.selected = true;
                fundSelect.empty().append(loadingOpt);
                fundSelect.prop('disabled', true);
                $.get('/ajax/funds/' + id).done(function(res) {
                    var list = (res && res.funds) ? res.funds : [];
                    fundSelect.empty().append(new Option('--- select Fund ---', ''));
                    if (Array.isArray(list)) list.forEach(function(f) {
                        fundSelect.append(new Option(f.fund_name, f.id));
                    });
                }).fail(function() {
                    // on failure, show placeholder only
                    fundSelect.empty().append(new Option('--- select Fund ---', ''));
                }).always(function() {
                    fundSelect.prop('disabled', false);
                });
            });

            $('#searchReports').on('click', function() {
                var company = $('#company_id').val();
                var fund = $('#fund_id').val();
                var start = $('#start_date').val();
                var end = $('#end_date').val();

                // client-side validation
                var missing = [];
                if (!company) missing.push('Company');
                if (!fund) missing.push('Fund');
                if (!start) missing.push('From date');
                if (!end) missing.push('To date');
                if (missing.length) {
                    var msg = 'Please provide: ' + missing.join(', ');
                    if (typeof toastr !== 'undefined') toastr.error(msg);
                    else alert(msg);
                    return;
                }

                toggleSpinner(true);
                $('#reportsEmpty').addClass('d-none').text('');
                var data = $('#eodFilter').serialize();
                $.ajax({
                    url: '/eod/reports/list',
                    type: 'GET',
                    data: data,
                    success: function(json) {
                        toggleSpinner(false);
                        if (!json || !json.success) {
                            var msg = (json && json.message) ? json.message : 'Error loading reports';
                            if (typeof toastr !== 'undefined') toastr.error(msg);
                            $('#reportsEmpty').removeClass('d-none').text(msg);
                            if (table) table.clear().draw();
                            return;
                        }
                        if (!json.data || json.data.length === 0) {
                            var noMsg = 'No results';
                            if (typeof toastr !== 'undefined') toastr.info(noMsg);
                            $('#reportsEmpty').removeClass('d-none').text(noMsg);
                            if (table) table.clear().draw();
                            return;
                        }
                        $('#reportsEmpty').addClass('d-none').text('');
                        populateTable(json.data);
                    },
                    error: function(xhr) {
                        toggleSpinner(false);
                        var msg = 'Error loading reports';
                        try {
                            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                            else if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                        } catch (e) {}
                        if (typeof toastr !== 'undefined') toastr.error(msg);
                        $('#reportsEmpty').removeClass('d-none').text(msg);
                        if (table) table.clear().draw();
                    }
                });
            });

            $('#clearReports').on('click', function() {
                $('#eodFilter')[0].reset();
                if (table) table.clear().draw();
                $('#reportsEmpty').addClass('d-none').text('');
            });

            function downloadFromBlob(blob, filename) {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            }

            function exportAction(url, spinnerId, defaultFilename) {
                var company = $('#company_id').val();
                var fund = $('#fund_id').val();
                var start = $('#start_date').val();
                var end = $('#end_date').val();

                var missing = [];
                if (!company) missing.push('Company');
                if (!fund) missing.push('Fund');
                if (!start) missing.push('From date');
                if (!end) missing.push('To date');
                if (missing.length) {
                    var msg = 'Please provide: ' + missing.join(', ');
                    if (typeof toastr !== 'undefined') toastr.error(msg);
                    else alert(msg);
                    return;
                }

                $('#' + spinnerId).removeClass('d-none');
                var data = $('#eodFilter').serialize();
                // use fetch so we can receive blob response easily
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/octet-stream'
                    },
                    body: data
                }).then(function(response) {
                    if (!response.ok) {
                        return response.json().then(function(err) {
                            throw err;
                        });
                    }
                    var disposition = response.headers.get('Content-Disposition') || '';
                    var filename = defaultFilename;
                    var match = disposition.match(/filename\*?=([^;]+)/i);
                    if (match && match[1]) {
                        filename = match[1].replace(/UTF-8''/, '').replace(/"/g, '').trim();
                    }
                    return response.blob().then(function(blob) {
                        return {
                            blob: blob,
                            filename: filename
                        };
                    });
                }).then(function(res) {
                    downloadFromBlob(res.blob, res.filename || defaultFilename);
                }).catch(function(err) {
                    var msg = (err && err.message) ? err.message : 'Failed to prepare export';
                    if (typeof toastr !== 'undefined') toastr.error(msg);
                }).finally(function() {
                    $('#' + spinnerId).addClass('d-none');
                });
            }

            $('#exportExcel').on('click', function() {
                exportAction('/eod/reports/export/excel', 'exportExcelSpinner', 'eod_reports.csv');
            });

            $('#exportPdf').on('click', function() {
                exportAction('/eod/reports/export/pdf', 'exportPdfSpinner', 'eod_reports.pdf');
            });


        });
    })(jQuery);
</script>
@endsection



@endsection
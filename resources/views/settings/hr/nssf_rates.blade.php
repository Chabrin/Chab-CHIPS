@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">NSSF Rates Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_nssf_rates')
                                <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addNssfRateModal">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add NSSF Rate</span>
                                </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Shown while no row is in force; the script shows or hides it each time the table loads --}}
                        <div class="alert alert-info {{ $inUse['source'] === 'standard' ? '' : 'd-none' }}" id="nssfStandardNotice">
                            No rate below is in force today, so payroll is using the standard figures:
                            {{ rtrim(rtrim(number_format(config('nssf.rate') * 100, 2), '0'), '.') }}% of pay, Tier I up to {{ number_format(config('nssf.lower_earnings_limit')) }}
                            and Tier II up to {{ number_format(config('nssf.upper_earnings_limit')) }}.
                        </div>
                        <p class="text-muted small">
                            Payroll uses the row with the latest From Date that has started. A row with a future From Date starts on that day, and
                            earlier months keep the rate that applied then. Rows without a Tier I and Tier II limit are not used.
                        </p>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="nssf-rates-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>From Date</th>
                                        <th>Employee %</th>
                                        <th>Employer %</th>
                                        <th>Tier I up to</th>
                                        <th>Tier II up to</th>
                                        <th>Most one person pays</th>
                                        <th>Tier I name</th>
                                        <th>Tier II name</th>
                                        <th>In use</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Date/Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add/Edit NSSF Rate Modal -->
<div class="modal fade" id="addNssfRateModal" tabindex="-1" aria-labelledby="addNssfRateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNssfRateModalLabel">Add NSSF Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="nssfRateForm">
                    <input type="hidden" name="id" id="nssfRateId">
                    <div class="mb-3">
                        <label for="from_date" class="form-label">From Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" name="from_date" id="from_date" required>
                        <small class="text-muted">The first day these figures apply.</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="employee_perc" class="form-label">Employee % <span style="color:red">*</span></label>
                            <input type="number" class="form-control" name="employee_perc" id="employee_perc" required step="0.01" min="0.01" max="100">
                            <small class="text-muted">Enter 6 for 6%.</small>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="employer_perc" class="form-label">Employer % <span style="color:red">*</span></label>
                            <input type="number" class="form-control" name="employer_perc" id="employer_perc" required step="0.01" min="0" max="100">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="lower_earnings_limit" class="form-label">Tier I up to <span style="color:red">*</span></label>
                            <input type="number" class="form-control" name="lower_earnings_limit" id="lower_earnings_limit" required step="0.01" min="0">
                            <small class="text-muted">Pay up to this amount is Tier I.</small>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="upper_earnings_limit" class="form-label">Tier II up to <span style="color:red">*</span></label>
                            <input type="number" class="form-control" name="upper_earnings_limit" id="upper_earnings_limit" required step="0.01" min="0">
                            <small class="text-muted">Pay from the Tier I limit up to this amount is Tier II.</small>
                        </div>
                    </div>
                    <p class="small mb-3">Most one person pays: <strong id="nssfMaximum">-</strong></p>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="tier1_name" class="form-label">Tier I name</label>
                            <input type="text" class="form-control" name="tier1_name" id="tier1_name" maxlength="60" placeholder="Tier I (NSSF)">
                        </div>
                        <div class="col-6 mb-3">
                            <label for="tier2_name" class="form-label">Tier II name</label>
                            <input type="text" class="form-control" name="tier2_name" id="tier2_name" maxlength="60" placeholder="Tier II (Britam)">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_nssf_rates')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveNssfRateBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // use toastr for notifications
    $(document).ready(function() {
        const money = n => n === null || n === undefined || n === '' ? '-' : Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const limit = n => n === null || n === undefined || n === '' ? '-' : Number(n).toLocaleString();
        const text = data => data ? $('<div>').text(data).html() : '-';

        let table = $('#nssf-rates-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/nssf/rates/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'nssfrates'
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'from_date', title: 'From Date', render: data => data ? data : '-' },
                { data: 'employee_perc', title: 'Employee %', render: data => data !== null ? data : '-' },
                { data: 'employer_perc', title: 'Employer %', render: data => data !== null ? data : '-' },
                { data: 'lower_earnings_limit', title: 'Tier I up to', render: limit },
                { data: 'upper_earnings_limit', title: 'Tier II up to', render: limit },
                { data: 'maximum_contribution', title: 'Most one person pays', render: money },
                { data: 'tier1_name', title: 'Tier I name', render: text },
                { data: 'tier2_name', title: 'Tier II name', render: text },
                { data: 'in_use', title: 'In use', render: data => data ? '<span class="badge bg-success">In use</span>' : '' },
                { data: 'current_status_id', title: 'Status', render: data => data == 5 ? 'Active' : 'Inactive' },
                { data: 'created_by', title: 'Created By', render: data => data && data.name ? data.name : '-' },
                { data: 'date_time', title: 'Date/Time', render: data => data ? new Date(data).toLocaleString() : '-' },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-nssf-rate" data-id="${row.id}" style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-nssf-rate"
                                    data-id="${row.id}" style="padding:1px 2px;">
                                    <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                    <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                                </button>`;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        // The standard-figures notice applies only while no row is in force
        table.on('xhr', function(e, settings, json) {
            const inForce = ((json && json.nssfrates) || []).some(row => row.in_use);
            $('#nssfStandardNotice').toggleClass('d-none', inForce);
        });

        // The most one person pays is worked out from the rate and the Tier II limit
        function showMaximum() {
            const rate = parseFloat($('#employee_perc').val());
            const top = parseFloat($('#upper_earnings_limit').val());
            $('#nssfMaximum').text(rate > 0 && top > 0 ? money(rate * top / 100) : '-');
        }
        $('#employee_perc, #upper_earnings_limit').on('input', showMaximum);

        $('#nssfRateForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#nssfRateId').val();
            var url = id ? "{{ url('/ajax/hr/nssf/rates') }}/" + id : "{{ url('/ajax/hr/nssf/rates') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveNssfRateBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();

            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addNssfRateModal').modal('hide');
                        $('#nssfRateForm')[0].reset();
                        $('#nssfRateId').val('');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-nssf-rate', function() {
            const row = table.row($(this).closest('tr')).data();
            $('#addNssfRateModalLabel').text('Edit NSSF Rate');
            $('#nssfRateId').val(row.id);
            $('#from_date').val(row.from_date);
            $('#employee_perc').val(row.employee_perc);
            $('#employer_perc').val(row.employer_perc);
            $('#lower_earnings_limit').val(row.lower_earnings_limit);
            $('#upper_earnings_limit').val(row.upper_earnings_limit);
            $('#tier1_name').val(row.tier1_name);
            $('#tier2_name').val(row.tier2_name);
            showMaximum();
            $('#addNssfRateModal').modal('show');
        });

        $(document).on('click', '.delete-nssf-rate', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this NSSF rate?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/nssf/rates') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Deleted');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);

                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addNssfRateModal').on('hidden.bs.modal', function() {
            $('#nssfRateForm')[0].reset();
            $('#nssfRateId').val('');
            $('#addNssfRateModalLabel').text('Add NSSF Rate');
            showMaximum();
        });
    });
</script>
@endsection

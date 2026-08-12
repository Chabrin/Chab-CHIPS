@extends('layouts.main')
<link rel="stylesheet" href="{{ asset('assets/css/datatables.css') }}">

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Statutory Deductions Report</h4>
                    </div>
                    <div class="card-body">
                        <!-- Filter Form -->
                        <form id="reportFilterForm">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="deduction_type" class="form-label">Deduction Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="deduction_type" name="deduction_type" required>
                                        <option value="">-- Select Deduction Type --</option>
                                        <option value="NSSF">NSSF</option>
                                        <option value="SHIF">SHIF</option>
                                        <option value="Housing Levy">Housing Levy</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="period" class="form-label">Period <span class="text-danger">*</span></label>
                                    <input type="month" class="form-control" id="period" name="period" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-flex align-items-center w-100" id="loadReportBtn">
                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="loadSpinner"></span>
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;" id="loadIcon">search</span>
                                        <span id="loadBtnText">Load Report</span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Results Section -->
                        <div id="resultsSection" class="mt-4">
                            <hr>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <h5 class="mb-0" id="reportTitle">Statutory Deductions</h5>
                                    <p class="text-muted mb-0" id="reportSubtitle">Select deduction type and period to load report</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center ms-auto" id="downloadExcelBtn" disabled>
                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="downloadSpinner"></span>
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;" id="downloadIcon">download</span>
                                        <span>Download Excel</span>
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive custom-scrollbar">
                                <table class="display nowrap" id="deductionsTable" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Staff Number</th>
                                            <th>Full Names</th>
                                            <th>National ID</th>
                                            <th>Amount (KSh)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- DataTable will populate this -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="4" class="text-end">Total:</th>
                                            <th class="text-end" id="totalAmount">0.00</th>
                                        </tr>
                                        <tr>
                                            <th colspan="4" class="text-end">Total Records:</th>
                                            <th class="text-end" id="totalCount">0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/datatable/datatables/jquery.dataTables.min.js') }}"></script>
<script>
    let dataTable = null;

    $(document).ready(function() {
        // Set default period to current month
        const today = new Date();
        const currentMonth = today.toISOString().substr(0, 7);
        $('#period').val(currentMonth);

        // Initialize DataTable on page load with empty data
        dataTable = $('#deductionsTable').DataTable({
            processing: true,
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            scrollX: true,
            autoWidth: false,
            data: [], // Start with empty data
            columns: [{
                    data: 'no',
                    width: '5%'
                },
                {
                    data: 'staff_number',
                    width: '15%'
                },
                {
                    data: 'full_names',
                    width: '35%',
                    className: 'text-nowrap'
                },
                {
                    data: 'national_id',
                    width: '20%'
                },
                {
                    data: 'amount',
                    width: '15%',
                    className: 'text-end'
                }
            ]
        });

        // Form submission
        $('#reportFilterForm').on('submit', function(e) {
            e.preventDefault();
            loadReport();
        });

        // Download Excel
        $('#downloadExcelBtn').on('click', function() {
            downloadExcel();
        });
    });

    function loadReport() {
        const deductionType = $('#deduction_type').val();
        const period = $('#period').val();

        if (!deductionType || !period) {
            toastr.error('Please select both deduction type and period');
            return;
        }

        const $btn = $('#loadReportBtn');
        const $spinner = $('#loadSpinner');
        const $icon = $('#loadIcon');
        const $btnText = $('#loadBtnText');

        // Show loading state
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');
        $btnText.text('Loading...');

        $.ajax({
            url: '/ajax/statutory-deductions/data',
            method: 'POST',
            data: {
                deduction_type: deductionType,
                period: period,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response, deductionType, period);
                } else {
                    toastr.error(response.message || 'Failed to load report');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to load report';
                toastr.error(message);
            },
            complete: function() {
                // Reset button state
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $icon.removeClass('d-none');
                $btnText.text('Load Report');
            }
        });
    }

    function displayResults(response, deductionType, period) {
        // Update report title
        const periodDate = new Date(period + '-01');
        const periodText = periodDate.toLocaleString('default', {
            month: 'long',
            year: 'numeric'
        });
        $('#reportTitle').text(deductionType + ' Deductions Report');
        $('#reportSubtitle').text('Period: ' + periodText + ' | Total Records: ' + response.count);

        // Update totals
        $('#totalAmount').text(response.total);
        $('#totalCount').text(response.count);

        // Clear and reload data in DataTable
        dataTable.clear();

        if (response.data.length > 0) {
            dataTable.rows.add(response.data);
        }

        dataTable.draw();

        // Enable download button
        $('#downloadExcelBtn').prop('disabled', false);

        toastr.success('Report loaded successfully');
    }

    function downloadExcel() {
        const deductionType = $('#deduction_type').val();
        const period = $('#period').val();

        if (!deductionType || !period) {
            toastr.error('Please load a report first');
            return;
        }

        const $btn = $('#downloadExcelBtn');
        const $spinner = $('#downloadSpinner');
        const $icon = $('#downloadIcon');

        // Show loading state
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        // Create form and submit
        const form = $('<form>', {
            'method': 'POST',
            'action': '/statutory-deductions/download'
        });

        form.append($('<input>', {
            'type': 'hidden',
            'name': '_token',
            'value': '{{ csrf_token() }}'
        }));

        form.append($('<input>', {
            'type': 'hidden',
            'name': 'deduction_type',
            'value': deductionType
        }));

        form.append($('<input>', {
            'type': 'hidden',
            'name': 'period',
            'value': period
        }));

        $('body').append(form);
        form.submit();
        form.remove();

        // Reset button state after a delay
        setTimeout(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');
            toastr.success('Download started');
        }, 1000);
    }
</script>
@endsection
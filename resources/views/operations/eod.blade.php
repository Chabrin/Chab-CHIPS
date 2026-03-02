@extends('layouts.main')
@section('content')
<style>
    .stat-box {
        padding: 10px;
        border-radius: 5px;
        background-color: #f8f9fa;
        margin-bottom: 10px;
    }

    .stat-box h4,
    .stat-box h5 {
        margin-bottom: 5px;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">
                            <span class="material-symbols-outlined me-2">schedule</span>
                            End of Day (EOD) Processing
                        </h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')

                        <form id="eodForm" class="row g-3">
                            @csrf <!-- Company Selection -->
                            <div class="col-lg-4 col-md-6 col-sm-12">
                                <label for="company_id" class="form-label">
                                    <span class="material-symbols-outlined me-1">business</span>
                                    Company <span class="text-danger">*</span>
                                </label>
                                <select name="company_id" id="company_id" class="form-control" required>
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                    <option value="{{ $company->id }}" data-code="{{ $company->company_code }}">
                                        {{ $company->company_name }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!-- Start Date -->
                            <div class="col-lg-4 col-md-6 col-sm-12">
                                <label for="start_date" class="form-label">
                                    <span class="material-symbols-outlined me-1">calendar_today</span>
                                    Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!-- End Date -->
                            <div class="col-lg-4 col-md-6 col-sm-12">
                                <label for="end_date" class="form-label">
                                    <span class="material-symbols-outlined me-1">event</span>
                                    End Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!-- Fund Selection Type -->
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">
                                    <span class="material-symbols-outlined me-1">account_balance</span>
                                    Fund Selection <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fund_selection" id="all_funds" value="all" checked>
                                        <label class="form-check-label" for="all_funds">
                                            <span class="material-symbols-outlined me-1">select_all</span>
                                            All Funds
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fund_selection" id="single_fund" value="single">
                                        <label class="form-check-label" for="single_fund">
                                            <span class="material-symbols-outlined me-1">filter_1</span>
                                            Single Fund
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Single Fund Selection -->
                            <div class="col-lg-6 col-md-6 col-sm-12" id="fund_selection_container" style="display: none;">
                                <label for="fund_id" class="form-label">
                                    <span class="material-symbols-outlined me-1">savings</span>
                                    Select Fund <span class="text-danger">*</span>
                                </label>
                                <select name="fund_id" id="fund_id" class="form-control">
                                    <option value="">Select Company First</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!-- Process Button -->
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center" id="resetBtn">
                                        <span class="material-symbols-outlined">restart_alt</span>
                                        <span class="ms-1">Reset Form</span>
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="processBtn">
                                        <span class="material-symbols-outlined">play_arrow</span>
                                        <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                                        <span class="ms-1">Process EOD</span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Processing Results -->
                        <div id="processing-results" class="mt-4" style="display: none;">
                            <div class="card border-success">
                                <div class="card-header bg-light-success">
                                    <h5 class="card-title text-success mb-0">
                                        <span class="material-symbols-outlined me-2">check_circle</span>
                                        EOD Processing Results
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="results-content">
                                        <!-- Results will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Processing Log -->
                        <div id="processing-log" class="mt-4" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="material-symbols-outlined me-2">description</span>
                                        Processing Log
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="log-content" class="bg-dark text-light p-3 rounded" style="font-family: monospace; max-height: 300px; overflow-y: auto;">
                                        <!-- Log entries will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Set default dates (current date)
        const today = new Date().toISOString().split('T')[0];
        $('#start_date').val(today);
        $('#end_date').val(today);

        // Toggle fund selection visibility
        $('input[name="fund_selection"]').change(function() {
            const fundContainer = $('#fund_selection_container');
            const fundSelect = $('#fund_id');

            if ($(this).val() === 'single') {
                fundContainer.show();
                fundSelect.prop('required', true);
            } else {
                fundContainer.hide();
                fundSelect.prop('required', false);
                fundSelect.val('');
            }
        });

        // Load funds when company changes
        $('#company_id').change(function() {
            const companyId = $(this).val();
            const fundSelect = $('#fund_id');

            if (companyId) {
                // Show loading in fund dropdown
                fundSelect.html('<option value="">Loading funds...</option>');
                fundSelect.prop('disabled', true);

                $.ajax({
                    url: '/operations/company/' + companyId + '/funds',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            let options = '<option value="">Select Fund</option>';
                            response.funds.forEach(function(fund) {
                                options += '<option value="' + fund.id + '" data-code="' + fund.fund_code + '">';
                                options += fund.fund_name + ' (' + fund.fund_code + ')';
                                options += '</option>';
                            });
                            fundSelect.html(options);
                        } else {
                            fundSelect.html('<option value="">Error loading funds</option>');
                            toastr.error('Failed to load funds');
                        }
                        fundSelect.prop('disabled', false);
                    },
                    error: function() {
                        fundSelect.html('<option value="">Error loading funds</option>');
                        fundSelect.prop('disabled', false);
                        toastr.error('Failed to load funds');
                    }
                });
            } else {
                fundSelect.html('<option value="">Select Company First</option>');
                fundSelect.prop('disabled', false);
            }
        });

        // Validate date range
        $('#start_date, #end_date').change(function() {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());

            if (startDate && endDate && startDate > endDate) {
                toastr.warning('Start date cannot be after end date');
                $(this).focus();
            }
        });

        // Reset form
        $('#resetBtn').click(function() {
            $('#eodForm')[0].reset();
            $('#fund_selection_container').hide();
            $('#fund_id').prop('required', false);
            $('#processing-results, #processing-log').hide();
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');

            // Reset fund dropdown
            $('#fund_id').html('<option value="">Select Company First</option>');

            // Reset to default dates
            $('#start_date').val(today);
            $('#end_date').val(today);
            $('#all_funds').prop('checked', true);

            toastr.info('Form has been reset');
        });

        // Form submission
        $('#eodForm').submit(function(e) {
            e.preventDefault();

            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');

            const $btn = $('#processBtn');
            const $spinner = $btn.find('.spinner-border');

            // Validate form
            if (!validateForm()) {
                return;
            }

            // Show loading state
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');

            // Show processing log
            $('#processing-log').show();
            addLogEntry('Starting EOD processing...');

            const formData = $(this).serialize();

            $.ajax({
                url: '{{ route("operations.eod.process") }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');

                    if (response.success) {
                        addLogEntry('EOD processing completed successfully');
                        displayResults(response);
                        toastr.success('EOD processing completed successfully');
                    } else {
                        addLogEntry('Error: ' + response.message);
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');

                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        displayValidationErrors(xhr.responseJSON.errors);
                    } else {
                        const errorMsg = xhr.responseJSON?.message || 'An error occurred during processing';
                        addLogEntry('Error: ' + errorMsg);
                        toastr.error(errorMsg);
                    }
                }
            });
        });

        function validateForm() {
            let isValid = true;

            // Validate required fields
            $('#eodForm [required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    $(this).next('.invalid-feedback').text('This field is required');
                    isValid = false;
                }
            });

            // Validate date range
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());

            if (startDate > endDate) {
                $('#end_date').addClass('is-invalid');
                $('#end_date').next('.invalid-feedback').text('End date must be after or equal to start date');
                isValid = false;
            }

            return isValid;
        }

        function displayValidationErrors(errors) {
            $.each(errors, function(field, messages) {
                const $field = $('#' + field);
                $field.addClass('is-invalid');
                $field.next('.invalid-feedback').text(messages[0]);
                addLogEntry('Validation Error - ' + field + ': ' + messages[0]);
            });
        }

        function displayResults(response) {
            $('#processing-results').show();
            let html = '';

            // Clean minimal summary
            if (response.summary) {
                html += '<div class="row">';

                // Funds Processed
                html += '<div class="col-md-3">';
                html += '<div class="text-center py-2">';
                html += '<div class="fs-5 fw-normal text-dark mb-1">' + response.summary.funds_processed + '</div>';
                html += '<div class="small text-muted">Funds Processed</div>';
                html += '</div>';
                html += '</div>';

                // Number of Records
                html += '<div class="col-md-3">';
                html += '<div class="text-center py-2">';
                html += '<div class="fs-5 fw-normal text-dark mb-1">' + response.summary.total_entries_created + '</div>';
                html += '<div class="small text-muted">Records Processed</div>';
                html += '</div>';
                html += '</div>';

                // Period
                html += '<div class="col-md-3">';
                html += '<div class="text-center py-2">';
                html += '<div class="fs-6 fw-normal text-dark mb-1">' + response.summary.period + '</div>';
                html += '<div class="small text-muted">Processing Period</div>';
                html += '</div>';
                html += '</div>';

                html += '</div>'; // End row
            }

            $('#results-content').html(html);
        }

        function addLogEntry(message) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = '[' + timestamp + '] ' + message + '\n';
            $('#log-content').append(logEntry);
            $('#log-content').scrollTop($('#log-content')[0].scrollHeight);
        }
    });
</script>
@endsection
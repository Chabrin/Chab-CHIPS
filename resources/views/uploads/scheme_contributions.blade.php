@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Scheme Contributions CSV Upload</h4>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif
                        @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <form id="schemeUploadForm" method="post" enctype="multipart/form-data" action="{{ url('/upload/scheme/contributions') }}">
                            @csrf

                            @if(isset($companies) && count($companies) > 0)
                            <ul class="nav nav-tabs mb-3" id="uploadTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button" role="tab" aria-controls="upload" aria-selected="true">
                                        <span class="material-symbols-outlined me-1">file_upload</span> Uploads
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview" type="button" role="tab" aria-controls="preview" aria-selected="false" style="display: none;">
                                        <span class="material-symbols-outlined me-1">preview</span> Preview
                                        <span class="badge bg-success ms-1" id="preview-badge" style="display: none; background-color: #3598db !important;"></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="instructions-tab" data-bs-toggle="tab" data-bs-target="#instructions" type="button" role="tab" aria-controls="instructions" aria-selected="false">
                                        <span class="material-symbols-outlined me-1">info</span> Instructions & Templates
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    @php $hasUploadErrors = session()->has('upload_errors') && is_array(session('upload_errors')) && count(session('upload_errors')) > 0; @endphp
                                    <button class="nav-link {{ $hasUploadErrors ? 'text-danger' : '' }}" id="errors-tab" data-bs-toggle="tab" data-bs-target="#errors" type="button" role="tab" aria-controls="errors" aria-selected="false">
                                        <span class="material-symbols-outlined me-1">error_outline</span> Errors
                                        @if($hasUploadErrors)
                                        <span class="badge bg-danger ms-1">{{ count(session('upload_errors')) }}</span>
                                        @endif
                                    </button>
                                </li>
                            </ul>
                            <div class="tab-content" id="uploadTabContent">
                                <div class="tab-pane fade show active" id="upload" role="tabpanel" aria-labelledby="upload-tab">
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Company <span class="text-danger">*</span></label>
                                            <select id="companySelect" name="company_id" class="form-control form-control-sm" required>
                                                <option value="">Select company</option>
                                                @foreach($companies as $c)
                                                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Fund <span class="text-danger">*</span></label>
                                            <select id="fundSelect" name="fund_code" class="form-control form-control-sm" required>
                                                <option value="">Select fund</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Value Date <span class="text-danger">*</span></label>
                                            <input type="date" id="valueDateInput" name="value_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required disabled>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3" id="navInfoRow" style="display: none;">
                                        <div class="col-md-8">
                                            <div id="navInfoAlert" class="alert mb-0 py-2" role="alert">
                                                <small id="navInfoText"></small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Method of Payment <span class="text-danger">*</span></label>
                                            <select name="mop" class="form-control form-control-sm" required>
                                                <option value="">-- Select MOP --</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Cheque">Cheque</option>
                                                <option value="Electronic">Electronic</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" name="reference_no" class="form-control form-control-sm" placeholder="Enter reference number">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Amount Received <span class="text-danger">*</span></label>
                                            <input type="number" name="amount_received" class="form-control form-control-sm" step="0.01" min="0" placeholder="Enter total amount received" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">CSV file <span class="text-danger">*</span></label>
                                        <div class="small text-danger mb-1">Only .csv files are allowed. Amounts can contain commas.</div>
                                        <input id="csvFileInput" type="file" name="csv_file" class="form-control form-control-sm" accept=".csv">
                                    </div>
                                    <br>
                                    <div class="d-flex gap-2 mt-3">

                                        <a href="{{ url('/upload/scheme/contributions/template') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center download-template-btn" style="width:auto;">
                                            <span class="material-symbols-outlined">file_download</span>
                                            <span class="ms-1">Download Template</span>
                                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                        </a>
                                        <button id="uploadBtn" type="submit" class="btn btn-primary btn-sm d-flex align-items-center">
                                            <span class="material-symbols-outlined">file_upload</span>
                                            <span class="ms-1">Upload Data</span>
                                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="preview" role="tabpanel" aria-labelledby="preview-tab">
                                    <div id="previewLoader" class="text-center py-5" style="display: none;">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <div class="mt-2">Loading staging data...</div>
                                    </div>

                                    <div id="previewContent" style="display: none;">
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="row" id="previewSummary">
                                                    <!-- Summary cards will be loaded here -->
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">Uploads Preview</h6>
                                                <div class="d-flex gap-2">
                                                    <button type="button" id="processToMainBtn" class="btn btn-primary btn-sm d-flex align-items-center">
                                                        <span class="material-symbols-outlined">check_circle</span>
                                                        <span class="ms-1">Complete Process</span>
                                                        <span id="processSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                                    </button>

                                                    <button type="button" id="deleteStagingBtn" class="btn btn-secondary btn-sm d-flex align-items-center">
                                                        <span class="material-symbols-outlined">clear_all</span>
                                                        <span class="ms-1">Delete</span>
                                                        <span id="deleteSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table id="stagingDataTable" class="table table-sm table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Payroll ID</th>
                                                                <th>Employee Name</th>
                                                                <th>Email</th>
                                                                <th>Date of Birth</th>
                                                                <th>ID Number</th>
                                                                <th>Employee Amount</th>
                                                                <th>Employer Amount</th>
                                                                <th>Voluntary Amount</th>
                                                                <th>Total Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!-- Data will be loaded via AJAX -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="noPreviewData" class="text-center py-5" style="display: none;">
                                        <div class="text-muted">
                                            <span class="material-symbols-outlined" style="font-size: 48px;">upload_file</span>
                                            <div class="mt-2">No staging data available</div>
                                            <div class="small">Upload a file first to see preview</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="instructions" role="tabpanel" aria-labelledby="instructions-tab">
                                    <h6>Instructions</h6>
                                    <p>Use the template to prepare your CSV. All columns are required.Dates must be parseable (e.g. YYYY-MM-DD).</p>
                                    <p>Template columns: payroll_id, employee_amount, employer_amount, voluntary_amount, total_amounts, effective_date, title, dob, first_name, surname, other_names, gms_no, id_no, pin_no, physical_address, postal_address, town, country, email, retirement_age, date_joined</p>
                                    <div class="mt-3">
                                        <a href="{{ url('/upload/scheme/contributions/template') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center download-template-btn" style="width:auto;">
                                            <span class="material-symbols-outlined">file_download</span>
                                            <span class="ms-1">Download CSV Template</span>
                                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                        </a>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="errors" role="tabpanel" aria-labelledby="errors-tab">
                                    <h6>Upload Errors</h6>
                                    <hr>
                                    @if($hasUploadErrors)
                                    <div class="alert alert-danger small">{{ count(session('upload_errors')) }} errors were returned. See list below.</div>
                                    @endif
                                    <div id="errorsAlertContainer" class="mb-2" style="display:none;"></div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Error</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if($hasUploadErrors)
                                                @foreach(session('upload_errors') as $i => $err)
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td class="small">{{ $err }}</td>
                                                </tr>
                                                @endforeach
                                                @else
                                                <tr>
                                                    <td colspan="2">No errors</td>
                                                </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                    </div>
                    @else
                    <div class="alert alert-warning">You have no companies assigned. Contact admin.</div>
                    @endif
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
</div>
</div>
@php
$metaHasErrors = session()->has('upload_errors') && is_array(session('upload_errors')) && count(session('upload_errors')) > 0;
$metaErrorCount = $metaHasErrors ? count(session('upload_errors')) : 0;
$metaErrorsJson = $metaHasErrors ? json_encode(session('upload_errors')) : '[]';
@endphp
<div id="uploadMeta" data-has-errors="{{ $metaHasErrors ? 1 : 0 }}" data-error-count="{{ $metaErrorCount }}" data-errors="{{ e($metaErrorsJson) }}" style="display:none"></div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmProcessModal" tabindex="-1" aria-labelledby="confirmProcessModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmProcessModalLabel">Confirm Upload Processing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to post this upload as final?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmProcessBtn">
                    <span class="material-symbols-outlined me-1" style="font-size: 16px;">check_circle</span>
                    Yes, Post as Final
                </button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    $(function() {
        var $company = $('#companySelect');
        var $fund = $('#fundSelect');
        var $valueDate = $('#valueDateInput');
        var $uploadBtn = $('#uploadBtn');
        var navAvailable = false;

        // Initially disable upload button and value date
        $uploadBtn.prop('disabled', true);
        $valueDate.prop('disabled', true);

        function resetFunds() {
            $fund.html('<option value="">Select fund</option>');
            $valueDate.prop('disabled', true);
            hideNavInfo();
            navAvailable = false;
            $uploadBtn.prop('disabled', true);
        }

        function setLoading() {
            $fund.html('<option>Loading...</option>');
        }

        function showNavInfo(message, isSuccess) {
            var $alert = $('#navInfoAlert');
            var $row = $('#navInfoRow');

            $alert.removeClass('alert-success alert-danger alert-warning alert-light-success alert-light-danger')
                .addClass(isSuccess ? 'alert-light-success' : 'alert-light-danger');
            $('#navInfoText').html(message);
            $row.slideDown();
        }

        function hideNavInfo() {
            $('#navInfoRow').slideUp();
        }

        function checkNavAvailability() {
            var fundCode = $fund.val();
            var valueDate = $valueDate.val();

            if (!fundCode || !valueDate) {
                hideNavInfo();
                navAvailable = false;
                $uploadBtn.prop('disabled', true);
                return;
            }

            // Show loading state
            showNavInfo('<i class="fa fa-spinner fa-spin"></i> Checking NAV availability...', false);
            $uploadBtn.prop('disabled', true);

            $.ajax({
                url: '/ajax/check-nav',
                method: 'GET',
                data: {
                    fund_code: fundCode,
                    value_date: valueDate
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            }).done(function(response) {
                if (response.success && response.found) {
                    navAvailable = true;
                    var message = '<strong>NAV Available:</strong> Price: <strong>' + parseFloat(response.price).toFixed(4) + '</strong>';
                    if (response.amount) {
                        message += ' | Amount: <strong>' + parseFloat(response.amount).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) + '</strong>';
                    }
                    showNavInfo(message, true);
                    $uploadBtn.prop('disabled', false);
                    toastr.success('NAV is available for selected fund and date');
                } else {
                    navAvailable = false;
                    var errorMsg = response.message || 'NAV not found for the selected fund and date. Please ensure NAV is available before uploading.';
                    showNavInfo('<strong>NAV Not Available:</strong> ' + errorMsg, false);
                    $uploadBtn.prop('disabled', true);
                    toastr.error('NAV not found for selected fund and date');
                }
            }).fail(function(xhr) {
                navAvailable = false;
                var errorMessage = 'Error checking NAV availability';
                try {
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                } catch (e) {}
                showNavInfo('<strong>Error:</strong> ' + errorMessage, false);
                $uploadBtn.prop('disabled', true);
                toastr.error(errorMessage);
            });
        }

        $company.on('change', function() {
            var cid = $(this).val();
            resetFunds();
            if (!cid) {
                return;
            }
            setLoading();
            $.get('/ajax/funds/' + cid)
                .done(function(res) {
                    resetFunds();
                    var list = [];
                    if (!res) return;
                    if (Array.isArray(res)) list = res;
                    else if (res.data && Array.isArray(res.data)) list = res.data;
                    else if (res.funds && Array.isArray(res.funds)) list = res.funds;

                    if (list.length === 0) {
                        toastr.info('No funds found for selected company');
                        return;
                    }

                    list.forEach(function(f) {
                        var code = f.fund_code || f.code || f.id || '';
                        var name = f.fund_name || f.name || f.fund || code;
                        if (code) $fund.append('<option value="' + code + '">' + name + '</option>');
                    });
                })
                .fail(function() {
                    resetFunds();
                    toastr.error('Failed to load funds for the selected company');
                });
        });

        // Enable value date when fund is selected and check NAV
        $fund.on('change', function() {
            var fundCode = $(this).val();
            if (fundCode) {
                $valueDate.prop('disabled', false);
                checkNavAvailability();
            } else {
                $valueDate.prop('disabled', true);
                hideNavInfo();
                navAvailable = false;
                $uploadBtn.prop('disabled', true);
            }
        });

        // Check NAV when date changes
        $valueDate.on('change', function() {
            checkNavAvailability();
        });

        // handle spinners for download and upload
        $('.download-template-btn').on('click', function(e) {
            var $btn = $(this);
            var $spinner = $btn.find('.spinner-border');
            $spinner.removeClass('d-none');
            setTimeout(function() {
                $spinner.addClass('d-none');
            }, 3000);
        });

        // AJAX form submit handler
        $('#schemeUploadForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $('#uploadBtn');
            var $spinner = $btn.find('.spinner-border');

            // client-side validation: file selected and has .csv extension
            var fileInput = document.getElementById('csvFileInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                toastr.error('Please select a CSV file to upload');
                return;
            }
            var file = fileInput.files[0];
            var name = file.name || '';
            if (!name.match(/\.csv$/i)) {
                toastr.error('Only .csv files are allowed');
                return;
            }

            // Build FormData and read CSRF token first (disabled inputs are excluded from FormData)
            var url = $form.attr('action');
            var fd = new FormData($form[0]);
            var csrf = $('input[name="_token"]').val();

            // disable form and show spinner
            $btn.prop('disabled', true);
            $form.find('input,select,button').prop('disabled', true);
            $spinner.removeClass('d-none');

            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf
                },
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function(resp, status, xhr) {
                // success responses
                if (resp && resp.success) {
                    toastr.success('Uploaded ' + (resp.processed || 0) + ' rows to staging');
                    updateErrorsTab([]);

                    // Store summary_id and show preview tab
                    window.currentSummaryId = resp.summary_id;
                    showPreviewTab();
                    loadStagingData(resp.summary_id);
                } else if (resp && resp.errors) {
                    // server returned errors array
                    updateErrorsTab(resp.errors || []);
                    toastr.error(resp.message || 'Upload completed with errors');
                } else {
                    // unexpected but valid JSON
                    toastr.info('Upload completed');
                }
            }).fail(function(xhr) {
                // handle validation 422 or other errors
                var parsed = null;
                try {
                    if (xhr && xhr.responseJSON) parsed = xhr.responseJSON;
                    else if (xhr && xhr.responseText) parsed = JSON.parse(xhr.responseText);
                } catch (e) {
                    console.error('Failed to parse error response', e, xhr && xhr.responseText);
                }

                if (xhr && xhr.status === 422 && parsed && parsed.errors) {
                    // Laravel validation errors
                    var vErrors = parsed.errors || {};
                    var errList = [];
                    Object.keys(vErrors).forEach(function(k) {
                        if (Array.isArray(vErrors[k])) {
                            vErrors[k].forEach(function(m) {
                                errList.push(k + ': ' + m);
                            });
                        } else {
                            errList.push(k + ': ' + vErrors[k]);
                        }
                    });
                    updateErrorsTab(errList);
                    toastr.error(parsed.message || 'Validation error. See Errors tab.');
                } else if (parsed && parsed.errors) {
                    // errors array from server
                    var flat = [];
                    if (Array.isArray(parsed.errors)) flat = parsed.errors;
                    else if (typeof parsed.errors === 'object') {
                        Object.keys(parsed.errors).forEach(function(k) {
                            var item = parsed.errors[k];
                            if (Array.isArray(item)) item.forEach(function(m) {
                                flat.push(k + ': ' + m);
                            });
                            else flat.push(k + ': ' + item);
                        });
                    }
                    updateErrorsTab(flat);
                    toastr.error(parsed.message || 'Upload completed with errors');
                } else {
                    var msg = 'Upload failed';
                    if (parsed && parsed.message) msg = parsed.message;
                    toastr.error(msg);
                    // log full xhr for debugging
                    console.error('Upload failed', xhr);
                }
            }).always(function() {
                $spinner.addClass('d-none');
                $btn.prop('disabled', false);
                $form.find('input,select,button').prop('disabled', false);
            });
        });

        // If there are upload errors returned via session, toast and flag the Errors tab
        var $meta = $('#uploadMeta');
        var hasErrors = ($meta.data('has-errors') == 1);
        if (hasErrors) {
            var count = parseInt($meta.data('error-count') || 0, 10);
            toastr.error('Upload completed with ' + count + ' errors. See Errors tab.');
            // add red outline to the errors tab
            $('#errors-tab').addClass('border border-danger rounded');
            // populate errors table from session (read JSON from data attribute)
            try {
                var errorsJson = $meta.attr('data-errors') || '[]';
                var errorsArr = JSON.parse(errorsJson);
                updateErrorsTab(errorsArr);
            } catch (e) {
                updateErrorsTab([]);
            }
        }

        // helper to populate the Errors tab dynamically
        function updateErrorsTab(errors) {
            var $tbody = $('#errors table tbody');
            if (!$tbody.length) return;
            var $alert = $('#errorsAlertContainer');
            var $table = $('#errors table');
            $tbody.empty();

            if (!errors || errors.length === 0) {
                $tbody.append('<tr><td colspan="2">No errors</td></tr>');
                $('#errors-tab').removeClass('border border-danger rounded');
                $alert.hide().empty();
                $table.show();
                return;
            }

            // Build the single alert using the user's requested classes
            var alertHtml = '<div class="alert alert-light-danger" role="alert">';
            alertHtml += '<p class="txt-danger">';
            // join errors with <br>
            errors.forEach(function(it, i) {
                alertHtml += $('<div>').text(it).html();
                if (i !== errors.length - 1) alertHtml += '<br/>';
            });
            alertHtml += '</p></div>';

            // hide the table and show the alert only
            $table.hide();
            $alert.html(alertHtml).show();
            // flag the tab visually
            $('#errors-tab').addClass('border border-danger rounded');
        }

        // Preview tab functions
        function showPreviewTab() {
            $('#preview-tab').show().find('.badge').show();
            $('#preview-tab').tab('show');
        }

        function loadStagingData(summaryId) {
            $('#previewLoader').show();
            $('#previewContent').hide();
            $('#noPreviewData').hide();

            if (!summaryId) {
                summaryId = window.currentSummaryId;
            }

            if (!summaryId) {
                $('#noPreviewData').show();
                $('#previewLoader').hide();
                return;
            }

            $.ajax({
                url: '/uploads/scheme/contributions/staging-data',
                method: 'GET',
                data: {
                    summary_id: summaryId
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            }).done(function(response) {
                if (response.success && response.summary && response.data) {
                    displayPreviewData(response.summary, response.data);
                    $('#previewContent').show();
                    $('#preview-badge').text(response.data.length);
                } else {
                    $('#noPreviewData').show();
                }
            }).fail(function(xhr) {
                console.error('Failed to load staging data', xhr);
                toastr.error('Failed to load staging data');
                $('#noPreviewData').show();
            }).always(function() {
                $('#previewLoader').hide();
            });
        }

        function displayPreviewData(summary, data) {
            // Display simple summary in one row
            var summaryHtml = '<div class="col-12">';
            summaryHtml += '<p class="mb-2"><strong>Upload Summary:</strong> ';
            summaryHtml += '<span style="color: #3598db; font-weight: bold;">' + (summary.total_records || 0) + ' records | ';
            summaryHtml += 'Employee: ' + formatMoney(summary.total_employee_amount || 0) + ' | ';
            summaryHtml += 'Employer: ' + formatMoney(summary.total_employer_amount || 0) + ' | ';
            summaryHtml += 'Voluntary: ' + formatMoney(summary.total_voluntary_amount || 0) + '</span>';
            summaryHtml += '</p></div>';

            $('#previewSummary').html(summaryHtml);

            // Initialize DataTable
            if ($.fn.DataTable.isDataTable('#stagingDataTable')) {
                $('#stagingDataTable').DataTable().destroy();
            }

            var tableData = data.map(function(item) {
                // Format dob to show only date part (YYYY-MM-DD)
                var formattedDob = '';
                if (item.dob) {
                    try {
                        var dobDate = new Date(item.dob);
                        if (!isNaN(dobDate.getTime())) {
                            formattedDob = dobDate.toISOString().split('T')[0];
                        } else {
                            formattedDob = item.dob;
                        }
                    } catch (e) {
                        formattedDob = item.dob;
                    }
                }

                return [
                    item.payroll_id || '',
                    (item.first_name || '') + ' ' + (item.surname || ''),
                    item.email || '',
                    formattedDob,
                    item.id_no || '',
                    formatMoney(item.employee_amount || 0),
                    formatMoney(item.employer_amount || 0),
                    formatMoney(item.voluntary_amount || 0),
                    formatMoney(item.total_amounts || 0)
                ];
            });

            $('#stagingDataTable').DataTable({
                data: tableData,
                pageLength: 25,
                responsive: true,
                order: [
                    [0, 'asc']
                ],
                columnDefs: [{
                    targets: [5, 6, 7, 8],
                    className: 'text-end'
                }]
            });
        }

        function formatMoney(amount) {
            return parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Process to main tables
        $(document).on('click', '#processToMainBtn', function() {
            var summaryId = window.currentSummaryId;

            if (!summaryId) {
                toastr.error('No staging data available to process');
                return;
            }

            // Show the confirmation modal
            $('#confirmProcessModal').modal('show');
        });

        // Handle modal confirmation
        $(document).on('click', '#confirmProcessBtn', function() {
            var $btn = $('#processToMainBtn');
            var summaryId = window.currentSummaryId;

            // Hide the modal
            $('#confirmProcessModal').modal('hide');

            $btn.prop('disabled', true);
            $('#processSpinner').removeClass('d-none');
            $btn.find('.material-symbols-outlined, .ms-1').addClass('d-none');

            $.ajax({
                url: '/uploads/scheme/contributions/process-staging',
                method: 'POST',
                data: {
                    summary_id: summaryId
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            }).done(function(response) {
                if (response.success) {
                    // Show success alert on upload tab
                    var successAlert = '<div class="alert alert-light-success alert-dismissible fade show" role="alert">' +
                        '<p class="txt-success mb-0">Successfully processed ' + (response.processed || 0) + ' records to main tables</p>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>';

                    toastr.success('Successfully processed ' + (response.processed || 0) + ' records to main tables');

                    $('#preview-tab').hide();
                    $('#upload-tab').tab('show');

                    // Add success alert to upload tab
                    $('#upload .alert').remove(); // Remove any existing alerts
                    $('#upload').prepend(successAlert);

                    // Reset form
                    $('#schemeUploadForm')[0].reset();
                    resetFunds();
                    window.currentSummaryId = null;
                    // Clear any previous errors
                    updateErrorsTab([]);
                } else {
                    toastr.error(response.message || 'Failed to process staging data');
                    // Show errors in errors tab if any
                    if (response.errors && response.errors.length > 0) {
                        updateErrorsTab(response.errors);
                        $('#errors-tab').tab('show');
                    }
                }
            }).fail(function(xhr) {
                console.error('Failed to process staging data', xhr);
                var errorMessage = 'Failed to process staging data';
                var errors = [];

                // Try to extract errors from response
                try {
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        if (xhr.responseJSON.errors) {
                            errors = Array.isArray(xhr.responseJSON.errors) ? xhr.responseJSON.errors : [xhr.responseJSON.errors];
                        }
                    }
                } catch (e) {
                    errors = [errorMessage];
                }

                toastr.error(errorMessage);
                if (errors.length > 0) {
                    updateErrorsTab(errors);
                    $('#errors-tab').tab('show');
                }
            }).always(function() {
                var $btn = $('#processToMainBtn');
                $btn.prop('disabled', false);
                $('#processSpinner').addClass('d-none');
                $btn.find('.material-symbols-outlined, .ms-1').removeClass('d-none');
            });
        });

        // Delete staging data
        $(document).on('click', '#deleteStagingBtn', function() {
            var $btn = $(this);
            var summaryId = window.currentSummaryId;

            if (!summaryId) {
                toastr.error('No staging data available to delete');
                return;
            }

            $btn.prop('disabled', true);
            $('#deleteSpinner').removeClass('d-none');
            $btn.find('.material-symbols-outlined, .ms-1').addClass('d-none');

            $.ajax({
                url: '/uploads/scheme/contributions/delete-staging',
                method: 'DELETE',
                data: {
                    summary_id: summaryId
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            }).done(function(response) {
                if (response.success) {
                    // Show success alert on upload tab
                    var successAlert = '<div class="alert alert-light-success alert-dismissible fade show" role="alert">' +
                        '<p class="txt-success mb-0">Uploads Data deleted successfully</p>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>';

                    toastr.success('Uploads Data deleted successfully');
                    $('#preview-tab').hide();
                    $('#upload-tab').tab('show');

                    // Add success alert to upload tab
                    $('#upload .alert').remove(); // Remove any existing alerts
                    $('#upload').prepend(successAlert);

                    window.currentSummaryId = null;
                } else {
                    toastr.error(response.message || 'Failed to delete staging data');
                }
            }).fail(function(xhr) {
                console.error('Failed to delete staging data', xhr);
                toastr.error('Failed to delete staging data');
            }).always(function() {
                $btn.prop('disabled', false);
                $('#deleteSpinner').addClass('d-none');
                $btn.find('.material-symbols-outlined, .ms-1').removeClass('d-none');
            });
        });
    });
</script>
@endsection
<style>
    /* small visual flag for errors tab */
    #errors-tab.border-danger {
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.15);
    }

    /* Extra small button style */
    .btn-xs {
        padding: 0.15rem 0.4rem;
        font-size: 0.75rem;
        line-height: 1.2;
        border-radius: 0.2rem;
    }
</style>
@endsection
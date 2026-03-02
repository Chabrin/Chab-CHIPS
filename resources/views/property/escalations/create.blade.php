@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">New Lease Escalation</h5>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="submitBtn" disabled>
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Submit for Approval</span>
                            </button>
                            <a href="{{ route('property.escalations.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="escalationForm" enctype="multipart/form-data">
                            @csrf

                            <!-- ─── Step 1 & 2: Landlord & Property ─── -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="badge bg-primary me-2">1</span> Select Landlord
                                    </h6>
                                    <label for="client_search" class="form-label">Search Landlord <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client_search" name="client_id" required>
                                        <option value="">Search by name or reference...</option>
                                    </select>
                                    <small class="text-muted">Type at least 2 characters to search</small>
                                </div>
                                <div class="col-md-6 mb-3" id="propertySection" style="display:none;">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="badge bg-primary me-2">2</span> Select Property
                                    </h6>
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select" id="property_id" name="property_id" required>
                                        <option value="">Select a property...</option>
                                    </select>
                                </div>
                            </div>

                            <!-- ─── Step 3: Scope & Unit Selection ─── -->
                            <div class="row mb-4" id="scopeSection" style="display:none;">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="badge bg-primary me-2">3</span> Escalation Scope
                                    </h6>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Scope <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="scope" id="scopeProperty" value="property" checked>
                                            <label class="form-check-label" for="scopeProperty">
                                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:text-bottom;">apartment</span>
                                                Entire Property (all units)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="scope" id="scopeUnit" value="unit">
                                            <label class="form-check-label" for="scopeUnit">
                                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:text-bottom;">door_front</span>
                                                Specific Unit
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3" id="unitSelectSection" style="display:none;">
                                    <label for="unit_id" class="form-label">Units <span class="text-danger">*</span></label>
                                    <select class="form-select" id="unit_id" name="unit_ids[]" multiple>
                                    </select>
                                </div>
                            </div>

                            <!-- ─── Step 4: Escalation Details ─── -->
                            <div class="row mb-4" id="escalationDetailsSection" style="display:none;">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="badge bg-primary me-2">4</span> Escalation Details
                                    </h6>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="escalation_type" class="form-label">Escalation Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="escalation_type" name="escalation_type" required>
                                        <option value="percentage" selected>Percentage (%)</option>
                                        <option value="flat_rate">Flat Rate (KES)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="escalation_value" class="form-label">
                                        <span id="valueLabel">Escalation Rate (%)</span> <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="escalation_value" name="escalation_value" 
                                           placeholder="e.g. 7.5" required min="0.01">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="effective_date" class="form-label">Effective Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                                </div>
                            </div>

                            <!-- ─── Units Preview Table ─── -->
                            <div class="row mb-4" id="unitsPreviewSection" style="display:none;">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;vertical-align:text-bottom;">preview</span>
                                        Units &amp; Current Lease Amounts
                                    </h6>
                                </div>
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered" id="unitsPreviewTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Unit Code</th>
                                                    <th>Unit Name</th>
                                                    <th>Tenant</th>
                                                    <th>Usage Type</th>
                                                    <th>Current Rent</th>
                                                    <th>Lease Ref</th>
                                                    <th>New Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot>
                                                <tr class="table-secondary fw-bold">
                                                    <td colspan="4">Totals</td>
                                                    <td id="totalCurrentRent">0.00</td>
                                                    <td></td>
                                                    <td id="totalNewRent">0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- ─── Step 5: Supporting Documents & Notes ─── -->
                            <div class="row mb-4" id="documentsSection" style="display:none;">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="badge bg-primary me-2">5</span> Supporting Documents &amp; Notes
                                    </h6>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="documents" class="form-label">Upload Documents</label>
                                    <input type="file" class="form-control" id="documents" name="documents[]" multiple 
                                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <small class="text-muted">PDF, DOC, DOCX, JPG, PNG — max 10MB each</small>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Notes / Comments</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Add any relevant details about this escalation..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function () {
    let allUnits = [];   // cached units for the selected property
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // ── Select2: Landlord Search ──
    $('#client_search').select2({
        placeholder: 'Search by name or reference...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("property.escalations.search-landlords") }}',
            dataType: 'json',
            delay: 300,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data.results }),
            cache: true
        }
    });

    // ── Select2: Property ──
    $('#property_id').select2({
        placeholder: 'Select a property...',
        allowClear: true
    });

    // ── Select2: Unit (multi-select) ──
    $('#unit_id').select2({
        placeholder: 'Select units...',
        allowClear: true,
        closeOnSelect: false
    });

    // ── Select2: Escalation Type ──
    // $('#escalation_type').select2({
    //     minimumResultsForSearch: Infinity
    // });

    // ── Landlord selected → load properties ──
    $('#client_search').on('change', function () {
        const clientId = $(this).val();
        resetFrom('property');

        if (!clientId) return;

        $.ajax({
            url: '{{ route("property.escalations.landlord-properties") }}',
            data: { client_id: clientId },
            success: function (res) {
                const $prop = $('#property_id');
                $prop.html('<option value="">Select a property...</option>');
                (res.data || []).forEach(p => {
                    $prop.append(`<option value="${p.id}" data-usage="${p.usage_type_id}">${p.property_name} (${p.reference_number || ''})</option>`);
                });
                $('#propertySection').show();
            },
            error: () => toastr.error('Failed to load properties')
        });
    });

    // ── Property selected → load units ──
    $('#property_id').on('change', function () {
        const propertyId = $(this).val();
        resetFrom('scope');

        if (!propertyId) return;

        $.ajax({
            url: '{{ route("property.escalations.property-units") }}',
            data: { property_id: propertyId },
            success: function (res) {
                allUnits = res.data || [];

                // Populate the unit dropdown
                const $unit = $('#unit_id');
                $unit.html('<option value="">Select a unit...</option>');
                allUnits.forEach(u => {
                    $unit.append(`<option value="${u.id}">${u.unit_code} — ${u.unit_name || ''} (Rent: ${parseFloat(u.rent_amount).toLocaleString()})</option>`);
                });

                $('#scopeSection').slideDown(200);
                $('#escalationDetailsSection').slideDown(200);
                $('#documentsSection').slideDown(200);

                // Default = property scope, show preview
                refreshPreview();
            },
            error: () => toastr.error('Failed to load units')
        });
    });

    // ── Scope toggle ──
    $('input[name="scope"]').on('change', function () {
        const scope = $(this).val();
        if (scope === 'unit') {
            $('#unitSelectSection').slideDown(200);
        } else {
            $('#unitSelectSection').slideUp(200);
            $('#unit_id').val(null).trigger('change');
        }
        refreshPreview();
    });

    // ── Unit selected (when unit scope) ──
    $('#unit_id').on('change', function () {
        refreshPreview();
    });

    // ── Escalation type & value change → recalculate preview ──
    $('#escalation_type').on('change', function () {
        const type = $(this).val();
        if (type === 'percentage') {
            $('#valueLabel').text('Escalation Rate (%)');
            $('#escalation_value').attr('placeholder', 'e.g. 7.5');
        } else {
            $('#valueLabel').text('Flat Amount (KES)');
            $('#escalation_value').attr('placeholder', 'e.g. 5000');
        }
        refreshPreview();
    });

    $('#escalation_value').on('input', function () {
        refreshPreview();
    });

    // ── Refresh the units preview table ──
    function refreshPreview() {
        const scope    = $('input[name="scope"]:checked').val();
        const unitIds  = ($('#unit_id').val() || []).map(Number);
        const type     = $('#escalation_type').val();
        const value    = parseFloat($('#escalation_value').val()) || 0;

        let displayUnits = [];
        if (scope === 'unit' && unitIds.length > 0) {
            displayUnits = allUnits.filter(u => unitIds.includes(u.id));
        } else if (scope === 'property') {
            displayUnits = allUnits;
        }

        const $tbody = $('#unitsPreviewTable tbody');
        $tbody.empty();

        let totalCurrent = 0;
        let totalNew = 0;

        if (displayUnits.length === 0) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">No units selected</td></tr>');
        } else {
            displayUnits.forEach(u => {
                const current = parseFloat(u.rent_amount) || 0;
                let newAmt = 0;
                if (type === 'percentage' && value > 0) {
                    newAmt = current * (1 + value / 100);
                } else if (type === 'flat_rate' && value > 0) {
                    newAmt = current + value;
                } else {
                    newAmt = current;
                }
                newAmt = Math.round(newAmt * 100) / 100;

                totalCurrent += current;
                totalNew += newAmt;

                const usageLabel = u.usage_type_id == 1 ? 'Residential' : (u.usage_type_id == 2 ? 'Commercial' : 'Other');

                $tbody.append(`<tr>
                    <td>${u.unit_code}</td>
                    <td>${u.unit_name || ''}</td>
                    <td>${u.tenant_name || '<span class="text-muted">Vacant</span>'}</td>
                    <td>${usageLabel}</td>
                    <td class="text-end">${current.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                    <td>${u.lease_ref || '<span class="text-muted">No Lease</span>'}</td>
                    <td class="text-end fw-bold ${newAmt > current ? 'text-success' : ''}">${newAmt.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                </tr>`);
            });
        }

        $('#totalCurrentRent').text(totalCurrent.toLocaleString(undefined, {minimumFractionDigits:2}));
        $('#totalNewRent').text(totalNew.toLocaleString(undefined, {minimumFractionDigits:2}));

        // Show preview section
        if (displayUnits.length > 0) {
            $('#unitsPreviewSection').slideDown(200);
        } else {
            $('#unitsPreviewSection').slideUp(200);
        }

        // Enable/disable submit
        validateForm();
    }

    // ── Form validation check ──
    function validateForm() {
        const clientId  = $('#client_search').val();
        const propId    = $('#property_id').val();
        const scope     = $('input[name="scope"]:checked').val();
        const unitIds   = $('#unit_id').val() || [];
        const type      = $('#escalation_type').val();
        const value     = parseFloat($('#escalation_value').val());
        const date      = $('#effective_date').val();

        let valid = clientId && propId && type && value > 0 && date;
        if (scope === 'unit' && unitIds.length === 0) valid = false;

        $('#submitBtn').prop('disabled', !valid);
    }

    // Run validation on key fields
    $('#effective_date').on('change', validateForm);
    $('#escalation_value').on('input', validateForm);

    // ── Submit escalation ──
    $('#submitBtn').on('click', function () {
        const formData = new FormData(document.getElementById('escalationForm'));

        $.ajax({
            url: '{{ route("property.escalations.store") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': csrfToken },
            beforeSend: function () {
                $('#submitBtn').prop('disabled', true);
                $('#submitSpinner').removeClass('d-none');
            },
            success: function (res) {
                toastr.success(res.message || 'Escalation submitted successfully.');
                setTimeout(() => {
                    window.location.href = '{{ route("property.escalations.index") }}';
                }, 1200);
            },
            error: function (xhr) {
                const errors = xhr.responseJSON?.errors;
                const msg    = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Submission failed.';
                if (errors) {
                    let errorHtml = '<ul>';
                    Object.values(errors).forEach(msgs => {
                        msgs.forEach(m => errorHtml += `<li>${m}</li>`);
                    });
                    errorHtml += '</ul>';
                    toastr.error(errorHtml, 'Validation Error', { timeOut: 8000, closeButton: true });
                } else {
                    toastr.error(msg);
                }
            },
            complete: function () {
                $('#submitBtn').prop('disabled', false);
                $('#submitSpinner').addClass('d-none');
                validateForm();
            }
        });
    });

    // ── Reset sections from a given point ──
    function resetFrom(section) {
        if (['property', 'scope', 'details'].includes(section)) {
            if (section === 'property') {
                $('#property_id').html('<option value="">Select a property...</option>');
                $('#propertySection').hide();
            }
        }
        if (['property', 'scope'].includes(section)) {
            allUnits = [];
            $('#unit_id').val(null).trigger('change').html('');
            $('#scopeSection').slideUp(200);
            $('#unitSelectSection').slideUp(200);
            $('#unitsPreviewSection').slideUp(200);
            $('#escalationDetailsSection').slideUp(200);
            $('#documentsSection').slideUp(200);
            $('input[name="scope"][value="property"]').prop('checked', true);
        }
        $('#submitBtn').prop('disabled', true);
    }
});
</script>
@endsection

@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Merge Properties / Units</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="previewBtn" disabled>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">preview</span>
                                <span>Preview Merge</span>
                            </button>
                            <button type="button" class="btn btn-success btn-sm d-flex align-items-center" id="saveMergeBtn" disabled>
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Execute Merge</span>
                            </button>
                            <a href="{{ route('property.merge.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Alert container -->
                        <div id="alertContainer"></div>

                        <!-- Step 1: Select Client & Merge Type -->
                        <div class="card mb-3 border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <span class="badge bg-primary me-2">1</span>
                                    Select Client & Merge Type
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="client_search" class="form-label">Client <span class="text-danger">*</span></label>
                                        <select class="form-select" id="client_search" name="client_id" required>
                                            <option value="">Search for a client/landlord...</option>
                                        </select>
                                        <small class="text-muted">Type at least 2 characters to search</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="merge_type" class="form-label">Merge Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="merge_type" disabled>
                                            <option value="">Select merge type</option>
                                            <option value="property">Merge Entire Properties</option>
                                            <option value="unit">Merge Individual Units</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="merged_property_name" class="form-label">Merged Property Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="merged_property_name" placeholder="Enter name for merged property" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Select Target Property -->
                        <div class="card mb-3 border" id="step2Card" style="display:none;">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <span class="badge bg-primary me-2">2</span>
                                    Select Target Property
                                    <small class="text-muted ms-2">(The property that will absorb the others)</small>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="target_property_id" class="form-label">Target Property <span class="text-danger">*</span></label>
                                        <select class="form-select" id="target_property_id" disabled>
                                            <option value="">Select client first</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div id="targetPropertyInfo" class="p-3 bg-light rounded" style="display:none;">
                                            <div class="d-flex align-items-center">
                                                <span class="material-symbols-outlined me-2" style="font-size:24px; color: #198754;">home</span>
                                                <div>
                                                    <strong id="targetPropertyName">-</strong>
                                                    <br><small class="text-muted">Ref: <span id="targetPropertyRef">-</span> | Units: <span id="targetPropertyUnits">-</span></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Select Source Properties or Units -->
                        <div class="card mb-3 border" id="step3Card" style="display:none;">
                            <div class="card-header bg-light d-flex align-items-center">
                                <h6 class="mb-0">
                                    <span class="badge bg-primary me-2">3</span>
                                    <span id="step3Title">Select Properties to Merge</span>
                                </h6>
                                <span class="ms-auto badge bg-secondary" id="selectedCount">0 selected</span>
                            </div>
                            <div class="card-body">
                                <!-- For Property Merge: show other properties -->
                                <div id="propertyMergeSection" style="display:none;">
                                    <div id="propertiesLoader" class="text-center py-3" style="display:none;">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <small class="text-muted ms-2">Loading properties...</small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover" id="sourcePropertiesTable">
                                            <thead>
                                                <tr>
                                                    <th style="width:40px;">
                                                        <input type="checkbox" class="form-check-input" id="selectAllProperties">
                                                    </th>
                                                    <th>Property Name</th>
                                                    <th>Reference</th>
                                                    <th>Description</th>
                                                    <th>Units</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <div id="noPropertiesMsg" class="text-center py-3 text-muted" style="display:none;">
                                        <span class="material-symbols-outlined" style="font-size:48px;">info</span>
                                        <p>No other properties available for this client.</p>
                                    </div>
                                </div>

                                <!-- For Unit Merge: unified unit selection -->
                                <div id="unitMergeSection" style="display:none;">

                                    <div class="alert alert-light-info mb-3">
                                        <span class="material-symbols-outlined align-middle me-1" style="font-size:18px;">info</span>
                                        Select units from any property (including the target). Units already in the target property are highlighted in
                                        <span class="badge bg-success">green</span>. You can merge units within a single property or across multiple properties.
                                    </div>

                                    <div class="mb-3">
                                        <label for="sourcePropertyFilter" class="form-label">Filter by Property</label>
                                        <select class="form-select form-select-sm" id="sourcePropertyFilter" style="max-width: 400px;">
                                            <option value="">All Properties</option>
                                        </select>
                                    </div>
                                    <div id="unitsLoader" class="text-center py-3" style="display:none;">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <small class="text-muted ms-2">Loading units...</small>
                                    </div>
                                    <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                                        <table class="table table-sm table-hover" id="sourceUnitsTable">
                                            <thead class="sticky-top bg-white">
                                                <tr>
                                                    <th style="width:40px;">
                                                        <input type="checkbox" class="form-check-input" id="selectAllUnits">
                                                    </th>
                                                    <th>Unit Name</th>
                                                    <th>Unit Code</th>
                                                    <th>Property</th>
                                                    <th>Rent Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <div id="noUnitsMsg" class="text-center py-3 text-muted" style="display:none;">
                                        <span class="material-symbols-outlined" style="font-size:48px;">info</span>
                                        <p>No units available for this client.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Select Bank Account -->
                        <div class="card mb-3 border" id="step4Card" style="display:none;">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <span class="badge bg-primary me-2">4</span>
                                    Select Bank Account
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="bank_account_id" class="form-label">Bank Account <span class="text-danger">*</span></label>
                                        <select class="form-select" id="bank_account_id" disabled>
                                            <option value="">Select client first</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div id="bankAccountInfo" class="p-3 bg-light rounded" style="display:none;">
                                            <div class="d-flex align-items-center">
                                                <span class="material-symbols-outlined me-2" style="font-size:24px; color: #0d6efd;">account_balance</span>
                                                <div>
                                                    <strong id="bankName">-</strong>
                                                    <br><small class="text-muted">Account: <span id="accountNumber">-</span> | Name: <span id="accountName">-</span></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" rows="2" placeholder="Optional notes about this merge..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Section -->
                        <div class="card mb-3 border border-primary" id="previewCard" style="display:none;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <span class="material-symbols-outlined me-2" style="vertical-align:middle;">preview</span>
                                    Merge Preview
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="previewContent"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Merge Modal -->
<div class="modal fade" id="confirmMergeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined me-2" style="vertical-align:middle;">warning</span>
                    Confirm Merge
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>You are about to merge the selected items.</strong></p>
                <p class="text-muted">This action will move all selected properties/units into the target property and update the bank account. This action can be reversed later if needed.</p>
                <div id="confirmMergeSummary" class="p-3 bg-light rounded mb-3"></div>
                <p class="text-danger mb-0"><strong>Do you want to proceed?</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmMergeBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="confirmSpinner"></span>
                    Yes, Execute Merge
                </button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    let clientProperties = [];
    let clientBankAccounts = [];
    let allUnitsData = {}; // { propertyId: [units] }
    let selectedSourceItems = [];

    // Initialize Select2 for client search
    $('#client_search').select2({
        placeholder: 'Search for a client/landlord...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 2,
        ajax: {
            url: "{{ route('ajax.clients.list') }}",
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    client_type: 'Landlord',
                    search: params.term
                };
            },
            processResults: function(response) {
                return {
                    results: response.clients.map(function(client) {
                        return {
                            id: client.id,
                            text: `${client.names} (${client.reference_number})`
                        };
                    })
                };
            },
            cache: true
        }
    });

    // When client is selected
    $('#client_search').on('change', function() {
        var clientId = $(this).val();
        resetForm();

        if (clientId) {
            $('#merge_type').prop('disabled', false);
            loadClientProperties(clientId);
            loadClientBankAccounts(clientId);
        }
    });

    // When merge type changes
    $('#merge_type').on('change', function() {
        var mergeType = $(this).val();
        $('#merged_property_name').prop('disabled', !mergeType);

        if (mergeType && clientProperties.length > 0) {
            showStep2();
        } else {
            $('#step2Card').hide();
            $('#step3Card').hide();
        }
    });

    // When target property is selected
    $('#target_property_id').on('change', function() {
        var targetId = $(this).val();
        if (targetId) {
            var property = clientProperties.find(p => p.id == targetId);
            if (property) {
                $('#targetPropertyInfo').show();
                $('#targetPropertyName').text(property.property_name);
                $('#targetPropertyRef').text(property.reference_number || 'N/A');
                $('#targetPropertyUnits').text(property.units_count);

                // Auto-fill merged property name if empty
                if (!$('#merged_property_name').val()) {
                    $('#merged_property_name').val(property.property_name);
                }
            }
            showStep3(targetId);
        } else {
            $('#targetPropertyInfo').hide();
            $('#step3Card').hide();
        }
    });

    // Bank account selection
    $('#bank_account_id').on('change', function() {
        var accountId = $(this).val();
        if (accountId) {
            var account = clientBankAccounts.find(a => a.id == accountId);
            if (account) {
                $('#bankAccountInfo').show();
                $('#bankName').text(account.bank_name || 'N/A');
                $('#accountNumber').text(account.account_number || 'N/A');
                $('#accountName').text(account.account_name || 'N/A');
            }
            updateButtons();
        } else {
            $('#bankAccountInfo').hide();
            updateButtons();
        }
    });

    // Select all properties
    $('#selectAllProperties').on('change', function() {
        var checked = $(this).is(':checked');
        $('#sourcePropertiesTable tbody .property-checkbox').prop('checked', checked);
        updateSelectedItems();
    });

    // Select all units
    $('#selectAllUnits').on('change', function() {
        var checked = $(this).is(':checked');
        $('#sourceUnitsTable tbody .unit-checkbox:visible').prop('checked', checked);
        updateSelectedItems();
    });

    // Individual checkbox change
    $(document).on('change', '.property-checkbox, .unit-checkbox', function() {
        updateSelectedItems();
    });

    // Source property filter for unit merge
    $('#sourcePropertyFilter').on('change', function() {
        var propertyId = $(this).val();
        if (propertyId) {
            $('#sourceUnitsTable tbody tr').hide();
            $('#sourceUnitsTable tbody tr[data-property-id="' + propertyId + '"]').show();
        } else {
            $('#sourceUnitsTable tbody tr').show();
        }
        // Uncheck hidden rows
        $('#sourceUnitsTable tbody tr:hidden .unit-checkbox').prop('checked', false);
        updateSelectedItems();
    });

    // Preview button
    $('#previewBtn').on('click', function() {
        generatePreview();
    });

    // Save merge button (opens confirmation modal)
    $('#saveMergeBtn').on('click', function() {
        var summary = `
            <p><strong>Merge Type:</strong> ${$('#merge_type').val() === 'property' ? 'Entire Properties' : 'Individual Units'}</p>
            <p><strong>Target Property:</strong> ${$('#target_property_id option:selected').text()}</p>
            <p><strong>Merged Name:</strong> ${$('#merged_property_name').val()}</p>
            <p><strong>Items to Merge:</strong> ${selectedSourceItems.length}</p>
            <p><strong>Bank Account:</strong> ${$('#bank_account_id option:selected').text()}</p>
        `;
        $('#confirmMergeSummary').html(summary);
        $('#confirmMergeModal').modal('show');
    });

    // Confirm merge execution
    $('#confirmMergeBtn').on('click', function() {
        executeMerge();
    });

    // ============= FUNCTIONS =============

    function resetForm() {
        clientProperties = [];
        allUnitsData = {};
        selectedSourceItems = [];

        $('#merge_type').prop('disabled', true).val('');
        $('#merged_property_name').prop('disabled', true).val('');
        $('#target_property_id').prop('disabled', true).html('<option value="">Select client first</option>');
        $('#bank_account_id').prop('disabled', true).html('<option value="">Select client first</option>');
        $('#targetPropertyInfo').hide();
        $('#bankAccountInfo').hide();
        $('#step2Card').hide();
        $('#step3Card').hide();
        $('#step4Card').hide();
        $('#previewCard').hide();
        $('#selectedCount').text('0 selected');
        updateButtons();
    }

    function loadClientProperties(clientId) {
        $.ajax({
            url: `/ajax/property/merge/client/${clientId}/properties`,
            method: 'GET',
            success: function(response) {
                clientProperties = response.data || [];
            },
            error: function(xhr) {
                showAlert('danger', 'Failed to load client properties.');
            }
        });
    }

    function loadClientBankAccounts(clientId) {
        $.ajax({
            url: `/ajax/property/merge/client/${clientId}/bank-accounts`,
            method: 'GET',
            success: function(response) {
                clientBankAccounts = response.data || [];
                var select = $('#bank_account_id');
                select.html('<option value="">Select bank account</option>');
                clientBankAccounts.forEach(function(account) {
                    select.append(`<option value="${account.id}">${account.bank_name} - ${account.account_number} (${account.account_name})</option>`);
                });
                select.prop('disabled', false);
            },
            error: function(xhr) {
                showAlert('danger', 'Failed to load bank accounts.');
            }
        });
    }

    function showStep2() {
        var targetSelect = $('#target_property_id');
        targetSelect.html('<option value="">Select target property</option>');
        clientProperties.forEach(function(property) {
            targetSelect.append(`<option value="${property.id}">${property.property_name} (${property.reference_number || 'N/A'}) - ${property.units_count} units</option>`);
        });
        targetSelect.prop('disabled', false);
        $('#step2Card').show();
        $('#step3Card').hide();
        $('#step4Card').hide();
        $('#previewCard').hide();
    }

    function showStep3(targetId) {
        var mergeType = $('#merge_type').val();
        selectedSourceItems = [];
        $('#selectedCount').text('0 selected');

        if (mergeType === 'property') {
            $('#step3Title').text('Select Properties to Merge');
            $('#propertyMergeSection').show();
            $('#unitMergeSection').hide();
            populateSourceProperties(targetId);
        } else {
            $('#step3Title').text('Select Units to Merge');
            $('#propertyMergeSection').hide();
            $('#unitMergeSection').show();
            populateSourceUnits(targetId);
        }

        $('#step3Card').show();
        $('#step4Card').show();
    }

    function populateSourceProperties(targetId) {
        var tbody = $('#sourcePropertiesTable tbody');
        tbody.empty();
        $('#selectAllProperties').prop('checked', false);

        var sourceProperties = clientProperties.filter(p => p.id != targetId);

        if (sourceProperties.length === 0) {
            $('#noPropertiesMsg').show();
            $('#sourcePropertiesTable').hide();
            return;
        }

        $('#noPropertiesMsg').hide();
        $('#sourcePropertiesTable').show();

        sourceProperties.forEach(function(property) {
            tbody.append(`
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input property-checkbox" value="${property.id}" data-name="${property.property_name}">
                    </td>
                    <td>${property.property_name}</td>
                    <td>${property.reference_number || 'N/A'}</td>
                    <td>${property.description || '-'}</td>
                    <td><span class="badge bg-info">${property.units_count}</span></td>
                </tr>
            `);
        });
    }

    function populateSourceUnits(targetId) {
        var tbody = $('#sourceUnitsTable tbody');
        tbody.empty();
        $('#selectAllUnits').prop('checked', false);

        // Include ALL client properties (including target)
        var allProperties = clientProperties;
        var targetProperty = clientProperties.find(p => p.id == targetId);

        // Build filter dropdown with ALL properties (target first, highlighted)
        var filterSelect = $('#sourcePropertyFilter');
        filterSelect.html('<option value="">All Properties</option>');
        allProperties.forEach(function(p) {
            var label = p.property_name;
            if (p.id == targetId) label += ' ★ (Target)';
            filterSelect.append(`<option value="${p.id}">${label}</option>`);
        });

        $('#unitsLoader').show();
        $('#sourceUnitsTable').hide();

        // Load units for ALL properties in parallel
        var allRequests = [];

        allProperties.forEach(function(property) {
            allRequests.push(
                $.ajax({
                    url: `/ajax/property/merge/property/${property.id}/units`,
                    method: 'GET'
                }).then(function(response) {
                    return { propertyId: property.id, propertyName: property.property_name, units: response.data || [], isTarget: property.id == targetId };
                })
            );
        });

        $.when.apply($, allRequests).done(function() {
            var results = allRequests.length === 1 ? [arguments] : Array.from(arguments);
            var totalUnits = 0;

            results.forEach(function(result) {
                var data = Array.isArray(result) ? result[0] : result;
                if (data && data.units) {
                    allUnitsData[data.propertyId] = data.units;
                    data.units.forEach(function(unit) {
                        var isTarget = data.isTarget;
                        var rowStyle = isTarget ? 'background-color: #d1e7dd;' : '';
                        var propertyBadge = isTarget
                            ? `<span class="badge bg-success">${data.propertyName} ★</span>`
                            : data.propertyName;

                        tbody.append(`
                            <tr data-property-id="${data.propertyId}" style="${rowStyle}">
                                <td>
                                    <input type="checkbox" class="form-check-input unit-checkbox" value="${unit.id}" data-name="${unit.unit_name || unit.unit_code}" data-property-id="${data.propertyId}">
                                </td>
                                <td>${unit.unit_name || '-'}</td>
                                <td>${unit.unit_code || unit.unit_number || '-'}</td>
                                <td>${propertyBadge}</td>
                                <td>${unit.rent_amount ? parseFloat(unit.rent_amount).toLocaleString('en-US', {minimumFractionDigits: 2}) : '-'}</td>
                            </tr>
                        `);
                        totalUnits++;
                    });
                }
            });

            $('#unitsLoader').hide();
            if (totalUnits > 0) {
                $('#sourceUnitsTable').show();
                $('#noUnitsMsg').hide();
            } else {
                $('#noUnitsMsg').show();
            }
        }).fail(function() {
            $('#unitsLoader').hide();
            showAlert('danger', 'Failed to load units.');
        });
    }

    function updateSelectedItems() {
        var mergeType = $('#merge_type').val();
        selectedSourceItems = [];

        if (mergeType === 'property') {
            $('.property-checkbox:checked').each(function() {
                selectedSourceItems.push($(this).val());
            });
        } else {
            $('.unit-checkbox:visible:checked').each(function() {
                selectedSourceItems.push($(this).val());
            });
        }

        $('#selectedCount').text(selectedSourceItems.length + ' selected');
        updateButtons();
    }

    function updateButtons() {
        var isValid = $('#client_search').val() &&
                      $('#merge_type').val() &&
                      $('#merged_property_name').val() &&
                      $('#target_property_id').val() &&
                      $('#bank_account_id').val() &&
                      selectedSourceItems.length > 0;

        $('#previewBtn').prop('disabled', !isValid);
        $('#saveMergeBtn').prop('disabled', !isValid);
    }

    // Listen for input changes to update buttons
    $('#merged_property_name').on('input', updateButtons);

    function generatePreview() {
        $.ajax({
            url: "{{ route('ajax.property.merge.preview') }}",
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: {
                client_id: $('#client_search').val(),
                merge_type: $('#merge_type').val(),
                target_property_id: $('#target_property_id').val(),
                source_items: selectedSourceItems
            },
            success: function(response) {
                var data = response.data;
                var html = '';

                html += `<div class="row mb-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded text-center">
                            <span class="material-symbols-outlined" style="font-size:32px; color:#198754;">home</span>
                            <h6 class="mt-2">Target Property</h6>
                            <p class="mb-0">${data.target_property.property_name}</p>
                            <small class="text-muted">${data.target_property.units_count} existing units</small>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <span class="material-symbols-outlined" style="font-size:48px; color:#0d6efd;">merge</span>
                            <p class="mb-0"><strong>${data.source_items.length}</strong> items merging in</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded text-center bg-light">
                            <span class="material-symbols-outlined" style="font-size:32px; color:#0d6efd;">apartment</span>
                            <h6 class="mt-2">After Merge</h6>
                            <p class="mb-0 fw-bold">${$('#merged_property_name').val()}</p>
                            <small class="text-success"><strong>${data.total_units_after_merge}</strong> total units</small>
                        </div>
                    </div>
                </div>`;

                // Source items table
                html += '<h6>Items Being Merged:</h6>';
                html += '<table class="table table-sm table-bordered"><thead><tr>';
                if ($('#merge_type').val() === 'property') {
                    html += '<th>Property</th><th>Reference</th><th>Units</th>';
                } else {
                    html += '<th>Unit</th><th>Source Property</th><th>Rent Amount</th>';
                }
                html += '</tr></thead><tbody>';

                data.source_items.forEach(function(item) {
                    html += '<tr>';
                    if ($('#merge_type').val() === 'property') {
                        html += `<td>${item.property_name}</td><td>${item.reference_number || 'N/A'}</td><td>${item.units_count}</td>`;
                    } else {
                        html += `<td>${item.unit_name || item.unit_code}</td><td>${item.source_property_name}</td><td>${item.rent_amount ? parseFloat(item.rent_amount).toLocaleString('en-US', {minimumFractionDigits: 2}) : '-'}</td>`;
                    }
                    html += '</tr>';
                });

                html += '</tbody></table>';

                $('#previewContent').html(html);
                $('#previewCard').show();
                $('html, body').animate({ scrollTop: $('#previewCard').offset().top - 100 }, 500);
            },
            error: function(xhr) {
                showAlert('danger', 'Failed to generate preview.');
            }
        });
    }

    function executeMerge() {
        $('#confirmSpinner').removeClass('d-none');
        $('#confirmMergeBtn').prop('disabled', true);

        $.ajax({
            url: "{{ route('property.merge.store') }}",
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: {
                client_id: $('#client_search').val(),
                merge_type: $('#merge_type').val(),
                merged_property_name: $('#merged_property_name').val(),
                target_property_id: $('#target_property_id').val(),
                bank_account_id: $('#bank_account_id').val(),
                source_items: selectedSourceItems,
                notes: $('#notes').val()
            },
            success: function(response) {
                $('#confirmMergeModal').modal('hide');
                if (response.success) {
                    showAlert('success', `Merge completed successfully! Reference: ${response.merge_reference}`);
                    // Redirect to show page after short delay
                    setTimeout(function() {
                        window.location.href = `/property/merge/${response.encrypted_id}`;
                    }, 1500);
                } else {
                    showAlert('danger', response.message || 'Merge failed.');
                }
            },
            error: function(xhr) {
                $('#confirmMergeModal').modal('hide');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during the merge.';
                showAlert('danger', msg);
            },
            complete: function() {
                $('#confirmSpinner').addClass('d-none');
                $('#confirmMergeBtn').prop('disabled', false);
            }
        });
    }

    function showAlert(type, message) {
        var alertClass = type === 'success' ? 'alert-light-success' : 'alert-light-danger';
        var icon = type === 'success' ? 'check_circle' : 'error';
        var html = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <span class="material-symbols-outlined me-2" style="vertical-align:middle;">${icon}</span>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#alertContainer').html(html);
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
});
</script>
@endsection
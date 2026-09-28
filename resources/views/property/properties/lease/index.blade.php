@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Lease Management</h5>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs mb-4" id="leaseTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="urgent-tab" data-bs-toggle="tab" data-bs-target="#urgent" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">warning</span>
                                    Expiring Leases (Urgent)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">bolt</span>
                                    Actions
                                </button>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content" id="leaseTabsContent">
                            <!-- Expiring Leases (Urgent) Tab -->
                            <div class="tab-pane fade show active" id="urgent" role="tabpanel">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div id="urgentSelectedBar" class="small text-muted"><strong id="urgentSelectedCount">0</strong> selected</div>
                                    <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" id="refreshUrgentBtn">
                                        <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
                                        <span class="ms-1">Refresh</span>
                                    </button>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="urgentLeasesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAllUrgentLeases" class="form-check-input"></th>
                                                <th>#</th>
                                                <th>Tenant Name</th>
                                                <th>Property</th>
                                                <th>Unit Code</th>
                                                <th>End Date</th>
                                                <th>Ending In</th>
                                                <th>Lease Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Actions Tab -->
                            <div class="tab-pane fade" id="actions" role="tabpanel">
                                <div class="d-flex flex-wrap gap-4">
                                    <a href="{{ route('property.leases.setup') }}" class="text-decoration-underline text-primary d-flex align-items-center gap-1" title="Clean up and activate migrated leases">
                                        <span class="material-symbols-outlined" style="font-size:18px;">build</span>
                                        Lease Setup
                                    </a>
                                    <a href="{{ route('property.lease-settings.contract-templates.index') }}" class="text-decoration-underline text-primary d-flex align-items-center gap-1" title="Manage contract templates">
                                        <span class="material-symbols-outlined" style="font-size:18px;">article</span>
                                        Contract Templates
                                    </a>
                                    <a href="javascript:void(0)" class="text-decoration-underline text-primary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#renewalModal">
                                        <span class="material-symbols-outlined" style="font-size:18px;">autorenew</span>
                                        Bulk Renewal
                                    </a>
                                    <a href="{{ route('tenant.lease.create') }}" class="text-decoration-underline text-primary d-flex align-items-center gap-1">
                                        <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                                        New Lease
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Renewal Modal -->
<div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewalModalLabel">Bulk Lease Renewal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="renewalForm">
                <div class="modal-body">
                    <div class="row mb-3">
                        <!-- Client Selection -->
                        <div class="col-md-12 mb-3">
                            <label for="renewal_client" class="form-label">Search Client/Landlord <span class="text-danger">*</span></label>
                            <select class="form-select" id="renewal_client" name="renewal_client" required>
                                <option value="">Search for a client...</option>
                            </select>
                            <small class="text-muted">Search and select a client first to see their properties</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Renewal Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="renewal_type" id="renewal_property" value="property" checked>
                                    <label class="form-check-label" for="renewal_property">
                                        Entire Property
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="renewal_type" id="renewal_units" value="units">
                                    <label class="form-check-label" for="renewal_units">
                                        Select Units
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Property Selection -->
                        <div class="col-md-12 mb-3" id="property_selection">
                            <label for="property_id" class="form-label">Select Property <span class="text-danger">*</span></label>
                            <select class="form-select" id="property_id" name="property_id" required>
                                <option value="">Loading properties...</option>
                            </select>
                            <small class="text-muted">All active leases in this property will be renewed</small>
                        </div>

                        <!-- Units Selection -->
                        <div class="col-md-12 mb-3" id="units_selection" style="display:none;">
                            <label for="selected_property" class="form-label">Select Property First <span class="text-danger">*</span></label>
                            <select class="form-select mb-2" id="selected_property" name="selected_property">
                                <option value="">Choose a property...</option>
                            </select>
                            <label class="form-label mt-2">Select Units to Renew <span class="text-danger">*</span></label>
                            <div id="units_list" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted">Select a property to see available units</p>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Expiration Criteria <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="expiring_within_days" class="form-label">Expiring Within (Days)</label>
                                    <input type="number" class="form-control" id="expiring_within_days" name="expiring_within_days" min="0" max="365" value="90" placeholder="e.g., 90">
                                    <small class="text-muted">Leases expiring in the next X days</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="expired_within_days" class="form-label">Already Expired By (Days)</label>
                                    <input type="number" class="form-control" id="expired_within_days" name="expired_within_days" min="0" max="365" value="0" placeholder="e.g., 30">
                                    <small class="text-muted">Leases expired within the last X days</small>
                                </div>
                            </div>
                            <div class="alert alert-info mt-2 mb-0" role="alert">
                                <strong>Examples:</strong>
                                <ul class="mb-0 mt-1" style="font-size: 0.9rem;">
                                    <li><strong>Expiring: 90, Expired: 30</strong> → Renews leases that expired in the last 30 days OR will expire in the next 90 days</li>
                                    <li><strong>Expiring: 30, Expired: 0</strong> → Only renews leases expiring in the next 30 days (no already-expired leases)</li>
                                    <li><strong>Expiring: 60, Expired: 60</strong> → Renews leases within a 120-day window (60 days past to 60 days future)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="renewal_years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="renewal_years" name="years" min="1" max="10" value="1" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="renewal_escalation" class="form-label">Escalation Rate (%) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="renewal_escalation" name="escalation_rate" min="0" max="100" value="0" required>
                            <small class="text-muted">Applied to all invoiceable items</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="renewBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="renewSpinner"></span>
                        <span id="renewBtnText">Renew Leases</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Lease Renew Modal -->
<div class="modal fade" id="renewLeaseModal" tabindex="-1" aria-labelledby="renewLeaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewLeaseModalLabel">Renew Lease</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="renewLeaseForm">
                <div class="modal-body">
                    <input type="hidden" id="renew_lease_id" name="lease_id">
                    <p class="text-muted mb-3">Renewing lease for <strong id="renew_lease_tenant"></strong></p>
                    <div class="mb-3">
                        <label for="renew_lease_years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="renew_lease_years" name="years" min="1" max="10" value="1" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="renew_lease_escalation" class="form-label">Escalation Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="renew_lease_escalation" name="escalation_rate" min="0" max="100" value="0" required>
                        <small class="text-muted">Applied to all invoiceable items</small>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="renewLeaseSaveBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="renewLeaseSpinner"></span>
                        <span id="renewLeaseBtnText">Renew Lease</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let propertiesData = [];

        // ── Expiring Leases (Urgent) Tab ──────────────────────────────
        let urgentSelected = new Set();

        function updateUrgentSelectedBar() {
            $('#urgentSelectedCount').text(urgentSelected.size);
        }

        function updateUrgentSelectAllHeader() {
            // DataTables fires its first drawCallback during construction, before the
            // `urgentLeasesTable = $(...).DataTable(...)` assignment below has completed.
            if (!urgentLeasesTable) return;
            const total = urgentLeasesTable.rows().count();
            const selected = urgentSelected.size;
            const $selectAll = $('#selectAllUrgentLeases');
            $selectAll.prop('checked', total > 0 && selected === total);
            $selectAll.prop('indeterminate', selected > 0 && selected < total);
        }

        var urgentLeasesTable = $('#urgentLeasesTable').DataTable({
            processing: true,
            ajax: {
                url: "{{ route('property.leases.urgent') }}",
                type: 'GET',
                dataSrc: 'data',
                error: function() {
                    toastr.error('Failed to load expiring leases');
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => `<input type="checkbox" class="form-check-input row-select-urgent-lease" data-id="${row.id}" ${urgentSelected.has(row.id) ? 'checked' : ''}>`
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'tenant_name',
                    render: function(data) {
                        return data || 'N/A';
                    }
                },
                { data: 'property_name' },
                { data: 'unit_code' },
                {
                    data: 'date_end',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'days_to_expiry',
                    render: function(data) {
                        if (data === null || data === undefined) return 'N/A';
                        return data < 0 ? (Math.abs(data) + ' days overdue') : (data + ' days');
                    }
                },
                {
                    data: 'lease_status',
                    render: function(data) {
                        return data || 'Unknown';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let viewBtn = row.encrypted_tenant_id ?
                            `<a href="/tenants/${row.encrypted_tenant_id}" class="text-primary" title="View Tenant">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </a>` : '';
                        let renewBtn = `<a href="javascript:void(0)" class="text-decoration-underline text-primary ms-2 renew-lease-link" data-id="${row.id}" data-tenant="${row.tenant_name || ''}">Renew</a>`;
                        return viewBtn + renewBtn;
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            order: [[5, 'desc']],
            drawCallback: function() {
                updateUrgentSelectAllHeader();
            }
        });

        $('#refreshUrgentBtn').on('click', function() {
            urgentSelected = new Set();
            updateUrgentSelectedBar();
            urgentLeasesTable.ajax.reload();
        });

        $(document).on('change', '.row-select-urgent-lease', function() {
            const id = parseInt($(this).data('id'));
            if ($(this).is(':checked')) {
                urgentSelected.add(id);
            } else {
                urgentSelected.delete(id);
            }
            updateUrgentSelectedBar();
            updateUrgentSelectAllHeader();
        });

        $(document).on('change', '#selectAllUrgentLeases', function() {
            const checked = $(this).is(':checked');
            urgentLeasesTable.rows().every(function() {
                const id = this.data().id;
                if (checked) {
                    urgentSelected.add(id);
                } else {
                    urgentSelected.delete(id);
                }
            });
            urgentLeasesTable.rows().invalidate('data').draw(false);
            updateUrgentSelectedBar();
        });

        // Single-lease Renew action
        $(document).on('click', '.renew-lease-link', function() {
            const leaseId = $(this).data('id');
            const tenantName = $(this).data('tenant');

            $('#renewLeaseForm')[0].reset();
            $('#renewLeaseForm .is-invalid').removeClass('is-invalid');
            $('#renewLeaseForm .invalid-feedback').text('');
            $('#renew_lease_id').val(leaseId);
            $('#renew_lease_tenant').text(tenantName || '');
            $('#renewLeaseModal').modal('show');
        });

        $('#renewLeaseForm').on('submit', function(e) {
            e.preventDefault();

            const leaseId = $('#renew_lease_id').val();
            const $btn = $('#renewLeaseSaveBtn');
            const $spinner = $('#renewLeaseSpinner');
            const $btnText = $('#renewLeaseBtnText');

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $btnText.text('Renewing...');
            $('#renewLeaseForm .is-invalid').removeClass('is-invalid');
            $('#renewLeaseForm .invalid-feedback').text('');

            $.ajax({
                url: '/property/leases/' + leaseId + '/renew',
                method: 'POST',
                data: {
                    years: $('#renew_lease_years').val(),
                    escalation_rate: $('#renew_lease_escalation').val(),
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    toastr.success(response.message);
                    $('#renewLeaseModal').modal('hide');
                    urgentLeasesTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        const fieldMap = {
                            years: '#renew_lease_years',
                            escalation_rate: '#renew_lease_escalation'
                        };
                        $.each(errors, function(field, messages) {
                            const $field = $(fieldMap[field] || ('#renew_lease_' + field));
                            $field.addClass('is-invalid');
                            $field.siblings('.invalid-feedback').text(messages[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Failed to renew lease');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    $btnText.text('Renew Lease');
                }
            });
        });
        // ─────────────────────────────────────────────────────────────

        // Load properties for renewal modal (filtered by client)
        function loadRenewalProperties(clientId = null) {
            if (!clientId) {
                $('#property_id').html('<option value="">Select a client first...</option>');
                $('#selected_property').html('<option value="">Select a client first...</option>');
                return;
            }

            $.ajax({
                url: "{{ route('property.leases.properties-units') }}",
                method: 'GET',
                data: {
                    client_id: clientId
                },
                success: function(response) {
                    // Filter properties by selected client
                    propertiesData = response.properties.filter(function(property) {
                        return property.client_id == clientId;
                    });

                    let propertyOptions = '<option value="">Select a property...</option>';
                    if (propertiesData.length > 0) {
                        propertiesData.forEach(function(property) {
                            propertyOptions += `<option value="${property.id}">${property.property_name} (${property.reference_number})</option>`;
                        });
                    } else {
                        propertyOptions = '<option value="">No properties found for this client</option>';
                    }

                    $('#property_id').html(propertyOptions);
                    $('#selected_property').html(propertyOptions);

                    // Reinitialize select2 on both property dropdowns
                    if ($('#property_id').hasClass('select2-hidden-accessible')) {
                        $('#property_id').select2('destroy');
                    }
                    if ($('#selected_property').hasClass('select2-hidden-accessible')) {
                        $('#selected_property').select2('destroy');
                    }

                    $('#property_id').select2({
                        dropdownParent: $('#renewalModal'),
                        placeholder: 'Select a property...',
                        allowClear: true,
                        width: '100%'
                    });

                    $('#selected_property').select2({
                        dropdownParent: $('#renewalModal'),
                        placeholder: 'Choose a property...',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                    toastr.error('Failed to load properties');
                }
            });
        }

        // Initialize client search in renewal modal
        $('#renewalModal').on('show.bs.modal', function() {
            // Initialize client dropdown with AJAX search
            $('#renewal_client').select2({
                dropdownParent: $('#renewalModal'),
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
                            results: response.data.map(function(client) {
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

            // Initialize property dropdowns with placeholder
            $('#property_id').html('<option value="">Select a client first...</option>');
            $('#selected_property').html('<option value="">Select a client first...</option>');
        });

        // When client changes in renewal modal, reload properties
        $(document).on('change', '#renewal_client', function() {
            const clientId = $(this).val();

            // Clear property selections
            $('#property_id').val('').trigger('change');
            $('#selected_property').val('').trigger('change');
            $('#units_list').html('<p class="text-muted">Select a property to see available units</p>');

            // Load properties for selected client
            if (clientId) {
                loadRenewalProperties(clientId);
            } else {
                $('#property_id').html('<option value="">Select a client first...</option>');
                $('#selected_property').html('<option value="">Select a client first...</option>');
            }
        });

        // Destroy select2 on modal close to prevent memory leaks
        $('#renewalModal').on('hidden.bs.modal', function() {
            if ($('#renewal_client').hasClass('select2-hidden-accessible')) {
                $('#renewal_client').select2('destroy');
            }
            if ($('#property_id').hasClass('select2-hidden-accessible')) {
                $('#property_id').select2('destroy');
            }
            if ($('#selected_property').hasClass('select2-hidden-accessible')) {
                $('#selected_property').select2('destroy');
            }

            // Reset form
            $('#renewal_client').val('').html('<option value="">Search for a client...</option>');
            $('#property_id').html('<option value="">Select a client first...</option>');
            $('#selected_property').html('<option value="">Select a client first...</option>');
            $('#units_list').html('<p class="text-muted">Select a property to see available units</p>');
        });

        // Toggle between property and units selection
        $('input[name="renewal_type"]').on('change', function() {
            if ($(this).val() === 'property') {
                $('#property_selection').show();
                $('#units_selection').hide();
                $('#property_id').prop('required', true);
            } else {
                $('#property_selection').hide();
                $('#units_selection').show();
                $('#property_id').prop('required', false);
            }
        });

        // Load units when property selected
        $('#selected_property').on('change', function() {
            const propertyId = $(this).val();
            const property = propertiesData.find(p => p.id == propertyId);

            if (property && property.units.length > 0) {
                let unitsHtml = '';
                property.units.forEach(function(unit) {
                    unitsHtml += `
                        <div class="form-check mb-2">
                            <input class="form-check-input unit-checkbox" type="checkbox" name="unit_ids[]" value="${unit.id}" id="unit_${unit.id}">
                            <label class="form-check-label" for="unit_${unit.id}">
                                <strong>${unit.unit_code}</strong> - Lease: ${unit.lease_ref} (Expires: ${moment(unit.lease_end_date).format('MMM D, YYYY')})
                            </label>
                        </div>
                    `;
                });

                $('#units_list').html(unitsHtml);
            } else {
                $('#units_list').html('<p class="text-muted">No units with active leases found in this property</p>');
            }
        });

        // Handle renewal form submission
        $('#renewalForm').on('submit', function(e) {
            e.preventDefault();

            const renewalType = $('input[name="renewal_type"]:checked').val();
            const formData = {
                renewal_type: renewalType,
                years: $('#renewal_years').val(),
                escalation_rate: $('#renewal_escalation').val(),
                expiring_within_days: $('#expiring_within_days').val() || 90,
                expired_within_days: $('#expired_within_days').val() || 0,
            };

            if (renewalType === 'property') {
                formData.property_id = $('#property_id').val();
                if (!formData.property_id) {
                    toastr.error('Please select a property');
                    return;
                }
            } else {
                const selectedUnits = $('.unit-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedUnits.length === 0) {
                    toastr.error('Please select at least one unit');
                    return;
                }

                formData.unit_ids = selectedUnits;
            }

            // Show spinner
            $('#renewSpinner').removeClass('d-none');
            $('#renewBtn').prop('disabled', true);
            $('#renewBtnText').text('Processing...');

            $.ajax({
                url: "{{ route('property.leases.bulk-renew') }}",
                method: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#renewalModal').modal('hide');
                    toastr.success(response.message);
                    urgentLeasesTable.ajax.reload();
                },
                error: function(xhr) {
                    const error = xhr.responseJSON;
                    toastr.error(error.message || 'Failed to renew leases');
                    console.error('Renewal error:', xhr);
                },
                complete: function() {
                    $('#renewSpinner').addClass('d-none');
                    $('#renewBtn').prop('disabled', false);
                    $('#renewBtnText').text('Renew Leases');
                }
            });
        });
    });
</script>
@endsection

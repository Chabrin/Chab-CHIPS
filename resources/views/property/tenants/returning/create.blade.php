@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Returning Tenant</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="returningTenantForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton" disabled>
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ route('property.tenants.listing') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-info py-2 px-3 mb-4">
                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">info</span>
                            Reassign a tenant to a (possibly different) unit and create their lease, in one action. Search for any tenant below — not only ones formally exited in this app, since tenant records here can also come from another system and may not reflect what's actually current.
                        </div>

                        <form id="returningTenantForm">
                            @csrf
                            <input type="hidden" id="tenant_id" name="tenant_id" value="">

                            <!-- Step 1: Find Tenant -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">1. Find Tenant</h6>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tenant_select" class="form-label">Search by name, reference, mobile, email or ID number <span class="text-danger">*</span></label>
                                    <select class="form-select" id="tenant_select" style="width:100%;">
                                        <option value="">-- Search for a tenant --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Current Assignment + Previous Tenancies — shown once a tenant is selected -->
                            <div id="tenancyInfoWrapper" class="d-none">
                                @include('property.tenants.partials.history-panels')
                            </div>

                            <!-- Shown only when the selected tenant currently has a unit/lease on record —
                                 must be explicitly acknowledged before a new unit can be assigned. -->
                            <div class="row mb-4 d-none" id="clearAssignmentSection">
                                <div class="col-12">
                                    <div class="alert alert-warning py-2 px-3 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="confirmClearCurrent" name="clear_current_assignment" value="1">
                                            <label class="form-check-label" for="confirmClearCurrent">
                                                This tenant's current unit/lease shown above is stale or incorrect and should be cleared before reassigning them.
                                            </label>
                                        </div>
                                        <div class="mt-2">
                                            <label for="clear_reason" class="form-label mb-1">Reason (optional)</label>
                                            <textarea class="form-control form-control-sm" id="clear_reason" name="clear_reason" rows="2" placeholder="e.g. data migrated from old system, never properly exited"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- The rest of the form is hidden until a tenant is picked (and, if they
                                 have a current assignment, until it's been acknowledged above) -->
                            <div id="restOfForm" class="d-none">

                                <!-- Step 2: New Unit -->
                                <div class="row mb-4">
                                    <div class="col-12 mb-3">
                                        <h6 class="text-muted border-bottom pb-2">2. New Unit</h6>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="return_landlord_id" class="form-label">Landlord (optional)</label>
                                        <select class="form-select" id="return_landlord_id" style="width:100%;">
                                            <option value="">-- Search landlord (optional) --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="return_property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                        <select class="form-select" id="return_property_id" style="width:100%;">
                                            <option value="">-- Search property --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="return_unit_id" class="form-label">Vacant Unit <span class="text-danger">*</span></label>
                                        <select class="form-select" id="return_unit_id" name="unit_id" style="width:100%;" disabled>
                                            <option value="">-- Select a property first --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Step 3: Lease Terms -->
                                <div class="row mb-4">
                                    <div class="col-12 mb-3">
                                        <h6 class="text-muted border-bottom pb-2">3. Lease Terms</h6>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="lease_start_date" class="form-label">Lease Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="lease_start_date" name="lease_start_date" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="lease_years" class="form-label">Lease Years <span class="text-danger">*</span></label>
                                        <select class="form-select" id="lease_years" name="lease_years" required>
                                            @for ($i = 1; $i <= 10; $i++)
                                                <option value="{{ $i }}" {{ $i === 1 ? 'selected' : '' }}>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="auto_renewal" class="form-label">Auto-Renewal <span class="text-danger">*</span></label>
                                        <select class="form-select" id="auto_renewal" name="auto_renewal" required>
                                            <option value="Yes" selected>Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="escalation_rate" class="form-label">Escalation Rate (%) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" id="escalation_rate" name="escalation_rate" value="7.5" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="escalation_frequency" class="form-label">Escalation Frequency <span class="text-danger">*</span></label>
                                        <select class="form-select" id="escalation_frequency" name="escalation_frequency" required>
                                            <option value="Annually">Annually</option>
                                            <option value="Semi-Annually">Semi-Annually</option>
                                            <option value="Quarterly">Quarterly</option>
                                            <option value="Monthly">Monthly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="frequency" class="form-label">Rent Payment Frequency <span class="text-danger">*</span></label>
                                        <select class="form-select" id="frequency" name="frequency" required>
                                            <option value="Annually">Annually</option>
                                            <option value="Monthly">Monthly</option>
                                            <option value="Quarterly">Quarterly</option>
                                            <option value="Biennially">Biennially</option>
                                            <option value="Semi-Annually">Semi-Annually</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="service_charges_amount" class="form-label">Service Charge Amount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="service_charges_amount" name="service_charges_amount">
                                        <small class="form-text text-muted">Auto-filled from the property's SLA when service charges are enabled there — editable, optional.</small>
                                    </div>
                                </div>

                                <!-- Invoicable Items — driven by the selected property's SLA billable items -->
                                <div class="row mb-4">
                                    <div class="col-12 mb-3">
                                        <h6 class="text-muted border-bottom pb-2">Invoicable Items</h6>
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-light-info py-2 px-3 mb-3" id="rentInfoNote" style="display:none;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">info</span>
                                            Rent is billed automatically from the unit's rent amount — not listed here.
                                        </div>
                                        <div class="text-muted small mb-3" id="noBillableItemsNote" style="display:none;">
                                            No additional billable items are configured for this property's SLA.
                                        </div>
                                    </div>
                                    <div id="invoicableItemsContainer"></div>
                                </div>

                                <!-- Step 4: Deposit -->
                                <div class="row mb-4">
                                    <div class="col-12 mb-3">
                                        <h6 class="text-muted border-bottom pb-2">4. Deposit</h6>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="deposit_months" class="form-label">Number of Months</label>
                                        <input type="number" step="0.5" min="0" class="form-control" id="deposit_months" name="deposit_months" placeholder="e.g. 2">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="deposit_amount_preview" class="form-label">Deposit Amount</label>
                                        <input type="text" class="form-control" id="deposit_amount_preview" readonly placeholder="Select a unit and enter months">
                                        <div class="form-text text-muted" style="font-size:0.75rem;">Unit rent × months — computed automatically. The amount actually recorded is always computed on the server the same way.</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="deposit_held_by" class="form-label">Deposit Held By</label>
                                        <select class="form-select" id="deposit_held_by" name="deposit_held_by">
                                            <option value="">Select if months entered</option>
                                            <option value="Company">Chabrin (Company)</option>
                                            <option value="Landlord">Landlord</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="deposit_reference" class="form-label">Deposit Reference</label>
                                        <input type="text" class="form-control" id="deposit_reference" name="deposit_reference">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($preselectedTenant)
<script>
    window.__preselectedTenant = @json($preselectedTenant);
</script>
@endif

<script>
    $(document).ready(function() {
        let invoiceableCycles = [];
        let unitsRentMap = {};
        let hasCurrentAssignment = false;

        loadInvoiceableCycles();
        function loadInvoiceableCycles() {
            $.ajax({
                url: "{{ route('property.settings.cycles.data') }}",
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.length > 0) {
                        invoiceableCycles = response;
                    }
                }
            });
        }

        function tenantOptionText(t) {
            var bits = [t.names];
            if (t.reference_number) bits.push(t.reference_number);
            if (t.mobile_number) bits.push(t.mobile_number);
            var text = bits.join(' — ');
            if (t.is_exited) text += ' [Exited]';
            return text;
        }

        // ── Step 1: search any tenant ──────────────────────────────
        $('#tenant_select').select2({
            placeholder: '-- Search for a tenant --',
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("units.tenants.returning.search") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { search: params.term };
                },
                processResults: function(response) {
                    var tenants = (response.success && response.tenants) ? response.tenants : [];
                    return {
                        results: tenants.map(function(t) {
                            return { id: t.encrypted_id, text: tenantOptionText(t) };
                        })
                    };
                }
            }
        });

        $('#tenant_select').on('change', function() {
            var tenantId = $(this).val();
            $('#tenant_id').val(tenantId || '');

            if (!tenantId) {
                $('#tenancyInfoWrapper').addClass('d-none');
                $('#clearAssignmentSection').addClass('d-none');
                $('#restOfForm').addClass('d-none');
                $('#saveButton').prop('disabled', true);
                return;
            }

            $('#tenancyInfoWrapper').removeClass('d-none');
            $('#confirmClearCurrent').prop('checked', false);
            loadCurrentTenancy(tenantId);
            loadTenantHistory(tenantId);
        });

        function updateGating() {
            var tenantSelected = !!$('#tenant_id').val();
            if (!tenantSelected) return;

            if (hasCurrentAssignment && !$('#confirmClearCurrent').is(':checked')) {
                $('#restOfForm').addClass('d-none');
                $('#saveButton').prop('disabled', true);
            } else {
                $('#restOfForm').removeClass('d-none');
                $('#saveButton').prop('disabled', false);
            }
        }
        $(document).on('change', '#confirmClearCurrent', updateGating);

        function renderCurrentAssignment(data) {
            var $table = $('#currentAssignmentTable');
            var $note = $('#noCurrentAssignmentNote');
            var $alert = $('#hasCurrentAssignmentAlert');

            hasCurrentAssignment = !!(data && data.has_current);
            $('#clearAssignmentSection').toggleClass('d-none', !hasCurrentAssignment);

            if (!hasCurrentAssignment) {
                $table.addClass('d-none');
                $note.removeClass('d-none');
                $alert.addClass('d-none');
                updateGating();
                return;
            }

            $note.addClass('d-none');
            $table.removeClass('d-none').find('tbody').empty().append(
                '<tr>' +
                '<td>' + (data.property_name || '-') + '</td>' +
                '<td>' + (data.unit_code || '-') + '</td>' +
                '<td>' + (data.sla_reference || '-') + '</td>' +
                '<td>' + (data.rent_amount !== null ? Number(data.rent_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-') + '</td>' +
                '<td>' + (data.lease_start_date || '-') + '</td>' +
                '<td>' + (data.lease_years || '-') + '</td>' +
                '<td>' + (data.frequency || '-') + '</td>' +
                '<td>' + (data.active_lease ? data.active_lease.ref_number : '-') + '</td>' +
                '<td>' + (data.active_lease ? data.active_lease.lease_status : '-') + '</td>' +
                '</tr>'
            );
            $alert.removeClass('d-none');
            updateGating();
        }

        function loadCurrentTenancy(tenantId) {
            $.ajax({
                url: '/units/tenants/returning/' + tenantId + '/current',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    renderCurrentAssignment(response.success ? response : null);
                },
                error: function() {
                    toastr.error('Failed to load current tenancy');
                    renderCurrentAssignment(null);
                }
            });
        }

        function loadTenantHistory(tenantId) {
            $('#tenantHistoryTable tbody').empty();
            $('#noHistoryNote').addClass('d-none');

            $.ajax({
                url: '/units/tenants/returning/' + tenantId + '/history',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    var rows = (response.success && response.history) ? response.history : [];
                    if (rows.length === 0) {
                        $('#noHistoryNote').removeClass('d-none');
                        return;
                    }
                    rows.forEach(function(h) {
                        $('#tenantHistoryTable tbody').append(
                            '<tr>' +
                            '<td>' + h.property_name + '</td>' +
                            '<td>' + h.unit_code + '</td>' +
                            '<td>' + h.landlord_name + '</td>' +
                            '<td>' + h.lease_start_date + '</td>' +
                            '<td>' + h.exit_date + '</td>' +
                            '<td>' + h.duration_label + '</td>' +
                            '<td>' + (h.rent_amount !== null ? Number(h.rent_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-') + '</td>' +
                            '<td>' + h.sla_reference + '</td>' +
                            '<td>' + h.exit_reason + '</td>' +
                            '</tr>'
                        );
                    });
                },
                error: function() {
                    toastr.error('Failed to load previous tenancy history');
                }
            });
        }

        // Pre-select a tenant arriving from the Tenant History page's
        // "Reassign This Tenant" link (?tenant=... resolved server-side into
        // window.__preselectedTenant, since select2's remote source needs the
        // option's display text seeded before it can be selected).
        if (window.__preselectedTenant) {
            var pre = window.__preselectedTenant;
            var option = new Option(tenantOptionText(pre), pre.encrypted_id, true, true);
            $('#tenant_select').append(option).trigger('change');
        }

        // ── Step 2: Landlord (optional) → Property → Unit cascade ──
        var selectedPropertyId = null;

        $('#return_landlord_id').select2({
            placeholder: 'Search landlord by name (optional)...',
            width: '100%',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("units.tenants.search-landlords") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(response) { return { results: response.results }; }
            }
        });

        function initPropertyPicker(scopedToLandlord) {
            var $field = $('#return_property_id');
            if ($field.hasClass('select2-hidden-accessible')) {
                $field.select2('destroy');
            }
            $field.select2({
                placeholder: scopedToLandlord ? 'Select from landlord\'s properties...' : 'Search property by name or reference...',
                width: '100%',
                minimumInputLength: scopedToLandlord ? 0 : 2,
                ajax: {
                    url: '{{ route("units.tenants.search-properties") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) {
                        return { term: params.term, client_id: $('#return_landlord_id').val() || null };
                    },
                    processResults: function(response) { return { results: response.results }; }
                }
            });
        }
        initPropertyPicker(false);

        $('#return_unit_id').select2({
            placeholder: 'Select a property first',
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: '{{ route("units.tenants.vacant-units") }}',
                dataType: 'json',
                delay: 200,
                data: function(params) { return { property_id: selectedPropertyId, term: params.term }; },
                processResults: function(response) { return { results: response.results }; }
            }
        });

        $('#return_landlord_id').on('change', function() {
            initPropertyPicker(!!$(this).val());
            $('#return_property_id').val(null).trigger('change');
        });

        $('#return_property_id').on('change', function() {
            selectedPropertyId = $(this).val();
            $('#return_unit_id').val(null).trigger('change');
            $('#return_unit_id').prop('disabled', !selectedPropertyId);

            if (selectedPropertyId) {
                loadUnitRents(selectedPropertyId);
                loadBillableItemsForProperty(selectedPropertyId);
            } else {
                unitsRentMap = {};
                $('#invoicableItemsContainer').empty();
                $('#rentInfoNote').hide();
                $('#noBillableItemsNote').hide();
            }
        });

        // Same endpoint the onboarding deposit preview uses — carries rent_amount
        // per unit, which the vacant-units picker's {id, text} shape doesn't.
        function loadUnitRents(propertyId) {
            unitsRentMap = {};
            $.ajax({
                url: '/property/' + propertyId + '/units',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    (response.units || []).forEach(function(u) {
                        unitsRentMap[u.id] = parseFloat(String(u.rent_amount || '0').replace(/,/g, '')) || 0;
                    });
                    updateDepositPreview();
                }
            });
        }

        function loadBillableItemsForProperty(propertyId) {
            $('#invoicableItemsContainer').empty();
            $('#rentInfoNote').hide();
            $('#noBillableItemsNote').hide();

            $.ajax({
                url: '/property/' + propertyId + '/sla',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#rentInfoNote').show();
                    renderBillableItems(response.billable_items || []);

                    var currentSla = (response.sla && response.sla.length) ? response.sla[0] : null;
                    if (currentSla && currentSla.service_charges_selected && parseFloat(currentSla.service_charges_cost) > 0) {
                        $('#service_charges_amount').val(currentSla.service_charges_cost);
                    } else {
                        $('#service_charges_amount').val('');
                    }
                }
            });
        }

        function renderBillableItems(items) {
            $('#invoicableItemsContainer').empty();
            $('#noBillableItemsNote').toggle(items.length === 0);

            let cycleOptions = '<option value="">Please select</option>';
            invoiceableCycles.forEach(function(cycle) {
                cycleOptions += '<option value="' + cycle.id + '">' + cycle.text + '</option>';
            });

            items.forEach(function(item, index) {
                const entry = `
                    <div class="billable-item-entry mb-3 p-3 border rounded">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input billable-item-checkbox" type="checkbox"
                                        id="item_enabled_${index}" checked data-target="item-row-${index}">
                                    <label class="form-check-label fw-semibold" for="item_enabled_${index}">${item.name}</label>
                                </div>
                                <input type="hidden" class="item-row-${index}" name="invoicable_items[${index}][item]" value="${item.item_id}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="amount_${index}" class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control item-row-${index}"
                                    id="amount_${index}" name="invoicable_items[${index}][amount]"
                                    value="${item.cost || '0.00'}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="cycle_${index}" class="form-label">Cycle <span class="text-danger">*</span></label>
                                <select class="form-select item-row-${index}" id="cycle_${index}" name="invoicable_items[${index}][cycle]" required>
                                    ${cycleOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label d-block">Prepay Client</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input item-row-${index}" type="checkbox" id="prepay_client_${index}" name="invoicable_items[${index}][prepay_client]" value="Yes">
                                    <label class="form-check-label" for="prepay_client_${index}">Yes</label>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('#invoicableItemsContainer').append(entry);
            });
        }

        $('#invoicableItemsContainer').on('change', '.billable-item-checkbox', function() {
            var target = $(this).data('target');
            $('.' + target).prop('disabled', !this.checked);
        });

        // ── Step 4: Deposit preview — unit rent × months ───────────
        function updateDepositPreview() {
            var unitId = $('#return_unit_id').val();
            var rent = unitsRentMap[unitId] || 0;
            var months = parseFloat($('#deposit_months').val()) || 0;
            var preview = $('#deposit_amount_preview');
            if (rent > 0 && months > 0) {
                preview.val((rent * months).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            } else {
                preview.val('');
            }
        }
        $(document).on('change', '#return_unit_id', updateDepositPreview);
        $(document).on('input', '#deposit_months', updateDepositPreview);

        // ── Submit ───────────────────────────────────────────────
        $('#returningTenantForm').on('submit', function(e) {
            e.preventDefault();

            if (!$('#tenant_id').val()) {
                toastr.error('Please select a tenant first.');
                return;
            }
            if (hasCurrentAssignment && !$('#confirmClearCurrent').is(':checked')) {
                toastr.error('Please confirm clearing this tenant\'s current unit/lease before reassigning them.');
                return;
            }
            if (!$('#return_unit_id').val()) {
                toastr.error('Please select a unit.');
                return;
            }

            var $btn = $('#saveButton');
            $('#returningTenantForm .is-invalid').removeClass('is-invalid');
            $('#submitSpinner').removeClass('d-none');
            $btn.prop('disabled', true);

            var formData = new FormData(this);

            $.ajax({
                url: '{{ route("units.tenants.returning.store") }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Tenant reassigned successfully');
                        setTimeout(function() {
                            window.location.href = res.redirect_url;
                        }, 800);
                    } else {
                        toastr.error(res.error || 'Failed to reassign tenant');
                        $('#submitSpinner').addClass('d-none');
                        $btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(key) {
                            toastr.error(errors[key][0]);
                        });
                    } else {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to reassign tenant';
                        toastr.error(msg);
                    }
                    $('#submitSpinner').addClass('d-none');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
</script>
@endsection

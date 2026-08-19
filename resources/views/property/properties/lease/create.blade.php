@extends('layouts.main')
@section('content')
<style>
    .btn-outline-primary.date-filter-radio {
        color: #3598db;
        border-color: #3598db;
    }

    .btn-outline-primary.date-filter-radio:hover {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }

    .btn-check:checked+.btn-outline-primary.date-filter-radio {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Lease Agreement</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="leaseAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : route('property.properties.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> Please correct the following errors:
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('property.leases.store') }}" method="POST" id="leaseAgreementForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Lease Agreements Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Lease Agreements</h6>
                                </div>

                                @if($client)
                                <div class="col-md-4 mb-3">
                                    <label for="client_name" class="form-label">Client/Landlord <span class="text-danger">*</span></label>
                                    <!-- Pre-filled client (coming from client details page) -->
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('client_name') is-invalid @enderror" id="client_name" name="client_name" value="{{ $client->names }}" required readonly>
                                        <input type="hidden" id="client_id" name="client_id" value="{{ $client->id }}">
                                        @error('client_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">Loading properties...</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @else
                                <div class="col-12 mb-3">
                                    <!-- Search Mode Toggle -->
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label d-block">Search By <span class="text-danger">*</span></label>
                                            <div class="btn-group" role="group" aria-label="Search mode">
                                                <input type="radio" class="btn-check" name="search_mode" id="mode_property" value="property" checked autocomplete="off">
                                                <label class="btn btn-outline-primary date-filter-radio" for="mode_property">
                                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">apartment</span>
                                                    Property
                                                </label>
                                                <input type="radio" class="btn-check" name="search_mode" id="mode_landlord" value="landlord" autocomplete="off">
                                                <label class="btn btn-outline-primary date-filter-radio" for="mode_landlord">
                                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">person</span>
                                                    Landlord
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="client_id" name="client_id" value="{{ old('client_id') }}">
                                    @error('client_id')
                                    <div class="text-danger small mb-2">{{ $message }}</div>
                                    @enderror

                                    <!-- MODE: Search by Property -->
                                    <div id="property_mode_section">
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label for="property_search_input" class="form-label mb-1">Search Property</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control form-control-sm" id="property_search_input" placeholder="-- Search --">
                                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_property_btn">
                                                        <span class="material-symbols-outlined" id="search_property_icon" style="font-size:18px;">search</span>
                                                        <span class="spinner-border spinner-border-sm" role="status" id="search_property_spinner" style="display:none;"></span>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Search by property name or reference</small>
                                            </div>
                                            <div class="col-md-6" id="property_select_slot_property"></div>
                                        </div>
                                        <div class="row mb-2" id="property_landlord_wrap" style="display:none;">
                                            <div class="col-md-6">
                                                <label for="property_landlord_select" class="form-label mb-1">Select Landlord <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="property_landlord_select" disabled>
                                                    <option value="">-- Select Property First --</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODE: Search by Landlord -->
                                    <div id="landlord_mode_section" style="display:none;">
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label for="landlord_search_input" class="form-label mb-1">Search Landlord</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control form-control-sm" id="landlord_search_input" placeholder="-- Search --">
                                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_landlord_btn">
                                                        <span class="material-symbols-outlined" id="search_landlord_icon" style="font-size:18px;">search</span>
                                                        <span class="spinner-border spinner-border-sm" role="status" id="search_landlord_spinner" style="display:none;"></span>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Search by name, reference, phone, email or ID number</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="landlord_direct_select" class="form-label mb-1">Select Landlord <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="landlord_direct_select" disabled>
                                                    <option value="">-- Search First --</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-6" id="property_select_slot_landlord"></div>
                                        </div>
                                    </div>

                                    <!-- Selected details (shared) -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <small class="text-muted">
                                                <strong>Selected Landlord:</strong> <span id="selected_client_name">-</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Canonical Property field — JS relocates this into whichever mode's slot is active -->
                                <div class="col-md-6 d-none" id="property_field_wrapper">
                                    <label for="property_id" class="form-label mb-1">Select Property <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required disabled>
                                        <option value="">-- Search First --</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif

                                <div class="col-md-6 mb-3">
                                    <label for="unit_id" class="form-label">Unit <span class="text-danger">*</span></label>
                                    <select class="form-select @error('unit_id') is-invalid @enderror" id="unit_id" name="unit_id" required>
                                        <option value="">Please select a property first</option>
                                    </select>
                                    <small class="text-muted">Units with a tenant already assigned (via Assign Unit / tenant creation) are shown with their tenant's name — pick one of those to create their lease.</small>
                                    @error('unit_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <input type="hidden" id="tenant_id" name="tenant_id" value="{{ old('tenant_id') }}">
                                    @error('tenant_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <div id="tenant_existing_lease_note" class="alert alert-light-danger py-2 px-3 mb-0" style="display:none; font-size: 0.85rem;">
                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">info</span>
                                        <span id="tenant_existing_lease_text"></span>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="linked_lease" class="form-label">Linked Lease</label>
                                    <select class="form-select @error('linked_lease') is-invalid @enderror" id="linked_lease" name="linked_lease">
                                        <option value="">Please select</option>
                                    </select>
                                    @error('linked_lease')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="date_start" class="form-label">Date Start <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_start') is-invalid @enderror"
                                        id="date_start" name="date_start"
                                        value="{{ old('date_start') }}" required>
                                    @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="years" class="form-label">Years <span class="text-danger">*</span></label>
                                    <select class="form-select @error('years') is-invalid @enderror" id="years" name="years" required>
                                        <option value="">Please select</option>
                                        <option value="1" {{ old('years') == '1' ? 'selected' : 'selected' }}>1</option>
                                        <option value="2" {{ old('years') == '2' ? 'selected' : '' }}>2</option>
                                        <option value="3" {{ old('years') == '3' ? 'selected' : '' }}>3</option>
                                        <option value="4" {{ old('years') == '4' ? 'selected' : '' }}>4</option>
                                        <option value="5" {{ old('years') == '5' ? 'selected' : '' }}>5</option>
                                        <option value="6" {{ old('years') == '6' ? 'selected' : '' }}>6</option>
                                        <option value="7" {{ old('years') == '7' ? 'selected' : '' }}>7</option>
                                        <option value="8" {{ old('years') == '8' ? 'selected' : '' }}>8</option>
                                        <option value="9" {{ old('years') == '9' ? 'selected' : '' }}>9</option>
                                        <option value="10" {{ old('years') == '10' ? 'selected' : '' }}>10</option>
                                    </select>
                                    @error('years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="auto_renewal" class="form-label">Auto-Renewal <span class="text-danger">*</span></label>
                                    <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                        <option value="Yes" {{ old('auto_renewal') == 'Yes' ? 'selected' : 'selected' }}>Yes</option>
                                        <option value="No" {{ old('auto_renewal') == 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    @error('auto_renewal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_rate" class="form-label">Escalation Rate <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control @error('escalation_rate') is-invalid @enderror"
                                        id="escalation_rate" name="escalation_rate"
                                        value="{{ old('escalation_rate', '7.5') }}" required>
                                    @error('escalation_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_frequency" class="form-label">Escalation Frequency</label>
                                    <select class="form-select @error('escalation_frequency') is-invalid @enderror" id="escalation_frequency" name="escalation_frequency">
                                        <option value="Annually">Annually</option>
                                        <option value="Semi-Annually">Semi-Annually</option>
                                        <option value="Quarterly">Quarterly</option>
                                        <option value="Monthly">Monthly</option>
                                    </select>
                                    @error('escalation_frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="documents" class="form-label">Documents</label>
                                    <div class="input-group">
                                        <input type="file" class="form-control @error('documents') is-invalid @enderror"
                                            id="documents" name="documents[]" multiple accept=".pdf,.doc,.docx">
                                        <button type="button" class="btn btn-outline-primary" id="addFilesBtn">
                                            <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                                            Add files
                                        </button>
                                        @error('documents')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Invoicable Items Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Invoicable Items</h6>
                                </div>

                                <div class="col-12 mb-3">
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addInvoicableItem">
                                        <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                        Add Item
                                    </button>
                                </div>

                                <div id="invoicableItemsContainer">
                                    <div class="invoicable-item-entry mb-3 p-3 border rounded">
                                        <div class="row align-items-end">
                                            <div class="col-md-4 mb-3">
                                                <label for="item_0" class="form-label">Item <span class="text-danger">*</span></label>
                                                <select class="form-select" id="item_0" name="invoicable_items[0][item]" required>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="amount_0" class="form-label">Amount <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control"
                                                    id="amount_0" name="invoicable_items[0][amount]"
                                                    value="0.00" required>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="cycle_0" class="form-label">Cycle <span class="text-danger">*</span></label>
                                                <select class="form-select" id="cycle_0" name="invoicable_items[0][cycle]" required>
                                                    <option value="">Please select</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="form-label d-block">Prepay Client</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="prepay_client_0" name="invoicable_items[0][prepay_client]" value="Yes">
                                                    <label class="form-check-label" for="prepay_client_0">Yes</label>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-primary btn-sm add-item-inline d-none">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                                </button>
                                                <button type="button" class="btn btn-light btn-sm remove-invoicable-item d-none">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                </button>
                                            </div>
                                        </div>
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


<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Convert a Laravel dot-notation error key (e.g. "invoicable_items.0.item")
        // into the bracket-notation form field name it corresponds to
        // (e.g. "invoicable_items[0][item]") so we can locate the actual input.
        function dotKeyToFieldName(dotted) {
            var parts = dotted.split('.');
            return parts[0] + parts.slice(1).map(function(p) { return '[' + p + ']'; }).join('');
        }

        // Form submission — AJAX, so validation errors never wipe out what was typed
        $('#leaseAgreementForm').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $('#saveButton');

            $('#leaseAgreementForm .is-invalid').removeClass('is-invalid');
            $('#leaseAgreementForm .invalid-feedback').text('');

            $('#submitSpinner').removeClass('d-none');
            $btn.prop('disabled', true);

            var formData = new FormData(this);

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Lease Agreement created successfully');
                        setTimeout(function() {
                            window.location.href = res.redirect_url;
                        }, 800);
                    } else {
                        toastr.error(res.error || 'Failed to create lease agreement');
                        $('#submitSpinner').addClass('d-none');
                        $btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        var $firstInvalid = null;
                        var unmatched = [];

                        Object.keys(errors).forEach(function(key) {
                            var fieldName = dotKeyToFieldName(key);
                            var $field = $('[name="' + fieldName + '"]');

                            if ($field.length && $field.is(':visible')) {
                                $field.addClass('is-invalid');
                                var $feedback = $field.nextAll('.invalid-feedback').first();
                                if (!$feedback.length) {
                                    $feedback = $field.closest('.mb-3, .col-12, .col-md-4, .col-md-6').find('.invalid-feedback').first();
                                }
                                if ($feedback.length) {
                                    $feedback.text(errors[key][0]);
                                }
                                if (!$firstInvalid) {
                                    $firstInvalid = $field;
                                }
                            } else {
                                // Hidden/backing fields (e.g. client_id, tenant_id driven by the
                                // search widgets) have no visible input to flag — surface the message directly.
                                unmatched.push(errors[key][0]);
                            }
                        });

                        if ($firstInvalid) {
                            $('html, body').animate({ scrollTop: $firstInvalid.offset().top - 120 }, 300);
                        }

                        if (unmatched.length === 0) {
                            toastr.error('Please correct the highlighted fields');
                        }
                        unmatched.forEach(function(msg) {
                            toastr.error(msg);
                        });
                    } else {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to create lease agreement';
                        toastr.error(msg);
                    }
                    $('#submitSpinner').addClass('d-none');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Check if client is pre-filled or needs to be searched
        @if($client)
        // Client is pre-filled, load properties immediately
        const clientId = $('#client_id').val();
        if (clientId) {
            loadProperties(clientId);
        }
        @else
        // No client pre-filled — dual search widget (Property / Landlord), reusing
        // the exact same AJAX endpoints as /landlord/account/statement
        function resetClientSelection() {
            $('#client_id').val('');
            $('#selected_client_name').text('-');
            $('#property_id').html('<option value="">-- Search First --</option>').prop('disabled', true);
            $('#unit_id').html('<option value="">Please select a property first</option>').prop('disabled', true);
            $('#tenant_id').val('');
            $('#tenant_existing_lease_note').hide();
            $('#property_landlord_wrap').hide();
            $('#property_landlord_select').html('<option value="">-- Select Property First --</option>').prop('disabled', true);
        }

        // Move the canonical Property field into whichever mode's slot is active,
        // so it always sits beside that mode's search box (exact account-statement layout)
        function placePropertyField(mode) {
            var $wrapper = $('#property_field_wrapper').removeClass('d-none');
            if (mode === 'property') {
                $wrapper.appendTo('#property_select_slot_property');
            } else {
                $wrapper.appendTo('#property_select_slot_landlord');
            }
        }
        placePropertyField('property');

        // Search mode toggle
        $('input[name="search_mode"]').on('change', function() {
            var mode = $(this).val();
            resetClientSelection();
            placePropertyField(mode);
            if (mode === 'property') {
                $('#property_mode_section').show();
                $('#landlord_mode_section').hide();
            } else {
                $('#property_mode_section').hide();
                $('#landlord_mode_section').show();
            }
        });

        // ── Search by Property ──────────────────────────────────
        $('#search_property_btn').on('click', function() {
            const searchTerm = $('#property_search_input').val().trim();
            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            $('#search_property_icon').hide();
            $('#search_property_spinner').show();
            $('#search_property_btn').prop('disabled', true);

            $.ajax({
                url: '/ajax/landlord/properties',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.properties && response.properties.length > 0) {
                        $('#property_id').empty().append('<option value="">-- Select Property --</option>');
                        $.each(response.properties, function(index, prop) {
                            $('#property_id').append(`<option value="${prop.id}">${prop.property_name} - ${prop.reference_number || ''}</option>`);
                        });
                        $('#property_id').prop('disabled', false);
                        toastr.success(`Found ${response.properties.length} property(ies)`);
                    } else {
                        toastr.info('No properties found');
                        $('#property_id').empty().append('<option value="">-- No Results --</option>').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error searching properties');
                },
                complete: function() {
                    $('#search_property_icon').show();
                    $('#search_property_spinner').hide();
                    $('#search_property_btn').prop('disabled', false);
                }
            });
        });

        $('#property_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_property_btn').click();
            }
        });

        // Resolve the landlord for the selected property
        function loadLandlordForProperty(propertyId) {
            $('#property_landlord_wrap').show();
            $('#property_landlord_select').html('<option value="">Loading...</option>').prop('disabled', true);

            $.ajax({
                url: '/ajax/landlord/landlords-by-property',
                method: 'GET',
                data: {
                    property_id: propertyId
                },
                success: function(response) {
                    $('#property_landlord_select').empty().append('<option value="">-- Select Landlord --</option>');
                    if (response.success && response.landlords && response.landlords.length > 0) {
                        $.each(response.landlords, function(i, l) {
                            $('#property_landlord_select').append(`<option value="${l.id}">${l.names} - ${l.reference_number || ''}</option>`);
                        });
                        $('#property_landlord_select').prop('disabled', false);

                        if (response.landlords.length === 1) {
                            $('#property_landlord_select').val(response.landlords[0].id).trigger('change');
                        }
                    } else {
                        toastr.warning('No landlord found for this property');
                        $('#property_landlord_select').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error loading landlord for property');
                }
            });
        }

        $('#property_landlord_select').on('change', function() {
            const landlordId = $(this).val();
            const landlordText = $(this).find(':selected').text();
            if (landlordId) {
                $('#client_id').val(landlordId);
                $('#selected_client_name').text(landlordText);
            } else {
                $('#client_id').val('');
                $('#selected_client_name').text('-');
            }
        });

        // ── Search by Landlord ──────────────────────────────────
        $('#search_landlord_btn').on('click', function() {
            const searchTerm = $('#landlord_search_input').val().trim();
            if (searchTerm.length < 2) {
                toastr.warning('Please enter at least 2 characters to search');
                return;
            }

            $('#search_landlord_icon').hide();
            $('#search_landlord_spinner').show();
            $('#search_landlord_btn').prop('disabled', true);

            $.ajax({
                url: '/ajax/landlord/search-landlords',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    $('#landlord_direct_select').empty().append('<option value="">-- Select Landlord --</option>');
                    if (response.success && response.landlords && response.landlords.length > 0) {
                        $.each(response.landlords, function(i, l) {
                            $('#landlord_direct_select').append(`<option value="${l.id}">${l.names} - ${l.reference_number || ''}</option>`);
                        });
                        $('#landlord_direct_select').prop('disabled', false);
                        toastr.success('Found ' + response.landlords.length + ' landlord(s)');
                    } else {
                        toastr.info('No landlords found');
                        $('#landlord_direct_select').prop('disabled', true);
                    }
                },
                error: function() {
                    toastr.error('Error searching landlords');
                },
                complete: function() {
                    $('#search_landlord_icon').show();
                    $('#search_landlord_spinner').hide();
                    $('#search_landlord_btn').prop('disabled', false);
                }
            });
        });

        $('#landlord_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_landlord_btn').click();
            }
        });

        // When landlord selected directly, load their properties
        $('#landlord_direct_select').on('change', function() {
            const landlordId = $(this).val();
            const landlordText = $(this).find(':selected').text();

            if (landlordId) {
                $('#client_id').val(landlordId);
                $('#selected_client_name').text(landlordText);
                loadProperties(landlordId);
            } else {
                $('#client_id').val('');
                $('#selected_client_name').text('-');
                $('#property_id').html('<option value="">-- Select Landlord First --</option>').prop('disabled', true);
            }
        });
        @endif

        // Tenant is no longer searched separately. By the time a lease is created,
        // a tenant has typically already been created and assigned to a unit
        // (interactively, via bulk upload, or via "Assign Unit" on the tenant
        // listing) — so the Unit dropdown below shows each unit's assigned tenant
        // directly (see loadUnitsForProperty()/the #unit_id change handler) instead
        // of making the office re-search for someone already on record.

        // Function to load properties for the client
        function loadProperties(clientId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);

            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Properties loaded:', response);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');

                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}">${property.property_name}</option>`);
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found for this client</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties:', xhr.responseText);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Error loading properties</option>');
                }
            });
        }

        // Load Units when property is selected (SLA is resolved server-side, no
        // longer fetched/shown here)
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();

            if (propertyId) {
                loadUnitsForProperty(propertyId);
                @if(!$client)
                if ($('input[name="search_mode"]:checked').val() === 'property') {
                    loadLandlordForProperty(propertyId);
                }
                @endif
            } else {
                // Reset dependent fields
                $('#unit_id').html('<option value="">Please select a property first</option>').prop('disabled', false);
                $('#tenant_id').val('');
                $('#tenant_existing_lease_note').hide();
            }
        });

        // Function to load units for the selected property. Every unit shows
        // (not just vacant ones) so a unit that already has a tenant assigned —
        // via Assign Unit or interactive tenant creation — but no lease yet is
        // visible and selectable; that's exactly what this page exists to
        // formalize. Units with no tenant assigned at all render disabled —
        // there's nothing for a lease to attach to. Each unit's status/tenant
        // now comes from UnitsController::getAvailableUnits(), which determines
        // Taken/Pending Lease/Vacant from the unit's actual lease state
        // (Unit::activeLeaseAgreement()) rather than occupancy_status_id —
        // that column carries conflicting numbering schemes across this
        // codebase and can't be trusted. SLA Ref is gone from this form
        // entirely for the same underlying reason server-derivation was
        // adopted elsewhere: it's resolved from the property automatically
        // (LeasesController::store()) instead of being picked here.
        function loadUnitsForProperty(propertyId) {
            $('#unit_id').html('<option value="">Loading units...</option>').prop('disabled', true);
            $('#tenant_id').val('');
            $('#tenant_existing_lease_note').hide();

            $.ajax({
                url: `/property/${propertyId}/available-units`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Units loaded:', response);
                    $('#unit_id').empty().prop('disabled', false);
                    $('#unit_id').append('<option value="">Please select</option>');

                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            let label = unit.unit_code || unit.unit_name || unit.unit_number;
                            let disabled = '';

                            if (unit.tenant_name) {
                                label += ' — ' + unit.tenant_name + ' (' + unit.status + ')';
                            } else {
                                label += ' (No tenant assigned)';
                                disabled = 'disabled';
                            }

                            $('#unit_id').append(
                                `<option value="${unit.id}" data-tenant-id="${unit.tenant_id || ''}" data-status="${unit.status}" ${disabled}>${label}</option>`
                            );
                        });
                    } else {
                        $('#unit_id').append('<option value="">No units found for this property</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading units:', xhr.responseText);
                    $('#unit_id').empty().prop('disabled', false);
                    $('#unit_id').append('<option value="">Error loading units</option>');
                }
            });
        }

        // Selecting a unit auto-fills the tenant behind the scenes (no more
        // separate tenant search) and warns if the unit already has a lease.
        $('#unit_id').on('change', function() {
            var $selected = $(this).find(':selected');
            var tenantId = $selected.data('tenant-id');
            var status = $selected.data('status');

            $('#tenant_id').val(tenantId || '');

            if (status === 'Taken') {
                $('#tenant_existing_lease_text').text('This unit already has an active lease — creating another will not end it automatically; use Linked Lease if this is a renewal.');
                $('#tenant_existing_lease_note').show();
            } else {
                $('#tenant_existing_lease_note').hide();
            }
        });


        let invoiceableItems = []; // Store all invoiceable items
        let invoiceableCycles = []; // Store all billing cycles

        // Load invoiceable items and cycles on page load
        loadInvoiceableItems();
        loadInvoiceableCycles();

        // Function to load invoiceable items
        function loadInvoiceableItems() {
            $.ajax({
                url: '/invoiceable-items',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Invoiceable items loaded:', response);
                    if (response.items && response.items.length > 0) {
                        invoiceableItems = response.items;

                        // Populate the first dropdown
                        populateInvoiceableItems('#item_0');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading invoiceable items:', xhr.responseText);
                }
            });
        }

        // Function to load billing cycles
        function loadInvoiceableCycles() {
            $.ajax({
                url: "{{ route('property.settings.cycles.data') }}",
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.length > 0) {
                        invoiceableCycles = response;
                        populateCycleSelect('#cycle_0');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading cycles:', xhr.responseText);
                }
            });
        }

        // Function to populate a cycle dropdown
        function populateCycleSelect(selectId) {
            $(selectId).empty().append('<option value="">Please select</option>');
            invoiceableCycles.forEach(function(cycle) {
                $(selectId).append(`<option value="${cycle.id}">${cycle.text}</option>`);
            });
        }

        // Function to populate invoiceable items dropdown
        function populateInvoiceableItems(selectId) {
            $(selectId).empty();
            $(selectId).append('<option value="">Please select</option>');

            if (invoiceableItems.length > 0) {
                invoiceableItems.forEach(function(item) {
                    $(selectId).append(`<option value="${item.id}">${item.item_name}</option>`);
                });
            } else {
                $(selectId).append('<option value="">No items available</option>');
            }
        }

        // Update the Dynamic Invoicable Items section
        let itemCounter = 1;
        $('#addInvoicableItem').on('click', function() {
            if (invoiceableItems.length === 0) {
                alert('Loading invoiceable items, please wait...');
                return;
            }

            // Generate invoiceable items options
            let itemOptions = '<option value="">Please select</option>';
            invoiceableItems.forEach(function(item) {
                itemOptions += `<option value="${item.id}">${item.item_name}</option>`;
            });

            // Generate cycle options
            let cycleOptions = '<option value="">Please select</option>';
            invoiceableCycles.forEach(function(cycle) {
                cycleOptions += `<option value="${cycle.id}">${cycle.text}</option>`;
            });

            const itemEntry = `
                <div class="invoicable-item-entry mb-3 p-3 border rounded">
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-3">
                            <label for="item_${itemCounter}" class="form-label">Item <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_${itemCounter}" name="invoicable_items[${itemCounter}][item]" required>
                                ${itemOptions}
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="amount_${itemCounter}" class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" 
                                id="amount_${itemCounter}" name="invoicable_items[${itemCounter}][amount]" 
                                value="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cycle_${itemCounter}" class="form-label">Cycle <span class="text-danger">*</span></label>
                            <select class="form-select" id="cycle_${itemCounter}" name="invoicable_items[${itemCounter}][cycle]" required>
                                ${cycleOptions}
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label d-block">Prepay Client</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="prepay_client_${itemCounter}" name="invoicable_items[${itemCounter}][prepay_client]" value="Yes">
                                <label class="form-check-label" for="prepay_client_${itemCounter}">Yes</label>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-light btn-sm remove-invoicable-item">
                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#invoicableItemsContainer').append(itemEntry);
            itemCounter++;
            updateRemoveButtons('.invoicable-item-entry', '.remove-invoicable-item');
        });

        $(document).on('click', '.remove-invoicable-item', function() {
            $(this).closest('.invoicable-item-entry').remove();
            updateRemoveButtons('.invoicable-item-entry', '.remove-invoicable-item');
        });

        // Helper function to show/hide remove buttons
        function updateRemoveButtons(containerClass, buttonClass) {
            if ($(containerClass).length > 1) {
                $(buttonClass).removeClass('d-none');
            } else {
                $(buttonClass).addClass('d-none');
            }
        }

        // Add Files Button functionality
        $('#addFilesBtn').on('click', function() {
            $('#documents').click();
        });

        // Display selected files
        $('#documents').on('change', function() {
            let files = $(this)[0].files;
            if (files.length > 0) {
                let fileNames = [];
                for (let i = 0; i < files.length; i++) {
                    fileNames.push(files[i].name);
                }
                alert('Selected files:\n' + fileNames.join('\n'));
            }
        });

        // Calculate lease end date
        $('#date_start, #years').on('change', function() {
            let startDate = $('#date_start').val();
            let years = $('#years').val();

            if (startDate && years) {
                let start = new Date(startDate);
                start.setFullYear(start.getFullYear() + parseInt(years));
                console.log('Lease End Date:', start.toISOString().split('T')[0]);
            }
        });
    });
</script>
@endsection
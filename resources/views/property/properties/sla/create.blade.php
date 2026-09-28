@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Service Agreement</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="serviceAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save SLA</span>
                            </button>
                            <a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : url('/property/service-agreements') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> We are unable to find this record
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('service.agreements.store') }}" method="POST" id="serviceAgreementForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Service Agreement Details Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Service Agreement Details</h6>
                                </div>

                                <!-- Client selection (show dropdown if no client, otherwise hidden field) -->
                                @if($client)
                                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                                    <div class="col-md-4 mb-3">
                                        <label for="client_name" class="form-label">Client Name</label>
                                        <input type="text" class="form-control" id="client_name" value="{{ $client->names }}" readonly>
                                    </div>
                                @else
                                    <div class="col-md-4 mb-3">
                                        <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                        <select name="client_id" id="client_id" class="form-control" required style="width:100%;">
                                            <option value="">Type to search client...</option>
                                        </select>
                                        @error('client_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif

                                <div class="col-md-4 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">Loading properties...</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-danger d-none" id="currentSlaWarning"></small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="commission_percentage" class="form-label">Commission (%) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control @error('commission_percentage') is-invalid @enderror"
                                           id="commission_percentage" name="commission_percentage" required>
                                    @error('commission_percentage')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="vat_able" class="form-label">VATable <span class="text-danger">*</span></label>
                                    <select class="form-select @error('vat_able') is-invalid @enderror" id="vat_able" name="vat_able" required>
                                        <option value="Yes" {{ old('vat_able') == 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('vat_able') == 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Flat Rate" {{ old('vat_able') == 'Flat Rate' ? 'selected' : '' }}>Flat Rate</option>
                                    </select>
                                    @error('vat_able')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="vat_percentage" class="form-label">VAT (%)</label>
                                    <input type="number" step="0.01" class="form-control @error('vat_percentage') is-invalid @enderror" 
                                           id="vat_percentage" name="vat_percentage">
                                    @error('vat_percentage')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="flat_rate_vat" class="form-label">Flat Rate VAT</label>
                                    <input type="number" step="0.01" class="form-control @error('flat_rate_vat') is-invalid @enderror" 
                                           id="flat_rate_vat" name="flat_rate_vat">
                                    @error('flat_rate_vat')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="auto_renewal" class="form-label">Auto Renewal <span class="text-danger">*</span></label>
                                    <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                        <option value="1" {{ old('auto_renewal') == '1' ? 'selected' : '' }}>Yes</option>
                                        <option value="2" {{ old('auto_renewal') == '2' ? 'selected' : '' }}>No</option>
                                    </select>
                                    @error('auto_renewal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="move_in_inspection_required" class="form-label">Move-in Inspection Required</label>
                                    <select class="form-select" id="move_in_inspection_required" name="move_in_inspection_required">
                                        <option value="0" {{ old('move_in_inspection_required', '0') == '0' ? 'selected' : '' }}>No — Optional</option>
                                        <option value="1" {{ old('move_in_inspection_required') == '1' ? 'selected' : '' }}>Yes — Mandatory</option>
                                    </select>
                                    <div class="form-text text-muted" style="font-size:0.75rem;">When enabled, a move-in inspection must be completed during tenant onboarding.</div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date (Every Month) <span class="text-danger">*</span></label>
                                    <select class="form-select @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" required>
                                        <option value="">Select Day</option>
                                        @for($i = 1; $i <= 31; $i++)
                                        <option value="{{ $i }}" {{ old('payment_date') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                    @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="date_start" class="form-label">Date Start</label>
                                    <input type="date" class="form-control @error('date_start') is-invalid @enderror"
                                           id="date_start" name="date_start"
                                           value="{{ old('date_start', now()->format('Y-m-d')) }}">
                                    @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="duration_years" class="form-label">Duration (Years)</label>
                                    <input type="number" step="1" class="form-control @error('duration_years') is-invalid @enderror"
                                           id="duration_years" name="duration_years">
                                    @error('duration_years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="sla_documents" class="form-label">SLA Documents</label>
                                    <input type="file" class="form-control @error('sla_documents') is-invalid @enderror"
                                           id="sla_documents" name="sla_documents[]" multiple accept=".pdf,.doc,.docx">
                                    <small class="form-text text-muted">You can upload multiple documents</small>
                                    @error('sla_documents')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input control-toggle" type="checkbox" role="switch"
                                               id="charge_registration_fee" name="charge_registration_fee" value="1"
                                               {{ old('charge_registration_fee', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="charge_registration_fee">
                                            Charge Registration Fee
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Billable Items & Returns Combination -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted border-bottom pb-2">Billable Items</h6>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td style="width:32px;">
                                                    <input class="form-check-input" type="checkbox" id="rent_selected" name="rent_selected" value="1" checked>
                                                </td>
                                                <td>Rent</td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input billable-item-toggle" type="checkbox"
                                                           id="electricity_bill_selected" name="electricity_bill_selected" value="1"
                                                           data-target="electricity_bill_cost">
                                                </td>
                                                <td>Electricity Bill</td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                           id="electricity_bill_cost" name="electricity_bill_cost" placeholder="Cost per unit" disabled>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input billable-item-toggle" type="checkbox"
                                                           id="water_bill_selected" name="water_bill_selected" value="1"
                                                           data-target="water_bill_cost">
                                                </td>
                                                <td>Water Bill</td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                           id="water_bill_cost" name="water_bill_cost" placeholder="Cost per unit" disabled>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input billable-item-toggle" type="checkbox"
                                                           id="service_charges_selected" name="service_charges_selected" value="1"
                                                           data-target="service_charges_cost">
                                                </td>
                                                <td>Service Charges</td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                           id="service_charges_cost" name="service_charges_cost" placeholder="Cost per unit" disabled>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="text-muted border-bottom pb-2">Returns Combination</h6>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td style="width:32px;">
                                                    <input class="form-check-input" type="radio" name="returns_combination" id="rc_rent_separate"
                                                           value="rent_separate_rest_combined" {{ old('returns_combination') == 'rent_separate_rest_combined' ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="rc_rent_separate">Rent (Electricity Bill, Water Bill, Service Charges)</label></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input" type="radio" name="returns_combination" id="rc_each_separate"
                                                           value="each_separate" {{ old('returns_combination', 'each_separate') == 'each_separate' ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="rc_each_separate">Rent, Electricity Bill, Water Bill, Service Charges (each separate)</label></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input" type="radio" name="returns_combination" id="rc_all_combined"
                                                           value="all_combined" {{ old('returns_combination') == 'all_combined' ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="rc_all_combined">Rent, Electricity Bill, Water Bill, Service Charges (all combined)</label></td>
                                            </tr>
                                        </tbody>
                                    </table>
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
        // VATable change handler
        $('#vat_able').on('change', function() {
            const vatValue = $(this).val();
            
            if (vatValue === 'No') {
                // Disable both fields and clear values
                $('#vat_percentage').val('0').prop('disabled', true);
                $('#flat_rate_vat').val('0').prop('disabled', true);
            } else if (vatValue === 'Yes') {
                // Enable VAT % and disable Flat Rate VAT
                $('#vat_percentage').val('16').prop('disabled', false);
                $('#flat_rate_vat').val('0').prop('disabled', true);
            } else if (vatValue === 'Flat Rate') {
                // Disable VAT % and enable Flat Rate VAT
                $('#vat_percentage').val('0').prop('disabled', true);
                $('#flat_rate_vat').val('').prop('disabled', false);
            }
        });

        // Initialize VAT fields on page load based on current selection
        const initialVatValue = $('#vat_able').val();
        if (initialVatValue === 'No') {
            $('#vat_percentage').val('0').prop('disabled', true);
            $('#flat_rate_vat').val('0').prop('disabled', true);
        } else if (initialVatValue === 'Yes') {
            $('#vat_percentage').val('16').prop('disabled', false);
            $('#flat_rate_vat').val('0').prop('disabled', true);
        } else if (initialVatValue === 'Flat Rate') {
            $('#vat_percentage').val('0').prop('disabled', true);
            $('#flat_rate_vat').val('').prop('disabled', false);
        }

        // Form submission with spinner
        $('#serviceAgreementForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // VATable change handler
        $('#vat_able').on('change', function() {
            if ($(this).val() === 'No') {
                $('#vat_percentage').val('0');
            } else {
                $('#vat_percentage').val('16');
            }
        });

        // Load properties via AJAX when page loads (if client is preset)
        const clientIdInput = $('input[name="client_id"]').val();
        if (clientIdInput) {
            loadProperties(clientIdInput);
        } else {
            // Select2 AJAX search for client
            $('#client_id').select2({
                placeholder: 'Type to search client...',
                allowClear: true,
                minimumInputLength: 2,
                width: '100%',
                ajax: {
                    url: '{{ route("ajax.clients.search.select2") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) { return { results: data.results }; },
                    cache: true
                }
            });

            // Load properties when a client is selected
            $('#client_id').on('change', function() {
                const val = $(this).val();
                if (val) {
                    loadProperties(val);
                } else {
                    $('#property_id').html('<option value="">Select client first</option>').prop('disabled', true);
                }
            });
        }

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

        // Warn immediately if the selected property already has a current SLA,
        // rather than letting the team fill out the whole form only to be
        // rejected on submit. Hard block, not overridable — matches
        // SLAController::findCurrentSlaForProperty()'s server-side check exactly.
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            const $warning = $('#currentSlaWarning');
            const $saveButton = $('#saveButton');

            $warning.addClass('d-none').text('');
            $saveButton.prop('disabled', false);

            if (!propertyId) return;

            $.ajax({
                url: '{{ route("ajax.sla.check-current") }}',
                method: 'GET',
                data: { property_id: propertyId },
                success: function(response) {
                    if (response.exists) {
                        $warning.removeClass('d-none').text(
                            'This property already has a current SLA (' + response.reference_number +
                            ', ends ' + (response.date_end || 'never') +
                            ') — edit it instead, or wait until it ends to create a new one.'
                        );
                        $saveButton.prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking for a current SLA:', xhr.responseText);
                }
            });
        });

        // Calculate duration end date
        $('#date_start, #duration_years').on('change', function() {
            let startDate = $('#date_start').val();
            let duration = $('#duration_years').val();

            if (startDate && duration) {
                let start = new Date(startDate);
                start.setFullYear(start.getFullYear() + parseInt(duration));
                console.log('End Date:', start.toISOString().split('T')[0]);
            }
        });

        // Billable Items: enable/disable Cost per Unit alongside its checkbox
        $('.billable-item-toggle').on('change', function() {
            const targetId = $(this).data('target');
            if (!targetId) return; // Rent row has no cost field
            $('#' + targetId).prop('disabled', !this.checked).val(this.checked ? $('#' + targetId).val() : '');
        });

    });
</script>
@endsection
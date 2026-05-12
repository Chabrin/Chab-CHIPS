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
                            <a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : route('property.properties.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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
                                    <div class="col-md-6 mb-3">
                                        <label for="client_name" class="form-label">Client Name</label>
                                        <input type="text" class="form-control" id="client_name" value="{{ $client->names }}" readonly>
                                    </div>
                                @else
                                    <div class="col-md-6 mb-3">
                                        <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                        <select name="client_id" id="client_id" class="form-control" required style="width:100%;">
                                            <option value="">Type to search client...</option>
                                        </select>
                                        @error('client_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif

                                <div class="col-md-6 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">Loading properties...</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
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
                                    <label for="date_start" class="form-label">Date Start <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_start') is-invalid @enderror" 
                                           id="date_start" name="date_start" 
                                           value="{{ old('date_start', now()->format('Y-m-d')) }}" required>
                                    @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="duration_years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" class="form-control @error('duration_years') is-invalid @enderror" 
                                           id="duration_years" name="duration_years" required>
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
                            </div>

                            <!-- Invoice Client Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Invoice Client</h6>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="charge_client" class="form-label">Charge Client</label>
                                    <select class="form-select @error('charge_client') is-invalid @enderror" id="charge_client" name="charge_client">
                                        <option value="">Loading items...</option>
                                    </select>
                                    @error('charge_client')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="charge_amount" class="form-label">Amount</label>
                                    <input type="number" step="0.01" class="form-control @error('charge_amount') is-invalid @enderror" 
                                           id="charge_amount" name="charge_amount" 
                                           value="{{ old('charge_amount', '0.00') }}" placeholder="0.00">
                                    @error('charge_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-success btn-sm" id="addInvoiceCharge">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" id="removeInvoiceCharge">
                                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Billing Charges Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Billing Charges</h6>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="billing_item" class="form-label">Item</label>
                                    <select class="form-select @error('billing_item') is-invalid @enderror" id="billing_item" name="billing_item">
                                        <option value="">Loading items...</option>
                                    </select>
                                    @error('billing_item')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="cost_per_unit" class="form-label">Cost per Unit</label>
                                    <input type="number" step="0.01" class="form-control @error('cost_per_unit') is-invalid @enderror" 
                                           id="cost_per_unit" name="cost_per_unit">
                                    @error('cost_per_unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="commissionable" class="form-label">Commissionable</label>
                                    <select class="form-select @error('commissionable') is-invalid @enderror" id="commissionable" name="commissionable">
                                        <option value="No" {{ old('commissionable') == 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Yes" {{ old('commissionable') == 'Yes' ? 'selected' : '' }}>Yes</option>
                                    </select>
                                    @error('commissionable')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-success btn-sm" id="addBillingCharge">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" id="removeBillingCharge">
                                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- <!-- Returns Grouping Settings Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Returns Grouping Settings</h6>
                                </div>

                                <!-- Return Items Repeater entries will be added here -->

                                <div class="col-12 mb-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-success btn-sm" id="addReturnGroup">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span class="ms-1">Add Return Group</span>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" id="removeReturnGroup">
                                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                            <span class="ms-1">Remove Last</span>
                                        </button>
                                    </div>
                                </div>
                            </div> --}}

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let invoiceableItems = []; // Store all invoiceable items
        let returnGroupCounter = 0;

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

        // Load invoiceable items on page load
        loadInvoiceableItems();

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
                        
                        // Populate invoice client items dropdown (where feature = ["1"])
                        populateInvoiceClientItems();
                        
                        // Populate billing items dropdown (where is_bill = 'Yes')
                        populateBillingItems();
                        
                        // Add first return group automatically after items are loaded
                        // addReturnGroupEntry();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading invoiceable items:', xhr.responseText);
                }
            });
        }

        // Function to populate invoice client items dropdown
        function populateInvoiceClientItems() {
            $('#charge_client').empty().prop('disabled', false);
            $('#charge_client').append('<option value="">Select Charge Type</option>');
            
            // Filter items where feature array contains only "1"
            const invoiceClientItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                
                // Parse feature if it's a string (JSON), otherwise use as-is
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                
                // Check if feature array contains only "1"
                return featureArray.length === 1 && String(featureArray[0]) === '1';
            });
            
            console.log('Invoice client items:', invoiceClientItems);
            
            if (invoiceClientItems.length > 0) {
                invoiceClientItems.forEach(function(item) {
                    $('#charge_client').append(`<option value="${item.id}">${item.item_name}</option>`);
                });
            } else {
                $('#charge_client').append('<option value="">No invoice items available</option>');
            }
        }

        // Function to populate billing items dropdown
        function populateBillingItems() {
            $('#billing_item').empty().prop('disabled', false);
            $('#billing_item').append('<option value="">Select Item</option>');
            
            // Filter items where is_bill is 'Yes'
            const billingItems = invoiceableItems.filter(item => item.is_bill === 'Yes');
            console.log('Billing items:', billingItems);
            
            if (billingItems.length > 0) {
                billingItems.forEach(function(item) {
                    $('#billing_item').append(`<option value="${item.id}">${item.item_name}</option>`);
                });
            } else {
                $('#billing_item').append('<option value="">No billing items available</option>');
            }
        }

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

        // Dynamic Invoice Charges
        let invoiceChargeCounter = 1;
        $('#addInvoiceCharge').on('click', function() {
            // Generate invoice client items options
            let invoiceItemOptions = '<option value="">Select Charge Type</option>';
            const invoiceClientItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                return featureArray.length === 1 && String(featureArray[0]) === '1';
            });
            invoiceClientItems.forEach(function(item) {
                invoiceItemOptions += `<option value="${item.id}">${item.item_name}</option>`;
            });
        
            const chargeEntry = `
                <div class="col-12 invoice-charge-entry mb-3">
                    <div class="p-3 border rounded">
                        <div class="row">
                            <div class="col-md-5 mb-2">
                                <label class="form-label">Charge Type</label>
                                <select class="form-select" name="invoice_charges[${invoiceChargeCounter}][type]">
                                    ${invoiceItemOptions}
                                </select>
                            </div>
                            <div class="col-md-5 mb-2">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" class="form-control" name="invoice_charges[${invoiceChargeCounter}][amount]" placeholder="0.00">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm remove-invoice-charge w-100">
                                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#addInvoiceCharge').closest('.col-12').before(chargeEntry);
            invoiceChargeCounter++;
        });

        $(document).on('click', '.remove-invoice-charge', function() {
            $(this).closest('.invoice-charge-entry').remove();
        });

        $('#removeInvoiceCharge').on('click', function() {
            if ($('.invoice-charge-entry').length > 0) {
                $('.invoice-charge-entry').last().remove();
            }
        });

        // Dynamic Billing Charges
        let billingChargeCounter = 1;
        $('#addBillingCharge').on('click', function() {
            // Generate billing items options
            let billingItemOptions = '<option value="">Select Item</option>';
            const billingItems = invoiceableItems.filter(item => item.is_bill === 'Yes');
            billingItems.forEach(function(item) {
                billingItemOptions += `<option value="${item.id}">${item.item_name}</option>`;
            });

            const chargeEntry = `
                <div class="col-12 billing-charge-entry mb-3">
                    <div class="p-3 border rounded">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Item</label>
                                <select class="form-select" name="billing_charges[${billingChargeCounter}][item]">
                                    ${billingItemOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Cost per Unit</label>
                                <input type="number" step="0.01" class="form-control" name="billing_charges[${billingChargeCounter}][cost]">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Commissionable</label>
                                <select class="form-select" name="billing_charges[${billingChargeCounter}][commissionable]">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm remove-billing-charge w-100">
                                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#addBillingCharge').closest('.col-12').before(chargeEntry);
            billingChargeCounter++;
        });

        $(document).on('click', '.remove-billing-charge', function() {
            $(this).closest('.billing-charge-entry').remove();
        });

        $('#removeBillingCharge').on('click', function() {
            if ($('.billing-charge-entry').length > 0) {
                $('.billing-charge-entry').last().remove();
            }
        });

        // Function to add a return group entry
        function addReturnGroupEntry() {
            if (invoiceableItems.length === 0) {
                alert('Loading invoiceable items, please wait...');
                return;
            }
        
            // Filter items where feature array contains 1, 2, or 3
            const filteredItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                
                // Parse feature if it's a string (JSON), otherwise use as-is
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                
                // Check if feature array contains "1", "2", or "3" (as strings or numbers)
                return featureArray.some(function(feature) {
                    const featureStr = String(feature);
                    return featureStr === '1' || featureStr === '2' || featureStr === '3';
                });
            });
        
            console.log('Filtered return items:', filteredItems);
        
            // Generate return type dropdown
            let returnTypeOptions = '<option value="">Select Return Type</option>';
            @foreach($returnTypes as $type)
            returnTypeOptions += '<option value="{{ $type->id }}">{{ $type->type_name }}</option>';
            @endforeach
        
            // Generate invoiceable items checkboxes (split into 3 columns)
            let itemsPerColumn = Math.ceil(filteredItems.length / 3);
            let checkboxColumns = ['', '', ''];
            
            filteredItems.forEach(function(item, index) {
                let columnIndex = Math.floor(index / itemsPerColumn);
                if (columnIndex > 2) columnIndex = 2; // Ensure we don't exceed 3 columns
                
                checkboxColumns[columnIndex] += `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" 
                               name="return_groups[${returnGroupCounter}][items][]" 
                               value="${item.id}" 
                               id="return_item_${returnGroupCounter}_${item.id}"
                               data-commissionable="${item.commissionable}">
                        <label class="form-check-label" for="return_item_${returnGroupCounter}_${item.id}">
                            ${item.item_name}
                        </label>
                    </div>
                `;
            });
        
            const returnGroupEntry = `
                <div class="col-12 return-group-entry mb-3">
                    <div class="p-3 border rounded">
                        <div class="row mb-3">
                            <div class="col-md-10">
                                <label class="form-label">Return Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="return_groups[${returnGroupCounter}][return_type]" required>
                                    ${returnTypeOptions}
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm remove-return-group w-100">
                                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <label class="form-label fw-bold">Select Return Items <span class="text-danger">*</span></label>
                            </div>
                            <div class="col-md-4">
                                ${checkboxColumns[0]}
                            </div>
                            <div class="col-md-4">
                                ${checkboxColumns[1]}
                            </div>
                            <div class="col-md-4">
                                ${checkboxColumns[2]}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#addReturnGroup').closest('.col-12').before(returnGroupEntry);
            returnGroupCounter++;
        }

        // Dynamic Return Groups Repeater
        $('#addReturnGroup').on('click', function() {
            addReturnGroupEntry();
        });

        $(document).on('click', '.remove-return-group', function() {
            $(this).closest('.return-group-entry').remove();
        });

        $('#removeReturnGroup').on('click', function() {
            if ($('.return-group-entry').length > 0) {
                $('.return-group-entry').last().remove();
            }
        });
    });
</script>
@endsection
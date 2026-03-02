@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Service Agreement - {{ $sla->reference_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="serviceAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Update Agreement</span>
                            </button>
                            <a href="{{ route('property.clients.show', Crypt::encrypt($client->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Client</span>
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

                        <form action="{{ route('property.sla.update', Crypt::encrypt($sla->id)) }}" method="POST" enctype="multipart/form-data" id="serviceAgreementForm">
                            @csrf
                            @method('PUT')
                            
                            <!-- Hidden Fields -->
                            <input type="hidden" name="client_id" value="{{ $client->id }}">

                            <!-- Basic Information Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Agreement Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="client_name" class="form-label">Client <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" value="{{ $client->names }}" disabled>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                            <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                                <option value="">Select Property</option>
                                                @foreach($properties as $property)
                                                <option value="{{ $property->id }}" {{ $sla->property_id == $property->id ? 'selected' : '' }}>{{ $property->property_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('property_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="commission_percentage" class="form-label">Commission % <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control @error('commission_percentage') is-invalid @enderror" id="commission_percentage" name="commission_percentage" value="{{ old('commission_percentage', $sla->commission_percentage) }}" required>
                                            @error('commission_percentage')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="vat_able" class="form-label">VATable <span class="text-danger">*</span></label>
                                            <select class="form-select @error('vat_able') is-invalid @enderror" id="vat_able" name="vat_able" required>
                                                <option value="No" {{ $sla->vat == 'No' ? 'selected' : '' }}>No</option>
                                                <option value="Yes" {{ $sla->vat == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                <option value="Flat Rate" {{ $sla->vat == 'Flat Rate' ? 'selected' : '' }}>Flat Rate</option>
                                            </select>
                                            @error('vat_able')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="vat_percentage" class="form-label">VAT %</label>
                                            <input type="number" step="0.01" class="form-control @error('vat_percentage') is-invalid @enderror" id="vat_percentage" name="vat_percentage" value="{{ old('vat_percentage', $sla->vat_per) }}">
                                            @error('vat_percentage')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="flat_rate_vat" class="form-label">Flat Rate VAT</label>
                                            <input type="number" step="0.01" class="form-control @error('flat_rate_vat') is-invalid @enderror" id="flat_rate_vat" name="flat_rate_vat" value="{{ old('flat_rate_vat', $sla->flat_rate_vat) }}">
                                            @error('flat_rate_vat')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="auto_renewal" class="form-label">Auto Renewal <span class="text-danger">*</span></label>
                                            <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                                <option value="No" {{ $sla->auto_renewal == 'No' ? 'selected' : '' }}>No</option>
                                                <option value="Yes" {{ $sla->auto_renewal == 'Yes' ? 'selected' : '' }}>Yes</option>
                                            </select>
                                            @error('auto_renewal')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="payment_date" class="form-label">Payment Date (Day of Month) <span class="text-danger">*</span></label>
                                            <input type="number" min="1" max="31" class="form-control @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" value="{{ old('payment_date', $sla->landlord_payment_date) }}" required>
                                            @error('payment_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="date_start" class="form-label">Start Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('date_start') is-invalid @enderror" id="date_start" name="date_start" value="{{ old('date_start', $sla->date_start) }}" required>
                                            @error('date_start')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="duration_years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                                            <input type="number" min="1" class="form-control @error('duration_years') is-invalid @enderror" id="duration_years" name="duration_years" value="{{ old('duration_years', $sla->years) }}" required>
                                            @error('duration_years')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">SLA Documents</h6>
                                </div>
                                <div class="card-body">
                                    @if($sla->serviceAgreementDocuments && $sla->serviceAgreementDocuments->count() > 0)
                                    <div class="mb-3">
                                        <label class="form-label">Existing Documents</label>
                                        <div class="list-group">
                                            @foreach($sla->serviceAgreementDocuments as $document)
                                            <div class="list-group-item d-flex justify-content-between align-items-center existing-document" data-document-id="{{ $document->id }}">
                                                <span>{{ $document->title }}</span>
                                                <button type="button" class="btn btn-danger btn-sm delete-existing-document" data-document-id="{{ $document->id }}">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                </button>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="sla_documents" class="form-label">Upload New Documents</label>
                                            <input type="file" class="form-control @error('sla_documents.*') is-invalid @enderror" id="sla_documents" name="sla_documents[]" multiple accept=".pdf,.doc,.docx">
                                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 10MB each)</small>
                                            @error('sla_documents.*')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Charges Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Invoice Client For</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="invoiceChargesContainer">
                                        @foreach($sla->invoiceClients as $index => $invoiceCharge)
                                        <div class="col-12 invoice-charge-entry mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-2">
                                                            <label class="form-label">Charge Type</label>
                                                            <select class="form-select invoice-charge-type" name="invoice_charges[{{ $index }}][type]" required>
                                                                <option value="">Select Charge Type</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-5 mb-2">
                                                            <label class="form-label">Amount</label>
                                                            <input type="number" step="0.01" class="form-control" name="invoice_charges[{{ $index }}][amount]" value="{{ $invoiceCharge->item_amount }}" required>
                                                        </div>
                                                        <div class="col-md-1 mb-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-invoice-charge">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach

                                        @if($sla->invoiceClients->count() == 0)
                                        <div class="col-12 invoice-charge-entry mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-2">
                                                            <label class="form-label">Charge Type</label>
                                                            <select class="form-select invoice-charge-type" name="invoice_charges[0][type]" required>
                                                                <option value="">Select Charge Type</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-5 mb-2">
                                                            <label class="form-label">Amount</label>
                                                            <input type="number" step="0.01" class="form-control" name="invoice_charges[0][amount]" required>
                                                        </div>
                                                        <div class="col-md-1 mb-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-invoice-charge">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        <div class="col-12">
                                            <button type="button" class="btn btn-success btn-sm" id="addInvoiceCharge">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span> Add Charge
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Billing Charges Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Bill Tenant For</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="billingChargesContainer">
                                        @foreach($sla->billCharges as $index => $billCharge)
                                        <div class="col-12 billing-charge-entry mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4 mb-2">
                                                            <label class="form-label">Item</label>
                                                            <select class="form-select billing-item" name="billing_charges[{{ $index }}][item]" required>
                                                                <option value="">Select Item</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3 mb-2">
                                                            <label class="form-label">Cost per Unit</label>
                                                            <input type="number" step="0.01" class="form-control" name="billing_charges[{{ $index }}][cost]" value="{{ $billCharge->item_cost }}" required>
                                                        </div>
                                                        <div class="col-md-4 mb-2">
                                                            <label class="form-label">Commissionable</label>
                                                            <select class="form-select" name="billing_charges[{{ $index }}][commissionable]">
                                                                <option value="No" {{ $billCharge->commissionable == 'No' ? 'selected' : '' }}>No</option>
                                                                <option value="Yes" {{ $billCharge->commissionable == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-1 mb-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-billing-charge">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach

                                        @if($sla->billCharges->count() == 0)
                                        <div class="col-12 billing-charge-entry mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4 mb-2">
                                                            <label class="form-label">Item</label>
                                                            <select class="form-select billing-item" name="billing_charges[0][item]" required>
                                                                <option value="">Select Item</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3 mb-2">
                                                            <label class="form-label">Cost per Unit</label>
                                                            <input type="number" step="0.01" class="form-control" name="billing_charges[0][cost]" required>
                                                        </div>
                                                        <div class="col-md-4 mb-2">
                                                            <label class="form-label">Commissionable</label>
                                                            <select class="form-select" name="billing_charges[0][commissionable]">
                                                                <option value="No">No</option>
                                                                <option value="Yes">Yes</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-1 mb-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-billing-charge">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        <div class="col-12">
                                            <button type="button" class="btn btn-success btn-sm" id="addBillingCharge">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span> Add Charge
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Return Groups Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Return Groups</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="returnGroupsContainer">
                                        @foreach($sla->returnTypes as $index => $returnGroup)
                                        <div class="col-12 return-group-entry mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-11">
                                                            <label class="form-label">Return Type</label>
                                                            <select class="form-select return-type-select" name="return_groups[{{ $index }}][return_type]" required>
                                                                <option value="">Select Return Type</option>
                                                                @foreach($returnTypes as $type)
                                                                <option value="{{ $type->id }}" {{ $returnGroup->return_type_id == $type->id ? 'selected' : '' }}>{{ $type->type_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-1 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-return-group">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <label class="form-label">Select Items</label>
                                                            <div class="row return-items-checkboxes">
                                                                <!-- Will be populated by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach

                                        @if($sla->returnTypes->count() == 0)
                                        <div class="col-12 return-group-entry mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-11">
                                                            <label class="form-label">Return Type</label>
                                                            <select class="form-select return-type-select" name="return_groups[0][return_type]" required>
                                                                <option value="">Select Return Type</option>
                                                                @foreach($returnTypes as $type)
                                                                <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-1 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-return-group">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <label class="form-label">Select Items</label>
                                                            <div class="row return-items-checkboxes">
                                                                <!-- Will be populated by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        <div class="col-12">
                                            <button type="button" class="btn btn-success btn-sm" id="addReturnGroup">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span> Add Return Group
                                            </button>
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let invoiceableItems = [];
        let returnGroupCounter = {{ $sla->returnTypes->count() > 0 ? $sla->returnTypes->count() : 1 }};
        let invoiceChargeCounter = {{ $sla->invoiceClients->count() > 0 ? $sla->invoiceClients->count() : 1 }};
        let billingChargeCounter = {{ $sla->billCharges->count() > 0 ? $sla->billCharges->count() : 1 }};

        // Existing invoice charges data
        let existingInvoiceCharges = {
            @foreach($sla->invoiceClients as $charge)
            {{ $charge->item_id }}: {{ $charge->item_id }},
            @endforeach
        };
        
        // Existing billing charges data
        let existingBillingCharges = {
            @foreach($sla->billCharges as $charge)
            {{ $charge->item_id }}: {{ $charge->item_id }},
            @endforeach
        };

        // Existing return items data from invoiceableItems relationship
        let existingReturnItems = {};
        @foreach($sla->returnTypes as $returnTypeIndex => $returnType)
            existingReturnItems[{{ $returnTypeIndex }}] = [];
            @foreach($returnType->invoiceableItems as $index => $invoiceableItem)
                existingReturnItems[{{ $returnTypeIndex }}].push({{ $invoiceableItem->return_items }});
            @endforeach
        @endforeach

        // VATable change handler
        $('#vat_able').on('change', function() {
            const vatValue = $(this).val();
            
            if (vatValue === 'No') {
                $('#vat_percentage').val('0').prop('disabled', true);
                $('#flat_rate_vat').val('0').prop('disabled', true);
            } else if (vatValue === 'Yes') {
                $('#vat_percentage').val('16').prop('disabled', false);
                $('#flat_rate_vat').val('0').prop('disabled', true);
            } else if (vatValue === 'Flat Rate') {
                $('#vat_percentage').val('0').prop('disabled', true);
                $('#flat_rate_vat').val('').prop('disabled', false);
            }
        });

        // Initialize VAT fields on page load
        $('#vat_able').trigger('change');

        // Form submission with spinner
        $('#serviceAgreementForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Load invoiceable items on page load
        loadInvoiceableItems();

        // Function to load invoiceable items
        function loadInvoiceableItems() {
            $.ajax({
                url: '/invoiceable-items',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Invoiceable items loaded:', response);
                    invoiceableItems = response.items;
                    
                    // Populate existing invoice charges
                    $('.invoice-charge-type').each(function(index) {
                        populateInvoiceClientItems($(this), existingInvoiceCharges[Object.keys(existingInvoiceCharges)[index]]);
                    });

                    // Populate existing billing charges
                    $('.billing-item').each(function(index) {
                        populateBillingItems($(this), existingBillingCharges[Object.keys(existingBillingCharges)[index]]);
                    });

                    // Populate existing return groups
                    // $('.return-group-entry').each(function(index) {
                    //     populateReturnItemsCheckboxes($(this).find('.return-items-checkboxes'), existingReturnItems[index] || []);
                    // });

                    console.log('Existing Return Items:', existingReturnItems);
                    
                    // Inside the loadInvoiceableItems success callback, add:
                    console.log('Populating return groups...');
                    $('.return-group-entry').each(function(index) {
                        console.log('Processing return group index:', index, 'Selected items:', existingReturnItems[index]);
                        populateReturnItemsCheckboxes($(this).find('.return-items-checkboxes'), existingReturnItems[index] || []);
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading invoiceable items:', xhr.responseText);
                }
            });
        }

        // Function to populate invoice client items dropdown
        function populateInvoiceClientItems(selectElement, selectedId = null) {
            selectElement.empty();
            selectElement.append('<option value="">Select Charge Type</option>');
            
            const invoiceClientItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                return featureArray.length === 1 && String(featureArray[0]) === '1';
            });
            
            invoiceClientItems.forEach(function(item) {
                const selected = selectedId == item.id ? 'selected' : '';
                selectElement.append(`<option value="${item.id}" ${selected}>${item.item_name}</option>`);
            });
        }

        // Function to populate billing items dropdown
        function populateBillingItems(selectElement, selectedId = null) {
            selectElement.empty();
            selectElement.append('<option value="">Select Item</option>');
            
            const billingItems = invoiceableItems.filter(item => item.is_bill === 'Yes');
            
            billingItems.forEach(function(item) {
                const selected = selectedId == item.id ? 'selected' : '';
                selectElement.append(`<option value="${item.id}" ${selected}>${item.item_name}</option>`);
            });
        }

        // Function to populate return items checkboxes
        function populateReturnItemsCheckboxes(container, selectedItems = []) {
            container.empty();
            
            const filteredItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                return featureArray.some(function(feature) {
                    const featureStr = String(feature);
                    return featureStr === '1' || featureStr === '2' || featureStr === '3';
                });
            });

            let itemsPerColumn = Math.ceil(filteredItems.length / 3);
            let columns = ['', '', ''];
            
            filteredItems.forEach(function(item, index) {
                let columnIndex = Math.floor(index / itemsPerColumn);
                if (columnIndex > 2) columnIndex = 2;
                
                const groupIndex = container.closest('.return-group-entry').index();
                const checked = selectedItems.includes(item.id) ? 'checked' : '';
                
                columns[columnIndex] += `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="return_groups[${groupIndex}][items][]" value="${item.id}" id="return_item_${groupIndex}_${item.id}" ${checked}>
                        <label class="form-check-label" for="return_item_${groupIndex}_${item.id}">
                            ${item.item_name}
                        </label>
                    </div>
                `;
            });
            
            columns.forEach(function(columnHtml) {
                container.append(`<div class="col-md-4">${columnHtml}</div>`);
            });
        }

        // Dynamic Invoice Charges
        $('#addInvoiceCharge').on('click', function() {
            const chargeEntry = `
                <div class="col-12 invoice-charge-entry mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Charge Type</label>
                                    <select class="form-select invoice-charge-type" name="invoice_charges[${invoiceChargeCounter}][type]" required>
                                        <option value="">Select Charge Type</option>
                                    </select>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="invoice_charges[${invoiceChargeCounter}][amount]" required>
                                </div>
                                <div class="col-md-1 mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger btn-sm remove-invoice-charge">
                                        <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#addInvoiceCharge').closest('.col-12').before(chargeEntry);
            
            // Populate the newly added dropdown
            populateInvoiceClientItems($('.invoice-charge-type').last());
            
            invoiceChargeCounter++;
        });

        $(document).on('click', '.remove-invoice-charge', function() {
            $(this).closest('.invoice-charge-entry').remove();
        });

        // Dynamic Billing Charges
        $('#addBillingCharge').on('click', function() {
            const chargeEntry = `
                <div class="col-12 billing-charge-entry mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Item</label>
                                    <select class="form-select billing-item" name="billing_charges[${billingChargeCounter}][item]" required>
                                        <option value="">Select Item</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Cost per Unit</label>
                                    <input type="number" step="0.01" class="form-control" name="billing_charges[${billingChargeCounter}][cost]" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Commissionable</label>
                                    <select class="form-select" name="billing_charges[${billingChargeCounter}][commissionable]">
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger btn-sm remove-billing-charge">
                                        <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#addBillingCharge').closest('.col-12').before(chargeEntry);
            
            // Populate the newly added dropdown
            populateBillingItems($('.billing-item').last());
            
            billingChargeCounter++;
        });

        $(document).on('click', '.remove-billing-charge', function() {
            $(this).closest('.billing-charge-entry').remove();
        });

        // Dynamic Return Groups
        $('#addReturnGroup').on('click', function() {
            const returnGroupEntry = `
                <div class="col-12 return-group-entry mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-11">
                                    <label class="form-label">Return Type</label>
                                    <select class="form-select return-type-select" name="return_groups[${returnGroupCounter}][return_type]" required>
                                        <option value="">Select Return Type</option>
                                        @foreach($returnTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger btn-sm remove-return-group">
                                        <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">Select Items</label>
                                    <div class="row return-items-checkboxes">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#addReturnGroup').closest('.col-12').before(returnGroupEntry);
            
            // Populate the newly added checkboxes
            populateReturnItemsCheckboxes($('.return-group-entry').last().find('.return-items-checkboxes'));
            
            returnGroupCounter++;
        });

        $(document).on('click', '.remove-return-group', function() {
            $(this).closest('.return-group-entry').remove();
        });

        // Delete existing document AJAX
        $(document).on('click', '.delete-existing-document', function() {
            if (!confirm('Are you sure you want to delete this document?')) {
                return;
            }

            const documentId = $(this).data('document-id');
            const documentItem = $(this).closest('.existing-document');

            $.ajax({
                url: `/property/sla/documents/${documentId}`,
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    documentItem.fadeOut(300, function() {
                        $(this).remove();
                    });
                    toastr.success('Document deleted successfully');
                },
                error: function(xhr) {
                    console.error('Error deleting document:', xhr);
                    toastr.error('Failed to delete document');
                }
            });
        });
    });
</script>
@endsection
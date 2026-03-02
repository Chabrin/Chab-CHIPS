@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Repair & Maintenance Request</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="repairMaintenanceForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="#" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
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

                        <form action="#" method="POST" id="repairMaintenanceForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Repairs & Maintenance Requests Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Repairs & Maintenance Requests</h6>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select @error('client_id') is-invalid @enderror" id="client_id" name="client_id" required>
                                        <option value="">Please select</option>
                                        @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                            {{ $client->full_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('client_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">Please select</option>
                                        @foreach($properties as $property)
                                        <option value="{{ $property->id }}" {{ old('property_id') == $property->id ? 'selected' : '' }}>
                                            {{ $property->property_code }} - {{ $property->property_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="visited_datetime" class="form-label">Visited Date/Time <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('visited_datetime') is-invalid @enderror" 
                                           id="visited_datetime" name="visited_datetime" 
                                           value="{{ old('visited_datetime', '2025-12-31') }}" required>
                                    @error('visited_datetime')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Items Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Items</h6>
                                </div>

                                <div class="col-12 mb-3 text-end">
                                    <button type="button" class="btn btn-success btn-sm" id="addRepairItem">
                                        <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                        Add Item
                                    </button>
                                </div>

                                <div id="repairItemsContainer">
                                    <div class="repair-item-entry mb-4 p-3 border rounded position-relative">
                                        <!-- Add/Remove buttons in top right corner -->
                                        <div class="position-absolute top-0 end-0 mt-2 me-2">
                                            <button type="button" class="btn btn-success btn-sm add-item-inline d-none me-1">
                                                <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm remove-repair-item d-none">
                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                            </button>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label for="unit_0" class="form-label">Unit</label>
                                                <select class="form-select" id="unit_0" name="repair_items[0][unit_id]">
                                                    <option value="">Please select</option>
                                                    @foreach($units as $unit)
                                                    <option value="{{ $unit->id }}">{{ $unit->unit_number }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label for="element_0" class="form-label">Element <span class="text-danger">*</span></label>
                                                <select class="form-select" id="element_0" name="repair_items[0][element]" required>
                                                    <option value="">Please select</option>
                                                    <option value="Plumbing">Plumbing</option>
                                                    <option value="Electrical">Electrical</option>
                                                    <option value="HVAC">HVAC</option>
                                                    <option value="Painting">Painting</option>
                                                    <option value="Flooring">Flooring</option>
                                                    <option value="Roofing">Roofing</option>
                                                    <option value="Windows">Windows</option>
                                                    <option value="Doors">Doors</option>
                                                    <option value="Walls">Walls</option>
                                                    <option value="Appliances">Appliances</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label for="repair_type_0" class="form-label">Repair Type <span class="text-danger">*</span></label>
                                                <select class="form-select" id="repair_type_0" name="repair_items[0][repair_type]" required>
                                                    <option value="">Please select</option>
                                                    <option value="Emergency">Emergency</option>
                                                    <option value="Urgent">Urgent</option>
                                                    <option value="Routine">Routine</option>
                                                    <option value="Preventive">Preventive</option>
                                                    <option value="Cosmetic">Cosmetic</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label for="amount_0" class="form-label">Amount</label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       id="amount_0" name="repair_items[0][amount]" 
                                                       value="0.00" placeholder="0.00">
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="remarks_0" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks_0" name="repair_items[0][remarks]" 
                                                          rows="2" placeholder="Enter remarks or description..."></textarea>
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
        // Form submission with spinner
        $('#repairMaintenanceForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Client selection handler - filter properties
        $('#client_id').on('change', function() {
            let clientId = $(this).val();
            if (clientId) {
                // AJAX call to get properties for selected client
                $.ajax({
                    url: '/api/clients/' + clientId + '/properties',
                    method: 'GET',
                    success: function(response) {
                        $('#property_id').empty().append('<option value="">Please select</option>');
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}">${property.property_code} - ${property.property_name}</option>`);
                        });
                    }
                });
            }
        });

        // Property selection handler - filter units
        $('#property_id').on('change', function() {
            let propertyId = $(this).val();
            if (propertyId) {
                // AJAX call to get units for selected property
                $.ajax({
                    url: '/api/properties/' + propertyId + '/units',
                    method: 'GET',
                    success: function(response) {
                        $('.repair-item-entry select[name*="[unit_id]"]').each(function() {
                            let currentValue = $(this).val();
                            $(this).empty().append('<option value="">Please select</option>');
                            response.units.forEach(function(unit) {
                                let selected = currentValue == unit.id ? 'selected' : '';
                                $(this).append(`<option value="${unit.id}" ${selected}>${unit.unit_number}</option>`);
                            }.bind(this));
                        });
                    }
                });
            }
        });

        // Dynamic Repair Items
        let itemCounter = 1;
        $('#addRepairItem').on('click', function() {
            const itemEntry = `
                <div class="repair-item-entry mb-4 p-3 border rounded position-relative">
                    <div class="position-absolute top-0 end-0 mt-2 me-2">
                        <button type="button" class="btn btn-danger btn-sm remove-repair-item">
                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="unit_${itemCounter}" class="form-label">Unit</label>
                            <select class="form-select" id="unit_${itemCounter}" name="repair_items[${itemCounter}][unit_id]">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="element_${itemCounter}" class="form-label">Element <span class="text-danger">*</span></label>
                            <select class="form-select" id="element_${itemCounter}" name="repair_items[${itemCounter}][element]" required>
                                <option value="">Please select</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Electrical">Electrical</option>
                                <option value="HVAC">HVAC</option>
                                <option value="Painting">Painting</option>
                                <option value="Flooring">Flooring</option>
                                <option value="Roofing">Roofing</option>
                                <option value="Windows">Windows</option>
                                <option value="Doors">Doors</option>
                                <option value="Walls">Walls</option>
                                <option value="Appliances">Appliances</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="repair_type_${itemCounter}" class="form-label">Repair Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="repair_type_${itemCounter}" name="repair_items[${itemCounter}][repair_type]" required>
                                <option value="">Please select</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Routine">Routine</option>
                                <option value="Preventive">Preventive</option>
                                <option value="Cosmetic">Cosmetic</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="amount_${itemCounter}" class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" 
                                   id="amount_${itemCounter}" name="repair_items[${itemCounter}][amount]" 
                                   value="0.00" placeholder="0.00">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="remarks_${itemCounter}" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks_${itemCounter}" name="repair_items[${itemCounter}][remarks]" 
                                      rows="2" placeholder="Enter remarks or description..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('#repairItemsContainer').append(itemEntry);
            
            // Populate units dropdown for new entry
            let propertyId = $('#property_id').val();
            if (propertyId) {
                $.ajax({
                    url: '/api/properties/' + propertyId + '/units',
                    method: 'GET',
                    success: function(response) {
                        $(`#unit_${itemCounter}`).empty().append('<option value="">Please select</option>');
                        response.units.forEach(function(unit) {
                            $(`#unit_${itemCounter}`).append(`<option value="${unit.id}">${unit.unit_number}</option>`);
                        });
                    }
                });
            }
            
            itemCounter++;
            updateRemoveButtons('.repair-item-entry', '.remove-repair-item');
        });

        $(document).on('click', '.remove-repair-item', function() {
            $(this).closest('.repair-item-entry').remove();
            updateRemoveButtons('.repair-item-entry', '.remove-repair-item');
        });

        // Helper function to show/hide remove buttons
        function updateRemoveButtons(containerClass, buttonClass) {
            if ($(containerClass).length > 1) {
                $(buttonClass).removeClass('d-none');
            } else {
                $(buttonClass).addClass('d-none');
            }
        }

        // Calculate total amount
        $(document).on('input', 'input[name*="[amount]"]', function() {
            let total = 0;
            $('input[name*="[amount]"]').each(function() {
                let value = parseFloat($(this).val()) || 0;
                total += value;
            });
            console.log('Total Amount:', total.toFixed(2));
        });

        // Validate repair type and highlight emergency/urgent
        $(document).on('change', 'select[name*="[repair_type]"]', function() {
            let repairType = $(this).val();
            let container = $(this).closest('.repair-item-entry');
            
            // Remove all priority classes
            container.removeClass('border-danger border-warning border-info');
            
            // Add appropriate border color based on repair type
            if (repairType === 'Emergency') {
                container.addClass('border-danger');
                container.css('border-width', '2px');
            } else if (repairType === 'Urgent') {
                container.addClass('border-warning');
                container.css('border-width', '2px');
            } else {
                container.css('border-width', '1px');
            }
        });
    });
</script>
@endsection
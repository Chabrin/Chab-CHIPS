@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Property Inspection</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="propertyInspectionForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ route('property.inspections.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('property.inspection.store') }}" method="POST" id="propertyInspectionForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Properties Inspections Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Properties Inspections</h6>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="zone" class="form-label">Zone</label>
                                    <select class="form-select select2" id="zone" name="zone">
                                        <option value="">Please select</option>
                                        @foreach ( $zones as  $zone )
                                        <option value="{{ $zone->id }}" {{ old('zone') == $zone->id ? 'selected' : '' }}>
                                            {{ $zone->zone_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('zone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select select2" id="property_id" name="property_id" required>
                                        <option value="">Please select</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="visited_datetime" class="form-label">Visited Date/Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('visited_datetime') is-invalid @enderror" 
                                           id="visited_datetime" name="visited_datetime" 
                                           value="{{ old('visited_datetime', now()->format('Y-m-d\TH:i')) }}" required>
                                    @error('visited_datetime')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="left_datetime" class="form-label">Left Date/Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('left_datetime') is-invalid @enderror" 
                                           id="left_datetime" name="left_datetime" 
                                           value="{{ old('left_datetime', now()->format('Y-m-d\TH:i')) }}" required>
                                    @error('left_datetime')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Units Statuses Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Units Statuses</h6>
                                </div>

                                <div class="col-12 mb-3 text-end">
                                    <button type="button" class="btn btn-success btn-sm" id="addUnitStatus">
                                        <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                        Add Unit Status
                                    </button>
                                </div>

                                <div id="unitStatusesContainer">
                                    <div class="unit-status-entry mb-4 p-3 border rounded position-relative">
                                        <div class="position-absolute top-0 end-0 mt-2 me-2">
                                            <button type="button" class="btn btn-danger btn-sm remove-unit-status d-none">
                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                            </button>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="unit_0" class="form-label">Unit <span class="text-danger">*</span></label>
                                                <select class="form-select select2" id="unit_0" name="unit_statuses[0][unit_id]" required>
                                                    <option value="">Please select</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="previous_status_0" class="form-label">Previous Status</label>
                                                <select class="form-select select2" id="previous_status_0" name="unit_statuses[0][previous_status]">
                                                    <option value="">Please select</option>
                                                    <option value="Occupied">Occupied</option>
                                                    <option value="Vacant">Vacant</option>
                                                    <option value="Under Maintenance">Under Maintenance</option>
                                                    <option value="Reserved">Reserved</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="new_status_0" class="form-label">New Status <span class="text-danger">*</span></label>
                                                <select class="form-select select2" id="new_status_0" name="unit_statuses[0][new_status]" required>
                                                    <option value="">Please select</option>
                                                    <option value="Occupied">Occupied</option>
                                                    <option value="Vacant">Vacant</option>
                                                    <option value="Under Maintenance">Under Maintenance</option>
                                                    <option value="Reserved">Reserved</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="date_0" class="form-label">Date</label>
                                                <input type="date" class="form-control" 
                                                       id="date_0" name="unit_statuses[0][date]" 
                                                       value="{{ old('unit_statuses.0.date', now()->format('Y-m-d')) }}">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="remarks_0" class="form-label">Remarks <span class="text-danger">*</span></label>
                                                <select class="form-select select2" id="remarks_0" name="unit_statuses[0][remarks]" required>
                                                    <option value="">Please select</option>
                                                    <option value="Good Condition">Good Condition</option>
                                                    <option value="Needs Repair">Needs Repair</option>
                                                    <option value="Clean">Clean</option>
                                                    <option value="Dirty">Dirty</option>
                                                    <option value="Damaged">Damaged</option>
                                                    <option value="Perfect">Perfect</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="unit_photos_0" class="form-label">Unit Photos</label>
                                                <input type="file" class="form-control" 
                                                       id="unit_photos_0" name="unit_statuses[0][photos][]" 
                                                       multiple accept="image/*">
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="document.getElementById('unit_photos_0').click()">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">add_photo_alternate</span>
                                                    Add files
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 on all select elements
        function initializeSelect2() {
            $('.select2').each(function() {
                if ($(this).hasClass("select2-hidden-accessible")) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    placeholder: $(this).data('placeholder') || 'Please select',
                    allowClear: true
                });
            });
        }

        // Initialize Select2 on page load
        initializeSelect2();

        // Form submission with spinner
        $('#propertyInspectionForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Zone selection handler - load properties for selected zone
        $('#zone').on('change', function() {
            const zoneId = $(this).val();
            
            if (zoneId) {
                loadPropertiesForZone(zoneId);
            } else {
                // Reset property and units
                let $property_id = $('#property_id');
                $property_id.empty().append('<option value="">Please select</option>');
                if ($property_id.hasClass("select2-hidden-accessible")) $property_id.select2('destroy');
                $property_id.select2({ placeholder: 'Please select', allowClear: true });
                clearAllUnitDropdowns();
            }
        });

        // Function to load properties for the selected zone
        function loadPropertiesForZone(zoneId) {
            let $property_id = $('#property_id');
            $property_id.prop('disabled', true).empty().append('<option value="">Loading properties...</option>');
            if ($property_id.hasClass("select2-hidden-accessible")) $property_id.select2('destroy');
            $property_id.select2({ placeholder: 'Loading properties...', allowClear: true });
            
            $.ajax({
                url: `/zone/${zoneId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Properties loaded:', response);
                    $property_id.empty().prop('disabled', false);
                    $property_id.append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $property_id.append(`<option value="${property.id}">${property.reference_number} - ${property.property_name}</option>`);
                        });
                    } else {
                        $property_id.append('<option value="">No properties found for this zone</option>');
                    }
                    if ($property_id.hasClass("select2-hidden-accessible")) $property_id.select2('destroy');
                    $property_id.select2({ placeholder: 'Please select', allowClear: true });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties:', xhr.responseText);
                    $property_id.empty().prop('disabled', false);
                    $property_id.append('<option value="">Error loading properties</option>');
                    if ($property_id.hasClass("select2-hidden-accessible")) $property_id.select2('destroy');
                    $property_id.select2({ placeholder: 'Error loading properties', allowClear: true });
                }
            });
        }

        // Property selection handler - load units for selected property
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            
            if (propertyId) {
                loadUnitsForProperty(propertyId);
            } else {
                clearAllUnitDropdowns();
            }
        });

        // Function to load units for the selected property
        function loadUnitsForProperty(propertyId) {
            $.ajax({
                url: `/property/${propertyId}/units`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Units loaded:', response);
                    
                    // Update all unit dropdowns
                    $('.unit-status-entry select[name*="[unit_id]"]').each(function() {
                        let currentValue = $(this).val();
                        let $select = $(this);
                        
                        $select.empty().append('<option value="">Please select</option>');
                        
                        if (response.units && response.units.length > 0) {
                            response.units.forEach(function(unit) {
                                let selected = currentValue == unit.id ? 'selected' : '';
                                $select.append(`<option value="${unit.id}" data-status="${unit.status}" ${selected}>${unit.unit_number} - ${unit.status}</option>`);
                            });
                        } else {
                            $select.append('<option value="">No units found</option>');
                        }
                        
                        // Reinitialize Select2
                        if ($select.hasClass("select2-hidden-accessible")) $select.select2('destroy');
                        $select.select2({ placeholder: 'Please select', allowClear: true });
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading units:', xhr.responseText);
                    clearAllUnitDropdowns();
                }
            });
        }

        // Clear all unit dropdowns
        function clearAllUnitDropdowns() {
            $('.unit-status-entry select[name*="[unit_id]"]').each(function() {
                let $select = $(this);
                $select.empty().append('<option value="">Please select property first</option>');
                if ($select.hasClass("select2-hidden-accessible")) $select.select2('destroy');
                $select.select2({ placeholder: 'Please select property first', allowClear: true });
            });
        }

        // Auto-populate previous status when unit is selected
        $(document).on('change', 'select[name*="[unit_id]"]', function() {
            let unitId = $(this).val();
            let container = $(this).closest('.unit-status-entry');
            
            if (unitId) {
                // Get the status from data attribute
                let selectedOption = $(this).find('option:selected');
                let status = selectedOption.data('status');
                
                if (status) {
                    let $prevStatus = container.find('select[name*="[previous_status]"]');
                    $prevStatus.val(status);
                    if ($prevStatus.hasClass("select2-hidden-accessible")) $prevStatus.select2('destroy');
                    $prevStatus.select2({ placeholder: 'Please select', allowClear: true });
                }
            }
        });

        // Dynamic Unit Statuses
        let unitCounter = 1;
        $('#addUnitStatus').on('click', function() {
            const unitEntry = `
                <div class="unit-status-entry mb-4 p-3 border rounded position-relative">
                    <div class="position-absolute top-0 end-0 mt-2 me-2">
                        <button type="button" class="btn btn-danger btn-sm remove-unit-status">
                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="unit_${unitCounter}" class="form-label">Unit <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="unit_${unitCounter}" name="unit_statuses[${unitCounter}][unit_id]" required>
                                <option value="">Please select property first</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="previous_status_${unitCounter}" class="form-label">Previous Status</label>
                            <select class="form-select select2" id="previous_status_${unitCounter}" name="unit_statuses[${unitCounter}][previous_status]">
                                <option value="">Please select</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Vacant">Vacant</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Reserved">Reserved</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="new_status_${unitCounter}" class="form-label">New Status <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="new_status_${unitCounter}" name="unit_statuses[${unitCounter}][new_status]" required>
                                <option value="">Please select</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Vacant">Vacant</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Reserved">Reserved</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_${unitCounter}" class="form-label">Date</label>
                            <input type="date" class="form-control" 
                                   id="date_${unitCounter}" name="unit_statuses[${unitCounter}][date]" 
                                   value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="remarks_${unitCounter}" class="form-label">Remarks <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="remarks_${unitCounter}" name="unit_statuses[${unitCounter}][remarks]" required>
                                <option value="">Please select</option>
                                <option value="Good Condition">Good Condition</option>
                                <option value="Needs Repair">Needs Repair</option>
                                <option value="Clean">Clean</option>
                                <option value="Dirty">Dirty</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Perfect">Perfect</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unit_photos_${unitCounter}" class="form-label">Unit Photos</label>
                            <input type="file" class="form-control" 
                                   id="unit_photos_${unitCounter}" name="unit_statuses[${unitCounter}][photos][]" 
                                   multiple accept="image/*">
                        </div>
                    </div>
                </div>
            `;
            $('#unitStatusesContainer').append(unitEntry);
            
            // Initialize Select2 for new elements
            initializeSelect2();
            
            // If property is already selected, populate units for new entry
            let propertyId = $('#property_id').val();
            if (propertyId) {
                loadUnitsForProperty(propertyId);
            }
            
            unitCounter++;
            updateRemoveButtons('.unit-status-entry', '.remove-unit-status');
        });

        $(document).on('click', '.remove-unit-status', function() {
            $(this).closest('.unit-status-entry').remove();
            updateRemoveButtons('.unit-status-entry', '.remove-unit-status');
        });

        // Helper function to show/hide remove buttons
        function updateRemoveButtons(containerClass, buttonClass) {
            if ($(containerClass).length > 1) {
                $(buttonClass).removeClass('d-none');
            } else {
                $(buttonClass).addClass('d-none');
            }
        }
    });
</script>


@endsection
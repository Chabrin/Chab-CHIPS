@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Property Inspection</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="propertyInspectionForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Update</span>
                            </button>
                            <a href="{{ route('property.inspection.show', Crypt::encrypt($inspection->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('property.inspection.update', Crypt::encrypt($inspection->id)) }}" method="POST" id="propertyInspectionForm" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <!-- Properties Inspections Section -->
                            <div class="row mb-4">
                                <div class="col-md-12 mb-3">
                                    <h5 class="border-bottom pb-2">Inspection Details</h5>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="zone" class="form-label">Zone <span class="text-danger">*</span></label>
                                    <select class="form-select select2 @error('zone') is-invalid @enderror" id="zone" name="zone" data-placeholder="Please select">
                                        <option value="">Please select</option>
                                        @foreach($zones as $zone)
                                            <option value="{{ $zone->id }}" {{ old('zone', $inspection->zone_id ?? $inspection->property->zone_id) == $zone->id ? 'selected' : '' }}>
                                                {{ $zone->zone_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('zone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select select2 @error('property_id') is-invalid @enderror" id="property_id" name="property_id" data-placeholder="Please select">
                                        <option value="">Please select</option>
                                        <option value="{{ $inspection->property_id }}" selected>
                                            {{ $inspection->property->reference_number }} - {{ $inspection->property->property_name }}
                                        </option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="visited_datetime" class="form-label">Visited Date & Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('visited_datetime') is-invalid @enderror" id="visited_datetime" name="visited_datetime" value="{{ old('visited_datetime', \Carbon\Carbon::parse($inspection->visited_date_time)->format('Y-m-d\TH:i')) }}" required>
                                    @error('visited_datetime')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="left_datetime" class="form-label">Left Date & Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('left_datetime') is-invalid @enderror" id="left_datetime" name="left_datetime" value="{{ old('left_datetime', \Carbon\Carbon::parse($inspection->left_date_time)->format('Y-m-d\TH:i')) }}" required>
                                    @error('left_datetime')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Units Statuses Section -->
                            <div class="row mb-4">
                                <div class="col-md-12 mb-3">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <h5 class="mb-0">Units Status</h5>
                                        <button type="button" class="btn btn-success btn-sm" id="addUnitStatus">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            Add Unit
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-12" id="unitStatusesContainer">
                                    @foreach($inspection->units as $index => $inspectionUnit)
                                    <div class="unit-status-entry mb-4 p-3 border rounded position-relative">
                                        <div class="position-absolute top-0 end-0 mt-2 me-2">
                                            <button type="button" class="btn btn-danger btn-sm remove-unit-status {{ $index === 0 ? 'd-none' : '' }}">
                                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                            </button>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Unit <span class="text-danger">*</span></label>
                                                <select class="form-select select2" name="unit_statuses[{{ $index }}][unit_id]" required>
                                                    <option value="">Please select</option>
                                                    <option value="{{ $inspectionUnit->unit_id }}" selected>
                                                        {{ $inspectionUnit->unit->unit_number }} - {{ $inspectionUnit->unit->unit_name ?? 'N/A' }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Previous Status</label>
                                                <select class="form-select select2" name="unit_statuses[{{ $index }}][previous_status]">
                                                    <option value="">Please select</option>
                                                    <option value="Vacant" {{ $inspectionUnit->previous_status_id == 31 ? 'selected' : '' }}>Vacant</option>
                                                    <option value="Occupied" {{ $inspectionUnit->previous_status_id == 32 ? 'selected' : '' }}>Occupied</option>
                                                    <option value="Under Maintenance" {{ $inspectionUnit->previous_status_id == 33 ? 'selected' : '' }}>Under Maintenance</option>
                                                    <option value="Reserved" {{ $inspectionUnit->previous_status_id == 34 ? 'selected' : '' }}>Reserved</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">New Status <span class="text-danger">*</span></label>
                                                <select class="form-select select2" name="unit_statuses[{{ $index }}][new_status]" required>
                                                    <option value="">Please select</option>
                                                    <option value="Vacant" {{ $inspectionUnit->unit_status_id == 31 ? 'selected' : '' }}>Vacant</option>
                                                    <option value="Occupied" {{ $inspectionUnit->unit_status_id == 32 ? 'selected' : '' }}>Occupied</option>
                                                    <option value="Under Maintenance" {{ $inspectionUnit->unit_status_id == 33 ? 'selected' : '' }}>Under Maintenance</option>
                                                    <option value="Reserved" {{ $inspectionUnit->unit_status_id == 34 ? 'selected' : '' }}>Reserved</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Event Date</label>
                                                <input type="date" class="form-control" name="unit_statuses[{{ $index }}][date]" value="{{ \Carbon\Carbon::parse($inspectionUnit->event_date)->format('Y-m-d') }}">
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="unit_statuses[{{ $index }}][remarks]" rows="2" required>{{ $inspectionUnit->remarks }}</textarea>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Unit Photos (Optional - New photos will replace existing ones)</label>
                                                <input type="file" class="form-control" name="unit_statuses[{{ $index }}][photos][]" multiple accept="image/*">
                                                <small class="text-muted">You can select multiple images. Accepted formats: JPG, JPEG, PNG (Max 5MB each)</small>
                                                
                                                @if($inspectionUnit->pictures->count() > 0)
                                                <div class="mt-2">
                                                    <small class="text-muted">Current photos ({{ $inspectionUnit->pictures->count() }}):</small>
                                                    <div class="row g-2 mt-1">
                                                        @foreach($inspectionUnit->pictures as $picture)
                                                        <div class="col-md-2">
                                                            <a href="{{ asset('storage/' . $picture->unit_photos) }}" target="_blank">
                                                                <img src="{{ asset('storage/' . $picture->unit_photos) }}" 
                                                                     class="img-thumbnail" 
                                                                     style="width: 100%; height: 80px; object-fit: cover;" 
                                                                     alt="Unit Photo">
                                                            </a>
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
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
        let unitCounter = {{ $inspection->units->count() }};
        
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
                    $property_id.empty().prop('disabled', false);
                    $property_id.append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            const selected = property.id == {{ $inspection->property_id }} ? 'selected' : '';
                            $property_id.append(`<option value="${property.id}" ${selected}>${property.reference_number} - ${property.property_name}</option>`);
                        });
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
                url: `/property/${propertyId}/all_units`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('.unit-status-entry select[name*="[unit_id]"]').each(function() {
                        let currentValue = $(this).val();
                        let $select = $(this);
                        
                        $select.empty().append('<option value="">Please select</option>');
                        
                        if (response.units && response.units.length > 0) {
                            response.units.forEach(function(unit) {
                                const selected = unit.id == currentValue ? 'selected' : '';
                                $select.append(`<option value="${unit.id}" data-status="${unit.status}" ${selected}>${unit.unit_number} - ${unit.status}</option>`);
                            });
                        }
                        
                        if ($select.hasClass("select2-hidden-accessible")) $select.select2('destroy');
                        $select.select2({ placeholder: 'Please select', allowClear: true });
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading units:', xhr.responseText);
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
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select class="form-select select2" name="unit_statuses[${unitCounter}][unit_id]" required>
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Previous Status</label>
                            <select class="form-select select2" name="unit_statuses[${unitCounter}][previous_status]">
                                <option value="">Please select</option>
                                <option value="Vacant">Vacant</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Reserved">Reserved</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">New Status <span class="text-danger">*</span></label>
                            <select class="form-select select2" name="unit_statuses[${unitCounter}][new_status]" required>
                                <option value="">Please select</option>
                                <option value="Vacant">Vacant</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Reserved">Reserved</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Date</label>
                            <input type="date" class="form-control" name="unit_statuses[${unitCounter}][date]">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="unit_statuses[${unitCounter}][remarks]" rows="2" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Unit Photos (Optional)</label>
                            <input type="file" class="form-control" name="unit_statuses[${unitCounter}][photos][]" multiple accept="image/*">
                            <small class="text-muted">You can select multiple images. Accepted formats: JPG, JPEG, PNG (Max 5MB each)</small>
                        </div>
                    </div>
                </div>
            `;
            $('#unitStatusesContainer').append(unitEntry);
            
            initializeSelect2();
            
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

        // Load units for current property on page load
        let propertyId = $('#property_id').val();
        if (propertyId) {
            loadUnitsForProperty(propertyId);
        }
    });
</script>

@endsection

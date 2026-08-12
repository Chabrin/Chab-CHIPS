@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Edit Repair & Maintenance Request - {{ $repairRequest->ref_number }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="repairRequestForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Update</span>
                            </button>
                            <a href="{{ route('property.repairs.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                    <form action="{{ route('property.repairs.update', Crypt::encrypt($repairRequest->id)) }}" 
                          method="POST" id="repairRequestForm" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <!-- Client -->
                            <div class="col-md-4 mb-3">
                                <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                <select name="client_id" id="client_id" class="form-select" required>
                                    <option value="{{ $repairRequest->client_id }}" selected>
                                        {{ $repairRequest->client->names }}
                                    </option>
                                </select>
                                @error('client_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Property -->
                            <div class="col-md-4 mb-3">
                                <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                <select name="property_id" id="property_id" class="form-select" required data-selected="{{ $repairRequest->property_id }}">
                                    <option value="">Select Property</option>
                                </select>
                                @error('property_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Visited Date & Time -->
                            <div class="col-md-4 mb-3">
                                <label for="visited_date_time" class="form-label">Visited Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="visited_date_time" id="visited_date_time" 
                                       class="form-control" 
                                       value="{{ \Carbon\Carbon::parse($repairRequest->visited_date_time)->format('Y-m-d\TH:i') }}" 
                                       required>
                                @error('visited_date_time')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Repair Items Section -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Repair Items</h5>
                            <button type="button" class="btn btn-sm btn-success" id="addItemBtn">
                                <span class="material-symbols-outlined align-middle">add</span>
                                Add Item
                            </button>
                        </div>

                        <div id="repairItemsContainer">
                            @foreach($items as $index => $item)
                            <div class="card mb-3 repair-item">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="mb-0">Item <span class="item-number">#{{ $index + 1 }}</span></h6>
                                        <button type="button" class="btn btn-sm btn-danger remove-item-btn">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </div>
                                    <div class="row">
                                        <!-- Repair Scope -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Repair Scope <span class="text-danger">*</span></label>
                                            <select name="items[{{ $index }}][repair_scope]" class="form-select scope-select" required>
                                                <option value="">Select Scope</option>
                                                <option value="general" {{ ($item->repair_scope ?? ($item->unit_id ? 'unit_specific' : 'general')) === 'general' ? 'selected' : '' }}>General (Entire Property)</option>
                                                <option value="unit_specific" {{ ($item->repair_scope ?? ($item->unit_id ? 'unit_specific' : 'general')) === 'unit_specific' ? 'selected' : '' }}>Unit Specific</option>
                                            </select>
                                        </div>

                                        <!-- Unit (shown only when Unit Specific) -->
                                        <div class="col-md-3 mb-3 unit-field" style="{{ ($item->repair_scope ?? ($item->unit_id ? 'unit_specific' : 'general')) === 'unit_specific' ? '' : 'display:none;' }}">
                                            <label class="form-label">Unit</label>
                                            <select name="items[{{ $index }}][unit_id]" class="form-select unit-select" data-selected="{{ $item->unit_id }}">
                                                <option value="">Select Unit</option>
                                            </select>
                                        </div>

                                        <!-- Element -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Element <span class="text-danger">*</span></label>
                                            <select name="items[{{ $index }}][element_id]" class="form-select element-select" required>
                                                <option value="">Select Element</option>
                                                @foreach($elements as $element)
                                                    <option value="{{ $element->id }}"
                                                        {{ $item->property_element_id == $element->id ? 'selected' : '' }}>
                                                        {{ $element->element_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Repair Type -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Repair Type <span class="text-danger">*</span></label>
                                            <select name="items[{{ $index }}][repair_type_id]"
                                                    class="form-select repair-type-select"
                                                    required
                                                    data-selected="{{ $item->repair_type_id }}">
                                                <option value="">Select Repair Type</option>
                                            </select>
                                        </div>

                                        <!-- Amount -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Amount</label>
                                            <input type="number" name="items[{{ $index }}][amount]"
                                                   class="form-control" min="0" step="0.01"
                                                   placeholder="0.00"
                                                   value="{{ $item->amount }}">
                                        </div>

                                        <!-- Remarks -->
                                        <div class="col-md-9 mb-3">
                                            <label class="form-label">Remarks</label>
                                            <textarea name="items[{{ $index }}][remarks]" class="form-control" rows="2">{{ $item->remarks }}</textarea>
                                        </div>

                                        <!-- Existing images -->
                                        @if(!empty($item->photos_documents))
                                        <div class="col-12 mb-2">
                                            <label class="form-label small text-muted">Current Images</label>
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach($item->photos_documents as $photo)
                                                <a href="{{ Storage::disk('public')->url($photo) }}" target="_blank">
                                                    <img src="{{ Storage::disk('public')->url($photo) }}" alt="repair image"
                                                         style="height:80px;width:80px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                                                </a>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                        <!-- New images -->
                                        <div class="col-12 mb-1">
                                            <label class="form-label">{{ !empty($item->photos_documents) ? 'Replace / Add Images' : 'Images' }} <span class="text-muted small">(optional, max 5 MB each)</span></label>
                                            <input type="file" name="items[{{ $index }}][images][]" class="form-control image-upload"
                                                   accept="image/*" multiple>
                                            <div class="form-text text-muted">Uploading new images will replace existing ones for this item.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Item Template -->
<template id="itemTemplate">
    <div class="card mb-3 repair-item">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h6 class="mb-0">Item <span class="item-number"></span></h6>
                <button type="button" class="btn btn-sm btn-danger remove-item-btn">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
            <div class="row">
                <!-- Repair Scope -->
                <div class="col-md-3 mb-3">
                    <label class="form-label">Repair Scope <span class="text-danger">*</span></label>
                    <select name="items[INDEX][repair_scope]" class="form-select scope-select" required>
                        <option value="">Select Scope</option>
                        <option value="general">General (Entire Property)</option>
                        <option value="unit_specific">Unit Specific</option>
                    </select>
                </div>

                <!-- Unit (shown only when Unit Specific) -->
                <div class="col-md-3 mb-3 unit-field" style="display:none;">
                    <label class="form-label">Unit</label>
                    <select name="items[INDEX][unit_id]" class="form-select unit-select">
                        <option value="">Select Unit</option>
                    </select>
                </div>

                <!-- Element -->
                <div class="col-md-3 mb-3">
                    <label class="form-label">Element <span class="text-danger">*</span></label>
                    <select name="items[INDEX][element_id]" class="form-select element-select" required>
                        <option value="">Select Element</option>
                        @foreach($elements as $element)
                            <option value="{{ $element->id }}">{{ $element->element_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Repair Type -->
                <div class="col-md-3 mb-3">
                    <label class="form-label">Repair Type <span class="text-danger">*</span></label>
                    <select name="items[INDEX][repair_type_id]" class="form-select repair-type-select" required disabled>
                        <option value="">Select Repair Type</option>
                    </select>
                </div>

                <!-- Amount -->
                <div class="col-md-3 mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" name="items[INDEX][amount]" class="form-control"
                           min="0" step="0.01" placeholder="0.00">
                </div>

                <!-- Remarks -->
                <div class="col-md-9 mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="items[INDEX][remarks]" class="form-control" rows="2"></textarea>
                </div>

                <!-- Images -->
                <div class="col-12 mb-1">
                    <label class="form-label">Images <span class="text-muted small">(optional, max 5 MB each)</span></label>
                    <input type="file" name="items[INDEX][images][]" class="form-control image-upload"
                           accept="image/*" multiple>
                    <div class="form-text text-muted">Attach photos of the repair area.</div>
                </div>
            </div>
        </div>
    </div>
</template>


<script>
$(document).ready(function() {
    let itemIndex = {{ count($items) }};
    const savedClientId = {{ $repairRequest->client_id }};
    const savedPropertyId = {{ $repairRequest->property_id }};

    // Initialize Select2 for client
    $('#client_id').select2({
        placeholder: 'Select Client',
        ajax: {
            url: "{{ route('property.clients.data') }}",
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term,
                    client_type: 'Landlord'
                };
            },
            processResults: function(response) {
                return {
                    results: response.clients.map(function(client) {
                        return {
                            id: client.id,
                            text: client.names
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });

    // Initialize Select2 for property
    $('#property_id').select2({
        placeholder: 'Select Property',
        allowClear: true
    });

    // Initialize Select2 for existing items
    $('.scope-select').select2({
        placeholder: 'Select Scope',
        allowClear: false
    });
    $('.unit-select').select2({
        placeholder: 'Select Unit',
        allowClear: true
    });
    $('.element-select').select2({
        placeholder: 'Select Element',
        allowClear: true
    });
    $('.repair-type-select').select2({
        placeholder: 'Select Repair Type',
        allowClear: true
    });

    // Load properties on page load using the saved client ID
    loadProperties(savedClientId, savedPropertyId);

    // Load properties when client is selected
    $('#client_id').on('change', function() {
        const clientId = $(this).val();
        $('#property_id').empty().append('<option value="">Select Property</option>');
        
        if (clientId) {
            loadProperties(clientId);
        }
    });

    // Load units when property is selected
    let initialPropertyLoad = true;
    $('#property_id').on('change', function() {
        const propertyId = $(this).val();
        
        if (propertyId && !initialPropertyLoad) {
            // Load units for manual property changes
            loadUnitsForAllItems(propertyId, false);
        }
    });

    function loadProperties(clientId, selectedPropertyId = null) {
        $.ajax({
            url: `/client/${clientId}/properties`,
            method: 'GET',
            success: function(response) {
                const $propertySelect = $('#property_id');
                
                $propertySelect.empty().append('<option value="">Select Property</option>');
                if (response.properties && response.properties.length > 0) {
                    response.properties.forEach(function(property) {
                        $propertySelect.append(
                            $('<option>', {
                                value: property.id,
                                text: property.reference_number + ' - ' + property.property_name
                            })
                        );
                    });
                    
                    // Set the selected property and trigger units loading
                    if (selectedPropertyId) {
                        $propertySelect.val(selectedPropertyId);
                        // Manually trigger units loading for initial load
                        loadUnitsForAllItems(selectedPropertyId, true);
                    }
                } else {
                    $propertySelect.append('<option value="">No properties found</option>');
                }
                
                // Refresh Select2 to show the selected value
                $propertySelect.trigger('change.select2');
            },
            error: function() {
                toastr.error('Failed to load properties');
            }
        });
    }

    function loadUnitsForAllItems(propertyId, isInitialLoad = false) {
        $.ajax({
            url: `/property/${propertyId}/all_units`,
            method: 'GET',
            success: function(response) {
                $('.unit-select').each(function(index) {
                    const $select = $(this);
                    const savedUnitId = $select.data('selected');
                    const currentValue = isInitialLoad && savedUnitId ? savedUnitId : $select.val();
                    
                    $select.empty().append('<option value="">Select Unit</option>');
                    
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            $select.append(
                                $('<option>', {
                                    value: unit.id,
                                    text: unit.unit_code + ' - ' + (unit.unit_name || unit.unit_number)
                                })
                            );
                        });
                        
                        if (currentValue) {
                            $select.val(currentValue).trigger('change');
                        }
                    } else {
                        $select.append('<option value="">No units found</option>');
                    }
                });
                
                // After loading units, load repair types for existing items on initial load
                if (isInitialLoad) {
                    $('.element-select').each(function() {
                        const $elementSelect = $(this);
                        const elementId = $elementSelect.val();
                        const $repairTypeSelect = $elementSelect.closest('.repair-item').find('.repair-type-select');
                        const selectedRepairTypeId = $repairTypeSelect.data('selected');
                        
                        if (elementId) {
                            loadRepairTypes(elementId, $repairTypeSelect, selectedRepairTypeId);
                        }
                    });
                    // Mark initial load as complete
                    initialPropertyLoad = false;
                }
            },
            error: function() {
                toastr.error('Failed to load units');
            }
        });
    }

    // Repair types will be loaded after units are loaded on initial page load

    function loadRepairTypes(elementId, $repairTypeSelect, selectedId = null) {
        $.ajax({
            url: "{{ url('property/repairs/repair-types') }}/" + elementId,
            method: 'GET',
            success: function(response) {
                $repairTypeSelect.prop('disabled', false);
                $repairTypeSelect.empty().append('<option value="">Select Repair Type</option>');
                
                if (response.repair_types && response.repair_types.length > 0) {
                    response.repair_types.forEach(function(type) {
                        $repairTypeSelect.append(
                            $('<option>', {
                                value: type.id,
                                text: type.name,
                                selected: type.id == selectedId
                            })
                        );
                    });
                }
                $repairTypeSelect.trigger('change.select2');
            },
            error: function() {
                toastr.error('Failed to load repair types');
                $repairTypeSelect.prop('disabled', true);
            }
        });
    }

    // Add new item
    $('#addItemBtn').on('click', function() {
        const template = document.getElementById('itemTemplate');
        const clone = template.content.cloneNode(true);
        
        // Replace INDEX with actual index
        $(clone).find('select, input, textarea').each(function() {
            const name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace('INDEX', itemIndex));
            }
        });
        
        $(clone).find('.item-number').text('#' + (itemIndex + 1));
        
        $('#repairItemsContainer').append(clone);
        
        // Initialize Select2 for the new item's selects
        const $newItem = $('#repairItemsContainer .repair-item').last();
        $newItem.find('.scope-select').select2({
            placeholder: 'Select Scope',
            allowClear: false
        });
        $newItem.find('.unit-select').select2({
            placeholder: 'Select Unit',
            allowClear: true
        });
        $newItem.find('.element-select').select2({
            placeholder: 'Select Element',
            allowClear: true
        });
        $newItem.find('.repair-type-select').select2({
            placeholder: 'Select Repair Type',
            allowClear: true
        });
        
        // Load units if property is selected
        const propertyId = $('#property_id').val();
        if (propertyId) {
            loadUnitsForAllItems(propertyId);
        }
        
        itemIndex++;
        updateItemNumbers();
    });

    // Remove item
    $(document).on('click', '.remove-item-btn', function() {
        if ($('.repair-item').length > 1) {
            $(this).closest('.repair-item').remove();
            updateItemNumbers();
        } else {
            toastr.warning('At least one repair item is required');
        }
    });

    // Scope change — toggle unit field
    $(document).on('change', '.scope-select', function() {
        const $item = $(this).closest('.repair-item');
        if ($(this).val() === 'unit_specific') {
            $item.find('.unit-field').show();
        } else {
            $item.find('.unit-field').hide();
            $item.find('.unit-select').val(null).trigger('change');
        }
    });

    // Element change - load repair types
    $(document).on('change', '.element-select', function() {
        const elementId = $(this).val();
        const $repairTypeSelect = $(this).closest('.repair-item').find('.repair-type-select');
        
        $repairTypeSelect.empty().append('<option value="">Select Repair Type</option>');
        
        if (elementId) {
            loadRepairTypes(elementId, $repairTypeSelect);
        } else {
            $repairTypeSelect.prop('disabled', true);
        }
    });

    function updateItemNumbers() {
        $('.repair-item').each(function(index) {
            $(this).find('.item-number').text('#' + (index + 1));
        });
    }

    // Form submission with spinner
    $('#repairRequestForm').on('submit', function(e) {
        if ($('.repair-item').length === 0) {
            e.preventDefault();
            toastr.error('Please add at least one repair item');
            return false;
        }
        $('#submitSpinner').removeClass('d-none');
        $('#saveButton').prop('disabled', true);
    });
});
</script>
@endsection

@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Blacklisted Tenants</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="blacklistForm" class="btn btn-success btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined">save</span>
                                <span class="ms-1">Save</span>
                            </button>
                            <a href="{{ route('property.blacklist.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('property.blacklist.store') }}" method="POST" id="blacklistForm">
                            @csrf

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="blacklist_date" class="form-label text-danger">Blacklist Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('blacklist_date') is-invalid @enderror" 
                                           id="blacklist_date" name="blacklist_date" value="{{ old('blacklist_date', date('Y-m-d')) }}" required>
                                    @error('blacklist_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="client_search" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select @error('client_id') is-invalid @enderror" id="client_search" name="client_id" required>
                                        <option value="">Search for a client...</option>
                                    </select>
                                    @error('client_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Type at least 2 characters to search</small>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required disabled>
                                        <option value="">Select a client first</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="unit_id" class="form-label">Unit <span class="text-danger">*</span></label>
                                    <select class="form-select @error('unit_id') is-invalid @enderror" id="unit_id" name="unit_id" required disabled>
                                        <option value="">Select a property first</option>
                                    </select>
                                    @error('unit_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="lease_id" class="form-label">Lease <span class="text-danger">*</span></label>
                                    <select class="form-select @error('lease_id') is-invalid @enderror" id="lease_id" name="lease_id" required disabled>
                                        <option value="">Select a unit first</option>
                                    </select>
                                    @error('lease_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="reason_id" class="form-label">Reason <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select @error('reason_id') is-invalid @enderror" id="reason_id" name="reason_id" required>
                                            <option value="">Please select</option>
                                            @foreach($reasons as $reason)
                                            <option value="{{ $reason->id }}">{{ $reason->reason_name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-info" id="addReasonBtn" title="Add new reason">
                                            <span class="material-symbols-outlined">add_circle</span>
                                        </button>
                                    </div>
                                    @error('reason_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Add a new option to those above</small>
                                    <div id="newReasonContainer" class="mt-2 d-none">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="new_reason_name" placeholder="Enter new reason">
                                            <button type="button" class="btn btn-success" id="saveReasonBtn">Add</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                    @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="current_balance" class="form-label">Current Balance</label>
                                    <input type="number" step="0.01" class="form-control @error('current_balance') is-invalid @enderror" 
                                           id="current_balance" name="current_balance" value="{{ old('current_balance', '0.00') }}" required>
                                    @error('current_balance')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Hidden fields -->
                                <input type="hidden" id="tenant_id" name="tenant_id">
                                <input type="hidden" id="zone_id" name="zone_id">
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
        // Initialize Select2
        $('#client_search').select2({
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
                        results: response.clients.map(function(client) {
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

        $('#property_id, #unit_id, #lease_id, #reason_id').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });

        // When client changes, load properties
        $('#client_search').on('change', function() {
            const clientId = $(this).val();
            
            if (clientId) {
                loadProperties(clientId);
            } else {
                resetForm();
            }
        });

        function loadProperties(clientId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                success: function(response) {
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}" data-zone-id="${property.zone_id || ''}">${property.property_name} (${property.reference_number})</option>`);
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found</option>');
                    }
                    
                    $('#property_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                    $('#property_id').html('<option value="">Error loading properties</option>').prop('disabled', false);
                }
            });
        }

        // When property changes, load units
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            const zoneId = $(this).find('option:selected').data('zone-id');
            
            $('#zone_id').val(zoneId);
            
            if (propertyId) {
                loadUnits(propertyId);
            } else {
                $('#unit_id').html('<option value="">Select a property first</option>').prop('disabled', true);
                $('#lease_id').html('<option value="">Select a unit first</option>').prop('disabled', true);
            }
        });

        function loadUnits(propertyId) {
            $('#unit_id').html('<option value="">Loading units...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/property/${propertyId}/units`,
                method: 'GET',
                success: function(response) {
                    $('#unit_id').empty().prop('disabled', false);
                    $('#unit_id').append('<option value="">Please select</option>');
                    
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            $('#unit_id').append(`<option value="${unit.id}">${unit.unit_code || unit.unit_name}</option>`);
                        });
                    } else {
                        $('#unit_id').append('<option value="">No units found</option>');
                    }
                    
                    $('#unit_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading units:', xhr);
                }
            });
        }

        // When unit changes, load active leases
        $('#unit_id').on('change', function() {
            const unitId = $(this).val();
            
            if (unitId) {
                loadLeases(unitId);
            } else {
                $('#lease_id').html('<option value="">Select a unit first</option>').prop('disabled', true);
                $('#tenant_id').val('');
            }
        });

        function loadLeases(unitId) {
            $('#lease_id').html('<option value="">Loading leases...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/property/blacklist/unit/${unitId}/leases`,
                method: 'GET',
                success: function(response) {
                    $('#lease_id').empty().prop('disabled', false);
                    $('#lease_id').append('<option value="">Please select</option>');
                    
                    if (response.leases && response.leases.length > 0) {
                        response.leases.forEach(function(lease) {
                            $('#lease_id').append(`<option value="${lease.id}" 
                                data-tenant-id="${lease.tenant_id}" 
                                data-tenant-name="${lease.tenant_name}">${lease.ref_number} - ${lease.tenant_name}</option>`);
                        });
                    } else {
                        $('#lease_id').append('<option value="">No active leases found</option>');
                    }
                    
                    $('#lease_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading leases:', xhr);
                }
            });
        }

        // When lease changes, set tenant
        $('#lease_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            
            if (selectedOption.val()) {
                const tenantId = selectedOption.data('tenant-id');
                $('#tenant_id').val(tenantId);
            } else {
                $('#tenant_id').val('');
            }
        });

        // Add reason button
        $('#addReasonBtn').on('click', function() {
            $('#newReasonContainer').toggleClass('d-none');
        });

        // Save new reason
        $('#saveReasonBtn').on('click', function() {
            const reasonName = $('#new_reason_name').val().trim();
            
            if (!reasonName) {
                alert('Please enter a reason name');
                return;
            }

            $.ajax({
                url: '/property/blacklist/reasons/store',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    reason_name: reasonName
                },
                success: function(response) {
                    if (response.success) {
                        $('#reason_id').append(`<option value="${response.reason.id}" selected>${response.reason.reason_name}</option>`);
                        $('#reason_id').trigger('change');
                        $('#new_reason_name').val('');
                        $('#newReasonContainer').addClass('d-none');
                        
                        toastr.success('Reason added successfully');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to add reason';
                    alert(message);
                }
            });
        });

        function resetForm() {
            $('#property_id').html('<option value="">Select a client first</option>').prop('disabled', true);
            $('#unit_id').html('<option value="">Select a property first</option>').prop('disabled', true);
            $('#lease_id').html('<option value="">Select a unit first</option>').prop('disabled', true);
            $('#tenant_id').val('');
            $('#zone_id').val('');
        }

        // Form submission
        $('#blacklistForm').on('submit', function() {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });
    });
</script>
@endsection

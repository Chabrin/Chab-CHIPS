@extends('layouts.main')
@section('content')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Property - {{ $property->reference_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="propertyEditForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Update Property</span>
                            </button>
                            <a href="{{ route('property.properties.show', Crypt::encrypt($property->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Details</span>
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

                        <form action="{{ route('property.properties.update', Crypt::encrypt($property->id)) }}" method="POST" id="propertyEditForm">
                            @csrf
                            @method('PUT')
                            
                            <!-- Hidden client ID field -->
                            <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $property->client_id) }}">
                            
                            <!-- Hidden latitude and longitude fields -->
                            @php
                                $latitude = old('latitude');
                                $longitude = old('longitude');
                                
                                // Parse lat_long from property if not from old input
                                if (!$latitude && !$longitude && $property->lat_long) {
                                    // Format: (latitude,longitude):zoom_level
                                    preg_match('/\(([^,]+),([^)]+)\)/', $property->lat_long, $matches);
                                    if (count($matches) >= 3) {
                                        $latitude = trim($matches[1]);
                                        $longitude = trim($matches[2]);
                                    }
                                }
                            @endphp
                            <input type="hidden" name="latitude" id="latitude" value="{{ $latitude }}">
                            <input type="hidden" name="longitude" id="longitude" value="{{ $longitude }}">

                            <!-- Properties Details Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Properties Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="client_name" class="form-label">Client <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="client_name" value="{{ $client->names }}" readonly required>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_id" class="form-label">Zone <span class="text-danger">*</span></label>
                                            <select class="form-select @error('zone_id') is-invalid @enderror" id="zone_id" name="zone_id" required>
                                                <option value="">Please select</option>
                                            </select>
                                            @error('zone_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_area_id" class="form-label">Zone Area <span class="text-danger">*</span></label>
                                            <select class="form-select @error('zone_area_id') is-invalid @enderror" id="zone_area_id" name="zone_area_id" required>
                                                <option value="">Please select</option>
                                            </select>
                                            @error('zone_area_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="property_id" class="form-label">Property ID</label>
                                            <input type="text" class="form-control" id="property_id" value="{{ $property->reference_number }}" disabled>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="acquisition_date" class="form-label">Acquisition Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('acquisition_date') is-invalid @enderror" id="acquisition_date" name="acquisition_date" value="{{ old('acquisition_date', $property->acquisition_date ? $property->acquisition_date->format('Y-m-d') : '') }}" required>
                                            @error('acquisition_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_manager_id" class="form-label">Zone Manager</label>
                                            <select class="form-select @error('zone_manager_id') is-invalid @enderror" id="zone_manager_id" name="zone_manager_id">
                                                <option value="">Please select</option>
                                            </select>
                                            @error('zone_manager_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_supervisor_id" class="form-label">Zone Supervisor</label>
                                            <select class="form-select @error('zone_supervisor_id') is-invalid @enderror" id="zone_supervisor_id" name="zone_supervisor_id">
                                                <option value="">Please select</option>
                                            </select>
                                            @error('zone_supervisor_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="property_type_id" class="form-label">Property Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('property_type_id') is-invalid @enderror" id="property_type_id" name="property_type_id" required>
                                                <option value="">Please select</option>
                                                <option value="1" {{ old('property_type_id', $property->usage_type_id) == 1 ? 'selected' : '' }}>Commercial</option>
                                                <option value="3" {{ old('property_type_id', $property->usage_type_id) == 3 ? 'selected' : '' }}>Hybrid</option>
                                                <option value="2" {{ old('property_type_id', $property->usage_type_id) == 2 ? 'selected' : '' }}>Residential</option>
                                            </select>
                                            @error('property_type_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="property_name" class="form-label">Property Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('property_name') is-invalid @enderror" id="property_name" name="property_name" value="{{ old('property_name', $property->property_name) }}" required>
                                            @error('property_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="lr_number" class="form-label">LR Number</label>
                                            <input type="text" class="form-control @error('lr_number') is-invalid @enderror" id="lr_number" name="lr_number" value="{{ old('lr_number', $property->lr_number) }}">
                                            @error('lr_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="field_officer_id" class="form-label">Field Officer</label>
                                            <select class="form-select @error('field_officer_id') is-invalid @enderror" id="field_officer_id" name="field_officer_id">
                                                <option value="">Please select</option>
                                            </select>
                                            @error('field_officer_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $property->description) }}</textarea>
                                            @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Location Section -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Property Location</h6>
                                    <small class="text-muted">Click on the map to mark property location</small>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="latitude_display" class="form-label">Latitude</label>
                                            <input type="text" class="form-control @error('latitude') is-invalid @enderror" id="latitude_display" value="{{ $latitude }}" readonly placeholder="Click on map to set location">
                                            @error('latitude')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="longitude_display" class="form-label">Longitude</label>
                                            <input type="text" class="form-control @error('longitude') is-invalid @enderror" id="longitude_display" value="{{ $longitude }}" readonly placeholder="Click on map to set location">
                                            @error('longitude')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12">
                                            <div id="propertyMap" style="height: 450px; width: 100%; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Settlement Account Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Settlement Account</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="settlement_account_id" class="form-label">Settlement Account</label>
                                            <select class="form-select @error('settlement_account_id') is-invalid @enderror" id="settlement_account_id" name="settlement_account_id">
                                                <option value="">Please select</option>
                                                @if($client && $client->bankAccounts && $client->bankAccounts->count() > 0)
                                                    @foreach($client->bankAccounts as $bankAccount)
                                                        <option value="{{ $bankAccount->id }}" {{ old('settlement_account_id', $property->bank_account_id) == $bankAccount->id ? 'selected' : '' }}>
                                                            {{ $bankAccount->account_name }} - {{ $bankAccount->account_number }} ({{ $bankAccount->bank->bank_name ?? 'N/A' }})
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @error('settlement_account_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">
                                                @if($client && $client->bankAccounts && $client->bankAccounts->count() > 0)
                                                    Select the bank account where rental income will be deposited
                                                @else
                                                    No bank accounts found for this client. Please add bank accounts in client details.
                                                @endif
                                            </small>
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

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let zonesData = [];
        let staffData = [];
        let map;
        let marker;

        // Property data from server
        let existingPropertyData = {
            zone_id: {{ $property->zone_id ?? 'null' }},
            zone_area_id: {{ $property->zone_area_id ?? 'null' }},
            zone_manager_id: {{ $property->zone_manager_id ?? 'null' }},
            zone_supervisor_id: {{ $property->zone_supervisor_id ?? 'null' }},
            field_officer_id: {{ $property->field_officer_id ?? 'null' }},
            latitude: {{ $latitude ?? 'null' }},
            longitude: {{ $longitude ?? 'null' }}
        };

        // Initialize Leaflet map (centered on Nairobi, Kenya or existing location)
        let initialLat = existingPropertyData.latitude || -1.2921;
        let initialLng = existingPropertyData.longitude || 36.8219;
        let initialZoom = existingPropertyData.latitude ? 15 : 12;
        
        map = L.map('propertyMap').setView([initialLat, initialLng], initialZoom);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Custom marker icon
        var propertyIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        // Add existing marker if location exists
        if (existingPropertyData.latitude && existingPropertyData.longitude) {
            marker = L.marker([existingPropertyData.latitude, existingPropertyData.longitude], {icon: propertyIcon}).addTo(map);
            marker.bindPopup('<b>Property Location</b><br>Lat: ' + existingPropertyData.latitude + '<br>Lng: ' + existingPropertyData.longitude);
        }

        // Add click event to map
        map.on('click', function(e) {
            var lat = e.latlng.lat.toFixed(6);
            var lng = e.latlng.lng.toFixed(6);

            // Update hidden fields
            $('#latitude').val(lat);
            $('#longitude').val(lng);

            // Update display fields
            $('#latitude_display').val(lat);
            $('#longitude_display').val(lng);

            // Remove existing marker if any
            if (marker) {
                map.removeLayer(marker);
            }

            // Add new marker
            marker = L.marker([lat, lng], {icon: propertyIcon}).addTo(map);
            marker.bindPopup('<b>Property Location</b><br>Lat: ' + lat + '<br>Lng: ' + lng).openPopup();
        });

        // Handle form submission
        $('#propertyEditForm').on('submit', function() {
            var submitButton = $('#saveButton');
            var spinner = $('#submitSpinner');

            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Load zones with details
        $.ajax({
            url: "{{ route('ajax.zones.details') }}",
            type: 'GET',
            success: function(response) {
                if (response.zones) {
                    zonesData = response.zones;
                    populateZones();
                }
            },
            error: function(xhr) {
                console.error('Failed to load zones:', xhr);
                toastr.error('Failed to load zones. Please refresh the page.');
            }
        });

        // Load staff list
        $.ajax({
            url: "{{ route('ajax.staff.list') }}",
            type: 'GET',
            success: function(response) {
                if (response.staff) {
                    staffData = response.staff;
                    populateStaffDropdowns();
                }
            },
            error: function(xhr) {
                console.error('Failed to load staff:', xhr);
                toastr.error('Failed to load staff. Please refresh the page.');
            }
        });

        // Populate zones dropdown
        function populateZones() {
            var zoneSelect = $('#zone_id');
            zoneSelect.empty().append('<option value="">Please select</option>');
            zonesData.forEach(function(zone) {
                var selected = existingPropertyData.zone_id == zone.id ? 'selected' : '';
                zoneSelect.append(`<option value="${zone.id}" ${selected}>${zone.zone_name}</option>`);
            });

            // Trigger change to populate zone areas
            if (existingPropertyData.zone_id) {
                $('#zone_id').trigger('change');
            }
        }

        // Populate staff dropdowns
        function populateStaffDropdowns() {
            var selects = ['#zone_manager_id', '#zone_supervisor_id', '#field_officer_id'];
            selects.forEach(function(selector) {
                var select = $(selector);
                var fieldName = selector.replace('#', '').replace('_id', '_id');
                var selectedValue = existingPropertyData[fieldName];
                
                select.empty().append('<option value="">Please select</option>');
                staffData.forEach(function(staff) {
                    var selected = selectedValue == staff.id ? 'selected' : '';
                    select.append(`<option value="${staff.id}" ${selected}>${staff.full_names}</option>`);
                });
            });
        }

        // Handle zone change to auto-populate related fields
        $('#zone_id').on('change', function() {
            var zoneId = $(this).val();
            var zoneAreaSelect = $('#zone_area_id');
            var fieldOfficerSelect = $('#field_officer_id');
            var isInitialLoad = (zoneId == existingPropertyData.zone_id);
            
            // Clear dependent fields
            zoneAreaSelect.empty().append('<option value="">Please select</option>');
            
            if (!isInitialLoad) {
                $('#zone_manager_id').val('');
                $('#zone_supervisor_id').val('');
            }
            
            fieldOfficerSelect.empty().append('<option value="">Please select</option>');

            if (zoneId) {
                // Find the selected zone
                var selectedZone = zonesData.find(function(zone) {
                    return zone.id == zoneId;
                });

                if (selectedZone) {
                    // Populate zone areas
                    if (selectedZone.areas && selectedZone.areas.length > 0) {
                        selectedZone.areas.forEach(function(area) {
                            var selected = existingPropertyData.zone_area_id == area.id ? 'selected' : '';
                            zoneAreaSelect.append(`<option value="${area.id}" ${selected}>${area.area_name}</option>`);
                        });
                    }

                    // Auto-select manager if available (only on initial load or if not already set)
                    if (selectedZone.manager_id && !isInitialLoad) {
                        $('#zone_manager_id').val(selectedZone.manager_id);
                    } else if (isInitialLoad && existingPropertyData.zone_manager_id) {
                        $('#zone_manager_id').val(existingPropertyData.zone_manager_id);
                    }

                    // Auto-select supervisor if available
                    if (selectedZone.supervisor_id && !isInitialLoad) {
                        $('#zone_supervisor_id').val(selectedZone.supervisor_id);
                    } else if (isInitialLoad && existingPropertyData.zone_supervisor_id) {
                        $('#zone_supervisor_id').val(existingPropertyData.zone_supervisor_id);
                    }

                    // Populate field officers from the zone's field_officers array
                    if (selectedZone.field_officers && selectedZone.field_officers.length > 0) {
                        selectedZone.field_officers.forEach(function(officer) {
                            if (officer.id && officer.name) {
                                var selected = existingPropertyData.field_officer_id == officer.id ? 'selected' : '';
                                fieldOfficerSelect.append(`<option value="${officer.id}" ${selected}>${officer.name}</option>`);
                            }
                        });
                    } else {
                        // If no field officers assigned to zone, populate with all staff
                        if (staffData.length > 0) {
                            staffData.forEach(function(staff) {
                                var selected = existingPropertyData.field_officer_id == staff.id ? 'selected' : '';
                                fieldOfficerSelect.append(`<option value="${staff.id}" ${selected}>${staff.full_names}</option>`);
                            });
                        }
                    }
                }
            } else {
                // If no zone selected, repopulate field officer with all staff
                if (staffData.length > 0) {
                    staffData.forEach(function(staff) {
                        var selected = existingPropertyData.field_officer_id == staff.id ? 'selected' : '';
                        fieldOfficerSelect.append(`<option value="${staff.id}" ${selected}>${staff.full_names}</option>`);
                    });
                }
            }
        });

    });
</script>
@endsection
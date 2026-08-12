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
                        <h4 class="mb-0">Add New Property</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="propertyCreateForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Save Property</span>
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

                        <form action="{{ route('property.properties.store') }}" method="POST" id="propertyCreateForm">
                            @csrf
                            
                            <!-- Hidden client ID field -->
                            <input type="hidden" name="client_id" id="client_id" value="{{ $client->id ?? old('client_id') }}">
                            
                            <!-- Hidden latitude and longitude fields -->
                            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">

                            <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
                                <span class="material-symbols-outlined">info</span>
                                <div>
                                    Before creating a property, make sure the <strong>Zone</strong> has been created and its
                                    <strong>Zone Manager</strong> and <strong>Zone Supervisor</strong> have been assigned at
                                    <a href="{{ route('property.settings.zones.index') }}" target="_blank">Property Settings &rarr; Zones</a>.
                                    Selecting a zone below only auto-fills the manager/supervisor if this has been done.
                                </div>
                            </div>

                            <!-- Properties Details Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Properties Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="client_name" class="form-label">Client <span class="text-danger">*</span></label>
                                            @if($isClientPreSelected)
                                                <input type="text" class="form-control" id="client_name" value="{{ $client->names }}" readonly required>
                                            @else
                                                <div class="position-relative">
                                                    <input type="text" class="form-control @error('client_id') is-invalid @enderror" id="client_search" placeholder="Type to search client..." autocomplete="off" required>
                                                    <div id="client_search_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="display:none; max-height: 300px; overflow-y: auto; z-index: 1000; top: 100%; margin-top: 2px;">
                                                        <!-- Search results will appear here -->
                                                    </div>
                                                    <small class="text-muted">Start typing client name, email, or phone number</small>
                                                </div>
                                            @endif
                                            @error('client_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_id" class="form-label">Zone <span class="text-danger">*</span></label>
                                            <select class="form-select @error('zone_id') is-invalid @enderror" id="zone_id" name="zone_id" required>
                                                <option value="">Please select</option>
                                                <!-- Zones will be populated via AJAX or from controller -->
                                            </select>
                                            @error('zone_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_area_id" class="form-label">Zone Area <span class="text-danger">*</span></label>
                                            <select class="form-select @error('zone_area_id') is-invalid @enderror" id="zone_area_id" name="zone_area_id" required>
                                                <option value="">Please select</option>
                                                <!-- Zone areas will be populated based on zone selection -->
                                            </select>
                                            @error('zone_area_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="property_id" class="form-label">Property ID</label>
                                            <input type="text" class="form-control" id="property_id" value="Auto-generated" disabled>
                                        </div>

                                        

                                        <div class="col-md-3 mb-3">
                                            <label for="acquisition_date" class="form-label">Acquisition Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('acquisition_date') is-invalid @enderror" id="acquisition_date" name="acquisition_date" value="{{ old('acquisition_date', now()->format('Y-m-d')) }}" required>
                                            @error('acquisition_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_manager_id" class="form-label">Zone Manager</label>
                                            <select class="form-select @error('zone_manager_id') is-invalid @enderror" id="zone_manager_id" name="zone_manager_id">
                                                <option value="">Please select</option>
                                                <!-- Staff will be populated via AJAX -->
                                            </select>
                                            @error('zone_manager_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="zone_supervisor_id" class="form-label">Zone Supervisor</label>
                                            <select class="form-select @error('zone_supervisor_id') is-invalid @enderror" id="zone_supervisor_id" name="zone_supervisor_id">
                                                <option value="">Please select</option>
                                                <!-- Staff will be populated via AJAX -->
                                            </select>
                                            @error('zone_supervisor_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="property_type_id" class="form-label">Property Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('property_type_id') is-invalid @enderror" id="property_type_id" name="property_type_id" required>
                                                <option value="">Please select</option>
                                                <option value="1">Commercial</option>
                                                <option value="3">Hybrid</option>
                                                <option value="2">Residential</option>
                                            </select>
                                            @error('property_type_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="property_name" class="form-label">Property Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('property_name') is-invalid @enderror" id="property_name" name="property_name" value="{{ old('property_name') }}" required>
                                            @error('property_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="lr_number" class="form-label">LR Number</label>
                                            <input type="text" class="form-control @error('lr_number') is-invalid @enderror" id="lr_number" name="lr_number" value="{{ old('lr_number') }}">
                                            @error('lr_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="field_officer_id" class="form-label">Field Officer</label>
                                            <select class="form-select @error('field_officer_id') is-invalid @enderror" id="field_officer_id" name="field_officer_id">
                                                <option value="">Please select</option>
                                                <!-- Staff will be populated via AJAX -->
                                            </select>
                                            @error('field_officer_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
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
                                            <input type="text" class="form-control @error('latitude') is-invalid @enderror" id="latitude_display" value="{{ old('latitude') }}" readonly placeholder="Click on map to set location">
                                            @error('latitude')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="longitude_display" class="form-label">Longitude</label>
                                            <input type="text" class="form-control @error('longitude') is-invalid @enderror" id="longitude_display" value="{{ old('longitude') }}" readonly placeholder="Click on map to set location">
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
                                                        <option value="{{ $bankAccount->id }}" {{ old('settlement_account_id') == $bankAccount->id ? 'selected' : '' }}>
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
<script>
    $(document).ready(function() {
        let zonesData = [];
        let staffData = [];
        let clientsData = [];
        let selectedClientData = null;
        let isClientPreSelected = {{ $isClientPreSelected ? 'true' : 'false' }};
        let map;
        let marker;

        $('#field_officer_id').select2({
            placeholder: 'Search field officer...',
            allowClear: true,
            width: '100%'
        });

        // Initialize Leaflet map (centered on Nairobi, Kenya)
        map = L.map('propertyMap').setView([-1.2921, 36.8219], 12);

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

        // If old values exist (from validation error), place marker
        @if(old('latitude') && old('longitude'))
        var oldLat = {{ old('latitude') }};
        var oldLng = {{ old('longitude') }};
        marker = L.marker([oldLat, oldLng], {icon: propertyIcon}).addTo(map);
        marker.bindPopup('<b>Property Location</b><br>Lat: ' + oldLat + '<br>Lng: ' + oldLng);
        map.setView([oldLat, oldLng], 15);
        $('#latitude_display').val(oldLat);
        $('#longitude_display').val(oldLng);
        @endif

        // Handle form submission
        $('#propertyCreateForm').on('submit', function() {
            var submitButton = $('#saveButton');
            var spinner = $('#submitSpinner');

            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Client search functionality (only if not pre-selected)
        if (!isClientPreSelected) {
            let searchTimeout;
            
            // Handle client search input
            $('#client_search').on('input', function() {
                clearTimeout(searchTimeout);
                var searchQuery = $(this).val().trim();
                
                if (searchQuery.length < 2) {
                    $('#client_search_results').hide().empty();
                    return;
                }
                
                // Debounce search - wait 500ms after user stops typing
                searchTimeout = setTimeout(function() {
                    searchClients(searchQuery);
                }, 500);
            });
            
            // Search clients via AJAX
            function searchClients(query) {
                $('#client_search_results').html('<div class="p-3 text-center"><span class="spinner-border spinner-border-sm"></span> Searching...</div>').show();
                
                $.ajax({
                    url: "{{ route('ajax.clients.search') }}",
                    type: 'GET',
                    data: { search: query },
                    success: function(response) {
                        displayClientResults(response.clients);
                    },
                    error: function(xhr) {
                        $('#client_search_results').html('<div class="p-3 text-danger">Error loading clients</div>');
                        console.error('Failed to search clients:', xhr);
                    }
                });
            }
            
            // Display search results
            function displayClientResults(clients) {
                var resultsHtml = '';
                
                if (clients.length === 0) {
                    resultsHtml = '<div class="p-3 text-muted">No clients found</div>';
                } else {
                    clients.forEach(function(client) {
                        resultsHtml += `
                            <div class="client-result-item p-2 border-bottom" style="cursor: pointer;" data-client-id="${client.id}" data-client-name="${client.names}" data-client-data='${JSON.stringify(client)}'>
                                <div class="fw-bold">${client.names}</div>
                                <small class="text-muted">${client.email || '-'} | ${client.mobile_number || '-'}</small>
                            </div>
                        `;
                    });
                }
                
                $('#client_search_results').html(resultsHtml).show();
            }
            
            // Handle client selection from results
            $(document).on('click', '.client-result-item', function() {
                var clientId = $(this).data('client-id');
                var clientName = $(this).data('client-name');
                var clientData = $(this).data('client-data');
                
                // Set the hidden field and display field
                $('#client_id').val(clientId);
                $('#client_search').val(clientName);
                
                // Store selected client data
                selectedClientData = clientData;
                
                // Populate settlement accounts
                populateSettlementAccounts();
                
                // Hide results
                $('#client_search_results').hide();
            });
            
            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#client_search, #client_search_results').length) {
                    $('#client_search_results').hide();
                }
            });
        }

        // Populate settlement accounts based on selected client
        function populateSettlementAccounts() {
            var settlementSelect = $('#settlement_account_id');
            settlementSelect.empty().append('<option value="">Please select</option>');

            if (selectedClientData && selectedClientData.bank_accounts && selectedClientData.bank_accounts.length > 0) {
                selectedClientData.bank_accounts.forEach(function(account) {
                    var accountName = account.account_name || 'N/A';
                    var accountDisplay = account.account_number || 'N/A';
                    var bankDisplay = account.bank_name || 'N/A';
                    settlementSelect.append(`<option value="${account.id}">${accountName} - ${accountDisplay} (${bankDisplay})</option>`);
                });
            } else {
                settlementSelect.append('<option value="" disabled>No bank accounts found for this client</option>');
            }
        }

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
                zoneSelect.append(`<option value="${zone.id}">${zone.zone_name}</option>`);
            });
        }

        // Populate staff dropdowns
        function populateStaffDropdowns() {
            var selects = ['#zone_manager_id', '#zone_supervisor_id', '#field_officer_id'];
            selects.forEach(function(selector) {
                var select = $(selector);
                var currentValue = select.val(); // Preserve any existing value
                select.empty().append('<option value="">Please select</option>');
                staffData.forEach(function(staff) {
                    var selected = currentValue == staff.id ? 'selected' : '';
                    select.append(`<option value="${staff.id}" ${selected}>${staff.full_names}</option>`);
                });
            });
        }

        // Handle zone change to auto-populate related fields
        $('#zone_id').on('change', function() {
            var zoneId = $(this).val();
            var zoneAreaSelect = $('#zone_area_id');
            var fieldOfficerSelect = $('#field_officer_id');
            
            // Clear all dependent fields
            zoneAreaSelect.empty().append('<option value="">Please select</option>');
            $('#zone_manager_id').val('');
            $('#zone_supervisor_id').val('');
            fieldOfficerSelect.empty().append('<option value="">Please select</option>');

            if (zoneId) {
                // Find the selected zone
                var selectedZone = zonesData.find(function(zone) {
                    return zone.id == zoneId;
                });

                if (selectedZone) {
                    console.log('Selected zone:', selectedZone);

                    // Populate zone areas
                    if (selectedZone.areas && selectedZone.areas.length > 0) {
                        selectedZone.areas.forEach(function(area) {
                            zoneAreaSelect.append(`<option value="${area.id}">${area.area_name}</option>`);
                        });
                    }

                    // Auto-select manager if available
                    if (selectedZone.manager_id) {
                        $('#zone_manager_id').val(selectedZone.manager_id);
                        console.log('Auto-selected manager:', selectedZone.manager_name);
                    }

                    // Auto-select supervisor if available
                    if (selectedZone.supervisor_id) {
                        $('#zone_supervisor_id').val(selectedZone.supervisor_id);
                        console.log('Auto-selected supervisor:', selectedZone.supervisor_name);
                    }
                }
            }

            // Field officers aren't assignable per-zone in this app today — always
            // offer the full staff list, regardless of which (or whether a) zone is selected.
            if (staffData.length > 0) {
                staffData.forEach(function(staff) {
                    fieldOfficerSelect.append(`<option value="${staff.id}">${staff.full_names}</option>`);
                });
            }
            fieldOfficerSelect.trigger('change');
        });

    });
</script>
@endsection
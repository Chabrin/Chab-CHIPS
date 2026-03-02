@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Units</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="unitCreateForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Save</span>
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

                        <form action="{{ route('property.units.store') }}" method="POST" id="unitCreateForm" enctype="multipart/form-data">
                            @csrf
                            
                            <!-- Hidden fields -->
                            <input type="hidden" name="client_id" value="{{ $client->id ?? old('client_id') }}">

                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-4">
                                    <!-- Unit Number -->
                                    <div class="mb-3">
                                        <label for="unit_number" class="form-label">
                                            Unit Number (e.g. Shop 1) <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control @error('unit_number') is-invalid @enderror" id="unit_number" name="unit_number" value="{{ old('unit_number') }}" required>
                                        @error('unit_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Topology -->
                                    <div class="mb-3">
                                        <label for="topology_id" class="form-label">Topology</label>
                                        <select class="form-select @error('topology_id') is-invalid @enderror" id="topology_id" name="topology_id">
                                            <option value="">Please select</option>
                                            <!-- Topology options will be populated -->
                                        </select>
                                        @error('topology_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Category -->
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                                            <option value="">Please select</option>
                                            <option value="1">For Rent</option>
                                            <option value="2">Other</option>
                                        </select>
                                        @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- VAT -->
                                    <div class="mb-3">
                                        <label for="vat_able" class="form-label">VAT</label>
                                        <select class="form-select @error('vat_able') is-invalid @enderror" id="vat_able" name="vat_able">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                        @error('vat_able')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Middle Column -->
                                <div class="col-md-4">
                                    <!-- Property -->
                                    <div class="mb-3">
                                        <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                        <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                            <option value="">Loading properties...</option>
                                        </select>
                                        @error('property_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Unit Type -->
                                    <div class="mb-3">
                                        <label for="unit_type_id" class="form-label">Unit Type <span class="text-danger">*</span></label>
                                        <select class="form-select @error('unit_type_id') is-invalid @enderror" id="unit_type_id" name="unit_type_id" required>
                                            <option value="">Please select</option>
                                            <option value="1">Normal</option>
                                            <option value="2">Owner Occupied</option>
                                            <option value="3">Caretaker</option>
                                        </select>
                                        @error('unit_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Unit Condition -->
                                    <div class="mb-3">
                                        <label for="unit_condition_id" class="form-label">Unit Condition <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('unit_condition_id') is-invalid @enderror" id="unit_condition_id" name="unit_condition_id" value="{{ old('unit_condition_id') }}" required>
                                        @error('unit_condition_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Unit Name -->
                                    <div class="mb-3">
                                        <label for="unit_name" class="form-label">Unit Name</label>
                                        <input type="text" class="form-control @error('unit_name') is-invalid @enderror" id="unit_name" name="unit_name" value="{{ old('unit_name') }}">
                                        @error('unit_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="col-md-4">
                                    <!-- Usage Type -->
                                    <div class="mb-3">
                                        <label for="usage_type_id" class="form-label">Usage Type <span class="text-danger">*</span></label>
                                        <select class="form-select @error('usage_type_id') is-invalid @enderror" id="usage_type_id" name="usage_type_id" required>
                                            <option value="">Please select</option>
                                            <option value="1">Commercial</option>
                                            <option value="3">Hybrid</option>
                                            <option value="2">Residential</option>
                                        </select>
                                        @error('usage_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Rent -->
                                    <div class="mb-3">
                                        <label for="rent_amount" class="form-label">Rent</label>
                                        <input type="number" step="0.01" class="form-control @error('rent_amount') is-invalid @enderror" id="rent_amount" name="rent_amount" value="{{ old('rent_amount', '0.00') }}">
                                        @error('rent_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Description -->
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description') }}</textarea>
                                        @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Unit Uploads Section -->
                            <div class="row mt-3">
                                <!-- Check Occupancy Status -->
                                <div class="col-md-12 mb-4">
                                    <label for="occupancy_status_id" class="form-label">Occupation Status</label>
                                    <select class="form-select @error('occupancy_status_id') is-invalid @enderror" id="occupancy_status_id" name="occupancy_status_id">
                                        <option value="">Please select</option>
                                        <option value="38">Occupied</option>
                                        <option value="31">Vacant</option>
                                    </select>
                                    @error('occupancy_status_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-12">
                                    <label for="unit_uploads" class="form-label">Unit Uploads</label>
                                    <div class="input-group mb-3">
                                        <input type="file" class="form-control @error('unit_uploads') is-invalid @enderror" id="unit_uploads" name="unit_uploads[]" multiple accept="image/*,.pdf,.doc,.docx">
                                        <button class="btn btn-outline-secondary" type="button" id="addFilesBtn">
                                            <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                                        </button>
                                        @error('unit_uploads')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">Accepted formats: Images, PDF, Word documents (Max 5MB per file)</small>
                                </div>
                            </div>

                            <!-- Initial Water Meter Reading Section -->
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <label for="initial_water_meter_reading" class="form-label">Initial Water Meter Reading</label>
                                    <input type="number" step="0.01" class="form-control @error('initial_water_meter_reading') is-invalid @enderror" id="initial_water_meter_reading" name="initial_water_meter_reading" value="{{ old('initial_water_meter_reading', '0') }}">
                                    @error('initial_water_meter_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
        // Handle form submission
        $('#unitCreateForm').on('submit', function() {
            var submitButton = $('#saveButton');
            var spinner = $('#submitSpinner');

            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Add more file inputs
        $('#addFilesBtn').on('click', function() {
            var fileInput = $('#unit_uploads').clone();
            fileInput.val('');
            $('#unit_uploads').parent().before(fileInput);
        });

        // Load properties via AJAX when page loads
        const clientId = $('input[name="client_id"]').val();
        if (clientId) {
            loadProperties(clientId);
        }

        // Function to load properties for the client
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
                            $('#property_id').append(
                                `<option value="${property.id}">
                                    ${property.property_name}
                                </option>`
                            );
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found for this client</option>');
                    }
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Error loading properties</option>');
                }
            });
        }
    });
</script>
@endsection
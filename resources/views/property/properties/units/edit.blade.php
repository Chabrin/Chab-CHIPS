@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Unit - {{ $unit->unit_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="unitEditForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Update</span>
                            </button>
                            <a href="{{ route('property.properties.show', Crypt::encrypt($unit->property_id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('property.units.update', Crypt::encrypt($unit->id)) }}" method="POST" id="unitEditForm" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            
                            <!-- Hidden fields -->
                            <input type="hidden" name="client_id" value="{{ $unit->client_id }}">

                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-4">
                                    <!-- Unit Number -->
                                    <div class="mb-3">
                                        <label for="unit_number" class="form-label">
                                            Unit Number <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control @error('unit_number') is-invalid @enderror" id="unit_number" name="unit_number" value="{{ old('unit_number', $unit->unit_number) }}" required>
                                        @error('unit_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Topology -->
                                    <div class="mb-3">
                                        <label for="topology_id" class="form-label">Topology</label>
                                        <select class="form-select @error('topology_id') is-invalid @enderror" id="topology_id" name="topology_id">
                                            <option value="">Please select</option>
                                            <option value="1" {{ old('topology_id', $unit->topology_id) == 1 ? 'selected' : '' }}>Topology 1</option>
                                            <option value="2" {{ old('topology_id', $unit->topology_id) == 2 ? 'selected' : '' }}>Topology 2</option>
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
                                            <option value="1" {{ old('category_id', $unit->category_id) == 1 ? 'selected' : '' }}>Category 1</option>
                                            <option value="2" {{ old('category_id', $unit->category_id) == 2 ? 'selected' : '' }}>Category 2</option>
                                        </select>
                                        @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- VAT -->
                                    <div class="mb-3">
                                        <label for="vat_able" class="form-label">VAT</label>
                                        <select class="form-select @error('vat_able') is-invalid @enderror" id="vat_able" name="vat_able">
                                            <option value="0" {{ old('vat_able', $unit->vat_able) == 0 ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ old('vat_able', $unit->vat_able) == 1 ? 'selected' : '' }}>Yes</option>
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
                                            <option value="">Please select</option>
                                            @foreach($properties as $property)
                                                <option value="{{ $property->id }}" {{ old('property_id', $unit->property_id) == $property->id ? 'selected' : '' }}>
                                                    {{ $property->property_name }}
                                                </option>
                                            @endforeach
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
                                            <option value="1" {{ old('unit_type_id', $unit->unit_type_id) == 1 ? 'selected' : '' }}>Residential</option>
                                            <option value="2" {{ old('unit_type_id', $unit->unit_type_id) == 2 ? 'selected' : '' }}>Commercial</option>
                                            <option value="3" {{ old('unit_type_id', $unit->unit_type_id) == 3 ? 'selected' : '' }}>Industrial</option>
                                        </select>
                                        @error('unit_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Unit Condition -->
                                    <div class="mb-3">
                                        <label for="unit_condition_id" class="form-label">Unit Condition <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('unit_condition_id') is-invalid @enderror" id="unit_condition_id" name="unit_condition_id" value="{{ old('unit_condition_id', $unit->unit_condition_id) }}" required>
                                        @error('unit_condition_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Unit Name -->
                                    <div class="mb-3">
                                        <label for="unit_name" class="form-label">Unit Name</label>
                                        <input type="text" class="form-control @error('unit_name') is-invalid @enderror" id="unit_name" name="unit_name" value="{{ old('unit_name', $unit->unit_name) }}">
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
                                            <option value="1" {{ old('usage_type_id', $unit->usage_type_id) == 1 ? 'selected' : '' }}>Residential</option>
                                            <option value="2" {{ old('usage_type_id', $unit->usage_type_id) == 2 ? 'selected' : '' }}>Office</option>
                                            <option value="3" {{ old('usage_type_id', $unit->usage_type_id) == 3 ? 'selected' : '' }}>Retail</option>
                                            <option value="4" {{ old('usage_type_id', $unit->usage_type_id) == 4 ? 'selected' : '' }}>Warehouse</option>
                                        </select>
                                        @error('usage_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Rent -->
                                    <div class="mb-3">
                                        <label for="rent_amount" class="form-label">Rent</label>
                                        <input type="number" step="0.01" class="form-control @error('rent_amount') is-invalid @enderror" id="rent_amount" name="rent_amount" value="{{ old('rent_amount', $unit->rent_amount ?? '0.00') }}">
                                        @error('rent_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Description -->
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $unit->description) }}</textarea>
                                        @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Unit Status and Water Meter -->
                            <div class="row mt-3">
                                <!-- Occupancy Status -->
                                <div class="col-md-6 mb-4">
                                    <label for="occupancy_status_id" class="form-label">Occupation Status</label>
                                    <select class="form-select @error('occupancy_status_id') is-invalid @enderror" id="occupancy_status_id" name="occupancy_status_id">
                                        <option value="">Please select</option>
                                        <option value="38" {{ old('occupancy_status_id', $unit->occupancy_status_id) == 38 ? 'selected' : '' }}>Occupied</option>
                                        <option value="31" {{ old('occupancy_status_id', $unit->occupancy_status_id) == 31 ? 'selected' : '' }}>Vacant</option>
                                    </select>
                                    @error('occupancy_status_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Initial Water Meter Reading -->
                                <div class="col-md-6">
                                    <label for="initial_water_meter_reading" class="form-label">Initial Water Meter Reading</label>
                                    <input type="number" step="0.01" class="form-control @error('initial_water_meter_reading') is-invalid @enderror" id="initial_water_meter_reading" name="initial_water_meter_reading" value="{{ old('initial_water_meter_reading', $unit->initial_water_meter_reading ?? '0') }}">
                                    @error('initial_water_meter_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Existing Documents -->
                                                        <!-- Existing Documents -->
                            @if($unit->unit_documents && $unit->unit_documents->count() > 0)
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Existing Documents</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>File Name</th>
                                                    <th>Uploaded</th>
                                                    <th>Size</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($unit->unit_documents as $document)
                                                    @php
                                                        $params = [];
                                                        if (!empty($document->params)) {
                                                            $params = is_string($document->params) ? json_decode($document->params, true) : $document->params;
                                                        }
                                                    @endphp
                                                    <tr id="doc-row-{{ $document->id }}">
                                                        <td>
                                                            <a href="{{ asset('storage/' . $document->unit_uploads) }}" target="_blank">
                                                                {{ $params['original_name'] ?? basename($document->unit_uploads) }}
                                                            </a>
                                                        </td>
                                                        <td>{{ isset($params['uploaded_at']) ? \Carbon\Carbon::parse($params['uploaded_at'])->format('d M Y H:i') : 'N/A' }}</td>
                                                        <td>{{ isset($params['size']) ? number_format($params['size'] / 1024, 2) . ' KB' : 'N/A' }}</td>
                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm delete-doc" data-doc-id="{{ $document->id }}">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- New Unit Uploads Section -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label for="unit_uploads" class="form-label">Add New Documents</label>
                                    <div class="input-group mb-3">
                                        <input type="file" class="form-control" id="unit_uploads" name="unit_uploads[]" multiple>
                                        <button class="btn btn-outline-secondary" type="button" id="addFilesBtn">
                                            <span class="material-symbols-outlined">add</span>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Accepted formats: Images, PDF, Word documents (Max 5MB per file)</small>
                                </div>
                            </div>

                            <!-- Hidden input for documents to delete -->
                            <input type="hidden" name="delete_documents" id="delete_documents_input" value="">
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
        let documentsToDelete = [];

        // Handle form submission
        $('#unitEditForm').on('submit', function() {
            // Update hidden input with documents to delete
            $('#delete_documents_input').val(JSON.stringify(documentsToDelete));

            var submitButton = $('#saveButton');
            var spinner = $('#submitSpinner');

            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Handle document deletion
        $('.delete-doc').on('click', function() {
            const docId = $(this).data('doc-id');
            const row = $('#doc-row-' + docId);

            if (confirm('Are you sure you want to delete this document?')) {
                documentsToDelete.push(docId);
                row.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });

        // Add more file inputs
        $('#addFilesBtn').on('click', function() {
            var fileInput = $('#unit_uploads').clone();
            fileInput.val('');
            $('#unit_uploads').parent().before(fileInput.parent().clone());
        });
    });
</script>
@endsection

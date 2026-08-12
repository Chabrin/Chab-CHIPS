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

                                        <div class="col-md-3 mb-3">
                                            <label for="move_in_inspection_required" class="form-label">Move-in Inspection Required</label>
                                            <select class="form-select" id="move_in_inspection_required" name="move_in_inspection_required">
                                                <option value="0" {{ !$sla->move_in_inspection_required ? 'selected' : '' }}>No — Optional</option>
                                                <option value="1" {{ $sla->move_in_inspection_required  ? 'selected' : '' }}>Yes — Mandatory</option>
                                            </select>
                                            <div class="form-text text-muted" style="font-size:0.75rem;">When enabled, a move-in inspection must be completed during tenant onboarding.</div>
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
@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Record Move-In Inspection</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="inspectionForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                <span>Save Inspection</span>
                            </button>
                            <a href="{{ route('move.in.inspections.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <div class="alert alert-info py-2 px-3">
                            Use this form to record a move-in inspection that was skipped or forgotten during tenant
                            onboarding. The property and unit are taken from the tenant's own record.
                        </div>

                        <form action="{{ route('move.in.inspections.store') }}" method="POST" id="inspectionForm" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tenant_id" class="form-label">Tenant <span class="text-danger">*</span></label>
                                    <select class="form-select @error('tenant_id') is-invalid @enderror" id="tenant_id" name="tenant_id" required>
                                        @if($selectedTenant)
                                        <option value="{{ \Illuminate\Support\Facades\Crypt::encrypt($selectedTenant->id) }}" selected>
                                            {{ $selectedTenant->names }}{{ $selectedTenant->reference_number ? ' (' . $selectedTenant->reference_number . ')' : '' }}
                                        </option>
                                        @endif
                                    </select>
                                    @error('tenant_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted" id="noUnitWarning" style="display:none;">
                                        <span class="text-danger">This tenant has no property/unit assigned — cannot record an inspection for them.</span>
                                    </small>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="inspection_date" class="form-label">Inspection Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('inspection_date') is-invalid @enderror" id="inspection_date" name="inspection_date" value="{{ old('inspection_date', now()->format('Y-m-d')) }}" required>
                                    @error('inspection_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="inspector_name" class="form-label">Inspector Name</label>
                                    <input type="text" class="form-control @error('inspector_name') is-invalid @enderror" id="inspector_name" name="inspector_name" value="{{ old('inspector_name') }}" placeholder="Name of inspector">
                                    @error('inspector_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="overall_condition" class="form-label">Overall Condition</label>
                                    <select class="form-select @error('overall_condition') is-invalid @enderror" id="overall_condition" name="overall_condition">
                                        <option value="">&mdash; Select &mdash;</option>
                                        <option value="Excellent" {{ old('overall_condition') == 'Excellent' ? 'selected' : '' }}>Excellent</option>
                                        <option value="Good" {{ old('overall_condition') == 'Good' ? 'selected' : '' }}>Good</option>
                                        <option value="Fair" {{ old('overall_condition') == 'Fair' ? 'selected' : '' }}>Fair</option>
                                        <option value="Poor" {{ old('overall_condition') == 'Poor' ? 'selected' : '' }}>Poor</option>
                                    </select>
                                    @error('overall_condition')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @foreach([['walls','Walls'],['floors','Floors'],['ceiling','Ceiling'],['doors','Doors'],['windows','Windows'],['plumbing','Plumbing'],['electrical','Electrical']] as [$key, $label])
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">{{ $label }}</label>
                                    <select class="form-select @error($key . '_condition') is-invalid @enderror" name="{{ $key }}_condition">
                                        <option value="">&mdash; Select &mdash;</option>
                                        <option value="Excellent" {{ old($key . '_condition') == 'Excellent' ? 'selected' : '' }}>Excellent</option>
                                        <option value="Good" {{ old($key . '_condition') == 'Good' ? 'selected' : '' }}>Good</option>
                                        <option value="Fair" {{ old($key . '_condition') == 'Fair' ? 'selected' : '' }}>Fair</option>
                                        <option value="Poor" {{ old($key . '_condition') == 'Poor' ? 'selected' : '' }}>Poor</option>
                                    </select>
                                    @error($key . '_condition')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endforeach

                                <div class="col-12 mb-3">
                                    <label for="inspection_notes" class="form-label">Notes</label>
                                    <textarea class="form-control @error('inspection_notes') is-invalid @enderror" id="inspection_notes" name="inspection_notes" rows="3" placeholder="Any observations or notes about the unit condition...">{{ old('inspection_notes') }}</textarea>
                                    @error('inspection_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-1">
                                    <hr class="mt-0 mb-2">
                                    <p class="text-muted mb-2" style="font-size:0.8rem;">
                                        <span class="material-symbols-outlined align-middle" style="font-size:15px;">photo_camera</span>
                                        Upload photos and/or videos documenting the unit condition at move-in.
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Photos</label>
                                    <input type="file" name="inspection_photos[]" class="form-control @error('inspection_photos.0') is-invalid @enderror"
                                           accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple>
                                    <div class="form-text" style="font-size:0.72rem;">JPG, PNG, WEBP, HEIC &mdash; multiple allowed</div>
                                    @error('inspection_photos.0')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Videos</label>
                                    <input type="file" name="inspection_videos[]" class="form-control @error('inspection_videos.0') is-invalid @enderror"
                                           accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska" multiple>
                                    <div class="form-text" style="font-size:0.72rem;">MP4, MOV, AVI, MKV &mdash; multiple allowed</div>
                                    @error('inspection_videos.0')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
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
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#tenant_id').select2({
            placeholder: 'Search tenant by name or reference number...',
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("ajax.move.in.inspections.tenants") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { term: params.term };
                },
                processResults: function(response) {
                    return { results: response.results };
                }
            }
        });

        $('#tenant_id').on('select2:select', function(e) {
            var hasUnit = e.params.data.has_unit;
            $('#noUnitWarning').toggle(!hasUnit);
            $('#saveButton').prop('disabled', !hasUnit);
        });

        // Handle form submission
        $('#inspectionForm').on('submit', function() {
            var submitButton = $('#saveButton');
            var spinner = $('#submitSpinner');

            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });
    });
</script>
@endsection

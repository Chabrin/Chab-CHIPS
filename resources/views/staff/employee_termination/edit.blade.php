@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Employee Termination</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('employee.termination.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('employee.termination.update', $record->id) }}" method="POST" id="terminationForm" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-12 mb-2">
                                    <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required>
                                        <option value="{{ $record->staff->id }}">{{ $record->staff->full_names }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contract_id" class="form-label">Contract <span style="color:red">*</span></label>
                                    <select class="form-select" name="contract_id" id="contract_id" required>
                                        <option value="{{ $record->contract->id }}">
                                            {{ $record->contract->staff_number }} - {{ $record->staff->full_names }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="years_served" class="form-label">Years Served <span style="color:red">*</span></label>
                                    <input type="number" class="form-control" name="years_served" id="years_served" min="0" required value="{{ $record->years_served }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pay_per_year" class="form-label">Pay Per Year <span style="color:red">*</span></label>
                                    <input type="number" class="form-control" name="pay_per_year" id="pay_per_year" min="0" required value="{{ $record->pay_per_year }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="severance_payment" class="form-label">Severance Payment <span style="color:red">*</span></label>
                                    <input type="number" class="form-control" name="severance_payment" id="severance_payment" min="0" required value="{{ $record->severerance_payment }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="termination_reason_id" class="form-label">Termination Reason <span style="color:red">*</span></label>
                                    <select class="form-select" name="termination_reason_id" id="termination_reason_id" required>
                                        <option value="1" {{ $record->termination_reason_id == 1 ? 'selected' : '' }}>Retirement</option>
                                        <option value="2" {{ $record->termination_reason_id == 2 ? 'selected' : '' }}>Resignation</option>
                                        <option value="3" {{ $record->termination_reason_id == 3 ? 'selected' : '' }}>Termination</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="documents" class="form-label">Supporting Documents</label>
                                    <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                                    
                                </div>
                                <div class="col-md-12 mb-3">
                                    @if($record->documentTrackers && $record->documentTrackers->count())
                                        <div class="mt-2">
                                            <strong>Existing Documents:</strong>
                                            <ul>
                                                @foreach($record->documentTrackers as $doc)
                                                    <li class="d-flex justify-content-between">
                                                        <a href="{{ asset('storage/' . $doc->params) }}" target="_blank">{{ $doc->documents }}</a>
                                                        <button type="button" class="btn btn-sm btn-light ms-2 delete-document-btn" data-id="{{ $doc->id }}">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <hr class="mt-4 mb-4">
                            <div class="mb-3">
                                <h3 class="form-label mb-3">Severance Elements</h3>
                                <div id="severance-elements-list">
                                    @foreach($record->elements as $idx => $el)
                                    <div class="row g-2 severance-element-row mb-2">
                                        <div class="col-md-6">
                                            <select class="form-select" name="severance_elements[{{ $idx }}][element_id]" required>
                                                <option value="{{ $el->element_id }}" selected>{{ $el->element ? $el->element->element_name : '' }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" class="form-control" name="severance_elements[{{ $idx }}][amount]" min="0" value="{{ $el->amount }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-light remove-severance-element"><span class="material-symbols-outlined">Remove</button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" id="addSeveranceElementBtn">Add Severance Element</button>
                            </div>
                            @include('partials.form-actions', ['form' => 'terminationForm', 'buttonId' => 'editButton', 'cancelUrl' => route('employee.termination.index')])
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    // You can reuse most of the JS from create.blade.php, but pre-fill selects and values as needed.
    // For staff and contract selects, you may want to disable them or reload options via AJAX if you allow changing.
    // For severance elements, you can allow adding/removing as in create.

    // Remove repeater row
    $(document).on('click', '.remove-severance-element', function() {
        $(this).closest('.severance-element-row').remove();
    });

    // Handle form submission spinner
    $('#terminationForm').on('submit', function() {
        var submitButton = $('#editButton');
        var spinner = $('#submitSpinner');
        spinner.removeClass('d-none');
        submitButton.prop('disabled', true);
        submitButton.css('opacity', '0.6');
    });

    $(document).on('click', '.delete-document-btn', function() {
        if (!confirm('Are you sure you want to delete this document?')) return;
        var docId = $(this).data('id');
        var btn = $(this);
        $.ajax({
            url: '/ajax/hr/employee/termination/document/' + docId + '/delete',
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(res) {
                btn.closest('li').remove();
            },
            error: function() {
                alert('Failed to delete document.');
            }
        });
    });

    let severanceElementOptions = '';
    let elementIndex = {{ $record->elements->count() ?? 1 }};
    
    // Fetch elements for repeater
    $.ajax({
        url: "{{ url('/ajax/payroll/elements/list/simpler') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.elements && Array.isArray(response.elements)) {
                severanceElementOptions = response.elements.map(function(el) {
                    return `<option value="${el.id}">${el.element_name}</option>`;
                }).join('');
                // Set options for existing rows if needed
                $('#severance-elements-list select').each(function() {
                    if (!$(this).val()) {
                        $(this).html('<option value="">Select Element</option>' + severanceElementOptions);
                    }
                });
            }
        },
        error: function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load severance elements.');
        }
    });
    
    // When adding a new repeater row
    $('#addSeveranceElementBtn').on('click', function() {
        let row = `
        <div class="row g-2 severance-element-row mb-2">
            <div class="col-md-6">
                <select class="form-select entry-type-select" name="severance_elements[${elementIndex}][element_id]" required>
                    <option value="">Select Element</option>
                    ${severanceElementOptions}
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" class="form-control" name="severance_elements[${elementIndex}][amount]" min="0" placeholder="Amount" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-light remove-severance-element"><span class="material-symbols-outlined">Remove</button>
            </div>
        </div>`;
        $('#severance-elements-list').append(row);
        $('.entry-type-select').select2({
            placeholder: 'Select entry type',
            allowClear: true,
            width: '100%'
        });
        elementIndex++;
    });
    
    // Remove repeater row
    $(document).on('click', '.remove-severance-element', function() {
        $(this).closest('.severance-element-row').remove();
    });
});
</script>
@endsection
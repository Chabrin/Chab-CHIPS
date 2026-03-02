@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Staff Contracts</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="staffContractCreateForm" class="btn btn-primary btn-sm d-flex align-items-center" id="createButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save Conract</span>
                            </button>
                            <a href="{{ route('contract.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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


                        <form action="{{ route('contract.store') }}" method="POST" id="staffContractCreateForm">
                            @csrf

                            <h3 class="mb-3">Staff Contracts</h3>
                            <hr  class="mb-3 mt-3"/>
                            <div class="row">
                                <div class="mb-3 col-6">
                                    <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required>
                                        <!-- Populate dynamically -->
                                    </select>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="staff_positions" class="form-label">Positions</label>
                                    <select class="form-select" name="staff_positions" id="staff_positions" required>
                                        <option value="" disabled selected>Select positions</option>
                                        <!-- Options to be populated dynamically -->
                                    </select>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="date_start" class="form-label"> Date Start<span style="color:red">*</span></label>
                                    <input type="date" class="form-control" name="date_start" id="date_start" required>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="date_end" class="form-label"> Date End<span style="color:red">*</span></label>
                                    <input type="date" class="form-control" name="date_end" id="date_end" required>
                                </div>
                                
                                <div class="mb-3 col-12">
                                    <label for="basic_salary" class="form-label">Basic Salary</label>
                                    <input type="number" class="form-control" name="basic_salary" id="basic_salary" min="0">
                                </div>
                            </div>

                            <div class="mt-4">
                                <h3>Staff Specific Elements</h3>
                                <hr class="mb-4 mt-3">
                                <div id="elementsRepeater">
                                    <div class="row element-row mb-2">
                                        <div class="mb-3 col-6">
                                            <label class="form-label">Element</label>
                                            <select class="form-select element-select" name="elements[0][element]">
                                                <!-- Populate dynamically -->
                                            </select>
                                        </div>
                                        <div class="mb-3 col-6">
                                            <label class="form-label">Entry Type</label>
                                            <select class="form-select entry-type-select" name="elements[0][entry_type]">
                                                <option value="" disabled selected>Select entry type</option>
                                                <option value="2">Allowance</option>
                                                <option value="1">Deduction</option>
                                            </select>
                                        </div>
                                        <div class="mb-3 col-10">
                                            <label class="form-label">Amount</label>
                                            <input type="number" class="form-control" name="elements[0][amount]" min="0">
                                        </div>
                                        <div class="mb-3 col-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-light btn-sm d-flex align-items-center remove-element-row"><span class="material-symbols-outlined me-2">remove</span> Remove</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addElementRow"><span class="material-symbols-outlined">add</span> Add Element</button>
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

        $.ajax({
            url: "{{ url('/ajax/hr/staff/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $staff_id = $('#staff_id');
                $staff_id.empty();
                $staff_id.append($('<option>', {
                    value: '',
                    text: 'Select staff',
                    disabled: true,
                    selected: true
                }));
                if (response.staff && Array.isArray(response.staff)) {
                    response.staff.forEach(function(staff) {
                        $staff_id.append($('<option>', {
                            value: staff.id,
                            text: staff.full_names
                        }));
                    });
                }

                // Check if Select2 is already initialized and destroy it
                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }
                
                // Initialize Select2
                $staff_id.select2({
                    placeholder: 'Select Staff',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });

        $.ajax({
            url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $staff_positions = $('#staff_positions');
                $staff_positions.empty();
                $staff_positions.append($('<option>', {
                    value: '',
                    text: 'Select staff positions',
                    disabled: true,
                    selected: true
                }));
                if (response.staff_positions && Array.isArray(response.staff_positions)) {
                    response.staff_positions.forEach(function(position) {
                        $staff_positions.append($('<option>', {
                            value: position.id,
                            text: position.position_name
                        }));
                    });
                }

                // Check if Select2 is already initialized and destroy it
                if ($staff_positions.hasClass("select2-hidden-accessible")) {
                    $staff_positions.select2('destroy');
                }
                
                // Initialize Select2
                $staff_positions.select2({
                    placeholder: 'Select Position',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff positions.');
            }
        });

        $.ajax({
            url: "{{ url('/ajax/payroll/elements/list/simpler') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $element = $('#element');
                $element.empty();
                $element.append($('<option>', {
                    value: '',
                    text: 'Select element',
                    disabled: true,
                    selected: true
                }));
                if (response.elements && Array.isArray(response.elements)) {
                    response.elements.forEach(function(el) {
                        $element.append($('<option>', {
                            value: el.id,
                            text: el.element_name
                        }));
                    });
                }
        
                // Check if Select2 is already initialized and destroy it
                if ($element.hasClass("select2-hidden-accessible")) {
                    $element.select2('destroy');
                }
                
                // Initialize Select2
                $element.select2({
                    placeholder: 'Select Element',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load elements.');
            }
        });

        function populateElementSelect($select) {
            $.ajax({
                url: "{{ url('/ajax/payroll/elements/list/simpler') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $select.empty();
                    $select.append($('<option>', {
                        value: '',
                        text: 'Select element',
                        disabled: true,
                        selected: true
                    }));
                    if (response.elements && Array.isArray(response.elements)) {
                        response.elements.forEach(function(el) {
                            $select.append($('<option>', {
                                value: el.id,
                                text: el.element_name
                            }));
                        });
                    }
                    if ($select.hasClass("select2-hidden-accessible")) {
                        $select.select2('destroy');
                    }
                    $select.select2({
                        placeholder: 'Select Element',
                        allowClear: true
                    });
                }
            });

            $('.entry-type-select').select2({
                placeholder: 'Select entry type',
                allowClear: true,
                width: '100%'
            });
        }

        $('.entry-type-select').select2({
            placeholder: 'Select entry type',
            allowClear: true,
            width: '100%'
        });

        // Initial population for the first row
        populateElementSelect($('.element-select').first());

        // Add new element row
        $('#addElementRow').on('click', function() {
            let index = $('#elementsRepeater .element-row').length;
            let $newRow = $(`
                <div class="row element-row mb-2">
                    <div class="mb-3 col-6">
                        <label class="form-label">Element</label>
                        <select class="form-select element-select" name="elements[${index}][element]"></select>
                    </div>
                    <div class="mb-3 col-6">
                        <label class="form-label">Entry Type</label>
                        <select class="form-select entry-type-select" name="elements[${index}][entry_type]">
                            <option value="" disabled selected>Select entry type</option>
                            <option value="1">Allowance</option>
                            <option value="2">Deduction</option>
                        </select>
                    </div>
                    <div class="mb-3 col-10">
                        <label class="form-label">Amount</label>
                        <input type="number" class="form-control" name="elements[${index}][amount]" min="0">
                    </div>
                    <div class="mb-3 col-2 d-flex align-items-end">
                        <button type="button" class="btn btn-light btn-sm d-flex align-items-center remove-element-row"><span class="material-symbols-outlined me-2">remove</span> Remove</button>
                    </div>
                </div>
            `);
            $('#elementsRepeater').append($newRow);
            populateElementSelect($newRow.find('.element-select'));
        });

        // Remove element row
        $(document).on('click', '.remove-element-row', function() {
            $(this).closest('.element-row').remove();
            // Optionally, re-index names if needed for backend
        });

        // Handle form submission
        $('#staffContractCreateForm').on('submit', function() {
            var submitButton = $('#createButton');
            var spinner = $('#submitSpinner');

            // Show spinner and disable button
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });


    });
</script>
@endsection
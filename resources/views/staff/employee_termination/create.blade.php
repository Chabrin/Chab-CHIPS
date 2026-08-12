@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Employee Termination</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="terminationForm" class="btn btn-primary btn-sm d-flex align-items-center" id="createButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save Termination</span>
                            </button>
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

                        <form action="{{ route('employee.termination.store') }}" method="POST" id="terminationForm" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-12 mb-2">
                                    <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required></select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contract_id" class="form-label">Contract <span style="color:red">*</span></label>
                                    <select class="form-select" name="contract_id" id="contract_id" required></select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="years_served" class="form-label">Years Served <span style="color:red">*</span></label>
                                    <input type="number" class="form-control" name="years_served" id="years_served" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pay_per_year" class="form-label">Pay Per Year <span style="color:red">*</span></label>
                                    <input type="number" class="form-control" name="pay_per_year" id="pay_per_year" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="severance_payment" class="form-label">Severance Payment <span style="color:red">*</span></label>
                                    <input type="number" class="form-control" name="severance_payment" id="severance_payment" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="termination_reason_id" class="form-label">Termination Reason <span style="color:red">*</span></label>
                                    <select class="form-select" name="termination_reason_id" id="termination_reason_id" required>
                                        <option value="1">Retirement</option>
                                        <option value="2">Resignation</option>
                                        <option value="3">Termination</option>
                                        
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="documents" class="form-label">Supporting Documents</label>
                                    <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                                </div>
                            </div>
                            <hr class="mt-4 mb-4">
                            <div class="mb-3">
                                <h3 class="form-label mb-3">Severance Elements</h3>
                                <div id="severance-elements-list">
                                    <div class="row g-2 severance-element-row mb-2">
                                        <div class="col-md-6">
                                            <select class="form-select entry-type-select" name="severance_elements[0][element_id]" required>
                                                <option value="">Select Element</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" class="form-control" name="severance_elements[0][amount]" min="0" placeholder="Amount" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-light remove-severance-element"><span class="material-symbols-outlined">Remove</span></button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" id="addSeveranceElementBtn">Add Severance Element</button>
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
    // Staff select
    $.ajax({
        url: "{{ url('/ajax/hr/staff/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $staff_id = $('#staff_id');
            $staff_id.empty().append('<option value="">Select staff</option>');
            if (response.staff && Array.isArray(response.staff)) {
                response.staff.forEach(function(staff) {
                    $staff_id.append($('<option>', {
                        value: staff.id,
                        text: staff.full_names
                    }));
                });
            }
            if ($staff_id.hasClass("select2-hidden-accessible")) $staff_id.select2('destroy');
            $staff_id.select2({ placeholder: 'Select Staff', allowClear: true });
        }
    });

    // Contract select (populate after staff is selected)
    $('#staff_id').on('change', function() {
        let staffId = $(this).val();
        let $contract_id = $('#contract_id');
        $contract_id.empty().append('<option value="">Select contract</option>');
        if (!staffId) return;
        $.ajax({
            url: "{{ url('/ajax/hr/employee/termination/contracts/list') }}/" + staffId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.contracts && Array.isArray(response.contracts)) {
                    response.contracts.forEach(function(contract) {
                        let $option = $('<option>', {
                            value: contract.id,
                            text: (contract.staff_number ? contract.staff_number + ' - ' : '') + (contract.employee ? contract.employee.name : '') + (contract.position ? ' (' + contract.position.position_name + ')' : ''),
                            'data-date_start': contract.date_start,
                            'data-basic_salary': contract.basic_salary,
                            'data-elements': JSON.stringify(contract.elements || [])
                        });
                        $contract_id.append($option);
                    });
                }
                if ($contract_id.hasClass("select2-hidden-accessible")) $contract_id.select2('destroy');
                $contract_id.select2({ placeholder: 'Select Contract', allowClear: true });
            }
        });
    });
    
    // When contract is selected, calculate years served and pay per year
    $('#contract_id').on('change', function() {
        let $selected = $(this).find('option:selected');
        let dateStart = $selected.data('date_start');
        let basicSalary = parseFloat($selected.data('basic_salary')) || 0;
        let elements = [];
        try {
            elements = JSON.parse($selected.attr('data-elements')) || [];
        } catch (e) {
            elements = [];
        }

        console.log('Elements data:', elements);
        console.log('Basic Salary data:', basicSalary);
    
        // Always define yearsServed
        let yearsServed = 0;
        if (dateStart) {
            let startDate = new Date(dateStart);
            let today = new Date();
            yearsServed = today.getFullYear() - startDate.getFullYear();
            if (
                today.getMonth() < startDate.getMonth() ||
                (today.getMonth() === startDate.getMonth() && today.getDate() < startDate.getDate())
            ) {
                yearsServed--;
            }
            yearsServed = yearsServed < 0 ? 0 : yearsServed;
            $('#years_served').val(yearsServed);
        } else {
            $('#years_served').val('');
        }
    
        // Calculate pay per year (all values are monthly, so multiply by 12)
        let totalMonthlyAllowances = 0;
        let totalMonthlyDeductions = 0;
        elements.forEach(function(el) {
            if (el.entry_type == "2") { // Allowance
                totalMonthlyAllowances += parseFloat(el.amount) || 0;
            } else if (el.entry_type == "1") { // Deduction
                totalMonthlyDeductions += parseFloat(el.amount) || 0;
            }
        });
        let annualBasicSalary = basicSalary * 12;
        let annualAllowances = totalMonthlyAllowances * 12;
        let annualDeductions = totalMonthlyDeductions * 12;
        let payPerYear = annualBasicSalary + annualAllowances - annualDeductions;
        $('#pay_per_year').val(payPerYear > 0 ? payPerYear : 0);
    
        // Populate severance elements repeater with contract elements
        let $severanceList = $('#severance-elements-list');
        $severanceList.empty();

        if (elements.length === 0) {
            console.log("No elements found for this contract.");
            // Optionally add a blank row if no elements
            let row = `
            <div class="row g-2 severance-element-row mb-2">
                <div class="col-md-6">
                    <select class="form-select" name="severance_elements[0][element_id]" required>
                        <option value="">Select Element</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="severance_elements[0][amount]" min="0" placeholder="Amount" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-light remove-severance-element"><span class="material-symbols-outlined">Remove</button>
                </div>
            </div>`;
            $severanceList.append(row);
        } else {
            console.log("Populating severance elements:", elements);
            elements.forEach(function(el, idx) {
                let row = `
                <div class="row g-2 severance-element-row mb-2">
                    <div class="col-md-6">
                        <select class="form-select" name="severance_elements[${idx}][element_id]" required>
                            <option value="${el.id}" selected>${el.element_name}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control" name="severance_elements[${idx}][amount]" min="0" value="${el.amount}" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-light remove-severance-element"><span class="material-symbols-outlined">Remove</button>
                    </div>
                </div>`;
                $severanceList.append(row);
            });
        }
    
        // Calculate monthly gross salary
        let monthlyGrossSalary = basicSalary + totalMonthlyAllowances - totalMonthlyDeductions;

        // Calculate severance payment
        let severancePayment = (monthlyGrossSalary / 30) * 15 * yearsServed;
        $('#severance_payment').val(severancePayment > 0 ? severancePayment : 0);
    });

    // Severance elements select options
    let severanceElementOptions = '';
    let elementIndex = 1;
    
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
                // Set options for first row
                $('#severance-elements-list select').html('<option value="">Select Element</option>' + severanceElementOptions);
            }
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
                <button type="button" class="btn btn-light remove-severance-element"><span class="material-symbols-outlined">Remove</span></button>
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

    // Handle form submission spinner
    $('#terminationForm').on('submit', function() {
        var submitButton = $('#createButton');
        var spinner = $('#submitSpinner');
        spinner.removeClass('d-none');
        submitButton.prop('disabled', true);
        submitButton.css('opacity', '0.6');
    });

    $('.entry-type-select').select2({
        placeholder: 'Select entry type',
        allowClear: true,
        width: '100%'
    });
});
</script>
@endsection
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
                                <span>Update Conract</span>
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


                        <form action="{{ route('contract.update', ['id' => $contract->id]) }}" method="POST" id="staffContractCreateForm" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                        
                            <h3 class="mb-3">Staff Contracts</h3>
                            <hr class="mb-3 mt-3"/>
                            <div class="row">
                                <div class="mb-3 col-6">
                                    <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required>
                                        <!-- Populate dynamically, set selected="{{ $contract->staff_id }}" -->
                                    </select>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="staff_positions" class="form-label">Positions</label>
                                    <select class="form-select" name="staff_positions" id="staff_positions" required>
                                        <!-- Populate dynamically, set selected="{{ $contract->position_id }}" -->
                                    </select>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="date_start" class="form-label"> Date Start<span style="color:red">*</span></label>
                                    <input type="date" class="form-control" name="date_start" id="date_start" required value="{{ \Carbon\Carbon::parse($contract->date_start)->format('Y-m-d') }}">
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="date_end" class="form-label"> Date End<span style="color:red">*</span></label>
                                    <input type="date" class="form-control" name="date_end" id="date_end" required value="{{ \Carbon\Carbon::parse($contract->date_end)->format('Y-m-d') }}">
                                </div>
                                <div class="mb-3 col-12">
                                    <label for="basic_salary" class="form-label">Basic Salary</label>
                                    <input type="number" class="form-control" name="basic_salary" id="basic_salary" min="0" step="0.01" value="{{ $contract->basic_salary }}">
                                </div>
                                <div class="mb-3 col-12">
                                    <label for="signed_contract" class="form-label">Signed Contract (PDF)</label>
                                    @if($contract->signedDocument)
                                    <div class="mb-1">
                                        <a href="{{ route('documents.view', $contract->signedDocument->id) }}" target="_blank">{{ $contract->signedDocument->file_name }}</a>
                                        <span class="text-muted small">- uploading a new file replaces it</span>
                                    </div>
                                    @endif
                                    <input type="file" class="form-control @error('signed_contract') is-invalid @enderror" name="signed_contract" id="signed_contract" accept=".pdf,application/pdf">
                                    <small class="text-muted">Upload the physically signed contract as a PDF (max 5MB).</small>
                                    @error('signed_contract')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        
                            <div class="mt-4">
                                <h3>Recurring for all staff</h3>
                                <hr class="mb-3 mt-3">
                                <p class="text-muted small mb-2">These apply to every staff member automatically when payroll runs, worked out from the pay on this contract. They can't be edited here.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0" id="allStaffItems">
                                        <thead>
                                            <tr><th>Item</th><th>Type</th><th>How it is worked out</th><th class="text-end">Amount</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="4" class="text-muted">Enter a basic salary to see the amounts.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h3>Staff Specific Elements</h3>
                                <hr class="mb-4 mt-3">
                                @if($hiddenElements->isNotEmpty())
                                <div class="alert alert-warning">
                                    <strong>These items are no longer kept on contracts and are removed when you save:</strong>
                                    <ul class="mb-1">
                                        @foreach($hiddenElements as $hidden)
                                        <li>
                                            {{ $hidden->payrollElement?->element_name ?? 'Deleted element' }} ({{ number_format((float) $hidden->amount, 2) }}) -
                                            @if(!$hidden->payrollElement) the element no longer exists
                                            @elseif($hidden->payrollElement->isOneTime()) one-time item: enter it on Monthly Payroll Items
                                            @elseif($hidden->payrollElement->isStatutory()) calculated by the payroll run for every staff member
                                            @else comes from the basic salary
                                            @endif
                                        </li>
                                        @endforeach
                                    </ul>
                                    <small>The payroll run already ignores them.</small>
                                </div>
                                @endif
                                <div id="elementsRepeater">
                                    @foreach($visibleElements->values() as $i => $element)
                                        <div class="row element-row mb-2">
                                            <div class="mb-3 col-6">
                                                <label class="form-label">Element</label>
                                                <select class="form-select element-select" name="elements[{{ $i }}][element]" data-selected="{{ $element->element_id }}" required>
                                                    <!-- Options populated by JS -->
                                                </select>
                                            </div>
                                            <div class="mb-3 col-6">
                                                <label class="form-label">Entry Type</label>
                                                <select class="form-select entry-type-select" name="elements[{{ $i }}][entry_type]" required>
                                                    <option value="" disabled>Select entry type</option>
                                                    <option value="2" {{ $element->entry_type_id == 2 ? 'selected' : '' }}>Allowance</option>
                                                    <option value="1" {{ $element->entry_type_id == 1 ? 'selected' : '' }}>Deduction</option>
                                                </select>
                                            </div>
                                            <div class="mb-3 col-10">
                                                <label class="form-label">Amount</label>
                                                <input type="number" class="form-control" name="elements[{{ $i }}][amount]" min="0" step="0.01" value="{{ $element->amount }}">
                                            </div>
                                            <div class="mb-3 col-2 d-flex align-items-end">
                                                <button type="button" class="btn btn-danger remove-element-row" style="height:38px;">Remove</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-success" id="addElementRow">Add Element</button>
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
                // Set selected value from PHP
                $staff_id.val("{{ $contract->staff_id }}").trigger('change');
                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }
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
                // Set selected value from PHP
                $staff_positions.val("{{ $contract->position_id }}").trigger('change');
                if ($staff_positions.hasClass("select2-hidden-accessible")) {
                    $staff_positions.select2('destroy');
                }
                $staff_positions.select2({
                    placeholder: 'Select Position',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff positions.');
            }
        });


        @include('staff.contracts.partials.element-scripts')

        // Handle form submission spinner
        $('#staffContractCreateForm').on('submit', function() {
            var submitButton = $('#createButton');
            var spinner = $('#submitSpinner');
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });


    });
</script>
@endsection
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


                        <form action="{{ route('contract.update', ['id' => $contract->id]) }}" method="POST" id="staffContractCreateForm" autocomplete="off" enctype="multipart/form-data" data-file-slots>
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
                                @include('staff.contracts.partials.period-fields', ['contract' => $contract, 'openEnded' => $openEnded ?? false])
                                <div class="mb-3 col-12">
                                    <label for="basic_salary" class="form-label">Basic Salary</label>
                                    <input type="number" class="form-control" name="basic_salary" id="basic_salary" min="0" step="0.01" value="{{ $contract->basic_salary }}">
                                </div>
                                <div class="mb-3 col-12">
                                    @php
                                        // The contract's own signed PDF, or else the one saved with the staff record (attached when this form is saved)
                                        $onRecord = $contract->signedDocument ?: ($staffCopy ?? null);
                                        $signedDocument = $onRecord ? [
                                            'id' => $onRecord->id,
                                            'name' => $onRecord->file_name,
                                            'uploaded' => $onRecord->created_at?->format('d M Y'),
                                            'by' => $onRecord->uploaded_by ? \App\Models\User::whereKey($onRecord->uploaded_by)->value('name') : null,
                                            'attachedOnSave' => !$contract->signedDocument,
                                        ] : null;
                                    @endphp
                                    @include('staff.contracts.partials.signed-contract', ['document' => $signedDocument, 'optional' => true])
                                </div>
                            </div>
                            @include('staff.contracts.partials.all-staff-table')

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
                                                    <option value="2" {{ $element->entry_type_id == 2 ? 'selected' : '' }}>Earnings</option>
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
                                            @include('staff.contracts.partials.terms-fields', ['index' => $i, 'row' => $element])
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-success" id="addElementRow">Add Element</button>
                            </div>
                            @include('partials.form-actions', ['form' => 'staffContractCreateForm', 'buttonId' => 'createButton', 'cancelUrl' => route('contract.index')])
                        </form>

                        {{-- Who changed what on this contract, when, and what it was before. Read only. --}}
                        @if(auth()->user()?->hasPermission('read_contracts') || auth()->user()?->hasPermission('read_staff'))
                        <div class="mt-4 pt-3 border-top">
                            <h5 class="mb-2">History</h5>
                            @include('staff.partials.history-panel', ['url' => route('contract.history', $contract->id), 'lazy' => false])
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/payroll-all-staff-rows.js') }}?v={{ filemtime(public_path('assets/js/payroll-all-staff-rows.js')) }}"></script>
<script src="{{ asset('assets/js/contract-element-terms.js') }}?v={{ filemtime(public_path('assets/js/contract-element-terms.js')) }}"></script>
<script src="{{ asset('assets/js/contract-exclusions.js') }}?v={{ filemtime(public_path('assets/js/contract-exclusions.js')) }}"></script>
<script src="{{ asset('assets/js/contract-period.js') }}?v={{ filemtime(public_path('assets/js/contract-period.js')) }}"></script>
<script src="{{ asset('assets/js/file-slot.js') }}?v={{ filemtime(public_path('assets/js/file-slot.js')) }}"></script>
<script src="{{ asset('assets/js/signed-contract.js') }}?v={{ filemtime(public_path('assets/js/signed-contract.js')) }}"></script>
<script>
    $(document).ready(function() {

        // Staff in a category with no contract end date (permanent staff) are asked for probation only
        var staffInfo = {};
        $('#staff_id').on('change', function() {
            var info = staffInfo[$(this).val()];
            if (info) {
                // A contract that already has an end date or period keeps its fields, whatever the category
                var hasDates = $('#date_end').val() !== '' || $('#period_value').val() !== '';
                window.ContractPeriod.setOpenEnded(info.has_end_date === false && !hasDates);
            }
        });

        // The signed contract on record: View and Remove, and the upload button only once it is removed
        window.SignedContract.attach(document, {
            token: '{{ csrf_token() }}',
            notify: function(kind, text) { toastr[kind](text); }
        });

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
                        staffInfo[staff.id] = staff;
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
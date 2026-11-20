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

                        {{-- Add Staff goes straight on to this form, so the result of the password link email is shown here as it would be on the profile --}}
                        @include('staff.partials.account-setup-notice')
                        @include('staff.partials.helb-prompt')

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


                        <form action="{{ route('contract.store') }}" method="POST" id="staffContractCreateForm" autocomplete="off" enctype="multipart/form-data" data-file-slots>
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
                                @include('staff.contracts.partials.period-fields', ['contract' => null, 'openEnded' => false])
                                
                                <div class="mb-3 col-12">
                                    <label for="basic_salary" class="form-label">Basic Salary</label>
                                    <input type="number" class="form-control" name="basic_salary" id="basic_salary" min="0" step="0.01">
                                </div>
                                <div class="mb-3 col-12">
                                    @include('staff.contracts.partials.signed-contract', ['document' => null, 'optional' => true])
                                </div>
                            </div>

                            @include('staff.contracts.partials.all-staff-table')

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
                                                <option value="2">Earnings</option>
                                                <option value="1">Deduction</option>
                                            </select>
                                        </div>
                                        <div class="mb-3 col-10">
                                            <label class="form-label">Amount</label>
                                            <input type="number" class="form-control" name="elements[0][amount]" min="0" step="0.01">
                                        </div>
                                        <div class="mb-3 col-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-light btn-sm d-flex align-items-center remove-element-row"><span class="material-symbols-outlined me-2">remove</span> Remove</button>
                                        </div>
                                        @include('staff.contracts.partials.terms-fields', ['index' => 0, 'row' => null])
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addElementRow"><span class="material-symbols-outlined">add</span> Add Element</button>
                            </div>

                            @include('partials.form-actions', ['form' => 'staffContractCreateForm', 'buttonId' => 'createButton', 'cancelUrl' => route('contract.index')])
                        </form>
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

        // ── Signed contract already uploaded with the staff record: shown with View and Remove instead of an upload button ───────
        var staffSignedContracts = {{ \Illuminate\Support\Js::from($staffSignedContracts) }};
        var signedContractBox = window.SignedContract.attach(document, {
            token: '{{ csrf_token() }}',
            notify: function(kind, text) { toastr[kind](text); },
            onRemoved: function() { delete staffSignedContracts[$('#staff_id').val()]; }
        });

        function showSignedContractOnFile() {
            var onFile = staffSignedContracts[$('#staff_id').val()];
            if (onFile) {
                signedContractBox.show(onFile);
            } else {
                signedContractBox.showUpload();
            }
        }

        // Staff in a category with no contract end date (permanent staff) are asked for probation only
        var staffInfo = {};
        function applyStaffCategory() {
            var info = staffInfo[$('#staff_id').val()];
            window.ContractPeriod.setOpenEnded(!!info && info.has_end_date === false);
        }

        $('#staff_id').on('change', function() {
            showSignedContractOnFile();
            applyStaffCategory();
        });

        // ── Staff dropdown ───────────────────────────────────────────────────
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

                // Preselect staff when coming from the staff profile "Add Contract" link
                var preselectedStaff = "{{ request('staff_id') }}";
                if (preselectedStaff) {
                    $staff_id.val(preselectedStaff);
                }
                applyStaffCategory();

                // Check if Select2 is already initialized and destroy it
                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }

                // Initialize Select2
                $staff_id.select2({
                    placeholder: 'Select Staff',
                    allowClear: true
                });

                showSignedContractOnFile();
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });

        // ── Positions dropdown ───────────────────────────────────────────────
        $.ajax({
            url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}?active=1",
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

                // Preselect position from the link
                var preselectedPosition = "{{ request('position_id') }}";
                if (preselectedPosition) {
                    $staff_positions.val(preselectedPosition);
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

        @include('staff.contracts.partials.element-scripts')

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
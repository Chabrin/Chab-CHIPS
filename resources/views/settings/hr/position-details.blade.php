@php
use Illuminate\Support\Facades\Crypt;
@endphp
@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Position Details - {{ $position->position_name }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('settings.hr.positions') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Summary Header -->
                        <div class="mb-3">
                            <h5 class="mb-1">{{ $position->position_name }}</h5>
                            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                <strong>Basic Salary:</strong> {{ $position->basic_salary ? 'UGX ' . number_format($position->basic_salary, 2) : 'Not Set' }} |
                                <strong>Reports To:</strong> {{ $position->reportsTo->position_name ?? 'None' }} |
                                <strong>Staff Count:</strong> {{ $position->staff->count() }}
                            </p>
                        </div>

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-3" id="positionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">work</span>
                                    Position Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">group</span>
                                    Staff in Position ({{ $position->staff->count() }})
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="elements-tab" data-bs-toggle="tab" data-bs-target="#elements" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">payments</span>
                                    Payroll Elements
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="positionTabContent">
                            <!-- Position Details Tab -->
                            <div class="tab-pane fade show active" id="details" role="tabpanel">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">info</span>
                                            Basic Information
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row g-1">
                                            <div class="col-3"><small class="text-muted">Position Name:</small></div>
                                            <div class="col-3"><small><strong>{{ $position->position_name }}</strong></small></div>

                                            <div class="col-3"><small class="text-muted">Basic Salary:</small></div>
                                            <div class="col-3"><small>{{ $position->basic_salary ? 'UGX ' . number_format($position->basic_salary, 2) : '-' }}</small></div>

                                            <div class="col-3"><small class="text-muted">Reports To:</small></div>
                                            <div class="col-3"><small>{{ $position->reportsTo->position_name ?? '-' }}</small></div>

                                            <div class="col-3"><small class="text-muted">Salary Account:</small></div>
                                            <div class="col-3"><small>{{ $position->salaryAccount->full_account ?? '-' }}</small></div>

                                            <div class="col-3"><small class="text-muted">Date Created:</small></div>
                                            <div class="col-3"><small>{{ $position->date_time ? $position->date_time->format('d M Y H:i') : '-' }}</small></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Staff in Position Tab -->
                            <div class="tab-pane fade" id="staff" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="staff-table" data-has-staff="{{ $position->staff->count() }}">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Staff ID</th>
                                                <th>Full Name</th>
                                                <th>Phone</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($position->staff as $index => $staff)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $staff->staff_number }}</td>
                                                <td>{{ $staff->full_names }}</td>
                                                <td>{{ $staff->mobile_number ?? '-' }}</td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No staff assigned to this position</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Payroll Elements Tab -->
                            <div class="tab-pane fade" id="elements" role="tabpanel">
                                <form id="payrollElementsForm" data-update-url="{{ route('settings.hr.positions.update-elements', Crypt::encrypt($position->id)) }}">
                                    @csrf
                                    <div class="row g-2">
                                        @foreach($allPayrollElements as $element)
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="elements[]"
                                                    value="{{ $element->id }}"
                                                    id="element_{{ $element->id }}"
                                                    {{ in_array($element->id, $assignedElementIds) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="element_{{ $element->id }}">
                                                    {{ $element->element_name }}
                                                    <small class="fst-italic text-muted">
                                                        @if($element->element_type == '1')
                                                        (Deduction)
                                                        @elseif($element->element_type == '2')
                                                        (Allowance)
                                                        @else
                                                        (Unknown)
                                                        @endif
                                                    </small>
                                                </label>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>

                                    @permission('update_staff_positions')
                                    <div class="mt-3">
                                        <button type="submit" id="saveElementsBtn" class="btn btn-primary">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpinner" role="status" aria-hidden="true"></span>
                                            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">save</span>
                                            <span id="saveBtnText">Save Changes</span>
                                        </button>
                                    </div>
                                    @endpermission
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card-header {
        border-bottom: 1px solid #e0e0e0;
    }

    .card-body .row.g-1>div {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        line-height: 1.5;
    }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .card {
        border: 1px solid #e0e0e0;
    }
</style>
@endsection

<script>
    $(document).ready(function() {
        // Initialize DataTable for staff
        var $staffTable = $('#staff-table');
        if ($staffTable.length && $staffTable.data('has-staff') > 0) {
            $staffTable.DataTable({
                paging: true,
                info: true,
                lengthChange: true,
                order: [
                    [1, 'asc']
                ]
            });
        }

        // Handle payroll elements form submission
        $('#payrollElementsForm').on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const $btn = $('#saveElementsBtn');
            const $spinner = $('#saveSpinner');
            const $btnText = $('#saveBtnText');

            // Disable button and show spinner
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $btnText.text('Saving...');

            const selectedElements = [];
            $('input[name="elements[]"]:checked').each(function() {
                selectedElements.push($(this).val());
            });

            $.ajax({
                url: $form.data('update-url'),
                method: 'POST',
                data: {
                    _token: $('input[name="_token"]').val(),
                    elements: selectedElements
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message || 'Failed to save changes');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'An error occurred while saving changes';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                    toastr.error(errorMsg);
                },
                complete: function() {
                    // Re-enable button and hide spinner
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    $btnText.text('Save Changes');
                }
            });
        });
    });
</script>
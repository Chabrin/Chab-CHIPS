@extends('layouts.main')

@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Employee Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            @if(isset($employee) && !$employee->confirmed)
                            <button id="confirmBtn" class="btn btn-success btn-sm d-flex align-items-center" type="button">
                                <span class="material-symbols-outlined me-1" id="confirmIcon">check_circle</span>
                                <span id="confirmText">Confirm Employee</span>
                                <span id="confirmSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                            </button>
                            @endif
                            <a href="{{ route('listemployees') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(isset($error))
                        <div class="alert alert-danger">{{ $error }}</div>
                        @endif
                        @if(isset($employee))
                        <div class="row">
                            <div class="col-md-12">
                                <table class="table mb-0 employee-details-table" style="border:none;">
                                    <tbody>
                                        <tr>
                                            <td><strong>Employee No:</strong></td>
                                            <td>{{ $employee->employee_no }}</td>
                                            <td><strong>First Name:</strong></td>
                                            <td>{{ $employee->first_name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Surname:</strong></td>
                                            <td>{{ $employee->surname }}</td>
                                            <td><strong>Other Name:</strong></td>
                                            <td>{{ $employee->other_name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email Address:</strong></td>
                                            <td>{{ $employee->email_address }}</td>
                                            <td><strong>National ID:</strong></td>
                                            <td>{{ $employee->national_id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone No:</strong></td>
                                            <td>{{ $employee->phone_no }}</td>
                                            <td><strong>Date of Birth:</strong></td>
                                            <td>{{ $employee->dob }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Date Employed:</strong></td>
                                            <td>{{ $employee->date_employed }}</td>
                                            <td><strong>Confirmed:</strong></td>
                                            <td id="confirmedStatus">{{ $employee->confirmed ? 'Yes' : 'No' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Department:</strong></td>
                                            <td>{{ optional($employee->department)->name }}</td>
                                            <td><strong>Gender:</strong></td>
                                            <td>{{ optional($employee->gender)->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Marital Status:</strong></td>
                                            <td>{{ optional($employee->maritalStatus)->name }}</td>
                                            <td><strong>Employment Terms:</strong></td>
                                            <td>{{ optional($employee->employmentTerms)->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Post Address:</strong></td>
                                            <td>{{ $employee->post_address }}</td>
                                            <td><strong>Physical Address:</strong></td>
                                            <td>{{ $employee->physical_address }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>County:</strong></td>
                                            <td>{{ $employee->county_id }}</td>
                                            <td><strong>Country:</strong></td>
                                            <td>{{ $employee->country_id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>ZIP:</strong></td>
                                            <td>{{ $employee->zip }}</td>
                                            <td><strong>Town:</strong></td>
                                            <td>{{ $employee->town_id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Passport Photo:</strong></td>
                                            <td colspan="3">
                                                @if($employee->passport_photo)
                                                <img src="{{ asset('storage/' . $employee->passport_photo) }}" alt="Passport Photo" style="max-width:120px;max-height:120px;">
                                                @else
                                                <span class="text-muted">No photo</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#confirmBtn').on('click', function() {
            var $btn = $(this);
            var $icon = $('#confirmIcon');
            var $text = $('#confirmText');
            var $spinner = $('#confirmSpinner');
            $btn.prop('disabled', true);
            $spinner.show();
            $icon.hide();
            $text.text('Confirming...');
            $.ajax({
                url: "{{ route('confirmemployee', ['employee_no' => Crypt::encrypt($employee->id)]) }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $text.text('Confirmed');
                        $btn.removeClass('btn-success').addClass('btn-secondary');
                        $spinner.hide();
                        $icon.show();
                        $icon.text('verified');
                    } else {
                        $text.text('Error');
                        $btn.prop('disabled', false);
                        $spinner.hide();
                        $icon.show();
                        alert(response.error || 'Failed to confirm.');
                    }
                },
                error: function(xhr) {
                    $text.text('Error');
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    $icon.show();
                    alert('Failed to confirm.');
                }
            });
        });
    });
</script>
@endsection
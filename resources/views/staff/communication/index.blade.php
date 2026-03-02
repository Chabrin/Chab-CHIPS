@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header">
                <h5>Send Communication</h5>
            </div>
            <div class="card-body">
                <form id="communication-form">
                    @csrf
                    <div class="mb-3">
                        <label for="comm_type" class="form-label">Communication Type</label>
                        <select class="form-select" id="comm_type" name="comm_type" required>
                            <option value="">Select Communication Type</option>
                            <option value="email">Email</option>
                            <option value="sms">Text (SMS)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="recipient_type" class="form-label">Recipient Type</label>
                        <select class="form-select" id="recipient_type" name="recipient_type" required>
                            <option value="">Select Recipient Type</option>
                            <option value="staff">Select Staff</option>
                            <option value="position">Select Department/Position</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="staff-select-div">
                        <label for="staff_id" class="form-label">Select Staff</label>
                        <select class="form-select" id="staff_id" name="staff_ids[]" multiple></select>
                    </div>
                    <div class="mb-3 d-none" id="position-select-div">
                        <label for="position_id" class="form-label">Select Department/Position</label>
                        <select class="form-select" id="position_id" name="position_ids[]" multiple></select>
                    </div>
                    <div class="mb-3 d-none" id="subject-div">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject">
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    <div class="mb-3 d-none" id="attachment-div">
                        <label for="attachment" class="form-label">Attach Document (optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                    </div>
                    <button type="submit" class="btn btn-primary" id="send-btn">
                        <span id="send-btn-text">Send</span>
                        <span id="send-btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Show/hide selects based on recipient type
    $('#recipient_type').on('change', function() {
        if ($(this).val() === 'staff') {
            $('#staff-select-div').removeClass('d-none');
            $('#position-select-div').addClass('d-none');
        } else {
            $('#staff-select-div').addClass('d-none');
            $('#position-select-div').removeClass('d-none');
        }
    });

    $('#comm_type').on('change', function() {
        if ($(this).val() === 'email') {
            $('#subject-div').removeClass('d-none');
            $('#subject').attr('required', true);
            $('#attachment-div').removeClass('d-none');
        } else {
            $('#subject-div').addClass('d-none');
            $('#subject').removeAttr('required');
            $('#attachment-div').addClass('d-none');
        }
    });

    // Load staff for multi-select
    $.ajax({
        url: "{{ url('/ajax/hr/staff/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $staff_id = $('#staff_id');
            $staff_id.empty();
            if (response.staff && Array.isArray(response.staff)) {
                response.staff.forEach(function(staff) {
                    $staff_id.append($('<option>', {
                        value: staff.id,
                        text: staff.full_names
                    }));
                });
            }
            if ($staff_id.hasClass("select2-hidden-accessible")) {
                $staff_id.select2('destroy');
            }
            $staff_id.select2({
                placeholder: 'Select Staff',
                allowClear: true,
                width: '100%'
            });
        },
        error: function() {
            toastr.error('Failed to load staff.');
        }
    });

    // Load positions for multi-select
    $.ajax({
        url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $position_id = $('#position_id');
            $position_id.empty();
            if (response.staff_positions && Array.isArray(response.staff_positions)) {
                response.staff_positions.forEach(function(position) {
                    $position_id.append($('<option>', {
                        value: position.id,
                        text: position.position_name
                    }));
                });
            }
            if ($position_id.hasClass("select2-hidden-accessible")) {
                $position_id.select2('destroy');
            }
            $position_id.select2({
                placeholder: 'Select Department/Position',
                allowClear: true,
                width: '100%'
            });
        },
        error: function() {
            toastr.error('Failed to load staff positions.');
        }
    });

    // Handle form submission
    $('#communication-form').on('submit', function(e) {
        e.preventDefault();
        // Show spinner
        $('#send-btn-text').addClass('d-none');
        $('#send-btn-spinner').removeClass('d-none');
        $('#send-btn').prop('disabled', true);
    
        let formData = new FormData(this);
        $.ajax({
            url: "{{ route('hr.communication.send') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                alert(res.message || 'Communication sent!');
                $('#communication-form')[0].reset();
                $('#staff-select-div, #position-select-div').addClass('d-none');
                location.reload();
            },
            error: function() {
                alert('Failed to send communication.');
            },
            complete: function() {
                // Hide spinner and enable button
                $('#send-btn-text').removeClass('d-none');
                $('#send-btn-spinner').addClass('d-none');
                $('#send-btn').prop('disabled', false);
            }
        });
    });
});
</script>
@endsection
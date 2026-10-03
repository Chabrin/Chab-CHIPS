@extends('layouts.main')
@section('content')

@php
$canReadStaff = auth()->user()->hasPermission('read_staff');
$canUpdateStaff = auth()->user()->hasPermission('update_staff');
$canCreateUsers = auth()->user()->hasPermission('create_users');
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Staff Management</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_staff')
                            <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Staff</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <div id="staff-preloader" class="text-center my-5">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading employees...</p>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="staff-table" style="visibility: hidden;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff No</th>
                                        <th>Full Names</th>
                                        <th>Position</th>
                                        <th>Gender</th>
                                        <th>Mobile Number</th>
                                        <th>National ID</th>
                                        <th>Zone Roles</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($staff as $index => $member)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $member->staff_number ?? '-' }}</td>
                                        <td>
                                            @if($canReadStaff)
                                            <a class="staff-link" href="{{ route('staff.show', $member->encrypted_id) }}">
                                                {{ $member->full_names }}
                                            </a>
                                            @else
                                            {{ $member->full_names }}
                                            @endif
                                        </td>
                                        <td>{{ $member->position->position_name ?? '-' }}</td>
                                        <td>{{ $member->gender ?? '-' }}</td>
                                        <td>{{ $member->mobile_number ?? '-' }}</td>
                                        <td>{{ $member->national_id ?? '-' }}</td>
                                        <td>@include('staff.partials.zone-role-badges', ['member' => $member])</td>
                                        <td>
                                            <span class="status-badge" data-status-id="{{ $member->current_status_id }}">{{ \App\Models\Staff::STATUSES[$member->current_status_id] ?? 'Unknown' }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-row gap-1">
                                                @if($canReadStaff)
                                                <a class="staff-link" href="{{ route('staff.show', $member->encrypted_id) }}" title="View">
                                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                                </a>
                                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                @endif
                                                @if($canUpdateStaff)
                                                <a href="{{ route('staff.edit', $member->encrypted_id) }}" title="Edit">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                                </a>
                                                <button type="button" class="btn btn-link p-0 btn-update-status" title="Update Status"
                                                    data-id="{{ $member->encrypted_id }}"
                                                    data-name="{{ $member->full_names }}"
                                                    data-status-id="{{ $member->current_status_id }}"
                                                    style="line-height:1; color: inherit;">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">swap_horiz</span>
                                                </button>
                                                @endif
                                                @if($canCreateUsers)
                                                <button type="button" class="btn btn-link p-0 btn-convert-user" title="Convert to User"
                                                    data-id="{{ $member->encrypted_id }}"
                                                    data-name="{{ $member->full_names }}"
                                                    data-email="{{ $member->email_address }}"
                                                    style="line-height:1; color: inherit;">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">person_add</span>
                                                </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canUpdateStaff)
<!-- Status Update Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-labelledby="statusUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusUpdateModalLabel">Update Employee Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="modal-staff-name" style="font-weight:600;"></p>
                <div class="mb-3">
                    <label for="modal-status-select" class="form-label">New Status</label>
                    <select class="form-select" id="modal-status-select">
                        @foreach(\App\Models\Staff::STATUSES as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-status">
                    <span id="save-status-spinner" class="spinner-border spinner-border-sm me-1" style="display:none;" role="status"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@if($canCreateUsers)
<!-- Convert Staff To User Modal -->
<div class="modal fade" id="convertUserModal" tabindex="-1" aria-labelledby="convertUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="convert-user-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="convertUserModalLabel">Convert Staff To User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" id="convert-user-staff-name" style="font-weight:600;"></p>
                    <div class="mb-3">
                        <label for="convert-user-email" class="form-label">Company Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="convert-user-email" name="email" required autocomplete="off">
                        <div class="invalid-feedback" id="convert-email-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="convert-user-password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="convert-user-password" name="password" required minlength="6">
                        <div class="invalid-feedback" id="convert-password-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="convertUserBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                        <span class="material-symbols-outlined me-1" style="font-size:18px;">manage_accounts</span>
                        <span>Save User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    var updateStatusRoute = '{{ route("staff.updateStatus", ":id") }}';
    var convertUserRoute = '{{ $canCreateUsers ? route("staff.convertToUser", ":id") : "" }}';
    var canCreateUsers = '{{ $canCreateUsers ? "1" : "0" }}' === '1';
    var canUpdateStaff = '{{ $canUpdateStaff ? "1" : "0" }}' === '1';
    var csrfToken = '{{ csrf_token() }}';
    var statusLabels = @json(\App\Models\Staff::STATUSES);

    $(document).ready(function() {
        // Init staff table
        $('#staff-table').DataTable({
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [2, 'asc']
            ],
            initComplete: function() {
                $('#staff-preloader').hide();
                $('#staff-table').css('visibility', 'visible');
            }
        });

        // Row spinner on staff link
        $(document).on('click', '.staff-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var url = $(this).attr('href');
            row.find('.view-icon').hide();
            row.find('.row-spinner').show();
            row.find('a').css('pointer-events', 'none').css('opacity', '0.6');
            window.location.href = url;
        });

        if (canCreateUsers) {
            var convertEncryptedId = null;

            function resetConvertForm() {
                $('#convert-user-email').val('').removeClass('is-invalid is-valid');
                $('#convert-user-password').val('').removeClass('is-invalid');
                $('#convert-email-feedback').text('');
                $('#convert-password-feedback').text('');
            }

            // Open convert staff to user modal
            $(document).on('click', '.btn-convert-user', function() {
                convertEncryptedId = $(this).data('id');
                var name = $(this).data('name');
                var email = $(this).data('email') || '';

                resetConvertForm();
                $('#convert-user-staff-name').text(name);
                $('#convert-user-email').val(email);

                var modal = new bootstrap.Modal(document.getElementById('convertUserModal'));
                modal.show();
            });

            // Reset on modal hide
            $('#convertUserModal').on('hidden.bs.modal', function() {
                resetConvertForm();
                convertEncryptedId = null;
            });

            // Email uniqueness check on blur
            $('#convert-user-email').on('blur', function() {
                var email = $.trim($(this).val());
                if (!email) return;
                $.get('{{ route("user.ajax.check-email") }}', { email: email }, function(res) {
                    if (res.exists) {
                        $('#convert-user-email').addClass('is-invalid').removeClass('is-valid');
                        $('#convert-email-feedback').text('This email is already registered as a system user.');
                    } else {
                        $('#convert-user-email').removeClass('is-invalid').addClass('is-valid');
                        $('#convert-email-feedback').text('');
                    }
                });
            });

            // AJAX form submit
            $('#convert-user-form').on('submit', function(e) {
                e.preventDefault();

                // Block if email already taken
                if ($('#convert-user-email').hasClass('is-invalid')) return;
                if (!convertEncryptedId) return;

                var btn = $('#convertUserBtn');
                var spinner = btn.find('.spinner-border');
                btn.prop('disabled', true);
                spinner.removeClass('d-none');

                var actionUrl = convertUserRoute.replace(':id', convertEncryptedId);
                $.ajax({
                    url: actionUrl,
                    method: 'POST',
                    data: $(this).serialize(),
                    headers: { 'Accept': 'application/json' },
                    success: function(res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(document.getElementById('convertUserModal')).hide();
                            toastr.success(res.message || 'Staff converted successfully.');
                            setTimeout(function() { location.reload(); }, 1200);
                        }
                    },
                    error: function(xhr) {
                        var json = xhr.responseJSON || {};
                        if (xhr.status === 422 && json.errors) {
                            if (json.errors.email) {
                                $('#convert-user-email').addClass('is-invalid');
                                $('#convert-email-feedback').text(json.errors.email[0]);
                            }
                            if (json.errors.password) {
                                $('#convert-user-password').addClass('is-invalid');
                                $('#convert-password-feedback').text(json.errors.password[0]);
                            }
                        } else {
                            toastr.error(json.error || 'Failed to convert staff to user.');
                        }
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                    }
                });
            });
        }

        if (canUpdateStaff) {
            var currentEncryptedId = null;
            var currentRow = null;

            // Open status update modal
            $(document).on('click', '.btn-update-status', function() {
                currentEncryptedId = $(this).data('id');
                var name = $(this).data('name');
                var statusId = $(this).data('status-id');
                currentRow = $(this).closest('tr');

                $('#modal-staff-name').text(name);
                $('#modal-status-select').val(statusId);
                var modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
                modal.show();
            });

            // Save status
            $('#btn-save-status').on('click', function() {
                var newStatusId = parseInt($('#modal-status-select').val());
                var url = updateStatusRoute.replace(':id', currentEncryptedId);

                $('#save-status-spinner').show();
                $('#btn-save-status').prop('disabled', true);

                $.ajax({
                    type: 'PATCH',
                    url: url,
                    data: {
                        status_id: newStatusId,
                        _token: csrfToken,
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update status text in the row
                            var badge = currentRow.find('.status-badge');
                            badge.text(statusLabels[newStatusId] || 'Unknown');
                            badge.data('status-id', newStatusId);

                            // Update the button's data-status-id for next open
                            currentRow.find('.btn-update-status').data('status-id', newStatusId);

                            bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal')).hide();
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to update status. Please try again.');
                    },
                    complete: function() {
                        $('#save-status-spinner').hide();
                        $('#btn-save-status').prop('disabled', false);
                    }
                });
            });
        }
    });
</script>
@endsection
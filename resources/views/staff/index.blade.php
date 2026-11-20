@extends('layouts.main')
@section('content')

@php
$canReadStaff = auth()->user()->hasPermission('read_staff');
$canUpdateStaff = auth()->user()->hasPermission('update_staff');
$canCreateUsers = auth()->user()->hasPermission('create_users');
@endphp

<style>
    /* Chabrin Modern Table Design System */
    .table-modern {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100% !important;
    }
    .table-modern thead tr {
        background-color: #f8fafc !important;
    }
    .table-modern thead th {
        background-color: #f8fafc !important;
        color: #475569 !important;
        font-size: 0.74rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border-bottom: 2px solid #e2e8f0 !important;
        border-top: none !important;
        padding: 12px 14px !important;
        vertical-align: middle;
        white-space: nowrap;
    }
    .table-modern tbody td {
        padding: 11px 14px !important;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        border-top: none !important;
        font-size: 0.84rem;
        color: #334155;
    }
    .table-modern tbody tr:hover td {
        background-color: #f8fafc !important;
    }

    /* Avatar Initial Circle */
    .avatar-circle {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.78rem;
        flex-shrink: 0;
    }

    /* Soft Action Tool Buttons */
    .action-btn {
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        transition: all 0.15s ease;
        padding: 0;
        text-decoration: none;
    }
    .action-btn:hover { background-color: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
    .action-btn.btn-view:hover { background-color: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
    .action-btn.btn-edit:hover { background-color: #fef9c3; color: #a16207; border-color: #fde047; }
    .action-btn.btn-status:hover { background-color: #f3e8ff; color: #7e22ce; border-color: #e9d5ff; }
    .action-btn.btn-user:hover { background-color: #dcfce7; color: #15803d; border-color: #bbf7d0; }

    /* Clean DataTables Form Controls */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 0.85rem;
        font-size: 0.85rem;
        color: #64748b;
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 4px 8px;
        margin: 0 4px;
        outline: none;
        color: #334155;
    }
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 5px 10px;
        margin-left: 6px;
        outline: none;
        color: #334155;
    }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
    }
</style>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                {{-- Standard Card with Blue Theme Top Accent --}}
                <div class="card shadow-sm border-0 border-top border-3 border-primary">
                    <div class="card-header bg-white card-no-border py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-primary" 
                                 style="width: 36px; height: 36px; background-color: #e7f1ff;">
                                <span class="material-symbols-outlined" style="font-size: 20px;">badge</span>
                            </div>
                            <div>
                                <h5 class="mb-0 fw-bold text-dark">Staff Management</h5>
                                <small class="text-muted">Manage employee records, roles, statuses, and user access</small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                                @permission('read_staff')
                                <a href="{{ route('staff.export') }}" class="btn btn-outline-success d-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size:18px;">table_view</span> Export Excel
                                </a>
                                @endpermission
                            </div>
                        <div class="d-flex gap-2">
                            @permission('create_staff')
                            <a href="{{ route('staff.onboarding.index') }}" class="btn btn-outline-primary rounded-pill px-3 py-1 d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined" style="font-size: 18px;">mail</span>
                                <span class="fw-semibold">Onboarding</span>
                            </a>
                            <a href="{{ route('staff.create') }}" class="btn btn-primary rounded-pill px-3 py-1 d-flex align-items-center gap-1 shadow-sm">
                                <span class="material-symbols-outlined" style="font-size: 18px;">add_circle</span>
                                <span class="fw-semibold">Add Staff</span>
                            </a>
                            @endpermission
                        </div>
                    </div>

                    <div class="card-body pt-2">
                        {{-- Notifications --}}
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                            <strong><i class="fa fa-check-circle me-1"></i> Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                            <strong><i class="fa fa-exclamation-circle me-1"></i> Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        {{-- Preloader --}}
                        <div id="staff-preloader" class="text-center my-5">
                            <div class="spinner-border text-primary" style="width: 2.5rem; height: 2.5rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted small">Loading employees...</p>
                        </div>

                        {{-- Filter Toolbar --}}
                        <div class="row g-2 align-items-center mb-3 p-2 bg-light rounded border">
                            <div class="col-auto">
                                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center">
                                    <span class="material-symbols-outlined me-1" style="font-size: 18px;">tune</span> Filter:
                                </span>
                            </div>

                           {{-- Status Filter --}}
                            <div class="col-sm-3 col-md-2">
                                <select id="filter-status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    @foreach(\App\Models\Staff::STATUSES as $id => $label)
                                        <option value="{{ $id }}" {{ strtolower($label) === 'active' ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Position Filter --}}
                            <div class="col-sm-3 col-md-2">
                                <select id="filter-position" class="form-select form-select-sm">
                                    <option value="">All Positions</option>
                                    @foreach($staff->pluck('position.position_name')->filter()->unique() as $pos)
                                        <option value="{{ ucwords(strtolower($pos)) }}">{{ ucwords(strtolower($pos)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Reset Button --}}
                            <div class="col-auto ms-auto">
                                <button type="button" id="btn-reset-filters" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">refresh</span>
                                    <span>Reset</span>
                                </button>
                            </div>
                        </div>

                        {{-- Standard Table Layout --}}
                        <div class="table-responsive custom-scrollbar">
                            <table class="table-modern display" id="staff-table" style="visibility: hidden;">
                                <thead>
                                    <tr>
                                        <th class="ps-3 text-center no-sort" style="width: 45px;">#</th>
                                        <th style="width: 90px;">Staff No</th>
                                        <th style="min-width: 230px;">Employee</th>
                                        <th style="min-width: 140px;">Position</th>
                                        <th style="width: 85px;">Gender</th>
                                        <th style="min-width: 155px;">Contact</th>
                                        <th style="width: 110px;">National ID</th>
                                        <th style="min-width: 130px;">Zone Roles</th>
                                        <th style="width: 95px;">Status</th>
                                        <th class="text-end pe-3 no-sort" style="width: 110px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($staff as $index => $member)
                                    @php
                                        $nameParts = explode(' ', trim($member->full_names ?? 'S'));
                                        $initials = strtoupper(substr($nameParts[0] ?? 'S', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                                        
                                        $statusLabel = \App\Models\Staff::STATUSES[$member->current_status_id] ?? 'Unknown';
                                        $statusClass = match(strtolower($statusLabel)) {
                                            'active'    => 'bg-success-subtle text-success border-success-subtle',
                                            'inactive'  => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                            'suspended' => 'bg-danger-subtle text-danger border-danger-subtle',
                                            'on leave'  => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                            default     => 'bg-light text-dark border'
                                        };
                                    @endphp
                                    <tr>
                                        {{-- # --}}
                                        <td class="ps-3 text-center text-muted small">{{ $index + 1 }}</td>

                                        {{-- Staff No --}}
                                        <td>
                                            @if($member->staff_number)
                                                <span class="badge bg-light text-secondary border font-monospace px-2 py-1">
                                                    {{ $member->staff_number }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Full Names --}}
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2 bg-primary-subtle text-primary border border-primary-subtle flex-shrink-0">
                                                    {{ $initials }}
                                                </div>
                                                <div class="text-truncate">
                                                    @if($canReadStaff)
                                                        <a class="fw-semibold text-dark text-decoration-none staff-link" 
                                                           href="{{ route('staff.show', $member->encrypted_id) }}">
                                                            {{ ucwords(strtolower($member->full_names)) }}
                                                        </a>
                                                    @else
                                                        <span class="fw-semibold text-dark">
                                                            {{ ucwords(strtolower($member->full_names)) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Position --}}
                                        <td>
                                            @if($member->position?->position_name)
                                                <span class="badge bg-light text-dark border px-2 py-1 fw-normal text-nowrap">
                                                    {{ ucwords(strtolower($member->position->position_name)) }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Gender --}}
                                        <td>
                                            <span class="text-secondary small">{{ $member->gender ?? '—' }}</span>
                                        </td>

                                        {{-- Contact --}}
                                        <td class="text-nowrap">
                                            @if($member->mobile_number)
                                                <a href="tel:{{ $member->mobile_number }}" class="text-secondary text-decoration-none small d-inline-flex align-items-center">
                                                    <span class="material-symbols-outlined text-muted me-1" style="font-size: 15px;">call</span>
                                                    <span>{{ $member->mobile_number }}</span>
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- National ID --}}
                                        <td class="text-nowrap">
                                            {{-- Only the last four show in the list; the full number is on the profile --}}
                                            <span class="font-monospace text-secondary small" @if($member->national_id) title="Last four characters. The full number is on the staff profile." @endif>{{ \App\Support\MaskedNumber::orDash($member->national_id) }}</span>
                                        </td>

                                        {{-- Zone Roles --}}
                                        <td>
                                            @include('staff.partials.zone-role-badges', ['member' => $member])
                                        </td>

                                        {{-- Status --}}
                                        <td data-search="{{ $member->current_status_id }}">
                                            <span class="badge border rounded-pill px-2 py-1 text-nowrap status-badge {{ $statusClass }}" 
                                                  data-status-id="{{ $member->current_status_id }}">
                                                ● {{ $statusLabel }}
                                            </span>
                                        </td>

                                        {{-- Action Toolbar --}}
                                        <td class="pe-3 text-end">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                @if($canReadStaff)
                                                <a class="action-btn btn-view staff-link" 
                                                   href="{{ route('staff.show', $member->encrypted_id) }}" 
                                                   title="View Profile">
                                                    <span class="material-symbols-outlined view-icon" style="font-size: 16px;">visibility</span>
                                                </a>
                                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                @endif

                                                @if($canUpdateStaff)
                                                <a class="action-btn btn-edit" 
                                                   href="{{ route('staff.edit', $member->encrypted_id) }}" 
                                                   title="Edit Record">
                                                    <span class="material-symbols-outlined" style="font-size: 16px;">edit_document</span>
                                                </a>
                                                <button type="button" 
                                                        class="action-btn btn-status btn-update-status" 
                                                        title="Update Status"
                                                        data-id="{{ $member->encrypted_id }}"
                                                        data-name="{{ $member->full_names }}"
                                                        data-status-id="{{ $member->current_status_id }}">
                                                    <span class="material-symbols-outlined" style="font-size: 16px;">swap_horiz</span>
                                                </button>
                                                @endif

                                                @if($canCreateUsers)
                                                <button type="button" 
                                                        class="action-btn btn-user btn-convert-user" 
                                                        title="Convert to User"
                                                        data-id="{{ $member->encrypted_id }}"
                                                        data-name="{{ $member->full_names }}"
                                                        data-email="{{ $member->email_address }}">
                                                    <span class="material-symbols-outlined" style="font-size: 16px;">person_add</span>
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
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold" id="statusUpdateModalLabel">Update Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-primary fw-medium small mb-2" id="modal-staff-name"></p>
                <div>
                    <label for="modal-status-select" class="form-label text-muted small">New Status</label>
                    <select class="form-select" id="modal-status-select">
                        @foreach(\App\Models\Staff::STATUSES as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary px-3" id="btn-save-status">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="convert-user-form">
                @csrf
                <div class="modal-header border-bottom-0 pb-0">
                    <h6 class="modal-title fw-bold" id="convertUserModalLabel">Convert Staff to System User</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-primary fw-medium small mb-3" id="convert-user-staff-name"></p>
                    <div class="mb-3">
                        <label for="convert-user-email" class="form-label text-muted small">Company Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="convert-user-email" name="email" required autocomplete="off">
                        <div class="invalid-feedback" id="convert-email-feedback"></div>
                    </div>
                    <p class="text-muted small mb-0">They are emailed a link to choose their own password. You will not see or set it.</p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="convertUserBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
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
        // Search the numeric status ID stored on each status cell. This keeps
        // Active (1) distinct from any other label and avoids parsing badge HTML.
        function applyStatusFilter(val, dataTable) {
            dataTable.column(8).search(val ? '^' + $.fn.dataTable.util.escapeRegex(val) + '$' : '', true, false).draw();
        }

        // 1. Single DataTable Initialization
        var table = $('#staff-table').DataTable({
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: 'no-sort' }
            ],
            initComplete: function() {
                $('#staff-preloader').hide();
                $('#staff-table').css('visibility', 'visible');

                // Apply the selected default (Active) once DataTables has rows.
                applyStatusFilter($('#filter-status').val(), this.api());
            }
        });

        // 2. Status Filter (Column 8: Status)
        $('#filter-status').on('change', function() {
            applyStatusFilter($(this).val(), table);
        });

        // 3. Position Filter (Column 3: Position)
        $('#filter-position').on('change', function() {
            var val = $.trim($(this).val());
            if (val) {
                // Case-insensitive containment match with escaped characters
                table.column(3).search($.fn.dataTable.util.escapeRegex(val), false, false, true).draw();
            } else {
                table.column(3).search('').draw();
            }
        });

        // 4. Reset Filters → show everything again
        $('#btn-reset-filters').on('click', function() {
            $('#filter-status').val('');
            $('#filter-position').val('');
            table.search('').columns().search('').draw();
        });

        // 5. Row spinner on staff link
        $(document).on('click', '.staff-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var url = $(this).attr('href');
            row.find('.view-icon').hide();
            row.find('.row-spinner').show();
            row.find('a').css('pointer-events', 'none').css('opacity', '0.6');
            window.location.href = url;
        });

        // 6. Convert Staff to User
        if (canCreateUsers) {
            var convertEncryptedId = null;

            function resetConvertForm() {
                $('#convert-user-email').val('').removeClass('is-invalid is-valid');
                $('#convert-email-feedback').text('');
            }

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

            $('#convertUserModal').on('hidden.bs.modal', function() {
                resetConvertForm();
                convertEncryptedId = null;
            });

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

            $('#convert-user-form').on('submit', function(e) {
                e.preventDefault();

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
                            // The user exists either way; a failed email gets a warning so it is not missed
                            if (res.email_sent === false) {
                                toastr.warning(res.message, '', { timeOut: 10000 });
                            } else {
                                toastr.success(res.message || 'Staff converted successfully.');
                            }
                            setTimeout(function() { location.reload(); }, res.email_sent === false ? 4000 : 1200);
                        }
                    },
                    error: function(xhr) {
                        var json = xhr.responseJSON || {};
                        if (xhr.status === 422 && json.errors) {
                            if (json.errors.email) {
                                $('#convert-user-email').addClass('is-invalid');
                                $('#convert-email-feedback').text(json.errors.email[0]);
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

        // 7. Update Staff Status
        if (canUpdateStaff) {
            var currentEncryptedId = null;
            var currentRow = null;

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
                            var badge = currentRow.find('.status-badge');
                            currentRow.find('td').eq(8).attr('data-search', newStatusId);
                            badge.text('● ' + (statusLabels[newStatusId] || 'Unknown'));
                            badge.data('status-id', newStatusId);

                            currentRow.find('.btn-update-status').data('status-id', newStatusId);
                            $('#staff-table').DataTable().draw(false);
                            bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal')).hide();
                        }
                    },
                    error: function() {
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

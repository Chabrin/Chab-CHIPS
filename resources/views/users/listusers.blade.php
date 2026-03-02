@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">System Users</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar" style="visibility:hidden">
                            <table class="display" id="users-dt">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>National ID</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded by AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- View User Modal -->
                        <div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">User Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">person</span>
                                                    <div>
                                                        <div class="small text-muted">Name</div>
                                                        <div id="view-name" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">account_circle</span>
                                                    <div>
                                                        <div class="small text-muted">Username</div>
                                                        <div id="view-username" class="fw-bold">
                                                            <span class="badge bg-primary text-white rounded-pill px-3 py-2" id="view-username-pill"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">email</span>
                                                    <div>
                                                        <div class="small text-muted">Email</div>
                                                        <div id="view-email" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">work</span>
                                                    <div>
                                                        <div class="small text-muted">Role</div>
                                                        <div id="view-role" class="fw-bold">
                                                            <span id="view-role-badge" class="badge bg-primary text-white"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">perm_identity</span>
                                                    <div>
                                                        <div class="small text-muted">National ID</div>
                                                        <div id="view-national-id" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">calendar_today</span>
                                                    <div>
                                                        <div class="small text-muted">Start Date</div>
                                                        <div id="view-start-date" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">calendar_today</span>
                                                    <div>
                                                        <div class="small text-muted">End Date</div>
                                                        <div id="view-end-date" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">schedule</span>
                                                    <div>
                                                        <div class="small text-muted">Time In</div>
                                                        <div id="view-time-in" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">schedule</span>
                                                    <div>
                                                        <div class="small text-muted">Time Out</div>
                                                        <div id="view-time-out" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="material-symbols-outlined" style="font-size:28px;">event</span>
                                                    <div>
                                                        <div class="small text-muted">Login Days</div>
                                                        <div id="view-login-days" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="mt-2">
                                                    <div class="small text-muted mb-1">Assigned Companies</div>
                                                    <div id="view-assigned-companies" class="d-flex gap-1 flex-wrap"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                                            <span class="material-symbols-outlined">close</span>
                                            <span class="ms-1">Close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit User Modal -->
                        <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <form id="editUserForm">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" id="edit-user-id">
                                            <div class="row g-3">
                                                <div class="col-lg-4">
                                                    <label class="form-label">Username</label>
                                                    <input type="text" class="form-control" id="edit-username" name="username" required>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="edit-start-date" name="start_date" required>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="edit-end-date" name="end_date" required>
                                                </div>

                                                <div class="col-lg-4">
                                                    <label class="form-label">Time In</label>
                                                    <input type="time" class="form-control" id="edit-time-in" name="time_in" required>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label class="form-label">Time Out</label>
                                                    <input type="time" class="form-control" id="edit-time-out" name="time_out" required>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label class="form-label">Role</label>
                                                    <select id="edit-role" name="role_id" class="form-select">
                                                        <option value="">-- Select Role --</option>
                                                        @foreach(
                                                        \App\Models\Role::all() as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Assigned Companies</label>
                                                    <select id="edit-role-companies" name="role_companies[]" class="form-select" multiple></select>
                                                    <div class="small text-muted">Assign companies. Host (0) is included by default.</div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Login Days</label>
                                                    <div class="d-flex gap-2 align-items-center flex-wrap" id="edit-login-days">
                                                        @php
                                                        $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                                                        @endphp
                                                        @foreach($days as $d)
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" value="{{ $d }}" id="edit-day-{{ $d }}">
                                                            <label class="form-check-label small" for="edit-day-{{ $d }}">{{ ucfirst($d) }}</label>
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                                                <span class="material-symbols-outlined">close</span>
                                                <span class="ms-1">Cancel</span>
                                            </button>
                                            <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveEditUserBtn">
                                                <span class="spinner-border spinner-border-sm me-1 d-none" id="saveEditSpinner" role="status" aria-hidden="true"></span>
                                                <span class="material-symbols-outlined">done</span>
                                                <span class="ms-1">Save</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



@endsection

@section('scripts')
<script>
    (function() {
        const dt = $('#users-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ url("/ajax/users/list") }}',
                dataSrc: function(json) {
                    if (json.error) {
                        toastr.error(json.error || 'Error loading users');
                        return [];
                    }
                    return json.users || [];
                },
                error: function() {
                    toastr.error('Failed to load users');
                    $('#users-dt tbody').html('<tr><td colspan="6">Error loading data</td></tr>');
                }
            },
            columns: [{
                    data: 'name'
                },
                {
                    data: 'username'
                },
                {
                    data: 'email'
                },
                {
                    data: 'role',
                    render: function(data) {
                        return '<span class="badge badge-light-success">' + data + '</span>';
                    }
                },
                {
                    data: 'national_id'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return '<div class="d-flex gap-2">' +
                            '<span class="material-symbols-outlined view-user text-success" data-id="' + data.id + '" title="View" style="cursor:pointer;">file_open</span>' +
                            '<span class="material-symbols-outlined edit-user text-success" data-id="' + data.id + '" title="Edit" style="cursor:pointer;">edit</span>' +
                            '</div>';
                    }
                }
            ],
            initComplete: function() {
                // show table now that DataTables has initialized to avoid flicker
                $('#users-dt').closest('.table-responsive').css('visibility', 'visible');
            }
        });

        // View handler
        $('#users-dt').on('click', '.view-user', function(e) {
            e.preventDefault();
            const btn = $(this);
            const id = btn.data('id');
            const spinner = btn.find('.action-spinner');
            spinner.removeClass('d-none');
            btn.prop('disabled', true);
            $.get('/ajax/users/' + id).done(function(res) {
                const u = res.user || {};
                const emp = u.employee || {};
                $('#view-name').text((emp.first_name ? (emp.first_name + ' ' + (emp.surname || '')) : (u.name || '')));
                $('#view-username-pill').text(u.username || '').toggleClass('bg-light text-dark', !!(u.username)).toggleClass('text-muted', !u.username);
                $('#view-email').text(u.email || '');
                var roleName = res.role_name || '';
                if (roleName) {
                    $('#view-role-badge').text(roleName).removeClass('d-none');
                } else {
                    $('#view-role-badge').text('').addClass('d-none');
                }
                $('#view-national-id').text(emp.national_id || u.national_id || '');
                $('#view-start-date').text(u.start_date ? u.start_date.substring(0, 10) : '');
                $('#view-end-date').text(u.end_date ? u.end_date.substring(0, 10) : '');
                $('#view-time-in').text(u.time_in || '');
                $('#view-time-out').text(u.time_out || '');
                // login days: present as comma-separated titles
                var days = (u.login_days || []).map(function(d) {
                    return ('' + d).charAt(0).toUpperCase() + ('' + d).slice(1);
                });
                $('#view-login-days').text(days.join(', '));
                // render assigned companies badges
                var assigned = res.role_companies || [];
                var out = '';
                assigned.forEach(function(cid) {
                    var name = (typeof companiesMap[cid] !== 'undefined') ? companiesMap[cid] : (cid === 0 ? 'Host' : cid);
                    out += '<span class="badge bg-success text-white">' + name + '</span> ';
                });
                $('#view-assigned-companies').html(out);
                var vmodal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                vmodal.show();
            }).fail(function() {
                toastr.error('Failed to fetch user details');
            }).always(function() {
                spinner.addClass('d-none');
                btn.prop('disabled', false);
            });
        });

        // companies map to resolve id -> name and populate edit select
        var companiesMap = {};

        function loadCompaniesIntoEditSelect() {
            var $sel = $('#edit-role-companies');
            $sel.empty();
            $.get('{{ url("/ajax/companies/list") }}').done(function(res) {
                if (res && res.success && res.data) {
                    // include Host option (0) always
                    $sel.append('<option value="0">Host</option>');
                    res.data.forEach(function(c) {
                        companiesMap[c.id] = c.name || c.id;
                        if (parseInt(c.id) === 0) return;
                        $sel.append('<option value="' + c.id + '">' + (c.name || c.id) + '</option>');
                    });
                }
            }).fail(function() {
                console.warn('Failed to load companies for edit user select');
                if (typeof toastr !== 'undefined') toastr.warning('Failed to load companies.');
                // ensure Host option exists
                $sel.append('<option value="0">Host</option>');
            });
        }

        loadCompaniesIntoEditSelect();

        // Edit handler
        $('#users-dt').on('click', '.edit-user', function(e) {
            e.preventDefault();
            const btn = $(this);
            const id = btn.data('id');
            const spinner = btn.find('.action-spinner');
            spinner.removeClass('d-none');
            btn.prop('disabled', true);
            $('#edit-user-id').val(id);
            $.get('/ajax/users/' + id).done(function(res) {
                const u = res.user || {};
                // prefer raw fields; format dates
                $('#edit-username').val(u.username || '');
                $('#edit-start-date').val(u.start_date ? u.start_date.substring(0, 10) : '');
                $('#edit-end-date').val(u.end_date ? u.end_date.substring(0, 10) : '');
                $('#edit-time-in').val(u.time_in || '');
                $('#edit-time-out').val(u.time_out || '');
                $('#edit-role').val(res.role_id || '');
                // preselect companies if provided
                var companies = res.role_companies || [0];
                // normalize to strings for select
                companies = companies.map(function(c) {
                    return '' + c;
                });
                $('#edit-role-companies').val(companies);
                // login_days can be stored as ['monday',..] or ['mon',..] depending on earlier code - normalize
                var days = (u.login_days || []).map(function(d) {
                    return ('' + d).toLowerCase();
                });
                $('#edit-login-days input[type=checkbox]').each(function() {
                    var val = $(this).val().toLowerCase();
                    // check if stored days includes full name or 3-letter code
                    var full = val; // our checkboxes contain full names (monday etc.)
                    var short = val.substring(0, 3);
                    $(this).prop('checked', days.includes(full) || days.includes(short));
                });
                var emodal = new bootstrap.Modal(document.getElementById('editUserModal'));
                emodal.show();
            }).fail(function() {
                toastr.error('Failed to fetch user details');
            }).always(function() {
                spinner.addClass('d-none');
                btn.prop('disabled', false);
            });
        });

        // Save edit
        $('#editUserForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#edit-user-id').val();
            const payload = {
                username: $('#edit-username').val(),
                start_date: $('#edit-start-date').val(),
                end_date: $('#edit-end-date').val(),
                time_in: $('#edit-time-in').val(),
                time_out: $('#edit-time-out').val(),
                role_id: $('#edit-role').val(),
                login_days: $('#edit-login-days input:checked').map(function() {
                    return $(this).val();
                }).get()
            };
            // include role_companies from select (ensure Host 0 included)
            var sel = $('#edit-role-companies').val() || [];
            if (sel.length === 0) sel = ['0'];
            payload.role_companies = sel.map(function(s) {
                return parseInt(s);
            });
            // include CSRF token (in case headers are not applied by environment)
            payload._token = $('meta[name="csrf-token"]').attr('content');
            $('#saveEditSpinner').removeClass('d-none');
            $('#saveEditUserBtn').prop('disabled', true);
            $.post('/ajax/users/' + id, payload).done(function(res) {
                toastr.success(res.message || 'User updated');
                $('#editUserModal').modal('hide');
                dt.ajax.reload(null, false);
            }).fail(function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errs = xhr.responseJSON.errors;
                    const messages = Object.values(errs).flat().join('<br>');
                    toastr.error(messages);
                } else {
                    toastr.error('Failed to update user');
                }
            }).always(function() {
                $('#saveEditSpinner').addClass('d-none');
                $('#saveEditUserBtn').prop('disabled', false);
            });
        });

    })();
</script>
@endsection
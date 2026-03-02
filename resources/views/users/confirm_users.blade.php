@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Confirm Users</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <div id="users-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
                            <table class="table display" id="confirm-users-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Confirmed</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
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
    $(function() {
        // Use existing DataTable if present; otherwise initialize.
        var dataTable;

        function ensureTable() {
            if ($.fn.dataTable.isDataTable('#confirm-users-table')) {
                console.debug('confirm-users-table already initialized');
                dataTable = $('#confirm-users-table').DataTable();
                // If the existing instance doesn't have ajax configured, destroy and recreate
                var settings = dataTable.settings && dataTable.settings().init();
                console.debug('Existing datatable settings:', settings);
                var hasAjax = settings && (settings.ajax || (dataTable.ajax && typeof dataTable.ajax.url === 'function'));
                console.debug('hasAjax =>', hasAjax);
                if (!hasAjax) {
                    try {
                        dataTable.destroy();
                        // replace table element with fresh markup (keep thead)
                        var newTable = '<table class="table display" id="confirm-users-table">' +
                            '<thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Confirmed</th><th style="width:120px;">Action</th></tr></thead>' +
                            '<tbody></tbody></table>';
                        $('#confirm-users-table').replaceWith(newTable);
                    } catch (e) {
                        console.warn('Failed to destroy/replace existing basic-1:', e);
                    }
                    dataTable = $('#confirm-users-table').DataTable({
                        ajax: {
                            url: "{{ route('confirm.users.list') }}",
                            dataSrc: function(json) {
                                console.debug('DataTables dataSrc received:', json);
                                if (!json) return [];
                                return json.users || [];
                            },
                            error: function() {
                                if (typeof toastr !== 'undefined') toastr.error('Failed to load users');
                            }
                        },
                        columns: [{
                                data: 'username'
                            },
                            {
                                data: 'name'
                            },
                            {
                                data: 'email'
                            },
                            {
                                data: 'role',
                                render: function(d) {
                                    return d ? '<span class="badge badge-light-success">' + d + '</span>' : '';
                                }
                            },
                            {
                                data: 'confirmed',
                                render: function(d) {
                                    return (d == 1 || d === true || d === '1') ? '<span class="badge badge-light-success">Confirmed</span>' : '<span class="badge badge-light-danger">Unconfirmed</span>';
                                }
                            },
                            {
                                data: null,
                                orderable: false,
                                searchable: false,
                                render: function(row) {
                                    return '<button class="btn btn-sm btn-outline-secondary view-user p-1" data-id="' + row.id + '" title="View">' +
                                        '<span class="spinner-border spinner-border-sm me-0 d-none action-spinner" role="status" aria-hidden="true"></span>' +
                                        '<span class="material-symbols-outlined" style="font-size:18px;">visibility</span>' +
                                        '</button>';
                                }
                            }
                        ],
                        paging: true,
                        info: true,
                        lengthChange: true,
                        lengthMenu: [
                            [10, 25, 50, 100, -1],
                            [10, 25, 50, 100, 'All']
                        ]
                    });
                } else {
                    // existing instance has ajax, ensure correct url and reload
                    try {
                        console.debug('Updating ajax URL on existing datatable');
                        dataTable.ajax.url("{{ route('confirm.users.list') }}").load();
                    } catch (e) {
                        console.warn('Failed to reload confirm-users-table:', e);
                    }
                }
            } else {
                dataTable = $('#confirm-users-table').DataTable({
                    ajax: {
                        url: "{{ route('confirm.users.list') }}",
                        dataSrc: function(json) {
                            console.debug('DataTables init dataSrc received:', json);
                            if (!json) return [];
                            return json.users || [];
                        },
                        error: function() {
                            if (typeof toastr !== 'undefined') toastr.error('Failed to load users');
                        }
                    },
                    columns: [{
                            data: 'username'
                        },
                        {
                            data: 'name'
                        },
                        {
                            data: 'email'
                        },
                        {
                            data: 'role',
                            render: function(d) {
                                return d ? '<span class="badge badge-light-success">' + d + '</span>' : '';
                            }
                        },
                        {
                            data: 'confirmed',
                            render: function(d) {
                                return (d == 1 || d === true || d === '1') ? '<span class="badge badge-light-success">Confirmed</span>' : '<span class="badge badge-light-danger">Unconfirmed</span>';
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(row) {
                                return '<button class="btn btn-sm btn-outline-secondary view-user p-1" data-id="' + row.id + '" title="View">' +
                                    '<span class="spinner-border spinner-border-sm me-0 d-none action-spinner" role="status" aria-hidden="true"></span>' +
                                    '<span class="material-symbols-outlined" style="font-size:18px;">visibility</span>' +
                                    '</button>';
                            }
                        }
                    ],
                    paging: true,
                    info: true,
                    lengthChange: true,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, 'All']
                    ]
                });
            }

            // Bind view handler once - opens modal and handles confirm inside modal
            if (!$('#confirm-users-table').data('view-bound')) {
                // insert full view modal (based on listusers view) but keep ids used by confirm logic
                var modalHtml = '\n<div class="modal fade" id="viewConfirmModal" tabindex="-1" aria-hidden="true">\n  <div class="modal-dialog modal-lg modal-dialog-centered">\n    <div class="modal-content">\n      <div class="modal-header">\n        <h5 class="modal-title">User Details</h5>\n        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>\n      </div>\n      <div class="modal-body">\n        <div class="row g-3">\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">person</span>\n              <div>\n                <div class="small text-muted">Name</div>\n                <div id="view-name" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">account_circle</span>\n              <div>\n                <div class="small text-muted">Username</div>\n                <div id="view-username" class="fw-bold">\n                  <span class="badge bg-primary text-white rounded-pill px-3 py-2" id="view-username-pill"></span>\n                </div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">email</span>\n              <div>\n                <div class="small text-muted">Email</div>\n                <div id="view-email" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">work</span>\n              <div>\n                <div class="small text-muted">Role</div>\n                <div id="view-role" class="fw-bold">\n                  <span id="view-role-badge" class="badge bg-primary text-white"></span>\n                </div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">perm_identity</span>\n              <div>\n                <div class="small text-muted">National ID</div>\n                <div id="view-national-id" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">calendar_today</span>\n              <div>\n                <div class="small text-muted">Start Date</div>\n                <div id="view-start-date" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">calendar_today</span>\n              <div>\n                <div class="small text-muted">End Date</div>\n                <div id="view-end-date" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">schedule</span>\n              <div>\n                <div class="small text-muted">Time In</div>\n                <div id="view-time-in" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">schedule</span>\n              <div>\n                <div class="small text-muted">Time Out</div>\n                <div id="view-time-out" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n\n          <div class="col-lg-4">\n            <div class="d-flex align-items-start gap-2">\n              <span class="material-symbols-outlined" style="font-size:28px;">event</span>\n              <div>\n                <div class="small text-muted">Login Days</div>\n                <div id="view-login-days" class="fw-bold"></div>\n              </div>\n            </div>\n          </div>\n          <div class="col-12">\n            <div class="mt-2">\n              <div class="small text-muted mb-1">Assigned Companies</div>\n              <div id="view-assigned-companies" class="d-flex gap-1 flex-wrap"></div>\n            </div>\n          </div>\n        </div>\n      </div>\n      <div class="modal-footer">\n        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">\n          <span class="material-symbols-outlined">close</span>\n          <span class="ms-1">Close</span>\n        </button>\n        <button type="button" class="btn btn-primary d-flex align-items-center" id="confirmUserBtn">\n          <span class="spinner-border spinner-border-sm me-1 d-none" id="confirmUserSpinner" role="status" aria-hidden="true"></span>\n          <span class="material-symbols-outlined">check_circle</span>\n          <span class="ms-1">Confirm</span>\n        </button>\n      </div>\n    </div>\n  </div>\n</div>\n';
                $('body').append(modalHtml);

                // Bind on document to catch clicks on dynamically created .view-user buttons reliably
                $(document).on('click', '.view-user', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var id = btn.data('id');
                    var spinner = btn.find('.action-spinner');
                    spinner.removeClass('d-none');
                    btn.prop('disabled', true);
                    $.get('/ajax/users/' + id).done(function(res) {
                        var u = res.user || {};
                        var emp = u.employee || {};
                        // name
                        $('#view-name').text((emp.first_name ? (emp.first_name + ' ' + (emp.surname || '')) : (u.name || '')));
                        // username pill
                        $('#view-username-pill').text(u.username || '').toggleClass('bg-light text-dark', !!(u.username)).toggleClass('text-muted', !u.username);
                        $('#view-username').text(u.username || '');
                        $('#view-email').text(u.email || '');
                        var roleName = res.role_name || u.role || '';
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
                        var days = (u.login_days || []).map(function(d) {
                            return ('' + d).charAt(0).toUpperCase() + ('' + d).slice(1);
                        });
                        $('#view-login-days').text(days.join(', '));

                        // assigned companies
                        var assigned = res.role_companies || u.role_companies || [];
                        var out = '';
                        assigned.forEach(function(cid) {
                            var name = (typeof window.companiesMap !== 'undefined' && window.companiesMap[cid]) ? window.companiesMap[cid] : (cid === 0 ? 'Host' : cid);
                            out += '<span class="badge badge-light-success">' + name + '</span> ';
                        });
                        $('#view-assigned-companies').html(out);

                        $('#confirmUserBtn').data('user-id', id);
                        var vmodal = new bootstrap.Modal(document.getElementById('viewConfirmModal'));
                        vmodal.show();
                    }).fail(function() {
                        toastr.error('Failed to fetch user details');
                    }).always(function() {
                        spinner.addClass('d-none');
                        btn.prop('disabled', false);
                    });
                });

                // confirm inside modal (no browser alert) — include CSRF token
                $('body').on('click', '#confirmUserBtn', function() {
                    var $btn = $(this);
                    var userId = $btn.data('user-id');
                    if (!userId) return toastr.error('Missing user id');
                    $btn.prop('disabled', true);
                    $('#confirmUserSpinner').removeClass('d-none');
                    var token = $('meta[name="csrf-token"]').attr('content');
                    $.ajax({
                        url: "{{ route('confirm.user') }}",
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token
                        },
                        data: {
                            user_id: userId
                        },
                        success: function(resp) {
                            if (resp && resp.success) {
                                if (typeof toastr !== 'undefined') toastr.success('User confirmed');
                                // hide modal
                                var modalEl = document.getElementById('viewConfirmModal');
                                var bm = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                                bm.hide();
                                // reload table
                                if (dataTable && dataTable.ajax) dataTable.ajax.reload(null, false);
                            } else {
                                if (typeof toastr !== 'undefined') toastr.error(resp.error || 'Failed to confirm user');
                            }
                        },
                        error: function() {
                            if (typeof toastr !== 'undefined') toastr.error('Request failed');
                        },
                        complete: function() {
                            $('#confirmUserSpinner').addClass('d-none');
                            $btn.prop('disabled', false);
                        }
                    });
                });

                $('#confirm-users-table').data('view-bound', true);
            }
            // small fallback: if after 1s no rows are visible, fetch JSON and render plain rows for inspection
            setTimeout(function() {
                try {
                    var rowCount = (dataTable && dataTable.rows) ? dataTable.rows().count() : $('#confirm-users-table tbody tr').length;
                    console.debug('Row count after init:', rowCount);
                    if (!rowCount || rowCount === 0) {
                        console.debug('No rows in DataTable after init — fetching JSON fallback');
                        $.getJSON("{{ route('confirm.users.list') }}").done(function(json) {
                            console.debug('Fallback JSON fetch:', json);
                            var $tbody = $('#confirm-users-table tbody').empty();
                            if (json && json.users && json.users.length) {
                                json.users.forEach(function(u) {
                                    var tr = $('<tr>');
                                    tr.append($('<td>').text(u.username));
                                    tr.append($('<td>').text(u.name));
                                    tr.append($('<td>').text(u.email));
                                    tr.append($('<td>').html(u.role ? '<span class="badge badge-light-success">' + u.role + '</span>' : ''));
                                    tr.append($('<td>').html((u.confirmed == 1 || u.confirmed === true || u.confirmed === '1') ? '<span class="badge badge-light-success">Confirmed</span>' : '<span class="badge badge-light-danger">Unconfirmed</span>'));
                                    tr.append($('<td>').html('<button class="btn btn-sm btn-outline-secondary view-user p-1" data-id="' + u.id + '" title="View">' +
                                        '<span class="spinner-border spinner-border-sm me-0 d-none action-spinner" role="status" aria-hidden="true"></span>' +
                                        '<span class="material-symbols-outlined" style="font-size:18px;">visibility</span>' +
                                        '</button>'));
                                    $tbody.append(tr);
                                });
                            } else {
                                $tbody.append($('<tr>').append($('<td colspan="6">').text('No users awaiting confirmation')));
                            }
                        }).fail(function(err) {
                            console.warn('Fallback JSON fetch failed', err);
                        });
                    }
                } catch (err) {
                    console.warn('Error during fallback check', err);
                }
            }, 1000);
        }

        ensureTable();
    });
</script>
@endsection
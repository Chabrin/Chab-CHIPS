@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Recoveries Management</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('update_recoveries')
                            <a href="{{ route('property.recoveries.landlord-posting.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">account_balance</span>
                                <span class="ms-1">Post to Landlord Account</span>
                            </a>
                            @endpermission
                            <button type="button" id="confirmer-setting-btn" class="btn btn-outline-secondary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#confirmer-modal">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <span class="ms-1">Who confirms</span>
                            </button>
                            <button type="button" id="pending-confirmation-btn" class="btn btn-outline-warning btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">pending_actions</span>
                                <span class="ms-1">Pending confirmation <span class="badge bg-warning text-dark" id="pending-confirmation-count">0</span></span>
                            </button>
                            <a href="{{ route('property.recovery.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Recovery</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Preloader -->
                        <div id="recoveries-preloader" class="text-center my-5">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading recoveries...</p>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="recoveries-table" style="visibility: hidden;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Ref Number</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Recovery Date</th>
                                        <th>Amount</th>
                                        <th>Client</th>
                                        <th>Property</th>
                                        <th>Zone</th>
                                        <th>Commissionable</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmer-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Who confirms paid-owner recoveries</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Currently: <strong id="confirmer-current">...</strong></p>
                <p class="text-muted small" id="confirmer-note">The person who recorded a paid-owner recovery can never confirm it, whatever their role.</p>
                <div id="confirmer-form" class="d-none">
                    <label class="form-label" for="confirmer-role">Role that confirms</label>
                    <select class="form-select" id="confirmer-role"></select>
                    <div id="confirmer-warning" class="text-muted small mt-1 d-none">Saving replaces the current confirmer.</div>
                </div>
                <div id="confirmer-message" class="small mt-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm d-none" id="confirmer-save">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/datatable/datatables/jquery.dataTables.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#recoveries-table').DataTable({
            ajax: {
                url: '{{ route('ajax.property.recoveries.list') }}',
                dataSrc: 'data'
            },
            columns: [
                { 
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { 
                    data: 'ref_number',
                    render: function(data, type, row) {
                        return `<a class="recovery-link" style="color:#3598db" href="/property/recovery/${row.encrypted_id}">${data}</a>`;
                    }
                },
                { data: 'type' },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        var cls = row.status_id == 5 ? 'bg-success' : (row.status_id == 41 ? 'bg-danger' : 'bg-warning text-dark');
                        return '<span class="badge ' + cls + '">' + data + '</span>';
                    }
                },
                { data: 'recovery_date' },
                { 
                    data: 'amount',
                    render: function(data) {
                        return `KES ${data}`;
                    }
                },
                { data: 'client' },
                { data: 'property' },
                { data: 'zone' },
                { 
                    data: 'commisionable',
                    render: function(data) {
                        if (data === 'Yes') {
                            return '<span class="badge bg-success">Yes</span>';
                        } else {
                            return '<span class="badge bg-secondary">No</span>';
                        }
                    }
                },
                { data: 'created_by' },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex flex-row gap-1">
                                <a class="recovery-link" href="/property/recovery/${row.encrypted_id}" title="View">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                ${row.status_id == 1 ? `<a href="/property/recovery/${row.encrypted_id}/edit" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>` : ''}
                                ${row.can_confirm ? `<a class="recovery-link" href="/property/recovery/${row.encrypted_id}" title="Confirm or reject">
                                    <span class="material-symbols-outlined" style="font-size:18px;color:#198754;">task_alt</span>
                                </a>` : ''}
                            </div>
                        `;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [[4, 'desc']], // Sort by recovery date descending
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            initComplete: function() {
                // Hide preloader and show table
                $('#recoveries-preloader').hide();
                $('#recoveries-table').css('visibility', 'visible');
            }
        });

        // "Pending confirmation": the paid-owner recoveries this user can confirm now
        var onlyPending = false;
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (!onlyPending) { return true; }
            var row = table.row(dataIndex).data();
            return row && row.can_confirm;
        });
        table.on('xhr.dt draw.dt', function() {
            var rows = table.rows().data().toArray();
            $('#pending-confirmation-count').text(rows.filter(function(r) { return r.can_confirm; }).length);
        });
        $('#pending-confirmation-btn').on('click', function() {
            onlyPending = !onlyPending;
            $(this).toggleClass('active', onlyPending);
            table.draw();
        });

        // "Who confirms": show the confirmer, and let a user with update_recoveries change the role
        function loadConfirmer() {
            $('#confirmer-message').addClass('d-none');
            $.getJSON('{{ route('ajax.property.recoveries.confirmer') }}', function(setting) {
                $('#confirmer-current').text(setting.label);
                $('#confirmer-warning').toggleClass('d-none', !setting.role_id && !setting.has_named_users);
                if (setting.can_change) {
                    var select = $('#confirmer-role').empty().append('<option value="">Choose a role</option>');
                    setting.roles.forEach(function(role) {
                        select.append($('<option>').val(role.id).text(role.name).prop('selected', role.id == setting.role_id));
                    });
                    $('#confirmer-form, #confirmer-save').removeClass('d-none');
                } else {
                    $('#confirmer-form, #confirmer-save').addClass('d-none');
                    $('#confirmer-note').append(' Only a user with the update recoveries permission can change who confirms.');
                }
            }).fail(function() {
                $('#confirmer-current').text('could not be loaded');
            });
        }
        $('#confirmer-modal').on('show.bs.modal', function() { $('#confirmer-note').text('The person who recorded a paid-owner recovery can never confirm it, whatever their role.'); loadConfirmer(); });
        $('#confirmer-save').on('click', function() {
            var roleId = $('#confirmer-role').val();
            var message = $('#confirmer-message');
            if (!roleId) { message.text('Choose a role.').removeClass('d-none text-success').addClass('text-danger'); return; }
            message.text('Saving...').removeClass('d-none text-danger text-success').addClass('text-muted');
            var button = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ route('ajax.property.recoveries.confirmer.save') }}',
                method: 'POST',
                data: { role_id: roleId, _token: '{{ csrf_token() }}' },
                dataType: 'json'
            }).done(function(res) {
                // Saved: close the window and say so (the next time it opens it reads the setting afresh)
                message.addClass('d-none');
                var modalElement = document.getElementById('confirmer-modal');
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                } else {
                    $(modalElement).modal('hide');
                }
                toastr.success(res.message || 'Saved.');
                table.ajax.reload(null, false);
            }).fail(function(xhr) {
                var text = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save.';
                message.text(text).removeClass('d-none text-success text-muted').addClass('text-danger');
            }).always(function() { button.prop('disabled', false); });
        });

        // Handle row-level spinner on recovery link click
        $(document).on('click', '.recovery-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var url = $(this).attr('href');

            // Hide view icon and show spinner
            row.find('.view-icon').hide();
            row.find('.row-spinner').show();

            // Disable all links in the row
            row.find('a').css('pointer-events', 'none').css('opacity', '0.6');

            // Navigate to the details page
            window.location.href = url;
        });
    });
</script>
@endsection
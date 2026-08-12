@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Property Activation Management</h5>
                        <div class="ms-auto d-flex gap-2">
                            <button id="btn-bulk-activate" class="btn btn-success btn-sm d-none align-items-center">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Activate Selected</span>
                            </button>
                            <button id="btn-bulk-deactivate" class="btn btn-danger btn-sm d-none align-items-center">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">block</span>
                                <span>Deactivate Selected</span>
                            </button>
                            <a href="{{ route('property.properties.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Listings</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
                            <span class="material-symbols-outlined">info</span>
                            <div>
                                Inactive properties are hidden from the main Property Listings page and
                                everywhere else the system reads property data. Select one or more
                                properties below and use Activate/Deactivate to change that — you'll see
                                the affected units and tenants before anything is applied.
                            </div>
                        </div>
                        <div id="selection-summary" class="mb-2 d-none">
                            <div>
                                <span class="badge bg-secondary me-1">Selected: <span id="selected-total">0</span></span>
                                <span class="badge badge-light-success me-1">Active: <span id="selected-active">0</span></span>
                                <span class="badge badge-light-danger">Inactive: <span id="selected-inactive">0</span></span>
                            </div>
                            <div class="small text-muted mt-1" id="selected-names"></div>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="activation-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all"></th>
                                        <th>Property Name</th>
                                        <th>Reference Number</th>
                                        <th>Zone</th>
                                        <th>Landlord</th>
                                        <th>Status</th>
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

<!-- Confirm Modal -->
<div class="modal fade" id="activationConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activationConfirmModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="activationConfirmModalBody">
                <div class="text-center"><div class="spinner-border" role="status"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn" id="activationConfirmModalConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
    let ROUTES = {
        list: '{{ route("property.activation.data") }}',
        preview: '{{ route("property.activation.preview") }}',
        activate: '{{ route("property.activation.activate") }}',
        deactivate: '{{ route("property.activation.deactivate") }}',
    };

    (function() {
        let dt;
        let pendingAction = null; // 'activate' | 'deactivate'

        // Selection state lives here, independent of the DOM/current page —
        // id (string) -> active (bool). DataTables only keeps the CURRENT page's
        // rows attached to the visible <tbody> (the rest exist only in its internal
        // data model), so checkbox `checked` state can't be trusted to survive a
        // page change or redraw on its own. Every checkbox's on/off state is instead
        // driven FROM this object (via drawCallback, on every draw/page-turn/reload),
        // and every user click updates this object rather than being read back from
        // the DOM — that's what lets a selection made on page 1 still count once the
        // user has paged forward to select more on page 2, and lets "select all"
        // apply to every row matching the current search across all pages, not just
        // the ~10 rows currently rendered.
        let selected = {};

        $(document).ready(function() {
            dt = $('#activation-table').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: ROUTES.list,
                    dataSrc: 'data'
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<input type="checkbox" class="select-row" value="${row.id}" data-active="${row.active ? 1 : 0}">`;
                        }
                    },
                    {
                        data: 'property_name',
                        render: function(name, type, row) {
                            return name + '<br><small class="text-muted">' + row.units_count + ' unit(s)</small>';
                        }
                    },
                    { data: 'reference_number' },
                    { data: 'zone_name' },
                    { data: 'client_name' },
                    {
                        data: 'active',
                        render: function(active) {
                            return active
                                ? '<span class="badge badge-light-success">Active</span>'
                                : '<span class="badge badge-light-danger">Inactive</span>';
                        }
                    }
                ],
                order: [[1, 'asc']],
                drawCallback: function() {
                    // DataTables fires an initial drawCallback synchronously while
                    // .DataTable(...) is still being evaluated — before the `dt = ...`
                    // assignment below has actually completed — so `dt` is still
                    // undefined the very first time this runs. Every draw after that
                    // (once real data has loaded) runs after `dt` is assigned.
                    if (!dt) {
                        return;
                    }

                    // Re-apply checked state to whichever rows are on the page that
                    // was just drawn, based on the persistent `selected` map.
                    dt.rows({ page: 'current' }).every(function() {
                        var rowData = this.data();
                        $(this.node()).find('.select-row').prop('checked', selected.hasOwnProperty(String(rowData.id)));
                    });
                    updateSelectAllState();
                    toggleBulkButtons();
                    updateSelectionSummary();
                }
            });

            // Select all — applies to every row matching the current search, across
            // every page, not just the page currently on screen.
            $('#select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                if (isChecked) {
                    dt.rows({ search: 'applied' }).every(function() {
                        var rowData = this.data();
                        selected[String(rowData.id)] = { active: !!rowData.active, name: rowData.property_name };
                    });
                } else {
                    selected = {};
                }
                dt.draw(false); // re-run drawCallback without resetting the current page
            });

            // Individual checkbox
            $('#activation-table tbody').on('change', '.select-row', function() {
                var rowData = dt.row($(this).closest('tr')).data();
                var id = String(rowData.id);
                if ($(this).is(':checked')) {
                    selected[id] = { active: !!rowData.active, name: rowData.property_name };
                } else {
                    delete selected[id];
                }
                updateSelectAllState();
                toggleBulkButtons();
                updateSelectionSummary();
            });

            function updateSelectAllState() {
                var pageData = dt.rows({ page: 'current' }).data();
                var total = pageData.count();
                var checkedCount = 0;
                pageData.each(function(rowData) {
                    if (selected.hasOwnProperty(String(rowData.id))) checkedCount++;
                });
                $('#select-all').prop('checked', total > 0 && checkedCount === total);
            }

            function toggleBulkButtons() {
                var anyChecked = Object.keys(selected).length > 0;
                $('#btn-bulk-activate, #btn-bulk-deactivate').toggleClass('d-none', !anyChecked).toggleClass('d-flex', anyChecked);
            }

            function updateSelectionSummary() {
                var ids = Object.keys(selected);
                var total = ids.length;
                var active = 0;
                var inactive = 0;
                var names = [];

                ids.forEach(function(id) {
                    if (selected[id].active) {
                        active++;
                    } else {
                        inactive++;
                    }
                    names.push(selected[id].name);
                });

                $('#selected-total').text(total);
                $('#selected-active').text(active);
                $('#selected-inactive').text(inactive);

                var namesLimit = 30;
                var namesText = names.slice(0, namesLimit).join(', ');
                if (names.length > namesLimit) {
                    namesText += ', and ' + (names.length - namesLimit) + ' more';
                }
                $('#selected-names').text(namesText);

                $('#selection-summary').toggleClass('d-none', total === 0);
            }

            function getSelectedIds() {
                return Object.keys(selected);
            }

            function renderPreviewBody(properties) {
                if (!properties.length) {
                    return '<p class="text-muted">No properties found.</p>';
                }
                var html = '';
                properties.forEach(function(p) {
                    html += '<div class="mb-3 pb-2 border-bottom">';
                    html += '<strong>' + p.property_name + '</strong> (' + p.reference_number + ') — ';
                    html += p.units_count + ' unit(s), ' + p.tenants_count + ' tenant(s)';
                    if (p.units_count > 0 && p.units_count <= 10) {
                        html += '<div class="small text-muted">Units: ' + p.units.join(', ') + '</div>';
                    }
                    if (p.tenants_count > 0 && p.tenants_count <= 10) {
                        html += '<div class="small text-muted">Tenants: ' + p.tenants.join(', ') + '</div>';
                    }
                    html += '</div>';
                });
                return html;
            }

            function openConfirmModal(action) {
                var ids = getSelectedIds();
                if (!ids.length) {
                    toastr.warning('Please select at least one property.');
                    return;
                }

                pendingAction = action;

                var isActivate = action === 'activate';
                $('#activationConfirmModalTitle').text(isActivate ? 'Confirm Activation' : 'Confirm Deactivation');
                $('#activationConfirmModalConfirmBtn')
                    .removeClass('btn-success btn-danger')
                    .addClass(isActivate ? 'btn-success' : 'btn-danger')
                    .text(isActivate ? 'Activate' : 'Deactivate');
                $('#activationConfirmModalBody').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
                $('#activationConfirmModal').modal('show');

                $.post(ROUTES.preview, {
                    ids: ids,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res && res.success) {
                        $('#activationConfirmModalBody').html(renderPreviewBody(res.data));
                    } else {
                        $('#activationConfirmModalBody').html('<p class="text-danger">Failed to load preview.</p>');
                    }
                }).fail(function() {
                    $('#activationConfirmModalBody').html('<p class="text-danger">Failed to load preview.</p>');
                });
            }

            $('#btn-bulk-activate').on('click', function() { openConfirmModal('activate'); });
            $('#btn-bulk-deactivate').on('click', function() { openConfirmModal('deactivate'); });

            $('#activationConfirmModalConfirmBtn').on('click', function() {
                var ids = getSelectedIds();
                if (!ids.length || !pendingAction) return;

                var url = pendingAction === 'activate' ? ROUTES.activate : ROUTES.deactivate;
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(url, {
                    ids: ids,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res && res.success) {
                        toastr.success(res.message);
                        $('#activationConfirmModal').modal('hide');
                        selected = {};
                        dt.ajax.reload(null, false); // triggers drawCallback, which resyncs everything
                    } else {
                        toastr.error(res.message || 'Failed to update properties.');
                    }
                }).fail(function() {
                    toastr.error('Failed to update properties.');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
        });
    })();
</script>
@endsection

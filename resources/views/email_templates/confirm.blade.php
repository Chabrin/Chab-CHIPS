@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;" id="confirm-email-templates-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="mb-0">Confirm Email Templates</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button id="btn-bulk-confirm-templates" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1">check_circle</span>
                                Confirm Selected
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar position-relative">
                            <div id="email-templates-confirm-loading" class="table-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:5;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                    <div>Loading unconfirmed templates...</div>
                                </div>
                            </div>
                            <table class="display" id="emailTemplatesConfirmTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAllConfirm" /></th>
                                        <th>Name</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Added By</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Action</th>
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
    (function() {
        const ROUTES = {
            list: '{{ route("email_templates.list") }}',
            show: '{{ route("email_templates.show", ":id") }}',
            confirm: '{{ route("email_templates.confirm", ":id") }}',
            bulk: '{{ route("email_templates.bulk_confirm") }}'
        };

        const currentUser = '{{ optional(Auth::user())->username ?? "system" }}';
        let dt;

        function showLoading() {
            $('#email-templates-confirm-loading').show();
        }

        function hideLoading() {
            $('#email-templates-confirm-loading').hide();
        }

        $(document).ready(function() {
            showLoading();
            dt = $('#emailTemplatesConfirmTable').DataTable({
                ajax: {
                    url: ROUTES.list,
                    data: {
                        confirmed: '0'
                    },
                    dataSrc: 'templates'
                },
                columns: [{
                        data: 'id',
                        orderable: false,
                        render: function(v, t, row) {
                            return '<input type="checkbox" class="template-confirm-checkbox" value="' + v + '" />';
                        }
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'subject',
                        render: function(d) {
                            return d && d.length > 60 ? d.substring(0, 60) + '...' : (d || '-');
                        }
                    },
                    {
                        data: 'comtype',
                        render: d => d ? '<span class="badge bg-secondary">' + d + '</span>' : '-'
                    },
                    {
                        data: 'added_by'
                    },
                    {
                        data: 'confirmed',
                        render: v => v ? '<span class="badge badge-light-success">Confirmed</span>' : '<span class="badge badge-light-warning">Pending</span>'
                    },
                    {
                        data: 'created_at',
                        render: d => d ? new Date(d).toLocaleDateString() : '-'
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(row) {
                            const viewBtn = '<button title="View" class="btn btn-primary btn-sm p-1 me-1 view-template" data-id="' + row.id + '" style="width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;"><span class="material-symbols-outlined" style="font-size:18px">visibility</span></button>';
                            let confirmBtn = '';
                            if (row.added_by === currentUser) {
                                confirmBtn = '<button title="Cannot confirm own template" class="btn btn-secondary btn-sm p-1 me-1" disabled style="width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;"><span class="material-symbols-outlined" style="font-size:18px">lock</span></button>';
                            } else {
                                confirmBtn = '<button title="Confirm" class="btn btn-primary btn-sm p-1 me-1 confirm-template" data-id="' + row.id + '" style="width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;"><span class="material-symbols-outlined" style="font-size:18px">check_circle</span></button>';
                            }
                            return viewBtn + confirmBtn;
                        }
                    }
                ],
                order: [
                    [6, 'desc']
                ],
                pageLength: 25,
                initComplete: hideLoading
            });

            $('#selectAllConfirm').on('change', function() {
                $('.template-confirm-checkbox').prop('checked', this.checked);
            });

            $(document).on('click', '.view-template', function() {
                const id = $(this).data('id');
                $.get(ROUTES.show.replace(':id', id)).done(function(res) {
                    if (!res || !res.template) {
                        toastr.error('Failed to load template');
                        return;
                    }
                    const t = res.template;
                    const modalHtml = `
                        <div class="modal fade" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title d-flex align-items-center">
                                            <span class="material-symbols-outlined me-2">visibility</span>
                                            View Email Template
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3 mb-2">
                                            <div class="col-lg-6">
                                                <label class="form-label mb-1">Template Name</label>
                                                <input type="text" class="form-control form-control-sm" value="${t.name || ''}" readonly>
                                            </div>
                                            <div class="col-lg-6">
                                                <label class="form-label mb-1">Email Subject</label>
                                                <input type="text" class="form-control form-control-sm" value="${t.subject || ''}" readonly>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label mb-1">Email Body</label>
                                            <div class="border rounded p-3 bg-white preview-body" style="min-height:150px;">${t.body || '-'}</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                                            <span class="material-symbols-outlined me-1">close</span>
                                            <span>Close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    const $m = $(modalHtml);
                    $('body').append($m);
                    $m.modal('show');
                    $m.on('hidden.bs.modal', function() {
                        $m.remove();
                    });
                    // Apply formatting similar to manage preview
                    $m.find('.preview-body').css({
                        'font-size': '0.95rem',
                        'line-height': '1.5',
                        'color': '#212529',
                        'word-wrap': 'break-word',
                        'white-space': 'pre-wrap'
                    });
                }).fail(function() {
                    toastr.error('Failed to load template');
                });
            });

            $(document).on('click', '.confirm-template', function() {
                const id = $(this).data('id');
                if (!confirm('Confirm this email template?')) return;
                $.post(ROUTES.confirm.replace(':id', id), {
                    _token: '{{ csrf_token() }}'
                }).done(function(res) {
                    toastr.success(res.message || 'Template confirmed');
                    dt.ajax.reload();
                }).fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.error || 'Failed to confirm');
                });
            });

            $('#btn-bulk-confirm-templates').on('click', function() {
                const ids = $('.template-confirm-checkbox:checked').map(function() {
                    return this.value;
                }).get();
                if (ids.length === 0) {
                    toastr.warning('Select at least one template');
                    return;
                }
                if (!confirm('Confirm ' + ids.length + ' selected template(s)?')) return;
                $.post(ROUTES.bulk, {
                    _token: '{{ csrf_token() }}',
                    ids: ids
                }).done(function(res) {
                    toastr.success(res.message || 'Templates confirmed');
                    dt.ajax.reload();
                    $('#selectAllConfirm').prop('checked', false);
                }).fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.error || 'Bulk confirm failed');
                });
            });
        });
    })();
</script>
@endsection
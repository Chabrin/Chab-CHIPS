@extends('layouts.main')

@section('styles')
<style>
    /* Employment categories page. Scoped to .ec-* so nothing else on the site changes. */
    .ec-hint { font-size: .8125rem; color: #6b7a8c; }

    /* Summary tiles */
    .ec-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .75rem; }
    .ec-stat { border: 1px solid #e8edf3; border-radius: 10px; padding: .75rem 1rem; background: #fff; display: flex; align-items: center; gap: .75rem; }
    .ec-stat .ec-stat-icon { width: 36px; height: 36px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; background: #eef3ff; color: #3b5bdb; flex-shrink: 0; }
    .ec-stat .ec-stat-icon .material-symbols-outlined { font-size: 20px; }
    .ec-stat-value { font-size: 1.25rem; font-weight: 600; line-height: 1.1; color: #1f2d3d; }
    .ec-stat-label { font-size: .75rem; color: #6b7a8c; }

    /* Toolbar */
    .ec-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; }
    .ec-search { position: relative; max-width: 280px; width: 100%; }
    .ec-search .material-symbols-outlined { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); font-size: 18px; color: #94a3b8; pointer-events: none; }
    .ec-search input { padding-left: 2rem; }
    .ec-filter .btn { font-size: .8125rem; }

    /* Table */
    #ecTable { margin-bottom: 0; }
    #ecTable thead th { background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; border-bottom: 1px solid #e8edf3; }
    #ecTable tbody td { vertical-align: middle; border-color: #f0f3f7; }
    #ecTable tbody tr:hover > td { background: #fbfcfe; }
    #ecTable tr.ec-off > td { background: #fafafa; }
    #ecTable tr.ec-off .ec-name, #ecTable tr.ec-off .ec-chip { opacity: .6; }
    .ec-name { font-weight: 600; color: #1f2d3d; }
    .ec-off-tag { font-size: .7rem; font-weight: 600; color: #8a6d00; background: #fff6d6; border-radius: 999px; padding: .1rem .5rem; margin-left: .4rem; }

    .ec-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; background: #f1f4f9; color: #334155; border-radius: 6px; padding: .15rem .45rem; }
    .ec-chip { display: inline-flex; align-items: center; gap: .25rem; font-size: .78rem; font-weight: 500; border-radius: 999px; padding: .15rem .55rem; white-space: nowrap; }
    .ec-chip .material-symbols-outlined { font-size: 15px; }
    .ec-chip-yes { background: #e7f6ec; color: #1e7a3c; }
    .ec-chip-no { background: #f1f4f9; color: #64748b; }
    .ec-chip-term { background: #fff1e6; color: #b45309; }
    .ec-chip-open { background: #eef3ff; color: #3b5bdb; }
    .ec-count { font-variant-numeric: tabular-nums; font-weight: 600; color: #1f2d3d; }
    .ec-count-zero { color: #a0aec0; font-weight: 400; }

    .ec-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; padding: 0; border-radius: 8px; }
    .ec-icon-btn .material-symbols-outlined { font-size: 18px; }
    .ec-actions { white-space: nowrap; }

    /* Own on/off switch, so the theme's checkbox styles can't break it */
    .ec-switch { position: relative; display: inline-block; width: 38px; height: 22px; margin: 0; cursor: pointer; vertical-align: middle; }
    .ec-switch input { position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; z-index: 1; }
    .ec-switch span { position: absolute; inset: 0; background: #cbd5e1; border-radius: 999px; transition: background .15s; }
    .ec-switch span::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s; }
    .ec-switch input:checked + span { background: #22a35a; }
    .ec-switch input:checked + span::after { transform: translateX(16px); }
    .ec-switch input:focus-visible + span { outline: 2px solid #3b5bdb; outline-offset: 2px; }
    .ec-switch input:disabled + span { opacity: .55; }
    .ec-switch input:disabled { cursor: wait; }

    .ec-empty { padding: 3rem 1rem; text-align: center; color: #6b7a8c; }
    .ec-empty .material-symbols-outlined { font-size: 40px; color: #c3cdd8; }

    .ec-skel { height: 12px; border-radius: 6px; background: linear-gradient(90deg, #eef1f5 25%, #f7f9fb 50%, #eef1f5 75%); background-size: 200% 100%; animation: ec-shimmer 1.2s infinite; }
    @keyframes ec-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }

    /* Modal */
    .ec-option { border: 1px solid #e8edf3; border-radius: 10px; padding: .75rem .9rem; display: flex; gap: .75rem; align-items: flex-start; }
    .ec-option + .ec-option { margin-top: .5rem; }
    .ec-option .form-check-input { margin-top: .2rem; flex-shrink: 0; }
    .ec-option-title { font-weight: 600; font-size: .875rem; color: #1f2d3d; }
    .ec-preview { display: flex; align-items: center; gap: .5rem; margin-top: .5rem; font-size: .8125rem; color: #6b7a8c; }
    .ec-lock { display: none; align-items: center; gap: .25rem; font-size: .78rem; color: #8a6d00; margin-top: .35rem; }
    .ec-lock .material-symbols-outlined { font-size: 15px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-start flex-wrap gap-2 py-3">
                <div class="me-auto">
                    <h5 class="mb-0">Employment Categories</h5>
                    <div class="ec-hint mt-1">How staff are employed. A category that is switched off is no longer offered for new staff, and staff already in it keep it.</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('settings.hr.positions') }}" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Staff Positions
                    </a>
                    @if ($canCreate)
                    <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="ecAdd">
                        <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span> Add Category
                    </button>
                    @endif
                </div>
            </div>

            <div class="card-body pt-0">
                <div class="ec-stats mb-3">
                    <div class="ec-stat">
                        <span class="ec-stat-icon"><span class="material-symbols-outlined">category</span></span>
                        <div><div class="ec-stat-value" id="ecStatTotal">–</div><div class="ec-stat-label">Categories</div></div>
                    </div>
                    <div class="ec-stat">
                        <span class="ec-stat-icon" style="background:#e7f6ec;color:#1e7a3c;"><span class="material-symbols-outlined">check_circle</span></span>
                        <div><div class="ec-stat-value" id="ecStatOn">–</div><div class="ec-stat-label">Offered for new staff</div></div>
                    </div>
                    <div class="ec-stat">
                        <span class="ec-stat-icon" style="background:#fff1e6;color:#b45309;"><span class="material-symbols-outlined">groups</span></span>
                        <div><div class="ec-stat-value" id="ecStatStaff">–</div><div class="ec-stat-label">Staff in categories</div></div>
                    </div>
                </div>

                <div class="ec-toolbar mb-2">
                    <div class="ec-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" class="form-control form-control-sm" id="ecSearch" placeholder="Search categories" aria-label="Search categories">
                    </div>
                    <div class="btn-group btn-group-sm ec-filter" role="group" aria-label="Filter categories">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="on">Offered</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="off">Switched off</button>
                    </div>
                </div>

                <div class="table-responsive border rounded-3">
                    <table class="table align-middle" id="ecTable">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Staff number</th>
                                <th>System login</th>
                                <th>Contract</th>
                                <th class="text-end">Staff</th>
                                <th class="text-center">Offered</th>
                                <th class="text-end"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add / edit --}}
<div class="modal fade" id="ecModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="ecForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="ecModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ecId" value="">
                <div class="mb-3">
                    <label for="ecName" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ecName" maxlength="100" placeholder="e.g. Contract" required>
                    <div class="invalid-feedback" id="ecNameError"></div>
                </div>
                <div class="mb-3">
                    <label for="ecPrefix" class="form-label">Staff number prefix <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" class="form-control" id="ecPrefix" maxlength="5" placeholder="e.g. CT" style="max-width:9rem;text-transform:uppercase;">
                    <div class="invalid-feedback" id="ecPrefixError"></div>
                    <div class="ec-preview">Staff numbers will look like <span class="ec-code" id="ecPreview">1, 2, 3</span></div>
                    <div class="ec-lock" id="ecPrefixLock"><span class="material-symbols-outlined">lock</span> Locked because staff already use this prefix.</div>
                </div>

                <div class="ec-option">
                    <input class="form-check-input" type="checkbox" role="switch" id="ecLogin" checked>
                    <label for="ecLogin" class="mb-0">
                        <div class="ec-option-title">Gets a system login</div>
                        <div class="ec-hint">Switch off for staff who don't use the system, such as casual workers.</div>
                    </label>
                </div>
                <div class="ec-option">
                    <input class="form-check-input" type="checkbox" role="switch" id="ecEndDate" checked>
                    <label for="ecEndDate" class="mb-0">
                        <div class="ec-option-title">Contracts have an end date</div>
                        <div class="ec-hint">Switch off for permanent staff. Their contracts are open-ended and show probation details instead.</div>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="ecSave">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete confirmation --}}
<div class="modal fade" id="ecDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center pt-4">
                <span class="material-symbols-outlined text-danger" style="font-size:40px;">delete</span>
                <h6 class="mt-2 mb-1">Delete <span id="ecDeleteName"></span>?</h6>
                <div class="ec-hint">This cannot be undone.</div>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="ecDeleteConfirm">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(function() {
        const can = { update: @json($canUpdate), delete: @json($canDelete) };
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const urls = {
            list: "{{ route('ajax.hr.employment_categories.list') }}",
            store: "{{ route('ajax.hr.employment_categories.store') }}",
            base: "{{ url('/ajax/hr/employment-categories') }}"
        };
        let categories = [];
        let filter = 'all';
        let pendingDelete = null;
        const modal = new bootstrap.Modal(document.getElementById('ecModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('ecDeleteModal'));
        const $body = $('#ecTable tbody');

        // Whether a category is offered for new staff. The list endpoint's field
        // for this is looked up from the names below, first match wins.
        const ON_KEYS = ['is_active', 'active', 'is_offered', 'offered', 'is_enabled', 'enabled', 'status'];
        let onKey = null;
        function isOn(category) {
            if (!onKey || !(onKey in category)) {
                onKey = ON_KEYS.filter(function(k) { return k in category; })[0] || null;
                if (!onKey) { console.warn('Employment categories: no on/off field found in', category); return true; }
            }
            const v = category[onKey];
            if (typeof v === 'string') return ['1', 'true', 'yes', 'on', 'active', 'enabled'].indexOf(v.toLowerCase()) !== -1;
            return v === true || Number(v) === 1;
        }

        function esc(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function sample(prefix) {
            prefix = (prefix || '').trim().toUpperCase();
            return prefix ? prefix + '-001' : '1, 2, 3';
        }

        function message(xhr, fallback) {
            const json = xhr.responseJSON || {};
            if (json.errors) return Object.values(json.errors).flat().join(' ');
            return json.error || json.message || fallback;
        }

        function api(method, url, data) {
            return $.ajax({ url: url, type: method, data: data || {}, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function skeleton() {
            let html = '';
            for (let i = 0; i < 3; i++) {
                html += '<tr>' + '<td><div class="ec-skel" style="width:60%"></div></td>'.repeat(7) + '</tr>';
            }
            $body.html(html);
        }

        function rowHtml(c) {
            const on = isOn(c);
            const staff = Number(c.staff_count) || 0;

            const login = c.gets_login
                ? '<span class="ec-chip ec-chip-yes"><span class="material-symbols-outlined">key</span>Yes</span>'
                : '<span class="ec-chip ec-chip-no"><span class="material-symbols-outlined">block</span>No</span>';
            const contract = c.has_end_date
                ? '<span class="ec-chip ec-chip-term"><span class="material-symbols-outlined">event</span>Fixed term</span>'
                : '<span class="ec-chip ec-chip-open"><span class="material-symbols-outlined">all_inclusive</span>Open-ended</span>';

            const toggle = can.update
                ? '<label class="ec-switch" title="' + (on ? 'Offered. Click to switch off' : 'Switched off. Click to offer') + '"><input type="checkbox" class="ec-toggle" aria-label="Offered for new staff"' + (on ? ' checked' : '') + '><span></span></label>'
                : (on ? '<span class="ec-chip ec-chip-yes">Yes</span>' : '<span class="ec-chip ec-chip-no">No</span>');

            let actions = '';
            if (can.update) {
                actions += '<button type="button" class="btn btn-light ec-icon-btn ec-edit" title="Edit"><span class="material-symbols-outlined">edit</span></button> ';
            }
            if (can.delete) {
                actions += '<button type="button" class="btn btn-light ec-icon-btn text-danger ec-delete" title="Delete"><span class="material-symbols-outlined">delete</span></button>';
            }

            return '<tr data-id="' + esc(c.id) + '"' + (on ? '' : ' class="ec-off"') + '>'
                + '<td><span class="ec-name">' + esc(c.name) + '</span>' + (on ? '' : '<span class="ec-off-tag">Off</span>') + '</td>'
                + '<td><span class="ec-code">' + esc(sample(c.staff_number_prefix)) + '</span></td>'
                + '<td>' + login + '</td>'
                + '<td>' + contract + '</td>'
                + '<td class="text-end"><span class="ec-count' + (staff ? '' : ' ec-count-zero') + '">' + staff + '</span></td>'
                + '<td class="text-center">' + toggle + '</td>'
                + '<td class="text-end ec-actions">' + actions + '</td>'
                + '</tr>';
        }

        function emptyHtml(icon, title, text) {
            return '<tr><td colspan="7"><div class="ec-empty"><span class="material-symbols-outlined">' + icon + '</span>'
                + '<div class="fw-semibold mt-2 text-body">' + title + '</div><div class="ec-hint">' + text + '</div></div></td></tr>';
        }

        function render() {
            const term = $('#ecSearch').val().trim().toLowerCase();
            const rows = categories.filter(function(c) {
                if (filter === 'on' && !isOn(c)) return false;
                if (filter === 'off' && isOn(c)) return false;
                if (!term) return true;
                return String(c.name).toLowerCase().indexOf(term) !== -1
                    || String(c.staff_number_prefix || '').toLowerCase().indexOf(term) !== -1;
            });

            if (!categories.length) {
                $body.html(emptyHtml('category', 'No categories yet', 'Add one to start assigning staff to it.'));
            } else if (!rows.length) {
                $body.html(emptyHtml('search_off', 'Nothing matches', 'Try another search or filter.'));
            } else {
                $body.html(rows.map(rowHtml).join(''));
            }

            $('#ecStatTotal').text(categories.length);
            $('#ecStatOn').text(categories.filter(isOn).length);
            $('#ecStatStaff').text(categories.reduce(function(sum, c) { return sum + (Number(c.staff_count) || 0); }, 0));
        }

        function load() {
            $.getJSON(urls.list).done(function(res) {
                categories = res.categories || [];
                render();
            }).fail(function(xhr) {
                $body.html(emptyHtml('error', 'Could not load categories', 'Refresh the page to try again.'));
                toastr.error(message(xhr, 'The categories could not be loaded.'));
            });
        }

        function find(id) {
            return categories.filter(function(c) { return String(c.id) === String(id); })[0];
        }

        function clearErrors() {
            $('#ecName, #ecPrefix').removeClass('is-invalid');
            $('#ecNameError, #ecPrefixError').text('');
        }

        function openModal(category) {
            clearErrors();
            const locked = !!category && Number(category.staff_count) > 0;
            $('#ecModalTitle').text(category ? 'Edit ' + category.name : 'Add Category');
            $('#ecId').val(category ? category.id : '');
            $('#ecName').val(category ? category.name : '');
            $('#ecPrefix').val(category ? (category.staff_number_prefix || '') : '').prop('readonly', locked);
            $('#ecPrefixLock').css('display', locked ? 'flex' : 'none');
            $('#ecPreview').text(sample($('#ecPrefix').val()));
            $('#ecLogin').prop('checked', category ? !!category.gets_login : true);
            $('#ecEndDate').prop('checked', category ? !!category.has_end_date : true);
            modal.show();
        }

        $('#ecModal').on('shown.bs.modal', function() { $('#ecName').trigger('focus'); });

        $('#ecPrefix').on('input', function() {
            const clean = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (clean !== this.value) this.value = clean;
            $('#ecPreview').text(sample(clean));
        });

        $('#ecSearch').on('input', render);
        $('.ec-filter .btn').on('click', function() {
            $('.ec-filter .btn').removeClass('active');
            $(this).addClass('active');
            filter = $(this).data('filter');
            render();
        });

        $('#ecAdd').on('click', function() { openModal(null); });
        $(document).on('click', '.ec-edit', function() { openModal(find($(this).closest('tr').attr('data-id'))); });

        $('#ecForm').on('submit', function(event) {
            event.preventDefault();
            clearErrors();
            const id = $('#ecId').val();
            const $save = $('#ecSave').prop('disabled', true).text('Saving...');
            api('POST', id ? urls.base + '/' + id : urls.store, {
                name: $('#ecName').val().trim(),
                staff_number_prefix: $('#ecPrefix').val().trim().toUpperCase(),
                gets_login: $('#ecLogin').is(':checked') ? 1 : 0,
                has_end_date: $('#ecEndDate').is(':checked') ? 1 : 0
            }).done(function(res) {
                modal.hide();
                toastr.success(res.message);
                load();
            }).fail(function(xhr) {
                const errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                if (errors.name) { $('#ecName').addClass('is-invalid'); $('#ecNameError').text(errors.name[0]); }
                if (errors.staff_number_prefix) { $('#ecPrefix').addClass('is-invalid'); $('#ecPrefixError').text(errors.staff_number_prefix[0]); }
                if (!errors.name && !errors.staff_number_prefix) toastr.error(message(xhr, 'The category could not be saved.'));
            }).always(function() {
                $save.prop('disabled', false).text('Save');
            });
        });

        $(document).on('change', '.ec-toggle', function() {
            const $box = $(this).prop('disabled', true);
            api('POST', urls.base + '/' + $box.closest('tr').attr('data-id') + '/toggle').done(function(res) {
                toastr.success(res.message);
                load();
            }).fail(function(xhr) {
                $box.prop('checked', !$box.is(':checked')).prop('disabled', false);
                toastr.error(message(xhr, 'The category could not be changed.'));
            });
        });

        $(document).on('click', '.ec-delete', function() {
            pendingDelete = find($(this).closest('tr').attr('data-id'));
            if (!pendingDelete) return;
            $('#ecDeleteName').text('"' + pendingDelete.name + '"');
            deleteModal.show();
        });

        $('#ecDeleteConfirm').on('click', function() {
            if (!pendingDelete) return;
            const $btn = $(this).prop('disabled', true);
            api('DELETE', urls.base + '/' + pendingDelete.id).done(function(res) {
                deleteModal.hide();
                toastr.success(res.message);
                load();
            }).fail(function(xhr) {
                toastr.error(message(xhr, 'The category could not be deleted.'));
            }).always(function() {
                $btn.prop('disabled', false);
                pendingDelete = null;
            });
        });

        skeleton();
        load();
    });
</script>
@endsection
@extends('layouts.main')

@section('styles')
<style>
#accountsTable tbody tr, #accountsTable tbody tr.odd, #accountsTable tbody tr.even,
#tabClassesTable tbody tr, #tabClassesTable tbody tr.odd, #tabClassesTable tbody tr.even,
#tabGroupsTable tbody tr, #tabGroupsTable tbody tr.odd, #tabGroupsTable tbody tr.even,
#tabCategoriesTable tbody tr, #tabCategoriesTable tbody tr.odd, #tabCategoriesTable tbody tr.even {
    background: transparent !important;
}
#accountsTable tbody tr:hover,
#tabClassesTable tbody tr:hover,
#tabGroupsTable tbody tr:hover,
#tabCategoriesTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }

/* ── Ledger Tabs ── */
.ledger-tab-nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}
.ledger-tab-nav .nav-link {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 18px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-size: 0.83rem;
    font-weight: 500;
    transition: color 0.15s, border-color 0.15s;
    white-space: nowrap;
    text-decoration: none;
}
.ledger-tab-nav .nav-link:hover { color: #333; }
.ledger-tab-nav .nav-link.active {
    color: #222;
    font-weight: 600;
    border-bottom: 3px solid #3598db;
}
.ledger-tab-nav .nav-link .material-symbols-outlined {
    font-size: 22px;
    line-height: 1;
    color: #3598db;
    font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
}
.ledger-tab-nav .tab-sep {
    color: #ced4da;
    font-size: 1rem;
    line-height: 1;
    user-select: none;
}
.tab-ref-link {
    font-size: 0.8rem;
    color: #3598db;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.tab-ref-link:hover { text-decoration: underline; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <nav class="ledger-tab-nav nav" id="ledgerTabs" role="tablist">
                            @if($canReadAccounts)
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-accounts" role="tab">
                                <span class="material-symbols-outlined">account_balance</span> Chart of Accounts
                            </a>
                            @endif
                            @if($canReadClasses)
                            @if($canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link {{ !$canReadAccounts ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-classes" role="tab" id="classes-tab-trigger">
                                <span class="material-symbols-outlined">layers</span> Account Classes
                            </a>
                            @endif
                            @if($canReadGroups)
                            @if($canReadClasses || $canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-groups" role="tab" id="groups-tab-trigger">
                                <span class="material-symbols-outlined">folder_open</span> Account Groups
                            </a>
                            @endif
                            @if($canReadCategories)
                            @if($canReadGroups || $canReadClasses || $canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-categories" role="tab" id="categories-tab-trigger">
                                <span class="material-symbols-outlined">label</span> Categories
                            </a>
                            @endif
                        </nav>
                        <div class="d-flex gap-2">
                            @if($canReadAccounts)
                            <a href="{{ route('ledger.accounts.export') }}" class="btn btn-success btn-sm" id="exportAccountsBtn">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">download</span> Export Excel
                            </a>
                            @endif
                            @permission('create_gl_accounts')
                            <button type="button" id="addAccountBtn" class="btn btn-primary btn-sm">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">add</span> Add Account
                            </button>
                            @endpermission
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="tab-content">

                            {{-- ── ACCOUNTS TAB ── --}}
                            @if($canReadAccounts)
                            <div class="tab-pane fade show active" id="tab-accounts" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="accountsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Code</th>
                                                <th>Account Name</th>
                                                <th>Category</th>
                                                <th>Group</th>
                                                <th>Class</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                                <th style="width:80px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            @endif

                            {{-- ── CLASSES TAB ── --}}
                            @if($canReadClasses)
                            <div class="tab-pane fade {{ !$canReadAccounts ? 'show active' : '' }}" id="tab-classes" role="tabpanel">
                                <div class="d-flex justify-content-end mb-2">
                                    <a href="{{ route('ledger.classes.index') }}" class="tab-ref-link">
                                        Open Account Classes <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">open_in_new</span>
                                    </a>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="tabClassesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Class Name</th>
                                                <th>Balance Nature</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            @endif

                            {{-- ── GROUPS TAB ── --}}
                            @if($canReadGroups)
                            <div class="tab-pane fade" id="tab-groups" role="tabpanel">
                                <div class="d-flex justify-content-end mb-2">
                                    <a href="{{ route('ledger.groups.index') }}" class="tab-ref-link">
                                        Open Account Groups <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">open_in_new</span>
                                    </a>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="tabGroupsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Group Name</th>
                                                <th>Class</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            @endif

                            {{-- ── CATEGORIES TAB ── --}}
                            @if($canReadCategories)
                            <div class="tab-pane fade" id="tab-categories" role="tabpanel">
                                <div class="d-flex justify-content-end mb-2">
                                    <a href="{{ route('ledger.categories.index') }}" class="tab-ref-link">
                                        Open Account Categories <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">open_in_new</span>
                                    </a>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="tabCategoriesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Category Name</th>
                                                <th>Group</th>
                                                <th>Class</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
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
</div>

<!-- Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accountModalLabel">Add Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="accountForm">
                <div class="modal-body">
                    <input type="hidden" id="accountId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="accountCode" name="account_code" placeholder="e.g. 1001">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="accountName" name="account_name" placeholder="e.g. Cash at Bank">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountClassId" name="class_id">
                                <option value="">-- Select Class --</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Group <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountGroupId" name="account_group_id" disabled>
                                <option value="">-- Select Class first --</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountCategoryId" name="category_id" disabled>
                                <option value="">-- Select Group first --</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Balance Nature <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountNormally" name="normally">
                                <option value="">-- Select --</option>
                                <option value="Debit">Debit</option>
                                <option value="Credit">Credit</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountStatus" name="current_status_id">
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="accountSaveBtn">
                        <span class="btn-label">Save</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm"></span> Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    // ── Toggle Add/Export button visibility per active tab ──────────────────────────
    const $addBtn = $('#addAccountBtn');
    const $exportBtn = $('#exportAccountsBtn');
    $('#ledgerTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        const isAccountsTab = $(this).attr('href') === '#tab-accounts';
        $addBtn.toggle(isAccountsTab);
        $exportBtn.toggle(isAccountsTab);
    });

    // ── ACCOUNTS TABLE ───────────────────────────────────────────────────────
    @if($canReadAccounts)
    const accountsTable = $('#accountsTable').DataTable({
        processing: true,
        ajax: { url: '{{ route("ledger.accounts.data") }}', dataSrc: 'data', error: () => toastr.error('Failed to load accounts') },
        columns: [
            { data: null, orderable: false, searchable: false, render: (d,t,r,m) => m.row + 1 },
            { data: 'account_code' },
            { data: 'account_name' },
            { data: 'category_name' },
            { data: 'group_name' },
            { data: 'class_name' },
            { data: 'normally' },
            { data: 'status' },
            {
                data: null, orderable: false, searchable: false,
                render: d => {
                    let a = '';
                    if (d.can_update) a += `<a href="#" class="edit-account me-2" data-row='${JSON.stringify(d)}' title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_square</span></a>`;
                    if (d.can_delete) a += `<a href="#" class="delete-account" data-id="${d.encrypted_id}" data-name="${d.account_name}" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">scan_delete</span></a>`;
                    return a || '-';
                }
            }
        ],
        order: [[1, 'asc']], pageLength: 25
    });
    @endif

    // ── CLASSES TAB TABLE (lazy, view-only) ──────────────────────────────────
    @if($canReadClasses)
    let classesTabLoaded = false;
    const classesTabTable = $('#tabClassesTable').DataTable({
        processing: true, pageLength: 10, order: [[1, 'asc']],
        columns: [
            { data: null, orderable: false, searchable: false, render: (d,t,r,m) => m.row + 1 },
            { data: 'name' },
            { data: 'balance_nature_id' }
        ]
    });
    $('#classes-tab-trigger').on('shown.bs.tab', function () {
        if (!classesTabLoaded) {
            classesTabLoaded = true;
            $.get('{{ route("ledger.classes.data") }}', function (r) {
                classesTabTable.clear().rows.add(r.data).draw();
            });
        }
    });
    @endif

    // ── GROUPS TAB TABLE (lazy, view-only) ───────────────────────────────────
    @if($canReadGroups)
    let groupsTabLoaded = false;
    const groupsTabTable = $('#tabGroupsTable').DataTable({
        processing: true, pageLength: 10, order: [[1, 'asc']],
        columns: [
            { data: null, orderable: false, searchable: false, render: (d,t,r,m) => m.row + 1 },
            { data: 'group_name' },
            { data: 'class_name' }
        ]
    });
    $('#groups-tab-trigger').on('shown.bs.tab', function () {
        if (!groupsTabLoaded) {
            groupsTabLoaded = true;
            $.get('{{ route("ledger.groups.data") }}', function (r) {
                groupsTabTable.clear().rows.add(r.data).draw();
            });
        }
    });
    @endif

    // ── CATEGORIES TAB TABLE (lazy, view-only) ───────────────────────────────
    @if($canReadCategories)
    let categoriesTabLoaded = false;
    const categoriesTabTable = $('#tabCategoriesTable').DataTable({
        processing: true, pageLength: 10, order: [[1, 'asc']],
        columns: [
            { data: null, orderable: false, searchable: false, render: (d,t,r,m) => m.row + 1 },
            { data: 'category_name' },
            { data: 'group_name' },
            { data: 'class_name' }
        ]
    });
    $('#categories-tab-trigger').on('shown.bs.tab', function () {
        if (!categoriesTabLoaded) {
            categoriesTabLoaded = true;
            $.get('{{ route("ledger.categories.data") }}', function (r) {
                categoriesTabTable.clear().rows.add(r.data).draw();
            });
        }
    });
    @endif

    // ── CASCADING SELECTS FOR ACCOUNT MODAL ─────────────────────────────────
    $.get('{{ route("ledger.classes.dropdown") }}', function (data) {
        data.forEach(c => $('#accountClassId').append(`<option value="${c.id}">${c.name}</option>`));
    });

    $('#accountClassId').on('change', function () {
        const classId = $(this).val();
        $('#accountGroupId').html('<option value="">-- Loading... --</option>').prop('disabled', true);
        $('#accountCategoryId').html('<option value="">-- Select Group first --</option>').prop('disabled', true);
        if (!classId) { $('#accountGroupId').html('<option value="">-- Select Class first --</option>'); return; }
        $.get('{{ route("ledger.groups.dropdown") }}', { class_id: classId }, function (data) {
            const sel = $('#accountGroupId');
            sel.html('<option value="">-- Select Group --</option>');
            data.forEach(g => sel.append(`<option value="${g.id}">${g.name}</option>`));
            sel.prop('disabled', false);
        });
    });

    $('#accountGroupId').on('change', function () {
        const groupId = $(this).val();
        $('#accountCategoryId').html('<option value="">-- Loading... --</option>').prop('disabled', true);
        if (!groupId) { $('#accountCategoryId').html('<option value="">-- Select Group first --</option>'); return; }
        $.get('{{ route("ledger.categories.dropdown") }}', { group_id: groupId }, function (data) {
            const sel = $('#accountCategoryId');
            sel.html('<option value="">-- Select Category --</option>');
            data.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
            sel.prop('disabled', false);
        });
    });

    // ── ACCOUNT MODAL HELPERS ────────────────────────────────────────────────
    function resetAccountModal() {
        $('#accountForm')[0].reset();
        $('#accountId').val('');
        $('#accountClassId').val('');
        $('#accountGroupId').html('<option value="">-- Select Class first --</option>').prop('disabled', true);
        $('#accountCategoryId').html('<option value="">-- Select Group first --</option>').prop('disabled', true);
        $('#accountStatus').val('1');
        $('#accountModalLabel').text('Add Account');
        $('.form-control, .form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    function setAccountLoading(on) {
        $('#accountSaveBtn .btn-label').toggleClass('d-none', on);
        $('#accountSaveBtn .btn-spinner').toggleClass('d-none', !on);
        $('#accountSaveBtn').prop('disabled', on);
    }

    function showErrors(errors) {
        $.each(errors, (field, msgs) => {
            const el = $(`[name="${field}"]`);
            el.addClass('is-invalid');
            el.siblings('.invalid-feedback').text(msgs[0]);
        });
    }

    $('#addAccountBtn').click(function () { resetAccountModal(); $('#accountModal').modal('show'); });

    @if($canReadAccounts)
    $('#accountsTable').on('click', '.edit-account', function (e) {
        e.preventDefault();
        const d = $(this).data('row');
        resetAccountModal();
        $('#accountId').val(d.encrypted_id);
        $('#accountCode').val(d.account_code !== '-' ? d.account_code : '');
        $('#accountName').val(d.account_name !== '-' ? d.account_name : '');
        $('#accountNormally').val(d.normally !== '-' ? d.normally : '');
        $('#accountStatus').val(d.current_status_id);
        $('#accountModalLabel').text('Edit Account');
        $('#accountClassId').val(d.class_id).trigger('change');
        setTimeout(() => {
            $('#accountGroupId').val(d.account_group_id).trigger('change');
            setTimeout(() => $('#accountCategoryId').val(d.category_id), 400);
        }, 400);
        $('#accountModal').modal('show');
    });

    $('#accountsTable').on('click', '.delete-account', function (e) {
        e.preventDefault();
        const id = $(this).data('id'), name = $(this).data('name');
        if (!confirm(`Delete account "${name}"?`)) return;
        $.ajax({
            url: '{{ route("ledger.accounts.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: r => { accountsTable.ajax.reload(); toastr.success(r.message); },
            error: xhr => toastr.error(xhr.responseJSON?.message || 'Failed to delete account')
        });
    });
    @endif

    $('#accountForm').submit(function (e) {
        e.preventDefault();
        const id = $('#accountId').val();
        const url = id
            ? '{{ route("ledger.accounts.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("ledger.accounts.store") }}';
        setAccountLoading(true);
        $('.form-control, .form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');
        $.ajax({
            url, method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setAccountLoading(false);
                $('#accountModal').modal('hide');
                @if($canReadAccounts) accountsTable.ajax.reload(); @endif
                toastr.success(r.message);
            },
            error: function (xhr) {
                setAccountLoading(false);
                if (xhr.status === 422) showErrors(xhr.responseJSON.errors);
                else toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });
});
</script>
@endsection

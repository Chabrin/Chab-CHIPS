@extends('layouts.main')

@section('styles')
<style>
#accountsTable tbody tr, #accountsTable tbody tr.odd, #accountsTable tbody tr.even,
#tabClassesTable tbody tr, #tabClassesTable tbody tr.odd, #tabClassesTable tbody tr.even,
#tabGroupsTable tbody tr, #tabGroupsTable tbody tr.odd, #tabGroupsTable tbody tr.even,
#tabCategoriesTable tbody tr, #tabCategoriesTable tbody tr.odd, #tabCategoriesTable tbody tr.even,
#txnTypeTable tbody tr, #txnTypeTable tbody tr.odd, #txnTypeTable tbody tr.even {
    background: transparent !important;
}
#accountsTable tbody tr:hover,
#tabClassesTable tbody tr:hover,
#tabGroupsTable tbody tr:hover,
#tabCategoriesTable tbody tr:hover,
#txnTypeTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }

#txnTypeTable thead th { font-size: 0.78rem; white-space: nowrap; }
#txnTypeTable tbody td { font-size: 0.8rem; vertical-align: middle; }

/* ── Ledger Tabs ── */
.ledger-tab-nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}
.ledger-tab-nav .nav-link {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-size: 0.78rem;
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
    font-size: 18px;
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
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-accounts" role="tab" title="Chart of Accounts">
                                <span class="material-symbols-outlined">account_balance</span> Accounts
                            </a>
                            @endif
                            @if($canReadClasses)
                            @if($canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link {{ !$canReadAccounts ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-classes" role="tab" id="classes-tab-trigger" title="Account Classes">
                                <span class="material-symbols-outlined">layers</span> Classes
                            </a>
                            @endif
                            @if($canReadGroups)
                            @if($canReadClasses || $canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-groups" role="tab" id="groups-tab-trigger" title="Account Groups">
                                <span class="material-symbols-outlined">folder_open</span> Groups
                            </a>
                            @endif
                            @if($canReadCategories)
                            @if($canReadGroups || $canReadClasses || $canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-categories" role="tab" id="categories-tab-trigger" title="Categories">
                                <span class="material-symbols-outlined">label</span> Categories
                            </a>
                            @endif
                            @if($canReadTransactionTypes)
                            @if($canReadCategories || $canReadGroups || $canReadClasses || $canReadAccounts)<span class="tab-sep">|</span>@endif
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-transaction-types" role="tab" id="transaction-types-tab-trigger" title="Transaction Types">
                                <span class="material-symbols-outlined">receipt_long</span> Txn Types
                            </a>
                            @endif
                        </nav>
                        <div class="d-flex gap-2">
                            @if($canReadAccounts)
                            <span id="exportAccountsBtnWrap">
                                <a href="{{ route('ledger.accounts.export') }}" class="btn btn-success btn-sm" id="exportAccountsBtn" title="Export Excel">
                                    <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                                </a>
                            </span>
                            @endif
                            @permission('create_gl_accounts')
                            <span id="addAccountBtnWrap">
                                <button type="button" id="addAccountBtn" class="btn btn-primary btn-sm" title="Add Account">
                                    <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                </button>
                            </span>
                            @endpermission
                            @if($canReadTransactionTypes)
                            <span id="exportTypesBtnWrap" style="display:none;">
                                <a href="{{ route('finance.transaction-types.export') }}" class="btn btn-success btn-sm" id="exportTypesBtn" title="Export Excel">
                                    <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                                </a>
                            </span>
                            @endif
                            @permission('create_transaction_types')
                            <span id="addTypeBtnWrap" style="display:none;">
                                <button type="button" id="addTypeBtn" class="btn btn-primary btn-sm" title="Add New Type">
                                    <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                </button>
                            </span>
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

                            {{-- ── TRANSACTION TYPES TAB ── --}}
                            @if($canReadTransactionTypes)
                            <div class="tab-pane fade" id="tab-transaction-types" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="txnTypeTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Item Code</th>
                                                <th>Type Name</th>
                                                <th>Debit Account</th>
                                                <th>Credit Account</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
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

@if($canReadTransactionTypes)
{{-- Transaction Type Add / Edit Modal --}}
<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="typeModalTitle">Add Transaction Type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="typeForm">
                    <input type="hidden" id="modal_id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_type_name" maxlength="255" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Item Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_item_code" maxlength="50" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="default_amount" class="form-label">Default Amount</label>
                            <input type="number" class="form-control" id="default_amount" name="default_amount" step="0.01" min="0" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-control" id="category_id" name="category_id">
                                <option value="">— Select Category —</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Debit Account</label>
                            <select id="modal_debit_account_id" class="form-select form-select-sm w-100"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Credit Account</label>
                            <select id="modal_credit_account_id" class="form-select form-select-sm w-100"></select>
                        </div>
                        <div class="col-md-12" id="statusRow" style="display:none;">
                            <label class="form-label fw-semibold">Status</label>
                            <select id="modal_status" class="form-select form-select-sm" style="max-width:200px;">
                                <option value="5">Active</option>
                                <option value="1">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="saveTypeBtn">
                    <span class="btn-save-label d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                        <span id="saveBtnText">Save</span>
                    </span>
                    <span class="btn-save-spinner d-none d-flex align-items-center gap-1">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>Saving…</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Type Delete Confirmation Modal -->
<div class="modal fade" id="deleteTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this transaction type?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteTypeBtn">Delete</button>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    // ── Toggle Add/Export button visibility per active tab ──────────────────────────
    // Note: this app's global CSS forces `.btn { display: inline-flex !important; }`,
    // which beats jQuery's inline-style show()/hide()/toggle() on the buttons
    // themselves — so each button is wrapped in a plain <span> and the WRAPPER is
    // what gets shown/hidden instead.
    const $addAccountBtnWrap = $('#addAccountBtnWrap');
    const $exportAccountsBtnWrap = $('#exportAccountsBtnWrap');
    const $addTypeBtnWrap = $('#addTypeBtnWrap');
    const $exportTypesBtnWrap = $('#exportTypesBtnWrap');
    function syncTabActionButtons(href) {
        $addAccountBtnWrap.toggle(href === '#tab-accounts');
        $exportAccountsBtnWrap.toggle(href === '#tab-accounts');
        $addTypeBtnWrap.toggle(href === '#tab-transaction-types');
        $exportTypesBtnWrap.toggle(href === '#tab-transaction-types');
    }
    $('#ledgerTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        syncTabActionButtons($(this).attr('href'));
    });
    syncTabActionButtons($('#ledgerTabs a.nav-link.active').attr('href'));

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

    // ── TRANSACTION TYPES TAB (full CRUD, moved in from /finance/transaction-types) ──
    @if($canReadTransactionTypes)
    (function () {
        const dataUrl   = '{{ route("ajax.finance.transaction-types.data") }}';
        const getOneUrl = '{{ url("/ajax/finance/transaction-types") }}';
        const storeUrl  = '{{ route("finance.transaction-types.store") }}';
        const updateUrl = '{{ url("/finance/transaction-types") }}';
        const acctUrl   = '{{ route("ajax.gl.accounts.search") }}';

        const txnTypeTable = $('#txnTypeTable').DataTable({
            ajax: { url: dataUrl, dataSrc: 'data' },
            columns: [
                { data: null, orderable: false, searchable: false,
                  render: (d, t, r, m) => m.row + 1 },
                { data: 'item_code' },
                { data: 'name' },
                {
                    data: 'debit_account_code',
                    render: (d, t, row) => d
                        ? `<span style="font-size:0.8rem;">${d} — ${row.debit_account_name || ''}</span>`
                        : '<span class="text-muted">—</span>'
                },
                {
                    data: 'credit_account_code',
                    render: (d, t, row) => d
                        ? `<span style="font-size:0.8rem;">${d} — ${row.credit_account_name || ''}</span>`
                        : '<span class="text-muted">—</span>'
                },
                {
                    data: 'current_status_id',
                    render: d => d === 5
                        ? '<span class="badge bg-success-light text-success" style="font-size:0.72rem;">Active</span>'
                        : '<span class="badge bg-light text-secondary" style="font-size:0.72rem;">Inactive</span>'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: (d, t, row) => {
                        let buttons = '';
                        if (row.can_edit) {
                            buttons += `
                                <button class="btn btn-sm btn-light edit-type-btn py-0 px-1" data-id="${row.id}" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:17px;vertical-align:-4px;">edit_document</span>
                                </button>
                            `;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-sm btn-light delete-type-btn py-0 px-1" data-id="${row.id}" title="Delete">
                                    <span class="material-symbols-outlined" style="font-size:17px;vertical-align:-4px;">delete</span>
                                </button>
                            `;
                        }
                        return buttons || '';
                    }
                },
            ],
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
            order: [[1, 'asc']],
        });

        $('#category_id').select2({
            allowClear: true,
            width: '100%',
            parent: $('#typeModal')
        });

        function loadTypeOptions() {
            $.get("{{ route('ajax.finance.taxes.options') }}", function(res) {
                if (!res.success) {
                    toastr.error(res.error || 'Unable to load options');
                    return;
                }
                var category = $('#category_id');
                category.find('option:gt(0)').remove();
                (res.data.categories || []).forEach(function(item) {
                    category.append('<option value="' + item.id + '">' + item.category_name + '</option>');
                });
            }).fail(function() {
                toastr.error('Failed to load categories');
            });
        }

        function initTypeAccountSelect(selector) {
            $(selector).select2({
                dropdownParent: $('#typeModal'),
                theme: 'default',
                width: '100%',
                placeholder: 'Search by code or name…',
                allowClear: true,
                minimumInputLength: 0,
                ajax: {
                    url: acctUrl,
                    dataType: 'json',
                    delay: 250,
                    data: p => ({ q: p.term || '' }),
                    processResults: d => ({ results: d.results }),
                    cache: true,
                },
            });
        }

        initTypeAccountSelect('#modal_debit_account_id');
        initTypeAccountSelect('#modal_credit_account_id');
        loadTypeOptions();

        function setTypeAccountOption(selector, id, code, name) {
            const $s = $(selector);
            $s.empty();
            if (id) {
                const opt = new Option(code + ' — ' + name, id, true, true);
                $s.append(opt).trigger('change');
            } else {
                $s.trigger('change');
            }
        }

        $('#addTypeBtn').on('click', function () {
            $('#typeModalTitle').text('Add Transaction Type');
            $('#saveBtnText').text('Save');
            $('#modal_id').val('');
            $('#typeForm')[0].reset();
            $('#modal_type_name, #modal_item_code').removeClass('is-invalid');
            setTypeAccountOption('#modal_debit_account_id', null);
            setTypeAccountOption('#modal_credit_account_id', null);
            $('#statusRow').hide();
            $('#typeModal').modal('show');
        });

        $('#txnTypeTable').on('click', '.edit-type-btn', function () {
            const id = $(this).data('id');
            $('#typeModalTitle').text('Edit Transaction Type');
            $('#saveBtnText').text('Update');
            $('#modal_id').val(id);
            $('#statusRow').show();

            $.get(getOneUrl + '/' + id, function (res) {
                if (!res.success) { toastr.error('Failed to load record.'); return; }
                const d = res.data;
                $('#modal_type_name').val(d.type_name).removeClass('is-invalid');
                $('#modal_item_code').val(d.item_code).removeClass('is-invalid');
                $('#modal_status').val(d.current_status_id);
                $('#default_amount').val(d.default_amount || '0.00');
                setTypeAccountOption('#category_id', d.category_id, d.category_name, d.category_name);
                setTypeAccountOption('#modal_debit_account_id',  d.debit_account_id,  d.debit_account_code,  d.debit_account_name);
                setTypeAccountOption('#modal_credit_account_id', d.credit_account_id, d.credit_account_code, d.credit_account_name);
                $('#typeModal').modal('show');
            }).fail(() => toastr.error('Failed to load record.'));
        });

        $('#saveTypeBtn').on('click', function () {
            const typeName = $('#modal_type_name').val().trim();
            const itemCode = $('#modal_item_code').val().trim();
            let valid = true;

            if (!typeName) {
                $('#modal_type_name').addClass('is-invalid').next('.invalid-feedback').text('Type name is required.');
                valid = false;
            } else {
                $('#modal_type_name').removeClass('is-invalid');
            }
            if (!itemCode) {
                $('#modal_item_code').addClass('is-invalid').next('.invalid-feedback').text('Item code is required.');
                valid = false;
            } else {
                $('#modal_item_code').removeClass('is-invalid');
            }
            if (!valid) return;

            const id = $('#modal_id').val();
            const isEdit = !!id;

            const payload = {
                _token:             $('meta[name="csrf-token"]').attr('content'),
                type_name:          typeName,
                item_code:          itemCode,
                category_id:        $('#category_id').val() || '',
                default_amount:     $('#default_amount').val() || 0,
                debit_account_id:   $('#modal_debit_account_id').val() || '',
                credit_account_id:  $('#modal_credit_account_id').val() || '',
            };

            if (isEdit) {
                payload._method           = 'PUT';
                payload.current_status_id = $('#modal_status').val();
            }

            const url = isEdit ? updateUrl + '/' + id : storeUrl;

            $('#saveTypeBtn .btn-save-label').addClass('d-none');
            $('#saveTypeBtn .btn-save-spinner').removeClass('d-none');
            $('#saveTypeBtn').prop('disabled', true);

            $.ajax({
                url, method: 'POST', data: payload,
                success: function (res) {
                    resetTypeSaveBtn();
                    if (res.success) {
                        toastr.success(res.message || (isEdit ? 'Updated successfully.' : 'Created successfully.'));
                        $('#typeModal').modal('hide');
                        txnTypeTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Save failed.');
                    }
                },
                error: function (xhr) {
                    resetTypeSaveBtn();
                    const errs = xhr.responseJSON?.errors;
                    if (errs) {
                        toastr.error(Object.values(errs).flat().join('<br>'));
                    } else {
                        toastr.error(xhr.responseJSON?.error || 'Save failed.');
                    }
                },
            });

            function resetTypeSaveBtn() {
                $('#saveTypeBtn .btn-save-label').removeClass('d-none');
                $('#saveTypeBtn .btn-save-spinner').addClass('d-none');
                $('#saveTypeBtn').prop('disabled', false);
            }
        });

        let deleteTypeId = null;
        $(document).on('click', '.delete-type-btn', function () {
            deleteTypeId = $(this).data('id');
            $('#deleteTypeModal').modal('show');
        });

        $('#confirmDeleteTypeBtn').on('click', function () {
            if (!deleteTypeId) return;
            $.ajax({
                url: `/finance/transaction-types/${deleteTypeId}`,
                type: 'DELETE',
                data: { _token: $('meta[name="csrf-token"]').attr('content') },
                success: function () {
                    $('#deleteTypeModal').modal('hide');
                    txnTypeTable.ajax.reload(null, false);
                    toastr.success('Transaction type deleted successfully');
                },
                error: function (xhr) {
                    $('#deleteTypeModal').modal('hide');
                    toastr.error(xhr.responseJSON?.message || 'Error deleting record');
                }
            });
        });
    })();
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

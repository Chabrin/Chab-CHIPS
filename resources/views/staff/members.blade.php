@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Member Summary</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url('/manage/members') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Members Listing</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif
                        @if(isset($member))
                        <ul class="nav nav-tabs mb-3" id="memberTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="true">
                                    <span class="material-symbols-outlined me-1">person</span> Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="accounts-tab" data-bs-toggle="tab" data-bs-target="#accounts" type="button" role="tab" aria-controls="accounts" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">account_balance_wallet</span> Accounts
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="banks-tab" data-bs-toggle="tab" data-bs-target="#banks" type="button" role="tab" aria-controls="banks" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">account_balance</span> Banks
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="beneficiaries-tab" data-bs-toggle="tab" data-bs-target="#beneficiaries" type="button" role="tab" aria-controls="beneficiaries" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">groups</span> Beneficiaries
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions" type="button" role="tab" aria-controls="actions" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">action_key</span> Actions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="statements-tab" data-bs-toggle="tab" data-bs-target="#statements" type="button" role="tab" aria-controls="statements" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">description</span> Statements
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="memberTabContent">
                            <input type="hidden" id="member_number" name="member_number" value="{{ $member->member_no }}">
                            <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <h5 class="mb-1">{{ $member->all_names }} <small class="text-muted">({{ $member->member_no }})</small>
                                            <span class="badge badge-light-success ms-2">{{ optional($member->company)->company_name ?? 'No Company' }}</span>
                                        </h5>
                                        <div class="text-muted">ID: {{ $member->id_no }} &nbsp;|&nbsp; Email: {{ $member->e_mail }} &nbsp;|&nbsp; Phone: {{ $member->gsm_no }}</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card mb-3">
                                            <div class="card-header">Personal Info</div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Salutation</div>
                                                        <div>{{ optional($member->salutation)->name }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">First Name</div>
                                                        <div>{{ $member->first_name }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Last Name</div>
                                                        <div>{{ $member->last_name }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Other Names</div>
                                                        <div>{{ $member->other_names }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Date of Birth</div>
                                                        <div><b>{{ $member->dob ? \Carbon\Carbon::parse($member->dob)->toDateString() : '' }}</b></div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Gender</div>
                                                        <div>{{ optional($member->gender)->name }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Marital Status</div>
                                                        <div>{{ optional($member->maritalStatus)->name }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">National ID</div>
                                                        <div>{{ $member->id_no }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">PIN No</div>
                                                        <div>{{ $member->pin_no }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card mb-3">
                                            <div class="card-header">Employment</div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Employer</div>
                                                        <div>{{ $member->employer }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Occupation</div>
                                                        <div>{{ $member->occupation }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Salary</div>
                                                        <div>{{ $member->salary ? number_format($member->salary, 2) : '' }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Payroll ID</div>
                                                        <div><b>{{ $member->payrolid }}</b></div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Employment Date</div>
                                                        <div>{{ $member->employmentdate }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Previous Benefits</div>
                                                        <div>{{ $member->previous_benefits ? number_format($member->previous_benefits, 2) : '' }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Retirement Age</div>
                                                        <div>{{ $member->retage }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Expected Retirement</div>
                                                        <div><b>{{ $member->exptretyr ? \Carbon\Carbon::parse($member->exptretyr)->format('Y-m-d') : '' }}</b></div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Funds Source</div>
                                                        <div>{{ $member->funds_source }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Membership Type</div>
                                                        <div>{{ $member->memtype }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Scheme Company</div>
                                                        <div>{{ $member->scheme_company_id }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card mb-3">
                                            <div class="card-header">Contact & Address</div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Telephone</div>
                                                        <div>{{ $member->tel_no }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">GSM No</div>
                                                        <div>{{ $member->gsm_no }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Email</div>
                                                        <div>{{ $member->e_mail }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">CC Email</div>
                                                        <div>{{ $member->ccemail }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Post Address</div>
                                                        <div>{{ $member->post_address }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Physical Address</div>
                                                        <div>{{ $member->phys_address }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Estate</div>
                                                        <div>{{ $member->estate }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Street</div>
                                                        <div>{{ $member->street }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">House No</div>
                                                        <div>{{ $member->hse_no }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">LR No</div>
                                                        <div>{{ $member->lr_no }}</div>
                                                    </div>

                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Town</div>
                                                        <div>{{ $member->town_id }}</div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <div class="small text-muted">Country</div>
                                                        <div>{{ optional($member->country)->name }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="accounts" role="tabpanel" aria-labelledby="accounts-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-end mb-2">
                                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                                                <span class="material-symbols-outlined">add_circle</span>
                                                <span class="ms-1">Add Account</span>
                                            </button>
                                        </div>
                                        <div class="table-responsive custom-scrollbar">
                                            <div id="accounts-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
                                            <div id="member-accounts-error" class="text-danger small mb-2" style="display:none;"></div>
                                            <table class="display" id="member-accounts-dt">
                                                <thead>
                                                    <tr>
                                                        <th>Account No</th>
                                                        <th>Member No</th>
                                                        <th>Security Code</th>
                                                        <th>Fund Name</th>
                                                        <th>Status</th>
                                                        <th style="width:120px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="banks" role="tabpanel" aria-labelledby="banks-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-end mb-2">
                                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                                <span class="material-symbols-outlined">add_circle</span>
                                                <span class="ms-1">Add Bank</span>
                                            </button>
                                        </div>
                                        <div class="table-responsive custom-scrollbar">
                                            <div id="banks-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
                                            <div id="member-banks-error" class="text-danger small mb-2" style="display:none;"></div>
                                            <table class="display" id="member-banks-dt">
                                                <thead>
                                                    <tr>
                                                        <th>Account No</th>
                                                        <th>Account Name</th>
                                                        <th>Bank</th>
                                                        <th>Branch</th>
                                                        <th>Default</th>
                                                        <th style="width:120px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="beneficiaries" role="tabpanel" aria-labelledby="beneficiaries-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div id="beneficiariesContent">Loading beneficiaries...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="actions" role="tabpanel" aria-labelledby="actions-tab">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div id="retirementStatusContent">
                                                    <div class="text-center py-4">
                                                        <div class="spinner-border text-primary" role="status">
                                                            <span class="visually-hidden">Loading...</span>
                                                        </div>
                                                        <div class="mt-2">Loading retirement status...</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div id="retirementActionsContent">
                                                    <div class="text-center py-4">
                                                        <div class="spinner-border text-primary" role="status">
                                                            <span class="visually-hidden">Loading...</span>
                                                        </div>
                                                        <div class="mt-2">Loading actions...</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="statements" role="tabpanel" aria-labelledby="statements-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="mb-3">Generate Member Statement</h6>
                                                <form id="memberStatementForm">
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">Select Account <span class="text-danger">*</span></label>
                                                            <select id="statement_account_no" name="account_no" class="form-control" required>
                                                                <option value="">-- Select Account --</option>
                                                            </select>
                                                            <div class="invalid-feedback">Please select an account</div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">As At Date <span class="text-danger">*</span></label>
                                                            <input type="date" id="statement_end_date" name="end_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                            <div class="invalid-feedback">Please select a date</div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">&nbsp;</label>
                                                            <div>
                                                                <button type="button" id="generateStatementBtn" class="btn btn-primary d-flex align-items-center">
                                                                    <span class="material-symbols-outlined me-1">picture_as_pdf</span>
                                                                    <span class="ms-1">Generate Statement</span>
                                                                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                                <div id="statement-errors" class="alert alert-danger mt-3" style="display:none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================= -->
<!-- MODALS SECTION                              -->
<!-- ============================================= -->

<!-- Retirement Modal -->
<div class="modal fade" id="retirementModal" tabindex="-1" aria-labelledby="retirementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="retirementModalLabel">
                    <span class="material-symbols-outlined me-2">action_key</span>
                    Retire Member
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="retirementForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Member</label>
                        <div class="fw-bold">{{ $member->all_names }} ({{ $member->member_no }})</div>
                    </div>

                    <div class="mb-3">
                        <label for="retirement_type" class="form-label required">Retirement Type</label>
                        <select class="form-select" id="retirement_type" name="retirement_type" required>
                            <option value="">Select retirement type</option>
                            <option value="normal">Normal Retirement</option>
                            <option value="early">Early Retirement</option>
                            <option value="late">Late Retirement</option>
                        </select>
                        <div class="form-text">
                            <small id="retirement_description"></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="retirement_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="retirement_notes" name="retirement_notes" rows="3" placeholder="Add any additional notes about this retirement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined me-2">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success d-flex align-items-center" id="confirmRetirementBtn">
                        <span class="material-symbols-outlined me-2">check_circle</span>
                        Confirm Retirement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountModalLabel">Add Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="memberAddAccountForm">
                    <input type="hidden" name="member_no" value="{{ $member->member_no }}">
                    <input type="hidden" name="company_id" value="{{ $member->company_id }}">
                    <div class="container-fluid">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="add_security_code" class="form-label">Security (Fund) <span style="color:red">*</span></label>
                                <select class="form-select" id="add_security_code" name="security_code" required>
                                    <option value="">Select fund</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="add_description" class="form-label">Description</label>
                                <input type="text" class="form-control" name="description" id="add_description" placeholder="Optional description">
                            </div>
                            <div class="col-md-6 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-secondary d-flex align-items-center me-2" data-bs-dismiss="modal">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </button>
                                <button type="submit" class="btn btn-success d-flex align-items-center" id="saveAddMemberAccountBtn">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Save</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAccountModalLabel">Edit Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="memberEditAccountForm">
                    <input type="hidden" name="id" id="editMemberAccountId">
                    <input type="hidden" name="member_no" value="{{ $member->member_no }}">
                    <input type="hidden" name="company_id" value="{{ $member->company_id }}">
                    <div class="container-fluid">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="edit_security_code" class="form-label">Security (Fund) <span style="color:red">*</span></label>
                                <select class="form-select" id="edit_security_code" name="security_code" required>
                                    <option value="">Select fund</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_description" class="form-label">Description</label>
                                <input type="text" class="form-control" name="description" id="edit_description" placeholder="Optional description">
                            </div>
                            <div class="col-md-6 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-secondary d-flex align-items-center me-2" data-bs-dismiss="modal">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </button>
                                <button type="submit" class="btn btn-success d-flex align-items-center" id="saveEditMemberAccountBtn">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Save Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Bank Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1" aria-labelledby="addBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBankModalLabel">Add Bank Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="memberAddBankForm">
                    <input type="hidden" name="member_no" value="{{ $member->member_no }}">
                    <input type="hidden" name="company_id" value="{{ $member->company_id }}">
                    <div class="container-fluid">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="add_account_no" class="form-label">Account No <span style="color:red">*</span></label>
                                <input type="text" class="form-control" name="account_no" id="add_account_no" required placeholder="Enter account number">
                            </div>
                            <div class="col-md-6">
                                <label for="add_account_name" class="form-label">Account Name <span style="color:red">*</span></label>
                                <input type="text" class="form-control" name="account_name" id="add_account_name" required placeholder="Enter account name">
                            </div>

                            <div class="col-md-6">
                                <label for="add_member_bank_id" class="form-label">Bank <span style="color:red">*</span></label>
                                <select class="form-select" id="add_member_bank_id" name="bank_id" required>
                                    <option value="">Select bank</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="add_member_branch_id" class="form-label">Branch <span style="color:red">*</span></label>
                                <select class="form-select" id="add_member_branch_id" name="branch_id" required>
                                    <option value="">Select branch</option>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="add_default_account" name="default_account" value="1">
                                    <label class="form-check-label ms-2" for="add_default_account">Set as Default Account</label>
                                </div>
                            </div>

                            <div class="col-md-6 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-secondary d-flex align-items-center me-2" data-bs-dismiss="modal">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </button>
                                <button type="submit" class="btn btn-success d-flex align-items-center" id="saveAddMemberBankBtn">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Save</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bank Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1" aria-labelledby="editBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBankModalLabel">Edit Bank Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="memberEditBankForm">
                    <input type="hidden" name="id" id="editMemberBankId">
                    <input type="hidden" name="member_no" value="{{ $member->member_no }}">
                    <input type="hidden" name="company_id" value="{{ $member->company_id }}">
                    <div class="container-fluid">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="edit_account_no" class="form-label">Account No <span style="color:red">*</span></label>
                                <input type="text" class="form-control" name="account_no" id="edit_account_no" required placeholder="Enter account number">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_account_name" class="form-label">Account Name <span style="color:red">*</span></label>
                                <input type="text" class="form-control" name="account_name" id="edit_account_name" required placeholder="Enter account name">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_member_bank_id" class="form-label">Bank <span style="color:red">*</span></label>
                                <select class="form-select" id="edit_member_bank_id" name="bank_id" required>
                                    <option value="">Select bank</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_member_branch_id" class="form-label">Branch <span style="color:red">*</span></label>
                                <select class="form-select" id="edit_member_branch_id" name="branch_id" required>
                                    <option value="">Select branch</option>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="edit_default_account" name="default_account" value="1">
                                    <label class="form-check-label ms-2" for="edit_default_account">Set as Default Account</label>
                                </div>
                            </div>

                            <div class="col-md-6 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-secondary d-flex align-items-center me-2" data-bs-dismiss="modal">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </button>
                                <button type="submit" class="btn btn-success d-flex align-items-center" id="saveEditMemberBankBtn">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Save Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

<style>
    /* small spinner animation for material icons when .spin is toggled by JS */
    .material-symbols-outlined.spin {
        display: inline-block;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }
</style>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(function() {
        function showSuccess(msg) {
            toastr.success(msg);
        }

        function showError(msg) {
            toastr.error(msg);
        }

        // accounts DataTable
        var memberAccountsTable = $('#member-accounts-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/members/accounts/list') }}",
                type: 'POST',
                data: {
                    member_no: "{{ $member->member_no }}",
                    company_id: "{{ $member->company_id }}",
                    _token: "{{ csrf_token() }}"
                },
                dataType: 'json',
                dataSrc: function(json) {
                    if (!json || json.error) {
                        var m = (json && json.error) ? json.error : 'Failed to load accounts';
                        showError(m);
                        $('#member-accounts-error').text(m).show();
                        $('#member-accounts-dt tbody').html('<tr class="odd"><td valign="top" colspan="5" class="dataTables_empty">Error loading data</td></tr>');
                        return [];
                    }
                    $('#member-accounts-error').hide().text('');
                    try {
                        $('#member-accounts-dt tbody').empty();
                    } catch (e) {}
                    return json.accounts || [];
                },
                error: function(xhr) {
                    var msg = 'Failed to load accounts';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    showError(msg);
                    $('#member-accounts-error').text(msg).show();
                    try {
                        memberAccountsTable.clear().draw();
                        memberAccountsTable.processing(false);
                    } catch (e) {}
                    $('#member-accounts-dt tbody').html('<tr class="odd"><td valign="top" colspan="5" class="dataTables_empty">Error loading data</td></tr>');
                }
            },
            columns: [{
                    data: 'account_no'
                },
                {
                    data: 'member_no'
                },
                {
                    data: 'security_code'
                },
                {
                    data: 'fund_name',
                    defaultContent: ''
                },
                //add column for status, if closed==1 show 'Closed' else 'Active'
                {
                    data: 'closed',
                    render: function(data, type, row) {
                        return row.closed == 1 ? '<span class="badge badge-light-danger">Closed</span>' : '<span class="badge badge-light-success">Active</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-member-account d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-security_code="' + (row.security_code || '') + '" data-description="' + (row.description || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-member-account d-flex align-items-center" ' +
                            'data-id="' + row.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</button>' +
                            '</div>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        // populate funds dropdown on table load for both add & edit forms
        memberAccountsTable.on('xhr', function() {
            var json = memberAccountsTable.ajax.json();
            if (json && json.funds_list) {
                var opts = '<option value="">Select fund</option>';
                $.each(json.funds_list, function(i, f) {
                    opts += '<option value="' + f.fund_code + '">' + f.fund_name + ' (' + f.fund_code + ')</option>';
                });
                $('#add_security_code, #edit_security_code').html(opts);
            }
        });

        // Add account
        $('#memberAddAccountForm').on('submit', function(e) {
            e.preventDefault();
            var url = "{{ url('/ajax/members') }}/{{ $member->member_no }}/{{ $member->company_id }}/accounts";
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveAddMemberAccountBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.post(url, formData).done(function(res) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                if (res.success) {
                    showSuccess('Saved successfully');
                    $('#addAccountModal').modal('hide');
                    $('#memberAddAccountForm')[0].reset();
                    memberAccountsTable.ajax.reload(null, false);
                } else {
                    showError(res.error || 'Error saving');
                }
            }).fail(function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).join('<br>');
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
            });
        });

        // Edit account
        $(document).on('click', '.edit-member-account', function() {
            var $btn = $(this);
            $('#editMemberAccountId').val($btn.data('id'));
            $('#edit_security_code').val($btn.data('security_code'));
            $('#edit_description').val($btn.data('description'));
            $('#editAccountModal').modal('show');
        });

        $('#memberEditAccountForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#editMemberAccountId').val();
            var url = "{{ url('/ajax/members') }}/{{ $member->member_no }}/{{ $member->company_id }}/accounts/" + id;
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveEditMemberAccountBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.post(url, formData).done(function(res) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                if (res.success) {
                    showSuccess('Updated successfully');
                    $('#editAccountModal').modal('hide');
                    $('#memberEditAccountForm')[0].reset();
                    memberAccountsTable.ajax.reload(null, false);
                } else {
                    showError(res.error || 'Error updating');
                }
            }).fail(function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).join('<br>');
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
            });
        });

        // Delete account
        $(document).on('click', '.delete-member-account', function() {
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/members') }}/{{ $member->member_no }}/{{ $member->company_id }}/accounts/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        showSuccess('Deleted');
                        memberAccountsTable.ajax.reload(null, false);
                    } else {
                        showError(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    showError(msg);
                }
            });
        });

        // ensure forms reset when modals hide
        $('#addAccountModal').on('hidden.bs.modal', function() {
            $('#memberAddAccountForm')[0].reset();
        });
        $('#editAccountModal').on('hidden.bs.modal', function() {
            $('#memberEditAccountForm')[0].reset();
            $('#editMemberAccountId').val('');
        });
    });
</script>

<script>
    $(function() {
        // placeholder: front-end will fetch accounts/banks/beneficiaries via AJAX when tabs are shown
        $('#accounts-tab').on('shown.bs.tab', function() {
            $('#accountsContent').text('No accounts yet.');
        });
        $('#banks-tab').on('shown.bs.tab', function() {
            // clear any previous inline error and reload DataTable
            $('#member-banks-error').hide().text('');
            if (typeof memberBanksTable !== 'undefined') {
                memberBanksTable.ajax.reload(null, false);
            }
        });
        $('#beneficiaries-tab').on('shown.bs.tab', function() {
            $('#beneficiariesContent').text('No beneficiaries yet.');
        });
    });
</script>

<script>
    $(function() {
        // Populate accounts dropdown when statements tab is shown
        $('#statements-tab').on('shown.bs.tab', function() {
            loadMemberAccounts();
        });

        function loadMemberAccounts() {
            var memberNo = "{{ $member->member_no }}";
            var companyId = "{{ $member->company_id }}";

            $.ajax({
                url: "{{ url('/ajax/members/accounts/list') }}",
                type: 'POST',
                data: {
                    member_no: memberNo,
                    company_id: companyId,
                    _token: "{{ csrf_token() }}"
                },
                dataType: 'json',
                success: function(response) {
                    var $select = $('#statement_account_no');
                    $select.empty().append('<option value="">-- Select Account --</option>');

                    if (response && response.accounts && response.accounts.length > 0) {
                        $.each(response.accounts, function(index, account) {
                            $select.append(
                                $('<option></option>')
                                .attr('value', account.account_no)
                                .text(account.account_no + ' - ' + (account.fund_name || 'Unknown Fund'))
                            );
                        });
                    } else {
                        toastr.info('No accounts found for this member');
                    }
                },
                error: function(xhr) {
                    toastr.error('Failed to load member accounts');
                    console.error('Error loading accounts:', xhr);
                }
            });
        }

        // Generate statement button click
        $('#generateStatementBtn').on('click', function() {
            var $btn = $(this);
            var $spinner = $btn.find('.spinner-border');
            var $form = $('#memberStatementForm');

            // Clear previous errors
            $('#statement-errors').hide().empty();
            $form.find('.is-invalid').removeClass('is-invalid');

            // Validate form
            var accountNo = $('#statement_account_no').val();
            var endDate = $('#statement_end_date').val();
            var memberNo = "{{ $member->member_no }}";

            var isValid = true;
            if (!accountNo) {
                $('#statement_account_no').addClass('is-invalid');
                isValid = false;
            }
            if (!endDate) {
                $('#statement_end_date').addClass('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                toastr.error('Please fill all required fields');
                return;
            }

            // Disable button and show spinner
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');

            // Send POST request with data
            fetch("{{ url('/get/statements/summary/pdf') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        account_no: accountNo,
                        end_date: endDate,
                        member_no: memberNo
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        // Check if response is JSON or HTML
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            return response.json().then(data => {
                                throw new Error(data.message || data.error || 'Failed to generate statement');
                            });
                        } else {
                            // Likely an HTML error page
                            throw new Error('Server error occurred. Please check if you are logged in and try again.');
                        }
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Create blob URL and open in new window
                    var blobUrl = URL.createObjectURL(blob);
                    window.open(blobUrl, '_blank');
                    toastr.success('Statement generated successfully');
                })
                .catch(error => {
                    console.error('Error generating statement:', error);
                    $('#statement-errors').text(error.message || 'Failed to generate statement').show();
                    toastr.error(error.message || 'Failed to generate statement');
                })
                .finally(() => {
                    // Re-enable button and hide spinner
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });
    });
</script>



<script>
    $(function() {
        // use toastr for notifications (success/danger) to match industry pattern
        function showSuccess(msg) {
            toastr.success(msg);
        }

        function showError(msg) {
            toastr.error(msg);
        }

        // member banks DataTable
        var memberBanksTable = $('#member-banks-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/members/banks/list') }}",
                type: 'POST',
                data: {
                    member_no: "{{ $member->member_no }}",
                    company_id: "{{ $member->company_id }}",
                    _token: "{{ csrf_token() }}"
                },
                dataType: 'json',
                dataSrc: function(json) {
                    // handle error payloads gracefully and show toast + inline error instead of breaking DataTables
                    if (!json) {
                        var m = 'Failed to load banks';
                        showError(m);
                        $('#member-banks-error').text(m).show();
                        // replace table body with a clear error row
                        $('#member-banks-dt tbody').html('<tr class="odd"><td valign="top" colspan="6" class="dataTables_empty">Error loading data</td></tr>');
                        return [];
                    }
                    if (json.error) {
                        showError(json.error);
                        $('#member-banks-error').text(json.error).show();
                        $('#member-banks-dt tbody').html('<tr class="odd"><td valign="top" colspan="6" class="dataTables_empty">Error loading data</td></tr>');
                        return [];
                    }
                    // success: clear inline error and any custom tbody message
                    $('#member-banks-error').hide().text('');
                    try {
                        $('#member-banks-dt tbody').empty();
                    } catch (e) {}
                    return json.banks || [];
                },
                error: function(xhr, status, err) {
                    var msg = 'Failed to load banks';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    showError(msg);
                    $('#member-banks-error').text(msg).show();
                    try {
                        if (typeof memberBanksTable !== 'undefined') {
                            memberBanksTable.clear().draw();
                            memberBanksTable.processing(false);
                        }
                    } catch (e) {}
                    // ensure table shows a proper error row instead of 'Loading...'
                    $('#member-banks-dt tbody').html('<tr class="odd"><td valign="top" colspan="6" class="dataTables_empty">Error loading data</td></tr>');
                }
            },
            columns: [{
                    data: 'account_no'
                },
                {
                    data: 'account_name'
                },
                {
                    data: function(row) {
                        return row.bank ? row.bank.name : '';
                    },
                    defaultContent: ''
                },
                {
                    data: function(row) {
                        return row.branch ? row.branch.branch_name : '';
                    },
                    defaultContent: ''
                },
                {
                    data: function(row) {
                        return (row.default_account == 1 || row.default_account === 1 || row.default_account === '1') ? 'Yes' : 'No';
                    },
                    defaultContent: ''
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-member-bank d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-account_no="' + (row.account_no || '') + '" data-account_name="' + (row.account_name || '') + '" data-bank_id="' + (row.bank_id || '') + '" data-branch_id="' + (row.branch_id || '') + '" data-default_account="' + (row.default_account == 1 ? 1 : 0) + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-member-bank d-flex align-items-center" ' +
                            'data-id="' + row.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</button>' +
                            '</div>';
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

        // populate banks dropdown on table load for both add & edit bank forms
        memberBanksTable.on('xhr', function() {
            var json = memberBanksTable.ajax.json();
            if (json && json.banks_list) {
                var opts = '<option value="">Select bank</option>';
                $.each(json.banks_list, function(i, b) {
                    opts += '<option value="' + b.id + '">' + b.name + ' (' + b.bank_code + ')</option>';
                });
                $('#add_member_bank_id, #edit_member_bank_id').html(opts);
            }
        });

        // when bank changes, load branches for both add & edit forms
        var currentEditingBranchId = null; // used to preselect branch when editing
        $(document).on('change', '#add_member_bank_id, #edit_member_bank_id', function() {
            var bankId = $(this).val();
            // choose corresponding branch select based on which triggered
            var isAdd = $(this).attr('id') === 'add_member_bank_id';
            var $branch = isAdd ? $('#add_member_branch_id') : $('#edit_member_branch_id');
            $branch.html('<option value="">Loading...</option>');
            if (!bankId) {
                $branch.html('<option value="">Select branch</option>');
                return;
            }
            $.get("{{ url('/ajax/banks') }}\/" + bankId + "/branches", function(res) {
                var opts = '<option value="">Select branch</option>';
                if (res && res.branches) {
                    $.each(res.branches, function(i, br) {
                        opts += '<option value="' + br.id + '">' + br.branch_name + ' (' + br.branch_code + ')</option>';
                    });
                }
                $branch.html(opts);
                // if we're editing, preselect the branch that belongs to the record
                if (!isAdd && currentEditingBranchId) {
                    $branch.val(currentEditingBranchId);
                    currentEditingBranchId = null;
                }
            }).fail(function() {
                $branch.html('<option value="">Select branch</option>');
                currentEditingBranchId = null;
            });
        });

        // Add bank
        $('#memberAddBankForm').on('submit', function(e) {
            e.preventDefault();
            var url = "{{ url('/ajax/members') }}/{{ $member->member_no }}/{{ $member->company_id }}/banks";
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveAddMemberBankBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.post(url, formData).done(function(res) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                if (res.success) {
                    showSuccess('Saved successfully');
                    $('#addBankModal').modal('hide');
                    $('#memberAddBankForm')[0].reset();
                    memberBanksTable.ajax.reload(null, false);
                } else {
                    showError(res.error || 'Error saving');
                }
            }).fail(function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).join('<br>');
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
            });
        });

        // Edit bank
        $(document).on('click', '.edit-member-bank', function() {
            var $btn = $(this);
            $('#editMemberBankId').val($btn.data('id'));
            $('#edit_account_no').val($btn.data('account_no'));
            $('#edit_account_name').val($btn.data('account_name'));
            // set branch after bank options load via currentEditingBranchId
            currentEditingBranchId = $btn.data('branch_id');
            $('#edit_member_bank_id').val($btn.data('bank_id')).trigger('change');
            $('#edit_default_account').prop('checked', $btn.data('default_account') == 1);
            $('#editBankModal').modal('show');
        });

        $('#memberEditBankForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#editMemberBankId').val();
            var url = "{{ url('/ajax/members') }}/{{ $member->member_no }}/{{ $member->company_id }}/banks/" + id;
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveEditMemberBankBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.post(url, formData).done(function(res) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                if (res.success) {
                    showSuccess('Updated successfully');
                    $('#editBankModal').modal('hide');
                    $('#memberEditBankForm')[0].reset();
                    memberBanksTable.ajax.reload(null, false);
                } else {
                    showError(res.error || 'Error updating');
                }
            }).fail(function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).join('<br>');
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
            });
        });

        $(document).on('click', '.delete-member-bank', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/members') }}/{{ $member->member_no }}/{{ $member->company_id }}/banks/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        showSuccess('Deleted');
                        memberBanksTable.ajax.reload(null, false);
                    } else {
                        showError(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                    showError(msg);
                }
            });
        });

        $('#addBankModal').on('hidden.bs.modal', function() {
            $('#memberAddBankForm')[0].reset();
        });
        $('#editBankModal').on('hidden.bs.modal', function() {
            $('#memberEditBankForm')[0].reset();
            $('#editMemberBankId').val('');
        });
    });

    // Retirement functionality
    $(document).ready(function() {
        // Load Actions tab content when the tab is shown
        $('#actions-tab').on('shown.bs.tab', function() {
            loadActionsTabContent();
        });

        // Function to load Actions tab content via AJAX
        function loadActionsTabContent() {
            const memberNo = $('#member_number').val();

            if (!memberNo) {
                showError('Member number not found');
                return;
            }

            $.ajax({
                url: '/members/retirementdata',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    member_no: memberNo
                }),
                success: function(response) {
                    if (response.success) {
                        // Update retirement status content
                        $('#retirementStatusContent').html(response.statusContent);

                        // Update retirement actions content
                        $('#retirementActionsContent').html(response.actionsContent);
                    } else {
                        showError(response.error || 'Failed to load actions data');
                        $('#retirementStatusContent').html('<div class="text-center text-danger">Failed to load retirement status</div>');
                        $('#retirementActionsContent').html('<div class="text-center text-danger">Failed to load actions</div>');
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Failed to load actions data';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    showError(errorMessage);
                    $('#retirementStatusContent').html('<div class="text-center text-danger">Error loading retirement status</div>');
                    $('#retirementActionsContent').html('<div class="text-center text-danger">Error loading actions</div>');
                }
            });
        }

        // Update retirement type descriptions
        $('#retirement_type').change(function() {
            const type = $(this).val();
            let description = '';

            switch (type) {
                case 'normal':
                    description = 'Standard retirement at the expected retirement age.';
                    break;
                case 'early':
                    description = 'Retirement before the normal retirement age. May affect pension calculations.';
                    break;
                case 'late':
                    description = 'Retirement after the normal retirement age.';
                    break;
                default:
                    description = '';
            }

            $('#retirement_description').text(description);
        });

        // Handle retirement form submission
        $('#retirementForm').submit(function(e) {
            e.preventDefault();

            const memberNo = $('#member_number').val();
            console.log('Member No:', memberNo); // Debugging line
            const retirementType = $('#retirement_type').val();
            const notes = $('#retirement_notes').val();

            if (!retirementType) {
                showError('Please select a retirement type');
                return;
            }

            // Show loading state
            const $submitBtn = $('#confirmRetirementBtn');
            $submitBtn.prop('disabled', true);
            $submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processing...');

            $.ajax({
                url: '/members/initiate-retirement',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    member_no: memberNo,
                    retirement_type: retirementType,
                    notes: notes
                }),
                success: function(response) {
                    if (response.success) {
                        toastr.success('Retirement initiated successfully');
                        $('#retirementModal').modal('hide');

                        // Reload the Actions tab content to show updated status
                        loadActionsTabContent();
                    } else {
                        toastr.error(response.error || 'Failed to initiate retirement');
                        resetRetirementButton();
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Failed to initiate retirement';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    toastr.error(errorMessage);
                    resetRetirementButton();
                }
            });
        });

        function resetRetirementButton() {
            const $submitBtn = $('#confirmRetirementBtn');
            $submitBtn.prop('disabled', false);
            $submitBtn.html('<span class="material-symbols-outlined me-2">check_circle</span>Confirm Retirement');
        }

        // Reset form when modal is hidden
        $('#retirementModal').on('hidden.bs.modal', function() {
            $('#retirementForm')[0].reset();
            $('#retirement_description').text('');
            resetRetirementButton();
        });
    });
</script>
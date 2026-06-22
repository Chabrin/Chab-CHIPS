@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top: 10px;">
    <div class="container-fluid">

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{--  PAGE HEADER                                                         --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:24px;color:#e74c3c;">exit_to_app</span>
                    Tenant Exit Processing
                </h4>
                <small class="text-muted">Search for a tenant by name or property to begin the exit workflow.</small>
            </div>
            <div id="exitStatusBadge" class="d-none">
                <span class="badge bg-warning text-dark fs-6 px-3 py-2" id="exitStatusText"></span>
            </div>
            <a href="{{ route('tenant.exit.exited') }}" class="btn btn-sm btn-outline-primary">
                <span class="material-symbols-outlined align-middle" style="font-size:16px;">exit_to_app</span>
                View Exited Tenants
            </a>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{--  SEARCH PANEL                                                         --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="card border-0 shadow-sm mb-3" id="searchPanel">
            <div class="card-body pb-2">
                <ul class="nav nav-pills nav-fill mb-3" id="searchTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="tab-tenant" onclick="switchSearchTab('tenant')">
                            <span class="material-symbols-outlined align-middle" style="font-size:16px;">person_search</span>
                            Search by Tenant
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="tab-property" onclick="switchSearchTab('property')">
                            <span class="material-symbols-outlined align-middle" style="font-size:16px;">location_city</span>
                            Search by Property → Unit
                        </button>
                    </li>
                </ul>

                {{-- Tenant search --}}
                <div id="tenantSearchPane">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><span class="material-symbols-outlined" style="font-size:18px;">search</span></span>
                        <input type="text" id="tenantSearchInput" class="form-control" placeholder="Name, reference, phone, email or ID number…" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearTenantSearch()">
                            <span class="material-symbols-outlined" style="font-size:16px;">close</span>
                        </button>
                    </div>
                    <div id="tenantSearchResults" class="position-relative"></div>
                </div>

                {{-- Property → Unit search --}}
                <div id="propertySearchPane" class="d-none">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><span class="material-symbols-outlined" style="font-size:18px;">apartment</span></span>
                                <input type="text" id="propertySearchInput" class="form-control" placeholder="Search property…" autocomplete="off">
                            </div>
                            <div id="propertySearchResults" class="position-relative"></div>
                        </div>
                        <div class="col-md-6">
                            <select id="unitSelect" class="form-select" disabled>
                                <option value="">— Select unit —</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{--  MAIN CONTENT (hidden until tenant loaded)                           --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div id="exitWorkspace" class="d-none">

            {{-- Hidden state --}}
            <input type="hidden" id="stateTenantId">
            <input type="hidden" id="stateTenantEncId">
            <input type="hidden" id="statePropertyId">
            <input type="hidden" id="stateUnitId">
            <input type="hidden" id="stateExitId">

            {{-- Tenant Banner --}}
            <div class="card border-0 shadow-sm mb-3" style="border-left: 4px solid #e74c3c !important; border-left-width:4px;">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0" id="bannerName">-</h5>
                            <small class="text-muted"><span id="bannerRef">-</span> &bull; <span id="bannerMobile">-</span> &bull; <span id="bannerEmail">-</span></small>
                        </div>
                        <div class="col-md-3 text-md-center">
                            <div class="fw-bold" id="bannerProperty">-</div>
                            <small class="text-muted">Unit: <strong id="bannerUnit">-</strong></small>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <div class="text-muted" style="font-size:0.78rem;">Closing Balance</div>
                            <div class="fw-bold fs-5" id="bannerBalance" style="color:#e74c3c;">-</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab navigation --}}
            <ul class="nav nav-tabs mb-0" id="workspaceTabs">
                <li class="nav-item"><button class="nav-link active" onclick="switchWorkspaceTab('exit-details')">
                    <span class="material-symbols-outlined align-middle" style="font-size:15px;">assignment_return</span> Exit Details</button></li>
                <li class="nav-item"><button class="nav-link" onclick="switchWorkspaceTab('lease-history')">
                    <span class="material-symbols-outlined align-middle" style="font-size:15px;">history</span> Lease History</button></li>
                <li class="nav-item"><button class="nav-link" onclick="switchWorkspaceTab('statement')">
                    <span class="material-symbols-outlined align-middle" style="font-size:15px;">receipt_long</span> Full Statement</button></li>
                <li class="nav-item"><button class="nav-link" onclick="switchWorkspaceTab('inspection')">
                    <span class="material-symbols-outlined align-middle" style="font-size:15px;">checklist</span> Unit Inspection</button></li>
                <li class="nav-item"><button class="nav-link" onclick="switchWorkspaceTab('communications')">
                    <span class="material-symbols-outlined align-middle" style="font-size:15px;">mark_email_read</span> Communications</button></li>
                <li class="nav-item"><button class="nav-link" onclick="switchWorkspaceTab('finalise')">
                    <span class="material-symbols-outlined align-middle" style="font-size:15px;">task_alt</span> Finalise Exit</button></li>
            </ul>

            <div class="card border-0 shadow-sm" style="border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-body">

                {{-- ────────────── TAB: EXIT DETAILS ────────────── --}}
                <div id="tab-exit-details" class="workspace-tab">
                    <h6 class="fw-bold mb-3">Exit Details</h6>
                    <form id="exitDetailsForm">
                        <input type="hidden" name="tenant_id" id="exitTenantId">
                        <input type="hidden" name="property_id" id="exitPropertyId">
                        <input type="hidden" name="unit_id" id="exitUnitId">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Vacate Notice Date <span class="text-danger">*</span></label>
                                <input type="date" name="vacate_notice_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Intended Exit Date <span class="text-danger">*</span></label>
                                <input type="date" name="intended_exit_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Actual Exit Date</label>
                                <input type="date" name="actual_exit_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reason for Exit <span class="text-danger">*</span></label>
                                <select name="exit_reason" class="form-select" required>
                                    <option value="">Select…</option>
                                    <option>End of Lease</option>
                                    <option>Voluntary Vacate</option>
                                    <option>Eviction</option>
                                    <option>Transfer</option>
                                    <option>Upgrade / Downgrade</option>
                                    <option>Non-Payment</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Notes</label>
                                <textarea name="exit_notes" class="form-control" rows="2" placeholder="Any additional context…"></textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined align-middle" style="font-size:16px;">save</span> Save Exit Details
                            </button>
                            <div id="exitDetailsSaving" class="d-none align-items-center gap-2">
                                <div class="spinner-border spinner-border-sm text-primary"></div> Saving…
                            </div>
                        </div>
                    </form>

                    {{-- Deposits summary --}}
                    <hr class="my-4">
                    <h6 class="fw-bold mb-2">Deposits on Record</h6>
                    <div id="depositsTable">
                        <div class="text-muted text-center py-3">Load a tenant to view deposits.</div>
                    </div>
                </div>

                {{-- ────────────── TAB: LEASE HISTORY ────────────── --}}
                <div id="tab-lease-history" class="workspace-tab d-none">
                    <h6 class="fw-bold mb-3">All Units / Leases Occupied</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered mb-0" id="leaseHistoryTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Lease Ref</th>
                                    <th>Property</th>
                                    <th>Unit</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="leaseHistoryBody">
                                <tr><td colspan="6" class="text-center text-muted py-3">Load a tenant to view history.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-2">Completed Stay History</h6>
                    <p class="text-muted mb-2" style="font-size:0.82rem;">Permanent record of all tenancies this person has completed — showing landlord, property, unit and duration.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered mb-0" style="font-size:0.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Property</th>
                                    <th>Unit</th>
                                    <th>Landlord</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody id="stayHistoryBody">
                                <tr><td colspan="7" class="text-center text-muted py-3">No completed stays on record.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ────────────── TAB: STATEMENT ────────────── --}}
                <div id="tab-statement" class="workspace-tab d-none">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Full Tenant Statement (All Time)</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="text-muted" style="font-size:0.8rem;">Closing Balance:</span>
                            <span class="badge fs-6 px-3" id="statementBalanceBadge" style="background:#1a1a2e;">-</span>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height:480px;overflow-y:auto;">
                        <table class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Narration</th>
                                    <th class="text-end">Debit (KES)</th>
                                    <th class="text-end">Credit (KES)</th>
                                    <th class="text-end">Balance (KES)</th>
                                </tr>
                            </thead>
                            <tbody id="statementBody">
                                <tr><td colspan="6" class="text-center text-muted py-3">Load a tenant to view statement.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ────────────── TAB: INSPECTION ────────────── --}}
                <div id="tab-inspection" class="workspace-tab d-none">
                    <h6 class="fw-bold mb-3">Unit Condition Inspection</h6>
                    <form id="inspectionForm" enctype="multipart/form-data">
                        <input type="hidden" name="exit_id" id="inspExitId">
                        <input type="hidden" name="tenant_id" id="inspTenantId">
                        <input type="hidden" name="unit_id" id="inspUnitId">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Inspection Date <span class="text-danger">*</span></label>
                                <input type="date" name="inspection_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Inspected By</label>
                                <input type="text" name="inspected_by_name" class="form-control" placeholder="Inspector name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Overall Condition <span class="text-danger">*</span></label>
                                <select name="overall_condition" class="form-select" required>
                                    <option value="">Select…</option>
                                    <option>Excellent</option><option>Good</option>
                                    <option>Fair</option><option>Poor</option><option>Very Poor</option>
                                </select>
                            </div>

                            @foreach([
                                ['walls_condition',     'Walls'],
                                ['floors_condition',    'Floors'],
                                ['ceiling_condition',   'Ceiling'],
                                ['doors_condition',     'Doors & Locks'],
                                ['windows_condition',   'Windows'],
                                ['plumbing_condition',  'Plumbing'],
                                ['electrical_condition','Electrical / Fittings'],
                            ] as [$field, $label])
                            <div class="col-md-3">
                                <label class="form-label">{{ $label }}</label>
                                <select name="{{ $field }}" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    <option>Excellent</option><option>Good</option>
                                    <option>Fair</option><option>Poor</option><option>Very Poor</option>
                                </select>
                            </div>
                            @endforeach

                            <div class="col-md-4">
                                <label class="form-label">Keys Returned <span class="text-danger">*</span></label>
                                <select name="keys_returned" class="form-select" required>
                                    <option value="">Select…</option>
                                    <option>Yes</option><option>No</option><option>Partial</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Keys Notes</label>
                                <input type="text" name="keys_notes" class="form-control" placeholder="e.g. 2 of 3 keys returned">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Damage Description</label>
                                <textarea name="damage_description" class="form-control" rows="2" placeholder="Describe any visible damage…"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estimated Damage Cost (KES)</label>
                                <input type="number" name="damage_amount" class="form-control" min="0" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cleaning Required <span class="text-danger">*</span></label>
                                <select name="cleaning_required" class="form-select" required>
                                    <option value="">Select…</option>
                                    <option>Yes</option><option>No</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cleaning Cost (KES)</label>
                                <input type="number" name="cleaning_cost" class="form-control" min="0" step="0.01">
                            </div>

                            <div class="col-12">
                                <label class="form-label">General Notes</label>
                                <textarea name="general_notes" class="form-control" rows="3" placeholder="Any other observations…"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Inspection Photos</label>
                                <input type="file" name="inspection_photos[]" class="form-control" multiple accept="image/*">
                                <small class="text-muted">Upload photos of each room / damaged area.</small>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined align-middle" style="font-size:16px;">save</span> Save Inspection
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ────────────── TAB: COMMUNICATIONS ────────────── --}}
                <div id="tab-communications" class="workspace-tab d-none">
                    <div class="row g-3">
                        {{-- Compose panel --}}
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Compose Communication</h6>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="loadTemplates()">
                                            <span class="material-symbols-outlined" style="font-size:14px;">auto_awesome</span> Templates
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    {{-- Templates dropdown --}}
                                    <div id="templatesPanel" class="d-none mb-3">
                                        <label class="form-label">Select a Template</label>
                                        <select class="form-select form-select-sm" id="templateSelect" onchange="applyTemplate()">
                                            <option value="">— Choose template —</option>
                                        </select>
                                    </div>

                                    <form id="commForm">
                                        <input type="hidden" name="exit_id" id="commExitId">
                                        <input type="hidden" name="tenant_id" id="commTenantId">
                                        <input type="hidden" name="template_used" id="commTemplateUsed">

                                        <div class="mb-2">
                                            <label class="form-label">Send Via <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="comm_type" value="Email" id="commEmail" checked onchange="toggleCommFields()">
                                                    <label class="form-check-label" for="commEmail">Email</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="comm_type" value="SMS" id="commSMS" onchange="toggleCommFields()">
                                                    <label class="form-check-label" for="commSMS">SMS</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="comm_type" value="Both" id="commBoth" onchange="toggleCommFields()">
                                                    <label class="form-check-label" for="commBoth">Both</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="emailFields">
                                            <div class="mb-2">
                                                <label class="form-label">To (Email)</label>
                                                <input type="email" name="email_address" id="commEmailAddr" class="form-control form-control-sm" placeholder="Pre-filled from tenant profile">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Subject</label>
                                                <input type="text" name="subject" id="commSubject" class="form-control form-control-sm">
                                            </div>
                                        </div>

                                        <div id="smsFields" class="d-none">
                                            <div class="mb-2">
                                                <label class="form-label">To (Mobile)</label>
                                                <input type="text" name="mobile_number" id="commMobile" class="form-control form-control-sm">
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label">Message <span class="text-danger">*</span></label>
                                            <textarea name="message_body" id="commBody" class="form-control" rows="14" style="min-height:280px;resize:vertical;" placeholder="Type your message here…"></textarea>
                                            <small class="text-muted float-end"><span id="commCharCount">0</span> chars</small>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100">
                                            <span class="material-symbols-outlined align-middle" style="font-size:16px;">send</span> Send
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Communication history --}}
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Communication History</h6>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="loadCommunications()">
                                        <span class="material-symbols-outlined" style="font-size:14px;">refresh</span>
                                    </button>
                                </div>
                                <div class="card-body p-2" style="overflow-y:auto;max-height:520px;">
                                    <div id="commHistory">
                                        <div class="text-center text-muted py-4">No communications logged yet.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ────────────── TAB: FINALISE EXIT ────────────── --}}
                <div id="tab-finalise" class="workspace-tab d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-header bg-danger text-white py-2">
                                    <h6 class="mb-0">
                                        <span class="material-symbols-outlined align-middle" style="font-size:17px;">warning</span>
                                        Finalise & Close Tenancy
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning mb-3" style="font-size:0.85rem;">
                                        <strong>This action is irreversible.</strong> Finalising will:
                                        <ul class="mb-0 mt-1">
                                            <li>Mark the tenant's active lease as <strong>Ended</strong></li>
                                            <li>Set the unit status to <strong>Vacant</strong></li>
                                            <li>Unlink the tenant from the property & unit</li>
                                        </ul>
                                    </div>

                                    <form id="finaliseForm">
                                        <input type="hidden" name="exit_id" id="finalExitId">
                                        <input type="hidden" name="tenant_id" id="finalTenantId">

                                        <div class="mb-3">
                                            <label class="form-label">Actual Exit Date <span class="text-danger">*</span></label>
                                            <input type="date" name="actual_exit_date" class="form-control" required value="{{ date('Y-m-d') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Deposit Refund Amount (KES)</label>
                                            <input type="number" name="deposit_refund_amount" class="form-control" min="0" step="0.01" placeholder="0.00">
                                            <small class="text-muted">Leave blank if no refund applicable.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Refund Notes</label>
                                            <textarea name="deposit_refund_notes" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Final Notes</label>
                                            <textarea name="final_notes" class="form-control" rows="3" placeholder="Any final remarks for the record…"></textarea>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="finalConfirmCheck" required>
                                            <label class="form-check-label" for="finalConfirmCheck">
                                                I confirm all exit steps have been completed and this tenancy is ready to be closed.
                                            </label>
                                        </div>

                                        <button type="submit" class="btn btn-danger w-100">
                                            <span class="material-symbols-outlined align-middle" style="font-size:16px;">lock</span>
                                            Finalise Exit
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Closing summary --}}
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0">Exit Checklist Summary</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush" id="checklistSummary">
                                        <li class="list-group-item d-flex justify-content-between align-items-center" id="check-exitDetails">
                                            Exit details saved
                                            <span class="badge bg-secondary rounded-pill">Pending</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center" id="check-inspection">
                                            Unit inspection completed
                                            <span class="badge bg-secondary rounded-pill">Pending</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center" id="check-communications">
                                            Tenant notified
                                            <span class="badge bg-secondary rounded-pill">Pending</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center" id="check-statement">
                                            Statement reviewed
                                            <span class="badge bg-secondary rounded-pill">Pending</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- card-body --}}
            </div>{{-- card --}}
        </div>{{-- #exitWorkspace --}}

    </div>{{-- container-fluid --}}
</div>{{-- page-body --}}


{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{--  STYLES                                                                   --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<style>
.autocomplete-dropdown {
    position: absolute;
    left: 0; right: 0; top: 100%;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0 0 6px 6px;
    z-index: 9999;
    max-height: 280px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}
.autocomplete-item {
    padding: 8px 14px;
    cursor: pointer;
    font-size: 0.85rem;
    border-bottom: 1px solid #f0f0f0;
}
.autocomplete-item:hover { background: #f8f9fa; }
.autocomplete-item .ref { color: #888; font-size: 0.75rem; }

#workspaceTabs .nav-link {
    font-size: 0.82rem;
    padding: 8px 12px;
    border-radius: 0;
    color: #555;
}
#workspaceTabs .nav-link.active {
    background: #fff;
    border-bottom-color: #fff;
    color: #1a1a2e;
    font-weight: 600;
}

.comm-card {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 8px;
    font-size: 0.82rem;
}
.comm-card .comm-meta { color: #888; font-size: 0.72rem; margin-top: 4px; }
.comm-card.email-card { border-left: 3px solid #3498db; }
.comm-card.sms-card { border-left: 3px solid #2ecc71; }
.comm-card.both-card { border-left: 3px solid #9b59b6; }

.statement-dr { color: #e74c3c; }
.statement-cr { color: #27ae60; }
.statement-bal-pos { color: #27ae60; }
.statement-bal-neg { color: #e74c3c; }

.condition-Excellent { color: #27ae60; font-weight: 600; }
.condition-Good      { color: #2ecc71; }
.condition-Fair      { color: #f39c12; }
.condition-Poor      { color: #e67e22; font-weight: 600; }
.condition-VeryPoor  { color: #e74c3c; font-weight: 700; }

.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20; }
</style>


{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{--  JAVASCRIPT                                                                --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
'use strict';

/* ── routes ─────────────────────────────────────────────────────────────── */
const ROUTES = {
    searchTenants:    "{{ route('tenant.exit.search.tenants') }}",
    searchProperties: "{{ route('tenant.exit.search.properties') }}",
    getOccupiedUnits: "{{ url('tenant-exit/occupied-units') }}",
    getTenantByUnit:  "{{ url('tenant-exit/tenant-by-unit') }}",
    getExitProfile:   "{{ route('tenant.exit.profile') }}",
    initiateExit:     "{{ route('tenant.exit.initiate') }}",
    saveInspection:   "{{ route('tenant.exit.inspection.save') }}",
    sendComm:         "{{ route('tenant.exit.communication.send') }}",
    getComms:         "{{ url('tenant-exit/communications') }}",
    getTemplates:     "{{ route('tenant.exit.templates') }}",
    finaliseExit:     "{{ route('tenant.exit.finalise') }}",
};

/* ── state ──────────────────────────────────────────────────────────────── */
let currentProfile = null;

/* ── search tab switching ───────────────────────────────────────────────── */
function switchSearchTab(tab) {
    document.getElementById('tab-tenant').classList.toggle('active',   tab === 'tenant');
    document.getElementById('tab-property').classList.toggle('active', tab === 'property');
    document.getElementById('tenantSearchPane').classList.toggle('d-none',   tab !== 'tenant');
    document.getElementById('propertySearchPane').classList.toggle('d-none', tab !== 'property');
}

/* ── workspace tab switching ────────────────────────────────────────────── */
function switchWorkspaceTab(tab) {
    document.querySelectorAll('.workspace-tab').forEach(el => el.classList.add('d-none'));
    document.getElementById('tab-' + tab).classList.remove('d-none');
    document.querySelectorAll('#workspaceTabs .nav-link').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('#workspaceTabs .nav-link').forEach(btn => {
        if (btn.getAttribute('onclick')?.includes(tab)) btn.classList.add('active');
    });
    // Lazy-load comms when tab activated
    if (tab === 'communications') {
        loadCommunications();
        if (currentProfile) {
            const t = currentProfile.tenant;
            if (!$('#commEmailAddr').val()) $('#commEmailAddr').val(t.email_address || '');
            if (!$('#commMobile').val())    $('#commMobile').val(t.mobile_number || '');
        }
    }
    if (tab === 'statement') markChecklist('statement');
}

/* ═══════════════════════════════════════════════════════════════════════
   TENANT SEARCH (by name/ref/phone/email)
═══════════════════════════════════════════════════════════════════════ */
let tenantSearchTimer;
$('#tenantSearchInput').on('input', function () {
    clearTimeout(tenantSearchTimer);
    const q = $(this).val().trim();
    if (q.length < 2) { $('#tenantSearchResults').empty(); return; }
    tenantSearchTimer = setTimeout(() => {
        $.get(ROUTES.searchTenants, { search: q }, data => {
            if (!data.success) return;
            renderTenantDropdown(data.tenants);
        });
    }, 300);
});

function renderTenantDropdown(tenants) {
    const $el = $('#tenantSearchResults');
    if (!tenants.length) { $el.html('<div class="autocomplete-dropdown"><div class="autocomplete-item text-muted">No tenants found</div></div>'); return; }
    let html = '<div class="autocomplete-dropdown">';
    tenants.forEach(t => {
        html += `<div class="autocomplete-item" onclick="selectTenant(${JSON.stringify(t).replace(/"/g,"&quot;")})">
            <strong>${t.names}</strong> <span class="ref">${t.reference_number}</span><br>
            <span class="ref">${t.mobile_number} &bull; ${t.property_name} / ${t.unit_name}</span>
        </div>`;
    });
    html += '</div>';
    $el.html(html);
}

function clearTenantSearch() {
    $('#tenantSearchInput').val('');
    $('#tenantSearchResults').empty();
}

/* ═══════════════════════════════════════════════════════════════════════
   PROPERTY → UNIT SEARCH
═══════════════════════════════════════════════════════════════════════ */
let propSearchTimer;
$('#propertySearchInput').on('input', function () {
    clearTimeout(propSearchTimer);
    const q = $(this).val().trim();
    if (q.length < 2) { $('#propertySearchResults').empty(); return; }
    propSearchTimer = setTimeout(() => {
        $.get(ROUTES.searchProperties, { search: q }, data => {
            if (!data.success) return;
            let html = '<div class="autocomplete-dropdown">';
            data.properties.forEach(p => {
                html += `<div class="autocomplete-item" onclick="selectProperty(${p.id}, '${p.property_name.replace(/'/g,"\\'")}')">
                    <strong>${p.property_name}</strong> <span class="ref">${p.reference_number}</span>
                </div>`;
            });
            html += '</div>';
            $('#propertySearchResults').html(html);
        });
    }, 300);
});

function selectProperty(propertyId, propertyName) {
    $('#propertySearchInput').val(propertyName);
    $('#propertySearchResults').empty();
    const $unit = $('#unitSelect');
    $unit.prop('disabled', true).html('<option>Loading units…</option>');
    $.get(ROUTES.getOccupiedUnits + '/' + propertyId, data => {
        if (!data.success || !data.units.length) {
            $unit.html('<option>No occupied units found</option>');
            return;
        }
        let opts = '<option value="">— Select unit —</option>';
        data.units.forEach(u => {
            opts += `<option value="${u.id}" data-property="${propertyId}">${u.unit_name} (${u.unit_code})</option>`;
        });
        $unit.html(opts).prop('disabled', false);
    });
}

$('#unitSelect').on('change', function () {
    const unitId = $(this).val();
    const propertyId = $(this).find(':selected').data('property');
    if (!unitId) return;
    $.get(ROUTES.getTenantByUnit + '/' + unitId, data => {
        if (data.success && data.tenant) {
            loadTenantProfile(data.tenant.encrypted_id);
        } else {
            toastr.warning('No active tenant found on this unit.');
        }
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   SELECT TENANT & LOAD PROFILE
═══════════════════════════════════════════════════════════════════════ */
function selectTenant(tenant) {
    $('#tenantSearchResults').empty();
    $('#tenantSearchInput').val(tenant.names);
    loadTenantProfile(tenant.encrypted_id);
}

function loadTenantProfile(encId) {
    $('#exitWorkspace').addClass('d-none');

    $.get(ROUTES.getExitProfile, { tenant_id: encId }, data => {
        if (!data.success) { toastr.error(data.message || 'Failed to load profile'); return; }

        currentProfile = data;
        const t = data.tenant;

        /* banner */
        $('#bannerName').text(t.names);
        $('#bannerRef').text(t.reference_number);
        $('#bannerMobile').text(t.mobile_number);
        $('#bannerEmail').text(t.email_address);
        $('#bannerProperty').text(t.property_name);
        $('#bannerUnit').text(t.unit_name);

        const bal = parseFloat(data.closing_balance);
        $('#bannerBalance').text((bal >= 0 ? 'CR ' : 'DR ') + Math.abs(bal).toLocaleString('en-KE', {minimumFractionDigits:2}))
                          .css('color', bal >= 0 ? '#27ae60' : '#e74c3c');

        /* hidden state */
        $('#stateTenantId').val(t.id);
        $('#stateTenantEncId').val(encId);
        $('#statePropertyId').val(/* from tenant */ data.tenant.property_id ?? '');
        $('#stateUnitId').val(data.tenant.unit_id ?? '');
        if (data.exit_record) $('#stateExitId').val(data.exit_record.id);

        /* wire forms */
        $('#exitTenantId').val(t.id);
        $('#exitPropertyId').val($('#statePropertyId').val());
        $('#exitUnitId').val($('#stateUnitId').val());
        $('#inspTenantId').val(t.id);
        $('#inspUnitId').val($('#stateUnitId').val());
        if (data.exit_record) {
            $('#inspExitId').val(data.exit_record.id);
            $('#commExitId').val(data.exit_record.id);
            $('#finalExitId').val(data.exit_record.id);
        }
        $('#commTenantId').val(t.id);
        $('#finalTenantId').val(t.id);

        /* pre-fill communications fields */
        $('#commEmailAddr').val(t.email_address);
        $('#commMobile').val(t.mobile_number);

        /* pre-fill exit details if record exists */
        if (data.exit_record) {
            const er = data.exit_record;
            $('[name=vacate_notice_date]').val(er.vacate_notice_date ?? '');
            $('[name=intended_exit_date]').val(er.intended_exit_date ?? '');
            $('[name=actual_exit_date]').val(er.actual_exit_date ?? '');
            $('[name=exit_reason]').val(er.exit_reason ?? '');
            $('[name=exit_notes]').val(er.exit_notes ?? '');

            const statusLabel = er.current_status_id === 2 ? 'Completed' : 'In Progress';
            $('#exitStatusText').text('Exit: ' + statusLabel);
            $('#exitStatusBadge').removeClass('d-none');
            updateChecklist('exitDetails');
        }

        /* render lease history */
        renderLeaseHistory(data.lease_history);

        /* render statement */
        renderStatement(data.statement, data.closing_balance);

        /* render deposits */
        renderDeposits(data.deposits, data.total_deposit_refunded);

        /* render stay history */
        renderStayHistory(data.stay_history);

        /* show workspace */
        $('#exitWorkspace').removeClass('d-none');
        $('html, body').animate({ scrollTop: $('#exitWorkspace').offset().top - 20 }, 300);
    }).fail(() => toastr.error('Failed to load tenant profile.'));
}

/* ─── Lease History ──────────────────────────────────────────────────────── */
function renderLeaseHistory(leases) {
    let html = '';

    // Normalize: always work with an array
    const leaseArray = Array.isArray(leases) ? leases : (leases ? [leases] : []);

    leaseArray.forEach(l => {
        const badge = l.is_active
            ? '<span class="badge bg-success">Active</span>'
            : `<span class="badge bg-secondary">${l.lease_status}</span>`;
        html += `<tr>
            <td>${l.ref_number}</td>
            <td>${l.property}</td>
            <td><strong>${l.unit}</strong></td>
            <td>${l.date_start}</td>
            <td>${l.date_end}</td>
            <td>${badge}</td>
        </tr>`;
    });

    $('#leaseHistoryBody').html(
        html || '<tr><td colspan="6" class="text-center text-muted">No leases found.</td></tr>'
    );
}


/* ─── Statement ─────────────────────────────────────────────────────────── */
function renderStatement(entries, closingBalance) {
    let html = '';
    (entries || []).forEach(e => {
        const isNeg = parseFloat(e.balance) < 0;
        html += `<tr>
            <td>${e.date_formatted}</td>
            <td>${e.reference}</td>
            <td>${e.narration}</td>
            <td class="text-end statement-dr">${e.debit_formatted}</td>
            <td class="text-end statement-cr">${e.credit_formatted}</td>
            <td class="text-end fw-bold ${isNeg ? 'statement-bal-neg' : 'statement-bal-pos'}">${e.balance_formatted}</td>
        </tr>`;
    });
    $('#statementBody').html(html || '<tr><td colspan="6" class="text-center text-muted">No transactions found.</td></tr>');

    const bal = parseFloat(closingBalance);
    const balFmt = (bal >= 0 ? 'CR ' : 'DR ') + Math.abs(bal).toLocaleString('en-KE', {minimumFractionDigits:2});
    $('#statementBalanceBadge')
        .text(balFmt)
        .css('background', bal >= 0 ? '#27ae60' : '#e74c3c');
}

/* ─── Deposits ──────────────────────────────────────────────────────────── */
function renderDeposits(deposits, totalRefunded) {
    if (!deposits || !deposits.length) {
        $('#depositsTable').html('<div class="text-muted" style="font-size:0.85rem;">No deposit records found.</div>');
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Reference</th><th>Property</th><th>Unit</th><th class="text-end">Amount (KES)</th><th>Received</th></tr></thead><tbody>';
    deposits.forEach(d => {
        html += `<tr><td>${d.reference}</td><td>${d.property_name}</td><td>${d.unit_name}</td><td class="text-end">${d.amount}</td><td>${d.date_received}</td></tr>`;
    });
    html += '</tbody></table></div>';
    if (totalRefunded && parseFloat(totalRefunded.replace(/,/g,'')) > 0) {
        html += `<div class="mt-2 text-end" style="font-size:0.82rem;">
            Total refunded via exit: <strong class="text-success">KES ${totalRefunded}</strong>
        </div>`;
    }
    $('#depositsTable').html(html);
}

/* ─── Stay History ──────────────────────────────────────────────────────── */
function renderStayHistory(history) {
    if (!history || !history.length) {
        $('#stayHistoryBody').html('<tr><td colspan="7" class="text-center text-muted py-3">No completed stays on record.</td></tr>');
        return;
    }
    let html = '';
    history.forEach(h => {
        html += `<tr>
            <td>${h.property_name}</td>
            <td><strong>${h.unit_name}</strong></td>
            <td>${h.landlord_name}</td>
            <td>${h.lease_start_date}</td>
            <td>${h.exit_date}</td>
            <td><span class="badge bg-light text-dark border">${h.duration}</span></td>
            <td><span class="badge bg-secondary">${h.exit_reason}</span></td>
        </tr>`;
    });
    $('#stayHistoryBody').html(html);
}

/* ═══════════════════════════════════════════════════════════════════════
   FORM SUBMISSIONS
═══════════════════════════════════════════════════════════════════════ */

/* Exit Details */
$('#exitDetailsForm').on('submit', function (e) {
    e.preventDefault();
    $('#exitDetailsSaving').removeClass('d-none').addClass('d-flex');
    const data = Object.fromEntries(new FormData(this));
    data._token = '{{ csrf_token() }}';
    $.post(ROUTES.initiateExit, data, res => {
        if (res.success) {
            toastr.success(res.message);
            if (res.exit_id) {
                $('#stateExitId').val(res.exit_id);
                $('#inspExitId').val(res.exit_id);
                $('#commExitId').val(res.exit_id);
                $('#finalExitId').val(res.exit_id);
            }
            updateChecklist('exitDetails');
        } else {
            toastr.error(res.message || 'Failed to save exit details');
        }
    }).always(() => $('#exitDetailsSaving').addClass('d-none').removeClass('d-flex'));
});

/* Inspection */
$('#inspectionForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', '{{ csrf_token() }}');
    $.ajax({
        url: ROUTES.saveInspection, type: 'POST', data: fd,
        processData: false, contentType: false,
        success: res => {
            if (res.success) { toastr.success(res.message); updateChecklist('inspection'); }
            else toastr.error(res.message);
        },
        error: () => toastr.error('Failed to save inspection.')
    });
});

/* Communication */
$('#commForm').on('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    data._token = '{{ csrf_token() }}';
    $.post(ROUTES.sendComm, data, res => {
        if (res.success) {
            toastr.success(res.message);
            $('#commBody').val(''); $('#commSubject').val('');
            loadCommunications();
            updateChecklist('communications');
        } else toastr.error(res.message);
    }).fail(() => toastr.error('Failed to send communication.'));
});

/* Finalise */
$('#finaliseForm').on('submit', function (e) {
    e.preventDefault();
    if (!$('#finalConfirmCheck').is(':checked')) { toastr.warning('Please confirm the checklist before finalising.'); return; }
    if (!confirm('Are you sure? This will permanently close this tenancy and cannot be undone.')) return;
    const data = Object.fromEntries(new FormData(this));
    data._token = '{{ csrf_token() }}';
    $.post(ROUTES.finaliseExit, data, res => {
        if (res.success) {
            toastr.success(res.message);
            $('#exitStatusText').text('Exit: Completed');
            $('#exitStatusBadge').removeClass('d-none');
            $('#exitWorkspace').addClass('d-none');
            $('#tenantSearchInput').val('');
            currentProfile = null;
        } else toastr.error(res.message);
    }).fail(() => toastr.error('Failed to finalise exit.'));
});

/* ═══════════════════════════════════════════════════════════════════════
   COMMUNICATIONS
═══════════════════════════════════════════════════════════════════════ */
function toggleCommFields() {
    const type = $('input[name=comm_type]:checked').val();
    $('#emailFields').toggleClass('d-none', type === 'SMS');
    $('#smsFields').toggleClass('d-none',   type === 'Email');
}

$('#commBody').on('input', function () {
    $('#commCharCount').text($(this).val().length);
});

function loadCommunications() {
    const exitId = $('#stateExitId').val();
    if (!exitId) return;
    $.get(ROUTES.getComms + '/' + exitId, data => {
        if (!data.success) return;
        if (!data.communications.length) {
            $('#commHistory').html('<div class="text-center text-muted py-4">No communications logged yet.</div>');
            return;
        }
        let html = '';
        data.communications.forEach(c => {
            const cls = c.comm_type === 'Email' ? 'email-card' : c.comm_type === 'SMS' ? 'sms-card' : 'both-card';
            html += `<div class="comm-card ${cls}">
                <div class="d-flex justify-content-between">
                    <strong>${c.subject || c.comm_type + ' Message'}</strong>
                    <span class="badge bg-light text-dark border">${c.comm_type}</span>
                </div>
                <div style="font-size:0.8rem;color:#333;margin-top:4px;">${c.message_body.substring(0,140)}${c.message_body.length > 140 ? '…' : ''}</div>
                <div class="comm-meta">Sent by ${c.sent_by_name ?? '-'} on ${new Date(c.sent_at).toLocaleString()}</div>
            </div>`;
        });
        $('#commHistory').html(html);
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   TEMPLATES
═══════════════════════════════════════════════════════════════════════ */
function loadTemplates() {
    const t = currentProfile?.tenant;
    if (!t) return;
    const params = {
        tenant_name:   t.names,
        property_name: t.property_name,
        exit_date:     $('[name=intended_exit_date]').val() || '-',
    };
    $.get(ROUTES.getTemplates, params, data => {
        if (!data.success) return;
        $('#templateSelect').html('<option value="">— Choose template —</option>');
        data.templates.forEach(tpl => {
            $('#templateSelect').append(`<option value="${tpl.key}" data-subject="${tpl.subject}" data-body="${encodeURIComponent(tpl.body)}">${tpl.label}</option>`);
        });
        $('#templatesPanel').removeClass('d-none');
    });
}

function applyTemplate() {
    const sel = $('#templateSelect').find(':selected');
    if (!sel.val()) return;
    $('#commSubject').val(sel.data('subject'));
    $('#commBody').val(decodeURIComponent(sel.data('body')));
    $('#commCharCount').text($('#commBody').val().length);
    $('#commTemplateUsed').val(sel.val());
}

/* ═══════════════════════════════════════════════════════════════════════
   CHECKLIST
═══════════════════════════════════════════════════════════════════════ */
function updateChecklist(step) {
    const map = {
        exitDetails:    '#check-exitDetails',
        inspection:     '#check-inspection',
        communications: '#check-communications',
        statement:      '#check-statement',
    };
    if (map[step]) {
        $(map[step]).find('.badge')
            .removeClass('bg-secondary').addClass('bg-success')
            .text('Done');
    }
}
function markChecklist(tab) {
    if (tab === 'statement') updateChecklist('statement');
}

/* ═══════════════════════════════════════════════════════════════════════
   CLOSE DROPDOWN ON OUTSIDE CLICK
═══════════════════════════════════════════════════════════════════════ */
$(document).on('click', function (e) {
    if (!$(e.target).closest('#tenantSearchInput, #tenantSearchResults').length)
        $('#tenantSearchResults').empty();
    if (!$(e.target).closest('#propertySearchInput, #propertySearchResults').length)
        $('#propertySearchResults').empty();
});

/* ─── Pre-load tenant if passed via URL (from controller) ──────────────── */
@if($tenant)
$(function () { loadTenantProfile('{{ Crypt::encrypt($tenant->id) }}'); });
@endif
</script>
@endsection
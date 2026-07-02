@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{--  PAGE HEADER                                                         --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">exit_to_app</span>
            Tenant Exit Processing
        </h4>
        <small class="text-muted">Manage tenant vacates and deposit settlements</small>
    </div>
    <a href="{{ route('tenant.exit.exited') }}" class="btn btn-sm btn-outline-secondary">
        <span class="material-symbols-outlined align-middle" style="font-size:14px;">list</span> View Exited Tenants
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
{{--  MAIN WORKSPACE (hidden until tenant loaded)                         --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div id="exitWorkspace" class="d-none">

    {{-- Hidden state --}}
    <input type="hidden" id="stateTenantId">
    <input type="hidden" id="stateTenantEncId">
    <input type="hidden" id="statePropertyId">
    <input type="hidden" id="stateUnitId">
    <input type="hidden" id="stateExitId">
    <input type="hidden" id="stateVacateType" value="With Notice">
    <input type="hidden" id="stateP1Done" value="0">

    {{-- ─── TENANT BANNER ────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-3" style="border-left:4px solid #e74c3c !important;">
        <div class="card-body py-2">
            <div class="row align-items-center g-2">
                <div class="col-md-5">
                    <h5 class="mb-0" id="bannerName">-</h5>
                    <small class="text-muted">
                        <span id="bannerRef">-</span> &bull;
                        <span id="bannerMobile">-</span> &bull;
                        <span id="bannerEmail">-</span>
                    </small>
                </div>
                <div class="col-md-3 text-md-center">
                    <div class="fw-bold" id="bannerProperty">-</div>
                    <small class="text-muted">Unit: <strong id="bannerUnit">-</strong></small>
                </div>
                <div class="col-md-2 text-md-center">
                    <div class="text-muted" style="font-size:0.75rem;">Closing Balance</div>
                    <div class="fw-bold fs-6" id="bannerBalance" style="color:#e74c3c;">-</div>
                </div>
                <div class="col-md-2 text-md-end">
                    <div id="exitStatusBadges">
                        <div style="font-size:0.72rem;" class="text-muted mb-1">Exit Status</div>
                        <span id="p1BannerBadge" class="badge me-1" style="background:#f59e0b;font-size:0.68rem;">Process 1: Pending</span>
                        <span id="p2BannerBadge" class="badge" style="background:#6c757d;font-size:0.68rem;">Process 2: Pending</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{--  PROCESS 1 — VACATE PROCESS                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-3" id="process1Card">
        <div class="card-header py-2 d-flex align-items-center justify-content-between"
             style="background:#1a1a2e;color:#fff;border-radius:8px 8px 0 0;">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;">how_to_reg</span>
                <span class="fw-semibold">Process 1 — Vacate Process</span>
            </div>
            <span id="p1StatusBadge" class="badge" style="background:#f59e0b;font-size:0.7rem;">In Progress</span>
        </div>
        <div class="card-body p-0" id="process1Body">

            {{-- Description — always visible above tabs --}}
            <div class="px-3 pt-3 pb-0">
                <div class="alert alert-info mb-0 py-2 px-3" style="font-size:0.82rem;border-left:4px solid #0ea5e9;background:#f0f9ff;border-radius:6px;">
                    <div class="fw-semibold mb-1" style="color:#0369a1;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;">info</span>
                        What this process covers
                    </div>
                    <p class="mb-1 text-muted">
                        Process 1 handles the <strong>physical exit</strong> of the tenant from the unit. Record <strong>why</strong> the tenant is leaving, the <strong>intended and actual exit dates</strong>, and all pre-exit activity (communications, field officer visits, and date changes if the tenant postpones). Once the unit is physically empty, click <strong>"Mark Unit Vacant"</strong> to end the tenancy and unlock Process 2.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-1" style="font-size:0.78rem;">
                        <span class="badge rounded-pill" style="background:#e0f2fe;color:#0369a1;">Step 1 — Exit Details: capture vacate type &amp; dates</span>
                        <span class="badge rounded-pill" style="background:#e0f2fe;color:#0369a1;">Step 2 — Pre-Exit Tracking: comms, visits, date changes (With Notice only)</span>
                        <span class="badge rounded-pill" style="background:#fee2e2;color:#b91c1c;">Step 3 — Complete Vacate: mark the unit empty to unlock settlement</span>
                    </div>
                </div>
            </div>

            {{-- Tab navigation --}}
            <div class="px-3 pt-2 border-bottom">
                <ul class="nav nav-tabs border-0" id="p1TabNav">
                    <li class="nav-item">
                        <button class="nav-link active d-flex align-items-center gap-1 py-2" id="p1TabBtn-details" onclick="switchP1Tab('details')">
                            <span class="badge rounded-pill me-1" style="background:#1a1a2e;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">1</span>
                            Exit Details
                            <span class="material-symbols-outlined d-none ms-1" id="p1Chk-details" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                    <li class="nav-item d-none" id="p1TabLi-tracking">
                        <button class="nav-link d-flex align-items-center gap-1 py-2" id="p1TabBtn-tracking" onclick="switchP1Tab('tracking')">
                            <span class="badge rounded-pill me-1" style="background:#6c757d;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">2</span>
                            Pre-Exit Tracking
                            <span class="material-symbols-outlined d-none ms-1" id="p1Chk-tracking" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link d-flex align-items-center gap-1 py-2" id="p1TabBtn-vacate" onclick="switchP1Tab('vacate')">
                            <span class="badge rounded-pill me-1" style="background:#6c757d;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">3</span>
                            Complete Vacate
                            <span class="material-symbols-outlined d-none ms-1" id="p1Chk-vacate" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                </ul>
            </div>

            {{-- Tab 1: Exit Details --}}
            <div id="p1Tab-details" class="p-3">
                <form id="exitDetailsForm">
                    @csrf
                    <input type="hidden" name="tenant_id" id="exitTenantId">
                    <input type="hidden" name="property_id" id="exitPropertyId">
                    <input type="hidden" name="unit_id" id="exitUnitId">

                    <div class="mb-3">
                        <label class="form-label form-label-sm mb-1 fw-semibold">Vacate Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 flex-wrap" style="font-size:0.82rem;">
                            <div class="text-muted">Select how the tenant is leaving — this affects which tracking steps are required.</div>
                        </div>
                        <div class="btn-group mt-2" role="group" id="vacateTypeGroup">
                            <input type="radio" class="btn-check" name="vacate_type" id="vtEviction"    value="Eviction"      autocomplete="off">
                            <label class="btn btn-sm btn-outline-danger"   for="vtEviction">
                                <span class="material-symbols-outlined align-middle" style="font-size:13px;">gavel</span> Eviction
                            </label>
                            <input type="radio" class="btn-check" name="vacate_type" id="vtNoNotice"   value="Without Notice" autocomplete="off">
                            <label class="btn btn-sm btn-outline-warning"  for="vtNoNotice">
                                <span class="material-symbols-outlined align-middle" style="font-size:13px;">bolt</span> Without Notice
                            </label>
                            <input type="radio" class="btn-check" name="vacate_type" id="vtWithNotice" value="With Notice"    autocomplete="off" checked>
                            <label class="btn btn-sm btn-outline-success"  for="vtWithNotice">
                                <span class="material-symbols-outlined align-middle" style="font-size:13px;">event_available</span> With Notice
                            </label>
                        </div>
                        <div id="vacateTypeHint" class="mt-1" style="font-size:0.78rem;color:#6c757d;">
                            <span id="vtHintText">Pre-Exit Tracking will be available — notice communications, field visits, and date changes will be tracked.</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3" id="noticeDateWrapper">
                            <label class="form-label form-label-sm mb-1">Vacate Notice Date <span class="text-danger">*</span></label>
                            <input type="date" name="vacate_notice_date" id="vacateNoticeDate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3" id="intendedDateWrapper">
                            <label class="form-label form-label-sm mb-1">Intended Exit Date <span class="text-danger">*</span></label>
                            <input type="date" name="intended_exit_date" id="intendedExitDate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1" id="actualExitDateLabel">Actual Exit Date</label>
                            <input type="date" name="actual_exit_date" id="actualExitDateTop" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Reason for Exit <span class="text-danger">*</span></label>
                            <select id="exitReasonSelect" class="form-select form-select-sm" onchange="onExitReasonChange(this.value)" required>
                                <option value="">Select…</option>
                                <option>End of Lease</option>
                                <option>Voluntary Vacate</option>
                                <option>Eviction</option>
                                <option>Transfer</option>
                                <option>Upgrade or Downgrade</option>
                                <option>Non-Payment</option>
                                <option value="Other">Other (specify below)</option>
                            </select>
                            <input type="hidden" name="exit_reason" id="exitReason">
                            <input type="text" id="exitReasonCustom" class="form-control form-control-sm mt-1 d-none"
                                   placeholder="Describe the reason…" oninput="$('#exitReason').val(this.value)">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1">Exit Notes</label>
                            <textarea name="exit_notes" id="exitNotes" class="form-control form-control-sm" rows="2" placeholder="Any additional context…"></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined align-middle" style="font-size:14px;">save</span> Save Exit Details
                        </button>
                        <div id="exitDetailsSaving" class="d-none align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary"></div> Saving…
                        </div>
                    </div>
                </form>
            </div>

            {{-- Tab 2: Pre-Exit Tracking (With Notice only) --}}
            <div id="p1Tab-tracking" class="p-3 d-none">
                <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.8rem;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#0369a1;">info</span>
                    Use this tab to track all activity during the notice period. Send reconfirmation communications to the tenant, log field officer visits to check the unit's status, and record any changes to the exit date.
                </div>
                <div class="row g-3">
                    {{-- Column 1: Reconfirmation Communications --}}
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-semibold" style="font-size:0.82rem;">
                                    <span class="material-symbols-outlined align-middle" style="font-size:14px;">mark_email_read</span>
                                    Reconfirmation Communications
                                </span>
                                <button class="btn btn-xs btn-outline-secondary" style="font-size:0.72rem;padding:2px 6px;" onclick="loadTemplates()">
                                    <span class="material-symbols-outlined" style="font-size:12px;">auto_awesome</span> Templates
                                </button>
                            </div>
                            <div class="card-body p-2">
                                <div id="trackingTemplatesPanel" class="d-none mb-2">
                                    <select class="form-select form-select-sm" id="trackingTemplateSelect" onchange="applyTrackingTemplate()">
                                        <option value="">— Choose template —</option>
                                    </select>
                                </div>
                                <form id="commForm">
                                    <input type="hidden" name="exit_id" id="commExitId">
                                    <input type="hidden" name="tenant_id" id="commTenantId">
                                    <input type="hidden" name="template_used" id="commTemplateUsed">
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm mb-1">Send Via <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <div class="form-check form-check-sm">
                                                <input class="form-check-input" type="radio" name="comm_type" value="Email" id="commEmail" checked onchange="toggleCommFields()">
                                                <label class="form-check-label" for="commEmail" style="font-size:0.8rem;">Email</label>
                                            </div>
                                            <div class="form-check form-check-sm">
                                                <input class="form-check-input" type="radio" name="comm_type" value="SMS" id="commSMS" onchange="toggleCommFields()">
                                                <label class="form-check-label" for="commSMS" style="font-size:0.8rem;">SMS</label>
                                            </div>
                                            <div class="form-check form-check-sm">
                                                <input class="form-check-input" type="radio" name="comm_type" value="Both" id="commBoth" onchange="toggleCommFields()">
                                                <label class="form-check-label" for="commBoth" style="font-size:0.8rem;">Both</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="emailFields">
                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">Email To</label>
                                            <input type="email" name="email_address" id="commEmailAddr" class="form-control form-control-sm">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">Subject</label>
                                            <input type="text" name="subject" id="commSubject" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <div id="smsFields" class="d-none">
                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">SMS To</label>
                                            <input type="text" name="mobile_number" id="commMobile" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm mb-1">Message <span class="text-danger">*</span></label>
                                        <textarea name="message_body" id="commBody" class="form-control form-control-sm" rows="8" placeholder="Type your message…"></textarea>
                                        <small class="text-muted float-end"><span id="commCharCount">0</span> chars</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-1">
                                        <span class="material-symbols-outlined align-middle" style="font-size:13px;">send</span> Send
                                    </button>
                                </form>
                                <div id="commHistory" class="mt-2" style="max-height:300px;overflow-y:auto;">
                                    <div class="text-center text-muted py-3" style="font-size:0.8rem;">No communications yet.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Column 2: Field Visit Log --}}
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2">
                                <span class="fw-semibold" style="font-size:0.82rem;">
                                    <span class="material-symbols-outlined align-middle" style="font-size:14px;">home_work</span>
                                    Field Visit Log
                                </span>
                                <div class="text-muted mt-1" style="font-size:0.72rem;">Log each physical check-in visit by a field officer to confirm the unit's status during the notice period.</div>
                            </div>
                            <div class="card-body p-2">
                                <form id="fieldVisitForm">
                                    <input type="hidden" name="exit_id" id="visitExitId">
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm mb-1">Visit Date <span class="text-danger">*</span></label>
                                        <input type="date" name="visit_date" id="visitDate" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm mb-1">Officer Name <span class="text-danger">*</span></label>
                                        <input type="text" name="officer_name" class="form-control form-control-sm" placeholder="Name of visiting officer" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm mb-1">Unit Status Found <span class="text-danger">*</span></label>
                                        <select name="unit_status_found" class="form-select form-select-sm" required>
                                            <option value="">Select…</option>
                                            <option>Occupied</option>
                                            <option>Partially Cleared</option>
                                            <option>Fully Cleared</option>
                                            <option>Empty</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm mb-1">Notes</label>
                                        <textarea name="visit_notes" class="form-control form-control-sm" rows="2" placeholder="Observations…"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <span class="material-symbols-outlined align-middle" style="font-size:13px;">save</span> Save Visit
                                    </button>
                                </form>
                                <div class="mt-2 table-responsive" style="max-height:260px;overflow-y:auto;">
                                    <table class="table table-sm table-bordered mb-0" style="font-size:0.75rem;" id="visitTable">
                                        <thead class="table-light sticky-top">
                                            <tr><th>Date</th><th>Officer</th><th>Status</th><th>Notes</th></tr>
                                        </thead>
                                        <tbody id="visitTableBody">
                                            <tr><td colspan="4" class="text-center text-muted py-2">No visits logged.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Column 3: Exit Date Changes --}}
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2">
                                <span class="fw-semibold" style="font-size:0.82rem;">
                                    <span class="material-symbols-outlined align-middle" style="font-size:14px;">event_repeat</span>
                                    Exit Date Changes
                                </span>
                                <div class="text-muted mt-1" style="font-size:0.72rem;">Track every time the tenant pushes their exit date — keeping a full history of previous and new dates with reasons.</div>
                            </div>
                            <div class="card-body p-2">
                                <div class="text-center mb-2 p-2 rounded" style="background:#f8f9fa;">
                                    <div class="text-muted" style="font-size:0.72rem;">Current Intended Exit Date</div>
                                    <div class="fw-bold" id="currentIntendedDateDisplay" style="font-size:1rem;color:#1a1a2e;">—</div>
                                </div>
                                <button class="btn btn-sm btn-outline-warning w-100 mb-2" onclick="toggleDateChangeForm()">
                                    <span class="material-symbols-outlined align-middle" style="font-size:13px;">edit_calendar</span> Log Date Change
                                </button>
                                <div id="dateChangeFormWrapper" class="d-none mb-2">
                                    <form id="dateChangeForm">
                                        <input type="hidden" name="exit_id" id="dateChangeExitId">
                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">New Date <span class="text-danger">*</span></label>
                                            <input type="date" name="new_date" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">Reason <span class="text-danger">*</span></label>
                                            <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Reason for the change…" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-warning btn-sm w-100" style="color:#000;">
                                            <span class="material-symbols-outlined align-middle" style="font-size:13px;">check</span> Confirm Change
                                        </button>
                                    </form>
                                </div>
                                <div class="table-responsive" style="max-height:260px;overflow-y:auto;">
                                    <table class="table table-sm table-bordered mb-0" style="font-size:0.72rem;">
                                        <thead class="table-light sticky-top">
                                            <tr><th>Previous</th><th>New</th><th>Reason</th><th>By</th><th>When</th></tr>
                                        </thead>
                                        <tbody id="dateChangeTableBody">
                                            <tr><td colspan="5" class="text-center text-muted py-2">No changes logged.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>{{-- /row --}}
            </div>{{-- /p1Tab-tracking --}}

            {{-- Tab 3: Complete Vacate --}}
            <div id="p1Tab-vacate" class="p-3 d-none">

                {{-- Gate: shown before exit details are saved --}}
                <div id="panelCompleteVacateGate" class="text-center py-5">
                    <span class="material-symbols-outlined d-block mb-2" style="font-size:36px;color:#cbd5e1;">lock</span>
                    <div class="fw-semibold mb-1" style="color:#64748b;">Save Exit Details First</div>
                    <div class="text-muted" style="font-size:0.82rem;">Go to the <strong>Exit Details</strong> tab, fill in the vacate type and dates, then click <strong>Save Exit Details</strong>. This tab will unlock once the exit record is created.</div>
                    <button class="btn btn-sm btn-outline-secondary mt-3" onclick="switchP1Tab('details')">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> Go to Exit Details
                    </button>
                </div>

                {{-- Active form (P1 not yet done) --}}
                <div id="panelCompleteVacate" class="d-none">
                    <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.82rem;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#0369a1;">info</span>
                        This is the final step of Process 1. Clicking the button below will:
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Mark the unit as <strong>Physically Vacant</strong> (occupancy updated immediately)</li>
                            <li>Set the lease status to <strong>Vacating</strong> — keeping the accounting link open for deposit settlement</li>
                            <li>Unlock <strong>Process 2 — Deposit Settlement</strong></li>
                        </ul>
                        <div class="mt-1 text-danger fw-semibold" style="font-size:0.78rem;">This action cannot be undone. Ensure exit details and any pre-exit tracking are complete before proceeding.</div>
                    </div>
                    <div class="alert alert-warning mb-3 py-2" style="font-size:0.84rem;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:15px;">warning</span>
                        <strong>Confirm the actual date the unit was physically vacated before clicking.</strong>
                        The lease will be fully terminated only after deposit settlement is finalised.
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Actual Exit Date <span class="text-danger">*</span></label>
                            <input type="date" id="actualExitDateFinal" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-auto">
                            <button type="button" id="completeVacateBtn" class="btn btn-danger">
                                <span class="material-symbols-outlined align-middle" style="font-size:16px;">check_circle</span>
                                Mark Unit Vacant &amp; Complete Process 1
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Done state --}}
                <div id="panelP1Done" class="d-none">
                    <div class="alert alert-success mb-0 py-3" style="font-size:0.9rem;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:18px;">task_alt</span>
                        <strong>Process 1 Completed</strong> — Unit was vacated on <strong id="p1VacatedDate">—</strong>.
                        <div class="mt-1 text-muted" style="font-size:0.82rem;">Lease status: <strong>Vacating</strong> (will be set to Terminated once deposit settlement is finalised). Scroll down to <strong>Process 2</strong> to continue.</div>
                    </div>
                </div>

            </div>{{-- /p1Tab-vacate --}}

        </div>{{-- /process1Body --}}
    </div>{{-- /process1Card --}}

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{--  PROCESS 2 — DEPOSIT SETTLEMENT                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-3" id="process2Card">
        <div class="card-header py-2 d-flex align-items-center justify-content-between"
             style="background:#1a1a2e;color:#fff;border-radius:8px 8px 0 0;">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;">payments</span>
                <span class="fw-semibold">Process 2 — Deposit Settlement</span>
            </div>
            <span id="p2StatusBadge" class="badge" style="background:#6c757d;font-size:0.7rem;">Pending</span>
        </div>

        {{-- Description — always visible, even when locked --}}
        <div class="px-3 pt-3 pb-0">
            <div class="alert alert-info mb-0 py-2 px-3" style="font-size:0.82rem;border-left:4px solid #8b5cf6;background:#faf5ff;border-radius:6px;">
                <div class="fw-semibold mb-1" style="color:#6d28d9;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;">info</span>
                    What this process covers
                </div>
                <p class="mb-1 text-muted">
                    Process 2 handles the <strong>financial settlement</strong> of the tenant's deposit after the unit has been vacated. Review the <strong>deposit held</strong>, check the tenant's <strong>account statement</strong> for arrears, capture <strong>unit inspection findings</strong> (damage and cleaning costs), then compute the final settlement balance.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-1" style="font-size:0.78rem;">
                    <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;">Step 1 — Deposit: see what is held &amp; optionally apply to final rent</span>
                    <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;">Step 2 — Statement: confirm closing balance / arrears</span>
                    <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;">Step 3 — Inspection: record damage &amp; cleaning costs</span>
                    <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;">Step 4 — Settlement: auto-computed breakdown</span>
                    <span class="badge rounded-pill" style="background:#fee2e2;color:#b91c1c;">Step 5 — Finalise: lock the settlement record</span>
                </div>
                <p class="mb-0 mt-1 text-muted">Outcome: <strong class="text-success">Refund to Tenant</strong>, <strong class="text-danger">Invoice to Tenant</strong>, or <strong class="text-secondary">Zero Balance</strong>.</p>
            </div>
        </div>

        {{-- Locked overlay (Process 1 not done) --}}
        <div id="p2Locked" class="text-center py-5">
            <span class="material-symbols-outlined" style="font-size:48px;color:#ccc;">lock</span>
            <div class="text-muted mt-2" style="font-size:0.85rem;">Complete Process 1 — Mark Unit Vacant first</div>
        </div>

        {{-- Process 2 body (shown once P1 done) --}}
        <div id="process2Body" style="display:none;" class="p-0">

            {{-- Tab navigation --}}
            <div class="px-3 pt-2 border-bottom">
                <ul class="nav nav-tabs border-0" id="p2TabNav">
                    <li class="nav-item">
                        <button class="nav-link active d-flex align-items-center gap-1 py-2" id="p2TabBtn-deposit" onclick="switchP2Tab('deposit')">
                            <span class="badge rounded-pill me-1" style="background:#1a1a2e;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">1</span>
                            Deposit on Record
                            <span class="material-symbols-outlined d-none ms-1" id="p2Chk-deposit" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link d-flex align-items-center gap-1 py-2" id="p2TabBtn-statement" onclick="switchP2Tab('statement')">
                            <span class="badge rounded-pill me-1" style="background:#6c757d;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">2</span>
                            Statement &amp; Arrears
                            <span class="material-symbols-outlined d-none ms-1" id="p2Chk-statement" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link d-flex align-items-center gap-1 py-2" id="p2TabBtn-inspection" onclick="switchP2Tab('inspection')">
                            <span class="badge rounded-pill me-1" style="background:#6c757d;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">3</span>
                            Unit Inspection
                            <span class="material-symbols-outlined d-none ms-1" id="p2Chk-inspection" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link d-flex align-items-center gap-1 py-2" id="p2TabBtn-settlement" onclick="switchP2Tab('settlement')">
                            <span class="badge rounded-pill me-1" style="background:#6c757d;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">4</span>
                            Settlement
                            <span class="material-symbols-outlined d-none ms-1" id="p2Chk-settlement" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link d-flex align-items-center gap-1 py-2" id="p2TabBtn-finalise" onclick="switchP2Tab('finalise')">
                            <span class="badge rounded-pill me-1" style="background:#6c757d;font-size:0.62rem;width:18px;height:18px;line-height:18px;padding:0;">5</span>
                            Finalise
                            <span class="material-symbols-outlined d-none ms-1" id="p2Chk-finalise" style="font-size:15px;color:#27ae60;">check_circle</span>
                        </button>
                    </li>
                </ul>
            </div>

            {{-- P2 Tab 1: Deposits on Record --}}
            <div id="p2Tab-deposit" class="p-3">
                <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.8rem;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#6d28d9;">info</span>
                    This shows all deposit amounts held on record for the tenant. If the tenant gave notice, you can optionally apply the deposit to cover the final month's rent (requires landlord approval).
                </div>
                <div id="p2DepositsTable">
                    <div class="text-muted text-center py-3" style="font-size:0.85rem;">Loading…</div>
                </div>
                {{-- Deposit-as-Rent (With Notice only) --}}
                <div id="depositAsRentSection" class="mt-3 d-none">
                    <div class="card border" style="border-left:3px solid #f59e0b !important;">
                        <div class="card-body p-2">
                            <div class="fw-semibold mb-1" style="font-size:0.85rem;">
                                <span class="material-symbols-outlined align-middle me-1" style="font-size:15px;color:#d97706;">savings</span>
                                Deposit-as-Rent Option
                            </div>
                            <div class="text-muted mb-2" style="font-size:0.78rem;">
                                With landlord approval, the deposit can be applied to cover the final month's rent. The amount applied reduces what's available for arrears/damage offset in the settlement.
                            </div>

                            {{-- Eligibility result banner --}}
                            <div id="darEligibilityBanner" class="mb-2" style="display:none;"></div>

                            {{-- Residual Hold Calculator --}}
                            <div id="darCalculator" class="mb-2" style="display:none;">
                                <table class="table table-sm table-borderless mb-0" style="font-size:0.79rem;">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted ps-0">Deposit on Record</td>
                                            <td class="text-end fw-semibold pe-0"><span id="darCalcDeposit">—</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted ps-0">Monthly Rent</td>
                                            <td class="text-end fw-semibold pe-0"><span id="darCalcRent">—</span></td>
                                        </tr>
                                        <tr class="border-top">
                                            <td class="ps-0 fw-semibold">Amount to Apply</td>
                                            <td class="text-end fw-semibold pe-0 text-success"><span id="darCalcApply">—</span></td>
                                        </tr>
                                        <tr id="darResidualRow">
                                            <td class="text-muted ps-0">Residual Hold (for repairs)</td>
                                            <td class="text-end pe-0 text-warning fw-semibold"><span id="darCalcResidual">—</span></td>
                                        </tr>
                                        <tr id="darShortfallRow" style="display:none;">
                                            <td class="ps-0 text-danger fw-semibold">Shortfall Invoice (rent − deposit)</td>
                                            <td class="text-end pe-0 text-danger fw-semibold"><span id="darCalcShortfall">—</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="darShortfallAlert" class="alert alert-warning py-1 px-2 mb-0 mt-1" style="font-size:0.76rem;display:none;">
                                    <span class="material-symbols-outlined align-middle me-1" style="font-size:13px;">warning</span>
                                    The deposit is less than rent. A shortfall invoice will be automatically generated on settlement finalisation.
                                </div>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="applyDepositAsRent" onchange="toggleDepositAsRentFields()">
                                <label class="form-check-label fw-semibold" for="applyDepositAsRent" style="font-size:0.85rem;">
                                    Apply deposit to final month's rent
                                </label>
                            </div>
                            <div id="depositAsRentFields" class="d-none">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="darApproved">
                                            <label class="form-check-label" for="darApproved" style="font-size:0.8rem;">
                                                Landlord has approved this in writing
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-sm btn-warning" style="color:#000;" onclick="saveDepositAsRent()">
                                            <span class="material-symbols-outlined align-middle" style="font-size:13px;">save</span> Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button class="btn btn-sm btn-primary" onclick="switchP2Tab('statement')">
                        Next: Statement &amp; Arrears <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_forward</span>
                    </button>
                </div>
            </div>

            {{-- P2 Tab 2: Statement & Arrears --}}
            <div id="p2Tab-statement" class="p-3 d-none">
                <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.8rem;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#6d28d9;">info</span>
                    Review the tenant's full account statement. The <strong>closing balance</strong> shown here determines the arrears figure used in the settlement computation — a <strong>DR balance means the tenant owes money</strong>, a CR balance means there is credit in their favour.
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem;color:#555;text-transform:uppercase;letter-spacing:.04em;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:15px;">receipt_long</span>
                        Full Account Statement
                    </h6>
                    <span class="badge fs-6 px-3" id="p2StatementBalanceBadge" style="background:#1a1a2e;">-</span>
                </div>
                <div class="table-responsive" style="max-height:380px;overflow-y:auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" style="font-size:0.8rem;">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Date</th><th>Reference</th><th>Narration</th>
                                <th class="text-end">Debit (KES)</th>
                                <th class="text-end">Credit (KES)</th>
                                <th class="text-end">Balance (KES)</th>
                            </tr>
                        </thead>
                        <tbody id="p2StatementBody">
                            <tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button class="btn btn-sm btn-outline-secondary" onclick="switchP2Tab('deposit')">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> Back
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="switchP2Tab('inspection')">
                        Next: Unit Inspection <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_forward</span>
                    </button>
                </div>
            </div>

            {{-- P2 Tab 3: Unit Inspection --}}
            <div id="p2Tab-inspection" class="p-3 d-none">
                <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.8rem;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#6d28d9;">info</span>
                    Record the physical condition of the unit after the tenant vacated. The <strong>damage cost</strong> and <strong>cleaning cost</strong> entered here flow directly into the settlement computation on the next tab.
                </div>
                {{-- Saved inspection view --}}
                <div id="savedInspectionView" class="d-none mb-3">
                    <div class="alert alert-success py-2 mb-2" style="font-size:0.83rem;">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">task_alt</span>
                        Inspection saved.
                        <button class="btn btn-link btn-sm p-0 ms-2" onclick="editInspection()">Edit</button>
                    </div>
                    <div id="savedInspectionSummary" class="row g-2" style="font-size:0.8rem;"></div>
                </div>
                <form id="inspectionForm" enctype="multipart/form-data">
                    <input type="hidden" name="exit_id" id="inspExitId">
                    <input type="hidden" name="tenant_id" id="inspTenantId">
                    <input type="hidden" name="unit_id" id="inspUnitId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1">Inspection Date <span class="text-danger">*</span></label>
                            <input type="date" name="inspection_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1">Inspected By</label>
                            <input type="text" name="inspected_by_name" class="form-control form-control-sm" placeholder="Inspector name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1">Overall Condition <span class="text-danger">*</span></label>
                            <select name="overall_condition" class="form-select form-select-sm" required>
                                <option value="">Select…</option>
                                <option>Excellent</option><option>Good</option>
                                <option>Fair</option><option>Poor</option><option>Very Poor</option>
                            </select>
                        </div>
                        @foreach([
                            ['walls_condition',      'Walls'],
                            ['floors_condition',     'Floors'],
                            ['ceiling_condition',    'Ceiling'],
                            ['doors_condition',      'Doors & Locks'],
                            ['windows_condition',    'Windows'],
                            ['plumbing_condition',   'Plumbing'],
                            ['electrical_condition', 'Electrical / Fittings'],
                        ] as [$field, $label])
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">{{ $label }}</label>
                            <select name="{{ $field }}" class="form-select form-select-sm">
                                <option value="">—</option>
                                <option>Excellent</option><option>Good</option>
                                <option>Fair</option><option>Poor</option><option>Very Poor</option>
                            </select>
                        </div>
                        @endforeach
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1">Keys Returned <span class="text-danger">*</span></label>
                            <select name="keys_returned" class="form-select form-select-sm" required>
                                <option value="">Select…</option>
                                <option>Yes</option><option>No</option><option>Partial</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label form-label-sm mb-1">Keys Notes</label>
                            <input type="text" name="keys_notes" class="form-control form-control-sm" placeholder="e.g. 2 of 3 keys returned">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1">Damage Description</label>
                            <textarea name="damage_description" class="form-control form-control-sm" rows="2" placeholder="Describe any visible damage…"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Estimated Damage Cost (KES)</label>
                            <input type="number" name="damage_amount" id="inspDamageAmount" class="form-control form-control-sm" min="0" step="0.01" oninput="syncInspectionToSettlement()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Cleaning Required <span class="text-danger">*</span></label>
                            <select name="cleaning_required" class="form-select form-select-sm" required>
                                <option value="">Select…</option>
                                <option>Yes</option><option>No</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Cleaning Cost (KES)</label>
                            <input type="number" name="cleaning_cost" id="inspCleaningCost" class="form-control form-control-sm" min="0" step="0.01" oninput="syncInspectionToSettlement()">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1">General Notes</label>
                            <textarea name="general_notes" class="form-control form-control-sm" rows="2" placeholder="Any other observations…"></textarea>
                        </div>
                        <div class="col-12">
                            <hr class="my-2">
                            <p class="text-muted mb-2" style="font-size:0.78rem;">
                                <span class="material-symbols-outlined align-middle" style="font-size:14px;">photo_camera</span>
                                Upload photos and/or videos of the unit condition. Multiple files allowed.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1">Photos</label>
                            <input type="file" name="inspection_photos[]" class="form-control form-control-sm"
                                   accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                                   multiple id="exitInspectionPhotos">
                            <div class="form-text" style="font-size:0.72rem;">JPG, PNG, WEBP, HEIC — multiple allowed</div>
                            <div id="exitPhotoPreview" class="d-flex flex-wrap gap-1 mt-2"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1">Videos</label>
                            <input type="file" name="inspection_videos[]" class="form-control form-control-sm"
                                   accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska"
                                   multiple id="exitInspectionVideos">
                            <div class="form-text" style="font-size:0.72rem;">MP4, MOV, AVI, MKV — multiple allowed</div>
                            <div id="exitVideoPreview" class="d-flex flex-wrap gap-1 mt-2"></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined align-middle" style="font-size:14px;">save</span> Save Inspection
                        </button>
                    </div>
                </form>
                <div class="mt-3 d-flex justify-content-between">
                    <button class="btn btn-sm btn-outline-secondary" onclick="switchP2Tab('statement')">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> Back
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="switchP2Tab('settlement')">
                        Next: Settlement Computation <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_forward</span>
                    </button>
                </div>
            </div>

            {{-- P2 Tab 4: Settlement Computation --}}
            <div id="p2Tab-settlement" class="p-3 d-none">
                <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.8rem;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#6d28d9;">info</span>
                    The settlement balance is computed automatically from the figures above. <strong>Other Deductions</strong> can be adjusted manually. Click <strong>Refresh from Server</strong> to reload live computed values from the database. The result determines whether a refund is issued, an invoice is raised, or the accounts are clear.
                </div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem;color:#555;text-transform:uppercase;letter-spacing:.04em;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:15px;">calculate</span>
                        Settlement Breakdown
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshSettlementFromServer()">
                        <span class="material-symbols-outlined align-middle" style="font-size:13px;">refresh</span> Refresh from Server
                    </button>
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card border">
                            <div class="card-body p-3">
                                <table class="table table-sm mb-0" style="font-size:0.85rem;">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted border-0">Deposit on Record</td>
                                            <td class="text-end fw-semibold border-0">KES <span id="settlDepositOnRecord">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted border-0">Less: Arrears (DR closing balance)</td>
                                            <td class="text-end text-danger border-0">− KES <span id="settlArrears">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted border-0">Less: Inspection Damage</td>
                                            <td class="text-end text-danger border-0">− KES <span id="settlDamage">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted border-0">Less: Cleaning Cost</td>
                                            <td class="text-end text-danger border-0">− KES <span id="settlCleaning">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted border-0">Less: Deposit Applied to Rent</td>
                                            <td class="text-end text-danger border-0">− KES <span id="settlDepositAsRent">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td class="border-0">
                                                <span class="text-muted">Less: Other Deductions</span>
                                                <input type="number" id="otherDeductionsInput" class="form-control form-control-sm d-inline-block ms-1" style="width:110px;" min="0" step="0.01" value="0" oninput="computeSettlement()">
                                            </td>
                                            <td class="text-end text-danger border-0">− KES <span id="settlOther">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="border-0 pb-1">
                                                <label class="form-label form-label-sm mb-1">Other Deductions Notes</label>
                                                <textarea id="otherDeductionsNotes" class="form-control form-control-sm" rows="1" placeholder="Describe other deductions…"></textarea>
                                            </td>
                                        </tr>
                                        <tr style="border-top:2px solid #1a1a2e;">
                                            <td class="fw-bold pt-2 fs-6">Settlement Balance</td>
                                            <td class="text-end fw-bold fs-5 pt-2">KES <span id="settlBalance">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td class="border-0 pt-1">Outcome</td>
                                            <td class="text-end border-0 pt-1">
                                                <span id="settlType" class="badge bg-secondary px-3 py-2" style="font-size:0.85rem;">Zero Balance</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <input type="hidden" id="hiddenSettlBalance" value="0">
                                <input type="hidden" id="hiddenSettlType" value="Zero Balance">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button class="btn btn-sm btn-outline-secondary" onclick="switchP2Tab('inspection')">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> Back
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="switchP2Tab('finalise')">
                        Next: Finalise Settlement <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_forward</span>
                    </button>
                </div>
            </div>

            {{-- P2 Tab 5: Finalise Settlement --}}
            <div id="p2Tab-finalise" class="p-3 d-none" id="panelFinaliseSettlement">

                {{-- Done state --}}
                <div id="settlementDoneView" class="d-none">
                    <div class="alert alert-success py-3" style="font-size:0.9rem;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:18px;">task_alt</span>
                        <strong>Process 2 Completed</strong> — Settlement has been finalised.
                        <div id="settlDoneSummary" class="mt-1 text-muted" style="font-size:0.82rem;"></div>
                    </div>
                </div>

                <div id="finaliseSettlementForm">
                    <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:0.8rem;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;color:#6d28d9;">info</span>
                        Review the settlement breakdown on the previous tab before finalising. Once submitted, the settlement record is locked and a refund or invoice will be generated as appropriate.
                    </div>
                    {{-- Settlement summary recap --}}
                    <div class="card border mb-3" style="border-left:4px solid #1a1a2e !important;">
                        <div class="card-body py-2 px-3" style="font-size:0.85rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Settlement Balance:</span>
                                <span class="fw-bold fs-5">KES <span id="recapBalance">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="text-muted">Outcome:</span>
                                <span id="recapType" class="badge bg-secondary px-3 py-1" style="font-size:0.82rem;">Zero Balance</span>
                            </div>
                        </div>
                    </div>
                    <form id="settlementForm">
                        <input type="hidden" name="exit_id" id="settlExitId">
                        <input type="hidden" name="tenant_id" id="settlTenantId">
                        <div class="mb-3">
                            <label class="form-label form-label-sm mb-1">Final Notes</label>
                            <textarea name="final_notes" id="settlFinalNotes" class="form-control" rows="3" placeholder="Any final remarks for the settlement record…"></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="switchP2Tab('settlement')">
                                <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> Back
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <span class="material-symbols-outlined align-middle" style="font-size:18px;">lock</span>
                                Finalise Settlement
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>{{-- /process2Body --}}
    </div>{{-- /process2Card --}}

</div>{{-- /exitWorkspace --}}

</div>{{-- /container-fluid --}}
</div>{{-- /page-body --}}

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{--  STYLES                                                                   --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<style>
.autocomplete-dropdown {
    position:absolute; left:0; right:0; top:100%;
    background:#fff; border:1px solid #dee2e6;
    border-radius:0 0 6px 6px; z-index:9999;
    max-height:280px; overflow-y:auto;
    box-shadow:0 4px 12px rgba(0,0,0,.12);
}
.autocomplete-item {
    padding:8px 14px; cursor:pointer;
    font-size:0.85rem; border-bottom:1px solid #f0f0f0;
}
.autocomplete-item:hover { background:#f8f9fa; }
.autocomplete-item .ref { color:#888; font-size:0.75rem; }

.comm-card { border:1px solid #e9ecef; border-radius:6px; padding:8px 10px; margin-bottom:6px; font-size:0.78rem; }
.comm-card .comm-meta { color:#888; font-size:0.7rem; margin-top:3px; }
.comm-card.email-card { border-left:3px solid #3498db; }
.comm-card.sms-card   { border-left:3px solid #2ecc71; }
.comm-card.both-card  { border-left:3px solid #9b59b6; }

.statement-dr      { color:#e74c3c; }
.statement-cr      { color:#27ae60; }
.statement-bal-pos { color:#27ae60; }
.statement-bal-neg { color:#e74c3c; }

.material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 20; }
</style>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{--  JAVASCRIPT                                                                --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
'use strict';

/* ── Routes ─────────────────────────────────────────────────────────────── */
const Routes = {
    searchTenants:     "{{ route('tenant.exit.search.tenants') }}",
    searchProperties:  "{{ route('tenant.exit.search.properties') }}",
    getOccupiedUnits:  "{{ url('tenant-exit/occupied-units') }}",
    getTenantByUnit:   "{{ url('tenant-exit/tenant-by-unit') }}",
    getExitProfile:    "{{ route('tenant.exit.profile') }}",
    initiateExit:      "{{ route('tenant.exit.initiate') }}",
    completeVacate:    "{{ route('tenant.exit.complete-vacate') }}",
    dateChange:        "{{ route('tenant.exit.date-change') }}",
    getDateChanges:    "{{ url('tenant-exit/date-changes') }}",
    fieldVisit:        "{{ route('tenant.exit.field-visit') }}",
    getFieldVisits:    "{{ url('tenant-exit/field-visits') }}",
    darEligibility:    "{{ route('tenant.exit.deposit-as-rent-eligibility') }}",
    depositAsRent:     "{{ route('tenant.exit.deposit-as-rent') }}",
    settlementPreview: "{{ route('tenant.exit.settlement-preview') }}",
    saveSettlement:    "{{ route('tenant.exit.settlement') }}",
    saveInspection:    "{{ route('tenant.exit.inspection.save') }}",
    sendComm:          "{{ route('tenant.exit.communication.send') }}",
    getComms:          "{{ url('tenant-exit/communications') }}",
    getTemplates:      "{{ route('tenant.exit.templates') }}",
};

/* ── State ──────────────────────────────────────────────────────────────── */
let currentProfile = null;

/* ═══════════════════════════════════════════════════════════════════════
   SEARCH TAB SWITCHING
═══════════════════════════════════════════════════════════════════════ */
function switchSearchTab(tab) {
    document.getElementById('tab-tenant').classList.toggle('active',   tab === 'tenant');
    document.getElementById('tab-property').classList.toggle('active', tab === 'property');
    document.getElementById('tenantSearchPane').classList.toggle('d-none',   tab !== 'tenant');
    document.getElementById('propertySearchPane').classList.toggle('d-none', tab !== 'property');
}

/* ═══════════════════════════════════════════════════════════════════════
   TENANT SEARCH
═══════════════════════════════════════════════════════════════════════ */
let tenantSearchTimer;
$('#tenantSearchInput').on('input', function () {
    clearTimeout(tenantSearchTimer);
    const q = $(this).val().trim();
    if (q.length < 2) { $('#tenantSearchResults').empty(); return; }
    tenantSearchTimer = setTimeout(() => {
        $.get(Routes.searchTenants, { search: q }, data => {
            if (!data.success) return;
            renderTenantDropdown(data.tenants);
        });
    }, 300);
});

function renderTenantDropdown(tenants) {
    const $el = $('#tenantSearchResults');
    if (!tenants.length) {
        $el.html('<div class="autocomplete-dropdown"><div class="autocomplete-item text-muted">No tenants found</div></div>');
        return;
    }
    let html = '<div class="autocomplete-dropdown">';
    tenants.forEach(t => {
        html += `<div class="autocomplete-item" onclick="selectTenant(${JSON.stringify(t).replace(/"/g,'&quot;')})">
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
        $.get(Routes.searchProperties, { search: q }, data => {
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
    $.get(Routes.getOccupiedUnits + '/' + propertyId, data => {
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
    if (!unitId) return;
    $.get(Routes.getTenantByUnit + '/' + unitId, data => {
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
    $.get(Routes.getExitProfile, { tenant_id: encId }, data => {
        if (!data.success) { toastr.error(data.message || 'Failed to load profile'); return; }

        currentProfile = data;
        const t = data.tenant;
        const er = data.exit_record || null;

        /* banner */
        $('#bannerName').text(t.names);
        $('#bannerRef').text(t.reference_number);
        $('#bannerMobile').text(t.mobile_number);
        $('#bannerEmail').text(t.email_address);
        $('#bannerProperty').text(t.property_name);
        $('#bannerUnit').text(t.unit_name);

        const bal = parseFloat(data.closing_balance);
        $('#bannerBalance')
            .text((bal >= 0 ? 'CR ' : 'DR ') + Math.abs(bal).toLocaleString('en-KE', {minimumFractionDigits:2}))
            .css('color', bal >= 0 ? '#27ae60' : '#e74c3c');

        /* state — prefer exit record's property/unit if tenant links were already cleared */
        const propId = er?.property_id || t.property_id || '';
        const unitId = er?.unit_id     || t.unit_id     || '';
        $('#stateTenantId').val(t.id);
        $('#stateTenantEncId').val(encId);
        $('#statePropertyId').val(propId);
        $('#stateUnitId').val(unitId);

        /* wire form ids */
        $('#exitTenantId').val(t.id);
        $('#exitPropertyId').val(propId);
        $('#exitUnitId').val(unitId);
        $('#inspTenantId').val(t.id);
        $('#inspUnitId').val(unitId);
        $('#commTenantId').val(t.id);
        $('#commEmailAddr').val(t.email_address || '');
        $('#commMobile').val(t.mobile_number || '');

        /* pre-fill exit details */
        if (er) {
            $('#stateExitId').val(er.id);
            $('#inspExitId').val(er.id);
            $('#commExitId').val(er.id);
            $('#visitExitId').val(er.id);
            $('#dateChangeExitId').val(er.id);
            $('#settlExitId').val(er.id);
            $('#settlTenantId').val(t.id);

            // Vacate type
            const vt = er.vacate_type || 'With Notice';
            $('input[name=vacate_type][value="' + vt + '"]').prop('checked', true);
            $('#stateVacateType').val(vt);
            onVacateTypeChange(vt);

            $('#vacateNoticeDate').val(er.vacate_notice_date ?? '');
            $('#intendedExitDate').val(er.intended_exit_date ?? '');
            $('#actualExitDateTop').val(er.actual_exit_date ?? '');
            setExitReasonFromSaved(er.exit_reason ?? '');
            $('#exitNotes').val(er.exit_notes ?? '');

            // Show "Complete Vacate" panel only if not yet completed
            if (!er.process_1_completed_at) {
                $('#panelCompleteVacateGate').addClass('d-none');
                $('#panelCompleteVacate').removeClass('d-none');
                $('#panelP1Done').addClass('d-none');
                $('#stateP1Done').val('0');
                updateProcessBadges('p1-inprogress');
                // Mark details tab done (exit record exists)
                $('#p1Chk-details').removeClass('d-none');
                switchP1Tab('details');
            } else {
                $('#panelCompleteVacateGate').addClass('d-none');
                $('#panelCompleteVacate').addClass('d-none');
                $('#panelP1Done').removeClass('d-none');
                $('#p1VacatedDate').text(er.actual_exit_date || er.process_1_completed_at);
                $('#stateP1Done').val('1');
                updateProcessBadges('p1-done');
                // Mark all P1 tabs done
                $('#p1Chk-details').removeClass('d-none');
                if ((er.vacate_type || 'With Notice') === 'With Notice') {
                    $('#p1Chk-tracking').removeClass('d-none');
                }
                $('#p1Chk-vacate').removeClass('d-none');
                switchP1Tab('vacate');
                unlockProcess2(data, er);
            }

            // Load pre-exit tracking data
            loadFieldVisits();
            loadDateChanges();
            loadCommunications();
            updateCurrentIntendedDate(er.intended_exit_date);
        } else {
            // Fresh — default state
            $('#stateExitId').val('');
            $('#settlExitId').val('');
            $('#settlTenantId').val(t.id);
            onVacateTypeChange('With Notice');
            $('#panelCompleteVacateGate').removeClass('d-none');
            $('#panelCompleteVacate').addClass('d-none');
            $('#panelP1Done').addClass('d-none');
            updateProcessBadges('fresh');
        }

        /* P2 statement & deposits (always render for reference) */
        renderP2Statement(data.statement, data.closing_balance);
        renderP2Deposits(data.deposits);
        populateSettlementFigures(data);

        /* Show workspace */
        $('#exitWorkspace').removeClass('d-none');
        $('html, body').animate({ scrollTop: $('#exitWorkspace').offset().top - 20 }, 300);

    }).fail(() => toastr.error('Failed to load tenant profile.'));
}

/* ── P1 tab switcher ────────────────────────────────────────────────────── */
function switchP1Tab(name) {
    ['details','tracking','vacate'].forEach(t => {
        $('#p1Tab-' + t).toggleClass('d-none', t !== name);
        $('#p1TabBtn-' + t).toggleClass('active', t === name);
        // update number badge colour on active vs inactive
        const pill = $('#p1TabBtn-' + t + ' .badge.rounded-pill');
        pill.css('background', t === name ? '#1a1a2e' : '#6c757d');
    });
}

/* ── P2 tab switcher ────────────────────────────────────────────────────── */
function switchP2Tab(name) {
    ['deposit','statement','inspection','settlement','finalise'].forEach(t => {
        $('#p2Tab-' + t).toggleClass('d-none', t !== name);
        $('#p2TabBtn-' + t).toggleClass('active', t === name);
        const pill = $('#p2TabBtn-' + t + ' .badge.rounded-pill');
        pill.css('background', t === name ? '#1a1a2e' : '#6c757d');
    });
    // Load eligibility data when deposit tab is shown (With Notice only)
    if (name === 'deposit' && $('#stateVacateType').val() === 'With Notice') {
        loadDarEligibility();
    }
    // Sync recap balances on finalise tab
    if (name === 'finalise') {
        $('#recapBalance').text($('#settlBalance').text());
        const typeEl = $('#settlType');
        $('#recapType').text(typeEl.text())
            .removeClass('bg-success bg-danger bg-secondary')
            .addClass(typeEl.hasClass('bg-success') ? 'bg-success' : (typeEl.hasClass('bg-danger') ? 'bg-danger' : 'bg-secondary'));
    }
}

/* ── Vacate type change handler ─────────────────────────────────────────── */
function onVacateTypeChange(vt) {
    $('#stateVacateType').val(vt);
    const isWithNotice = vt === 'With Notice';

    // Notice Date — only for With Notice
    $('#noticeDateWrapper').toggleClass('d-none', !isWithNotice);
    $('#vacateNoticeDate').prop('required', isWithNotice);

    // Intended Exit Date — only meaningful for With Notice
    $('#intendedDateWrapper').toggleClass('d-none', !isWithNotice);
    $('#intendedExitDate').prop('required', isWithNotice);
    if (!isWithNotice) $('#intendedExitDate').val('');

    // Actual Exit Date label + required changes based on type
    $('#actualExitDateLabel').html(
        isWithNotice
            ? 'Actual Exit Date'
            : 'Exit Date <span class="text-danger">*</span>'
    );
    $('#actualExitDateTop').prop('required', !isWithNotice);

    // Pre-Exit Tracking tab: only visible for With Notice
    $('#p1TabLi-tracking').toggleClass('d-none', !isWithNotice);
    if (!isWithNotice && $('#p1Tab-tracking').is(':visible')) {
        switchP1Tab('details');
    }

    // Deposit-as-Rent: only for With Notice
    $('#depositAsRentSection').toggleClass('d-none', !isWithNotice);

    // Hint text
    const hints = {
        'With Notice':    'Pre-Exit Tracking will be available — notice communications, field visits, and date changes will be tracked.',
        'Without Notice': 'No pre-exit tracking required. Proceed to Complete Vacate after saving these details.',
        'Eviction':       'No pre-exit tracking required. Proceed to Complete Vacate after saving these details.',
    };
    $('#vtHintText').text(hints[vt] || '');
}

$('input[name=vacate_type]').on('change', function () {
    onVacateTypeChange($(this).val());
});

/* ── Exit reason: standard pick or free-text ────────────────────────────── */
const STANDARD_REASONS = ['End of Lease','Voluntary Vacate','Eviction','Transfer','Upgrade or Downgrade','Non-Payment'];

function onExitReasonChange(val) {
    if (val === 'Other') {
        $('#exitReasonCustom').removeClass('d-none').attr('required', true).focus();
        $('#exitReason').val($('#exitReasonCustom').val() || '');
    } else {
        $('#exitReasonCustom').addClass('d-none').removeAttr('required').val('');
        $('#exitReason').val(val);
    }
}

function setExitReasonFromSaved(saved) {
    if (!saved) return;
    if (STANDARD_REASONS.includes(saved)) {
        $('#exitReasonSelect').val(saved);
        $('#exitReason').val(saved);
    } else {
        $('#exitReasonSelect').val('Other');
        $('#exitReasonCustom').removeClass('d-none').attr('required', true).val(saved);
        $('#exitReason').val(saved);
    }
}

/* ── Process status badges ──────────────────────────────────────────────── */
function updateProcessBadges(state) {
    const $p1b = $('#p1StatusBadge, #p1BannerBadge');
    const $p2b = $('#p2StatusBadge, #p2BannerBadge');
    if (state === 'fresh') {
        $p1b.text('Process 1: Pending').css('background','#f59e0b');
        $p2b.text('Process 2: Pending').css('background','#6c757d');
        $('#p1StatusBadge').text('In Progress').css('background','#f59e0b');
        $('#p1BannerBadge').text('Process 1: In Progress').css('background','#f59e0b');
    } else if (state === 'p1-inprogress') {
        $('#p1StatusBadge').text('In Progress').css('background','#f59e0b');
        $('#p1BannerBadge').text('Process 1: In Progress').css('background','#f59e0b');
        $('#p2StatusBadge').text('Pending').css('background','#6c757d');
        $('#p2BannerBadge').text('Process 2: Pending').css('background','#6c757d');
    } else if (state === 'p1-done') {
        $('#p1StatusBadge').text('Completed').css('background','#27ae60');
        $('#p1BannerBadge').text('Process 1: Completed').css('background','#27ae60');
        $('#p2StatusBadge').text('In Progress').css('background','#f59e0b');
        $('#p2BannerBadge').text('Process 2: In Progress').css('background','#f59e0b');
    } else if (state === 'p2-done') {
        $('#p1StatusBadge').text('Completed').css('background','#27ae60');
        $('#p1BannerBadge').text('Process 1: Completed').css('background','#27ae60');
        $('#p2StatusBadge').text('Completed').css('background','#27ae60');
        $('#p2BannerBadge').text('Process 2: Completed').css('background','#27ae60');
    }
}

/* ── Unlock Process 2 ───────────────────────────────────────────────────── */
function unlockProcess2(data, er) {
    $('#p2Locked').hide();
    $('#process2Body').show();

    // Show deposit-as-rent section if With Notice
    if ((er.vacate_type || 'With Notice') === 'With Notice') {
        $('#depositAsRentSection').removeClass('d-none');
        if (er.deposit_as_rent) {
            $('#applyDepositAsRent').prop('checked', true);
            $('#depositAsRentFields').removeClass('d-none');
            $('#darApproved').prop('checked', !!er.deposit_as_rent_approved);
            $('#settlDepositAsRent').text(parseFloat(er.deposit_as_rent_amount || 0).toLocaleString('en', {minimumFractionDigits:2}));
        }
    }

    // P2 completion: check settlement record (settled_at), NOT a non-existent column
    const settlement = er.settlement || data.settlement || null;
    if (settlement && settlement.settled_at) {
        updateProcessBadges('p2-done');
        $('#p2Chk-finalise').removeClass('d-none');
        $('#settlementDoneView').removeClass('d-none');
        $('#finaliseSettlementForm').addClass('d-none');
        const typeLabel = settlement.settlement_type || '';
        const bal = parseFloat(settlement.settlement_balance || 0).toLocaleString('en', {minimumFractionDigits:2});
        $('#settlDoneSummary').html(
            'Outcome: <strong>' + typeLabel + '</strong> &mdash; KES ' + bal +
            ' &mdash; Finalised on ' + (settlement.settled_at || '')
        );
        // Mark settlement computation tab as done too
        $('#p2Chk-settlement').removeClass('d-none');
        switchP2Tab('finalise');
    }

    // Inspection checkmark
    if (data.inspection) {
        $('#p2Chk-inspection').removeClass('d-none');
    }
}

/* ═══════════════════════════════════════════════════════════════════════
   RENDER HELPERS
═══════════════════════════════════════════════════════════════════════ */
function renderP2Statement(entries, closingBalance) {
    let html = '';
    (entries || []).forEach(e => {
        const isNeg = parseFloat(e.balance) < 0;
        html += `<tr>
            <td>${e.date_formatted}</td><td>${e.reference}</td><td>${e.narration}</td>
            <td class="text-end statement-dr">${e.debit_formatted}</td>
            <td class="text-end statement-cr">${e.credit_formatted}</td>
            <td class="text-end fw-bold ${isNeg ? 'statement-bal-neg' : 'statement-bal-pos'}">${e.balance_formatted}</td>
        </tr>`;
    });
    $('#p2StatementBody').html(html || '<tr><td colspan="6" class="text-center text-muted">No transactions found.</td></tr>');
    const bal = parseFloat(closingBalance);
    const balFmt = (bal >= 0 ? 'CR ' : 'DR ') + Math.abs(bal).toLocaleString('en-KE', {minimumFractionDigits:2});
    $('#p2StatementBalanceBadge').text(balFmt).css('background', bal >= 0 ? '#27ae60' : '#e74c3c');
}

function renderP2Deposits(deposits) {
    if (!deposits || !deposits.length) {
        $('#p2DepositsTable').html('<div class="text-muted" style="font-size:0.85rem;">No deposit records found.</div>');
        return;
    }
    let total = 0;
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-1"><thead class="table-light"><tr><th>Reference</th><th>Property</th><th>Unit</th><th>Date Received</th><th class="text-end">Amount (KES)</th></tr></thead><tbody>';
    deposits.forEach(d => {
        const amt = parseFloat((d.amount + '').replace(/,/g,''));
        total += isNaN(amt) ? 0 : amt;
        html += `<tr><td>${d.reference}</td><td>${d.property_name}</td><td>${d.unit_name}</td><td>${d.date_received}</td><td class="text-end fw-semibold">${d.amount}</td></tr>`;
    });
    html += `<tr class="table-light fw-bold"><td colspan="4" class="text-end">Total Deposit Held</td><td class="text-end">KES ${total.toLocaleString('en',{minimumFractionDigits:2})}</td></tr>`;
    html += '</tbody></table></div>';
    $('#p2DepositsTable').html(html);
}

function populateSettlementFigures(data) {
    const deposits = data.deposits || [];
    let totalDeposit = 0;
    deposits.forEach(d => {
        const a = parseFloat((d.amount + '').replace(/,/g,''));
        if (!isNaN(a)) totalDeposit += a;
    });
    const bal = parseFloat(data.closing_balance || 0);
    const arrears = bal < 0 ? Math.abs(bal) : 0;
    $('#settlDepositOnRecord').text(totalDeposit.toLocaleString('en',{minimumFractionDigits:2}));
    $('#settlArrears').text(arrears.toLocaleString('en',{minimumFractionDigits:2}));
    computeSettlement();
}

function updateCurrentIntendedDate(d) {
    $('#currentIntendedDateDisplay').text(d || '—');
    $('#intendedExitDate').on('change', function(){
        $('#currentIntendedDateDisplay').text($(this).val() || '—');
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   SETTLEMENT COMPUTATION
═══════════════════════════════════════════════════════════════════════ */
function computeSettlement() {
    var deposit   = parseFloat($('#settlDepositOnRecord').text().replace(/,/g,'')) || 0;
    var arrears   = parseFloat($('#settlArrears').text().replace(/,/g,''))         || 0;
    var damage    = parseFloat($('#settlDamage').text().replace(/,/g,''))           || 0;
    var cleaning  = parseFloat($('#settlCleaning').text().replace(/,/g,''))         || 0;
    var dar       = parseFloat($('#settlDepositAsRent').text().replace(/,/g,''))    || 0;
    var other     = parseFloat($('#otherDeductionsInput').val())                    || 0;
    var balance   = deposit - arrears - damage - cleaning - dar - other;

    $('#settlOther').text(other.toLocaleString('en',{minimumFractionDigits:2}));
    $('#settlBalance').text(Math.abs(balance).toLocaleString('en',{minimumFractionDigits:2}));

    if (balance > 0.005) {
        $('#settlType').text('Refund to Tenant').removeClass('bg-danger bg-secondary').addClass('bg-success');
    } else if (balance < -0.005) {
        $('#settlType').text('Invoice to Tenant').removeClass('bg-success bg-secondary').addClass('bg-danger');
    } else {
        $('#settlType').text('Zero Balance').removeClass('bg-success bg-danger').addClass('bg-secondary');
    }
    $('#hiddenSettlBalance').val(Math.abs(balance).toFixed(2));
    $('#hiddenSettlType').val(balance > 0.005 ? 'Refund to Tenant' : (balance < -0.005 ? 'Invoice to Tenant' : 'Zero Balance'));
}

function syncInspectionToSettlement() {
    var damage   = parseFloat($('#inspDamageAmount').val()) || 0;
    var cleaning = parseFloat($('#inspCleaningCost').val()) || 0;
    $('#settlDamage').text(damage.toLocaleString('en',{minimumFractionDigits:2}));
    $('#settlCleaning').text(cleaning.toLocaleString('en',{minimumFractionDigits:2}));
    computeSettlement();
}

function refreshSettlementFromServer() {
    const exitId = $('#stateExitId').val();
    if (!exitId) { toastr.warning('No exit record found.'); return; }
    $.get(Routes.settlementPreview, { exit_id: exitId }, data => {
        if (!data.success) { toastr.error(data.message || 'Failed to refresh'); return; }
        if (data.deposit_held !== undefined)  $('#settlDepositOnRecord').text(parseFloat(data.deposit_held).toLocaleString('en',{minimumFractionDigits:2}));
        if (data.arrears !== undefined)       $('#settlArrears').text(parseFloat(data.arrears).toLocaleString('en',{minimumFractionDigits:2}));
        if (data.damage_amount !== undefined) $('#settlDamage').text(parseFloat(data.damage_amount).toLocaleString('en',{minimumFractionDigits:2}));
        if (data.cleaning_cost !== undefined) $('#settlCleaning').text(parseFloat(data.cleaning_cost).toLocaleString('en',{minimumFractionDigits:2}));
        if (data.deposit_as_rent !== undefined) $('#settlDepositAsRent').text(parseFloat(data.deposit_as_rent).toLocaleString('en',{minimumFractionDigits:2}));
        computeSettlement();
        toastr.success('Settlement figures refreshed.');
    }).fail(() => toastr.error('Failed to refresh settlement.'));
}

/* ═══════════════════════════════════════════════════════════════════════
   FORM: SAVE EXIT DETAILS
═══════════════════════════════════════════════════════════════════════ */
$('#exitDetailsForm').on('submit', function (e) {
    e.preventDefault();
    $('#exitDetailsSaving').removeClass('d-none').addClass('d-flex');
    const formData = Object.fromEntries(new FormData(this));
    formData._token = '{{ csrf_token() }}';
    $.ajax({
        url: Routes.initiateExit, type: 'POST',
        data: formData,
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Exit details saved.');
                if (res.exit_id) {
                    const eid = res.exit_id;
                    $('#stateExitId').val(eid);
                    $('#inspExitId').val(eid);
                    $('#commExitId').val(eid);
                    $('#visitExitId').val(eid);
                    $('#dateChangeExitId').val(eid);
                    $('#settlExitId').val(eid);
                    $('#settlTenantId').val($('#stateTenantId').val());
                }
                // Show Complete Vacate panel, hide gate
                $('#panelCompleteVacateGate').addClass('d-none');
                $('#panelCompleteVacate').removeClass('d-none');
                // Sync current intended date display
                updateCurrentIntendedDate($('#intendedExitDate').val());
                updateProcessBadges('p1-inprogress');
            } else {
                toastr.error(res.message || 'Failed to save exit details');
            }
        },
        error: (xhr) => {
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                const msgs = Object.values(xhr.responseJSON.errors).flat();
                toastr.error(msgs.join('<br>'), 'Validation Error', { escapeHtml: false });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Failed to save exit details.');
            }
        },
    }).always(() => $('#exitDetailsSaving').addClass('d-none').removeClass('d-flex'));
});

/* ═══════════════════════════════════════════════════════════════════════
   COMPLETE VACATE (Mark Unit Vacant)
═══════════════════════════════════════════════════════════════════════ */
$('#completeVacateBtn').on('click', function () {
    const exitId = $('#stateExitId').val();
    if (!exitId) { toastr.warning('Please save exit details first.'); return; }
    const exitDate = $('#actualExitDateFinal').val();
    if (!exitDate) { toastr.warning('Please enter the actual exit date.'); return; }
    if (!confirm('This will mark the unit as vacant and unlink the tenant. Continue?')) return;
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing…');
    $.ajax({
        url: Routes.completeVacate, type: 'POST',
        data: { exit_id: exitId, tenant_id: $('#stateTenantId').val(), actual_exit_date: exitDate, _token: '{{ csrf_token() }}' },
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Process 1 complete. Unit is now vacant.');
                $('#panelCompleteVacate').addClass('d-none');
                $('#panelP1Done').removeClass('d-none');
                $('#p1VacatedDate').text(exitDate);
                $('#stateP1Done').val('1');
                updateProcessBadges('p1-done');
                // Mark all P1 tabs complete
                $('#p1Chk-details, #p1Chk-vacate').removeClass('d-none');
                if ($('#stateVacateType').val() === 'With Notice') {
                    $('#p1Chk-tracking').removeClass('d-none');
                }
                switchP1Tab('vacate');
                // Unlock P2
                $('#p2Locked').hide();
                $('#process2Body').show();
                if ($('#stateVacateType').val() === 'With Notice') {
                    $('#depositAsRentSection').removeClass('d-none');
                }
                // Scroll to P2 and open deposit tab
                switchP2Tab('deposit');
                $('html,body').animate({ scrollTop: $('#process2Card').offset().top - 20 }, 400);
            } else {
                toastr.error(res.message || 'Failed to complete vacate.');
                $('#completeVacateBtn').prop('disabled', false).html('<span class="material-symbols-outlined align-middle" style="font-size:14px;">check_circle</span> Mark Unit Vacant &amp; Complete Process 1');
            }
        },
        error: () => {
            toastr.error('Failed to complete vacate.');
            $('#completeVacateBtn').prop('disabled', false).html('<span class="material-symbols-outlined align-middle" style="font-size:14px;">check_circle</span> Mark Unit Vacant &amp; Complete Process 1');
        }
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   FIELD VISIT
═══════════════════════════════════════════════════════════════════════ */
$('#fieldVisitForm').on('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    data._token = '{{ csrf_token() }}';
    $.ajax({
        url: Routes.fieldVisit, type: 'POST', data: data,
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) { toastr.success(res.message || 'Visit saved.'); this.reset(); loadFieldVisits(); }
            else toastr.error(res.message || 'Failed to save visit.');
        },
        error: () => toastr.error('Failed to save visit.')
    });
});

function loadFieldVisits() {
    const exitId = $('#stateExitId').val();
    if (!exitId) return;
    $.get(Routes.getFieldVisits + '/' + exitId, data => {
        if (!data.success || !data.visits.length) return;
        let html = '';
        data.visits.forEach(v => {
            html += `<tr><td>${v.visit_date}</td><td>${v.officer_name}</td><td>${v.unit_status_found}</td><td>${v.visit_notes || '—'}</td></tr>`;
        });
        $('#visitTableBody').html(html);
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   DATE CHANGES
═══════════════════════════════════════════════════════════════════════ */
function toggleDateChangeForm() {
    $('#dateChangeFormWrapper').toggleClass('d-none');
}

$('#dateChangeForm').on('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    data._token = '{{ csrf_token() }}';
    $.ajax({
        url: Routes.dateChange, type: 'POST', data: data,
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Date change saved.');
                this.reset();
                $('#dateChangeFormWrapper').addClass('d-none');
                if (res.new_date) {
                    $('#intendedExitDate').val(res.new_date);
                    updateCurrentIntendedDate(res.new_date);
                }
                loadDateChanges();
            } else toastr.error(res.message || 'Failed to save date change.');
        },
        error: () => toastr.error('Failed to save date change.')
    });
});

function loadDateChanges() {
    const exitId = $('#stateExitId').val();
    if (!exitId) return;
    $.get(Routes.getDateChanges + '/' + exitId, data => {
        if (!data.success || !data.changes.length) return;
        let html = '';
        data.changes.forEach(c => {
            html += `<tr><td>${c.previous_date}</td><td>${c.new_date}</td><td>${c.reason}</td><td>${c.changed_by_name || '—'}</td><td>${c.created_at}</td></tr>`;
        });
        $('#dateChangeTableBody').html(html);
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   COMMUNICATIONS
═══════════════════════════════════════════════════════════════════════ */
function toggleCommFields() {
    const type = $('input[name=comm_type]:checked').val();
    $('#emailFields').toggleClass('d-none', type === 'SMS');
    $('#smsFields').toggleClass('d-none', type === 'Email');
}

$('#commBody').on('input', function () {
    $('#commCharCount').text($(this).val().length);
});

$('#commForm').on('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    data._token = '{{ csrf_token() }}';
    $.ajax({
        url: Routes.sendComm, type: 'POST', data: data,
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Sent.');
                $('#commBody').val(''); $('#commSubject').val(''); $('#commCharCount').text('0');
                loadCommunications();
            } else toastr.error(res.message || 'Failed to send.');
        },
        error: () => toastr.error('Failed to send communication.')
    });
});

function loadCommunications() {
    const exitId = $('#stateExitId').val();
    if (!exitId) return;
    $.get(Routes.getComms + '/' + exitId, data => {
        if (!data.success) return;
        if (!data.communications.length) {
            $('#commHistory').html('<div class="text-center text-muted py-3" style="font-size:0.78rem;">No communications yet.</div>');
            return;
        }
        let html = '';
        data.communications.forEach(c => {
            const cls = c.comm_type === 'Email' ? 'email-card' : c.comm_type === 'SMS' ? 'sms-card' : 'both-card';
            html += `<div class="comm-card ${cls}">
                <div class="d-flex justify-content-between">
                    <strong>${c.subject || c.comm_type + ' Message'}</strong>
                    <span class="badge bg-light text-dark border" style="font-size:0.68rem;">${c.comm_type}</span>
                </div>
                <div style="color:#333;margin-top:3px;">${(c.message_body+'').substring(0,120)}${(c.message_body+'').length > 120 ? '…' : ''}</div>
                <div class="comm-meta">Sent by ${c.sent_by_name ?? '-'} on ${new Date(c.sent_at).toLocaleString()}</div>
            </div>`;
        });
        $('#commHistory').html(html);
    });
}

/* ── Templates ──────────────────────────────────────────────────────────── */
function loadTemplates() {
    const t = currentProfile?.tenant;
    if (!t) { toastr.warning('Load a tenant first.'); return; }
    const params = {
        tenant_name:   t.names,
        property_name: t.property_name,
        exit_date:     $('#intendedExitDate').val() || '-',
    };
    $.get(Routes.getTemplates, params, data => {
        if (!data.success) return;
        const $sel = $('#trackingTemplateSelect');
        $sel.html('<option value="">— Choose template —</option>');
        data.templates.forEach(tpl => {
            $sel.append(`<option value="${tpl.key}" data-subject="${tpl.subject}" data-body="${encodeURIComponent(tpl.body)}">${tpl.label}</option>`);
        });
        $('#trackingTemplatesPanel').removeClass('d-none');
    });
}

function applyTrackingTemplate() {
    const sel = $('#trackingTemplateSelect').find(':selected');
    if (!sel.val()) return;
    $('#commSubject').val(sel.data('subject'));
    $('#commBody').val(decodeURIComponent(sel.data('body')));
    $('#commCharCount').text($('#commBody').val().length);
    $('#commTemplateUsed').val(sel.val());
}

/* ═══════════════════════════════════════════════════════════════════════
   DEPOSIT AS RENT
═══════════════════════════════════════════════════════════════════════ */
let darEligibilityData = null;

function loadDarEligibility() {
    const exitId = $('#stateExitId').val();
    const vacateType = $('#stateVacateType').val();
    if (!exitId || vacateType !== 'With Notice') return;

    $.get(Routes.darEligibility, { exit_id: exitId }, data => {
        darEligibilityData = data;
        if (!data.success) return;

        // Populate calculator
        const fmt = n => parseFloat(n || 0).toLocaleString('en', { minimumFractionDigits: 2 });
        $('#darCalcDeposit').text('KES ' + fmt(data.deposit_total));
        $('#darCalcRent').text('KES ' + fmt(data.monthly_rent));
        $('#darCalcApply').text('KES ' + fmt(data.dar_amount));
        $('#darCalcResidual').text('KES ' + fmt(data.residual_hold));
        $('#darCalcShortfall').text('KES ' + fmt(data.shortfall));
        $('#darCalculator').show();

        if (data.shortfall > 0.01) {
            $('#darShortfallRow').show();
            $('#darShortfallAlert').show();
        } else {
            $('#darShortfallRow').hide();
            $('#darShortfallAlert').hide();
        }

        // Eligibility banner
        const $banner = $('#darEligibilityBanner');
        if (data.eligible) {
            $banner.html('<div class="alert alert-success py-1 px-2 mb-0" style="font-size:0.76rem;"><span class="material-symbols-outlined align-middle me-1" style="font-size:13px;">check_circle</span>Eligible: all conditions met for deposit-as-rent.</div>');
            $('#applyDepositAsRent').prop('disabled', false);
        } else {
            const issueList = data.issues.map(i => `<li>${i}</li>`).join('');
            $banner.html(`<div class="alert alert-danger py-1 px-2 mb-0" style="font-size:0.76rem;"><span class="material-symbols-outlined align-middle me-1" style="font-size:13px;">cancel</span><strong>Not eligible:</strong><ul class="mb-0 mt-1">${issueList}</ul></div>`);
            $('#applyDepositAsRent').prop('disabled', true).prop('checked', false);
            $('#depositAsRentFields').addClass('d-none');
        }
        $banner.show();
    });
}

function toggleDepositAsRentFields() {
    const checked = $('#applyDepositAsRent').is(':checked');
    $('#depositAsRentFields').toggleClass('d-none', !checked);
    if (!checked) {
        $('#settlDepositAsRent').text('0.00');
        computeSettlement();
    }
}

function saveDepositAsRent() {
    const exitId = $('#stateExitId').val();
    if (!exitId) { toastr.warning('No exit record found.'); return; }
    const apply    = $('#applyDepositAsRent').is(':checked') ? 1 : 0;
    const approved = $('#darApproved').is(':checked') ? 1 : 0;
    $.ajax({
        url: Routes.depositAsRent, type: 'POST',
        data: { exit_id: exitId, deposit_as_rent: apply, deposit_as_rent_approved: approved, _token: '{{ csrf_token() }}' },
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Saved.');
                const darAmt = apply && darEligibilityData ? (darEligibilityData.dar_amount || 0) : 0;
                $('#settlDepositAsRent').text(parseFloat(darAmt).toLocaleString('en', { minimumFractionDigits: 2 }));
                computeSettlement();
            } else {
                toastr.error(res.message || 'Failed.');
            }
        },
        error: xhr => {
            const msg = xhr.responseJSON?.message || 'Failed to save deposit-as-rent.';
            toastr.error(msg);
        }
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   INSPECTION
═══════════════════════════════════════════════════════════════════════ */
$('#inspectionForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', '{{ csrf_token() }}');
    $.ajax({
        url: Routes.saveInspection, type: 'POST', data: fd,
        processData: false, contentType: false,
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Inspection saved.');
                syncInspectionToSettlement();
                showSavedInspection();
            } else toastr.error(res.message || 'Failed to save inspection.');
        },
        error: () => toastr.error('Failed to save inspection.')
    });
});

/* ── Inspection media previews ── */
document.getElementById('exitInspectionPhotos').addEventListener('change', function() {
    const preview = document.getElementById('exitPhotoPreview');
    preview.innerHTML = '';
    Array.from(this.files).forEach(file => {
        const url = URL.createObjectURL(file);
        preview.insertAdjacentHTML('beforeend',
            `<div class="position-relative" style="width:64px;height:64px;">
                <img src="${url}" style="width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                <span class="position-absolute bottom-0 start-0 w-100 text-truncate px-1" style="font-size:0.6rem;background:rgba(0,0,0,.45);color:#fff;border-radius:0 0 4px 4px;">${file.name}</span>
             </div>`
        );
    });
});
document.getElementById('exitInspectionVideos').addEventListener('change', function() {
    const preview = document.getElementById('exitVideoPreview');
    preview.innerHTML = '';
    Array.from(this.files).forEach(file => {
        preview.insertAdjacentHTML('beforeend',
            `<div class="d-flex align-items-center gap-1 border rounded px-2 py-1" style="font-size:0.72rem;max-width:180px;">
                <span class="material-symbols-outlined" style="font-size:16px;color:#6c757d;">videocam</span>
                <span class="text-truncate">${file.name}</span>
             </div>`
        );
    });
});

function showSavedInspection() {
    $('#savedInspectionView').removeClass('d-none');
    const summaryFields = [
        ['Inspection Date', 'input[name=inspection_date]'],
        ['Overall Condition', 'select[name=overall_condition]'],
        ['Keys Returned', 'select[name=keys_returned]'],
        ['Damage Cost (KES)', '#inspDamageAmount'],
        ['Cleaning Cost (KES)', '#inspCleaningCost'],
    ];
    let html = '';
    summaryFields.forEach(([label, sel]) => {
        const val = $(sel).val() || '—';
        html += `<div class="col-md-3"><small class="text-muted">${label}</small><div class="fw-semibold" style="font-size:0.85rem;">${val}</div></div>`;
    });
    $('#savedInspectionSummary').html(html);
}

function editInspection() {
    $('#savedInspectionView').addClass('d-none');
}

/* ═══════════════════════════════════════════════════════════════════════
   FINALISE SETTLEMENT
═══════════════════════════════════════════════════════════════════════ */
$('#settlementForm').on('submit', function (e) {
    e.preventDefault();
    if (!confirm('Finalise settlement? This completes the exit process.')) return;
    const exitId  = $('#stateExitId').val();
    const tenantId = $('#stateTenantId').val();
    const data = {
        exit_id:               exitId,
        tenant_id:             tenantId,
        settlement_balance:    $('#hiddenSettlBalance').val(),
        settlement_type:       $('#hiddenSettlType').val(),
        other_deductions:      $('#otherDeductionsInput').val() || 0,
        other_deductions_notes:$('#otherDeductionsNotes').val() || '',
        final_notes:           $('#settlFinalNotes').val() || '',
        _token:                '{{ csrf_token() }}',
    };
    $.ajax({
        url: Routes.saveSettlement, type: 'POST', data: data,
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        success: res => {
            if (res.success) {
                toastr.success(res.message || 'Settlement finalised.');
                updateProcessBadges('p2-done');
                $('#p2Chk-finalise, #p2Chk-settlement').removeClass('d-none');
                $('#settlementDoneView').removeClass('d-none');
                $('#finaliseSettlementForm').addClass('d-none');
                const stType = data.settlement_type || $('#hiddenSettlType').val();
                const stBal  = data.settlement_balance || $('#hiddenSettlBalance').val();
                $('#settlDoneSummary').html(
                    'Outcome: <strong>' + stType + '</strong> &mdash; KES ' +
                    parseFloat(stBal).toLocaleString('en', {minimumFractionDigits:2})
                );
            } else toastr.error(res.message || 'Failed to finalise settlement.');
        },
        error: () => toastr.error('Failed to finalise settlement.')
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   CLOSE DROPDOWNS ON OUTSIDE CLICK
═══════════════════════════════════════════════════════════════════════ */
$(document).on('click', function (e) {
    if (!$(e.target).closest('#tenantSearchInput, #tenantSearchResults').length)
        $('#tenantSearchResults').empty();
    if (!$(e.target).closest('#propertySearchInput, #propertySearchResults').length)
        $('#propertySearchResults').empty();
});

/* ── Pre-load tenant if passed via URL (from controller) ──────────────── */
@if($tenant)
$(function () { loadTenantProfile('{{ Crypt::encrypt($tenant->id) }}'); });
@endif
</script>
@endsection

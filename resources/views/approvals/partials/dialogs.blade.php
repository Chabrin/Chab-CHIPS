{{-- Bulk bar, review modal, confirm and reject dialogs shared by the approval queue pages. Expects $noun. --}}
{{-- Floating bulk bar --}}
<div class="sla-bulk" id="sla-bulk">
    <span class="count"><span id="sel-count">0</span> selected</span>
    <button type="button" class="bulk-btn ok" id="bulk-approve"><span class="material-symbols-outlined">check</span>Approve</button>
    <button type="button" class="bulk-btn bad" id="bulk-reject"><span class="material-symbols-outlined">close</span>Reject</button>
    <button type="button" class="bulk-btn ghost" id="bulk-clear" title="Clear selection"><span class="material-symbols-outlined">close</span></button>
</div>

{{-- Review modal --}}
<div class="modal fade sla-modal" id="slaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="sla-hero" id="slaHero">
                <button type="button" class="icon-btn close-x" data-bs-dismiss="modal" aria-label="Close"><span class="material-symbols-outlined">close</span></button>
                <div id="slaHeroInner"></div>
                <div class="m-tabs" id="mTabs"></div>
            </div>
            <div class="modal-body" id="slaModalBody">
                <div class="sla-empty">Loading…</div>
            </div>
            <div class="modal-footer">
                <div class="foot-note" id="foot-note"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-soft" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn-bad d-none" id="modal-reject">Reject</button>
                    <button type="button" class="btn-ok d-none" id="modal-approve">Approve</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Confirm modal --}}
<div class="modal fade small-modal" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <h5 class="mb-2" id="confirmActionModalTitle">Confirm</h5>
                <p class="text-muted mb-4" id="confirmActionModalBody"></p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-ok" id="confirmActionModalConfirmBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Rejection reason modal --}}
<div class="modal fade small-modal" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <h5 class="mb-1">Reject {{ $noun }}</h5>
                <p class="text-muted mb-3">Tell the submitter what needs to change.</p>
                <textarea id="rejectReasonInput" class="form-control" rows="3" maxlength="1000" placeholder="Reason (required)" style="border-radius:12px;"></textarea>
                <div class="invalid-feedback">Please give a reason.</div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-bad" id="rejectReasonConfirmBtn" style="background:var(--bad);color:#fff;border-color:var(--bad);">Reject</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Notice: shown when some or all items could not be approved or rejected, with the reasons --}}
<div class="modal fade small-modal" id="noticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="d-flex align-items-start gap-3">
                    <span class="notice-ico"><span class="material-symbols-outlined">block</span></span>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="noticeModalTitle">Not approved</h5>
                        <p class="text-muted mb-3" id="noticeModalLead"></p>
                        <div id="noticeModalList"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="button" class="btn-soft" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

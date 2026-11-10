{{-- Read-message popup shared by Sent, Logs and the batch page. Open it with cmOpenMessage(url) from _message_modal_js. --}}
<div class="modal fade cm-modal" id="cm_msg_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cmm_title">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cmm_loading" class="cm-empty" style="padding:40px 20px;"><span class="material-symbols-outlined cm-spin">progress_activity</span>Loading the message…</div>
                <div id="cmm_content" style="display:none;">
                    <dl class="cm-kv" id="cmm_meta"></dl>
                    <div class="cm-msg-card">
                        <div class="cm-msg-subject" id="cmm_subject"></div>
                        <div class="cm-msg-body" id="cmm_body"></div>
                    </div>
                    <div class="cm-warn-box" id="cmm_error" style="display:none;background:#fee2e2;border-color:#fecaca;color:#b91c1c;"></div>
                    <div class="cm-hint mt-2" id="cmm_note"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cm-btn cm-btn-light" id="cmm_copy"><span class="material-symbols-outlined">content_copy</span>Copy text</button>
                <button type="button" class="cm-btn cm-btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

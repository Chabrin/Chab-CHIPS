{{-- Bottom of every Add Staff tab. Save & Next keeps the form as a draft and opens the following tab; on the last tab it becomes
     Save Staff. Someone who cannot keep drafts only gets Next. public/assets/js/staff-form-steps.js decides which tab is last
     (System User is hidden for casual staff). --}}
<div class="staff-step-footer d-flex align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
    <small class="text-muted js-step-note"></small>
    <small class="js-draft-note" aria-live="polite"></small>
    <div class="ms-auto">
        {{-- Cancel is plain and sits left of the coloured buttons, as on every form. Leaving with unsaved typing asks first (staff-drafts.js). --}}
        <a href="{{ route('staff.index') }}" class="btn btn-light me-1">Cancel</a>
        @if (auth()->user()?->hasPermission('create_staff'))
        {{-- Keeps what is filled in so far, files included, on the server. public/assets/js/staff-drafts.js sends it. --}}
        <button type="button" class="btn btn-outline-secondary me-1 js-save-draft">
            <span class="material-symbols-outlined" style="font-size:19px;vertical-align:middle;">draft</span>
            Save as Draft
        </button>
        @endif
        <button type="button" class="btn btn-primary js-next-step">
            {{ auth()->user()?->hasPermission('create_staff') ? 'Save & Next' : 'Next Tab' }}
            <span class="material-symbols-outlined" style="font-size:19px;vertical-align:middle;">arrow_forward</span>
        </button>
        <button type="submit" class="btn btn-primary js-save-step d-none">
            <span class="material-symbols-outlined" style="font-size:19px;vertical-align:middle;">check_circle</span>
            Save Staff
        </button>
    </div>
</div>

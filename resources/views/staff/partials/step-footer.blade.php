{{-- Bottom of every Add Staff tab. Next opens the following tab; on the last tab it becomes Save Staff.
     public/assets/js/staff-form-steps.js decides which tab is last (System User is hidden for casual staff). --}}
<div class="staff-step-footer d-flex align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
    <small class="text-muted js-step-note"></small>
    <div class="ms-auto">
        <button type="button" class="btn btn-primary js-next-step">
            Next Tab 
            <span class="material-symbols-outlined" style="font-size:19px;vertical-align:middle;">arrow_forward</span>
        </button>
        <button type="submit" class="btn btn-primary js-save-step d-none">
            <span class="material-symbols-outlined" style="font-size:19px;vertical-align:middle;">check_circle</span>
            Save Staff
        </button>
    </div>
</div>

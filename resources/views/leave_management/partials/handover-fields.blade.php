<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-6">Work handover</legend>
    <p class="text-muted small">Choose a colleague and describe the work or items to hand over. They will receive a notification with your notes.</p>
    <div class="mb-3 handover-picker" data-handover-picker>
        <label for="{{ $prefix }}_handover_staff_id" class="form-label">Hand over to</label>
        <select id="{{ $prefix }}_handover_staff_id" name="handover_staff_id" class="form-select" autocomplete="off">
            <option value="" selected>Choose an employee...</option>
            @foreach($handoverStaff as $colleague)
                <option value="{{ $colleague->id }}">{{ $colleague->full_names }}</option>
            @endforeach
        </select>
        <div class="handover-picker__search" data-handover-search hidden>
            <div class="handover-picker__control">
                <svg class="handover-picker__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                    <circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4.5 4.5"></path>
                </svg>
                <input id="{{ $prefix }}_handover_employee_search" type="text" class="form-control"
                    placeholder="Search by employee name..." autocomplete="off" spellcheck="false"
                    role="combobox" aria-autocomplete="list" aria-expanded="false"
                    aria-controls="{{ $prefix }}_handover_employee_results"
                    aria-describedby="{{ $prefix }}_handover_employee_hint" data-handover-input>
                <button type="button" class="handover-picker__clear" aria-label="Clear handover employee" data-handover-clear hidden>Clear</button>
            </div>
            <div id="{{ $prefix }}_handover_employee_results" class="handover-picker__results" role="listbox" aria-label="Matching employees" data-handover-results hidden></div>
            <div id="{{ $prefix }}_handover_employee_hint" class="handover-picker__hint" role="status" aria-live="polite" data-handover-hint>Type at least 2 letters, then choose an employee from the results.</div>
        </div>
    </div>
    <label for="{{ $prefix }}_handover_notes" class="form-label">Handover notes</label>
    <textarea id="{{ $prefix }}_handover_notes" name="handover_notes" class="form-control" rows="4" maxlength="5000"
        placeholder="List the work, pending items, deadlines, and information your colleague will need."></textarea>
    <div class="form-text">Both a colleague and notes are required when handing work over. The list shows active colleagues with a linked system account. Your selected colleague, supervisor, and HR can read these notes.</div>
</fieldset>

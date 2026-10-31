<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-6">Relief Arrangements</legend>
    <p class="text-muted small">Choose the colleague who will cover your duties and describe the work they will handle. They will receive a notification to review and acknowledge these arrangements.</p>
    <div class="mb-3 handover-picker" data-handover-picker>
        <label for="{{ $prefix }}_handover_staff_id" class="form-label">Relief employee <span class="text-danger">*</span></label>
        <select id="{{ $prefix }}_handover_staff_id" name="handover_staff_id" class="form-select" autocomplete="off">
            <option value="" selected>Choose an employee...</option>
            @foreach($handoverStaff as $colleague)
                <option value="{{ $colleague->id }}" data-department-id="{{ $colleague->department_id }}" @disabled($prefix === 'hr')>{{ $colleague->full_names }}</option>
            @endforeach
        </select>
        <div class="handover-picker__search" data-handover-search hidden>
            <div class="handover-picker__control">
                <svg class="handover-picker__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                    <circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4.5 4.5"></path>
                </svg>
                <input id="{{ $prefix }}_handover_employee_search" type="text" class="form-control"
                    placeholder="Search your department by employee name..." autocomplete="off" spellcheck="false" required
                    role="combobox" aria-autocomplete="list" aria-expanded="false"
                    aria-controls="{{ $prefix }}_handover_employee_results"
                    aria-describedby="{{ $prefix }}_handover_employee_hint" data-handover-input>
                <button type="button" class="handover-picker__clear" aria-label="Clear relief employee" data-handover-clear hidden>Clear</button>
            </div>
            <div id="{{ $prefix }}_handover_employee_results" class="handover-picker__results" role="listbox" aria-label="Matching employees" data-handover-results hidden></div>
            <div id="{{ $prefix }}_handover_employee_hint" class="handover-picker__hint" role="status" aria-live="polite" data-handover-hint>Type at least 2 letters, then choose an employee from the results.</div>
        </div>
    </div>
    <label for="{{ $prefix }}_handover_notes" class="form-label">Relief duties and arrangements <span class="text-danger">*</span></label>
    <textarea id="{{ $prefix }}_handover_notes" name="handover_notes" class="form-control" rows="4" maxlength="5000" required
        placeholder="List the work, pending items, deadlines, and information your colleague will need."></textarea>
    <div class="form-text">Relief arrangements are required for a leave application. Choose a colleague from the applicant's department. The relief employee, supervisor, and HR can read the notes and files.</div>
    <div class="mt-3">
        <label for="{{ $prefix }}_handover_documents" class="form-label">Relief attachments <span class="text-muted small">(optional)</span></label>
        <input id="{{ $prefix }}_handover_documents" name="handover_documents[]" type="file" class="form-control" multiple aria-describedby="{{ $prefix }}_handover_file_hint">
        <div id="{{ $prefix }}_handover_file_hint" class="form-text">Any file format. Up to 5 files per handover, maximum 5 MB each, subject to the server's upload limit. Files are private and downloaded by authorised users.</div>
        <div id="{{ $prefix }}_handover_existing_files" class="form-text"></div>
    </div>
</fieldset>

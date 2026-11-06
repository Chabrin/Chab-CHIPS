@php
    $entrySections = [
        'bank' => ['Bank Details', 'Bank Account', 'account_balance', 'Add at least one bank account for client payments.', 'No bank accounts added yet', true, [
            ['bank_id', 'Bank', 'select', true, null],
            ['branch_id', 'Branch', 'select', false, null],
            ['account_name', 'Account Name', 'text', true, 255],
            ['account_number', 'Account Number', 'text', true, 50],
        ]],
        'nextofkin' => ['Next of Kin', 'Next of Kin', 'family_restroom', 'Add at least one next of kin for an individual client.', 'No next of kin added yet', true, [
            ['first_name', 'First Name', 'text', true, 255],
            ['last_name', 'Last Name', 'text', true, 255],
            ['second_name', 'Second Name', 'text', false, 255],
            ['relation', 'Relationship', 'select', false, null],
            ['mobile', 'Mobile Number', 'tel', false, 20],
            ['email', 'Email Address', 'email', false, 255],
            ['national_id', 'National ID', 'text', false, 50],
        ]],
        'contact' => ['Other Contacts', 'Contact', 'contact_phone', 'Add any other people the team may contact about this client. This section is optional.', 'No other contacts added yet', false, [
            ['names', 'Full Name', 'text', true, 255],
            ['mobile', 'Mobile Number', 'tel', false, 20],
            ['email', 'Email Address', 'email', false, 255],
        ]],
        'partner' => ['Partners', 'Partner', 'groups', 'Add the partners for this partnership or trust.', 'No partners added yet', false, [
            ['name', 'Name', 'text', true, 255],
            ['national_id', 'National ID', 'text', false, 50],
            ['kra_pin', 'KRA PIN', 'text', false, 50],
            ['phone', 'Phone', 'tel', false, 20],
        ]],
    ];
    [$entryTitle, $entryLabel, $entryIcon, $entryHelp, $entryEmpty, $entryRequired, $entryFields] = $entrySections[$kind];
@endphp
<section class="client-entry-section" data-client-repeater="{{ $kind }}" data-label="{{ $entryLabel }}" data-icon="{{ $entryIcon }}" data-empty="{{ $entryEmpty }}" aria-label="{{ $entryTitle }}">
    <div class="client-entry-heading">
        <div>
            <h2>{{ $entryTitle }} <span class="client-section-badge" @if($kind === 'nextofkin') data-kin-requirement @endif>{{ $entryRequired ? 'Required' : 'Optional' }}</span></h2>
            <p @if($kind === 'nextofkin') data-kin-help @endif>{{ $entryHelp }}</p>
        </div>
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" data-entry-action="add">
            <span class="material-symbols-outlined" aria-hidden="true">add</span> Add {{ $entryLabel }}
        </button>
    </div>
    <div class="client-entry-status" data-entry-status role="status" aria-live="polite"></div>
    <fieldset class="client-entry-editor" data-entry-editor hidden disabled>
        <legend class="visually-hidden">{{ $entryLabel }} details</legend>
        <h3 class="client-editor-heading"><span class="material-symbols-outlined" aria-hidden="true">{{ $entryIcon }}</span> <span data-editor-title>Add {{ $entryLabel }}</span></h3>
        <div class="client-editor-body">
            <p class="client-editor-help">Fields marked <span class="text-danger">*</span> are required. This entry is included when you save the client.</p>
            <div class="client-entry-errors" data-entry-errors role="alert" hidden></div>
            <div class="client-entry-fields">
                @foreach($entryFields as [$fieldKey, $fieldLabel, $fieldType, $fieldRequired, $fieldMax])
                <div class="client-entry-field">
                    <label for="client-{{ $kind }}-{{ $fieldKey }}">{{ $fieldLabel }} @if($fieldRequired)<span class="text-danger" aria-label="required">*</span>@else<span class="client-optional">(optional)</span>@endif</label>
                    @if($fieldType === 'select')
                    <select id="client-{{ $kind }}-{{ $fieldKey }}" class="form-select" data-entry-field="{{ $fieldKey }}" data-field-label="{{ $fieldLabel }}" @required($fieldRequired) aria-describedby="client-{{ $kind }}-{{ $fieldKey }}-error">
                        <option value="">Select {{ strtolower($fieldLabel) }}</option>
                        @if($fieldKey === 'relation')
                        @foreach(['Spouse', 'Parent', 'Sibling', 'Child', 'Other'] as $relationship)
                        <option value="{{ $relationship }}">{{ $relationship }}</option>
                        @endforeach
                        @endif
                    </select>
                    @else
                    <input id="client-{{ $kind }}-{{ $fieldKey }}" type="{{ $fieldType }}" class="form-control" data-entry-field="{{ $fieldKey }}" data-field-label="{{ $fieldLabel }}" @required($fieldRequired) @if($fieldMax) maxlength="{{ $fieldMax }}" @endif aria-describedby="client-{{ $kind }}-{{ $fieldKey }}-error">
                    @endif
                    <div class="client-field-error" id="client-{{ $kind }}-{{ $fieldKey }}-error" data-error-for="{{ $fieldKey }}" hidden></div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="client-editor-footer">
            <button type="button" class="btn btn-outline-primary" data-entry-action="cancel">Cancel</button>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-entry-action="save"><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span><span data-entry-save-label>Add {{ $entryLabel }}</span></button>
        </div>
    </fieldset>
    <div class="client-entry-list" data-entry-list aria-live="polite"><p class="client-loading">Loading {{ strtolower($entryTitle) }}…</p></div>
    <p class="client-entry-footnote">Entries added here become part of the client after Save Client succeeds.</p>
</section>

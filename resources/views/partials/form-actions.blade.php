{{--
    The button bar at the bottom of an HR form: Cancel (plain) on the left, Save (coloured) on the right, kept in view at the bottom
    of the screen while a long form is scrolled. The same on every sub-module. Put it inside the form, after the last field.
    public/assets/js/form-actions.js shows "Saving…" while a normal form is being sent and warns before leaving with unsaved changes.

    $form       id of the form it saves
    $cancelUrl  where Cancel goes (the list the form came from)
    $buttonId   id of the Save button (the page's own script may look for it); default saveButton
    $saveLabel  default Save
--}}
@php
    $buttonId = $buttonId ?? 'saveButton';
    $saveLabel = $saveLabel ?? 'Save';
@endphp
@once
<style>
    .form-actions-bar { position: sticky; bottom: 0; z-index: 20; display: flex; justify-content: flex-end; align-items: center; gap: .5rem; flex-wrap: wrap; margin: 1.5rem 0 0; padding: .75rem 0; background: #fff; border-top: 1px solid #e3e8ef; }
</style>
<script src="{{ asset('assets/js/form-actions.js') }}?v={{ filemtime(public_path('assets/js/form-actions.js')) }}"></script>
@endonce
<div class="form-actions-bar" data-form-actions-for="{{ $form }}">
    <a href="{{ $cancelUrl }}" class="btn btn-light">Cancel</a>
    <button type="submit" form="{{ $form }}" class="btn btn-primary d-inline-flex align-items-center" id="{{ $buttonId }}" data-form-actions-save>
        <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner" role="status" aria-hidden="true"></span>
        <span class="form-actions-label">{{ $saveLabel }}</span>
    </button>
</div>

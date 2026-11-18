@if (auth()->user()?->canUseStaffMessaging())
<li class="sm-header-item">
    <a class="sm-header-link" id="staff-messaging-link" href="{{ route('staff-messaging.index') }}"
       data-base-url="{{ url('/staff-messaging') }}" data-user-id="{{ auth()->id() }}" aria-label="Staff messages" title="Staff messages">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M4 4h16v12H8l-4 4V4Z" stroke-linejoin="round"/><path d="M8 8h8M8 12h5"/>
        </svg>
        <span class="sm-badge" id="staff-message-count" hidden></span>
    </a>
    <div class="sm-message-alert" id="staff-message-alert" role="status" aria-live="polite" hidden></div>
</li>
@endif

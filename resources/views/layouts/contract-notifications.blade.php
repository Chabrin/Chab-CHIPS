<li class="dropdown header-notifications">
    <button type="button" class="notification-bell-trigger" id="hr-notification-bell" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications" aria-controls="hr-notification-panel" title="Notifications"
        data-user-id="{{ auth()->id() }}" data-feed-url="{{ route('notifications.feed') }}"
        data-list-url="{{ route('notifications.index') }}" data-read-base="{{ url('/notifications') }}"
        data-fallback-url="{{ url('/dashboard') }}">
        <svg class="notification-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
            <path d="M10 21h4M12 2V1" />
        </svg>
        <span id="hr-notification-count" class="notification-bell-count d-none" aria-hidden="true"></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end notification-panel" id="hr-notification-panel" aria-labelledby="hr-notification-bell">
        <div class="notification-panel-header">
            <div>
                <h2>Notifications</h2>
                <p>Your latest updates</p>
            </div>
            <span id="hr-notification-summary" class="notification-panel-summary">Inbox</span>
        </div>
        <div id="hr-notification-list" class="notification-list" aria-live="polite" aria-busy="false">
            <div class="notification-state">Open to see your latest notifications.</div>
        </div>
        <div class="notification-panel-footer">Select a notification to view its details.</div>
    </div>
</li>
<script src="{{ asset('assets/js/notification-bell.js') }}?v={{ filemtime(public_path('assets/js/notification-bell.js')) }}" defer></script>

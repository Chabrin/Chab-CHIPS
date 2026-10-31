<li class="dropdown">
    <button type="button" class="btn btn-light position-relative" id="hr-notification-bell" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
        <span class="material-symbols-outlined">notifications</span>
        <span id="hr-notification-count" class="badge bg-danger position-absolute top-0 start-100 translate-middle d-none"></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-3" style="width:min(360px,90vw); max-height:420px; overflow-y:auto;">
        <strong>Notifications</strong>
        <div id="hr-notification-list" class="mt-2" aria-live="polite">Open to load notifications.</div>
    </div>
</li>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bell = document.getElementById('hr-notification-bell');
    const badge = document.getElementById('hr-notification-count');
    const list = document.getElementById('hr-notification-list');
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    function count(value) {
        badge.textContent = value > 99 ? '99+' : value;
        badge.classList.toggle('d-none', !value);
    }
    fetch(@json(route('notifications.unread-count')), { headers })
        .then(response => response.ok ? response.json() : Promise.reject())
        .then(data => count(typeof data === 'number' ? data : data.unread_count))
        .catch(() => {});

    bell.addEventListener('click', async function () {
        list.textContent = 'Loading…';
        try {
            const response = await fetch(@json(route('notifications.index')), { headers });
            if (!response.ok) throw new Error();
            const data = await response.json();
            count(data.unread_count);
            list.textContent = '';
            const items = data.notifications.data || [];
            if (!items.length) list.textContent = 'No notifications yet.';
            items.forEach(item => {
                const link = document.createElement('a');
                const target = new URL(item.action_url || @json(route('contract.index')), window.location.origin);
                link.href = target.origin === window.location.origin ? target.href : '#';
                link.className = 'd-block border-bottom py-2 text-decoration-none';
                const title = document.createElement('strong');
                title.className = 'd-block';
                title.textContent = (item.read_at ? '' : '• ') + item.title;
                const message = document.createElement('span');
                message.className = 'small text-muted';
                message.textContent = item.message || '';
                link.append(title, message);
                link.addEventListener('click', async function (event) {
                    event.preventDefault();
                    try {
                        await fetch(@json(url('/notifications')) + '/' + encodeURIComponent(item.id) + '/read', {
                            method: 'POST', headers: { ...headers, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                        });
                    } finally { window.location.assign(link.href); }
                });
                list.appendChild(link);
            });
        } catch (error) { list.textContent = 'Unable to load notifications. Please try again.'; }
    });
});
</script>

(function (root) {
    'use strict';

    function feedState(savedCursor) {
        let cursor = savedCursor !== null && Number.isSafeInteger(Number(savedCursor)) && Number(savedCursor) >= 0
            ? Number(savedCursor) : null;
        return {
            get cursor() { return cursor; },
            accept(data) {
                if (!Number.isSafeInteger(data.cursor) || data.cursor < 0 || !Array.isArray(data.notifications)) {
                    throw new Error('Invalid notification response');
                }
                const previous = cursor;
                const seen = new Set();
                const arrivals = data.notifications.filter(item => {
                    const id = Number(item.id);
                    if (previous === null || !Number.isSafeInteger(id) || id <= previous || seen.has(id) || item.read_at) return false;
                    seen.add(id);
                    return true;
                });
                cursor = Math.max(cursor || 0, data.cursor, ...arrivals.map(item => Number(item.id)));
                return arrivals;
            }
        };
    }

    function safeUrl(value, fallback, origin) {
        try {
            const url = new URL(value || fallback, origin);
            return url.origin === origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch (_) { return null; }
    }

    root.HrNotificationBell = { feedState, safeUrl };
    if (!root.document) return;
    root.document.addEventListener('DOMContentLoaded', function () {
        const document = root.document;
        const bell = document.getElementById('hr-notification-bell');
        if (!bell) return;
        const badge = document.getElementById('hr-notification-count');
        const list = document.getElementById('hr-notification-list');
        const summary = document.getElementById('hr-notification-summary');
        const toasts = document.getElementById('notification-toast-container');
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const storageKey = 'notification-cursor-' + bell.dataset.userId;
        let stored = null;
        try { stored = root.sessionStorage.getItem(storageKey); } catch (_) { /* storage may be disabled */ }
        const state = feedState(stored);
        let polling = false;
        let timer;

        function count(value) {
            const total = Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.classList.toggle('d-none', !total);
            bell.setAttribute('aria-label', total ? 'Notifications: ' + total + ' unread' : 'Notifications');
            if (summary) summary.textContent = total ? total + ' unread' : 'All read';
        }

        async function open(item, event) {
            event.preventDefault();
            const target = safeUrl(item.action_url, bell.dataset.fallbackUrl, root.location.origin);
            if (!target) return;
            try {
                const response = await root.fetch(bell.dataset.readBase + '/' + encodeURIComponent(item.id) + '/read', {
                    method: 'POST',
                    headers: { ...headers, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (response.ok) count((await response.json()).unread_count);
            } catch (_) { /* The user can still open the application if marking read fails. */ }
            root.location.assign(target);
        }

        function toast(item) {
            const box = document.createElement('div');
            box.className = 'notification-toast';
            box.setAttribute('role', 'status');
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close notification-toast-close';
            close.setAttribute('aria-label', 'Dismiss notification');
            close.addEventListener('click', () => box.remove());
            const title = document.createElement('strong');
            title.className = 'd-block';
            title.textContent = item.title || 'New notification';
            const message = document.createElement('div');
            message.className = 'notification-toast-message';
            message.textContent = item.message || '';
            box.append(close, title, message);
            if (safeUrl(item.action_url, bell.dataset.fallbackUrl, root.location.origin)) {
                const link = document.createElement('a');
                link.href = safeUrl(item.action_url, bell.dataset.fallbackUrl, root.location.origin);
                link.className = 'notification-toast-link';
                link.textContent = 'View details';
                link.addEventListener('click', event => open(item, event));
                box.appendChild(link);
            }
            toasts.appendChild(box);
            while (toasts.children.length > 3) toasts.firstElementChild.remove();
            root.setTimeout(() => box.remove(), 10000);
        }

        async function poll() {
            if (polling) return;
            root.clearTimeout(timer);
            if (document.hidden) {
                timer = root.setTimeout(poll, 15000);
                return;
            }
            polling = true;
            let more = false;
            try {
                const url = new URL(bell.dataset.feedUrl, root.location.origin);
                if (state.cursor !== null) url.searchParams.set('after_id', state.cursor);
                const response = await root.fetch(url.href, { headers, cache: 'no-store' });
                if (!response.ok) throw new Error('Unable to load notifications');
                const data = await response.json();
                const arrivals = state.accept(data);
                count(data.unread_count);
                arrivals.forEach(toast);
                try { root.sessionStorage.setItem(storageKey, String(state.cursor)); } catch (_) { /* optional */ }
                more = data.has_more === true;
            } catch (_) { /* Keep the last cursor and retry without repeating delivered toasts. */ }
            finally {
                polling = false;
                timer = root.setTimeout(poll, more ? 0 : 15000);
            }
        }

        function showListState(icon, titleText, messageText) {
            list.textContent = '';
            const box = document.createElement('div');
            box.className = 'notification-state';
            const symbol = document.createElement('span');
            symbol.className = 'material-symbols-outlined';
            symbol.setAttribute('aria-hidden', 'true');
            symbol.textContent = icon;
            const title = document.createElement('strong');
            title.textContent = titleText;
            const message = document.createElement('span');
            message.textContent = messageText;
            box.append(symbol, title, message);
            list.appendChild(box);
        }

        bell.addEventListener('click', async function () {
            list.setAttribute('aria-busy', 'true');
            showListState('notifications', 'Loading your notifications', 'Just a moment...');
            try {
                const response = await root.fetch(bell.dataset.listUrl, { headers, cache: 'no-store' });
                if (!response.ok) throw new Error();
                const data = await response.json();
                count(data.unread_count);
                list.textContent = '';
                const items = data.notifications.data || [];
                if (!items.length) showListState('notifications_none', 'You are all caught up', 'New notifications will appear here.');
                items.forEach(item => {
                    const target = safeUrl(item.action_url, bell.dataset.fallbackUrl, root.location.origin);
                    const link = document.createElement(target ? 'a' : 'div');
                    link.className = 'notification-item ' + (item.read_at ? 'is-read' : 'is-unread');
                    if (target) {
                        link.href = target;
                        link.addEventListener('click', event => open(item, event));
                    }
                    const title = document.createElement('strong');
                    title.className = 'notification-item-title';
                    title.textContent = item.title || 'Notification';
                    const message = document.createElement('span');
                    message.className = 'notification-item-message';
                    message.textContent = item.message || '';
                    link.append(title, message);
                    const createdAt = item.created_at ? new Date(item.created_at) : null;
                    if (createdAt && !Number.isNaN(createdAt.getTime())) {
                        const meta = document.createElement('span');
                        meta.className = 'notification-item-meta';
                        meta.textContent = (item.read_at ? '' : 'Unread · ') + createdAt.toLocaleString(undefined, {
                            month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
                        });
                        link.appendChild(meta);
                    }
                    list.appendChild(link);
                });
            } catch (_) {
                showListState('cloud_off', 'Unable to load notifications', 'Close this panel and try again.');
            } finally {
                list.setAttribute('aria-busy', 'false');
            }
        });
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
        root.addEventListener('focus', poll);
        poll();
    });
})(typeof window === 'undefined' ? globalThis : window);

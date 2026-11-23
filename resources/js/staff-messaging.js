const headerLink = document.getElementById('staff-messaging-link');

// crypto.randomUUID() only exists on HTTPS or localhost pages, so build a v4 UUID
// from getRandomValues (available everywhere) when it is missing, e.g. on http://*.test.
function newClientToken() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

if (headerLink) {
    initializeStaffMessaging(headerLink);
}

function initializeStaffMessaging(link) {
    const userId = Number(link.dataset.userId);
    const baseUrl = link.dataset.baseUrl;
    const badge = document.getElementById('staff-message-count');
    const alert = document.getElementById('staff-message-alert');
    const page = document.querySelector('[data-staff-messaging]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let unreadRequest = 0;
    let alertTimeout;
    let unreadTimer;

    function displayUnread(count) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.hidden = count === 0;
        link.setAttribute('aria-label', `Staff messages, ${count} unread`);
    }

    function scheduleUnreadRefresh() {
        clearTimeout(unreadTimer);
        unreadTimer = setTimeout(refreshUnread, 100);
    }

    async function api(path, { method = 'GET', data, params } = {}) {
        const response = await window.axios({
            url: baseUrl + path,
            method,
            data,
            params,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        });
        return response.data;
    }

    async function refreshUnread() {
        const version = ++unreadRequest;
        try {
            const result = await api('/unread-count');
            if (version !== unreadRequest) return;
            displayUnread(result.unread_count);
        } catch (error) {
            if (error.response?.status === 403) {
                badge.hidden = true;
            }
        }
    }

    function showIncomingAlert(event) {
        clearTimeout(alertTimeout);
        alert.replaceChildren();
        const destination = document.createElement('a');
        destination.href = baseUrl + '?conversation=' + event.conversation_id;
        destination.textContent = 'You have a new staff message. Open messages';
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.textContent = '×';
        dismiss.setAttribute('aria-label', 'Dismiss message alert');
        dismiss.addEventListener('click', () => { alert.hidden = true; });
        alert.append(destination, dismiss);
        alert.hidden = false;
        alertTimeout = setTimeout(() => { alert.hidden = true; }, 12000);
    }

    if (!page) refreshUnread();
    window.addEventListener('chips:staff-unread-refresh', scheduleUnreadRefresh);
    window.addEventListener('chips:staff-count', event => {
        ++unreadRequest;
        displayUnread(event.detail);
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !page) scheduleUnreadRefresh();
    });

    if (page) initializeInbox(page, userId, api);

    if (!window.Echo) {
        const status = document.getElementById('sm-live-status');
        if (status) status.textContent = 'Live updates unavailable. Refresh to check for new messages.';
        return;
    }

    const channel = window.Echo.private('staff-messaging.user.' + userId);
    channel.subscribed(() => {
        if (!page) scheduleUnreadRefresh();
        window.dispatchEvent(new CustomEvent('chips:staff-reconnected'));
    });
    channel.listen('.staff.messaging.updated', event => {
        const affectsUnread = (event.kind === 'message_created' && Number(event.actor_id) !== userId)
            || (event.kind === 'messages_read' && Number(event.actor_id) === userId);
        if (affectsUnread && (!page || page.dataset.listMode === 'staff')) scheduleUnreadRefresh();
        window.dispatchEvent(new CustomEvent('chips:staff-update', { detail: event }));
        const viewingConversation = !document.hidden
            && page?.dataset.activeConversation === String(event.conversation_id);
        if (event.kind === 'message_created' && Number(event.actor_id) !== userId && !viewingConversation) {
            showIncomingAlert(event);
        }
    });

    const connection = window.Echo.connector.pusher.connection;
    function updateConnectionStatus() {
        const status = document.getElementById('sm-live-status');
        if (!status) return;
        const connected = connection.state === 'connected';
        status.dataset.connected = String(connected);
        status.textContent = connected ? 'Live updates connected' : 'Live updates reconnecting…';
    }
    connection.bind('state_change', updateConnectionStatus);
    channel.error(() => {
        const status = document.getElementById('sm-live-status');
        if (status) status.textContent = 'Live access unavailable. Refresh CHIPS to retry.';
    });
    updateConnectionStatus();

}

function initializeInbox(page, userId, api) {
    const element = id => document.getElementById(id);
    const list = element('sm-list');
    const listStatus = element('sm-list-status');
    const moreList = element('sm-more-list');
    const searchArea = element('sm-staff-search-area');
    const search = element('sm-staff-search');
    const inboxTab = element('sm-inbox-tab');
    const staffTab = element('sm-staff-tab');
    const messageList = element('sm-messages');
    const history = element('sm-history');
    const older = element('sm-older');
    const historyStatus = element('sm-history-status');
    const composer = element('sm-compose');
    const body = element('sm-body');
    const sendButton = element('sm-send');
    const attachButton = element('sm-attach');
    const fileInput = element('sm-files');
    const draftFiles = element('sm-draft-files');
    const errorBox = element('sm-error');
    let mode = 'inbox';
    let listPage = 1;
    let listVersion = 0;
    let entries = [];
    let current = null;
    let searchTimeout;
    let inboxTimer;
    let listBusy = false;
    let listPending = false;

    function fileSize(bytes) {
        return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`;
    }

    function renderDraftFiles() {
        draftFiles.replaceChildren();
        draftFiles.hidden = !current?.files.length;
        attachButton.disabled = !current?.canSend || current.sending;
        for (const file of current?.files ?? []) {
            const item = document.createElement('li');
            const name = document.createElement('span');
            name.textContent = `${file.name} · ${fileSize(file.size)}`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.disabled = current.sending;
            remove.addEventListener('click', () => {
                current.files = current.files.filter(candidate => candidate !== file);
                renderDraftFiles();
                attachButton.focus();
            });
            item.append(name, remove);
            draftFiles.append(item);
        }
    }

    attachButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        const picked = Array.from(fileInput.files);
        fileInput.value = '';
        if (!picked.length || !current?.canSend || current.sending) return;
        const files = [...current.files, ...picked];
        const max = Number(composer.dataset.fileMax);
        const invalid = picked.find(file => !/\.(pdf|docx|xlsx|jpe?g|png)$/i.test(file.name) || !file.size || file.size > max);
        if (invalid) {
            showError(new Error(`${invalid.name}: choose a nonempty PDF, Word, Excel, JPEG or PNG up to ${fileSize(max)}.`));
            return;
        }
        if (files.length > Number(composer.dataset.uploadCount) || files.reduce((sum, file) => sum + file.size, 0) > Number(composer.dataset.uploadMax)) {
            showError(new Error('These files exceed the limit for one upload. Send them in separate messages.'));
            return;
        }
        clearError();
        current.files = files;
        renderDraftFiles();
    });

    function resizeWorkspace() {
        const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
        const debugbar = document.querySelector('.phpdebugbar');
        const toolbarTop = debugbar?.getBoundingClientRect().top ?? viewportHeight;
        const overlayHeight = Math.max(0, viewportHeight - toolbarTop);
        document.body.style.setProperty('--sm-viewport-height', viewportHeight + 'px');
        document.body.style.setProperty('--sm-debugbar-height', overlayHeight + 'px');
    }
    resizeWorkspace();
    window.addEventListener('resize', resizeWorkspace);
    window.visualViewport?.addEventListener('resize', resizeWorkspace);
    window.addEventListener('load', () => {
        resizeWorkspace();
        const debugbar = document.querySelector('.phpdebugbar');
        if (debugbar) new ResizeObserver(resizeWorkspace).observe(debugbar);
    });

    function scheduleInboxRefresh() {
        if (mode !== 'inbox') return;
        if (listBusy) {
            listPending = true;
            return;
        }
        clearTimeout(inboxTimer);
        inboxTimer = setTimeout(() => loadList(), 100);
    }

    function showError(error) {
        const errors = error.response?.data?.errors;
        errorBox.textContent = error.response?.status === 413 ? 'This upload is too large. Try smaller files or send them separately.'
            : errors ? Object.values(errors).flat().join(' ')
            : error.response?.data?.message ?? error.message ?? 'The request failed. Please retry.';
        errorBox.hidden = false;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function renderList() {
        list.replaceChildren();
        for (const entry of entries) {
            const person = mode === 'staff' ? entry : entry.other_user;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sm-list-entry';
            if (mode === 'inbox') button.setAttribute('aria-current', String(entry.id === current?.id));
            const avatar = document.createElement('span');
            avatar.className = 'sm-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.textContent = person.name.trim().slice(0, 1).toUpperCase();
            const copy = document.createElement('span');
            copy.className = 'sm-entry-copy';
            const name = document.createElement('span');
            name.className = 'sm-entry-name';
            name.textContent = person.name;
            const preview = document.createElement('span');
            preview.className = 'sm-entry-preview';
            preview.textContent = mode === 'staff' ? person.role
                : entry.latest_message?.body || (entry.latest_message?.attachment_count ? 'Attachment' : 'No messages yet');
            copy.append(name, preview);
            button.append(avatar, copy);
            if (mode === 'inbox' && entry.unread_count > 0) {
                const count = document.createElement('span');
                count.className = 'sm-unread';
                count.textContent = entry.unread_count > 99 ? '99+' : entry.unread_count;
                button.append(count);
            }
            button.addEventListener('click', () => mode === 'staff' ? startConversation(person, button) : openConversation(entry.id));
            list.append(button);
        }
    }

    async function loadList(append = false) {
        const version = ++listVersion;
        const requestedMode = mode;
        const requestedPage = append ? listPage + 1 : 1;
        listStatus.textContent = 'Loading…';
        moreList.disabled = true;
        listBusy = true;
        try {
            const result = await api(requestedMode === 'staff' ? '/directory' : '/conversations', {
                params: { page: requestedPage, search: requestedMode === 'staff' ? search.value.trim() : undefined },
            });
            if (version !== listVersion || mode !== requestedMode) return;
            const incoming = requestedMode === 'staff' ? result.users : result.conversations;
            entries = append ? entries.concat(incoming) : incoming;
            listPage = requestedPage;
            if (requestedMode === 'inbox') {
                window.dispatchEvent(new CustomEvent('chips:staff-count', { detail: result.unread_count }));
            }
            moreList.hidden = !result.has_more;
            renderList();
            listStatus.textContent = entries.length ? '' : requestedMode === 'staff'
                ? 'No colleagues match your search.' : 'No conversations yet. Choose Find staff to begin.';
        } catch (error) {
            if (version !== listVersion) return;
            listStatus.textContent = 'Unable to load this list.';
            showError(error);
        } finally {
            if (version === listVersion) {
                moreList.disabled = false;
                listBusy = false;
                if (listPending) {
                    listPending = false;
                    scheduleInboxRefresh();
                }
            }
        }
    }

    function selectMode(nextMode) {
        mode = nextMode;
        page.dataset.listMode = mode;
        inboxTab.setAttribute('aria-pressed', String(mode === 'inbox'));
        staffTab.setAttribute('aria-pressed', String(mode === 'staff'));
        searchArea.hidden = mode !== 'staff';
        list.setAttribute('aria-label', mode === 'staff' ? 'Staff directory' : 'Conversations');
        entries = [];
        renderList();
        loadList();
        if (mode === 'staff') search.focus();
    }

    async function startConversation(person, button) {
        clearError();
        button.disabled = true;
        try {
            const result = await api('/conversations', { method: 'POST', data: { user_id: person.id } });
            selectMode('inbox');
            openConversation(result.conversation.id);
        } catch (error) {
            showError(error);
            button.disabled = false;
        }
    }

    function renderMessages(state, scrollToBottom) {
        if (state !== current) return;
        messageList.replaceChildren();
        const messages = Array.from(state.messages.values()).sort((a, b) => a.id - b.id);
        for (const message of messages) {
            const item = document.createElement('li');
            item.className = 'sm-message';
            item.dataset.own = String(Number(message.sender_id) === userId);
            const text = document.createElement('div');
            text.className = 'sm-message-body';
            text.textContent = message.body;
            for (const file of message.attachments ?? []) {
                const download = document.createElement('a');
                download.className = 'sm-file-download';
                download.href = file.url;
                download.textContent = `${file.name} · ${fileSize(file.size_bytes)}`;
                text.append(download);
            }
            const time = document.createElement('time');
            time.className = 'sm-message-time';
            time.dateTime = message.created_at;
            time.textContent = new Date(message.created_at).toLocaleString([], {
                month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
            });
            item.append(text, time);
            messageList.append(item);
        }
        historyStatus.textContent = messages.length ? '' : 'No messages yet. Say hello.';
        older.hidden = !state.hasOlder;
        if (scrollToBottom) history.scrollTop = history.scrollHeight;
    }

    async function markVisibleRead(state) {
        if (state !== current || document.hidden || !state.messages.size
            || page.dataset.activeConversation !== String(state.id)) return;
        const through = state.cursor;
        if (!through) return;
        if (through <= state.readThrough) return;
        const hasUnread = Array.from(state.messages.values()).some(message =>
            Number(message.sender_id) !== userId && !message.read_at && Number(message.id) <= through);
        if (!hasUnread) return;
        try {
            const result = await api(`/conversations/${state.id}/read`, { method: 'POST', data: { through_id: through } });
            state.readThrough = Math.max(state.readThrough, through);
            for (const message of state.messages.values()) {
                if (Number(message.sender_id) !== userId && Number(message.id) <= through) {
                    message.read_at = new Date().toISOString();
                }
            }
            window.dispatchEvent(new CustomEvent('chips:staff-count', { detail: result.unread_count }));
            const entry = entries.find(item => item.id === state.id);
            if (entry && mode === 'inbox') {
                entry.unread_count = Array.from(state.messages.values()).filter(message =>
                    Number(message.sender_id) !== userId && !message.read_at).length;
                renderList();
            }
        } catch (error) {
            if (state === current) showError(error);
        }
    }

    async function syncConversation(state) {
        if (state.loading) {
            state.pending = true;
            return;
        }
        state.loading = true;
        try {
            do {
                state.pending = false;
                const newest = state.cursor;
                const result = await api(`/conversations/${state.id}/messages`, {
                    params: newest ? { after_id: newest } : {},
                });
                if (state !== current) return;
                const nearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 120;
                for (const message of result.messages) state.messages.set(Number(message.id), message);
                if (result.messages.length) state.cursor = Number(result.messages.at(-1).id);
                if (!newest) state.hasOlder = result.has_more;
                state.canSend = result.conversation.can_send;
                element('sm-chat-title').textContent = result.conversation.other_user.name;
                element('sm-chat-subtitle').textContent = state.canSend
                    ? 'Only you and this colleague can access this conversation in CHIPS.'
                    : 'This colleague is currently unavailable for new messages.';
                body.disabled = !state.canSend;
                sendButton.disabled = !state.canSend || state.sending;
                renderDraftFiles();
                composer.hidden = false;
                renderMessages(state, !newest || nearBottom);
                if (newest && result.has_more) state.pending = true;
            } while (state.pending);
            await markVisibleRead(state);
        } catch (error) {
            if (state === current) {
                historyStatus.textContent = 'Unable to load messages.';
                showError(error);
            }
        } finally {
            state.loading = false;
            if (state.pending && state === current) syncConversation(state);
        }
    }

    function openConversation(id) {
        clearError();
        current = { id: Number(id), messages: new Map(), hasOlder: false, loading: false,
            pending: false, cursor: 0, readThrough: 0, canSend: false, sending: false, pendingSend: null, files: [] };
        page.dataset.activeConversation = String(current.id);
        element('sm-empty').hidden = true;
        element('sm-chat-title').textContent = 'Loading conversation…';
        element('sm-chat-subtitle').textContent = '';
        history.hidden = false;
        composer.hidden = true;
        messageList.replaceChildren();
        older.hidden = true;
        body.value = '';
        renderDraftFiles();
        sendButton.setAttribute('aria-label', 'Send message');
        sendButton.title = 'Send message';
        sendButton.removeAttribute('aria-busy');
        historyStatus.textContent = 'Loading messages…';
        renderList();
        const url = new URL(window.location.href);
        url.searchParams.set('conversation', current.id);
        window.history.replaceState(null, '', url);
        syncConversation(current);
    }

    older.addEventListener('click', async () => {
        const state = current;
        if (!state?.messages.size) return;
        older.disabled = true;
        const previousHeight = history.scrollHeight;
        const previousTop = history.scrollTop;
        try {
            const result = await api(`/conversations/${state.id}/messages`, {
                params: { before_id: Math.min(...state.messages.keys()) },
            });
            if (state !== current) return;
            for (const message of result.messages) state.messages.set(Number(message.id), message);
            state.hasOlder = result.has_more;
            renderMessages(state, false);
            history.scrollTop = previousTop + history.scrollHeight - previousHeight;
        } catch (error) {
            if (state === current) showError(error);
        } finally {
            older.disabled = false;
        }
    });

    composer.addEventListener('submit', async event => {
        event.preventDefault();
        const state = current;
        const text = body.value.trim();
        if (!state || state.sending || !state.canSend || (!text && !state.files.length)) return;
        clearError();
        state.sending = true;
        sendButton.disabled = true;
        renderDraftFiles();
        sendButton.setAttribute('aria-label', 'Sending message');
        sendButton.title = 'Sending message…';
        sendButton.setAttribute('aria-busy', 'true');
        if (state.pendingSend?.body !== text || state.pendingSend?.files !== state.files) {
            state.pendingSend = { body: text, files: state.files, client_token: newClientToken() };
        }
        try {
            const data = { body: text, client_token: state.pendingSend.client_token };
            const payload = state.files.length ? new FormData() : data;
            if (state.files.length) {
                Object.entries(data).forEach(([key, value]) => payload.append(key, value));
                state.files.forEach(file => payload.append('attachments[]', file));
            }
            const result = await api(`/conversations/${state.id}/messages`, { method: 'POST', data: payload });
            state.messages.set(Number(result.message.id), result.message);
            state.pendingSend = null;
            state.files = [];
            if (state === current) {
                if (body.value.trim() === text) body.value = '';
                renderMessages(state, true);
                body.focus();
                // Fetch any messages sent by the colleague during this request.
                syncConversation(state);
            }
            scheduleInboxRefresh();
        } catch (error) {
            if (state === current) showError(error);
        } finally {
            state.sending = false;
            if (state === current) {
                sendButton.disabled = !state.canSend;
                renderDraftFiles();
                sendButton.setAttribute('aria-label', 'Send message');
                sendButton.title = 'Send message';
                sendButton.removeAttribute('aria-busy');
            }
        }
    });

    body.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            composer.requestSubmit();
        }
    });
    inboxTab.addEventListener('click', () => selectMode('inbox'));
    staffTab.addEventListener('click', () => selectMode('staff'));
    element('sm-start').addEventListener('click', () => selectMode('staff'));
    element('sm-back').addEventListener('click', () => {
        delete page.dataset.activeConversation;
        selectMode('inbox');
    });
    moreList.addEventListener('click', () => loadList(true));
    search.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadList(), 250);
    });
    window.addEventListener('chips:staff-update', event => {
        if (event.detail.kind === 'message_created' && Number(event.detail.actor_id) === userId && current?.sending) return;
        if (event.detail.kind === 'message_created') scheduleInboxRefresh();
        if (event.detail.kind === 'messages_read' && Number(event.detail.actor_id) === userId) scheduleInboxRefresh();
        if (event.detail.kind === 'message_created' && current?.id === Number(event.detail.conversation_id)) {
            syncConversation(current);
        }
    });
    function catchUp() {
        if (document.hidden) return;
        scheduleInboxRefresh();
        if (current) syncConversation(current);
    }
    window.addEventListener('chips:staff-reconnected', catchUp);
    document.addEventListener('visibilitychange', catchUp);

    page.dataset.listMode = 'inbox';
    loadList();
    const initialId = Number(new URL(window.location.href).searchParams.get('conversation'));
    if (Number.isSafeInteger(initialId) && initialId > 0) openConversation(initialId);
}

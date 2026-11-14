@extends('layouts.main')

@section('body-class', 'staff-messaging-screen')

@section('content')
<div class="page-body">
    <main class="sm-page" data-staff-messaging>
        <div class="sm-page-heading">
            <div><h1>Messages</h1><p>Stay connected with your colleagues.</p></div>
            <span class="sm-live-status" id="sm-live-status" role="status">Connecting to live updates…</span>
        </div>
        <div id="sm-error" class="sm-error" role="alert" hidden></div>
        <div class="sm-workspace">
            <aside class="sm-sidebar" aria-label="Staff and conversations">
                <div class="sm-list-tabs">
                    <button type="button" id="sm-inbox-tab" aria-pressed="true">Conversations</button>
                    <button type="button" id="sm-staff-tab" aria-pressed="false">Find staff</button>
                </div>
                <div id="sm-staff-search-area" class="sm-search-area" hidden>
                    <label for="sm-staff-search">Search colleagues</label>
                    <input type="search" id="sm-staff-search" placeholder="Type a name…" maxlength="100" autocomplete="off">
                </div>
                <div id="sm-list" class="sm-conversation-list" aria-label="Conversations"></div>
                <p id="sm-list-status" class="sm-list-status" role="status">Loading conversations…</p>
                <button type="button" id="sm-more-list" class="sm-more" hidden>Load more</button>
            </aside>
            <section class="sm-chat" aria-label="Selected conversation">
                <header class="sm-chat-heading"><button type="button" id="sm-back" class="sm-back">← Conversations</button><h2 id="sm-chat-title">Your staff inbox</h2><p id="sm-chat-subtitle">Choose a conversation or find a colleague.</p></header>
                <div id="sm-empty" class="sm-empty"><span class="material-symbols-outlined" aria-hidden="true">forum</span><h3>A conversation starts here</h3><p>Find a colleague to send your first message.</p><button type="button" id="sm-start" class="sm-primary">Find staff</button></div>
                <div id="sm-history" class="sm-history" hidden>
                    <button type="button" id="sm-older" class="sm-more" hidden>Load earlier messages</button>
                    <ol id="sm-messages" class="sm-messages" aria-label="Message history"></ol>
                    <p id="sm-history-status" class="sm-list-status" role="status"></p>
                </div>
                <form id="sm-compose" class="sm-compose" data-file-max="{{ $uploadLimits['file'] }}" data-upload-max="{{ $uploadLimits['total'] }}" data-upload-count="{{ $uploadLimits['count'] }}" hidden>
                    <label for="sm-body">Message</label>
                    <input type="file" id="sm-files" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" multiple hidden>
                    <ul id="sm-draft-files" class="sm-draft-files" aria-label="Selected attachments" hidden></ul>
                    <div class="sm-compose-input">
                        <button type="button" class="sm-attach-icon" id="sm-attach" aria-label="Attach documents or images" title="Attach PDF, Word, Excel, JPEG or PNG">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m20.8 11.6-8.6 8.6a5 5 0 0 1-7.1-7.1l9.2-9.2a3.5 3.5 0 0 1 5 5l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/></svg>
                        </button>
                        <textarea id="sm-body" rows="2" maxlength="5000" placeholder="Write a message…" aria-describedby="sm-compose-hint"></textarea>
                        <button type="submit" class="sm-send-icon" id="sm-send" aria-label="Send message" title="Send message">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m22 2-7 20-4-9-9-4 20-7Z M22 2 11 13"/></svg>
                        </button>
                    </div>
                    <div class="sm-compose-footer"><span id="sm-compose-hint">Enter to send · Shift + Enter for a new line</span></div>
                </form>
            </section>
        </div>
    </main>
</div>
@endsection

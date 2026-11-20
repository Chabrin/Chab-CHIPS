{{--
    The place a History list is shown. It loads from $url (read only) when it is first needed:
    $lazy true  = when the tab with id "history-tab" is first opened (the staff profile)
    $lazy false = straight away (a contract page)
--}}
<div id="historyPanel" data-url="{{ $url }}">
    <p class="text-muted mb-0" data-history-loading>Loading history…</p>
</div>
<script>
    (function () {
        var panel = document.getElementById('historyPanel');
        var loaded = false;

        function load() {
            if (loaded) return;
            loaded = true;
            fetch(panel.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(function (response) {
                    if (!response.ok) throw new Error('status ' + response.status);
                    return response.text();
                })
                .then(function (html) { panel.innerHTML = html; })
                .catch(function () {
                    loaded = false;
                    panel.innerHTML = '<p class="text-danger mb-0">The history could not be loaded. Open the tab again to retry.</p>';
                });
        }

        @if($lazy ?? false)
        var tab = document.getElementById('history-tab');
        if (tab) {
            tab.addEventListener('shown.bs.tab', load);
        }
        @else
        load();
        @endif
    })();
</script>

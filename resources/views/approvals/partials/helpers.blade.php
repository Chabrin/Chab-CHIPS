{{-- Small rendering helpers (window.QH) shared by the approval queue pages. Load before the page config. --}}
<script>
window.QH = (function () {
    function esc(v) {
        return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }
    function dash(v) { return (v === null || v === undefined || v === '' || v === '-') ? '<span style="color:#94a3b8;">&mdash;</span>' : esc(v); }
    function money(v) { return v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function icon(name) { return '<span class="material-symbols-outlined">' + name + '</span>'; }
    function statusPill(id, label) {
        const cls = {1: 'pending', 5: 'approved', 41: 'rejected'}[id] || 'other';
        return '<span class="status ' + cls + '">' + esc(label) + '</span>';
    }
    const palette = [['#e0f0fc', '#1d6fa5'], ['#e4f6ec', '#0b6b35'], ['#fdf3dc', '#8a5a0a'], ['#f1e8fd', '#6b3fb0'], ['#fde8ec', '#b4233f'], ['#e3f6f6', '#0f7a7a']];
    function avatar(name, large) {
        if (!name || name === '-') { return '<span class="avatar' + (large ? ' lg' : '') + '" style="background:#eef2f7;color:#94a3b8;">?</span>'; }
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        const initials = ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
        let h = 0; for (const ch of String(name)) { h = (h * 31 + ch.charCodeAt(0)) >>> 0; }
        const c = palette[h % palette.length];
        return '<span class="avatar' + (large ? ' lg' : '') + '" style="background:' + c[0] + ';color:' + c[1] + ';">' + esc(initials) + '</span>';
    }
    function kv(rows) {
        return '<dl class="kv">' + rows.map(r => {
            const empty = r[1] === null || r[1] === undefined || r[1] === '' || r[1] === '-';
            return '<dt>' + esc(r[0]) + '</dt><dd class="' + (empty ? 'none' : '') + '">' + (empty ? 'Not provided' : esc(r[1])) + '</dd>';
        }).join('') + '</dl>';
    }
    function warn(text) { return '<div class="banner warn" style="margin:0;">' + icon('warning') + '<div>' + esc(text) + '</div></div>'; }
    /** Pass/fail summary for a list of {label, ok} checks. */
    function readiness(checks) {
        const failing = checks.filter(k => !k.ok);
        return { passed: checks.length - failing.length, total: checks.length, failing: failing, tone: failing.length === 0 ? 'good' : (failing.length <= 2 ? 'warn' : 'bad') };
    }
    /** Banner plus the checklist, for the Overview tab. */
    function checksBlock(checks) {
        const r = readiness(checks);
        // Checks flagged blocking:true stop the approval; blocking:false (or no flag) only warn.
        const blocking = r.failing.filter(f => f.blocking === true), advisory = r.failing.filter(f => f.blocking !== true);
        const names = list => esc(list.map(f => f.label).join('; '));
        let banner = '<div class="banner ok">' + icon('verified') + '<div>Everything we check looks complete.</div></div>';
        if (blocking.length) {
            banner = '<div class="banner bad">' + icon('block') + '<div>Cannot approve until: ' + names(blocking) + '.' + (advisory.length ? ' Also worth a look: ' + names(advisory) + '.' : '') + '</div></div>';
        } else if (advisory.length) {
            banner = '<div class="banner warn">' + icon('warning') + '<div>' + advisory.length + ' check' + (advisory.length === 1 ? '' : 's') + ' to look at before you approve: ' + names(advisory) + '.</div></div>';
        }
        const list = '<div class="checks">' + checks.map(k => '<div class="check ' + (k.ok ? 'ok' : (k.blocking === false ? 'warn' : 'bad')) + '"><span class="dot">' + icon(k.ok ? 'check' : 'priority_high') + '</span>' + esc(k.label) + '</div>').join('') + '</div>';
        return { banner: banner, list: list };
    }
    return { esc, dash, money, icon, statusPill, avatar, kv, warn, readiness, checksBlock };
})();
</script>

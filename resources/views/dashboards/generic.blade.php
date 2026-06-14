@extends('layouts.main')

@section('styles')
<style>
/* ── Shell ───────────────────────────────────────── */
.gen-dashboard { padding: 20px 0; }

/* ── Greeting / profile card ─────────────────────── */
.gen-profile-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 10px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.gen-profile-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 4px;
    background: #5b9bd5;
    border-radius: 10px 0 0 10px;
}
.gen-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #b7d7f0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gen-avatar .material-symbols-outlined {
    font-size: 26px !important;
    color: #0a0a0a !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.gen-profile-body { flex: 1; min-width: 0; }
.gen-profile-name {
    font-size: 16px;
    font-weight: 700;
    color: #0a0a0a;
    margin: 0 0 4px 0;
    line-height: 1.2;
}
.gen-profile-greeting {
    font-size: 12px;
    color: #6b7280;
    margin: 0 0 8px 0;
}
.gen-role-badges { display: flex; flex-wrap: wrap; gap: 6px; }
.gen-role-badge {
    font-size: 10px;
    font-weight: 600;
    color: #0a0a0a;
    background: #d9ebf7;
    border: 1px solid #b7d7f0;
    border-radius: 20px;
    padding: 2px 10px;
    text-transform: capitalize;
    letter-spacing: 0.02em;
}
.gen-date-badge {
    background: #b7d7f0;
    color: #0a0a0a;
    font-size: 11px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    align-self: flex-start;
}
.gen-date-badge .material-symbols-outlined { font-size: 14px !important; }

/* ── Section label ───────────────────────────────── */
.gen-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 12px;
}

/* ── Leave balance cards ─────────────────────────── */
.gen-leave-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.gen-leave-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 8px;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
}
.gen-leave-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: #b7d7f0;
    border-radius: 8px 8px 0 0;
}
.gen-leave-type {
    font-size: 11px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gen-leave-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
}
.gen-leave-stat-item { text-align: center; }
.gen-leave-stat-value {
    font-size: 18px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1;
    margin-bottom: 3px;
}
.gen-leave-stat-value.balance { color: #16a34a; }
.gen-leave-stat-value.used    { color: #6b7280; }
.gen-leave-stat-label {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
}
.gen-leave-divider {
    border: none;
    border-top: 1px solid #d9ebf7;
    margin: 8px 0;
}

/* Skeleton for leave cards */
.gen-skeleton {
    display: inline-block;
    background: linear-gradient(90deg, #d9ebf7 25%, #b7d7f0 50%, #d9ebf7 75%);
    background-size: 200% 100%;
    animation: gen-pulse 1.4s ease infinite;
    border-radius: 6px;
    vertical-align: bottom;
}
@keyframes gen-pulse {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Self-service links ──────────────────────────── */
.gen-links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (max-width: 575px) { .gen-links-grid { grid-template-columns: 1fr; } }

.gen-link-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 8px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none !important;
    color: inherit !important;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
    position: relative;
    overflow: hidden;
}
.gen-link-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: var(--link-accent, #b7d7f0);
    border-radius: 8px 0 0 8px;
}
.gen-link-card:hover {
    box-shadow: 0 2px 12px rgba(91,155,213,0.14);
    border-color: #b7d7f0;
}
.gen-link-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--link-icon-bg, #d9ebf7);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gen-link-icon .material-symbols-outlined {
    font-size: 20px !important;
    color: #0a0a0a !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.gen-link-body { flex: 1; min-width: 0; }
.gen-link-title {
    font-size: 13px;
    font-weight: 700;
    color: #0a0a0a;
    margin-bottom: 2px;
}
.gen-link-sub {
    font-size: 11px;
    color: #6b7280;
}
.gen-link-arrow {
    flex-shrink: 0;
    color: #b7d7f0 !important;
    font-size: 20px !important;
}

/* ── Divider ─────────────────────────────────────── */
.gen-divider { border: none; border-top: 1px solid #d9ebf7; margin: 4px 0 24px; }

/* ── No-data / loading state ─────────────────────── */
.gen-empty {
    text-align: center;
    padding: 24px 0;
    color: #9ca3af;
    font-size: 12px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="gen-dashboard">

            {{-- Profile / Greeting --}}
            <div class="gen-profile-card">
                <div class="gen-avatar">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <div class="gen-profile-body">
                    <p class="gen-profile-greeting">
                        {{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}
                    </p>
                    <h5 class="gen-profile-name">{{ Auth::user()->name }}</h5>
                    <div class="gen-role-badges" id="role-badges">
                        <span class="gen-skeleton" style="width:80px;height:18px;border-radius:20px;"></span>
                    </div>
                </div>
                <div class="gen-date-badge">
                    <span class="material-symbols-outlined">calendar_today</span>
                    {{ now()->format('d M Y') }}
                </div>
            </div>

            <hr class="gen-divider">

            {{-- Leave Balance --}}
            <div class="gen-section-label">Leave Balance</div>
            <div class="gen-leave-grid" id="leave-balance-grid">
                {{-- Skeleton placeholders --}}
                @for ($i = 0; $i < 3; $i++)
                <div class="gen-leave-card">
                    <div class="gen-leave-type"><span class="gen-skeleton" style="width:100px;height:12px;"></span></div>
                    <hr class="gen-leave-divider">
                    <div class="gen-leave-stats">
                        @for ($j = 0; $j < 3; $j++)
                        <div class="gen-leave-stat-item">
                            <div class="gen-leave-stat-value"><span class="gen-skeleton" style="width:28px;height:18px;"></span></div>
                            <div class="gen-leave-stat-label"><span class="gen-skeleton" style="width:36px;height:9px;margin-top:3px;"></span></div>
                        </div>
                        @endfor
                    </div>
                </div>
                @endfor
            </div>

            <hr class="gen-divider">

            {{-- Self-Service --}}
            <div class="gen-section-label">Self Service</div>
            <div class="gen-links-grid">

                <a href="/self-service/my-leave" class="gen-link-card" style="--link-accent:#5b9bd5; --link-icon-bg:#b7d7f0;">
                    <div class="gen-link-icon">
                        <span class="material-symbols-outlined">beach_access</span>
                    </div>
                    <div class="gen-link-body">
                        <div class="gen-link-title">My Leave</div>
                        <div class="gen-link-sub">View and apply for leave</div>
                    </div>
                    <span class="material-symbols-outlined gen-link-arrow">chevron_right</span>
                </a>

                <a href="/self-service/my-payslip" class="gen-link-card" style="--link-accent:#b7d7f0; --link-icon-bg:#d9ebf7;">
                    <div class="gen-link-icon">
                        <span class="material-symbols-outlined">receipt_long</span>
                    </div>
                    <div class="gen-link-body">
                        <div class="gen-link-title">My Payslip</div>
                        <div class="gen-link-sub">Download your payslips</div>
                    </div>
                    <span class="material-symbols-outlined gen-link-arrow">chevron_right</span>
                </a>

            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    loadGenericDashboard();
});

function loadGenericDashboard() {
    $.ajax({
        url: '{{ route("dashboard.generic.data") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                showError();
                return;
            }
            renderRoles(response.roles);
            renderLeaveBalance(response.leave_balances);
        },
        error: function () { showError(); }
    });
}

function renderRoles(roles) {
    var $el = $('#role-badges');
    if (!roles || roles.length === 0) {
        $el.html('<span class="gen-role-badge">No role assigned</span>');
        return;
    }
    var html = '';
    roles.forEach(function (r) {
        var label = r.display_name || r.name.replace(/_/g, ' ');
        html += '<span class="gen-role-badge">' + label + '</span>';
    });
    $el.html(html);
}

function renderLeaveBalance(balances) {
    var $grid = $('#leave-balance-grid');

    if (!balances || balances.length === 0) {
        $grid.html(leaveCardHtml('—', 0, 0, 0));
        return;
    }

    var html = '';
    balances.forEach(function (item) {
        var entitled = parseFloat(item.entitled_days) || 0;
        var balance  = parseFloat(item.balance)       || 0;
        var used     = Math.max(0, parseFloat(item.used_days) || 0);
        html += leaveCardHtml(item.type_name, entitled, used, balance);
    });

    $grid.html(html);
}

function leaveCardHtml(typeName, entitled, used, balance, isError) {
    var errIcon = '<span class="material-symbols-outlined" style="font-size:16px;color:#dc3545;vertical-align:middle;" title="Failed to load">error</span>';
    var e = isError ? errIcon : entitled;
    var u = isError ? errIcon : used;
    var b = isError ? errIcon : balance;

    return '<div class="gen-leave-card">' +
        '<div class="gen-leave-type">' + escHtml(typeName) + '</div>' +
        '<hr class="gen-leave-divider">' +
        '<div class="gen-leave-stats">' +
            '<div class="gen-leave-stat-item">' +
                '<div class="gen-leave-stat-value">' + e + '</div>' +
                '<div class="gen-leave-stat-label">Entitled</div>' +
            '</div>' +
            '<div class="gen-leave-stat-item">' +
                '<div class="gen-leave-stat-value used">' + u + '</div>' +
                '<div class="gen-leave-stat-label">Used</div>' +
            '</div>' +
            '<div class="gen-leave-stat-item">' +
                '<div class="gen-leave-stat-value balance">' + b + '</div>' +
                '<div class="gen-leave-stat-label">Balance</div>' +
            '</div>' +
        '</div>' +
    '</div>';
}

function showError() {
    $('#role-badges').html('<span style="font-size:12px;color:#dc3545;">—</span>');
    $('#leave-balance-grid').html(
        leaveCardHtml('Annual Leave', 0, 0, 0, true) +
        leaveCardHtml('Sick Leave',   0, 0, 0, true) +
        leaveCardHtml('Other Leave',  0, 0, 0, true)
    );
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
@endsection

@extends('layouts.main')

@section('styles')


<style>

    :root {
    /* Brand Identity */
    --cb-primary: #1e5ba8;          /* Classic Chabrin corporate blue */
    --cb-primary-light: #eef4fc;    /* Light tinted background */
    --cb-primary-hover: #164684;
    
    /* Functional Accents */
    --cb-navy: #0f253e;             /* Heavy contrast text / charts */
    --cb-teal: #0d9488;             /* Positive / growth / units */
    --cb-amber: #e67e22;            /* Returns Advance / warnings */
    --cb-amber-light: #fef5ea;
    --cb-purple: #6366f1;           /* People / staffing */
    --cb-purple-light: #f3f4ff;

    /* Neutrals & Surfaces */
    --cb-surface: #ffffff;
    --cb-bg: #f8fafc;               /* Modern cool slate instead of stark white */
    --cb-border: #e2e8f0;           /* Clean, subtle gray borders */
    --cb-border-subtle: #edf2f7;
    --cb-text-main: #1e293b;        /* Slate 800 — softer and richer than #0a0a0a */
    --cb-text-muted: #64748b;       /* Slate 500 */
    
    /* Elevation */
    --cb-shadow-sm: 0 1px 2px rgba(15, 37, 62, 0.04);
    --cb-shadow-md: 0 4px 12px -2px rgba(15, 37, 62, 0.08), 0 2px 4px -2px rgba(15, 37, 62, 0.04);
    --cb-shadow-hover: 0 10px 20px -3px rgba(30, 91, 168, 0.12), 0 4px 6px -2px rgba(15, 37, 62, 0.05);
}
/* ── Shell ───────────────────────────────────────── */
.dir-dashboard { padding: 20px 0; }

/* ── Greeting ────────────────────────────────────── */
/* Modern Header Bar */
/* ── Hero Greeting Banner ────────────────────────── */
.dir-greeting {
    background: linear-gradient(135deg, #0d233a 0%, #174271 60%, #1e5ba8 100%);
    padding: 24px 28px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: nowrap; /* Keeps date pill on the right */
    gap: 16px;
    box-shadow: 0 4px 16px rgba(13, 35, 58, 0.12);
}

.dir-greeting-left h4 {
    font-size: 20px;
    font-weight: 700;
    color: #ffffff !important;
    margin: 0 0 4px 0;
    letter-spacing: -0.01em;
}

.dir-greeting-left p {
    font-size: 13px;
    color: #bfdbfe !important; /* Soft legible blue */
    margin: 0;
}

/* Elegant floating date badge */
.dir-date-badge {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    flex-shrink: 0;
}

.dir-date-badge .material-symbols-outlined {
    font-size: 16px !important;
    color: #93c5fd !important;
}

/* Hide the old harsh horizontal rule */
.dir-divider {
    display: none;
}

/* ── Section label ───────────────────────────────── */
.dir-section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cb-navy);
    margin: 28px 0 14px;
}

.dir-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--cb-border);
}

/* ── 3-column stat grid ──────────────────────────── */
.dir-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
/* ── 4-column portfolio grid ─────────────────────── */
.dir-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
@media (max-width: 991px) {
    .dir-grid-3, .dir-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 575px) {
    .dir-grid-3, .dir-grid-4 { grid-template-columns: 1fr; }
}

/* ── Stat card ───────────────────────────────────── */
/* Stat Card Shell */
/* ── Section Header Labels ───────────────────────── */
.dir-section-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #1e293b;
    margin: 24px 0 12px;
}
.dir-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e2e8f0;
}

/* ── Upgraded Stat Card Shell ─────────────────────── */
.dir-stat-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(15, 37, 62, 0.04);
}

.dir-stat-card::before {
    display: none; /* Removed the old harsh left-edge strip */
}

.dir-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -3px rgba(15, 37, 62, 0.1), 0 3px 6px -2px rgba(15, 37, 62, 0.05);
    border-color: #cbd5e1;
}

/* Modern Rounded Icon Badge */
.dir-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--card-icon-bg, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.18s ease;
}

.dir-stat-card:hover .dir-stat-icon {
    transform: scale(1.08);
}

.dir-stat-icon .material-symbols-outlined {
    font-size: 22px !important;
    color: var(--card-accent, #1e5ba8) !important;
    font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
}

.dir-stat-body {
    flex: 1;
    min-width: 0;
}

.dir-stat-number {
    font-size: 22px;
    font-weight: 700;
    color: #0f253e;
    line-height: 1.15;
    margin-bottom: 2px;
    letter-spacing: -0.02em;
}

.dir-stat-label {
    font-size: 11.5px;
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Returns Advance Card (Finance Banner) ────────── */
.dir-advance-card {
    background: linear-gradient(135deg, #ffffff 0%, #fffbf5 100%);
    border-radius: 12px;
    border: 1px solid #fed7aa;
    padding: 14px 18px;
    position: relative;
    overflow: hidden;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(230, 126, 34, 0.08);
}

.dir-advance-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
    background: #ea580c;
    border-radius: 12px 0 0 12px;
}

.dir-advance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -3px rgba(234, 88, 12, 0.15);
}

.dir-advance-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #ffedd5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dir-advance-icon .material-symbols-outlined {
    font-size: 22px !important;
    color: #ea580c !important;
    font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
}

.dir-advance-body {
    flex: 1;
    min-width: 0;
}

.dir-advance-amount {
    font-size: 20px;
    font-weight: 700;
    color: #9a3412;
    line-height: 1.15;
    margin-bottom: 2px;
    letter-spacing: -0.01em;
}

.dir-advance-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #c2410c;
}

/* ── Skeleton ────────────────────────────────────── */
.dir-skeleton {
    display: inline-block;
    height: 20px;
    background: linear-gradient(90deg, #d9ebf7 25%, #b7d7f0 50%, #d9ebf7 75%);
    background-size: 200% 100%;
    animation: dir-pulse 1.4s ease infinite;
    border-radius: 6px;
    vertical-align: bottom;
}
.dir-skeleton-sm   { width: 36px; }
.dir-skeleton-md   { width: 60px; }
.dir-skeleton-wide { width: 130px; height: 22px; }
@keyframes dir-pulse {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Chart cards ─────────────────────────────────── */
.dir-chart-card { background: #fff; border: 1px solid #d9ebf7; border-radius: 8px; overflow: hidden; }
.dir-chart-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #0a0a0a;
    border-bottom: 1px solid #d9ebf7;
    background: #f7fbff;
}
.dir-chart-header .material-symbols-outlined { font-size: 16px !important; color: #5b9bd5 !important; }
.dir-chart-body { padding: 14px; display: flex; align-items: center; gap: 20px; }
.dir-chart-wrap { position: relative; width: 130px; height: 130px; flex-shrink: 0; }
.dir-chart-wrap canvas { width: 130px !important; height: 130px !important; }
.dir-chart-loading {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.8);
}
.dir-spinner {
    width: 24px; height: 24px;
    border: 3px solid #d9ebf7;
    border-top-color: #5b9bd5;
    border-radius: 50%;
    animation: dir-spin 0.7s linear infinite;
}
@keyframes dir-spin { to { transform: rotate(360deg); } }
.dir-chart-legend { flex: 1; display: flex; flex-direction: column; gap: 10px; }
.dir-legend-item { display: flex; align-items: center; gap: 8px; }
.dir-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dir-legend-label { font-size: 11px; color: #6b7280; line-height: 1.2; }
.dir-legend-value { font-size: 13px; font-weight: 600; color: #0a0a0a; margin-top: 1px; }

/* ── Divider ─────────────────────────────────────── */
.dir-divider { border: none; border-top: 1px solid #d9ebf7; margin: 4px 0 24px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="dir-dashboard">

            {{-- Greeting --}}
            <div class="dir-greeting">
                <div class="dir-greeting-left">
                    <h4>{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ Auth::user()->name }}</h4>
                    <p>Company dashboard — all divisions at a glance.</p>
                </div>
                <div class="dir-date-badge">
                    <span class="material-symbols-outlined">calendar_today</span>
                    {{ now()->format('l, d F Y') }}
                </div>
            </div>

            <hr class="dir-divider">

            {{-- ── Charts ── --}}
            <div class="dir-section-label">Occupancy &amp; Collection</div>
            <div class="row g-3">

                <div class="col-md-4">
                    <div class="dir-chart-card">
                        <div class="dir-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Unit Occupancy
                        </div>
                        <div class="dir-chart-body">
                            <div class="dir-chart-wrap">
                                <canvas id="chartOccupancy"></canvas>
                                <div class="dir-chart-loading" id="loadingOccupancy"><div class="dir-spinner"></div></div>
                            </div>
                            <div class="dir-chart-legend" id="legendOccupancy"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="dir-chart-card">
                        <div class="dir-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Overall Collection
                        </div>
                        <div class="dir-chart-body">
                            <div class="dir-chart-wrap">
                                <canvas id="chartOverall"></canvas>
                                <div class="dir-chart-loading" id="loadingOverall"><div class="dir-spinner"></div></div>
                            </div>
                            <div class="dir-chart-legend" id="legendOverall"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="dir-chart-card">
                        <div class="dir-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            {{ now()->format('F Y') }} Collection
                        </div>
                        <div class="dir-chart-body">
                            <div class="dir-chart-wrap">
                                <canvas id="chartMonth"></canvas>
                                <div class="dir-chart-loading" id="loadingMonth"><div class="dir-spinner"></div></div>
                            </div>
                            <div class="dir-chart-legend" id="legendMonth"></div>
                        </div>
                    </div>
                </div>

            </div>

            <hr class="dir-divider">

            {{-- ── People ── --}}
            <div class="dir-section-label">People</div>
            <div class="row g-3 mb-3">

                <div class="col-lg-3 col-sm-6">
                   <div class="dir-stat-card" style="--card-accent:#6366f1; --card-icon-bg:#eef2ff;">
                        <div class="dir-stat-icon"><span class="material-symbols-outlined">groups</span></div>
                        <div class="dir-stat-body">
                            <div class="dir-stat-number" id="stat-employees"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                            <div class="dir-stat-label">Total Employees</div>
                        </div>
                    </div>  
                </div>

                <div class="col-lg-3 col-sm-6">
                    <div class="dir-stat-card" style="--card-accent:#4f46e5; --card-icon-bg:#e0e7ff;">
                        <div class="dir-stat-icon"><span class="material-symbols-outlined">person_pin</span></div>
                        <div class="dir-stat-body">
                            <div class="dir-stat-number" id="stat-field-officers"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                            <div class="dir-stat-label">Field Officers</div>
                        </div>
                    </div>
                 </div>

                <div class="col-lg-3 col-sm-6">
                    <div class="dir-stat-card" style="--card-accent:#8b5cf6; --card-icon-bg:#f5f3ff;">
                        <div class="dir-stat-icon"><span class="material-symbols-outlined">event_busy</span></div>
                        <div class="dir-stat-body">
                            <div class="dir-stat-number" id="stat-on-leave"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                            <div class="dir-stat-label">On Leave Today</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6">
                    <div class="dir-stat-card" style="--card-accent:#7c3aed; --card-icon-bg:#ede9fe;">
                        <div class="dir-stat-icon"><span class="material-symbols-outlined">manage_accounts</span></div>
                        <div class="dir-stat-body">
                            <div class="dir-stat-number" id="stat-zone-managers"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                            <div class="dir-stat-label">
                                <a id="zone-managers-link" href="#" style="text-decoration:underline;color:#7c3aed;font-size:11.5px;font-weight:600;">Zone Managers</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── Portfolio ── --}}
            <div class="dir-section-label">Portfolio</div>
            <div class="dir-grid-4">

               <div class="dir-stat-card" style="--card-accent:#0284c7; --card-icon-bg:#e0f2fe;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">business</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-clients"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Clients</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#0ea5e9; --card-icon-bg:#f0f9ff;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">apartment</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-properties"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Properties</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#1e5ba8; --card-icon-bg:#eef4fc;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">domain</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-units"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Units</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#0d9488; --card-icon-bg:#ccfbf1;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">people</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-tenants"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Tenants</div>
                    </div>
                </div>

            </div>

            {{-- ── Finance ── --}}
            <div class="dir-section-label">Finance</div>
            <div class="dir-grid-3">

                {{-- Returns Advance (highlighted) --}}
                <div class="dir-advance-card">
                    <div class="dir-advance-icon">
                        <span class="material-symbols-outlined">account_balance_wallet</span>
                    </div>
                    <div class="dir-advance-body">
                        <div class="dir-advance-amount" id="stat-advance"><span class="dir-skeleton dir-skeleton-wide"></span></div>
                        <div class="dir-advance-label">Returns Advance (Unrecovered)</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#059669; --card-icon-bg:#ecfdf5;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">book_2</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-leaves-open"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Cheque Leaves – Open Books</div>
                    </div>
                </div>

               <div class="dir-stat-card" style="--card-accent:#64748b; --card-icon-bg:#f1f5f9;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">menu_book</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-leaves-closed"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Cheque Leaves – Closed Books</div>
                    </div>
                </div>

            </div>

            @include('dashboards.leave-overview')
            @include('dashboards.contract-expiry-overview')
        </div>
    </div>
</div>

{{-- Zone Managers Modal --}}
<div class="modal fade" id="zoneManagersModal" tabindex="-1" aria-labelledby="zoneManagersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:10px;border:1px solid #d9ebf7;">
            <div class="modal-header" style="padding:10px 14px;border-bottom:1px solid #d9ebf7;background:#f7fbff;">
                <h6 class="modal-title" id="zoneManagersModalLabel" style="font-size:13px;font-weight:700;color:#0a0a0a;margin:0;">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;color:#5b9bd5;">manage_accounts</span>
                    Zone Managers
                </h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px;" id="zoneManagersModalBody">
                <div class="text-center py-3">
                    <div class="dir-spinner" style="margin:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var dirZoneManagersData = null;

$(document).ready(function () {
    loadDirectorStats();
    loadDirectorCollection();
    loadDirectorZoneManagers();

    $('#zone-managers-link').on('click', function (e) {
        e.preventDefault();
        openZoneManagersModal();
    });
});

// ── Stats ──────────────────────────────────────────
function loadDirectorStats() {
    $.ajax({
        url: '{{ route("dashboard.director.stats") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) { showStatsError(); return; }
            var s = response.stats;

            animateCount('stat-employees',     s.total_employees);
            animateCount('stat-field-officers', s.field_officers);
            animateCount('stat-on-leave',       s.on_leave_today);

            animateCount('stat-clients',    s.total_clients);
            animateCount('stat-properties', s.total_properties);
            animateCount('stat-units',      s.total_units);
            animateCount('stat-tenants',    s.total_tenants);

            $('#stat-advance').text(formatMoney(s.returns_advance));
            animateCount('stat-leaves-open',   s.leaves_in_open_books);
            animateCount('stat-leaves-closed', s.leaves_in_closed_books);

            renderOccupancyChart(s.occupied_units, s.vacant_units, s.total_units);
        },
        error: function () { showStatsError(); }
    });
}

function animateCount(id, target) {
    var $el = $('#' + id), duration = 900, startTime = null;
    $el.html('<span class="count-val">0</span>');
    function step(ts) {
        if (!startTime) startTime = ts;
        var p    = Math.min((ts - startTime) / duration, 1);
        var ease = 1 - Math.pow(1 - p, 3);
        $el.find('.count-val').text(Math.floor(ease * target).toLocaleString());
        if (p < 1) requestAnimationFrame(step);
        else $el.find('.count-val').text(target.toLocaleString());
    }
    requestAnimationFrame(step);
}

function loadDirectorZoneManagers() {
    $.ajax({
        url: '{{ route("dashboard.director.zone-managers") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                $('#stat-zone-managers').html('<span style="font-size:14px;color:#dc3545;">—</span>');
                return;
            }
            dirZoneManagersData = response.zones;
            animateCount('stat-zone-managers', response.count);
        },
        error: function () {
            $('#stat-zone-managers').html('<span style="font-size:14px;color:#dc3545;">—</span>');
        }
    });
}

function openZoneManagersModal() {
    var $body = $('#zoneManagersModalBody');

    if (!dirZoneManagersData) {
        $body.html('<div class="text-center py-3"><div class="dir-spinner" style="margin:auto;"></div></div>');
        $('#zoneManagersModal').modal('show');
        return;
    }

    if (dirZoneManagersData.length === 0) {
        $body.html('<p style="font-size:12px;color:#6b7280;margin:0;">No zone managers assigned.</p>');
        $('#zoneManagersModal').modal('show');
        return;
    }

    var html = '<table style="width:100%;border-collapse:collapse;">' +
        '<thead><tr>' +
        '<th style="font-size:11px;font-weight:700;color:#0a0a0a;padding:6px 8px;border-bottom:2px solid #d9ebf7;text-align:left;background:#f7fbff;">Zone</th>' +
        '<th style="font-size:11px;font-weight:700;color:#0a0a0a;padding:6px 8px;border-bottom:2px solid #d9ebf7;text-align:left;background:#f7fbff;">Zone Manager</th>' +
        '</tr></thead><tbody>';

    dirZoneManagersData.forEach(function (row, i) {
        var bg = i % 2 === 0 ? '#f7fbff' : '#fff';
        html += '<tr style="background:' + bg + ';">' +
            '<td style="font-size:12px;padding:7px 8px;border-bottom:1px solid #d9ebf7;color:#0a0a0a;">' +
                '<span class="material-symbols-outlined" style="font-size:13px;vertical-align:middle;color:#5b9bd5;margin-right:4px;">location_on</span>' +
                row.zone_name +
            '</td>' +
            '<td style="font-size:12px;padding:7px 8px;border-bottom:1px solid #d9ebf7;color:#374151;font-weight:600;">' +
                row.manager_name +
            '</td>' +
            '</tr>';
    });

    html += '</tbody></table>';
    $body.html(html);
    $('#zoneManagersModal').modal('show');
}

function showStatsError() {
    ['stat-employees','stat-field-officers','stat-on-leave','stat-zone-managers',
     'stat-clients','stat-properties','stat-units','stat-tenants',
     'stat-advance','stat-leaves-open','stat-leaves-closed']
        .forEach(function(id) {
            $('#' + id).html('<span style="font-size:14px;color:#dc3545;">—</span>');
        });
    $('#loadingOccupancy').hide();
    toastr.error('Failed to load dashboard stats. Please refresh the page.');
}

// ── Occupancy chart ────────────────────────────────
function renderOccupancyChart(occupied, vacant, total) {
    $('#loadingOccupancy').hide();
    var ctx = document.getElementById('chartOccupancy').getContext('2d');
    if (window.dirChartOcc) window.dirChartOcc.destroy();

    if (total <= 0) {
        $('#legendOccupancy').html('<span style="font-size:11px;color:#6b7280;">No unit data.</span>');
        window.dirChartOcc = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['No Data'], datasets: [{ data: [1], backgroundColor: ['#e9ecef'], borderWidth: 0 }] },
            options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
        });
        return;
    }

    window.dirChartOcc = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: ['Occupied','Vacant'], datasets: [{ data: [occupied, vacant], backgroundColor: ['#00008B', '#ADD8E6'], borderWidth: 0, hoverOffset: 4 }] },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw + ' units'; } } } } }
    });

    var pctO = ((occupied / total) * 100).toFixed(1);
    var pctV = ((vacant   / total) * 100).toFixed(1);
    $('#legendOccupancy').html(
        legendRow('#00008B', 'Occupied (' + pctO + '%)', occupied + ' units') +
        legendRow('#ADD8E6', 'Vacant ('   + pctV + '%)', vacant   + ' units') +
        '<div class="dir-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="dir-legend-label">Total Units</div><div class="dir-legend-value">' + total + ' units</div></div>' +
        '</div>'
    );
}

// ── Collection charts ──────────────────────────────
function loadDirectorCollection() {
    $.ajax({
        url: '{{ route("dashboard.director.collection") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) { $('#loadingOverall, #loadingMonth').hide(); return; }
            renderCollectionChart('chartOverall', 'loadingOverall', 'legendOverall', response.overall,       'dirChartOverall');
            renderCollectionChart('chartMonth',   'loadingMonth',   'legendMonth',   response.current_month, 'dirChartMonth');
        },
        error: function () {
            $('#loadingOverall, #loadingMonth').hide();
            toastr.error('Failed to load collection data.');
        }
    });
}

function renderCollectionChart(canvasId, loadingId, legendId, data, instanceKey) {
    $('#' + loadingId).hide();
    var ctx = document.getElementById(canvasId).getContext('2d');
    if (window[instanceKey]) window[instanceKey].destroy();

    if (data.total <= 0) {
        $('#' + legendId).html('<span style="font-size:11px;color:#6b7280;">No invoice data.</span>');
        window[instanceKey] = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['No Data'], datasets: [{ data: [1], backgroundColor: ['#e9ecef'], borderWidth: 0 }] },
            options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
        });
        return;
    }

    window[instanceKey] = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: ['Collected','Arrears'], datasets: [{ data: [data.collected, data.arrears], backgroundColor: ['#00008B', '#ADD8E6'], borderWidth: 0, hoverOffset: 4 }] },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + formatMoney(c.raw); } } } } }
    });

    var pctC = ((data.collected / data.total) * 100).toFixed(1);
    var pctA = ((data.arrears   / data.total) * 100).toFixed(1);
    $('#' + legendId).html(
        legendRow('#00008B', 'Collected (' + pctC + '%)', formatMoney(data.collected)) +
        legendRow('#ADD8E6', 'Arrears ('   + pctA + '%)', formatMoney(data.arrears))   +
        '<div class="dir-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="dir-legend-label">Total Invoiced</div><div class="dir-legend-value">' + formatMoney(data.total) + '</div></div>' +
        '</div>'
    );
}

// ── Helpers ────────────────────────────────────────
function legendRow(color, label, value) {
    return '<div class="dir-legend-item">' +
        '<div class="dir-legend-dot" style="background:' + color + ';"></div>' +
        '<div><div class="dir-legend-label">' + label + '</div><div class="dir-legend-value">' + value + '</div></div>' +
        '</div>';
}
function formatMoney(val) {
    return 'KES ' + parseFloat(val).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endsection

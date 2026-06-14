@extends('layouts.main')

@section('styles')
<style>
/* ── Shell ───────────────────────────────────────── */
.dir-dashboard { padding: 20px 0; }

/* ── Greeting ────────────────────────────────────── */
.dir-greeting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 8px;
}
.dir-greeting-left h4 {
    font-size: 18px;
    font-weight: 700;
    color: #0a0a0a;
    margin: 0 0 2px 0;
}
.dir-greeting-left p { font-size: 12px; color: #6b7280; margin: 0; }
.dir-date-badge {
    background: #b7d7f0;
    color: #0a0a0a;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.dir-date-badge .material-symbols-outlined { font-size: 16px !important; }

/* ── Section label ───────────────────────────────── */
.dir-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 12px;
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
.dir-stat-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid #d9ebf7;
    padding: 12px 14px 10px;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.15s ease;
    display: flex;
    align-items: center;
    gap: 12px;
}
.dir-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: var(--card-accent, #b7d7f0);
    border-radius: 8px 0 0 8px;
}
.dir-stat-card:hover { box-shadow: 0 2px 10px rgba(91,155,213,0.12); }

.dir-stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--card-icon-bg, #d9ebf7);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.dir-stat-icon .material-symbols-outlined {
    font-size: 16px !important;
    color: #0a0a0a !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.dir-stat-body { flex: 1; min-width: 0; }
.dir-stat-number {
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1.1;
    margin-bottom: 2px;
    min-height: 24px;
}
.dir-stat-label {
    font-size: 11px;
    font-weight: 500;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Returns Advance card (finance highlight) ────── */
.dir-advance-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid #fde8d0;
    padding: 14px 18px;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.15s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.dir-advance-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: #e67e22;
    border-radius: 8px 0 0 8px;
}
.dir-advance-card:hover { box-shadow: 0 2px 10px rgba(230,126,34,0.10); }
.dir-advance-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #fdebd0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.dir-advance-icon .material-symbols-outlined {
    font-size: 18px !important;
    color: #e67e22 !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.dir-advance-body { flex: 1; min-width: 0; }
.dir-advance-amount {
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1.1;
    margin-bottom: 2px;
    min-height: 24px;
}
.dir-advance-label { font-size: 11px; font-weight: 500; color: #6b7280; }

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

            {{-- ── People ── --}}
            <div class="dir-section-label">People</div>
            <div class="dir-grid-3">

                <div class="dir-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">groups</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-employees"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Employees</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">person_pin</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-field-officers"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Field Officers</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#8ab4cc; --card-icon-bg:#d9ebf7;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">event_busy</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-on-leave"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">On Leave Today</div>
                    </div>
                </div>

            </div>

            {{-- ── Portfolio ── --}}
            <div class="dir-section-label">Portfolio</div>
            <div class="dir-grid-4">

                <div class="dir-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">business</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-clients"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Clients</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">apartment</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-properties"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Properties</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">domain</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-units"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Total Units</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#8ab4cc; --card-icon-bg:#d9ebf7;">
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

                <div class="dir-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">book_2</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-leaves-open"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Cheque Leaves – Open Books</div>
                    </div>
                </div>

                <div class="dir-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="dir-stat-icon"><span class="material-symbols-outlined">menu_book</span></div>
                    <div class="dir-stat-body">
                        <div class="dir-stat-number" id="stat-leaves-closed"><span class="dir-skeleton dir-skeleton-sm"></span></div>
                        <div class="dir-stat-label">Cheque Leaves – Closed Books</div>
                    </div>
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

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function () {
    loadDirectorStats();
    loadDirectorCollection();
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

function showStatsError() {
    ['stat-employees','stat-field-officers','stat-on-leave',
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
        data: { labels: ['Occupied','Vacant'], datasets: [{ data: [occupied, vacant], backgroundColor: ['#b7d7f0','#0a0a0a'], borderWidth: 0, hoverOffset: 4 }] },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw + ' units'; } } } } }
    });

    var pctO = ((occupied / total) * 100).toFixed(1);
    var pctV = ((vacant   / total) * 100).toFixed(1);
    $('#legendOccupancy').html(
        legendRow('#b7d7f0', 'Occupied (' + pctO + '%)', occupied + ' units') +
        legendRow('#0a0a0a', 'Vacant ('   + pctV + '%)', vacant   + ' units') +
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
        data: { labels: ['Collected','Arrears'], datasets: [{ data: [data.collected, data.arrears], backgroundColor: ['#b7d7f0','#0a0a0a'], borderWidth: 0, hoverOffset: 4 }] },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + formatMoney(c.raw); } } } } }
    });

    var pctC = ((data.collected / data.total) * 100).toFixed(1);
    var pctA = ((data.arrears   / data.total) * 100).toFixed(1);
    $('#' + legendId).html(
        legendRow('#b7d7f0', 'Collected (' + pctC + '%)', formatMoney(data.collected)) +
        legendRow('#0a0a0a', 'Arrears ('   + pctA + '%)', formatMoney(data.arrears))   +
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

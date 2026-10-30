@extends('layouts.main')

@section('styles')
<style>
/* ── Dashboard shell ─────────────────────────────── */
.hm-dashboard {
    padding: 20px 0;
}

/* ── Greeting bar ────────────────────────────────── */
.hm-greeting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 8px;
}
.hm-greeting-left h4 {
    font-size: 18px;
    font-weight: 700;
    color: #0a0a0a;
    margin: 0 0 2px 0;
}
.hm-greeting-left p {
    font-size: 12px;
    color: #6b7280;
    margin: 0;
}
.hm-date-badge {
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
.hm-date-badge .material-symbols-outlined {
    font-size: 16px !important;
}

/* ── Section label ───────────────────────────────── */
.hm-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 12px;
}

/* ── Stat cards ──────────────────────────────────── */
.hm-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}
@media (max-width: 767px) { .hm-stats-grid { grid-template-columns: 1fr; } }
@media (min-width: 768px) and (max-width: 991px) { .hm-stats-grid { grid-template-columns: repeat(2, 1fr); } }

.hm-stat-card {
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
.hm-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: var(--card-accent, #b7d7f0);
    border-radius: 8px 0 0 8px;
}
.hm-stat-card:hover {
    box-shadow: 0 2px 10px rgba(91, 155, 213, 0.12);
}

.hm-stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--card-icon-bg, #d9ebf7);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.hm-stat-icon .material-symbols-outlined {
    font-size: 16px !important;
    color: #0a0a0a !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.hm-stat-body { flex: 1; min-width: 0; }

.hm-stat-number {
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1.1;
    margin-bottom: 2px;
    min-height: 24px;
}
.hm-stat-label {
    font-size: 11px;
    font-weight: 500;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Loading skeleton pulse */
.hm-skeleton {
    display: inline-block;
    width: 40px;
    height: 20px;
    background: linear-gradient(90deg, #d9ebf7 25%, #b7d7f0 50%, #d9ebf7 75%);
    background-size: 200% 100%;
    animation: hm-pulse 1.4s ease infinite;
    border-radius: 6px;
    vertical-align: bottom;
}
@keyframes hm-pulse {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Chart cards ─────────────────────────────────── */
.hm-chart-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 8px;
    overflow: hidden;
}
.hm-chart-header {
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
.hm-chart-header .material-symbols-outlined {
    font-size: 16px !important;
    color: #5b9bd5 !important;
}
.hm-chart-body {
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 20px;
}
.hm-chart-wrap {
    position: relative;
    width: 130px;
    height: 130px;
    flex-shrink: 0;
}
.hm-chart-wrap canvas {
    width: 130px !important;
    height: 130px !important;
}
.hm-chart-loading {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.8);
}
.hm-spinner {
    width: 24px;
    height: 24px;
    border: 3px solid #d9ebf7;
    border-top-color: #5b9bd5;
    border-radius: 50%;
    animation: hm-spin 0.7s linear infinite;
}
@keyframes hm-spin { to { transform: rotate(360deg); } }

.hm-chart-legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.hm-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.hm-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.hm-legend-label {
    font-size: 11px;
    color: #6b7280;
    line-height: 1.2;
}
.hm-legend-value {
    font-size: 13px;
    font-weight: 600;
    color: #0a0a0a;
    margin-top: 1px;
}

/* ── Divider ─────────────────────────────────────── */
.hm-divider {
    border: none;
    border-top: 1px solid #d9ebf7;
    margin: 4px 0 24px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="hm-dashboard">

            {{-- Greeting --}}
            <div class="hm-greeting">
                <div class="hm-greeting-left">
                    <h4>{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ Auth::user()->name }}</h4>
                    <p>Company overview for today.</p>
                </div>
                <div class="hm-date-badge">
                    <span class="material-symbols-outlined">calendar_today</span>
                    {{ now()->format('l, d F Y') }}
                </div>
            </div>

            <hr class="hm-divider">

            {{-- HR KPIs --}}
            <div class="hm-section-label">People</div>
            <div class="hm-stats-grid" style="margin-bottom:16px;">

                <div class="hm-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="hm-stat-icon">
                        <span class="material-symbols-outlined">groups</span>
                    </div>
                    <div class="hm-stat-body">
                        <div class="hm-stat-number" id="stat-employees"><span class="hm-skeleton"></span></div>
                        <div class="hm-stat-label">Total Employees</div>
                    </div>
                </div>

                <div class="hm-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="hm-stat-icon">
                        <span class="material-symbols-outlined">person_pin</span>
                    </div>
                    <div class="hm-stat-body">
                        <div class="hm-stat-number" id="stat-field-officers"><span class="hm-skeleton"></span></div>
                        <div class="hm-stat-label">Field Officers</div>
                    </div>
                </div>

                <div class="hm-stat-card" style="--card-accent:#8ab4cc; --card-icon-bg:#d9ebf7;">
                    <div class="hm-stat-icon">
                        <span class="material-symbols-outlined">event_busy</span>
                    </div>
                    <div class="hm-stat-body">
                        <div class="hm-stat-number" id="stat-on-leave"><span class="hm-skeleton"></span></div>
                        <div class="hm-stat-label">On Leave Today</div>
                    </div>
                </div>

            </div>

            {{-- Property KPIs --}}
            <div class="hm-section-label">Portfolio</div>
            <div class="hm-stats-grid">

                <div class="hm-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="hm-stat-icon">
                        <span class="material-symbols-outlined">business</span>
                    </div>
                    <div class="hm-stat-body">
                        <div class="hm-stat-number" id="stat-clients"><span class="hm-skeleton"></span></div>
                        <div class="hm-stat-label">Total Clients</div>
                    </div>
                </div>

                <div class="hm-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="hm-stat-icon">
                        <span class="material-symbols-outlined">apartment</span>
                    </div>
                    <div class="hm-stat-body">
                        <div class="hm-stat-number" id="stat-properties"><span class="hm-skeleton"></span></div>
                        <div class="hm-stat-label">Total Properties</div>
                    </div>
                </div>

                <div class="hm-stat-card" style="--card-accent:#8ab4cc; --card-icon-bg:#d9ebf7;">
                    <div class="hm-stat-icon">
                        <span class="material-symbols-outlined">people</span>
                    </div>
                    <div class="hm-stat-body">
                        <div class="hm-stat-number" id="stat-tenants"><span class="hm-skeleton"></span></div>
                        <div class="hm-stat-label">Total Tenants</div>
                    </div>
                </div>

            </div>

            <hr class="hm-divider">

            {{-- Charts --}}
            <div class="hm-section-label">Occupancy &amp; Collection</div>
            <div class="row g-3">

                {{-- Unit Occupancy --}}
                <div class="col-md-4">
                    <div class="hm-chart-card">
                        <div class="hm-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Unit Occupancy
                        </div>
                        <div class="hm-chart-body">
                            <div class="hm-chart-wrap">
                                <canvas id="chartOccupancy"></canvas>
                                <div class="hm-chart-loading" id="loadingOccupancy">
                                    <div class="hm-spinner"></div>
                                </div>
                            </div>
                            <div class="hm-chart-legend" id="legendOccupancy"></div>
                        </div>
                    </div>
                </div>

                {{-- Overall Collection --}}
                <div class="col-md-4">
                    <div class="hm-chart-card">
                        <div class="hm-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Overall Collection
                        </div>
                        <div class="hm-chart-body">
                            <div class="hm-chart-wrap">
                                <canvas id="chartOverall"></canvas>
                                <div class="hm-chart-loading" id="loadingOverall">
                                    <div class="hm-spinner"></div>
                                </div>
                            </div>
                            <div class="hm-chart-legend" id="legendOverall"></div>
                        </div>
                    </div>
                </div>

                {{-- Current Month --}}
                <div class="col-md-4">
                    <div class="hm-chart-card">
                        <div class="hm-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            {{ now()->format('F Y') }} Collection
                        </div>
                        <div class="hm-chart-body">
                            <div class="hm-chart-wrap">
                                <canvas id="chartMonth"></canvas>
                                <div class="hm-chart-loading" id="loadingMonth">
                                    <div class="hm-spinner"></div>
                                </div>
                            </div>
                            <div class="hm-chart-legend" id="legendMonth"></div>
                        </div>
                    </div>
                </div>

            </div>
            {{-- End Charts --}}

            @include('dashboards.leave-overview')
            @include('dashboards.contract-expiry-overview')

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function () {
    loadHrStats();
    loadHrCollection();
});

// ── Stats ──────────────────────────────────────────
function loadHrStats() {
    $.ajax({
        url: '{{ route("dashboard.hr-manager.stats") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) { showStatsError(); return; }
            var s = response.stats;
            animateCount('stat-employees',     s.total_employees);
            animateCount('stat-field-officers', s.field_officers);
            animateCount('stat-on-leave',       s.on_leave_today);
            animateCount('stat-clients',        s.total_clients);
            animateCount('stat-properties',     s.total_properties);
            animateCount('stat-tenants',        s.total_tenants);
            renderOccupancyChart(s.occupied_units, s.vacant_units, s.total_units);
        },
        error: function () { showStatsError(); }
    });
}

function animateCount(id, target) {
    var $el      = $('#' + id);
    var duration = 900;
    var startTime = null;
    $el.html('<span class="count-val">0</span>');
    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var ease     = 1 - Math.pow(1 - progress, 3);
        $el.find('.count-val').text(Math.floor(ease * target).toLocaleString());
        if (progress < 1) requestAnimationFrame(step);
        else $el.find('.count-val').text(target.toLocaleString());
    }
    requestAnimationFrame(step);
}

function showStatsError() {
    ['stat-employees','stat-field-officers','stat-on-leave','stat-clients','stat-properties','stat-tenants']
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
    if (window.hmChartOccupancy) window.hmChartOccupancy.destroy();

    if (total <= 0) {
        $('#legendOccupancy').html('<span style="font-size:11px;color:#6b7280;">No unit data.</span>');
        window.hmChartOccupancy = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['No Data'], datasets: [{ data: [1], backgroundColor: ['#e9ecef'], borderWidth: 0 }] },
            options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
        });
        return;
    }

    window.hmChartOccupancy = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Occupied', 'Vacant'],
            datasets: [{ data: [occupied, vacant], backgroundColor: ['#b7d7f0', '#0a0a0a'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw + ' units'; } } } } }
    });

    var pctOcc = ((occupied / total) * 100).toFixed(1);
    var pctVac = ((vacant   / total) * 100).toFixed(1);

    $('#legendOccupancy').html(
        legendRow('#b7d7f0', 'Occupied (' + pctOcc + '%)', occupied + ' units') +
        legendRow('#0a0a0a', 'Vacant ('   + pctVac + '%)', vacant   + ' units') +
        '<div class="hm-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="hm-legend-label">Total Units</div>' +
            '<div class="hm-legend-value">' + total + ' units</div></div>' +
        '</div>'
    );
}

// ── Collection charts ──────────────────────────────
function loadHrCollection() {
    $.ajax({
        url: '{{ route("dashboard.hr-manager.collection") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                $('#loadingOverall, #loadingMonth').hide();
                return;
            }
            renderCollectionChart('chartOverall', 'loadingOverall', 'legendOverall', response.overall,       'hmChartOverall');
            renderCollectionChart('chartMonth',   'loadingMonth',   'legendMonth',   response.current_month, 'hmChartMonth');
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
        data: {
            labels: ['Collected', 'Arrears'],
            datasets: [{ data: [data.collected, data.arrears], backgroundColor: ['#b7d7f0', '#0a0a0a'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + formatMoney(c.raw); } } } } }
    });

    var pctC = ((data.collected / data.total) * 100).toFixed(1);
    var pctA = ((data.arrears   / data.total) * 100).toFixed(1);

    $('#' + legendId).html(
        legendRow('#b7d7f0', 'Collected (' + pctC + '%)', formatMoney(data.collected)) +
        legendRow('#0a0a0a', 'Arrears ('   + pctA + '%)', formatMoney(data.arrears))   +
        '<div class="hm-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="hm-legend-label">Total Invoiced</div>' +
            '<div class="hm-legend-value">' + formatMoney(data.total) + '</div></div>' +
        '</div>'
    );
}

// ── Helpers ────────────────────────────────────────
function legendRow(color, label, value) {
    return '<div class="hm-legend-item">' +
        '<div class="hm-legend-dot" style="background:' + color + ';"></div>' +
        '<div><div class="hm-legend-label">' + label + '</div>' +
        '<div class="hm-legend-value">'  + value + '</div></div>' +
        '</div>';
}

function formatMoney(val) {
    return 'KES ' + parseFloat(val).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endsection

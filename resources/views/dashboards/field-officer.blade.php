@extends('layouts.main')

@section('styles')
<style>
/* ── Dashboard shell ─────────────────────────────── */
.fo-dashboard {
    padding: 20px 0;
}

/* ── Greeting bar ────────────────────────────────── */
.fo-greeting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 8px;
}
.fo-greeting-left h4 {
    font-size: 18px;
    font-weight: 700;
    color: #0a0a0a;
    margin: 0 0 2px 0;
}
.fo-greeting-left p {
    font-size: 12px;
    color: #6b7280;
    margin: 0;
}
.fo-date-badge {
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
.fo-date-badge .material-symbols-outlined {
    font-size: 16px !important;
}

/* ── Section label ───────────────────────────────── */
.fo-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 12px;
}

/* ── Stat cards ──────────────────────────────────── */
.fo-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}
@media (max-width: 575px) { .fo-stats-grid { grid-template-columns: 1fr; } }

.fo-stat-card {
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
.fo-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: var(--card-accent, #b7d7f0);
    border-radius: 8px 0 0 8px;
}
.fo-stat-card:hover {
    box-shadow: 0 2px 10px rgba(91, 155, 213, 0.12);
}

.fo-stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--card-icon-bg, #d9ebf7);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.fo-stat-icon .material-symbols-outlined {
    font-size: 16px !important;
    color: #0a0a0a !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.fo-stat-body { flex: 1; min-width: 0; }

.fo-stat-number {
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1.1;
    margin-bottom: 2px;
    min-height: 24px;
}
.fo-stat-label {
    font-size: 11px;
    font-weight: 500;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Loading skeleton pulse */
.fo-skeleton {
    display: inline-block;
    width: 40px;
    height: 20px;
    background: linear-gradient(90deg, #d9ebf7 25%, #b7d7f0 50%, #d9ebf7 75%);
    background-size: 200% 100%;
    animation: fo-pulse 1.4s ease infinite;
    border-radius: 6px;
    vertical-align: bottom;
}
@keyframes fo-pulse {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Chart cards ─────────────────────────────────── */
.fo-chart-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 8px;
    overflow: hidden;
}
.fo-chart-header {
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
.fo-chart-header .material-symbols-outlined {
    font-size: 16px !important;
    color: #5b9bd5 !important;
}
.fo-chart-body {
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 20px;
}
.fo-chart-wrap {
    position: relative;
    width: 130px;
    height: 130px;
    flex-shrink: 0;
}
.fo-chart-wrap canvas {
    width: 130px !important;
    height: 130px !important;
}
.fo-chart-loading {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.8);
}
.fo-spinner {
    width: 24px;
    height: 24px;
    border: 3px solid #d9ebf7;
    border-top-color: #5b9bd5;
    border-radius: 50%;
    animation: fo-spin 0.7s linear infinite;
}
@keyframes fo-spin { to { transform: rotate(360deg); } }

.fo-chart-legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.fo-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.fo-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.fo-legend-label {
    font-size: 11px;
    color: #6b7280;
    line-height: 1.2;
}
.fo-legend-value {
    font-size: 13px;
    font-weight: 600;
    color: #0a0a0a;
    margin-top: 1px;
}

/* ── Divider ─────────────────────────────────────── */
.fo-divider {
    border: none;
    border-top: 1px solid #d9ebf7;
    margin: 4px 0 24px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="fo-dashboard">

            {{-- Greeting --}}
            <div class="fo-greeting">
                <div class="fo-greeting-left">
                    <h4>{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ Auth::user()->name }}</h4>
                    <p>Here's a snapshot of your portfolio today.</p>
                </div>
                <div class="fo-date-badge">
                    <span class="material-symbols-outlined">calendar_today</span>
                    {{ now()->format('l, d F Y') }}
                </div>
            </div>

            <hr class="fo-divider">

            {{-- KPI Stats --}}
            <div class="fo-section-label">Portfolio Overview</div>
            <div class="fo-stats-grid">

                {{-- Properties --}}
                <div class="fo-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="fo-stat-icon">
                        <span class="material-symbols-outlined">apartment</span>
                    </div>
                    <div class="fo-stat-body">
                        <div class="fo-stat-number" id="stat-properties"><span class="fo-skeleton"></span></div>
                        <div class="fo-stat-label">Properties Managed</div>
                    </div>
                </div>

                {{-- Total Units --}}
                <div class="fo-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="fo-stat-icon">
                        <span class="material-symbols-outlined">domain</span>
                    </div>
                    <div class="fo-stat-body">
                        <div class="fo-stat-number" id="stat-total-units"><span class="fo-skeleton"></span></div>
                        <div class="fo-stat-label">Total Units</div>
                    </div>
                </div>


            </div>
            {{-- End KPI Stats --}}

            <hr class="fo-divider">

            {{-- Charts --}}
            <div class="fo-section-label">Occupancy &amp; Collection</div>
            <div class="row g-3">

                {{-- Occupancy --}}
                <div class="col-md-4">
                    <div class="fo-chart-card">
                        <div class="fo-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Unit Occupancy
                        </div>
                        <div class="fo-chart-body">
                            <div class="fo-chart-wrap">
                                <canvas id="chartOccupancy"></canvas>
                                <div class="fo-chart-loading" id="loadingOccupancy">
                                    <div class="fo-spinner"></div>
                                </div>
                            </div>
                            <div class="fo-chart-legend" id="legendOccupancy"></div>
                        </div>
                    </div>
                </div>

                {{-- Overall Collection --}}
                <div class="col-md-4">
                    <div class="fo-chart-card">
                        <div class="fo-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Overall Collection
                        </div>
                        <div class="fo-chart-body">
                            <div class="fo-chart-wrap">
                                <canvas id="chartOverall"></canvas>
                                <div class="fo-chart-loading" id="loadingOverall">
                                    <div class="fo-spinner"></div>
                                </div>
                            </div>
                            <div class="fo-chart-legend" id="legendOverall"></div>
                        </div>
                    </div>
                </div>

                {{-- Current Month --}}
                <div class="col-md-4">
                    <div class="fo-chart-card">
                        <div class="fo-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            {{ now()->format('F Y') }} Collection
                        </div>
                        <div class="fo-chart-body">
                            <div class="fo-chart-wrap">
                                <canvas id="chartMonth"></canvas>
                                <div class="fo-chart-loading" id="loadingMonth">
                                    <div class="fo-spinner"></div>
                                </div>
                            </div>
                            <div class="fo-chart-legend" id="legendMonth"></div>
                        </div>
                    </div>
                </div>

            </div>
            {{-- End Charts --}}

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function () {
    loadFieldOfficerStats();
    loadFieldOfficerCollection();
});

function loadFieldOfficerStats() {
    $.ajax({
        url: '{{ route("dashboard.field-officer.stats") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                showStatsError();
                return;
            }
            var s = response.stats;
            animateCount('stat-properties',  s.properties);
            animateCount('stat-total-units', s.total_units);
            renderOccupancyChart(s.occupied_units, s.vacant_units, s.total_units);
        },
        error: function () {
            showStatsError();
        }
    });
}

function animateCount(id, target) {
    var $el    = $('#' + id);
    var start  = 0;
    var duration = 900;
    var startTime = null;

    $el.html('<span class="count-val">0</span>');

    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var ease     = 1 - Math.pow(1 - progress, 3);
        var current  = Math.floor(ease * target);
        $el.find('.count-val').text(current.toLocaleString());
        if (progress < 1) requestAnimationFrame(step);
        else $el.find('.count-val').text(target.toLocaleString());
    }

    requestAnimationFrame(step);
}

function showStatsError() {
    ['stat-properties','stat-total-units'].forEach(function(id) {
        $('#' + id).html('<span style="font-size:14px;color:#dc3545;">—</span>');
    });
    $('#loadingOccupancy').hide();
    toastr.error('Failed to load dashboard stats. Please refresh the page.');
}

function renderOccupancyChart(occupied, vacant, total) {
    $('#loadingOccupancy').hide();
    var ctx = document.getElementById('chartOccupancy').getContext('2d');
    if (window.chartOccupancyInstance) window.chartOccupancyInstance.destroy();

    if (total <= 0) {
        $('#legendOccupancy').html('<span style="font-size:11px;color:#6b7280;">No unit data.</span>');
        window.chartOccupancyInstance = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['No Data'], datasets: [{ data: [1], backgroundColor: ['#e9ecef'], borderWidth: 0 }] },
            options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
        });
        return;
    }

    window.chartOccupancyInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Occupied', 'Vacant'],
            datasets: [{
                data: [occupied, vacant],
                backgroundColor: ['#b7d7f0', '#0a0a0a'],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return ' ' + ctx.label + ': ' + ctx.raw + ' units'; }
                    }
                }
            }
        }
    });

    var pctOcc = total > 0 ? ((occupied / total) * 100).toFixed(1) : '0.0';
    var pctVac = total > 0 ? ((vacant   / total) * 100).toFixed(1) : '0.0';

    $('#legendOccupancy').html(
        '<div class="fo-legend-item">' +
            '<div class="fo-legend-dot" style="background:#b7d7f0;"></div>' +
            '<div><div class="fo-legend-label">Occupied (' + pctOcc + '%)</div>' +
            '<div class="fo-legend-value">' + occupied + ' units</div></div>' +
        '</div>' +
        '<div class="fo-legend-item">' +
            '<div class="fo-legend-dot" style="background:#0a0a0a;"></div>' +
            '<div><div class="fo-legend-label">Vacant (' + pctVac + '%)</div>' +
            '<div class="fo-legend-value">' + vacant + ' units</div></div>' +
        '</div>' +
        '<div class="fo-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="fo-legend-label">Total Units</div>' +
            '<div class="fo-legend-value">' + total + ' units</div></div>' +
        '</div>'
    );
}

// ── Collection charts ──────────────────────────────
var chartOverallInstance = null;
var chartMonthInstance   = null;

function loadFieldOfficerCollection() {
    $.ajax({
        url: '{{ route("dashboard.field-officer.collection") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                $('#loadingOverall, #loadingMonth').hide();
                return;
            }
            renderPieChart('chartOverall', 'loadingOverall', 'legendOverall', response.overall, 'chartOverallInstance');
            renderPieChart('chartMonth',   'loadingMonth',   'legendMonth',   response.current_month, 'chartMonthInstance');
        },
        error: function () {
            $('#loadingOverall, #loadingMonth').hide();
            toastr.error('Failed to load collection data.');
        }
    });
}

function renderPieChart(canvasId, loadingId, legendId, data, instanceKey) {
    $('#' + loadingId).hide();

    var collected = data.collected;
    var arrears   = data.arrears;
    var total     = data.total;

    var ctx = document.getElementById(canvasId).getContext('2d');

    if (window[instanceKey]) {
        window[instanceKey].destroy();
    }

    // Empty state
    if (total <= 0) {
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
            datasets: [{
                data: [collected, arrears],
                backgroundColor: ['#b7d7f0', '#0a0a0a'],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.label + ': ' + formatMoney(ctx.raw);
                        }
                    }
                }
            }
        }
    });

    var pctCollected = total > 0 ? ((collected / total) * 100).toFixed(1) : '0.0';
    var pctArrears   = total > 0 ? ((arrears   / total) * 100).toFixed(1) : '0.0';

    $('#' + legendId).html(
        '<div class="fo-legend-item">' +
            '<div class="fo-legend-dot" style="background:#b7d7f0;"></div>' +
            '<div><div class="fo-legend-label">Collected (' + pctCollected + '%)</div>' +
            '<div class="fo-legend-value">' + formatMoney(collected) + '</div></div>' +
        '</div>' +
        '<div class="fo-legend-item">' +
            '<div class="fo-legend-dot" style="background:#0a0a0a;"></div>' +
            '<div><div class="fo-legend-label">Arrears (' + pctArrears + '%)</div>' +
            '<div class="fo-legend-value">' + formatMoney(arrears) + '</div></div>' +
        '</div>' +
        '<div class="fo-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="fo-legend-label">Total Invoiced</div>' +
            '<div class="fo-legend-value">' + formatMoney(total) + '</div></div>' +
        '</div>'
    );
}

function formatMoney(val) {
    return 'KES ' + parseFloat(val).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endsection

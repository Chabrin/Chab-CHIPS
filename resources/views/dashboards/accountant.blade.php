@extends('layouts.main')

@section('styles')
<style>
/* ── Dashboard shell ─────────────────────────────── */
.ac-dashboard { padding: 20px 0; }

/* ── Greeting bar ────────────────────────────────── */
.ac-greeting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 8px;
}
.ac-greeting-left h4 {
    font-size: 18px;
    font-weight: 700;
    color: #0a0a0a;
    margin: 0 0 2px 0;
}
.ac-greeting-left p { font-size: 12px; color: #6b7280; margin: 0; }
.ac-date-badge {
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
.ac-date-badge .material-symbols-outlined { font-size: 16px !important; }

/* ── Section label ───────────────────────────────── */
.ac-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 12px;
}

/* ── Stat grid ───────────────────────────────────── */
.ac-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
@media (max-width: 767px)  { .ac-stats-grid { grid-template-columns: 1fr; } }
@media (min-width: 768px) and (max-width: 991px) { .ac-stats-grid { grid-template-columns: repeat(2, 1fr); } }

/* ── Stat card (standard) ────────────────────────── */
.ac-stat-card {
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
.ac-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: var(--card-accent, #b7d7f0);
    border-radius: 8px 0 0 8px;
}
.ac-stat-card:hover { box-shadow: 0 2px 10px rgba(91,155,213,0.12); }

.ac-stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--card-icon-bg, #d9ebf7);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ac-stat-icon .material-symbols-outlined {
    font-size: 16px !important;
    color: #0a0a0a !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.ac-stat-body { flex: 1; min-width: 0; }
.ac-stat-number {
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1.1;
    margin-bottom: 2px;
    min-height: 24px;
}
.ac-stat-label {
    font-size: 11px;
    font-weight: 500;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Returns Advance wide card ───────────────────── */
.ac-advance-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid #d9ebf7;
    padding: 14px 18px;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.15s ease;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}
.ac-advance-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 3px;
    background: #e67e22;
    border-radius: 8px 0 0 8px;
}
.ac-advance-card:hover { box-shadow: 0 2px 10px rgba(230,126,34,0.10); }
.ac-advance-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: #fdebd0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ac-advance-icon .material-symbols-outlined {
    font-size: 18px !important;
    color: #e67e22 !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.ac-advance-body { flex: 1; min-width: 0; }
.ac-advance-amount {
    font-size: 22px;
    font-weight: 400;
    color: #0a0a0a;
    line-height: 1.1;
    margin-bottom: 2px;
    min-height: 26px;
}
.ac-advance-label {
    font-size: 11px;
    font-weight: 500;
    color: #6b7280;
}
.ac-advance-note {
    font-size: 10px;
    color: #9ca3af;
    margin-left: auto;
    flex-shrink: 0;
    align-self: flex-end;
    padding-bottom: 2px;
}

/* ── Skeleton pulse ──────────────────────────────── */
.ac-skeleton {
    display: inline-block;
    width: 40px;
    height: 20px;
    background: linear-gradient(90deg, #d9ebf7 25%, #b7d7f0 50%, #d9ebf7 75%);
    background-size: 200% 100%;
    animation: ac-pulse 1.4s ease infinite;
    border-radius: 6px;
    vertical-align: bottom;
}
.ac-skeleton-wide { width: 120px; height: 22px; }
@keyframes ac-pulse {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Chart cards ─────────────────────────────────── */
.ac-chart-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 8px;
    overflow: hidden;
}
.ac-chart-header {
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
.ac-chart-header .material-symbols-outlined { font-size: 16px !important; color: #5b9bd5 !important; }
.ac-chart-body {
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 20px;
}
.ac-chart-wrap {
    position: relative;
    width: 130px;
    height: 130px;
    flex-shrink: 0;
}
.ac-chart-wrap canvas { width: 130px !important; height: 130px !important; }
.ac-chart-loading {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.8);
}
.ac-spinner {
    width: 24px;
    height: 24px;
    border: 3px solid #d9ebf7;
    border-top-color: #5b9bd5;
    border-radius: 50%;
    animation: ac-spin 0.7s linear infinite;
}
@keyframes ac-spin { to { transform: rotate(360deg); } }
.ac-chart-legend { flex: 1; display: flex; flex-direction: column; gap: 10px; }
.ac-legend-item { display: flex; align-items: center; gap: 8px; }
.ac-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.ac-legend-label { font-size: 11px; color: #6b7280; line-height: 1.2; }
.ac-legend-value { font-size: 13px; font-weight: 600; color: #0a0a0a; margin-top: 1px; }

/* ── Divider ─────────────────────────────────────── */
.ac-divider { border: none; border-top: 1px solid #d9ebf7; margin: 4px 0 24px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="ac-dashboard">

            {{-- Greeting --}}
            <div class="ac-greeting">
                <div class="ac-greeting-left">
                    <h4>{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ Auth::user()->name }}</h4>
                    <p>Financial overview for today.</p>
                </div>
                <div class="ac-date-badge">
                    <span class="material-symbols-outlined">calendar_today</span>
                    {{ now()->format('l, d F Y') }}
                </div>
            </div>

            <hr class="ac-divider">

            {{-- Returns Advance --}}
            <div class="ac-section-label">Financials</div>
            <div class="ac-advance-card">
                <div class="ac-advance-icon">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
                <div class="ac-advance-body">
                    <div class="ac-advance-amount" id="stat-advance"><span class="ac-skeleton ac-skeleton-wide"></span></div>
                    <div class="ac-advance-label">Returns Advance (Unrecovered)</div>
                </div>
                <div class="ac-advance-note">entry_type_code = 0009, not recovered</div>
            </div>

            {{-- Cheque Books --}}
            <div class="ac-section-label">Cheque Books</div>
            <div class="ac-stats-grid" style="grid-template-columns: repeat(2, 1fr); max-width: 600px;">

                <div class="ac-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="ac-stat-icon">
                        <span class="material-symbols-outlined">book_2</span>
                    </div>
                    <div class="ac-stat-body">
                        <div class="ac-stat-number" id="stat-leaves-open"><span class="ac-skeleton"></span></div>
                        <div class="ac-stat-label">Available Leaves – Open Books</div>
                    </div>
                </div>

                <div class="ac-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="ac-stat-icon">
                        <span class="material-symbols-outlined">menu_book</span>
                    </div>
                    <div class="ac-stat-body">
                        <div class="ac-stat-number" id="stat-leaves-closed"><span class="ac-skeleton"></span></div>
                        <div class="ac-stat-label">Available Leaves – Closed Books</div>
                    </div>
                </div>

            </div>

            <hr class="ac-divider">

            {{-- Portfolio --}}
            <div class="ac-section-label">Portfolio</div>
            <div class="ac-stats-grid">

                <div class="ac-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="ac-stat-icon">
                        <span class="material-symbols-outlined">apartment</span>
                    </div>
                    <div class="ac-stat-body">
                        <div class="ac-stat-number" id="stat-properties"><span class="ac-skeleton"></span></div>
                        <div class="ac-stat-label">Total Properties</div>
                    </div>
                </div>

                <div class="ac-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="ac-stat-icon">
                        <span class="material-symbols-outlined">domain</span>
                    </div>
                    <div class="ac-stat-body">
                        <div class="ac-stat-number" id="stat-units"><span class="ac-skeleton"></span></div>
                        <div class="ac-stat-label">Total Units</div>
                    </div>
                </div>

                <div class="ac-stat-card" style="--card-accent:#8ab4cc; --card-icon-bg:#d9ebf7;">
                    <div class="ac-stat-icon">
                        <span class="material-symbols-outlined">people</span>
                    </div>
                    <div class="ac-stat-body">
                        <div class="ac-stat-number" id="stat-tenants"><span class="ac-skeleton"></span></div>
                        <div class="ac-stat-label">Total Tenants</div>
                    </div>
                </div>

            </div>

            <hr class="ac-divider">

            {{-- Charts --}}
            <div class="ac-section-label">Occupancy &amp; Collection</div>
            <div class="row g-3">

                <div class="col-md-4">
                    <div class="ac-chart-card">
                        <div class="ac-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Unit Occupancy
                        </div>
                        <div class="ac-chart-body">
                            <div class="ac-chart-wrap">
                                <canvas id="chartOccupancy"></canvas>
                                <div class="ac-chart-loading" id="loadingOccupancy"><div class="ac-spinner"></div></div>
                            </div>
                            <div class="ac-chart-legend" id="legendOccupancy"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="ac-chart-card">
                        <div class="ac-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            Overall Collection
                        </div>
                        <div class="ac-chart-body">
                            <div class="ac-chart-wrap">
                                <canvas id="chartOverall"></canvas>
                                <div class="ac-chart-loading" id="loadingOverall"><div class="ac-spinner"></div></div>
                            </div>
                            <div class="ac-chart-legend" id="legendOverall"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="ac-chart-card">
                        <div class="ac-chart-header">
                            <span class="material-symbols-outlined">donut_large</span>
                            {{ now()->format('F Y') }} Collection
                        </div>
                        <div class="ac-chart-body">
                            <div class="ac-chart-wrap">
                                <canvas id="chartMonth"></canvas>
                                <div class="ac-chart-loading" id="loadingMonth"><div class="ac-spinner"></div></div>
                            </div>
                            <div class="ac-chart-legend" id="legendMonth"></div>
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
    loadAccountantStats();
    loadAccountantCollection();
});

// ── Stats ──────────────────────────────────────────
function loadAccountantStats() {
    $.ajax({
        url: '{{ route("dashboard.accountant.stats") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) { showStatsError(); return; }
            var s = response.stats;

            // Cheque leaves (integer count)
            animateCount('stat-leaves-open',   s.leaves_in_open_books);
            animateCount('stat-leaves-closed',  s.leaves_in_closed_books);

            // Returns advance (money)
            $('#stat-advance').text(formatMoney(s.returns_advance));

            // Portfolio
            animateCount('stat-properties', s.total_properties);
            animateCount('stat-units',      s.total_units);
            animateCount('stat-tenants',    s.total_tenants);

            // Occupancy chart
            renderOccupancyChart(s.occupied_units, s.vacant_units, s.total_units);
        },
        error: function () { showStatsError(); }
    });
}

function animateCount(id, target) {
    var $el = $('#' + id);
    var duration = 900, startTime = null;
    $el.html('<span class="count-val">0</span>');
    function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var ease = 1 - Math.pow(1 - progress, 3);
        $el.find('.count-val').text(Math.floor(ease * target).toLocaleString());
        if (progress < 1) requestAnimationFrame(step);
        else $el.find('.count-val').text(target.toLocaleString());
    }
    requestAnimationFrame(step);
}

function showStatsError() {
    ['stat-leaves-open','stat-leaves-closed','stat-advance','stat-properties','stat-units','stat-tenants']
        .forEach(function(id) { $('#' + id).html('<span style="font-size:14px;color:#dc3545;">—</span>'); });
    $('#loadingOccupancy').hide();
    toastr.error('Failed to load dashboard stats. Please refresh the page.');
}

// ── Occupancy chart ────────────────────────────────
function renderOccupancyChart(occupied, vacant, total) {
    $('#loadingOccupancy').hide();
    var ctx = document.getElementById('chartOccupancy').getContext('2d');
    if (window.acChartOccupancy) window.acChartOccupancy.destroy();

    if (total <= 0) {
        $('#legendOccupancy').html('<span style="font-size:11px;color:#6b7280;">No unit data.</span>');
        window.acChartOccupancy = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['No Data'], datasets: [{ data: [1], backgroundColor: ['#e9ecef'], borderWidth: 0 }] },
            options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
        });
        return;
    }

    window.acChartOccupancy = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: ['Occupied','Vacant'], datasets: [{ data: [occupied, vacant], backgroundColor: ['#b7d7f0','#0a0a0a'], borderWidth: 0, hoverOffset: 4 }] },
        options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw + ' units'; } } } } }
    });

    var pctOcc = ((occupied / total) * 100).toFixed(1);
    var pctVac = ((vacant   / total) * 100).toFixed(1);
    $('#legendOccupancy').html(
        legendRow('#b7d7f0', 'Occupied (' + pctOcc + '%)', occupied + ' units') +
        legendRow('#0a0a0a', 'Vacant ('   + pctVac + '%)', vacant   + ' units') +
        '<div class="ac-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="ac-legend-label">Total Units</div><div class="ac-legend-value">' + total + ' units</div></div>' +
        '</div>'
    );
}

// ── Collection charts ──────────────────────────────
function loadAccountantCollection() {
    $.ajax({
        url: '{{ route("dashboard.accountant.collection") }}',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.success) { $('#loadingOverall, #loadingMonth').hide(); return; }
            renderCollectionChart('chartOverall', 'loadingOverall', 'legendOverall', response.overall,       'acChartOverall');
            renderCollectionChart('chartMonth',   'loadingMonth',   'legendMonth',   response.current_month, 'acChartMonth');
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
        '<div class="ac-legend-item" style="border-top:1px solid #d9ebf7;padding-top:8px;">' +
            '<div><div class="ac-legend-label">Total Invoiced</div><div class="ac-legend-value">' + formatMoney(data.total) + '</div></div>' +
        '</div>'
    );
}

// ── Helpers ────────────────────────────────────────
function legendRow(color, label, value) {
    return '<div class="ac-legend-item">' +
        '<div class="ac-legend-dot" style="background:' + color + ';"></div>' +
        '<div><div class="ac-legend-label">' + label + '</div><div class="ac-legend-value">' + value + '</div></div>' +
        '</div>';
}
function formatMoney(val) {
    return 'KES ' + parseFloat(val).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endsection

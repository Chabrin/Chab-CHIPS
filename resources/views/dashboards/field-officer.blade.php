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
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}
@media (max-width: 991px) { .fo-stats-grid { grid-template-columns: repeat(2, 1fr); } }
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
    font-weight: 700;
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

                {{-- Occupied --}}
                <div class="fo-stat-card" style="--card-accent:#5b9bd5; --card-icon-bg:#b7d7f0;">
                    <div class="fo-stat-icon">
                        <span class="material-symbols-outlined">person_check</span>
                    </div>
                    <div class="fo-stat-body">
                        <div class="fo-stat-number" id="stat-occupied"><span class="fo-skeleton"></span></div>
                        <div class="fo-stat-label">Occupied Units</div>
                    </div>
                </div>

                {{-- Vacant --}}
                <div class="fo-stat-card" style="--card-accent:#b7d7f0; --card-icon-bg:#d9ebf7;">
                    <div class="fo-stat-icon">
                        <span class="material-symbols-outlined">home_work</span>
                    </div>
                    <div class="fo-stat-body">
                        <div class="fo-stat-number" id="stat-vacant"><span class="fo-skeleton"></span></div>
                        <div class="fo-stat-label">Vacant Units</div>
                    </div>
                </div>

            </div>
            {{-- End KPI Stats --}}

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    loadFieldOfficerStats();
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
            animateCount('stat-occupied',    s.occupied_units);
            animateCount('stat-vacant',      s.vacant_units);
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
    ['stat-properties','stat-total-units','stat-occupied','stat-vacant'].forEach(function(id) {
        $('#' + id).html('<span style="font-size:14px;color:#dc3545;">—</span>');
    });
    toastr.error('Failed to load dashboard stats. Please refresh the page.');
}
</script>
@endsection

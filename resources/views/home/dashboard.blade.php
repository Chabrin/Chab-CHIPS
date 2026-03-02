@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <!-- Stats Row - Condensed -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-xl-3 col-md-6">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined text-muted me-2" style="font-size:24px;">group</span>
                                    <div>
                                        <div class="text-muted small">Total Employees</div>
                                        <h5 class="mb-0 fw-bold" id="total-employees">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined text-muted me-2" style="font-size:24px;">work</span>
                                    <div>
                                        <div class="text-muted small">Total Positions</div>
                                        <h5 class="mb-0 fw-bold" id="total-positions">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined text-muted me-2" style="font-size:24px;">male</span>
                                    <div>
                                        <div class="text-muted small">Male Staff</div>
                                        <h5 class="mb-0 fw-bold" id="male-count">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined text-muted me-2" style="font-size:24px;">female</span>
                                    <div>
                                        <div class="text-muted small">Female Staff</div>
                                        <h5 class="mb-0 fw-bold" id="female-count">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Gender Distribution Pie Chart -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="fw-semibold text-muted">Staff by Gender</small>
                            <span class="material-symbols-outlined text-muted" id="refresh-gender-chart" style="font-size:18px; cursor:pointer;" title="Refresh">refresh</span>
                        </div>
                        <div id="gender-chart-loading" class="text-center position-absolute w-100 d-none" style="top: 50%; left: 0; transform: translateY(-50%); z-index: 10;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </div>
                        <div id="gender-chart-error" class="alert alert-danger alert-sm d-none position-absolute w-100" style="top: 50%; left: 0; transform: translateY(-50%); z-index: 10;">
                            <span class="material-symbols-outlined">error</span>
                            Failed to load. <a href="#" id="retry-gender-chart">Retry</a>
                        </div>
                        <div style="height: 250px; position: relative;">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Position Distribution Bar Chart -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="fw-semibold text-muted">Staff Distribution per Position</small>
                            <span class="material-symbols-outlined text-muted" id="refresh-position-chart" style="font-size:18px; cursor:pointer;" title="Refresh">refresh</span>
                        </div>
                        <div id="position-chart-loading" class="text-center position-absolute w-100 d-none" style="top: 50%; left: 0; transform: translateY(-50%); z-index: 10;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </div>
                        <div id="position-chart-error" class="alert alert-danger alert-sm d-none position-absolute w-100" style="top: 50%; left: 0; transform: translateY(-50%); z-index: 10;">
                            <span class="material-symbols-outlined">error</span>
                            Failed to load. <a href="#" id="retry-position-chart">Retry</a>
                        </div>
                        <div style="height: 250px;">
                            <canvas id="positionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        let genderChart = null;
        let positionChart = null;

        // Load all data
        loadDashboardStats();
        loadGenderChart();
        loadPositionChart();

        // Refresh buttons
        $('#refresh-gender-chart, #retry-gender-chart').on('click', function(e) {
            e.preventDefault();
            loadGenderChart();
        });

        $('#refresh-position-chart, #retry-position-chart').on('click', function(e) {
            e.preventDefault();
            loadPositionChart();
        });

        /**
         * Load dashboard statistics
         */
        function loadDashboardStats() {
            $.ajax({
                url: '{{ route("dashboard.stats") }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#total-employees').text(response.stats.total_employees || 0);
                        $('#total-positions').text(response.stats.total_positions || 0);
                        $('#male-count').text(response.stats.male_count || 0);
                        $('#female-count').text(response.stats.female_count || 0);
                    } else {
                        showStatsError();
                    }
                },
                error: function() {
                    showStatsError();
                }
            });
        }

        function showStatsError() {
            $('#total-employees, #total-positions, #male-count, #female-count').html('<span class="text-danger">--</span>');
        }

        /**
         * Load gender distribution chart
         */
        function loadGenderChart() {
            $('#gender-chart-loading').removeClass('d-none');
            $('#gender-chart-error').addClass('d-none');
            if (genderChart) {
                genderChart.destroy();
                genderChart = null;
            }

            $.ajax({
                url: '{{ route("dashboard.gender") }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        renderGenderChart(response.data);
                    } else {
                        showGenderChartError();
                    }
                },
                error: function() {
                    showGenderChartError();
                }
            });
        }

        function renderGenderChart(data) {
            $('#gender-chart-loading').addClass('d-none');

            const ctx = document.getElementById('genderChart').getContext('2d');
            genderChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.counts,
                        backgroundColor: data.colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        function showGenderChartError() {
            $('#gender-chart-loading').addClass('d-none');
            $('#gender-chart-error').removeClass('d-none');
        }

        /**
         * Load position distribution chart
         */
        function loadPositionChart() {
            $('#position-chart-loading').removeClass('d-none');
            $('#position-chart-error').addClass('d-none');
            if (positionChart) {
                positionChart.destroy();
                positionChart = null;
            }

            $.ajax({
                url: '{{ route("dashboard.positions") }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        renderPositionChart(response.data);
                    } else {
                        showPositionChartError();
                    }
                },
                error: function() {
                    showPositionChartError();
                }
            });
        }

        function renderPositionChart(data) {
            $('#position-chart-loading').addClass('d-none');

            const ctx = document.getElementById('positionChart').getContext('2d');
            positionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Staff Count',
                        data: data.counts,
                        backgroundColor: '#3b82f6',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ` ${context.parsed.x} staff`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function showPositionChartError() {
            $('#position-chart-loading').addClass('d-none');
            $('#position-chart-error').removeClass('d-none');
        }
    });
</script>

<style>
    .card {
        border-radius: 8px;
    }

    .card-header {
        border-bottom: 1px solid #e9ecef;
    }

    @media (max-width: 768px) {
        .card-body h5 {
            font-size: 1.1rem;
        }
    }
</style>
@endsection
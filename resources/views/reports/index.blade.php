@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  HEADER                                                                      --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="mb-3">
    <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
        <span class="material-symbols-outlined align-middle me-1" style="font-size:26px;color:#3498db;">bar_chart</span>
        Reports & Analytics
    </h4>
    <small class="text-muted">Generate, filter and export operational reports across all business areas.</small>
</div>

{{-- ── KPI Strip ──────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4" id="kpiStrip">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold" style="font-size:1.5rem;color:#1a1a2e;" id="kpiProperties">—</div>
            <div class="text-muted" style="font-size:0.78rem;">Active Properties</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold" style="font-size:1.5rem;color:#27ae60;" id="kpiOccupancy">—</div>
            <div class="text-muted" style="font-size:0.78rem;">Portfolio Occupancy</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold" style="font-size:1.5rem;color:#3498db;" id="kpiStaff">—</div>
            <div class="text-muted" style="font-size:0.78rem;">Active Staff</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold" style="font-size:1.1rem;color:#e67e22;" id="kpiRentRoll">—</div>
            <div class="text-muted" style="font-size:0.78rem;">Monthly Rent Roll (KES)</div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="fw-semibold mb-2" style="font-size:0.8rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Occupancy Overview</div>
                <canvas id="hubOccupancyChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="fw-semibold mb-2" style="font-size:0.8rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Headcount by Department</div>
                <canvas id="hubHeadcountChart" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  ACCORDION WRAPPER                                                           --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div id="reportsAccordion">

{{-- ── HR ──────────────────────────────────────────────────────────────────── --}}
<div class="report-section mb-2" id="section-hr">
    <button class="section-toggle w-100 text-start border-0 bg-white p-0"
            data-bs-toggle="collapse" data-bs-target="#collapse-hr"
            data-bs-parent="#reportsAccordion" aria-expanded="true">
        <div class="section-header d-flex align-items-center justify-content-between px-3 py-2 rounded">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;color:#e74c3c;">groups</span>
                <span class="fw-semibold" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.8px;color:#1a1a2e;">Human Resources</span>
                <span class="badge section-count" id="count-hr" style="background:#f0f4ff;color:#1a1a2e;font-size:0.7rem;">22</span>
            </div>
            <span class="material-symbols-outlined section-chevron" style="font-size:18px;color:#aaa;">expand_less</span>
        </div>
    </button>

    <div class="collapse show" id="collapse-hr" data-bs-parent="#reportsAccordion">
        <div class="row g-2 mt-1 pb-2 px-1 cards-container" id="cards-hr">

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="staff directory full staff register contact details departments positions">
                <a href="{{ route('reports.hr.staff-directory') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#3498db;">badge</span>
                                <div><div class="rc-title">Staff Directory</div><div class="rc-desc">Full staff register with contact details, departments and positions.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="headcount department active inactive staff count grouped">
                <a href="{{ route('reports.hr.headcount') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#9b59b6;">group</span>
                                <div><div class="rc-title">Headcount by Department</div><div class="rc-desc">Active and inactive staff count grouped by department.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="payroll register earnings deductions breakdown staff period">
                <a href="{{ route('reports.hr.payroll-register') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#27ae60;">receipt_long</span>
                                <div><div class="rc-title">Payroll Register</div><div class="rc-desc">Full earnings and deductions breakdown per staff for a payroll period.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="payroll summary department totals gross net period">
                <a href="{{ route('reports.hr.payroll-summary') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#27ae60;">summarize</span>
                                <div><div class="rc-title">Payroll Summary</div><div class="rc-desc">Department-level payroll totals for a given period.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="statutory deductions nssf shif housing levy period">
                <a href="{{ route('reports.statutory.deductions') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e67e22;">account_balance</span>
                                <div><div class="rc-title">Statutory Deductions</div><div class="rc-desc">NSSF, SHIF and Housing Levy deductions by period.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="helb loan deductions monthly amounts balances status">
                <a href="{{ route('reports.hr.helb-schedule') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e67e22;">school</span>
                                <div><div class="rc-title">HELB Schedule</div><div class="rc-desc">HELB loan deductions — monthly amounts, balances and status.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="loan advance schedule repayment balances monthly deductions staff">
                <a href="{{ route('reports.hr.loan-schedule') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#c0392b;">payments</span>
                                <div><div class="rc-title">Loan / Advance Schedule</div><div class="rc-desc">Staff loan repayment schedules — balances and monthly deductions.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="leave summary entitlements days used balances employee">
                <a href="{{ route('reports.hr.leave-summary') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#16a085;">beach_access</span>
                                <div><div class="rc-title">Leave Summary</div><div class="rc-desc">Leave entitlements, days used and balances per employee.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="leave balance report detailed leave types per staff">
                <a href="{{ route('leave-management.leave-balance-report') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#16a085;">balance</span>
                                <div><div class="rc-title">Leave Balance Report</div><div class="rc-desc">Detailed leave balances across all leave types per staff.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="leave calendar schedule approved away when">
                <a href="{{ route('reports.hr.leave-calendar') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#2980b9;">calendar_month</span>
                                <div><div class="rc-title">Leave Calendar</div><div class="rc-desc">Approved leave schedule — who is away and when.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="attendance days present absent staff selected month">
                <a href="{{ route('reports.hr.attendance') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#8e44ad;">how_to_reg</span>
                                <div><div class="rc-title">Attendance Report</div><div class="rc-desc">Days present and absent per staff for a selected month.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="payslips view download individual staff payslips month">
                <a href="{{ route('payroll.payslips') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#27ae60;">description</span>
                                <div><div class="rc-title">Payslips</div><div class="rc-desc">View and download individual staff payslips by month.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="employee contracts active expiring expired employment">
                <a href="{{ route('reports.hr.contracts') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#2c3e50;">assignment</span>
                                <div><div class="rc-title">Employee Contracts</div><div class="rc-desc">Active, expiring and expired employment contracts.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="separations terminations terminated staff reasons dates severance">
                <a href="{{ route('reports.hr.terminations') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e74c3c;">person_off</span>
                                <div><div class="rc-title">Separations / Terminations</div><div class="rc-desc">Terminated staff records — reasons, dates and severance.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="discipline case summary disciplinary severity step status outcome">
                <a href="{{ route('reports.hr.discipline-summary') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#c0392b;">gavel</span>
                                <div><div class="rc-title">Discipline Case Summary</div><div class="rc-desc">All disciplinary cases — severity, step, status and outcome.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="lapsed discipline deadlines workflow step deadline missed">
                <a href="{{ route('discipline.tracker.reports.lapsed') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e74c3c;">alarm_off</span>
                                <div><div class="rc-title">Lapsed Discipline Deadlines</div><div class="rc-desc">Cases where a workflow step deadline has been missed.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.hr.overtime') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#e67e22;">schedule</span><div><div class="rc-title">Overtime & Hours Analysis</div><div class="rc-desc">Hours worked vs. expected, overtime flags and short days per staff.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.hr.leave-adjustments') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#16a085;">tune</span><div><div class="rc-title">Leave Adjustments Register</div><div class="rc-desc">All leave balance corrections and reconciliation entries with reasons.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.hr.discipline-audit') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#c0392b;">policy</span><div><div class="rc-title">Discipline Process Audit</div><div class="rc-desc">Per-case audit trail — opening, hearing, outcome, and closure dates.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.hr.payroll-elements') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#2980b9;">calculate</span><div><div class="rc-title">Payroll Elements Register</div><div class="rc-desc">Configured allowances and deductions per position — amounts and formulas.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.hr.bank-accounts') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#1a1a2e;">account_balance</span><div><div class="rc-title">Staff Bank Account Register</div><div class="rc-desc">Auditable list of staff payment destinations — bank, account name, number.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.hr.payroll-periods') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#8e44ad;">date_range</span><div><div class="rc-title">Payroll Period Status</div><div class="rc-desc">All processed payroll periods — dates, staff count and processing status.</div></div></div></div></div>
    </a>
</div>

        </div>
    </div>
</div>

{{-- ── Property ────────────────────────────────────────────────────────────── --}}
<div class="report-section mb-2" id="section-property">
    <button class="section-toggle w-100 text-start border-0 bg-white p-0"
            data-bs-toggle="collapse" data-bs-target="#collapse-property"
            data-bs-parent="#reportsAccordion" aria-expanded="false">
        <div class="section-header d-flex align-items-center justify-content-between px-3 py-2 rounded">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;color:#27ae60;">apartment</span>
                <span class="fw-semibold" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.8px;color:#1a1a2e;">Property Management</span>
                <span class="badge section-count" id="count-property" style="background:#f0f4ff;color:#1a1a2e;font-size:0.7rem;">16</span>
            </div>
            <span class="material-symbols-outlined section-chevron" style="font-size:18px;color:#aaa;">expand_more</span>
        </div>
    </button>

    <div class="collapse" id="collapse-property" data-bs-parent="#reportsAccordion">
        <div class="row g-2 mt-1 pb-2 px-1 cards-container" id="cards-property">

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="portfolio overview properties unit counts occupancy rates rent values">
                <a href="{{ route('reports.property.portfolio') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#27ae60;">domain</span>
                                <div><div class="rc-title">Portfolio Overview</div><div class="rc-desc">All properties with unit counts, occupancy rates and rent values.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="occupancy report unit tenant lease reference end date">
                <a href="{{ route('reports.property.occupancy') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#16a085;">meeting_room</span>
                                <div><div class="rc-title">Occupancy Report</div><div class="rc-desc">Unit-level occupancy — tenant, lease reference and end date.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="vacancy report vacant units rent amount last tenancy">
                <a href="{{ route('reports.property.vacancy') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e74c3c;">no_meeting_room</span>
                                <div><div class="rc-title">Vacancy Report</div><div class="rc-desc">All vacant units with rent amount and last tenancy end date.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="rent roll expected rent unit actual payments outstanding month">
                <a href="{{ route('reports.property.rent-roll') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#8e44ad;">currency_exchange</span>
                                <div><div class="rc-title">Rent Roll</div><div class="rc-desc">Expected rent per unit vs. actual payments and outstanding for a month.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="lease register all leases status escalation rates term dates">
                <a href="{{ route('reports.property.lease-register') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#2980b9;">contract</span>
                                <div><div class="rc-title">Lease Register</div><div class="rc-desc">All leases with status, escalation rates and term dates.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="lease expiry leases expiring 30 60 90 180 days">
                <a href="{{ route('reports.property.lease-expiry') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e67e22;">timer_off</span>
                                <div><div class="rc-title">Lease Expiry</div><div class="rc-desc">Leases expiring within 30, 60, 90 or 180 days.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="tenant roster active tenants property unit contact details">
                <a href="{{ route('reports.property.tenant-roster') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#3498db;">people</span>
                                <div><div class="rc-title">Tenant Roster</div><div class="rc-desc">All active tenants with property, unit and contact details.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="deposit register deposits received amounts allocations unallocated balances">
                <a href="{{ route('reports.property.deposit-register') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#1abc9c;">savings</span>
                                <div><div class="rc-title">Deposit Register</div><div class="rc-desc">All deposits received — amounts, allocations and unallocated balances.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="exit report vacated tenants exit dates reasons deposit refunds">
                <a href="{{ route('reports.property.exits') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e74c3c;">door_back</span>
                                <div><div class="rc-title">Exit Report</div><div class="rc-desc">Tenants who have vacated — exit dates, reasons and deposit refunds.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.rent-escalations') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#e67e22;">trending_up</span><div><div class="rc-title">Rent Escalation Register</div><div class="rc-desc">History of rent changes — previous/new amounts, approval chain and effective dates.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.maintenance') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#27ae60;">build</span><div><div class="rc-title">Maintenance Request Register</div><div class="rc-desc">All repair requests by property — status, dates and item counts.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.invoice-aging') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#e74c3c;">hourglass_bottom</span><div><div class="rc-title">Invoice Aging Report</div><div class="rc-desc">Outstanding invoices bucketed by days overdue — Current, 30, 60, 90+ days.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.tenant-movements') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#3498db;">swap_horiz</span><div><div class="rc-title">Tenant Movement History</div><div class="rc-desc">Unit transfer records — from/to unit, movement date and balance carried forward.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.blacklist') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#c0392b;">block</span><div><div class="rc-title">Tenant Blacklist Register</div><div class="rc-desc">Blacklisted tenants — reason, date, balance at blacklisting and current status.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.landlord-remittance') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#1abc9c;">account_balance_wallet</span><div><div class="rc-title">Landlord Remittance Ledger</div><div class="rc-desc">Transaction-level landlord account — entry type, amounts, voucher references.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.property.pending-contracts') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#e67e22;">pending_actions</span><div><div class="rc-title">Pending Lease Documents</div><div class="rc-desc">Leases where the signed contract has not been received or uploaded.</div></div></div></div></div>
    </a>
</div>

        </div>
    </div>
</div>

{{-- ── Accounts ────────────────────────────────────────────────────────────── --}}
<div class="report-section mb-2" id="section-accounts">
    <button class="section-toggle w-100 text-start border-0 bg-white p-0"
            data-bs-toggle="collapse" data-bs-target="#collapse-accounts"
            data-bs-parent="#reportsAccordion" aria-expanded="false">
        <div class="section-header d-flex align-items-center justify-content-between px-3 py-2 rounded">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;color:#e67e22;">account_balance_wallet</span>
                <span class="fw-semibold" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.8px;color:#1a1a2e;">Accounts & Finance</span>
                <span class="badge section-count" id="count-accounts" style="background:#f0f4ff;color:#1a1a2e;font-size:0.7rem;">17</span>
            </div>
            <span class="material-symbols-outlined section-chevron" style="font-size:18px;color:#aaa;">expand_more</span>
        </div>
    </button>

    <div class="collapse" id="collapse-accounts" data-bs-parent="#reportsAccordion">
        <div class="row g-2 mt-1 pb-2 px-1 cards-container" id="cards-accounts">

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="arrears aging report outstanding balances closing debit status">
                <a href="{{ route('reports.accounts.arrears') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e74c3c;">money_off</span>
                                <div><div class="rc-title">Arrears / Aging Report</div><div class="rc-desc">Tenant outstanding balances — closing balances and debit status.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="payment collection payments received date range amounts channels status">
                <a href="{{ route('reports.accounts.payment-collection') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#27ae60;">payments</span>
                                <div><div class="rc-title">Payment Collection</div><div class="rc-desc">All payments received in a date range — amounts, channels and status.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="invoice register tenant invoices amounts balances statuses">
                <a href="{{ route('reports.accounts.invoice-register') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#3498db;">request_quote</span>
                                <div><div class="rc-title">Invoice Register</div><div class="rc-desc">All tenant invoices — amounts, balances and statuses.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="unallocated payments not matched invoice days outstanding">
                <a href="{{ route('reports.accounts.unallocated-payments') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#e67e22;">pending_actions</span>
                                <div><div class="rc-title">Unallocated Payments</div><div class="rc-desc">Payments not yet matched to any invoice — with days outstanding.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="returns summary landlord settlement gross commission vat net amounts">
                <a href="{{ route('reports.accounts.returns-summary') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#8e44ad;">currency_exchange</span>
                                <div><div class="rc-title">Returns Summary</div><div class="rc-desc">Landlord settlement returns — gross, commission, VAT and net amounts.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="commission report property gross rent rate vat net earned">
                <a href="{{ route('reports.accounts.commission') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#16a085;">percent</span>
                                <div><div class="rc-title">Commission Report</div><div class="rc-desc">Commission earned per property — gross rent, rate, VAT and net.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="deposit refunds tenant exit original amount refunded">
                <a href="{{ route('reports.accounts.deposit-refunds') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#1abc9c;">undo</span>
                                <div><div class="rc-title">Deposit Refunds</div><div class="rc-desc">Deposits refunded at tenant exit — original amount vs. refunded.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="tenant statements account invoices payments balance individual">
                <a href="{{ route('invoices.tenant.index') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#3498db;">person_search</span>
                                <div><div class="rc-title">Tenant Statements</div><div class="rc-desc">Individual tenant account statements — invoices, payments, balance.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col" data-search="landlord statement account transactions running balance export">
                <a href="{{ route('landlord.account.statement') }}" class="report-card d-block text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 report-card-inner">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start gap-2">
                                <span class="material-symbols-outlined report-icon" style="color:#2c3e50;">account_circle</span>
                                <div><div class="rc-title">Landlord Statement</div><div class="rc-desc">Landlord account transactions with running balance and export.</div></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.cheque-register') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#1a1a2e;">receipt</span><div><div class="rc-title">Cheque Register</div><div class="rc-desc">All cheques issued — number, payee, amount, status and book reference.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.voucher-register') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#2c3e50;">description</span><div><div class="rc-title">Voucher Register</div><div class="rc-desc">All payment vouchers — amounts, approval chain (prepared/checked/authorized), status.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.withholding-tax') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#e67e22;">percent</span><div><div class="rc-title">Withholding Tax Register</div><div class="rc-desc">WHT deducted per payee/period — Kenya compliance (management fees, rent).</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.approval-audit') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#27ae60;">fact_check</span><div><div class="rc-title">Approval Audit Trail</div><div class="rc-desc">Who approved what and when — vouchers, invoices, returns by approval stage.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.payment-allocation') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#3498db;">link</span><div><div class="rc-title">Payment Allocation Detail</div><div class="rc-desc">Each payment split across invoices — receipt number, invoice ref, allocated amount.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.recovery-register') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#16a085;">restore</span><div><div class="rc-title">Recovery Register</div><div class="rc-desc">Arrears recoveries and write-offs — date, narration, amount, commissionable flag.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.landlord-disbursement') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#8e44ad;">send_money</span><div><div class="rc-title">Landlord Disbursement Schedule</div><div class="rc-desc">What each landlord is owed — by period, with voucher linkage and payment status.</div></div></div></div></div>
    </a>
</div>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 report-col">
    <a href="{{ route('reports.accounts.returns-status') }}" class="report-card d-block text-decoration-none">
        <div class="card border-0 shadow-sm h-100 report-card-inner"><div class="card-body py-3"><div class="d-flex align-items-start gap-2"><span class="material-symbols-outlined report-icon" style="color:#e74c3c;">swap_vert</span><div><div class="rc-title">Returns Processing Status</div><div class="rc-desc">Which returns are merged, pending approval or approved — by landlord and period.</div></div></div></div></div>
    </a>
</div>

        </div>
    </div>
</div>

</div>{{-- /reportsAccordion --}}
</div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (btn) {
        var target  = document.querySelector(btn.dataset.bsTarget);
        var chevron = btn.querySelector('.section-chevron');
        if (!target || !chevron) return;
        target.addEventListener('hide.bs.collapse', function () { chevron.textContent = 'expand_more'; });
        target.addEventListener('show.bs.collapse', function () { chevron.textContent = 'expand_less'; });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(function () {
    var occupancyChart = null;
    var headcountChart = null;

    $.get("{{ route('ajax.reports.hub-summary') }}", function (res) {
        if (!res.success) return;
        $('#kpiProperties').text(res.total_properties);
        $('#kpiOccupancy').text(res.occupancy_pct + '%');
        $('#kpiStaff').text(res.active_staff);
        $('#kpiRentRoll').text('KES ' + res.rent_roll);

        var ctx1 = document.getElementById('hubOccupancyChart').getContext('2d');
        occupancyChart = new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Vacant'],
                datasets: [{ data: [res.occupied_units, res.vacant_units], backgroundColor: ['#27ae60','#e74c3c'], borderWidth: 0 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
        });

        var ctx2 = document.getElementById('hubHeadcountChart').getContext('2d');
        headcountChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: res.headcount.map(function(h){ return h.department; }),
                datasets: [{ label: 'Staff', data: res.headcount.map(function(h){ return h.count; }), backgroundColor: '#3498db', borderRadius: 4 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    });
});
</script>
@endsection

<style>
.section-header {
    background: #f8f9fc;
    border: 1px solid #e8eaf0;
    cursor: pointer;
    transition: background 0.15s;
}
.section-toggle:hover .section-header {
    background: #f0f3fb;
}
.report-card-inner {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    border-radius: 8px;
}
.report-card:hover .report-card-inner {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1) !important;
}
.report-icon {
    font-size: 22px;
    flex-shrink: 0;
    margin-top: 1px;
}
.rc-title {
    font-weight: 600;
    font-size: 0.875rem;
    color: #1a1a2e;
    margin-bottom: 2px;
}
.rc-desc {
    font-size: 0.76rem;
    color: #888;
    line-height: 1.4;
}
</style>

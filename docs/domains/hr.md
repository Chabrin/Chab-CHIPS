# HR Domain — Staff, Payroll, Leave, Discipline

Deep-dive into the HR domain of Chab-CHIPS, generated 2026-09-08. Complements
`docs/architecture-overview.md` (system-wide) — this doc drills into controller
internals, verified line counts, and live-data checks against the dev Postgres
DB (`chips_db_v3`) that the prior audit didn't have.

## Overview

HR covers six sub-areas behind one set of controllers: Staff master data and
contracts, Payroll/payslips, Leave Management, Discipline Tracking, Zone
Assignments (the HR→Property integration point), and Attendance. Authorization
is path-based middleware/DB permissions, as elsewhere in the app (no
Policies). Several HR sub-features (Staff Contracts, Discipline, Zone
Assignments, Employee Termination, Communication Templates, Leave
Applications/Adjustments) route through the shared `ConfirmationLog` +
`WorkflowApprovalController` maker-checker mechanism also used by Property
confirmations — that shared mechanism is the Audit-domain agent's territory,
not re-derived here.

**Live-data reality check** (Postgres `chips_db_v3`, queried directly): 255
rows in `sys_staff`, 484 in `sys_pm_zones_assignments`, only 10 in
`sys_user_staff_links` (most staff have no login account, by design), and
**14,680** rows in `sys_hr_payroll_summary` — Payroll is clearly in active
production use. By contrast, `discipline_cases`, the legacy
`sys_hr_staff_discipline_tracker`, `sys_hr_leave_requests`, and
`sys_hr_employees_attendance` are **all at 0 rows**. Despite very large,
actively-maintained controllers, Discipline Tracking, Leave Management, and
Attendance appear to have zero real usage history here — see Known Issues.

## Key Controllers & Models

Verified current line counts (`wc -l`), compared against the prior audit:

| Controller | Path | Lines (now) | Lines (prior audit) |
|---|---|---|---|
| `StaffController` | `app/Http/Controllers/StaffController.php` | 1,587 | not listed |
| `HrSettingsController` | `app/Http/Controllers/HrSettingsController.php` | 1,661 | 1,826 |
| `PayrollController` | `app/Http/Controllers/PayrollController.php` | 1,276 | not listed |
| `PayslipsController` | `app/Http/Controllers/PayslipsController.php` | 289 | not listed |
| `LeaveManagementController` | `app/Http/Controllers/LeaveManagementController.php` | 1,280 | not listed |
| `DisciplineTrackerController` | `app/Http/Controllers/DisciplineTrackerController.php` | **2,085** | 1,512 |
| `WorkflowApprovalController` | `app/Http/Controllers/WorkflowApprovalController.php` | 1,130 | not listed |

`DisciplineTrackerController` has grown by ~570 lines (+38%) since the prior
audit — it now covers considerably more than the original 14-step case flow
(appeals, suspensions, deadline extensions, termination paperwork — see
Discipline section). `HrSettingsController` shrank slightly (likely
refactoring), still handling Departments, Positions, Banks, SHIF/NSSF rate
settings, Workdays, Public Holidays, Communication Templates, and Zone
Assignments CRUD.

**Models**:
- `Staff` (`sys_staff`) — employee master record; links to Department,
  Position, Bank/BankBranch, Zone/ZoneArea, and (via `UserStaffLink`)
  optionally to a `User` login account.
- `UserStaffLink` (`sys_user_staff_links`) — thin pivot model (`user_id`,
  `staff_id`, no timestamps) deliberately keeping Staff and User separate
  until explicit account creation. Only 10 of 255 staff have a linked account.
- `ZoneAssignment` (`sys_pm_zones_assignments`) — the HR↔Property integration
  point, from the HR side: `staff_id` + `property_id` + `zone_id` +
  `assigned_as` (Field Officer/Zone Manager role) + `current_status_id`.
  `$timestamps = false` with a manual `date_time` column; observed by
  `AuditLogsObserver`. Property's own approval routing
  (`sys_pm_properties.field_officer_id`/`zone_manager_id`) resolves back to
  these Staff/User IDs.
- Payroll: `PayrollSummary` (`sys_hr_payroll_summary`), `PayrollElement`
  (`sys_hr_payroll_elements`), `TaxBand` (`sys_hr_tax_bands`), `Contract`
  (`sys_hr_employee_contracts`).
- Leave: `LeaveRequest` (`sys_hr_leave_requests`), `LeaveRequestDaysTaken`,
  leave types/calendar plan models (`sys_hr_leave_types`,
  `sys_hr_leave_calendar`).
- Discipline: 27 dedicated tables/models for one case lifecycle
  (`DisciplineCase`, `DisciplineIncidentReport`, `DisciplineInvestigator`,
  `DisciplineEvidence(File)`, `DisciplineWitnessStatement`,
  `DisciplineShowCauseNotice`, `DisciplineDeadlineExtension`,
  `DisciplineSuspension`, `DisciplineHearing(Minutes)`, `DisciplineOutcome`,
  `DisciplineTrainingAssignment`, `DisciplineCaseClosure`, `DisciplineAppeal`,
  `DisciplineActivityLog`, etc.), plus a legacy, unrelated
  `StaffDiscipline`/`Incident`/`ActionTaken` set still referenced by
  `StaffController`. `DisciplineTrackerController` uses only
  `PdfGenerationTrait` — it does **not** use `HandlesApprovalWorkflow` (that
  trait is only consumed by `ReturnsApprovalController`, a Returns-domain
  controller, not by anything in HR).

## Staff Lifecycle & Zone Assignment

`StaffController` (1,587 lines) handles Staff CRUD, employee Contracts
(`contractsIndex/Store/Update/List`), Employee Termination
(`employeeTerminationIndex/Store/Update` with severance calculation, document
uploads, and termination-elements breakdown), Attendance CSV upload/list, and
Communication sending. Contracts are Laravel-only (the legacy MSSQL system
stored position/salary/start-date directly on the employee row, per
`docs/mapping/hr_controller_mssql_mapping.md`) — the newer Contract model
supports multiple date-ranged contracts per staff member plus per-contract
payroll element overrides (allowances), which now actually feed payroll (see
below).

Zone Assignment (`HrSettingsController::zoneAssignmentsIndex/Store/...`)
assigns a `Staff` row to a `Zone`/`ZoneArea` with a role (`assigned_as`, e.g.
Field Officer/Field Manager) against a `property_id` — the single real
cross-domain touchpoint between HR and Property, since Property's approval
routing depends on Staff/User IDs set here.

## Payroll Cycle

`PayrollController::calculatePayroll()` (private method, line 915) is the
actual live calculation: it loads the staff's contract elements (allowances)
and adds them to `basicSalary` to form `grossPay`; `calculateNSSF()` computes
tiered NSSF on **basic salary only** (pensionable pay, min/max clamped);
`calculateNHIF()` computes SHIF/NHIF at 2.75% of full gross pay (basic +
allowances); `calculateHousingLevy()` computes 1.5% of full gross pay; and
`calculatePAYE()` reads live `TaxBand` rows from `sys_hr_tax_bands` and
applies them progressively, with personal relief hardcoded at KSh 2,400 (per
the MSSQL mapping doc, matching the legacy `TAXRLF` value, never made
configurable).

**`TaxCalculator` trait dead-code finding — verified.** `app/Traits/TaxCalculator.php`
(59 lines, single method `calculateTax(float $amount)`) is **never `use`d by
any class in the codebase** — `grep -rln "TaxCalculator" app/` matches only
the trait file itself. It's also internally stale: it queries a `tax_bands`
DB table (no `sys_` prefix — doesn't match the live `TaxBand` model's
`sys_hr_tax_bands`) and `cb_system_configs` keys the live PAYE path doesn't
use at all. The real, live PAYE/NSSF/SHIF/Housing-Levy logic is 100% inline as
private methods on `PayrollController` (`calculateNSSF`, `calculateNHIF`,
`calculateHousingLevy`, `calculatePAYE`, lines ~1067–1166). Confirms the prior
audit's flag: anyone editing `TaxCalculator` expecting it to affect real
payroll runs would be editing dead code.

`PayslipsController` (289 lines) renders/downloads/emails payslips, reading
from `PayrollSummary.payslip_elements` (JSON breakdown) with a fallback
reconstruction from individual columns for historical rows saved before the
JSON breakdown existed (see Known Issues — payslip fixes).

## Leave Management

`LeaveManagementController` (1,280 lines) manages leave types, annual
calendar plans (per-employee entitlement/used/balance), leave applications,
and adjustments. Submission computes `days_balance = entitled_days -
requested_days` against the employee's calendar plan (`sys_hr_leave_calendar`)
before the request can be filed.

Approval is **two-stage**, implemented inside
`WorkflowApprovalController::approve()`'s `'Leave Application'` case (not a
separate leave-specific controller):
- **Stage 1** (`current_status_id == 1`, Pending): first approver enters the
  actual `days_taken` (handles both full-day and partial/hour-only leave by
  comparing start/end times against the 08:00–17:00 default), writes
  `LeaveRequestDaysTaken` rows, and flips status to **24 ("Partially
  Approved")**.
- **Stage 2** (`current_status_id == 24`): a *different* approver (explicitly
  blocked from being the same user as stage 1, checked via the
  `ConfirmationLog` history) confirms the days, sets the day-taken rows to
  status 5 (Approved), and the leave request itself moves to 5.

This matches the assigned brief's description (`1→24 Partially Approved→5`)
exactly, confirmed directly in code.

## Discipline Tracking

`DisciplineTrackerController::getStepName()` (line 2001) is the canonical
14-step map, confirmed verbatim in code:

1. Create Incident Report
2. Register Case & Notify HR
3. Assign Investigator
4. Upload Evidence
5. Add Witness Statements
6. Record Investigation Notes
7. Issue Show Cause Notice
8. Employee Submits Response
9. Schedule Hearing
10. Record Hearing Minutes
11. Save Final Outcome
12. Assign Training (Optional)
13. Update Employee Disciplinary History
14. Close Case

Each transition calls `logActivity()`, writing a `DisciplineActivityLog` row
(case_id, step_number, step_name, activity_type, description, performed_by,
metadata) — the case's own dedicated activity log, separate from the
system-wide `audit_logs` table. Each step also fires
`SendDisciplineNotification` (a `ShouldQueue` job) for per-step notification.
`current_step` on `DisciplineCase` tracks progress; `getAvailableSteps()`
separately flags which of the 14 are `required` vs. optional (only step 12,
Training, is optional).

**Scope has grown well past the "linear 14-step" description**, though: the
controller now also implements suspension pending investigation
(distinct from the Step-11 "Suspension" outcome, per its own code comment),
show-cause deadline extensions (request/review/lapsed-deadline reporting), a
post-closure appeals process (`fileAppeal`/`appealsIndex`/`decideAppeal`),
decision-letter/certificate-of-service PDF generation, and downstream
termination paperwork (exit interview, final dues) — all layered around the
same 14 fixed steps rather than replacing them.

## Jobs

`PayslipEmailJob` reads `cb_system_configs.encrypt_emailed_payslips` (default
enabled) and, if enabled and the staff has a `national_id`, calls Dompdf's
`setEncryption($password, $password, ['print'])` using the national ID as
both owner and user PDF password before emailing — matches
`payslip-fix-summary.md`'s encryption description exactly. `ProcessAttendanceUpload`
(58 lines) streams an uploaded CSV row-by-row (`fgetcsv`) into
`EmployeeAttendance`, with no validation beyond checking the file exists.
`GenerateAnnualLeaveCalendarPlans` (133 lines) bulk-creates next year's
per-staff, per-leave-type calendar rows. `SendDisciplineNotification` fires
after each of the 14 discipline steps; like other "SMS" paths app-wide it
only `Log::info`s rather than calling a real gateway (a system-wide stub, not
HR-specific).

## Known Issues & Open Items

From `payslip-fix-summary.md` (root), all listed as **shipped**:
- Deduction-breakdown fallback for historical payslips (reconstructs from
  individual columns when `payslip_elements` JSON is empty).
- Contract allowances now included in gross pay, PAYE, NSSF/NHIF/Housing-Levy
  bases (previously hardcoded to basic salary only).
- NHIF/Housing-Levy now correctly computed on full gross pay; NSSF correctly
  stays basic-salary-only.
- Bank details and a PAYE breakdown (taxable earnings, tax before relief,
  personal relief, PAYE payable) added to payslip templates.
- Position/Department N/A fallback to the Staff record's own values.

**Still open, verified**: Akiba Sacco Loan, Akiba Share Contribution, and
Social Welfare display correctly on payslips (`PayrollSummary` has
`akiba_sacco_loan`, `akiba_share_contribution`, `social_welfare` fillable
columns, read directly in the Blade templates per `PayslipsController.php:264`),
but **no code anywhere in `app/` assigns values to these three columns**
(`grep -rn "akiba_sacco_loan\s*=\|social_welfare\s*=" app/` returns nothing)
— confirmed still unwired into payroll processing, exactly as flagged.

**New finding, not in either existing doc**: despite `DisciplineTrackerController`
being the largest HR controller (2,085 lines) and `LeaveManagementController` a
close second (1,280 lines), live-DB queries show **zero rows** in
`discipline_cases` and `sys_hr_leave_requests` (also zero in the legacy
`sys_hr_staff_discipline_tracker` and in `sys_hr_employees_attendance`).
Payroll (14,680 rows, all `current_status_id = 5`) and core Staff/Zone-
Assignment data are clearly live; Discipline, Leave, and Attendance appear
fully built but never exercised in this environment — worth confirming with
the business whether that's expected (rollout pending) or a UI-adoption gap.

Historical mapping docs (`docs/mapping/hr_controller_mssql_mapping.md`,
`docs/mapping/table_mapping_reference.md`) — an MSSQL→Laravel migration
reference, not live documentation — mark Contracts, Termination detail,
Discipline, Leave, Zone Assignments, Workdays/Holidays, Communication
Templates, and Attendance as entirely new-in-Laravel with no legacy source;
only Staff/Department/Position/Bank/Payroll-Element/Tax-Band/Payroll-Period/
Payroll-Summary have a direct legacy table match (`CHSTFF`, `CHDEPT`,
`CHDESN`, `CHBANK`, `CHPREDS`, `CHPTABS`, `CHPRDS`, `CHPYSL`).

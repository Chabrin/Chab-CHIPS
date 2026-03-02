# Table Mapping Reference: MSSQL (chips) → Laravel (chips_2025)

> **MSSQL Server**: `localhost:51837` | **Database**: `chips`
> **Laravel Project**: `chips_2025`
> **Date**: 2026-02-25

This document is a standalone quick-reference showing every table in the Laravel system,
which MSSQL table it was mapped from, **why** that mapping decision was made, and
how each column was translated from the MSSQL source to the Laravel target.

**Related files:**
- `hr_controller_mssql_mapping.md` — Full controller-by-controller analysis with reasoning
- `hr_mssql_views.sql` — Executable SQL VIEW scripts organized by HR service

---

## 1. Master Mapping Table

### 1A. Laravel tables that HAVE a corresponding MSSQL table

| # | Laravel Table | Laravel Model | MSSQL Table | Mapping Type | Confidence | Reason for Mapping |
|---|---|---|---|---|---|---|
| 1 | `sys_staff` | `Staff` | `CHSTFF` | Direct | HIGH | Both are the primary employee master record. Field-by-field match on 25+ columns: employee number, name, gender, DOB, national ID, mobile, email, KRA PIN, NHIF/NSSF numbers, bank details, department FK, position FK, hire date, address fields, marital status, citizenship. The purpose, structure, and usage are identical — this is unambiguously the same table. |

| 2 | `sys_set_departments` | `Department` | `CHDEPT` | Direct | HIGH | Both are simple lookup tables storing department names. Only 2 functional columns in each (code/id + name). Staff records in both systems FK to this table (`CHSTFF.DEPTCODE` → `CHDEPT` / `sys_staff.department_id` → `sys_set_departments`). |

| 3 | `sys_set_staff_positions` | `Position` | `CHDESN` | Partial | HIGH | Both store job title/designation names. MSSQL `CHDESN` is a simple 2-column lookup (code + name). Laravel extends this with `basic_salary`, `reports_to_id`, `salary_account_id`, and a pivot to payroll elements. The core data (position name) maps directly. |

| 4 | `sys_set_banks` | `Bank` | `CHBANK` | Direct | HIGH | Both store bank names for employee salary payments. Staff records FK to this table (`CHSTFF.BANKCODE` → `CHBANK` / `sys_staff.bank_name` → `sys_set_banks`). Laravel also has a `banks` table (`LegacyBank` model) with `bank_code`, `name`, `swift_code` used in other modules (clients, returns). |

| 4b | `sys_set_banks_branches` | `BankBranch` | `CHBBRA` | Direct | HIGH | Both store bank branch names linked to a parent bank. MSSQL `CHBBRA` has `BANKCODE` (FK to `CHBANK`), `BRANCODE` (branch code), `BRANNAME` (branch name). Laravel `sys_set_banks_branches` has `bank_id` (FK), `branch_code`, `branch_name`. Staff records in MSSQL FK via `CHSTFF.BANKBRAN` → `CHBBRA.BRANCODE`; Laravel FKs via `sys_staff.branch_id` → `sys_set_banks_branches.id`. |

| 5 | `sys_hr_payroll_elements` | `PayrollElement` | `CHPREDS` | Direct | HIGH | Both define the catalogue of payroll earnings and deductions (not actual payslip values). MSSQL has 24 element definitions (BASIC SALARY, NSSF, NHIF, PAYE, HELB LOANS, HOUSING LEVY, etc.) with code, name, type, rate, GL account. Laravel mirrors this with `element_name`, `element_type`, `formula_id`, `amount`, `account_id`. |

| 6 | `sys_hr_tax_bands` | `TaxBand` | `CHPTABS` | Direct | HIGH | Both define progressive PAYE income tax brackets. MSSQL stores bands with min/max/rate per year-month (Band A: 0–24K @10%, Band B: 24K–32K @25%, Band D: 32K+ @30%). Laravel stores `range_from`, `range_to`, `tax_rate`, `is_last`. Same data, same purpose. |

| 7 | `sys_hr_payroll_calendar` | `PayrollCalendar` | `CHPRDS` | Reshaped | HIGH | Both track payroll months/periods. MSSQL stores one row per period with year, month, status, open/close dates, company info, tax thresholds. Laravel splits this into a parent (calendar = month header) and child (periods = date ranges). The MSSQL `CHPRDS` row maps to one calendar entry + one period entry. |

| 8 | `sys_hr_payroll_periods` | `PayrollPeriod` | `CHPRDS` | Reshaped | HIGH | Child of above. Each `CHPRDS` row's `OPEDATE`/`CLSDATE` become the period's `start_date`/`end_date`. Laravel allows multiple periods per calendar month; MSSQL has one period per month. |

| 9 | `sys_hr_payroll_summary` | `PayrollSummary` | `CHPYSL` | Pivoted | HIGH | Both store processed payroll results. **Key structural difference**: MSSQL is normalized (one row per employee × element × month); Laravel is denormalized (one row per employee × month with named columns for statutory items + JSON for detail). The pivot maps specific EDCODE rows to named columns: `00001`→basic_salary, `00004`→nssf, `00005`→nhif, `00013`→tax_payable, `00022`→housing_levy, `00014`→net_pay. |

| 10 | `sys_helb_deductions` | `HelbDeduction` | `CHLOAN` | Partial | MEDIUM | MSSQL `CHLOAN` is a general-purpose loan table (loans, salary advances, sacco loans). It has `LNAMT` (amount), `AMTMONT` (monthly), `LNBALANC` (balance), `REPSTAT` (status). Laravel separates HELB into a dedicated table. Match based on: same data fields (balance, monthly deduction, status), same purpose (tracking recurring loan deductions from payroll). Confidence is MEDIUM because CHLOAN also contains non-HELB loans. |

| 11 | `sys_prorated_deductions` | `ProratedDeduction` | `CHLOAN` + `CHPYSL` | Partial | MEDIUM | Similar to above. Prorated deductions (salary advances, overpayments amortized over months) map to `CHLOAN` rows for the loan setup and `CHPYSL` element rows (EDCODE `00007` SALARY ADVANCE, `00012` OTHER DEDUCTIONS) for the actual monthly deduction line items. |

| 12 | `sys_staff.kins_names` | `Staff` | `CHSNOK` | Collapsed | HIGH | MSSQL has a separate `CHSNOK` table with 1-to-many NOK rows per employee (name, relationship, type, address, phone). Laravel collapses this into a single `kins_names` text field on `sys_staff`. The mapping takes the first "Next of kin" typed row's `NOKNAM` value. |

### 1B. Laravel tables with NO corresponding MSSQL table

These are entirely new modules built in the Laravel system with no legacy equivalent.

| # | Laravel Table | Laravel Model | Why No MSSQL Match |
|---|---|---|---|
| 13 | `sys_hr_contracts` | `Contract` | MSSQL stores employment details (position, salary, start date) directly on `CHSTFF`. Laravel introduces a separate contracts table supporting multiple contracts per employee with date ranges, salary overrides, and per-contract payroll element assignments. This is a new abstraction. |
| 14 | `sys_hr_contract_elements` | *(pivot)* | Per-contract payroll element assignments — part of the new contract system. |
| 15 | `sys_hr_employee_terminations` | `EmployeeTermination` | MSSQL only tracks termination as a status change (`CHSTFF.CURRSTAT = 'Inactive'`). Laravel adds a full termination workflow with severance calculations, termination reasons, document uploads, and severance element breakdowns. |
| 16 | `sys_hr_discipline_*` | `DisciplineCase`, etc. | MSSQL has no discipline tracking whatsoever. Laravel implements a 14-step discipline workflow (incident → investigation → hearing → outcome → closure). |
| 17 | `sys_hr_leave_types` | `HrLeaveType` | MSSQL has no leave management system. |
| 18 | `sys_hr_leave_calendar_plans` | `LeaveCalendarPlan` | New — annual leave entitlement planning per employee. |
| 19 | `sys_hr_leave_applications` | *(model)* | New — leave request + approval workflow. |
| 20 | `sys_hr_zone_assignments` | `ZoneAssignment` | Zones are a property management concept specific to Laravel (field managers assigned to geographic zones). Not in MSSQL. |
| 21 | `sys_set_zones` | `Zone` | Geographic zone definitions — Laravel-only. |
| 22 | `sys_set_zone_areas` | `ZoneArea` | Sub-areas within zones — Laravel-only. |
| 23 | `sys_hr_workdays` | `WorkDay` | Configuration of working days (Mon–Sun) — Laravel-only. |
| 24 | `sys_hr_public_holidays` | `PublicHoliday` | Named holidays with date ranges and pay rates — Laravel-only. |
| 25 | `sys_hr_shif_rates` | `ShifRate` | SHIF/NHIF rate settings with date-based effectivity. In MSSQL, the NHIF rate is stored inline on `CHPRDS` (`NHIFRATE` field) — not a separate table. |
| 26 | `sys_hr_nssf_rates` | `NssfRate` | NSSF rate settings (employee/employer %, max contribution). In MSSQL, the NSSF rate is a fixed amount on `CHPREDS` record (`EDRATE=2160`) — not a separate table. |
| 27 | `sys_hr_communication_templates` | `CommunicationTemplate` | Email/SMS templates for HR communications — entirely new. |
| 28 | `sys_hr_communications` | `Communication` | HR communication log — entirely new. |
| 29 | `sys_hr_employee_attendance` | `EmployeeAttendance` | Attendance tracking via CSV upload — entirely new. |
| 30 | `sys_workflow_approvals` | *(model)* | Maker-checker approval workflow — entirely new. |
| 31 | `sys_hr_payroll_elements_repeat_staff_positions_id` | *(pivot)* | Position-to-payroll-element pivot — Laravel-only structural feature. |

### 1C. MSSQL tables with NO corresponding Laravel table

These legacy tables exist in MSSQL but are not mapped to any Laravel table.

| # | MSSQL Table | Purpose | Why Not Mapped |
|---|---|---|---|
| 32 | `CHGRDS` | Pay grades (MAN / NMA) | Laravel does not have a grades concept; salary is on Position/Contract. |
| 33 | `CHEMPT` | Employee types (permanent, contract, etc.) | Laravel does not have a separate employee types table. |
| 34 | `CHP9A` | P9A annual tax certificate data | Laravel derives this from `PayrollSummary` aggregation rather than storing separately. Available as a reporting view (`vw_sys_hr_p9a`). |
| 35 | `CHPYRP` | Payroll run parameters | Laravel handles payroll run config in controller logic, not a table. |
| 36 | `CHPSEQ` | Payroll journal voucher posting sequence | Laravel handles GL posting differently. |

---

## 2. Column-Level Mapping Detail

For every MSSQL → Laravel table pair, here is exactly how each column was mapped and why.

---

### 2.1 `CHSTFF` → `sys_staff` (Employee Master)

**Mapping logic**: Both tables serve as the single source of truth for employee data. Columns were matched by data content and business purpose, not by name similarity.

| # | MSSQL Column | MSSQL Type | → | Laravel Column | Laravel Type | Mapping Reason |
|---|---|---|---|---|---|---|
| 1 | `EMPNUMB` | nvarchar(10) | → | `staff_number` | varchar | Both are the human-readable employee identifier (e.g., "001"). Same purpose, same data. |
| 2 | `FULLNAME` | nvarchar(100) | → | `full_names` | varchar | Both store the employee's full display name. Direct content match. |
| 3 | `SURNAME` | nvarchar(50) | → | — | — | **Not mapped.** Laravel stores only full name, not separate first/last. |
| 4 | `OTHRNAM` | nvarchar(50) | → | — | — | **Not mapped.** Same reason — no split name fields in Laravel. |
| 5 | `GENDER` | nvarchar(6) | → | `gender` | varchar | Identical field — stores "Male"/"Female". |
| 6 | `EMPDOB` | smalldatetime | → | `birth_date` | date | Both store date of birth. Type cast from smalldatetime → date. |
| 7 | `NATIDNO` | nvarchar(10) | → | `national_id` | varchar | Both store Kenyan national ID number. |
| 8 | `EMPADDR` | varchar(50) | → | `address` | varchar | Both store postal/physical address. |
| 9 | `MOBNUM1` | varchar(13) | → | `mobile_number` | varchar | Both store primary mobile phone. |
| 10 | `MOBNUM2` | varchar(13) | → | — | — | **Not mapped.** Laravel has only one mobile field. |
| 11 | `WEMAIL` | nvarchar(100) | → | `email_address` | varchar | Both store work email address. |
| 12 | `PEMAIL` | nvarchar(100) | → | `personal_email` | varchar | Both store personal email address. |
| 13 | `NUMPIN` | nvarchar(50) | → | `pin_number` | varchar | Both store KRA Tax PIN number. |
| 14 | `NUMNHIF` | nvarchar(50) | → | `nhif_number` | varchar | Both store NHIF/SHIF membership number. |
| 15 | `NUMNSSF` | nvarchar(50) | → | `nssf_number` | varchar | Both store NSSF membership number. |
| 16 | `BANKCODE` | nvarchar(20) | → | `bank_name` | int (FK) | FK to banks table. MSSQL uses string code (e.g., "00001"), Laravel uses integer FK. Resolved via `CHBANK.BANKCODE` → `sys_set_banks.id` lookup. |
| 17 | `BANKBRAN` | nvarchar(20) | → | `branch_id` | int (FK) | FK to bank branches. MSSQL uses string branch code (e.g., "01089"), Laravel uses integer FK to `sys_set_banks_branches.id`. Resolved via `CHBBRA.BRANCODE` → `sys_set_banks_branches.id` lookup. |
| 18 | `BANKACC` | varchar(30) | → | `account_number` | varchar | Both store bank account number. |
| 19 | `DEPTCODE` | nvarchar(50) | → | `department_id` | int (FK) | FK to departments. String code → integer FK via `CHDEPT.DEPTCODE` → `sys_set_departments.id` lookup. |
| 20 | `DESIGN` | nvarchar(50) | → | `position_id` | int (FK) | FK to positions. String code → integer FK via `CHDESN.DESIGNCODE` → `sys_set_staff_positions.id` lookup. |
| 21 | `SALBASC` | money | → | — | — | **Not mapped directly.** MSSQL stores basic salary on the employee record. Laravel stores it on `Position.basic_salary` and `Contract.basic_salary`. Preserved as `mssql_basic_salary` in the VIEW. |
| 22 | `GRADE` | nvarchar(10) | → | — | — | **Not mapped.** Laravel has no grades concept. Preserved as `mssql_grade`. |
| 23 | `EMPTYPE` | nvarchar(20) | → | — | — | **Not mapped.** Laravel has no employee types table. Preserved as `mssql_emp_type`. |
| 24 | `CURRSTAT` | nvarchar(20) | → | `current_status_id` | int | Status field. MSSQL stores string ('Active'/'Inactive'), Laravel stores integer (1/2). Mapped via CASE expression. |
| 25 | `APPDATE` | varchar(25) | → | `hire_date` | datetime | Both store the appointment/hire date. |
| 26 | `DISTRCT` | nvarchar(30) | → | `district` | varchar | Both store district/county. |
| 27 | `IDLOCAT` | nvarchar(30) | → | `location` | varchar | Both store location name. |
| 28 | `IDSUBLOC` | nvarchar(30) | → | `sub_location` | varchar | Both store sub-location. |
| 29 | `MSTATUS` | nvarchar(10) | → | `marital` | varchar | Both store marital status. |
| 30 | `SPOUSNAM` | nvarchar(60) | → | `spouse_name` | varchar | Both store spouse name. |
| 31 | `PLACEOB` | nvarchar(50) | → | `birth_place` | varchar | Both store place of birth. |
| 32 | `CITIZEN` | varchar(13) | → | `citizenship` | varchar | Both store citizenship/nationality. |
| 33 | `PRMSPAS` | varchar(50) | → | `system_password` | varchar | Both store system login password. |
| 34 | `SYSGRP` | varchar(50) | → | `permission_group_id` | int | Access control group. String → integer FK. |
| 35 | — | — | → | `passport` | varchar | **Laravel-only.** Passport number field not in MSSQL. |
| 36 | — | — | → | `photo` | varchar | **Laravel-only.** Profile photo path not in MSSQL. |
| 37 | — | — | → | `uid` | varchar | **Laravel-only.** Auto-generated UUID. |
| 38 | — | — | → | `zone_id` | int (FK) | **Laravel-only.** Zone assignment for property management. |
| 39 | — | — | → | `zone_area_id` | int (FK) | **Laravel-only.** Zone area assignment. |
| 40 | — | — | → | `kins_names` | varchar | Mapped from `CHSNOK.NOKNAM` (separate table in MSSQL, collapsed to single field). |
| 41 | — | — | → | `account_name` | varchar | **Laravel-only.** Bank account holder name. |
| 42 | — | — | → | `disability` | varchar | **Laravel-only.** Disability status flag. |

---

### 2.2 `CHSNOK` → `sys_staff.kins_names` (Next of Kin)

**Mapping logic**: MSSQL stores next-of-kin in a separate table with multiple rows per employee. Laravel stores only the primary NOK name as a single field on the staff record. The VIEW takes the first "Next of kin" typed row.

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `EMPNUMB` | → | `staff_number` | FK link — identifies which employee the NOK belongs to. |
| 2 | `NOKNAM` | → | `kins_names` | The NOK's name. Only the first "Next of kin" row is taken because Laravel stores a single value. |
| 3 | `NOKRELA` | → | — | **Not mapped.** Relationship type (Spouse, Mother, etc.) has no Laravel equivalent. |
| 4 | `NOKTYPE` | → | — | **Not mapped.** Used as a filter (WHERE NOKTYPE='Next of kin') to select primary NOK. |
| 5 | `NOKADDR` | → | — | **Not mapped.** NOK address not stored in Laravel. |
| 6 | `NOKCITY` | → | — | **Not mapped.** NOK city not stored in Laravel. |
| 7 | `NOKTEL` | → | — | **Not mapped.** NOK phone not stored in Laravel. |

---

### 2.3 `CHDEPT` → `sys_set_departments` (Departments)

**Mapping logic**: Simple 1:1 lookup table. Both have only a code/id and a name.

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `DEPTCODE` | → | `id` | Primary key. MSSQL uses nvarchar string codes ('00001'), Laravel uses auto-increment integers. Mapped via ROW_NUMBER(). |
| 2 | `DEPTNAME` | → | `department_name` | Direct match — both store the department's display name. |
| 3 | — | → | `date_time` | **Laravel-only.** Audit timestamp, filled with GETDATE(). |

---

### 2.4 `CHDESN` → `sys_set_staff_positions` (Positions)

**Mapping logic**: Both store job title names. MSSQL is a simple lookup; Laravel adds salary and hierarchy fields.

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `DESIGNCODE` | → | `id` | Primary key. String → integer via ROW_NUMBER(). |
| 2 | `DESGINNAME` | → | `position_name` | Direct match — the job title string. |
| 3 | — | → | `basic_salary` | **Laravel-only.** MSSQL stores salary on `CHSTFF.SALBASC` (per employee), not per position. Filled with NULL. |
| 4 | — | → | `salary_account_id` | **Laravel-only.** GL account FK for salary posting. |
| 5 | — | → | `reports_to_id` | **Laravel-only.** Self-referencing FK for org hierarchy. |
| 6 | — | → | `current_status_id` | **Laravel-only.** Defaulted to 1 (active). |

---

### 2.5 `CHBANK` → `sys_set_banks` (Banks)

**Mapping logic**: Simple 1:1 lookup table for bank names.

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `BANKCODE` | → | `id` | Primary key. String → integer via ROW_NUMBER(). |
| 2 | `BANKNAME` | → | `bank_name` | Direct match — bank display name. |
| 3 | — | → | `address` | **Laravel-only.** Bank address not in MSSQL. |
| 4 | — | → | `current_status_id` | **Laravel-only.** Defaulted to 1 (active). |

---

### 2.5b `CHBBRA` → `sys_set_banks_branches` (Bank Branches)

**Mapping logic**: Both store bank branch names linked to a parent bank. MSSQL uses string codes for both the bank FK and the branch PK; Laravel uses integer auto-increment IDs. The `CHSTFF.BANKBRAN` field FKs to `CHBBRA.BRANCODE`, and in Laravel `sys_staff.branch_id` FKs to `sys_set_banks_branches.id`.

| # | MSSQL Column | MSSQL Type | → | Laravel Column | Laravel Type | Mapping Reason |
|---|---|---|---|---|---|---|
| 1 | `BRANCODE` | nvarchar(20) | → | `id` / `branch_code` | int (PK) / varchar | Branch identifier. MSSQL uses string codes (e.g., '01089'). Laravel uses integer PK + stores the original code in `branch_code`. |
| 2 | `BRANNAME` | nvarchar(50) | → | `branch_name` | varchar | Direct match — the branch display name (e.g., 'EASTLEIGH', 'HEAD OFFICE'). |
| 3 | `BANKCODE` | nvarchar(20) | → | `bank_id` | int (FK) | FK to parent bank. MSSQL string code → Laravel integer FK. Resolved via `CHBANK.BANKCODE` → `sys_set_banks.id` (or `banks.id` for `LegacyBank`). |
| 4 | — | — | → | `added_by` | varchar | **Laravel-only.** Audit field for who created the record. |
| 5 | — | — | → | `added_on` | datetime | **Laravel-only.** Audit timestamp. |

---

### 2.6 `CHPREDS` → `sys_hr_payroll_elements` (Payroll Element Definitions)

**Mapping logic**: Both define what payroll elements exist (BASIC SALARY, NSSF, NHIF, etc.) and their properties. MSSQL uses different terminology for types and calculation methods.

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `EDCODE` | → | `id` + `element_key` | MSSQL uses string codes ('00001'). Laravel uses integer PK + a slug key (e.g., 'nssf'). The slug is derived from the element name using a CASE expression. |
| 2 | `EDNAME` | → | `element_name` | Direct match — the element's display name (e.g., "NSSF", "BASIC SALARY"). |
| 3 | `EDTYPE` | → | `element_type` | Type classification. MSSQL uses strings: 'Earning', 'Deduction', 'Saving', 'Computed'. Laravel uses integers: 1=Deduction, 2=Earning/Allowance. Mapped via CASE: Earning→2, everything else→1. |
| 4 | `TYPECHR` | → | `formula_id` | Calculation method. MSSQL: 'Fixed' (constant amount), 'Computed' (calculated from other elements), 'Depends on Staff' (varies per employee). Laravel: formula_id 1/2/3 respectively. |
| 5 | `EDRATE` | → | `amount` | The fixed rate/amount for elements with TYPECHR='Fixed'. E.g., NHIF EDRATE=320. |
| 6 | `GLACCOUNT` | → | `account_id` | GL account for financial posting. MSSQL stores account string directly; Laravel uses integer FK to chart of accounts. Needs manual GL account ID resolution. |
| 7 | `TAXABLE` | → | — | **Not mapped directly.** Whether the element is tax-deductible. Handled in Laravel's formula/calculation logic. Preserved as `mssql_taxable`. |
| 8 | `RECURR` | → | — | **Not mapped directly.** Whether the element recurs monthly. Handled in processing logic. Preserved as `mssql_recurring`. |
| 9 | — | → | `staff_positions_id` | **Laravel-only.** Links element to a position via pivot table. |

---

### 2.7 `CHPTABS` → `sys_hr_tax_bands` (PAYE Tax Brackets)

**Mapping logic**: Both define progressive tax brackets for PAYE calculation. MSSQL stores them per year/month; Laravel stores a single current set.

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `CATMIN` | → | `range_from` | Lower bound of the tax bracket (e.g., 0, 24001, 32334). Direct numeric match. |
| 2 | `CATMAX` | → | `range_to` | Upper bound. MSSQL uses 999999999 for the last (unlimited) band; Laravel uses NULL. Mapped via CASE. |
| 3 | `CATRAT` | → | `tax_rate` | Tax rate percentage (e.g., 10, 25, 30). Direct match. |
| 4 | `CATEGO` | → | — | **Not mapped.** Band category letter (A, B, D) — cosmetic label only. |
| 5 | `PYEAR` / `PMONTH` | → | — | **Not mapped.** MSSQL is year/month-versioned; Laravel keeps one active set. VIEW filters to latest year/month. |
| 6 | — | → | `is_last` | **Derived.** Boolean flag — set to 1 when CATMAX ≥ 999999999, indicating the final open-ended bracket. |
| 7 | — | → | `added_by` | **Laravel-only.** Audit field — set to 'system' for imported data. |

---

### 2.8 `CHPRDS` → `sys_hr_payroll_calendar` + `sys_hr_payroll_periods` (Payroll Periods)

**Mapping logic**: MSSQL stores one row per payroll month with all data. Laravel splits this into a parent calendar (month header) and child periods (date ranges). One MSSQL row produces one calendar row + one period row.

#### Calendar columns:

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `PRYEAR` + `PRMNTH` | → | `payroll_month_date` | Combined into YYYYMM string (e.g., "202501"). This is the primary identifier for the payroll month. |
| 2 | `PRSTATUS` | → | `current_status_id` | Period status. 'Open'→5 (active), 'Closed'→6. Mapped via CASE. |
| 3 | `TAXTHRS` | → | — | **Not mapped.** Tax threshold per period (24000). In Laravel this comes from tax band configuration. |
| 4 | `TAXRLF` | → | — | **Not mapped.** Personal relief per period (2400). In Laravel this is hardcoded in the PAYE calculation. |
| 5 | `CONAME` / `COPIN` | → | — | **Not mapped.** Company name and PIN. In Laravel these come from application settings. |

#### Period columns:

| # | MSSQL Column | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | *(auto)* | → | `payroll_calendar_id` | FK to the parent calendar entry. Resolved via CROSS APPLY. |
| 2 | *(constant)* | → | `period_number` | Set to 1. MSSQL has one period per month; Laravel allows multiples (e.g., mid-month + end-of-month). |
| 3 | `OPEDATE` | → | `start_date` | The date the payroll period was opened. |
| 4 | `CLSDATE` | → | `end_date` | The date the payroll period was closed. |

---

### 2.9 `CHPYSL` → `sys_hr_payroll_summary` (Processed Payslips)

**Mapping logic**: This is the most complex mapping. MSSQL stores payroll data **normalized** (one row per employee × element × month). Laravel stores it **denormalized** (one row per employee × month with named columns). The VIEW pivots MSSQL rows by element code into Laravel columns.

| # | MSSQL Source | → | Laravel Column | Mapping Reason |
|---|---|---|---|---|
| 1 | `EMPNUMB` (from distinct set) | → | `staff_id` / `staff_number_id` | Employee identifier. Resolved to integer FK via `vw_sys_staff`. |
| 2 | `PRYEAR` + `PRMNTH` | → | `period` | Constructed as a date: `DATEFROMPARTS(PRYEAR, PRMNTH, 1)`. |
| 3 | Row where `EDCODE='00001'` → `EDAMT` | → | `basic_salary` | The BASIC SALARY element's amount for this employee/period. |
| 4 | Row where `EDCODE='00004'` → `EDAMT` | → | `nssf` | The NSSF element's amount. |
| 5 | Row where `EDCODE='00005'` → `EDAMT` | → | `nhif` | The NHIF element's amount. |
| 6 | Row where `EDCODE='00022'` → `EDAMT` | → | `housing_levy` | The HOUSING LEVY element's amount. |
| 7 | Row where `EDCODE='00013'` → `EDAMT` | → | `tax_payable` | The PAYE element's amount. |
| 8 | Row where `EDCODE='00020'` → `EDAMT` | → | `helb` | The HELB LOANS element's amount. |
| 9 | Row where `EDNAME='TOTAL DEDUCTIONS'` → `EDAMT` | → | `total_deductions` | The aggregated total deductions row. |
| 10 | Row where `EDCODE='00014'` → `EDAMT` | → | `net_pay` | The NET SALARY element's amount. |
| 11 | All EDCODE rows for this employee/period | → | `payslip_elements` (JSON) | **Supplementary VIEW** (`vw_sys_hr_payslip_elements`) provides individual element rows that can be serialized to JSON. |

---

### 2.10 `CHLOAN` → `sys_helb_deductions` / `sys_prorated_deductions` (Loans)

**Mapping logic**: MSSQL has one general-purpose loan table. Laravel has two specialized tables (HELB + prorated). Columns map by financial purpose.

| # | MSSQL Column | → | Laravel Column | Target | Mapping Reason |
|---|---|---|---|---|---|
| 1 | `LORDID` | → | `staff_id` | both | Employee identifier. Resolved to integer FK via `vw_sys_staff`. |
| 2 | `LNAMT` | → | `total_amount` / `loan_balance` | both | Original loan amount. Maps to `loan_balance` for HELB, `total_amount` for prorated. |
| 3 | `AMTMONT` | → | `monthly_deduction` | both | Fixed monthly installment amount. |
| 4 | `LNBALANC` | → | `loan_balance` | helb | Current outstanding balance. |
| 5 | `INTRATE` | → | — | prorated | Interest rate. Laravel handles interest differently (principal/interest tracking on repeats). |
| 6 | `AMTPRINC` | → | `principal_amount` | prorated | Principal portion of the loan. |
| 7 | `AMTINTER` | → | — | prorated | Interest portion. Tracked in Laravel via repeat records, not on the main deduction. |
| 8 | `REPSTAT` | → | `current_status_id` | both | Repayment status. String → integer mapping ('Active'→5, 'Completed'→6). |

---

### 2.11 `CHP9A` → *(derived from PayrollSummary)* (P9A Tax Certificate)

**Mapping logic**: MSSQL has a dedicated P9A table. Laravel derives P9A data by aggregating `PayrollSummary` rows for a year. A reporting VIEW is provided.

| # | MSSQL Column | → | Laravel Source | Mapping Reason |
|---|---|---|---|---|
| 1 | `EMPNUMB` | → | `PayrollSummary.staff_id` | Employee reference. |
| 2 | `PRYEAR` + `PRMNTH` | → | `PayrollSummary.period` | Tax period. |
| 3 | `AMTA` | → | `SUM(basic_salary)` | Gross pay — sum of basic salary for the year. |
| 4 | `AMTB` | → | — | Benefits/fringe value — not separately tracked in Laravel. |
| 5 | `AMTD` | → | `SUM(nssf)` | Defined contribution (NSSF). |
| 6 | `AMTF` | → | — | Taxable pay — derived: gross − exemptions. |
| 7 | `AMTG` | → | `SUM(tax_payable)` | Tax charged. |
| 8 | `AMTH` | → | 2400 × 12 | Personal relief — hardcoded in Laravel. |
| 9 | `AMTJ` | → | `SUM(tax_payable)` | PAYE — derived from monthly payroll. |

---

## 3. Element Code Reference (CHPYSL → PayrollSummary Pivot Key)

This table shows how each MSSQL payroll element code maps to a specific column or treatment in Laravel:

| MSSQL EDCODE | MSSQL Element Name | Maps To | Type |
|---|---|---|---|
| `00001` | BASIC SALARY | `PayrollSummary.basic_salary` | Named column |
| `00002` | HOUSE ALLOWANCE | `payslip_elements` JSON | JSON element |
| `00003` | TRANSPORT ALLOWANCE | `payslip_elements` JSON | JSON element |
| `00004` | NSSF | `PayrollSummary.nssf` | Named column |
| `00005` | NHIF | `PayrollSummary.nhif` | Named column |
| `00006` | AKIBA SACCO | `payslip_elements` JSON | JSON element |
| `00007` | SALARY ADVANCE | `ProratedDeduction` | Separate table |
| `00008` | PAYE DEDUCTIONS | `payslip_elements` JSON | JSON element |
| `00009` | AKIBA SACCO LOAN | `payslip_elements` JSON | JSON element |
| `00010` | OVERTIME | `payslip_elements` JSON | JSON element |
| `00011` | RESPONSIBILITY ALLOWANCE | `payslip_elements` JSON | JSON element |
| `00012` | OTHER DEDUCTIONS | `payslip_elements` JSON | JSON element |
| `00013` | PAYE | `PayrollSummary.tax_payable` | Named column |
| `00014` | NET SALARY | `PayrollSummary.net_pay` | Named column |
| `00015` | TOTAL EARNINGS | *(computed)* | Derived |
| `00016` | GROSS SALARY | *(computed)* | Derived |
| `00017` | SAFARI ADVANCE | `payslip_elements` JSON | JSON element |
| `00018` | IMPREST | `payslip_elements` JSON | JSON element |
| `00019` | OTHER EARNINGS | `payslip_elements` JSON | JSON element |
| `00020` | HELB LOANS | `HelbDeduction` / `PayrollSummary.helb` | Named column + separate table |
| `00021` | AIRTIME | `payslip_elements` JSON | JSON element |
| `00022` | HOUSING LEVY | `PayrollSummary.housing_levy` | Named column |
| `00023` | NSSF EMPLOYER | `payslip_elements` JSON | JSON element |
| `00024` | SHIF EMPLOYER | `payslip_elements` JSON | JSON element |

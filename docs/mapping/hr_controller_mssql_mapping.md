# HR Controllers → MS SQL Table Mapping (with Reasoning)

> **MS SQL Server**: `localhost:51837` | **Database**: `chips`
> **Generated**: 2026-02-25

This document maps every HR module in the Laravel application (based on controller analysis)
to the corresponding MS SQL tables, with clear reasoning for each mapping decision.

---

## Table of Contents

1. [Staff Management](#1-staff-management)
2. [Department Management](#2-department-management)
3. [Staff Positions / Designations](#3-staff-positions--designations)
4. [Payroll Elements (Earnings & Deductions definitions)](#4-payroll-elements)
5. [Tax Bands](#5-tax-bands)
6. [Payroll Calendar & Periods](#6-payroll-calendar--periods)
7. [Payroll Processing & Payslips](#7-payroll-processing--payslips)
8. [Tax Reports (P9A)](#8-tax-reports-p9a)
9. [HELB Loan Deductions](#9-helb-loan-deductions)
10. [Prorated / Amortized Deductions](#10-prorated--amortized-deductions)
11. [SHIF (NHIF) Rates](#11-shif-nhif-rates)
12. [NSSF Rates](#12-nssf-rates)
13. [Employee Contracts](#13-employee-contracts)
14. [Employee Termination](#14-employee-termination)
15. [Discipline Tracker](#15-discipline-tracker)
16. [Leave Management](#16-leave-management)
17. [Zone Assignments](#17-zone-assignments)
18. [Workdays & Public Holidays](#18-workdays--public-holidays)
19. [Communication Templates & HR Communication](#19-communication-templates--hr-communication)
20. [Employee Attendance](#20-employee-attendance)
21. [Statutory Deductions Reporting](#21-statutory-deductions-reporting)
22. [Workflow Approvals (Maker-Checker)](#22-workflow-approvals)

---

## 1. Staff Management

**Controller**: `StaffController` → `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`, `destroy()`

### What it does
CRUD for employee records. Creates/reads/updates/deletes staff in `sys_staff`, loading
dropdowns from `Department`, `Bank`, `Position`, `Zone`, `ZoneArea`.

### Laravel Tables → MS SQL Tables

| Laravel Table | Laravel Model | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|---|
| `sys_staff` | `Staff` | `CHSTFF` | **HIGH** | Both are the main employee master table. Field-by-field match: `staff_number` ↔ `EMPNUMB`, `full_names` ↔ `FULLNAME`, `national_id` ↔ `NATIDNO`, `gender` ↔ `GENDER`, `birth_date` ↔ `EMPDOB`, `mobile_number` ↔ `MOBNUM1`, `email_address` ↔ `WEMAIL`, `pin_number` ↔ `NUMPIN`, `nhif_number` ↔ `NUMNHIF`, `nssf_number` ↔ `NUMNSSF`, `district` ↔ `DISTRCT`, `location` ↔ `IDLOCAT`, etc. |
| `sys_set_departments` | `Department` | `CHDEPT` | **HIGH** | Both store department names. `department_name` ↔ `DEPTNAME`. Staff FK: `department_id` ↔ `DEPTCODE`. |
| `sys_set_banks` | `Bank` | `CHBANK` | **HIGH** | Both store bank names. `bank_name` ↔ `BANKNAME`. Staff FK: `bank_name` (int) ↔ `BANKCODE`. |
| `sys_set_staff_positions` | `Position` | `CHDESN` | **HIGH** | Both store job titles. `position_name` ↔ `DESGINNAME`. Staff FK: `position_id` ↔ `DESIGN`. |
| `sys_set_zones` | `Zone` | **NO MATCH** | — | MSSQL has no zone concept; zones are Laravel-only for property management field operations. |
| `sys_set_zone_areas` | `ZoneArea` | **NO MATCH** | — | Same — zone areas are Laravel-only. |
| *(not in Laravel)* | — | `CHSNOK` | **PARTIAL** | MSSQL stores NOK in a separate table (multiple rows per employee); Laravel collapses this into `sys_staff.kins_names` (single field). |
| `sys_set_banks_branches` | `BankBranch` | `CHBBRA` | **HIGH** | Both store bank branch names linked to a parent bank. `BANKBRAN` → `CHBBRA.BRANCODE` / `branch_id` → `sys_set_banks_branches.id`. Fields: `branch_code` ↔ `BRANCODE`, `branch_name` ↔ `BRANNAME`, `bank_id` ↔ `BANKCODE`. |
| *(not in Laravel)* | — | `CHGRDS` | **LOW** | Pay grades (MAN/NMA) — not in Laravel. `CHSTFF.GRADE` links here. |
| *(not in Laravel)* | — | `CHEMPT` | **LOW** | Employee types (e.g., permanent, contract) — not in Laravel. `CHSTFF.EMPTYPE` links here. |

### Key Differences
- **IDs**: Laravel uses auto-increment `int` PKs; MSSQL uses `nvarchar` codes (e.g., `'00001'`).
- **Next of Kin**: MSSQL has `CHSNOK` (1-to-many), Laravel has single `kins_names` string field.
- **Salary placement**: MSSQL stores `SALBASC` on `CHSTFF`; Laravel stores `basic_salary` on `Position` (and now on `Contract`).

---

## 2. Department Management

**Controller**: `HrSettingsController` → `departmentIndex()`, `departmentList()`, `departmentStore()`, `departmentUpdate()`, `departmentDelete()`

### What it does
Full CRUD on `sys_set_departments`. Stores `department_name` and `date_time`.

### Mapping

| Laravel Table | MS SQL Table | Reasoning |
|---|---|---|
| `sys_set_departments` | `CHDEPT` | **Direct match**. Both have only 2 functional fields: an ID/code and a name. MSSQL `CHDEPT` has `DEPTCODE` + `DEPTNAME`. Laravel has `id` + `department_name`. The 4 MSSQL rows (ADMINISTRATION, ACCOUNTS, FIELD, HR) are the same departments used in the CHSTFF→DEPTCODE FK. |

### VIEW Statement
```sql
CREATE VIEW vw_sys_set_departments AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DEPTCODE) AS id,
    GETDATE()                              AS date_time,
    DEPTNAME                               AS department_name,
    DEPTCODE                               AS mssql_dept_code
FROM CHDEPT;
```

---

## 3. Staff Positions / Designations

**Controller**: `HrSettingsController` → `positionsIndex()`, `positionsList()`, `positionsStoreAjax()`, `positionsUpdateAjax()`, `positionsShow()`, `positionsUpdateElements()`

### What it does
CRUD on `sys_set_staff_positions`. Each position has `position_name`, `basic_salary`, `reports_to_id` (self-referencing FK), and `salary_account_id` (FK to chart of accounts). Also manages payroll element assignments per position via pivot table.

### Mapping

| Laravel Table | MS SQL Table | Reasoning |
|---|---|---|
| `sys_set_staff_positions` | `CHDESN` | **Direct match** for the core data. Both store job title names. MSSQL `DESIGNCODE`/`DESGINNAME` ↔ Laravel `id`/`position_name`. However, Laravel's Position model is richer: it has `basic_salary`, `reports_to_id`, `salary_account_id`, and a pivot to payroll elements — none of which exist in MSSQL's `CHDESN`. |
| `sys_hr_payroll_elements_repeat_staff_positions_id` (pivot) | **NO MATCH** | The payroll-element-to-position mapping is Laravel-only. MSSQL has `CHPYSL` records per employee per element, not per position. |

### Key Difference
MSSQL `CHDESN` is a simple 2-column lookup. Laravel adds salary, hierarchy, GL account, and element assignments.

### VIEW Statement
```sql
CREATE VIEW vw_sys_set_staff_positions AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DESIGNCODE) AS id,
    GETDATE()                                AS date_time,
    DESGINNAME                               AS position_name,
    NULL                                     AS basic_salary,
    NULL                                     AS salary_account_id,
    1                                        AS current_status_id,
    NULL                                     AS reports_to_id,
    DESIGNCODE                               AS mssql_design_code
FROM CHDESN;
```

---

## 4. Payroll Elements

**Controller**: `PayrollController` → `payrollElementsIndex()`, `payrollElementsList()`, `payrollElementsStore()`, `payrollElementsUpdate()`, `payrollElementsDelete()`

### What it does
Manages payroll earning/deduction **definitions** (not actual payslip values). Each element has: `element_name`, `element_type` (1=Deduction, 2=Allowance), `formula_id`, `amount`, `account_id`, `staff_positions_id`, `element_key` (e.g., `'nssf'`, `'nhif'`, `'payee'`).

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_payroll_elements` | `CHPREDS` | **HIGH** | Both define the catalogue of payroll earnings and deductions. **CHPREDS** has 24 elements (BASIC SALARY, NSSF, NHIF, PAYE, HELB LOANS, HOUSING LEVY, etc.) with `EDCODE`, `EDNAME`, `EDTYPE` (Earning/Deduction/Saving), `EDRATE`, `GLACCOUNT`, `TAXABLE`, `RECURR`, `TYPECHR` (Fixed/Computed/Depends on Staff). Laravel's `PayrollElement` mirrors this with `element_name`, `element_type`, `formula_id`, `amount`, `account_id`. |

### Column Mapping

| CHPREDS Column | sys_hr_payroll_elements Column | Notes |
|---|---|---|
| `EDCODE` | `id` / `element_key` | MSSQL uses string codes; Laravel uses int PK + element_key |
| `EDNAME` | `element_name` | Both store the element name |
| `EDTYPE` | `element_type` | MSSQL: 'Earning'/'Deduction'/'Saving'; Laravel: 1=Deduction, 2=Allowance |
| `EDRATE` | `amount` | Fixed rate/amount for the element |
| `GLACCOUNT` | `account_id` | FK to chart of accounts / GL account |
| `TAXABLE` | *(handled in formula logic)* | Whether element is tax-deductible |
| `RECURR` | *(handled in formula logic)* | Recurring vs one-time |
| `TYPECHR` | `formula_id` | 'Fixed'/'Computed'/'Depends on Staff' ↔ formula 1-8 |

### VIEW Statement
```sql
CREATE VIEW vw_sys_hr_payroll_elements AS
SELECT
    ROW_NUMBER() OVER (ORDER BY EDCODE) AS id,
    GETDATE()                            AS date_time,
    EDNAME                               AS element_name,
    CASE
        WHEN EDTYPE = 'Deduction' THEN 1
        WHEN EDTYPE = 'Earning'   THEN 2
        WHEN EDTYPE = 'Saving'    THEN 1  -- Treat savings as deductions
        ELSE NULL
    END                                  AS element_type,
    CASE
        WHEN TYPECHR = 'Fixed'            THEN 1
        WHEN TYPECHR = 'Computed'         THEN 2
        WHEN TYPECHR = 'Depends on Staff' THEN 3
        ELSE NULL
    END                                  AS formula_id,
    EDRATE                               AS amount,
    GLACCOUNT                            AS mssql_gl_account,
    NULL                                 AS account_id,
    NULL                                 AS staff_positions_id,
    CASE EDNAME
        WHEN 'NSSF'          THEN 'nssf'
        WHEN 'NHIF'          THEN 'nhif'
        WHEN 'HOUSING LEVY'  THEN 'housing_levy'
        WHEN 'PAYE'          THEN 'payee'
        WHEN 'HELB LOANS'    THEN 'helb_loans'
        WHEN 'BASIC SALARY'  THEN 'basic_salary'
        WHEN 'NET SALARY'    THEN 'net_salary'
        ELSE LOWER(REPLACE(EDNAME, ' ', '_'))
    END                                  AS element_key,
    5                                    AS current_status_id,
    EDCODE                               AS mssql_ed_code,
    TAXABLE                              AS mssql_taxable,
    RECURR                               AS mssql_recurring
FROM CHPREDS;
```

---

## 5. Tax Bands

**Controller**: `PayrollController` → `taxBandsIndex()`, `taxBandsList()`, `taxBandsStore()`, `taxBandsUpdate()`

### What it does
Manages progressive income tax bands used in PAYE calculation. Each band has `range_from`, `range_to`, `tax_rate`, `is_last`.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_tax_bands` | `CHPTABS` | **HIGH** | Both define PAYE tax brackets. MSSQL `CHPTABS` stores year/month-specific bands with `CATMIN` (range_from), `CATMAX` (range_to), `CATRAT` (tax_rate). Sample data: Band A: 0–24,000 @10%, Band B: 24,001–32,333 @25%, Band D: 32,334–999,999,999 @30%. Laravel's `TaxBand` has `range_from`, `range_to`, `tax_rate`, `is_last`. |

### Column Mapping

| CHPTABS Column | sys_hr_tax_bands Column | Notes |
|---|---|---|
| `CATMIN` | `range_from` | Lower bound |
| `CATMAX` | `range_to` | Upper bound (NULL if is_last) |
| `CATRAT` | `tax_rate` | Percentage |
| `CATEGO` | *(no match)* | Band category letter (A, B, D) |
| `PYEAR` / `PMONTH` | *(no match)* | MSSQL is year/month-specific; Laravel is current |

### VIEW Statement
```sql
CREATE VIEW vw_sys_hr_tax_bands AS
SELECT
    ROW_NUMBER() OVER (ORDER BY t.CATMIN) AS id,
    GETDATE()                              AS date_time,
    t.CATMIN                               AS range_from,
    CASE WHEN t.CATMAX >= 999999999 THEN NULL ELSE t.CATMAX END AS range_to,
    t.CATRAT                               AS tax_rate,
    CASE WHEN t.CATMAX >= 999999999 THEN 1 ELSE 0 END AS is_last,
    'system'                               AS added_by,
    t.PYEAR                                AS mssql_year,
    t.PMONTH                               AS mssql_month
FROM CHPTABS t
WHERE t.PYEAR = (SELECT MAX(PYEAR) FROM CHPTABS)
  AND t.PMONTH = (SELECT MAX(PMONTH) FROM CHPTABS WHERE PYEAR = (SELECT MAX(PYEAR) FROM CHPTABS));
```

---

## 6. Payroll Calendar & Periods

**Controller**: `PayrollController` → `calendarIndex()`, `calendarStore()`, `periodsList()`, `periodsStore()`, etc.

### What it does
Manages payroll months (calendar) and date-range periods within each month. A calendar has a `payroll_month_date` (e.g., `202512`), and periods have `start_date`, `end_date`, `period_number`.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_payroll_calendar` | `CHPRDS` | **HIGH** | Both track payroll periods by year/month. MSSQL `CHPRDS` uses `PRYEAR + PRMNTH` as the composite key and stores status (Open/Closed), open/close dates, company info, tax thresholds. Laravel uses `payroll_month_date` (YYYYMM string). |
| `sys_hr_payroll_periods` | *(embedded in CHPRDS)* | **MEDIUM** | MSSQL doesn't separate periods from calendars — each `CHPRDS` row IS a period. Laravel has a parent-child split (Calendar→Periods) allowing multiple periods per month. |

### Column Mapping

| CHPRDS Column | Laravel Column | Notes |
|---|---|---|
| `PRYEAR` + `PRMNTH` | `payroll_month_date` | Combined as YYYYMM |
| `PRSTATUS` | `current_status_id` | 'Open'/'Closed' ↔ 5/inactive |
| `OPEDATE` | `start_date` (on Period) | Opening date |
| `CLSDATE` | `end_date` (on Period) | Closing date |
| `TAXTHRS` | *(in tax bands)* | Tax threshold — stored separately in Laravel |
| `TAXRLF` | *(in PAYE calc)* | Personal relief — hardcoded in Laravel at 2,400 |
| `COPIN` / `CONAME` / `COADDRE` | *(app config)* | Company details — in Laravel these come from settings |

---

## 7. Payroll Processing & Payslips

**Controller**: `PayrollController` → `processPayroll()`, `calculatePayroll()`, `savePayrollSummary()`
**Controller**: `PayslipsController` → `viewPayslip()`, `downloadPayslip()`, `emailPayslip()`

### What it does
Processes payroll for a period: iterates active contracts, calculates NSSF/NHIF/Housing Levy/PAYE, deducts HELB and prorated deductions, saves to `sys_hr_payroll_summary`. PayslipsController retrieves and renders/exports payslips.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_payroll_summary` | `CHPYSL` | **HIGH** | Both store processed payroll results per employee per period. MSSQL `CHPYSL` stores individual element lines per employee: `EMPNUMB`, `PRYEAR`, `PRMNTH`, `EDCODE`, `EDNAME`, `EDAMT`, `YTD`. Laravel's `PayrollSummary` stores aggregated amounts: `basic_salary`, `nssf`, `nhif`, `housing_levy`, `tax_payable`, `total_deductions`, `net_pay`, plus a `payslip_elements` JSON column for the per-element breakdown. |
| *(same)* | `CHP9A` | **MEDIUM** | MSSQL `CHP9A` is the P9A tax form data (annual tax certificate). It stores: `AMTA` (gross pay), `AMTB` (fringe benefits), `AMTD` (NSSF), `AMTE1`-`AMTE3` (allowances), `AMTF` (taxable pay), `AMTG` (tax charged), `AMTH` (personal relief), `AMTJ` (PAYE). Laravel doesn't have a separate P9A table — this data is derived from `PayrollSummary`. |

### Key Structural Difference
- **MSSQL `CHPYSL`**: One row per employee per element per month (normalized). E.g., employee 001 has separate rows for BASIC SALARY, NSSF, NHIF, TAX PAYABLE, NET PAY.
- **Laravel `sys_hr_payroll_summary`**: One row per employee per period (denormalized), with statutory columns (`nssf`, `nhif`, `housing_levy`) and a JSON `payslip_elements` array for the detailed breakdown.

### VIEW Statement (MSSQL→Laravel shape)
```sql
CREATE VIEW vw_sys_hr_payroll_summary AS
SELECT
    ROW_NUMBER() OVER (ORDER BY e.EMPNUMB, e.PRYEAR, e.PRMNTH) AS id,
    GETDATE() AS date_time,
    s_vw.id AS staff_id,
    e.EMPNUMB AS staff_number_id,
    DATEFROMPARTS(e.PRYEAR, e.PRMNTH, 1) AS period,
    ISNULL(basic.EDAMT, 0) AS basic_salary,
    ISNULL(paye.EDAMT, 0) AS tax_payable,
    ISNULL(nhif.EDAMT, 0) AS nhif,
    ISNULL(nssf.EDAMT, 0) AS nssf,
    ISNULL(ahl.EDAMT, 0) AS housing_levy,
    ISNULL(ded.EDAMT, 0) AS total_deductions,
    ISNULL(net.EDAMT, 0) AS net_pay,
    5 AS current_status_id
FROM (SELECT DISTINCT EMPNUMB, PRYEAR, PRMNTH FROM CHPYSL) e
LEFT JOIN vw_sys_staff s_vw ON e.EMPNUMB = s_vw.staff_number
LEFT JOIN CHPYSL basic ON e.EMPNUMB = basic.EMPNUMB AND e.PRYEAR = basic.PRYEAR AND e.PRMNTH = basic.PRMNTH AND basic.EDCODE = '00001'
LEFT JOIN CHPYSL paye  ON e.EMPNUMB = paye.EMPNUMB  AND e.PRYEAR = paye.PRYEAR  AND e.PRMNTH = paye.PRMNTH  AND paye.EDCODE = '00013'
LEFT JOIN CHPYSL nhif  ON e.EMPNUMB = nhif.EMPNUMB  AND e.PRYEAR = nhif.PRYEAR  AND e.PRMNTH = nhif.PRMNTH  AND nhif.EDCODE = '00005'
LEFT JOIN CHPYSL nssf  ON e.EMPNUMB = nssf.EMPNUMB  AND e.PRYEAR = nssf.PRYEAR  AND e.PRMNTH = nssf.PRMNTH  AND nssf.EDCODE = '00004'
LEFT JOIN CHPYSL ahl   ON e.EMPNUMB = ahl.EMPNUMB   AND e.PRYEAR = ahl.PRYEAR   AND e.PRMNTH = ahl.PRMNTH   AND ahl.EDCODE = '00022'
LEFT JOIN CHPYSL ded   ON e.EMPNUMB = ded.EMPNUMB   AND e.PRYEAR = ded.PRYEAR   AND e.PRMNTH = ded.PRMNTH   AND ded.EDNAME = 'TOTAL DEDUCTIONS'
LEFT JOIN CHPYSL net   ON e.EMPNUMB = net.EMPNUMB   AND e.PRYEAR = net.PRYEAR   AND e.PRMNTH = net.PRMNTH   AND net.EDCODE = '00014';
```

---

## 8. Tax Reports (P9A)

**Controller**: (generated from PayrollSummary data; no dedicated controller)

### Mapping

| Laravel Source | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| Derived from `sys_hr_payroll_summary` | `CHP9A` | **HIGH** | MSSQL has a dedicated `CHP9A` table for annual P9A tax certificates. Fields: `PRYEAR`, `PRMNTH`, `EMPNUMB`, `AMTA`–`AMTL` (gross pay, benefits, NSSF, allowances, taxable pay, tax, relief, PAYE). Laravel would derive equivalent data by summing `PayrollSummary` rows for a given year. |

---

## 9. HELB Loan Deductions

**Controller**: `DeductionsController` → `helbIndex()`, `helbStore()`, `helbUpdate()`, `helbShow()`, `helbRepeatsList()`

### What it does
Manages HELB (Higher Education Loans Board) loan deductions. Stores the loan with `staff_id`, `loan_balance`, `monthly_deduction`, `start_deduction_date`. Each payroll run creates `HelbDeductionRepeat` records to track amounts actually deducted.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_helb_deductions` | `CHLOAN` (partial) | **MEDIUM** | MSSQL `CHLOAN` is a general loan table (not HELB-specific). It stores: `LORDID` (landlord/employee ID), `LNAMT` (loan amount), `INTRATE` (interest rate), `AMTMONT` (monthly amount), `LNBALANC` (balance), `REPSTAT` (repayment status). HELB loans in MSSQL would be rows in `CHLOAN`. Laravel separates this into a dedicated HELB table. Match keys: `loan_balance` ↔ `LNBALANC`, `monthly_deduction` ↔ `AMTMONT`. |
| `sys_helb_deduction_repeats` | *(in CHPYSL)* | **MEDIUM** | MSSQL tracks HELB deductions as `CHPYSL` rows with `EDCODE='00020'` (HELB LOANS). Laravel tracks them in a separate repeats table with balance progression. |

### Reasoning for MEDIUM confidence
`CHLOAN` appears to be a multi-purpose loan table (it has `VENDORID`, `BANKN`, `CHEQNO` fields suggesting it also handles vendor/landlord loans). Laravel has a dedicated HELB table which is cleaner but narrower.

---

## 10. Prorated / Amortized Deductions

**Controller**: `DeductionsController` → `proratedIndex()`, `proratedStore()`, `proratedUpdate()`, `proratedRepeatsList()`

### What it does
Manages recurring deductions split over time (e.g., salary advances, overpayments). Has `total_amount`, `principal_amount`, `monthly_deduction`, and repeat tracking with interest/principal split.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_prorated_deductions` | `CHLOAN` + `CHPYSL` | **MEDIUM** | MSSQL handles these as loans (`CHLOAN` with `AMTPRINC`, `AMTINTER` for principal/interest split) and CHPYSL line items (e.g., EDCODE `00007` SALARY ADVANCE, `00009` AKIBA SACCO LOAN, `00012` OTHER DEDUCTIONS). Laravel's approach is more structured with separate repeat tracking. |
| `sys_prorated_deduction_repeats` | *(in CHPYSL)* | **MEDIUM** | Each deduction instance appears as a CHPYSL row in the payroll month. |

---

## 11. SHIF (NHIF) Rates

**Controller**: `HrSettingsController` → `shifRatesIndex()`, `shifRatesList()`, `shifRatesStore()`, `shifRatesUpdate()`

### What it does
Manages SHIF/NHIF rate settings stored in `sys_hr_shif_rates`. Each rate has `rate` (percentage), `from_date` (effective date).

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_shif_rates` | `CHPRDS.NHIFRATE` + `CHPRDS.CONHIF` | **LOW** | MSSQL doesn't have a separate NHIF rates table. Instead, it stores the NHIF rate inline on each payroll period row (`CHPRDS.NHIFRATE`). The NHIF contributor number is in `CHPRDS.CONHIF`. Laravel decouples this into its own settings table with date-based effectivity. Additionally, `CHPREDS` row for NHIF (EDCODE='00005') has `EDRATE=320.0` as a fixed rate. |

---

## 12. NSSF Rates

**Controller**: `HrSettingsController` → `nssfRatesIndex()`, `nssfRatesList()`, `nssfRatesStore()`, `nssfRatesUpdate()`

### What it does
Manages NSSF rate settings: `employee_perc`, `employer_perc`, `maximum_contribution`, `taxable`, `from_date`.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_nssf_rates` | `CHPRDS.CONSSF` + `CHPREDS` | **LOW** | Like NHIF, MSSQL doesn't have a separate NSSF rates table. `CHPRDS.CONSSF` stores the NSSF contributor number. `CHPREDS` row for NSSF (EDCODE='00004') has `EDRATE=2160.0` as a fixed amount. Laravel provides richer rate management with date effectivity, employer/employee split, and max contribution caps. |

---

## 13. Employee Contracts

**Controller**: `StaffController` → `contractsIndex()`, `contractStore()`, `contractUpdate()`, `contractList()`

### What it does
Manages employment contracts linking staff to positions with date ranges, basic salary, and assigned payroll elements. Each contract has `staff_id`, `position_id`, `date_start`, `date_end`, `basic_salary`, plus child `elements` (payroll element assignments with amounts).

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_contracts` | **NO DIRECT MATCH** | — | MSSQL has no separate contracts table. Employment details (position, salary, start date) are stored directly on `CHSTFF` fields: `DESIGN` (position), `SALBASC` (salary), `APPDATE` (appointment date). Laravel's contract system is a newer, richer abstraction that supports multiple contracts, date ranges, and element overrides per contract. |
| `sys_hr_contract_elements` | **NO MATCH** | — | Per-contract payroll element assignments are Laravel-only. MSSQL handles element amounts through `CHPYSL` entries at payroll processing time. |

---

## 14. Employee Termination

**Controller**: `StaffController` → `employeeTerminationIndex()`, `employeeTerminationStore()`, `employeeTerminationUpdate()`

### What it does
Records employee terminations with severance calculations: `years_served`, `pay_per_year`, `severance_payment`, `termination_reason_id`, document uploads, and severance elements (element_id + amount).

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_employee_terminations` | `CHSTFF.CURRSTAT` | **LOW** | MSSQL tracks termination only as a status change on `CHSTFF.CURRSTAT = 'Inactive'`. There is no separate termination table, no severance calculation, no document tracking. Laravel has a full termination workflow with costs, reasons, documents. |

---

## 15. Discipline Tracker

**Controller**: `StaffController` → discipline methods + `DisciplineTrackerController` (14-step workflow)

### What it does
Two systems exist:
1. **Legacy**: Simple incident logging with `StaffDiscipline`, `Incident`, `ActionTaken` models.
2. **New 14-Step Workflow**: Full discipline case lifecycle: incident report → case registration → investigator assignment → evidence → witness statements → notes → show cause → employee response → hearing → outcome → training → history update → case closure.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_discipline_*` (multiple tables) | **NO MATCH** | — | MSSQL has no discipline tracking system whatsoever. This is entirely new functionality built in Laravel. |

---

## 16. Leave Management

**Controller**: `LeaveManagementController` → calendar plans, applications, adjustments, balance reports

### What it does
Full leave management: leave type definitions, annual calendar plans per employee, leave applications with approval workflow, leave adjustments, and balance reporting.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_leave_types` | **NO MATCH** | — | MSSQL has no leave management system. |
| `sys_hr_leave_calendar_plans` | **NO MATCH** | — | Laravel-only. |
| `sys_hr_leave_applications` | **NO MATCH** | — | Laravel-only. |
| `sys_hr_leave_adjustments` | **NO MATCH** | — | Laravel-only. |

---

## 17. Zone Assignments

**Controller**: `HrSettingsController` → `zoneAssignmentsIndex()`, `zoneAssignmentsStore()`, etc.

### What it does
Assigns staff to zones/properties with assignment types (Field Manager, Field Officer, etc.).

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_zone_assignments` | **NO MATCH** | — | Zones and zone assignments are property management concepts that don't exist in MSSQL. MSSQL's staff table has `EMPLOC` (employee location) which is the closest equivalent, but it's just a text field. |

---

## 18. Workdays & Public Holidays

**Controller**: `HrSettingsController` → `workdaysIndex()`, `publicHolidaysIndex()`, etc.

### What it does
- **Workdays**: Defines which days of the week are working/off days (Mon–Sun).
- **Public Holidays**: Named holidays with date ranges, pay rates, recurrence settings.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_workdays` | **NO MATCH** | — | MSSQL has no workday configuration. |
| `sys_hr_public_holidays` | **NO MATCH** | — | MSSQL has no public holiday table. |

---

## 19. Communication Templates & HR Communication

**Controller**: `HrSettingsController` → templates CRUD; `StaffController` → `communicationSend()`

### What it does
Manages email/SMS templates (contract letters, disciplinary notices, leave notifications, general communications) and sends communications to staff.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_communication_templates` | **NO MATCH** | — | MSSQL has no communication/template system. |
| `sys_hr_communications` | **NO MATCH** | — | Laravel-only. |

---

## 20. Employee Attendance

**Controller**: `StaffController` → `attendanceIndex()`, `attendanceList()`, `attendanceUpload()`

### What it does
Tracks employee attendance via CSV uploads: date, time in, time out, hours worked per day.

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_hr_employee_attendance` | **NO MATCH** | — | MSSQL has no attendance tracking system. |

---

## 21. Statutory Deductions Reporting

**Controller**: `HrReportsController` → `statutoryDeductions()`, `getStatutoryDeductions()`, `downloadStatutoryDeductions()`

### What it does
Reads from `sys_hr_payroll_summary` to produce NSSF, SHIF, and Housing Levy reports by period. No separate table — pure reporting on payroll data.

### Mapping

| Laravel Source | MS SQL Source | Reasoning |
|---|---|---|
| `sys_hr_payroll_summary.nssf` | `CHPYSL WHERE EDCODE='00004'` | Same data, different structure |
| `sys_hr_payroll_summary.nhif` | `CHPYSL WHERE EDCODE='00005'` | Same data, different structure |
| `sys_hr_payroll_summary.housing_levy` | `CHPYSL WHERE EDCODE='00022'` | Same data, different structure |

---

## 22. Workflow Approvals

**Controller**: `WorkflowApprovalController` → `index()`, `approvalsList()`, `approve()`, `reject()`, `bulkApprove()`, `bulkReject()`

### What it does
Maker-checker approval workflow for various HR actions (leave applications, etc.).

### Mapping

| Laravel Table | MS SQL Table | Confidence | Reasoning |
|---|---|---|---|
| `sys_workflow_approvals` | **NO MATCH** | — | MSSQL has no approval workflow system. |

---

## Consolidated Column-Level Mappings

### Staff Master: `CHSTFF` → `sys_staff`

| MSSQL Column | MSSQL Type | Laravel Column | Laravel Type | Notes |
|---|---|---|---|---|
| `EMPNUMB` | nvarchar(10) | `staff_number` | varchar | Employee number |
| `FULLNAME` | nvarchar(100) | `full_names` | varchar | Full name |
| `SURNAME` | nvarchar(50) | — | — | Laravel uses full_names only |
| `OTHRNAM` | nvarchar(50) | — | — | Laravel uses full_names only |
| `GENDER` | nvarchar(6) | `gender` | varchar | |
| `EMPDOB` | smalldatetime | `birth_date` | date | |
| `NATIDNO` | nvarchar(10) | `national_id` | varchar | |
| `EMPADDR` | varchar(50) | `address` | varchar | |
| `MOBNUM1` | varchar(13) | `mobile_number` | varchar | |
| `MOBNUM2` | varchar(13) | — | — | Not in Laravel |
| `WEMAIL` | nvarchar(100) | `email_address` | varchar | |
| `PEMAIL` | nvarchar(100) | `personal_email` | varchar | |
| `NUMPIN` | nvarchar(50) | `pin_number` | varchar | KRA PIN |
| `NUMNHIF` | nvarchar(50) | `nhif_number` | varchar | |
| `NUMNSSF` | nvarchar(50) | `nssf_number` | varchar | |
| `BANKCODE` | nvarchar(20) | `bank_name` | int (FK) | String code → int FK |
| `BANKBRAN` | nvarchar(20) | `branch_id` | int (FK) | FK to bank branches. String code → int FK via `CHBBRA.BRANCODE` → `sys_set_banks_branches.id` |
| `BANKACC` | varchar(30) | `account_number` | varchar | |
| `DEPTCODE` | nvarchar(50) | `department_id` | int (FK) | String code → int FK |
| `DESIGN` | nvarchar(50) | `position_id` | int (FK) | String code → int FK |
| `SALBASC` | money | — | — | In Laravel this is on Position/Contract |
| `GRADE` | nvarchar(10) | — | — | Grades not in Laravel |
| `EMPTYPE` | nvarchar(20) | — | — | Employee types not in Laravel |
| `CURRSTAT` | nvarchar(20) | `current_status_id` | int | 'Active'→1, 'Inactive'→2 |
| `APPDATE` | varchar(25) | `hire_date` | datetime | |
| `DISTRCT` | nvarchar(30) | `district` | varchar | |
| `IDLOCAT` | nvarchar(30) | `location` | varchar | |
| `IDSUBLOC` | nvarchar(30) | `sub_location` | varchar | |
| `MSTATUS` | nvarchar(10) | `marital` | varchar | |
| `SPOUSNAM` | nvarchar(60) | `spouse_name` | varchar | |
| `PLACEOB` | nvarchar(50) | `birth_place` | varchar | |
| `CITIZEN` | varchar(13) | `citizenship` | varchar | |
| `PRMSPAS` | varchar(50) | `system_password` | varchar | |
| `SYSGRP` | varchar(50) | `permission_group_id` | int | |
| — | — | `passport` | varchar | Not in MSSQL |
| — | — | `photo` | varchar | Not in MSSQL |
| — | — | `uid` | varchar | Auto-generated |
| — | — | `zone_id` | int (FK) | Not in MSSQL |
| — | — | `zone_area_id` | int (FK) | Not in MSSQL |
| — | — | `kins_names` | varchar | In MSSQL → separate CHSNOK table |
| — | — | `account_name` | varchar | Not in MSSQL |
| — | — | `disability` | varchar | Not in MSSQL |

### Next of Kin: `CHSNOK` → `sys_staff.kins_names`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `EMPNUMB` | `staff_number` | FK link |
| `NOKNAM` | `kins_names` | 1:many → single field |
| `NOKRELA` | — | Relationship type |
| `NOKTYPE` | — | NOK type |
| `NOKADDR` | — | Address |
| `NOKCITY` | — | City |
| `NOKTEL` | — | Phone |

### Departments: `CHDEPT` → `sys_set_departments`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `DEPTCODE` | `id` | String → int PK |
| `DEPTNAME` | `department_name` | Direct match |
| — | `date_time` | Not in MSSQL |

### Positions: `CHDESN` → `sys_set_staff_positions`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `DESIGNCODE` | `id` | String → int PK |
| `DESGINNAME` | `position_name` | Direct match |
| — | `basic_salary` | In MSSQL this is on CHSTFF.SALBASC |
| — | `salary_account_id` | Not in MSSQL |
| — | `reports_to_id` | Not in MSSQL |
| — | `current_status_id` | Not in MSSQL |

### Banks: `CHBANK` → `sys_set_banks`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `BANKCODE` | `id` | String → int PK |
| `BANKNAME` | `bank_name` | Direct match |
| — | `address` | Not in MSSQL |
| — | `current_status_id` | Not in MSSQL |

### Bank Branches: `CHBBRA` → `sys_set_banks_branches`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `BRANCODE` | `id` / `branch_code` | Branch identifier. String code → int PK + original code preserved |
| `BRANNAME` | `branch_name` | Direct match — branch display name |
| `BANKCODE` | `bank_id` | FK to parent bank. String code → int FK |
| — | `added_by` | Not in MSSQL |
| — | `added_on` | Not in MSSQL |

### Payroll Elements: `CHPREDS` → `sys_hr_payroll_elements`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `EDCODE` | `id` / `element_key` | String code → int PK + slug key |
| `EDNAME` | `element_name` | Direct match |
| `EDTYPE` | `element_type` | 'Earning'→2, 'Deduction'/'Saving'→1 |
| `EDRATE` | `amount` | Fixed rate/amount |
| `GLACCOUNT` | `account_id` | GL account reference |
| `TAXABLE` | — | Handled in formula logic |
| `RECURR` | — | Handled in formula logic |
| `TYPECHR` | `formula_id` | 'Fixed'→1, 'Computed'→2, 'Depends on Staff'→3 |
| — | `staff_positions_id` | Not in MSSQL |

### Tax Bands: `CHPTABS` → `sys_hr_tax_bands`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `CATMIN` | `range_from` | Lower bound |
| `CATMAX` | `range_to` | Upper bound (NULL if last band) |
| `CATRAT` | `tax_rate` | Percentage |
| `CATEGO` | — | Band category letter (A/B/D) |
| `PYEAR` / `PMONTH` | — | Year/month-specific in MSSQL; single set in Laravel |
| — | `is_last` | Derived from CATMAX ≥ 999999999 |
| — | `added_by` | Not in MSSQL |

### Payroll Periods: `CHPRDS` → `sys_hr_payroll_calendar` + `sys_hr_payroll_periods`

| MSSQL Column | Laravel Column | Target Table | Notes |
|---|---|---|---|
| `PRYEAR` + `PRMNTH` | `payroll_month_date` | calendar | Combined as YYYYMM |
| `PRSTATUS` | `current_status_id` | calendar | 'Open'→5, 'Closed'→inactive |
| `OPEDATE` | `start_date` | periods | Opening date |
| `CLSDATE` | `end_date` | periods | Closing date |
| `TAXTHRS` | — | — | Tax threshold (in Laravel → tax bands) |
| `TAXRLF` | — | — | Personal relief (hardcoded 2,400 in Laravel) |
| `COPIN` / `CONAME` | — | — | Company info (in Laravel → settings) |

### Payroll Summary: `CHPYSL` → `sys_hr_payroll_summary`

| MSSQL Column | Laravel Column | Notes |
|---|---|---|
| `EMPNUMB` | `staff_number_id` / `staff_id` | Employee reference |
| `PRYEAR` + `PRMNTH` | `period` | Period date |
| `EDCODE='00001'` EDAMT | `basic_salary` | BASIC SALARY element |
| `EDCODE='00004'` EDAMT | `nssf` | NSSF element |
| `EDCODE='00005'` EDAMT | `nhif` | NHIF element |
| `EDCODE='00022'` EDAMT | `housing_levy` | HOUSING LEVY element |
| `EDCODE='00013'` EDAMT | `tax_payable` | PAYE element |
| `EDNAME='TOTAL DEDUCTIONS'` EDAMT | `total_deductions` | Aggregated row |
| `EDCODE='00014'` EDAMT | `net_pay` | NET SALARY element |
| All EDCODE rows | `payslip_elements` (JSON) | MSSQL normalized → Laravel JSON array |

### Loans: `CHLOAN` → `sys_helb_deductions` / `sys_prorated_deductions`

| MSSQL Column | Laravel Column | Target Table | Notes |
|---|---|---|---|
| `LORDID` | `staff_id` | both | Employee FK |
| `LNAMT` | `total_amount` / `loan_balance` | both | Loan amount |
| `AMTMONT` | `monthly_deduction` | both | Monthly installment |
| `LNBALANC` | `loan_balance` | helb | Running balance |
| `INTRATE` | — | prorated | Interest rate (Laravel uses principal/interest split) |
| `AMTPRINC` | `principal_amount` | prorated | Principal portion |
| `AMTINTER` | — | prorated | Interest portion |
| `REPSTAT` | `current_status_id` | both | Repayment status |

---

## Summary: Mapping Confidence Matrix

| # | Laravel Module | MS SQL Table(s) | Confidence | Status |
|---|---|---|---|---|
| 1 | Staff CRUD | `CHSTFF` + `CHSNOK` | **HIGH** | Direct field mapping available |
| 2 | Departments | `CHDEPT` | **HIGH** | Simple 1:1 match |
| 3 | Positions | `CHDESN` | **HIGH** (partial) | Name matches; Laravel adds salary/hierarchy |
| 4 | Payroll Elements | `CHPREDS` | **HIGH** | Both define E/D catalogue |
| 5 | Tax Bands | `CHPTABS` | **HIGH** | Both define PAYE brackets |
| 6 | Payroll Periods | `CHPRDS` | **HIGH** | Year/month period tracking |
| 7 | Payroll Processing | `CHPYSL` | **HIGH** | Processed payslip data |
| 8 | P9A Tax Reports | `CHP9A` | **HIGH** | Annual tax certificate |
| 9 | HELB Deductions | `CHLOAN` (partial) | **MEDIUM** | General loan table, not HELB-specific |
| 10 | Prorated Deductions | `CHLOAN` + `CHPYSL` | **MEDIUM** | Split across tables |
| 11 | SHIF Rates | `CHPRDS` (inline) | **LOW** | Stored on period, not separate table |
| 12 | NSSF Rates | `CHPREDS` (inline) | **LOW** | Stored as element rate |
| 13 | Contracts | **NONE** | — | Laravel-only concept |
| 14 | Termination | **NONE** (status only) | — | Just CHSTFF.CURRSTAT in MSSQL |
| 15 | Discipline | **NONE** | — | Entirely new in Laravel |
| 16 | Leave Mgmt | **NONE** | — | Entirely new in Laravel |
| 17 | Zone Assignments | **NONE** | — | Entirely new in Laravel |
| 18 | Workdays/Holidays | **NONE** | — | Entirely new in Laravel |
| 19 | Comms/Templates | **NONE** | — | Entirely new in Laravel |
| 20 | Attendance | **NONE** | — | Entirely new in Laravel |
| 21 | Statutory Reports | `CHPYSL` (derived) | **HIGH** | Same source data |
| 22 | Workflow Approvals | **NONE** | — | Entirely new in Laravel |

---

## All MS SQL HR Tables Cross-Referenced

| MS SQL Table | Used By Laravel? | Laravel Equivalent | Notes |
|---|---|---|---|
| `CHSTFF` | YES | `sys_staff` | Main employee table |
| `CHDEPT` | YES | `sys_set_departments` | Departments |
| `CHDESN` | YES | `sys_set_staff_positions` | Positions/designations |
| `CHBANK` | YES | `sys_set_banks` | Banks |
| `CHBBRA` | YES | `sys_set_banks_branches` | Bank branches |
| `CHGRDS` | NO | — | Pay grades |
| `CHEMPT` | NO | — | Employee types |
| `CHSNOK` | PARTIAL | `sys_staff.kins_names` | Next of kin |
| `CHPREDS` | YES | `sys_hr_payroll_elements` | Payroll element definitions |
| `CHPYSL` | YES | `sys_hr_payroll_summary` | Processed payslip data |
| `CHPRDS` | YES | `sys_hr_payroll_calendar` | Payroll periods |
| `CHPTABS` | YES | `sys_hr_tax_bands` | Tax bands |
| `CHP9A` | PARTIAL | *(derived)* | P9A tax data |
| `CHLOAN` | PARTIAL | `sys_helb_deductions` / `sys_prorated_deductions` | Loans |
| `CHPYRP` | NO | — | Payroll run parameters |
| `CHPSEQ` | NO | — | Payroll JV posting sequence |

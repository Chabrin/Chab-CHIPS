-- ============================================================================
-- HR MODULE: MS SQL → Laravel CREATE VIEW Scripts
-- ============================================================================
-- Server:   localhost:51837
-- Database: chips
-- Purpose:  Present MSSQL data in the shape expected by the Laravel application
-- Generated: 2026-02-25
--
-- These views reshape legacy MSSQL tables into the column names and types
-- used by the Laravel HR module. They are organized by HR service area.
--
-- IMPORTANT: Views that reference other views (e.g., vw_sys_staff references
-- vw_sys_set_departments) must be created in the order listed below.
-- ============================================================================

USE chips;
GO

-- ############################################################################
-- SECTION 1: STAFF MANAGEMENT
-- ############################################################################
-- Covers: Departments, Positions, Banks, Staff Master, Next of Kin
-- Controllers: StaffController, HrSettingsController (dept/position CRUD)
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 1.1  Departments
-- MSSQL: CHDEPT  →  Laravel: sys_set_departments
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_set_departments', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_set_departments;
GO

CREATE VIEW dbo.vw_sys_set_departments AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DEPTCODE)   AS id,
    GETDATE()                                AS date_time,
    DEPTNAME                                 AS department_name,
    DEPTCODE                                 AS mssql_dept_code   -- preserve original code for JOINs
FROM CHDEPT;
GO

-- ----------------------------------------------------------------------------
-- 1.2  Staff Positions / Designations
-- MSSQL: CHDESN  →  Laravel: sys_set_staff_positions
-- Note: Laravel adds basic_salary, reports_to_id, salary_account_id
--       which don't exist in MSSQL — filled with NULL.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_set_staff_positions', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_set_staff_positions;
GO

CREATE VIEW dbo.vw_sys_set_staff_positions AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DESIGNCODE) AS id,
    GETDATE()                                AS date_time,
    DESGINNAME                               AS position_name,
    CAST(NULL AS DECIMAL(13,2))              AS basic_salary,       -- MSSQL stores this on CHSTFF.SALBASC; MySQL=decimal(13,2)
    CAST(NULL AS VARCHAR(255))               AS salary_account_id,  -- MySQL=text
    1                                        AS current_status_id,
    NULL                                     AS reports_to_id,
    DESIGNCODE                               AS mssql_design_code   -- preserve original code for JOINs
FROM CHDESN;
GO

-- ----------------------------------------------------------------------------
-- 1.3  Banks
-- MSSQL: CHBANK  →  Laravel: sys_set_banks
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_set_banks', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_set_banks;
GO

CREATE VIEW dbo.vw_sys_set_banks AS
SELECT
    ROW_NUMBER() OVER (ORDER BY BANKCODE)   AS id,
    GETDATE()                                AS date_time,
    BANKNAME                                 AS bank_name,
    CAST(NULL AS VARCHAR(255))               AS address,            -- MySQL=text
    1                                        AS current_status_id,
    BANKCODE                                 AS mssql_bank_code    -- preserve original code for JOINs
FROM CHBANK;
GO

-- ----------------------------------------------------------------------------
-- 1.3b Bank Branches
-- MSSQL: CHBBRA  →  Laravel: sys_set_banks_branches
-- Both store bank branch names linked to a parent bank.
-- CHSTFF.BANKBRAN FKs to CHBBRA.BRANCODE; Laravel sys_staff.branch_id FKs here.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_set_banks_branches', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_set_banks_branches;
GO

CREATE VIEW dbo.vw_sys_set_banks_branches AS
SELECT
    ROW_NUMBER() OVER (ORDER BY BANKCODE, BRANCODE) AS id,
    GETDATE()                                        AS date_time,
    BRANCODE                                         AS branch_code,
    BRANNAME                                         AS branch_name,
    bk.id                                            AS bank_id,
    1                                                AS current_status_id,
    br.BANKCODE                                      AS mssql_bank_code,
    br.BRANCODE                                      AS mssql_branch_code
FROM CHBBRA br
LEFT JOIN vw_sys_set_banks bk ON br.BANKCODE = bk.mssql_bank_code;
GO

-- ----------------------------------------------------------------------------
-- 1.4  Staff Master
-- MSSQL: CHSTFF + CHSNOK  →  Laravel: sys_staff
-- Joins to the views above for FK resolution (string code → integer id).
-- NOK is collapsed from CHSNOK (1-to-many) into a single kins_names field.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_staff', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_staff;
GO

CREATE VIEW dbo.vw_sys_staff AS
SELECT
    CAST(s.EMPNUMB AS INT) 					 AS id,
    GETDATE()                                AS date_time,
    -- Personal info
    NULL                                     AS photo,
    s.FULLNAME                               AS full_names,
    s.GENDER                                 AS gender,
    s.EMPADDR                                AS address,
    s.MOBNUM1                                AS mobile_number,
    s.WEMAIL                                 AS email_address,
    CAST(s.EMPDOB AS DATE)                   AS birth_date,
    s.NATIDNO                                AS national_id,
    NULL                                     AS passport,
    -- Bank details (FK to vw_sys_set_banks)
    b.id                                     AS bank_name,
    NULL                                     AS account_name,
    s.BANKACC                                AS account_number,
    -- Tax & statutory IDs
    s.NUMPIN                                 AS pin_number,
    s.NUMNHIF                                AS nhif_number,
    s.NUMNSSF                                AS nssf_number,
    -- Employment details
    -- APPDATE is varchar(25) with MIXED formats:
    --   Text:  'Mar  9 2012 12:00AM'  → ISDATE()=1, CAST works
    --   US:    '5/22/2023'            → ISDATE()=1, CAST works (mdy)
    --   EU:    '13/02/2024'           → ISDATE()=0, need TRY_CONVERT style 103 (dd/mm/yyyy)
    --CASE
    --    WHEN s.APPDATE IS NULL THEN NULL
    --    WHEN ISDATE(s.APPDATE) = 1 THEN CAST(s.APPDATE AS DATETIME)
    --    WHEN TRY_CONVERT(DATETIME, s.APPDATE, 103) IS NOT NULL
    --         THEN TRY_CONVERT(DATETIME, s.APPDATE, 103)
    --    ELSE NULL
    --END
   CASE
		WHEN s.APPDATE IS NULL THEN NULL

		-- Text format like: Mar  9 2012 12:00AM
		WHEN TRY_CONVERT(datetime, s.APPDATE, 109) IS NOT NULL
			THEN TRY_CONVERT(datetime, s.APPDATE, 109)

		-- European format: 13/02/2024 (dd/mm/yyyy)
		WHEN TRY_CONVERT(datetime, s.APPDATE, 103) IS NOT NULL
			THEN TRY_CONVERT(datetime, s.APPDATE, 103)

		-- US format: 5/22/2023 (mm/dd/yyyy)
		WHEN TRY_CONVERT(datetime, s.APPDATE, 101) IS NOT NULL
			THEN TRY_CONVERT(datetime, s.APPDATE, 101)

		ELSE NULL
	END
	AS hire_date,
    d.id                                     AS department_id,     -- FK to vw_sys_set_departments
    p.id                                     AS position_id,       -- FK to vw_sys_set_staff_positions
    NULL                                     AS username,
    s.PRMSPAS                                AS system_password,
    NEWID()                                  AS uid,
    NULL                                     AS permission_group_id,
    CASE
        WHEN s.CURRSTAT = 'Active'   THEN 1
        WHEN s.CURRSTAT = 'Inactive' THEN 2
        ELSE NULL
    END                                      AS current_status_id,
    br.id                                    AS branch_id,         -- FK to vw_sys_set_banks_branches
    -- Location
    NULL                                     AS zone_id,
    NULL                                     AS zone_area_id,
    s.DISTRCT                                AS district,
    s.IDLOCAT                                AS location,
    s.IDSUBLOC                               AS sub_location,
    -- Personal details
    s.MSTATUS                                AS marital,
    s.SPOUSNAM                               AS spouse_name,
    s.PLACEOB                                AS birth_place,
    s.CITIZEN                                AS citizenship,
    s.PEMAIL                                 AS personal_email,
    NULL                                     AS disability,
    -- Next of Kin (collapsed from CHSNOK)
    nok.NOKNAM                               AS kins_names,
    -- Identifiers
    s.EMPNUMB                                AS staff_number,
    -- MSSQL-only fields preserved for reference
    s.SALBASC                                AS mssql_basic_salary,
    s.GRADE                                  AS mssql_grade,
    s.EMPTYPE                                AS mssql_emp_type,
    s.EMPSECT                                AS mssql_section
FROM CHSTFF s
LEFT JOIN vw_sys_set_departments d       ON s.DEPTCODE = d.mssql_dept_code
LEFT JOIN vw_sys_set_staff_positions p   ON s.DESIGN   = p.mssql_design_code
LEFT JOIN vw_sys_set_banks b             ON s.BANKCODE = b.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches br   ON s.BANKBRAN = br.mssql_branch_code AND s.BANKCODE = br.mssql_bank_code
OUTER APPLY (
    SELECT TOP 1 NOKNAM
    FROM CHSNOK n
    WHERE n.EMPNUMB = s.EMPNUMB
      AND n.NOKTYPE = 'Next of kin'
    ORDER BY n.NOKNAM
) nok;
GO

-- ----------------------------------------------------------------------------
-- 1.5  Next of Kin (Full Detail)
-- MSSQL: CHSNOK  →  No direct Laravel table (optional supplementary view)
-- Preserves the full 1-to-many NOK data that Laravel collapses.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_staff_next_of_kin', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_staff_next_of_kin;
GO

CREATE VIEW dbo.vw_sys_staff_next_of_kin AS
SELECT
    ROW_NUMBER() OVER (ORDER BY n.EMPNUMB, n.NOKTYPE) AS id,
    -- parent_id: FK to staff record (maps to sys_pm_clients_66_repeat.parent_id)
    staff.id                                             AS parent_id,
    -- Split NOKNAM into first/second/last names
    -- NOKNAM is a single field like 'John Doe' or 'Jane Mary Smith'
    PARSENAME(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '.'), 
        CASE 
            WHEN LEN(LTRIM(RTRIM(n.NOKNAM))) - LEN(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '')) >= 2 THEN 3
            WHEN LEN(LTRIM(RTRIM(n.NOKNAM))) - LEN(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '')) = 1 THEN 2
            ELSE 1
        END
    )                                                    AS kin_first_name,
    CASE
        WHEN LEN(LTRIM(RTRIM(n.NOKNAM))) - LEN(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '')) >= 2
        THEN PARSENAME(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '.'), 2)
        ELSE NULL
    END                                                  AS kin_second_name,
    CASE
        WHEN LEN(LTRIM(RTRIM(n.NOKNAM))) - LEN(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '')) >= 1
        THEN PARSENAME(REPLACE(LTRIM(RTRIM(n.NOKNAM)), ' ', '.'), 1)
        ELSE NULL
    END                                                  AS kin_last_name,
    n.NOKNAM                                             AS names,            -- full original name
    n.NOKTEL                                             AS mobile_number,    -- NOKTEL → mobile_number
    CAST(NULL AS VARCHAR(255))                           AS email_address,    -- no MSSQL source; MySQL=varchar(255)
    n.NOKRELA                                            AS relationship,
    CAST(NULL AS VARCHAR(255))                           AS national_id,      -- no MSSQL source; MySQL=varchar(255)
    -- MSSQL-only fields (not in MySQL table)
    n.EMPNUMB                                            AS mssql_staff_number,
    n.NOKTYPE                                            AS mssql_kin_type,
    n.NOKADDR                                            AS mssql_kin_address,
    n.NOKCITY                                            AS mssql_kin_city
FROM CHSNOK n
LEFT JOIN vw_sys_staff staff ON n.EMPNUMB = staff.staff_number;
GO


-- ############################################################################
-- SECTION 2: PAYROLL CONFIGURATION
-- ############################################################################
-- Covers: Payroll Element Definitions, Tax Bands, Payroll Calendar/Periods
-- Controllers: PayrollController (elements, tax bands, calendar CRUD)
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 2.1  Payroll Element Definitions
-- MSSQL: CHPREDS  →  Laravel: sys_hr_payroll_elements
-- These are the catalogue of earnings/deductions, not actual payslip values.
-- MSSQL has 24 definitions; Laravel uses same concept with different typing.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payroll_elements', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payroll_elements;
GO

CREATE VIEW dbo.vw_sys_hr_payroll_elements AS
SELECT
    ROW_NUMBER() OVER (ORDER BY EDCODE)  AS id,
    GETDATE()                             AS date_time,
    EDNAME                                AS element_name,
    -- Laravel element_type: 1 = Deduction, 2 = Allowance/Earning
    -- MySQL column is TEXT, so cast to VARCHAR
    CAST(CASE
        WHEN EDTYPE = 'Deduction' THEN 1
        WHEN EDTYPE = 'Earning'   THEN 2
        WHEN EDTYPE = 'Saving'    THEN 1   -- Treat savings as deductions
        WHEN EDTYPE = 'Computed'  THEN 1   -- Computed items (PAYE, Net) treated as deductions
        ELSE NULL
    END AS VARCHAR(10))                   AS element_type,
    -- Laravel formula_id: maps MSSQL TYPECHR
    -- MySQL column is TEXT, so cast to VARCHAR
    CAST(CASE
        WHEN TYPECHR = 'Fixed'             THEN 1
        WHEN TYPECHR = 'Computed'          THEN 2
        WHEN TYPECHR = 'Depends on Staff'  THEN 3
        ELSE NULL
    END AS VARCHAR(10))                   AS formula_id,
    CAST(EDRATE AS DECIMAL(15,4))          AS amount,             -- float(53) → decimal(15,4)
    CAST(NULL AS VARCHAR(255))            AS account_id,        -- Needs GL account mapping; MySQL=text
    NULL                                  AS staff_positions_id,
    NULL                                  AS created_by_id,     -- No MSSQL equivalent
    NULL                                  AS payslip_position,  -- No MSSQL equivalent
    -- Derive a slug key for programmatic lookups
    CASE EDNAME
        WHEN 'BASIC SALARY'   THEN 'basic_salary'
        WHEN 'NSSF'           THEN 'nssf'
        WHEN 'NHIF'           THEN 'nhif'
        WHEN 'HOUSING LEVY'   THEN 'housing_levy'
        WHEN 'PAYE'           THEN 'payee'
        WHEN 'HELB LOANS'     THEN 'helb_loans'
        WHEN 'NET SALARY'     THEN 'net_salary'
        WHEN 'SALARY ADVANCE' THEN 'salary_advance'
        ELSE LOWER(REPLACE(REPLACE(EDNAME, ' ', '_'), '-', '_'))
    END                                   AS element_key,
    5                                     AS current_status_id,
    -- MSSQL-only fields preserved
    EDCODE                                AS mssql_ed_code,
    GLACCOUNT                             AS mssql_gl_account,
    TAXABLE                               AS mssql_taxable,
    RECURR                                AS mssql_recurring,
    EDTYPE                                AS mssql_ed_type,
    TYPECHR                               AS mssql_type_chr
FROM CHPREDS
WHERE LEN(EDCODE) = 5;   -- Exclude malformed duplicates (0000024, 000023) that have 0 rows in CHPYSL
GO

-- ----------------------------------------------------------------------------
-- 2.2  Payroll Calendar (Period Headers)
-- MSSQL: CHPRDS  →  Laravel: sys_hr_payroll_calendar
-- Each CHPRDS row represents a payroll month. Laravel splits this into
-- calendar (month header) and periods (date ranges within a month).
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payroll_calendar', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payroll_calendar;
GO

CREATE VIEW dbo.vw_sys_hr_payroll_calendar AS
SELECT
    ROW_NUMBER() OVER (ORDER BY PRYEAR, PRMNTH)  AS id,
    GETDATE()                                      AS date_time,
    -- MySQL payroll_month_date column is INT, not VARCHAR — cast to integer
    CAST(
        CAST(PRYEAR AS VARCHAR(4)) + RIGHT('0' + CAST(PRMNTH AS VARCHAR(2)), 2)
        AS INT
    )                                              AS payroll_month_date,
    CASE
        WHEN PRSTATUS = 'Open'   THEN 5   -- active
        WHEN PRSTATUS = 'Closed' THEN 6   -- inactive/closed
        ELSE NULL
    END                                            AS current_status_id,
    NULL                                           AS created_by,    -- No MSSQL equivalent
    NULL                                           AS position_id,   -- Vestigial column, no MSSQL equivalent
    -- MSSQL-only fields
    PRYEAR                                         AS mssql_year,
    PRMNTH                                         AS mssql_month,
    PRSTATUS                                       AS mssql_status,
    TAXTHRS                                        AS mssql_tax_threshold,
    TAXRLF                                         AS mssql_tax_relief,
    CONAME                                         AS mssql_company_name,
    COPIN                                          AS mssql_company_pin
FROM CHPRDS;
GO

-- ----------------------------------------------------------------------------
-- 2.3  Payroll Periods (Date Ranges)
-- MSSQL: CHPRDS  →  Laravel: sys_hr_payroll_periods
-- In MSSQL each CHPRDS row IS a period; Laravel allows multiple periods
-- per calendar month. This view creates one period per calendar entry.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payroll_periods', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payroll_periods;
GO

CREATE VIEW dbo.vw_sys_hr_payroll_periods AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.PRYEAR, p.PRMNTH)  AS id,
    GETDATE()                                          AS date_time,
    -- Current DB column (datetime representing the payroll period)
    CAST(DATEFROMPARTS(p.PRYEAR, p.PRMNTH, 1) AS DATETIME) AS period,  -- date → datetime to match MySQL
    CASE
        WHEN p.PRSTATUS = 'Open'   THEN 5
        WHEN p.PRSTATUS = 'Closed' THEN 6
        ELSE NULL
    END                                                AS current_status_id,
    -- NOTE: MySQL sys_hr_payroll_periods table only has: id, date_time, period, current_status_id
    -- The columns calendar_id, period_number, start_date, end_date do NOT exist in MySQL.
    -- They are referenced in model $fillable but never migrated.
    -- Keeping them here as mssql_* reference columns only:
    cal.id                                             AS mssql_calendar_id,
    1                                                  AS mssql_period_number,
    p.OPEDATE                                          AS mssql_start_date,
    p.CLSDATE                                          AS mssql_end_date
FROM CHPRDS p
CROSS APPLY (
    SELECT TOP 1 id
    FROM vw_sys_hr_payroll_calendar cal
    WHERE cal.mssql_year  = p.PRYEAR
      AND cal.mssql_month = p.PRMNTH
) cal;
GO


-- ############################################################################
-- SECTION 3: PAYROLL PROCESSING & PAYSLIPS
-- ############################################################################
-- Covers: Processed payroll results (per-employee per-period)
-- Controllers: PayrollController (processPayroll), PayslipsController
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 3.1  Payroll Summary (Payslips)
-- MSSQL: CHPYSL  →  Laravel: sys_hr_payroll_summary
--
-- STRUCTURAL DIFFERENCE:
--   MSSQL:  One row per employee per element per month (normalized).
--   Laravel: One row per employee per period (denormalized),
--            with named columns for statutory items + JSON for detail.
--
-- This view pivots the normalized MSSQL data into the flat Laravel shape.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payroll_summary', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payroll_summary;
GO

CREATE VIEW dbo.vw_sys_hr_payroll_summary AS
SELECT
    ROW_NUMBER() OVER (ORDER BY e.EMPNUMB, e.PRYEAR, e.PRMNTH) AS id,
    GETDATE()                                                    AS date_time,
    -- Staff reference (join to vw_sys_staff for integer FK)
    staff.id                                                     AS staff_id,
    e.EMPNUMB                                                    AS staff_number_id,
    -- Period — DATEFROMPARTS produces date; MySQL expects datetime
    CAST(DATEFROMPARTS(e.PRYEAR, e.PRMNTH, 1) AS DATETIME)       AS period,
    -- Earnings (pivoted from CHPYSL element rows)
    -- All money(19,4) → explicit CAST to decimal(13,2) to match MySQL columns
    CAST(ISNULL(basic.EDAMT, 0) AS DECIMAL(13,2))                AS basic_salary,
    CAST(ISNULL(sarr.EDAMT, 0) AS DECIMAL(13,2))                 AS salary_arrears,       -- EDCODE 00019
    CAST(ISNULL(actall.EDAMT, 0) AS DECIMAL(13,2))               AS acting_allowance,     -- EDCODE 00018
    -- Statutory deductions
    CAST(ISNULL(paye.EDAMT, 0) AS DECIMAL(13,2))                 AS tax_payable,          -- EDCODE 00013
    CAST(ISNULL(nhif.EDAMT, 0) AS DECIMAL(13,2))                 AS nhif,                 -- EDCODE 00005
    CAST(ISNULL(nssf.EDAMT, 0) AS DECIMAL(13,2))                 AS nssf,                 -- EDCODE 00004
    CAST(ISNULL(socwel.EDAMT, 0) AS DECIMAL(13,2))               AS social_welfare,       -- EDCODE 00010
    -- Other deductions
    CAST(ISNULL(akiba.EDAMT, 0) AS DECIMAL(13,2))                AS akiba_sacco_loan,     -- EDCODE 00009
    CAST(ISNULL(sadv.EDAMT, 0) AS DECIMAL(13,2))                 AS salary_advance,       -- EDCODE 00007
    CAST(ISNULL(absent.EDAMT, 0) AS DECIMAL(13,2))               AS absenteeism,          -- EDCODE 00015
    CAST(ISNULL(secdepo.EDAMT, 0) AS DECIMAL(13,2))              AS security_deposit,     -- EDCODE 00017
    CAST(ISNULL(helb.EDAMT, 0) AS DECIMAL(13,2))                 AS helb_loans,           -- EDCODE 00020
    CAST(ISNULL(ahl.EDAMT, 0) AS DECIMAL(13,2))                  AS housing_levy,         -- EDCODE 00022
    CAST(ISNULL(otherded.EDAMT, 0) AS DECIMAL(13,2))             AS other_deductions,     -- EDCODE 00012
    -- Totals
    CAST(ISNULL(totded.EDAMT, 0) AS DECIMAL(13,2))               AS total_deductions,
    CAST(ISNULL(net.EDAMT, 0) AS DECIMAL(13,2))                  AS net_pay,              -- EDCODE 00014
    CAST(ISNULL(akibashare.EDAMT, 0) AS DECIMAL(13,2))           AS akiba_share_contribution, -- EDCODE 00011
    -- Status & FKs
    5                                                            AS current_status_id,
    NULL                                                         AS register_id,          -- No MSSQL equivalent
    CAST(NULL AS DECIMAL(13,2))                                  AS prorated_deduction,   -- No MSSQL equivalent
    CAST(ISNULL(upleave.EDAMT, 0) AS DECIMAL(13,2))              AS unpaid_leave,         -- EDCODE 00016
    staff.department_id                                          AS department_id,        -- From staff record
    staff.position_id                                            AS position_id,          -- From staff record
    CAST(NULL AS VARCHAR(255))                                   AS worked_days,          -- No MSSQL equivalent; MySQL=varchar(255)
    CAST(NULL AS DECIMAL(13,2))                                  AS guarantor_loan,       -- No MSSQL equivalent
    CAST(NULL AS DECIMAL(13,2))                                  AS emmergency_loan,      -- No MSSQL equivalent
    NULL                                                         AS period_id,            -- FK to payroll_periods
    CAST(ISNULL(neglig.EDAMT, 0) AS DECIMAL(13,2))               AS negligence,           -- EDCODE 00024
    CAST(ISNULL(bonus.EDAMT, 0) AS DECIMAL(13,2))                AS bonus,                -- EDCODE 00021
    -- MSSQL-only
    e.PRYEAR                                                     AS mssql_year,
    e.PRMNTH                                                     AS mssql_month
FROM (
    -- Get distinct employee/period combinations
    SELECT DISTINCT EMPNUMB, PRYEAR, PRMNTH
    FROM CHPYSL
) e
-- Resolve integer staff ID + department/position
LEFT JOIN vw_sys_staff staff ON e.EMPNUMB = staff.staff_number
-- Pivot individual element rows (each LEFT JOIN resolves one payslip column)
LEFT JOIN CHPYSL basic      ON e.EMPNUMB = basic.EMPNUMB      AND e.PRYEAR = basic.PRYEAR      AND e.PRMNTH = basic.PRMNTH      AND basic.EDCODE      = '00001'  -- BASIC SALARY
LEFT JOIN CHPYSL sarr       ON e.EMPNUMB = sarr.EMPNUMB       AND e.PRYEAR = sarr.PRYEAR       AND e.PRMNTH = sarr.PRMNTH       AND sarr.EDCODE       = '00019'  -- SALARY ARREARS
LEFT JOIN CHPYSL actall     ON e.EMPNUMB = actall.EMPNUMB     AND e.PRYEAR = actall.PRYEAR     AND e.PRMNTH = actall.PRMNTH     AND actall.EDCODE     = '00018'  -- ACTING ALLOWANCE
LEFT JOIN CHPYSL nssf       ON e.EMPNUMB = nssf.EMPNUMB       AND e.PRYEAR = nssf.PRYEAR       AND e.PRMNTH = nssf.PRMNTH       AND nssf.EDCODE       = '00004'  -- NSSF
LEFT JOIN CHPYSL nhif       ON e.EMPNUMB = nhif.EMPNUMB       AND e.PRYEAR = nhif.PRYEAR       AND e.PRMNTH = nhif.PRMNTH       AND nhif.EDCODE       = '00005'  -- NHIF
LEFT JOIN CHPYSL ahl        ON e.EMPNUMB = ahl.EMPNUMB        AND e.PRYEAR = ahl.PRYEAR        AND e.PRMNTH = ahl.PRMNTH        AND ahl.EDCODE        = '00022'  -- HOUSING LEVY
LEFT JOIN CHPYSL paye       ON e.EMPNUMB = paye.EMPNUMB       AND e.PRYEAR = paye.PRYEAR       AND e.PRMNTH = paye.PRMNTH       AND paye.EDCODE       = '00013'  -- PAYE
LEFT JOIN CHPYSL helb       ON e.EMPNUMB = helb.EMPNUMB       AND e.PRYEAR = helb.PRYEAR       AND e.PRMNTH = helb.PRMNTH       AND helb.EDCODE       = '00020'  -- HELB LOANS
LEFT JOIN CHPYSL socwel     ON e.EMPNUMB = socwel.EMPNUMB     AND e.PRYEAR = socwel.PRYEAR     AND e.PRMNTH = socwel.PRMNTH     AND socwel.EDCODE     = '00010'  -- SOCIAL WELFARE
LEFT JOIN CHPYSL akiba      ON e.EMPNUMB = akiba.EMPNUMB      AND e.PRYEAR = akiba.PRYEAR      AND e.PRMNTH = akiba.PRMNTH      AND akiba.EDCODE      = '00009'  -- AKIBA SACCO LOAN
LEFT JOIN CHPYSL sadv       ON e.EMPNUMB = sadv.EMPNUMB       AND e.PRYEAR = sadv.PRYEAR       AND e.PRMNTH = sadv.PRMNTH       AND sadv.EDCODE       = '00007'  -- SALARY ADVANCE
LEFT JOIN CHPYSL absent     ON e.EMPNUMB = absent.EMPNUMB     AND e.PRYEAR = absent.PRYEAR     AND e.PRMNTH = absent.PRMNTH     AND absent.EDCODE     = '00015'  -- ABSENTEEISM
LEFT JOIN CHPYSL secdepo    ON e.EMPNUMB = secdepo.EMPNUMB    AND e.PRYEAR = secdepo.PRYEAR    AND e.PRMNTH = secdepo.PRMNTH    AND secdepo.EDCODE    = '00017'  -- SECURITY DEPOSIT
LEFT JOIN CHPYSL otherded   ON e.EMPNUMB = otherded.EMPNUMB   AND e.PRYEAR = otherded.PRYEAR   AND e.PRMNTH = otherded.PRMNTH   AND otherded.EDCODE   = '00012'  -- OTHER DEDUCTIONS
LEFT JOIN CHPYSL totded     ON e.EMPNUMB = totded.EMPNUMB     AND e.PRYEAR = totded.PRYEAR     AND e.PRMNTH = totded.PRMNTH     AND totded.EDNAME     = 'TOTAL DEDUCTIONS'
LEFT JOIN CHPYSL net        ON e.EMPNUMB = net.EMPNUMB        AND e.PRYEAR = net.PRYEAR        AND e.PRMNTH = net.PRMNTH        AND net.EDCODE        = '00014'  -- NET SALARY
LEFT JOIN CHPYSL akibashare ON e.EMPNUMB = akibashare.EMPNUMB AND e.PRYEAR = akibashare.PRYEAR AND e.PRMNTH = akibashare.PRMNTH AND akibashare.EDCODE = '00011'  -- AKIBA SHARE CONTRIBUTION
LEFT JOIN CHPYSL upleave    ON e.EMPNUMB = upleave.EMPNUMB    AND e.PRYEAR = upleave.PRYEAR    AND e.PRMNTH = upleave.PRMNTH    AND upleave.EDCODE    = '00016'  -- UNPAID LEAVE
LEFT JOIN CHPYSL neglig     ON e.EMPNUMB = neglig.EMPNUMB     AND e.PRYEAR = neglig.PRYEAR     AND e.PRMNTH = neglig.PRMNTH     AND neglig.EDCODE     = '00024'  -- LOSS DUE TO NEGLIGENCE
LEFT JOIN CHPYSL bonus      ON e.EMPNUMB = bonus.EMPNUMB      AND e.PRYEAR = bonus.PRYEAR      AND e.PRMNTH = bonus.PRMNTH      AND bonus.EDCODE      = '00021'  -- BONUS
;
GO

-- ----------------------------------------------------------------------------
-- 3.2  Payslip Element Detail (per-employee per-period per-element)
-- MSSQL: CHPYSL raw rows  →  Feeds Laravel's payslip_elements JSON column
-- This view provides the individual element breakdown for building the JSON.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payslip_elements', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payslip_elements;
GO

CREATE VIEW dbo.vw_sys_hr_payslip_elements AS
SELECT
    ROW_NUMBER() OVER (ORDER BY sl.EMPNUMB, sl.PRYEAR, sl.PRMNTH, sl.EDCODE) AS id,
    sl.EMPNUMB                               AS staff_number,
    sl.PRYEAR                                AS payroll_year,
    sl.PRMNTH                                AS payroll_month,
    sl.EDCODE                                AS element_code,
    sl.EDNAME                                AS element_name,
    sl.EDTYPE                                AS element_type,     -- E=Earning, D=Deduction
    sl.EDAMT                                 AS amount,
    sl.YTD                                   AS year_to_date,
    -- Map to Laravel payroll element id
    el.id                                    AS payroll_element_id
FROM CHPYSL sl
LEFT JOIN vw_sys_hr_payroll_elements el ON sl.EDCODE = el.mssql_ed_code;
GO


-- ############################################################################
-- SECTION 5: TAX REPORTING (P9A)
-- ############################################################################
-- Covers: Annual P9A tax certificate data
-- No dedicated Laravel controller — data derived from PayrollSummary
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 5.1  P9A Tax Certificate Data
-- MSSQL: CHP9A  →  No direct Laravel table (derived from payroll_summary)
-- This view is useful for cross-checking or migrating historical P9A data.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_p9a', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_p9a;
GO

CREATE VIEW dbo.vw_sys_hr_p9a AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.EMPNUMB, p.PRYEAR, p.PRMNTH) AS id,
    GETDATE()                    AS date_time,
    staff.id                     AS staff_id,
    p.EMPNUMB                   AS staff_number,
    p.PRYEAR                    AS tax_year,
    p.PRMNTH                    AS tax_month,
    CAST(p.AMTA AS DECIMAL(13,2))   AS gross_pay,
    CAST(p.AMTB AS DECIMAL(13,2))   AS benefits_value,
    CAST(p.AMTD AS DECIMAL(13,2))   AS defined_contribution,    -- NSSF
    CAST(p.AMTE1 AS DECIMAL(13,2))  AS owner_occupied_interest,
    CAST(p.AMTE2 AS DECIMAL(13,2))  AS retirement_contribution,
    CAST(p.AMTE3 AS DECIMAL(13,2))  AS other_exemptions,
    CAST(p.AMTF AS DECIMAL(13,2))   AS taxable_pay,
    CAST(p.AMTG AS DECIMAL(13,2))   AS tax_charged,
    CAST(p.AMTH AS DECIMAL(13,2))   AS personal_relief,
    CAST(p.AMTJ AS DECIMAL(13,2))   AS paye_tax
FROM CHP9A p
LEFT JOIN vw_sys_staff staff ON p.EMPNUMB = staff.staff_number;
GO


-- ############################################################################
-- SECTION 6: STATUTORY DEDUCTIONS REPORTING
-- ############################################################################
-- Covers: NSSF, SHIF/NHIF, Housing Levy summary reports
-- Controller: HrReportsController
-- These are reporting views — no separate MSSQL table, derived from CHPYSL.
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 6.1  NSSF Report (per employee per month)
-- Source: CHPYSL WHERE EDCODE='00004'
-- Used by: HrReportsController::getStatutoryDeductions('nssf')
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_rpt_statutory_nssf', 'V') IS NOT NULL
    DROP VIEW dbo.vw_rpt_statutory_nssf;
GO

CREATE VIEW dbo.vw_rpt_statutory_nssf AS
SELECT
    staff.id                     AS staff_id,
    sl.EMPNUMB                  AS staff_number,
    staff.full_names             AS staff_name,
    staff.nssf_number            AS nssf_number,
    sl.PRYEAR                   AS payroll_year,
    sl.PRMNTH                   AS payroll_month,
    CAST(sl.EDAMT AS DECIMAL(13,2))  AS nssf_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = '00004';
GO

-- ----------------------------------------------------------------------------
-- 6.2  SHIF / NHIF Report (per employee per month)
-- Source: CHPYSL WHERE EDCODE='00005'
-- Used by: HrReportsController::getStatutoryDeductions('shif')
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_rpt_statutory_shif', 'V') IS NOT NULL
    DROP VIEW dbo.vw_rpt_statutory_shif;
GO

CREATE VIEW dbo.vw_rpt_statutory_shif AS
SELECT
    staff.id                     AS staff_id,
    sl.EMPNUMB                  AS staff_number,
    staff.full_names             AS staff_name,
    staff.nhif_number            AS nhif_number,
    sl.PRYEAR                   AS payroll_year,
    sl.PRMNTH                   AS payroll_month,
    CAST(sl.EDAMT AS DECIMAL(13,2))  AS shif_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = '00005';
GO

-- ----------------------------------------------------------------------------
-- 6.3  Housing Levy Report (per employee per month)
-- Source: CHPYSL WHERE EDCODE='00022'
-- Used by: HrReportsController::getStatutoryDeductions('housing_levy')
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_rpt_statutory_housing_levy', 'V') IS NOT NULL
    DROP VIEW dbo.vw_rpt_statutory_housing_levy;
GO

CREATE VIEW dbo.vw_rpt_statutory_housing_levy AS
SELECT
    staff.id                     AS staff_id,
    sl.EMPNUMB                  AS staff_number,
    staff.full_names             AS staff_name,
    sl.PRYEAR                   AS payroll_year,
    sl.PRMNTH                   AS payroll_month,
    CAST(sl.EDAMT AS DECIMAL(13,2))  AS housing_levy_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = '00022';
GO


-- ############################################################################
-- SECTION 7: STAFF → CLIENT REPEAT TABLES
-- ############################################################################
-- Covers: Staff bank accounts, staff other contacts
-- These map MSSQL staff data into the Laravel "client repeat" tables,
-- which store child records (bank accounts, contacts) linked via parent_id.
-- Controllers: ClientsController (saveBankAccounts, saveOtherContacts)
-- Models: ClientBankAccount, ClientOtherContact
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 7.1  Staff Bank Accounts
-- MSSQL: CHSTFF (bank columns)  →  Laravel: sys_pm_clients_17_repeat
-- Model: ClientBankAccount  |  Controller: ClientsController::saveBankAccounts
--
-- CHSTFF stores one bank per staff member (BANKCODE, BANKBRAN, BANKACC).
-- Laravel stores bank accounts as child rows in sys_pm_clients_17_repeat
-- linked to the parent client/staff via parent_id.
-- Only staff with a non-empty BANKACC are included.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_pm_clients_17_repeat', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_pm_clients_17_repeat;
GO

CREATE VIEW dbo.vw_sys_pm_clients_17_repeat AS
SELECT
    ROW_NUMBER() OVER (ORDER BY s.EMPNUMB)            AS id,
    staff.id                                           AS parent_id,       -- FK to sys_staff / sys_pm_clients
    bk.id                                              AS bank_id,         -- FK to sys_set_banks (resolved from BANKCODE)
    s.FULLNAME                                         AS account_name,    -- No separate account name in MSSQL; use staff name
    s.BANKACC                                          AS account_number,  -- Bank account number
    br.id                                              AS branch,          -- FK to sys_set_banks_branches (resolved from BANKBRAN)
    CAST(NULL AS DECIMAL(13,2))                        AS return_percentage, -- No MSSQL equivalent
    -- MSSQL-only fields
    s.EMPNUMB                                          AS mssql_staff_number,
    s.BANKCODE                                         AS mssql_bank_code,
    s.BANKBRAN                                         AS mssql_branch_code
FROM CHSTFF s
LEFT JOIN vw_sys_staff staff              ON s.EMPNUMB  = staff.staff_number
LEFT JOIN vw_sys_set_banks bk             ON s.BANKCODE = bk.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches br    ON s.BANKBRAN = br.mssql_branch_code AND s.BANKCODE = br.mssql_bank_code
WHERE s.BANKACC IS NOT NULL AND LTRIM(RTRIM(s.BANKACC)) != '';
GO

-- ----------------------------------------------------------------------------
-- 7.2  Staff Other Contacts
-- MSSQL: CHSCNTS  →  Laravel: sys_pm_clients_19_repeat
-- Model: ClientOtherContact  |  Controller: ClientsController::saveOtherContacts
--
-- CHSCNTS stores alternate contact details for staff (home address, mobile,
-- email, physical address). One row per staff member.
-- Laravel stores these as child rows in sys_pm_clients_19_repeat
-- linked to the parent client/staff via parent_id.
-- Only rows with at least a mobile or email are included.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_pm_clients_19_repeat', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_pm_clients_19_repeat;
GO

CREATE VIEW dbo.vw_sys_pm_clients_19_repeat AS
SELECT
    ROW_NUMBER() OVER (ORDER BY c.EMPNUMB)            AS id,
    staff.id                                           AS parent_id,           -- FK to sys_staff / sys_pm_clients
    staff.full_names                                   AS contact_names,       -- Staff name as contact reference
    c.CUEMAIL                                          AS other_email,         -- Alternate email
    c.MOBNO                                            AS other_mobile_number, -- Alternate mobile number
    -- MSSQL-only fields (address details not in MySQL table)
    c.EMPNUMB                                          AS mssql_staff_number,
    c.HMPBOX                                           AS mssql_home_po_box,
    c.HMTOWN                                           AS mssql_home_town,
    c.HHSETEL                                          AS mssql_home_tel,
    c.HFAXNO                                           AS mssql_home_fax,
    c.HMPCODE                                          AS mssql_home_postal_code,
    c.CAPBOX                                           AS mssql_company_po_box,
    c.CAPCODE                                          AS mssql_company_postal_code,
    c.CATOWN                                           AS mssql_company_town,
    c.CFAXNO                                           AS mssql_company_fax,
    c.PHYADD                                           AS mssql_physical_address
FROM CHSCNTS c
LEFT JOIN vw_sys_staff staff ON c.EMPNUMB = staff.staff_number
WHERE c.MOBNO IS NOT NULL OR c.CUEMAIL IS NOT NULL;
GO


-- ############################################################################
-- SECTION 8: EMPLOYEE CONTRACTS (derived from CHPYSL basic salary changes)
-- ############################################################################
-- The MSSQL system has no explicit contracts table, but contracts can be
-- DERIVED from payslip history: each distinct basic salary level for an
-- employee represents one contract period. date_start = 1st of first month,
-- date_end = last day of last month (NULL = current/active contract).
--
-- Contract elements are derived from the payroll elements active during
-- each contract period (excluding computed/total rows).
-- ----------------------------------------------------------------------------

-- 21. Employee Contracts (derived from CHPYSL)
-- Maps to: sys_hr_employee_contracts (Contract model)
-- Each distinct (EMPNUMB, basic_salary) period = one contract
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_employee_contracts', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_employee_contracts;
GO

CREATE VIEW dbo.vw_sys_hr_employee_contracts AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.EMPNUMB, p.first_period)  AS id,
    CAST(GETDATE() AS DATETIME)                             AS date_time,
    s.id                                                    AS staff_id,        -- FK to sys_staff.id
    CAST(DATEFROMPARTS(
        p.first_period / 100,
        p.first_period % 100, 1
    ) AS DATETIME)                                          AS date_start,      -- First day of first salary month
    CASE
        WHEN p.last_period = emp_max.max_period
        THEN CAST(NULL AS DATETIME)                                             -- Current/active contract
        ELSE CAST(EOMONTH(DATEFROMPARTS(
            p.last_period / 100,
            p.last_period % 100, 1
        )) AS DATETIME)                                                         -- Last day of last salary month
    END                                                     AS date_end,
    CASE
        WHEN p.last_period = emp_max.max_period THEN 5                          -- Active
        ELSE 6                                                                  -- Expired
    END                                                     AS current_status_id,
    s.position_id                                           AS position_id,     -- FK to sys_set_staff_positions.id
    CAST(NULL AS INT)                                       AS created_by,      -- No creator in legacy system
    p.salary                                                AS basic_salary,    -- DECIMAL(13,2)
    s.staff_number                                          AS staff_number,
    -- MSSQL-only reference columns
    p.EMPNUMB                                               AS mssql_emp_number,
    p.first_period                                          AS mssql_first_period,
    p.last_period                                           AS mssql_last_period,
    p.months                                                AS mssql_months_count
FROM (
    SELECT
        EMPNUMB,
        CAST(EDAMT AS DECIMAL(13,2))    AS salary,
        MIN(PRYEAR * 100 + PRMNTH)      AS first_period,
        MAX(PRYEAR * 100 + PRMNTH)      AS last_period,
        COUNT(*)                         AS months
    FROM CHPYSL
    WHERE EDNAME = 'BASIC SALARY'
    GROUP BY EMPNUMB, CAST(EDAMT AS DECIMAL(13,2))
) p
JOIN (
    SELECT
        EMPNUMB,
        MAX(PRYEAR * 100 + PRMNTH) AS max_period
    FROM CHPYSL
    WHERE EDNAME = 'BASIC SALARY'
    GROUP BY EMPNUMB
) emp_max ON p.EMPNUMB = emp_max.EMPNUMB
LEFT JOIN vw_sys_staff s ON TRY_CAST(p.EMPNUMB AS INT) = s.id;
GO


-- 22. Employee Contract Elements (derived from CHPYSL)
-- Maps to: sys_hr_employee_contracts_279_repeat (ContractElement model)
-- For each contract period, lists the distinct payroll elements active
-- during that period. Excludes computed/total pseudo-elements.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_employee_contracts_279_repeat', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_employee_contracts_279_repeat;
GO

CREATE VIEW dbo.vw_sys_hr_employee_contracts_279_repeat AS
SELECT
    ROW_NUMBER() OVER (ORDER BY ct.id, el.id)   AS id,
    ct.id                                       AS parent_id,       -- FK to vw_sys_hr_employee_contracts.id
    el.id                                       AS element_id,      -- FK to vw_sys_hr_payroll_elements.id
    CAST(1 AS INT)                              AS entry_type_id,   -- 1 = Fixed amount
    -- Average monthly amount for this element during the contract period
    -- (StaffController::contractStore saves amount per element)
    CAST(AVG(CAST(ps.EDAMT AS DECIMAL(13,2))) AS DECIMAL(13,2)) AS amount
FROM vw_sys_hr_employee_contracts ct
JOIN CHPYSL ps
    ON ps.EMPNUMB = ct.mssql_emp_number
    AND (ps.PRYEAR * 100 + ps.PRMNTH) BETWEEN ct.mssql_first_period AND ct.mssql_last_period
    AND ps.EDNAME != 'BASIC SALARY'
    AND ps.EDCODE NOT IN ('DEDUCTIONS','TAXC','TAXR','TEARNINGS')   -- Exclude computed totals
    AND ps.EDAMT != 0
JOIN vw_sys_hr_payroll_elements el
    ON ps.EDCODE = el.mssql_ed_code
GROUP BY ct.id, el.id;
GO


-- ############################################################################
-- SECTION 10: PAYROLL REGISTER & ELEMENT-POSITION PIVOT
-- ############################################################################
-- Covers: Payroll deductions register (audit log), element-to-position pivot.
-- Source: CHPYSL monthly deduction rows.
-- ############################################################################

-- ----------------------------------------------------------------------------
-- 10.1  Payroll Deductions Register (audit log)
-- MSSQL: CHPYSL  →  Laravel: sys_hr_payroll_deductions_register
-- One row per deduction element per employee per payroll period.
-- Covers all actual deduction EDCODEs (statutory + voluntary + loans).
-- Joins CHPYSL basic salary row for the salary column.
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payroll_deductions_register', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payroll_deductions_register;
GO

CREATE VIEW dbo.vw_sys_hr_payroll_deductions_register AS
SELECT
    ROW_NUMBER() OVER (ORDER BY s.id, p.PRYEAR, p.PRMNTH, p.EDCODE) AS id,
    GETDATE()                                             AS date_time,
    CAST(DATEFROMPARTS(p.PRYEAR, p.PRMNTH, 1) AS DATETIME) AS payroll_period,
    s.id                                                  AS staff_id,          -- FK → sys_staff.id
    el.id                                                 AS deduction_type_id, -- FK → sys_hr_payroll_elements.id
    CAST(p.EDAMT AS DECIMAL(13,2))                        AS amount,            -- Employee deduction amount
    CAST(0 AS DECIMAL(13,2))                              AS company_amount,    -- Employer portion not in CHPYSL
    CAST(p.EDAMT AS DECIMAL(13,2))                        AS total_amount,      -- = amount + company_amount
    CAST(ISNULL(bs.EDAMT, 0) AS DECIMAL(13,2))            AS salary             -- Basic salary for the period
FROM CHPYSL p
INNER JOIN vw_sys_staff s ON TRY_CAST(p.EMPNUMB AS INT) = s.id
INNER JOIN vw_sys_hr_payroll_elements el ON p.EDCODE = el.mssql_ed_code
LEFT JOIN CHPYSL bs
    ON p.EMPNUMB = bs.EMPNUMB
    AND p.PRYEAR = bs.PRYEAR
    AND p.PRMNTH = bs.PRMNTH
    AND bs.EDCODE = '00001'                               -- Basic Salary
WHERE p.EDCODE IN (
    '00004',  -- NSSF
    '00005',  -- NHIF
    '00007',  -- Salary Advance
    '00009',  -- Akiba Sacco Loan
    '00010',  -- Social Welfare
    '00011',  -- Akiba Share Contribution
    '00012',  -- Other Deductions
    '00013',  -- Tax Payable (PAYE)
    '00015',  -- Absenteeism
    '00016',  -- Unpaid Leave
    '00017',  -- Security Deposit
    '00020',  -- HELB Loans
    '00022',  -- Housing Levy
    '00024'   -- Loss Due to Negligence
)
AND p.EDAMT != 0;
GO

-- ----------------------------------------------------------------------------
-- 10.2  Payroll Element ↔ Staff Position Pivot
-- MSSQL: CHPYSL + vw_sys_staff  →  Laravel: sys_hr_payroll_elements_repeat_staff_positions_id
-- Derived: which payroll elements have historically been applied to which
-- staff positions. Many-to-many pivot between PayrollElement and Position.
-- parent_id = element_id (FK → sys_hr_payroll_elements).
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.vw_sys_hr_payroll_elements_repeat_staff_positions_id', 'V') IS NOT NULL
    DROP VIEW dbo.vw_sys_hr_payroll_elements_repeat_staff_positions_id;
GO

CREATE VIEW dbo.vw_sys_hr_payroll_elements_repeat_staff_positions_id AS
SELECT
    ROW_NUMBER() OVER (ORDER BY el.id, s.position_id) AS id,
    el.id                                              AS parent_id,           -- FK → sys_hr_payroll_elements.id
    CAST(s.position_id AS INT)                         AS staff_positions_id   -- FK → sys_set_staff_positions.id
FROM CHPYSL p
INNER JOIN vw_sys_staff s ON TRY_CAST(p.EMPNUMB AS INT) = s.id
INNER JOIN vw_sys_hr_payroll_elements el ON p.EDCODE = el.mssql_ed_code
WHERE p.EDCODE NOT IN ('00001', 'DEDUCTIONS', 'NETP', 'NHIFR', 'TAXC', 'TAXP', 'TAXR', 'TEARNINGS')
  AND p.EDAMT != 0
  AND s.position_id IS NOT NULL
GROUP BY el.id, s.position_id;
GO


-- ############################################################################
-- SECTION 9: MODULES WITH NO MSSQL EQUIVALENT
-- ############################################################################
-- The following Laravel HR modules have NO corresponding MSSQL tables.
-- No views can be created for these — they are listed for completeness:
--
--   - Employee Termination      (sys_hr_employee_terminations)
--   - Termination Elements      (hr_employment_termination_305_repeat) — settlement line items
--   - Discipline Tracker        (sys_hr_discipline_*)
--   - Leave Management          (sys_hr_leave_*)
--   - Zone Assignments          (sys_hr_zone_assignments)
--   - Workdays                  (sys_hr_workdays)
--   - Public Holidays           (sys_hr_public_holidays)
--   - SHIF Rates settings       (sys_hr_shif_rates)
--   - NSSF Rates settings       (sys_hr_nssf_rates)
--   - Communication Templates   (sys_hr_communication_templates)
--   - HR Communications         (sys_hr_communications)
--   - Employee Attendance       (sys_hr_employee_attendance)
--   - Workflow Approvals         (sys_workflow_approvals)
--
-- These modules are entirely new functionality in the Laravel application
-- and would need to be populated from scratch or from other data sources.
--
-- The following MSSQL tables have NO corresponding Laravel tables:
--
--   - CHBEFTS   (Electronic Funds Transfer records — 20 cols, approval workflow)
--   - CHGRDS    (Staff pay grades — GRADECODE, GRADENAME)
--   - CHEMPT    (Employee types)
--   - CHSNOK    (Next of Kin — mapped via vw_sys_staff_next_of_kin → sys_pm_clients_66_repeat,
--                and collapsed into sys_staff.kins_names)
--   - CHSCNTS   (Staff contacts — mapped via vw_sys_pm_clients_19_repeat → sys_pm_clients_19_repeat)
--   - CHSTFF bank cols — mapped via vw_sys_pm_clients_17_repeat → sys_pm_clients_17_repeat
--
-- The following Laravel HR modules have no MSSQL staff equivalent.
-- CHLOAN is a vendor/entity loans table (linked via CHVENDS), NOT staff:
--
--   - HELB Deductions           (sys_helb_deductions)                   — no staff-specific source
--   - HELB Monthly Repeat       (sys_helb_deductions_250_repeat)        — no staff-specific source
--   - Amortized Deductions      (sys_hr_employee_amortized_deductions)  — no staff-specific source
--   - Amortized Monthly Repeat  (sys_hr_employee_amortized_deductions_252_repeat) — no staff-specific source
--
-- CHPTABS is a historical tax-bands table (not staff-specific):
--
--   - Tax Bands                  (um_tax_bands)                          — CHPTABS not staff-related
--
-- ############################################################################

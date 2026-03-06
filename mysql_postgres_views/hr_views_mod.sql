-- ============================================================================
-- HR MODULE: CREATE OR ALTER VIEW Scripts  (mod batch)
-- ============================================================================
-- Source:    hr_views.txt
-- Database:  CHABRINS
-- Generated: 2026-03-06
--
-- Naming convention: original name  +  _0NN  suffix  (no dbo. prefix)
-- Internal cross-references are updated to the new names below.
--
-- Execution: Run the entire file as ONE query (no GO separators).
--            Each view is wrapped in EXEC sp_executesql so SQL Server
--            treats each CREATE OR ALTER VIEW as its own batch internally.
--
-- View index
--   _011  vw_sys_set_departments
--   _012  vw_sys_set_staff_positions
--   _013  vw_sys_set_banks
--   _014  vw_sys_set_banks_branches
--   _015  vw_sys_staff
--   _016  vw_sys_staff_next_of_kin
--   _017  vw_sys_hr_payroll_elements
--   _018  vw_um_tax_bands
--   _019  vw_sys_hr_payroll_calendar
--   _020  vw_sys_hr_payroll_periods
--   _021  vw_sys_hr_payroll_summary
--   _022  vw_sys_hr_payslip_elements
--   _023  vw_sys_helb_deductions
--   _024  vw_sys_hr_employee_amortized_deductions
--   _025  vw_sys_hr_p9a
--   _026  vw_rpt_statutory_nssf
--   _027  vw_rpt_statutory_shif
--   _028  vw_rpt_statutory_housing_levy
-- ============================================================================

USE CHABRINS;

-- ============================================================================
-- _011  vw_sys_set_departments
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_departments_011 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DEPTCODE)   AS id,
    GETDATE()                                AS date_time,
    DEPTNAME                                 AS department_name,
    DEPTCODE                                 AS mssql_dept_code
FROM CHDEPT;
';

-- ============================================================================
-- _012  vw_sys_set_staff_positions
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_staff_positions_012 AS
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
';

-- ============================================================================
-- _013  vw_sys_set_banks
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_banks_013 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY BANKCODE)   AS id,
    GETDATE()                                AS date_time,
    BANKNAME                                 AS bank_name,
    NULL                                     AS address,
    1                                        AS current_status_id,
    BANKCODE                                 AS mssql_bank_code
FROM CHBANK;
';

-- ============================================================================
-- _014  vw_sys_set_banks_branches
--       References: vw_sys_set_banks_013
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_banks_branches_014 AS
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
LEFT JOIN vw_sys_set_banks_013 bk ON br.BANKCODE = bk.mssql_bank_code;
';

-- ============================================================================
-- _015  vw_sys_staff
--       References: vw_sys_set_departments_011
--                   vw_sys_set_staff_positions_012
--                   vw_sys_set_banks_013
--                   vw_sys_set_banks_branches_014
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_staff_015 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY s.EMPNUMB)  AS id,
    GETDATE()                                AS date_time,
    NULL                                     AS photo,
    s.FULLNAME                               AS full_names,
    s.GENDER                                 AS gender,
    s.EMPADDR                                AS address,
    s.MOBNUM1                                AS mobile_number,
    s.WEMAIL                                 AS email_address,
    CAST(s.EMPDOB AS DATE)                   AS birth_date,
    s.NATIDNO                                AS national_id,
    NULL                                     AS passport,
    b.id                                     AS bank_name,
    NULL                                     AS account_name,
    s.BANKACC                                AS account_number,
    s.NUMPIN                                 AS pin_number,
    s.NUMNHIF                                AS nhif_number,
    s.NUMNSSF                                AS nssf_number,
    s.APPDATE                                AS hire_date,
    d.id                                     AS department_id,
    p.id                                     AS position_id,
    NULL                                     AS username,
    s.PRMSPAS                                AS system_password,
    NEWID()                                  AS uid,
    NULL                                     AS permission_group_id,
    CASE
        WHEN s.CURRSTAT = ''Active''   THEN 1
        WHEN s.CURRSTAT = ''Inactive'' THEN 2
        ELSE NULL
    END                                      AS current_status_id,
    br.id                                    AS branch_id,
    NULL                                     AS zone_id,
    NULL                                     AS zone_area_id,
    s.DISTRCT                                AS district,
    s.IDLOCAT                                AS location,
    s.IDSUBLOC                               AS sub_location,
    s.MSTATUS                                AS marital,
    s.SPOUSNAM                               AS spouse_name,
    s.PLACEOB                                AS birth_place,
    s.CITIZEN                                AS citizenship,
    s.PEMAIL                                 AS personal_email,
    NULL                                     AS disability,
    nok.NOKNAM                               AS kins_names,
    s.EMPNUMB                                AS staff_number,
    s.SALBASC                                AS mssql_basic_salary,
    s.GRADE                                  AS mssql_grade,
    s.EMPTYPE                                AS mssql_emp_type,
    s.EMPSECT                                AS mssql_section
FROM CHSTFF s
LEFT JOIN vw_sys_set_departments_011       d  ON s.DEPTCODE = d.mssql_dept_code
LEFT JOIN vw_sys_set_staff_positions_012   p  ON s.DESIGN   = p.mssql_design_code
LEFT JOIN vw_sys_set_banks_013             b  ON s.BANKCODE = b.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches_014    br ON s.BANKBRAN = br.mssql_branch_code
                                              AND s.BANKCODE = br.mssql_bank_code
OUTER APPLY (
    SELECT TOP 1 NOKNAM
    FROM CHSNOK n
    WHERE n.EMPNUMB = s.EMPNUMB
      AND n.NOKTYPE = ''Next of kin''
    ORDER BY n.NOKNAM
) nok;
';

-- ============================================================================
-- _016  vw_sys_staff_next_of_kin
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_staff_next_of_kin_016 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY n.EMPNUMB, n.NOKTYPE) AS id,
    n.EMPNUMB   AS staff_number,
    n.NOKNAM    AS kin_name,
    n.NOKRELA   AS relationship,
    n.NOKTYPE   AS kin_type,
    n.NOKADDR   AS kin_address,
    n.NOKCITY   AS kin_city,
    n.NOKTEL    AS kin_phone
FROM CHSNOK n;
';

-- ============================================================================
-- _017  vw_sys_hr_payroll_elements
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_elements_017 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY EDCODE)  AS id,
    GETDATE()                             AS date_time,
    EDNAME                                AS element_name,
    CASE
        WHEN EDTYPE = ''Deduction'' THEN 1
        WHEN EDTYPE = ''Earning''   THEN 2
        WHEN EDTYPE = ''Saving''    THEN 1
        WHEN EDTYPE = ''Computed''  THEN 1
        ELSE NULL
    END                                   AS element_type,
    CASE
        WHEN TYPECHR = ''Fixed''             THEN 1
        WHEN TYPECHR = ''Computed''          THEN 2
        WHEN TYPECHR = ''Depends on Staff''  THEN 3
        ELSE NULL
    END                                   AS formula_id,
    EDRATE                                AS amount,
    NULL                                  AS account_id,
    NULL                                  AS staff_positions_id,
    NULL                                  AS created_by_id,
    NULL                                  AS payslip_position,
    CASE EDNAME
        WHEN ''BASIC SALARY''   THEN ''basic_salary''
        WHEN ''NSSF''           THEN ''nssf''
        WHEN ''NHIF''           THEN ''nhif''
        WHEN ''HOUSING LEVY''   THEN ''housing_levy''
        WHEN ''PAYE''           THEN ''payee''
        WHEN ''HELB LOANS''     THEN ''helb_loans''
        WHEN ''NET SALARY''     THEN ''net_salary''
        WHEN ''SALARY ADVANCE'' THEN ''salary_advance''
        ELSE LOWER(REPLACE(REPLACE(EDNAME, '' '', ''_''), ''-'', ''_''))
    END                                   AS element_key,
    5                                     AS current_status_id,
    EDCODE                                AS mssql_ed_code,
    GLACCOUNT                             AS mssql_gl_account,
    TAXABLE                               AS mssql_taxable,
    RECURR                                AS mssql_recurring,
    EDTYPE                                AS mssql_ed_type,
    TYPECHR                               AS mssql_type_chr
FROM CHPREDS;
';

-- ============================================================================
-- _018  vw_um_tax_bands
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_um_tax_bands_018 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY t.CATMIN)  AS id,
    t.CATMIN                                AS range_from,
    CASE
        WHEN t.CATMAX >= 999999999 THEN NULL
        ELSE t.CATMAX
    END                                     AS range_to,
    t.CATRAT                                AS tax_rate,
    CASE
        WHEN t.CATMAX >= 999999999 THEN 1
        ELSE 0
    END                                     AS is_last,
    ''system''                              AS added_by,
    NULL                                    AS updated_by,
    GETDATE()                               AS created_at,
    NULL                                    AS updated_at,
    t.CATEGO                                AS mssql_category,
    t.PYEAR                                 AS mssql_year,
    t.PMONTH                                AS mssql_month
FROM CHPTABS t
WHERE t.PYEAR  = (SELECT MAX(PYEAR) FROM CHPTABS)
  AND t.PMONTH = (
      SELECT MAX(PMONTH)
      FROM CHPTABS
      WHERE PYEAR = (SELECT MAX(PYEAR) FROM CHPTABS)
  );
';

-- ============================================================================
-- _019  vw_sys_hr_payroll_calendar
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_calendar_019 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY PRYEAR, PRMNTH)  AS id,
    GETDATE()                                      AS date_time,
    CAST(PRYEAR AS VARCHAR(4)) + RIGHT(''0'' + CAST(PRMNTH AS VARCHAR(2)), 2)
                                                   AS payroll_month_date,
    CASE
        WHEN PRSTATUS = ''Open''   THEN 5
        WHEN PRSTATUS = ''Closed'' THEN 6
        ELSE NULL
    END                                            AS current_status_id,
    NULL                                           AS created_by,
    NULL                                           AS position_id,
    PRYEAR                                         AS mssql_year,
    PRMNTH                                         AS mssql_month,
    PRSTATUS                                       AS mssql_status,
    TAXTHRS                                        AS mssql_tax_threshold,
    TAXRLF                                         AS mssql_tax_relief,
    CONAME                                         AS mssql_company_name,
    COPIN                                          AS mssql_company_pin
FROM CHPRDS;
';

-- ============================================================================
-- _020  vw_sys_hr_payroll_periods
--       References: vw_sys_hr_payroll_calendar_019
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_periods_020 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.PRYEAR, p.PRMNTH)  AS id,
    GETDATE()                                          AS date_time,
    DATEFROMPARTS(p.PRYEAR, p.PRMNTH, 1)               AS period,
    CASE
        WHEN p.PRSTATUS = ''Open''   THEN 5
        WHEN p.PRSTATUS = ''Closed'' THEN 6
        ELSE NULL
    END                                                AS current_status_id,
    cal.id                                             AS calendar_id,
    1                                                  AS period_number,
    p.OPEDATE                                          AS start_date,
    p.CLSDATE                                          AS end_date
FROM CHPRDS p
CROSS APPLY (
    SELECT TOP 1 id
    FROM vw_sys_hr_payroll_calendar_019 cal
    WHERE cal.mssql_year  = p.PRYEAR
      AND cal.mssql_month = p.PRMNTH
) cal;
';

-- ============================================================================
-- _021  vw_sys_hr_payroll_summary
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_summary_021 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY e.EMPNUMB, e.PRYEAR, e.PRMNTH) AS id,
    GETDATE()                                                    AS date_time,
    staff.id                                                     AS staff_id,
    e.EMPNUMB                                                    AS staff_number_id,
    DATEFROMPARTS(e.PRYEAR, e.PRMNTH, 1)                         AS period,
    ISNULL(basic.EDAMT,      0)                                  AS basic_salary,
    ISNULL(sarr.EDAMT,       0)                                  AS salary_arrears,
    ISNULL(actall.EDAMT,     0)                                  AS acting_allowance,
    ISNULL(paye.EDAMT,       0)                                  AS tax_payable,
    ISNULL(nhif.EDAMT,       0)                                  AS nhif,
    ISNULL(nssf.EDAMT,       0)                                  AS nssf,
    ISNULL(socwel.EDAMT,     0)                                  AS social_welfare,
    ISNULL(akiba.EDAMT,      0)                                  AS akiba_sacco_loan,
    ISNULL(sadv.EDAMT,       0)                                  AS salary_advance,
    ISNULL(absent.EDAMT,     0)                                  AS absenteeism,
    ISNULL(secdepo.EDAMT,    0)                                  AS security_deposit,
    ISNULL(helb.EDAMT,       0)                                  AS helb_loans,
    ISNULL(ahl.EDAMT,        0)                                  AS housing_levy,
    ISNULL(otherded.EDAMT,   0)                                  AS other_deductions,
    ISNULL(totded.EDAMT,     0)                                  AS total_deductions,
    ISNULL(net.EDAMT,        0)                                  AS net_pay,
    ISNULL(akibashare.EDAMT, 0)                                  AS akiba_share_contribution,
    5                                                            AS current_status_id,
    NULL                                                         AS register_id,
    NULL                                                         AS prorated_deduction,
    ISNULL(upleave.EDAMT,    0)                                  AS unpaid_leave,
    staff.department_id                                          AS department_id,
    staff.position_id                                            AS position_id,
    NULL                                                         AS worked_days,
    NULL                                                         AS guarantor_loan,
    NULL                                                         AS emmergency_loan,
    NULL                                                         AS period_id,
    ISNULL(neglig.EDAMT,     0)                                  AS negligence,
    e.PRYEAR                                                     AS mssql_year,
    e.PRMNTH                                                     AS mssql_month
FROM (
    SELECT DISTINCT EMPNUMB, PRYEAR, PRMNTH
    FROM CHPYSL
) e
LEFT JOIN vw_sys_staff_015  staff     ON e.EMPNUMB = staff.staff_number
LEFT JOIN CHPYSL basic      ON e.EMPNUMB = basic.EMPNUMB      AND e.PRYEAR = basic.PRYEAR      AND e.PRMNTH = basic.PRMNTH      AND basic.EDCODE      = ''00001''
LEFT JOIN CHPYSL sarr       ON e.EMPNUMB = sarr.EMPNUMB       AND e.PRYEAR = sarr.PRYEAR       AND e.PRMNTH = sarr.PRMNTH       AND sarr.EDCODE       = ''00019''
LEFT JOIN CHPYSL actall     ON e.EMPNUMB = actall.EMPNUMB     AND e.PRYEAR = actall.PRYEAR     AND e.PRMNTH = actall.PRMNTH     AND actall.EDCODE     = ''00018''
LEFT JOIN CHPYSL nssf       ON e.EMPNUMB = nssf.EMPNUMB       AND e.PRYEAR = nssf.PRYEAR       AND e.PRMNTH = nssf.PRMNTH       AND nssf.EDCODE       = ''00004''
LEFT JOIN CHPYSL nhif       ON e.EMPNUMB = nhif.EMPNUMB       AND e.PRYEAR = nhif.PRYEAR       AND e.PRMNTH = nhif.PRMNTH       AND nhif.EDCODE       = ''00005''
LEFT JOIN CHPYSL ahl        ON e.EMPNUMB = ahl.EMPNUMB        AND e.PRYEAR = ahl.PRYEAR        AND e.PRMNTH = ahl.PRMNTH        AND ahl.EDCODE        = ''00022''
LEFT JOIN CHPYSL paye       ON e.EMPNUMB = paye.EMPNUMB       AND e.PRYEAR = paye.PRYEAR       AND e.PRMNTH = paye.PRMNTH       AND paye.EDCODE       = ''00013''
LEFT JOIN CHPYSL helb       ON e.EMPNUMB = helb.EMPNUMB       AND e.PRYEAR = helb.PRYEAR       AND e.PRMNTH = helb.PRMNTH       AND helb.EDCODE       = ''00020''
LEFT JOIN CHPYSL socwel     ON e.EMPNUMB = socwel.EMPNUMB     AND e.PRYEAR = socwel.PRYEAR     AND e.PRMNTH = socwel.PRMNTH     AND socwel.EDCODE     = ''00010''
LEFT JOIN CHPYSL akiba      ON e.EMPNUMB = akiba.EMPNUMB      AND e.PRYEAR = akiba.PRYEAR      AND e.PRMNTH = akiba.PRMNTH      AND akiba.EDCODE      = ''00009''
LEFT JOIN CHPYSL sadv       ON e.EMPNUMB = sadv.EMPNUMB       AND e.PRYEAR = sadv.PRYEAR       AND e.PRMNTH = sadv.PRMNTH       AND sadv.EDCODE       = ''00007''
LEFT JOIN CHPYSL absent     ON e.EMPNUMB = absent.EMPNUMB     AND e.PRYEAR = absent.PRYEAR     AND e.PRMNTH = absent.PRMNTH     AND absent.EDCODE     = ''00015''
LEFT JOIN CHPYSL secdepo    ON e.EMPNUMB = secdepo.EMPNUMB    AND e.PRYEAR = secdepo.PRYEAR    AND e.PRMNTH = secdepo.PRMNTH    AND secdepo.EDCODE    = ''00017''
LEFT JOIN CHPYSL otherded   ON e.EMPNUMB = otherded.EMPNUMB   AND e.PRYEAR = otherded.PRYEAR   AND e.PRMNTH = otherded.PRMNTH   AND otherded.EDCODE   = ''00012''
LEFT JOIN CHPYSL totded     ON e.EMPNUMB = totded.EMPNUMB     AND e.PRYEAR = totded.PRYEAR     AND e.PRMNTH = totded.PRMNTH     AND totded.EDNAME     = ''TOTAL DEDUCTIONS''
LEFT JOIN CHPYSL net        ON e.EMPNUMB = net.EMPNUMB        AND e.PRYEAR = net.PRYEAR        AND e.PRMNTH = net.PRMNTH        AND net.EDCODE        = ''00014''
LEFT JOIN CHPYSL akibashare ON e.EMPNUMB = akibashare.EMPNUMB AND e.PRYEAR = akibashare.PRYEAR AND e.PRMNTH = akibashare.PRMNTH AND akibashare.EDCODE = ''00011''
LEFT JOIN CHPYSL upleave    ON e.EMPNUMB = upleave.EMPNUMB    AND e.PRYEAR = upleave.PRYEAR    AND e.PRMNTH = upleave.PRMNTH    AND upleave.EDCODE    = ''00016''
LEFT JOIN CHPYSL neglig     ON e.EMPNUMB = neglig.EMPNUMB     AND e.PRYEAR = neglig.PRYEAR     AND e.PRMNTH = neglig.PRMNTH     AND neglig.EDCODE     = ''00024'';
';

-- ============================================================================
-- _022  vw_sys_hr_payslip_elements
--       References: vw_sys_hr_payroll_elements_017
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payslip_elements_022 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY sl.EMPNUMB, sl.PRYEAR, sl.PRMNTH, sl.EDCODE) AS id,
    sl.EMPNUMB  AS staff_number,
    sl.PRYEAR   AS payroll_year,
    sl.PRMNTH   AS payroll_month,
    sl.EDCODE   AS element_code,
    sl.EDNAME   AS element_name,
    sl.EDTYPE   AS element_type,
    sl.EDAMT    AS amount,
    sl.YTD      AS year_to_date,
    el.id       AS payroll_element_id
FROM CHPYSL sl
LEFT JOIN vw_sys_hr_payroll_elements_017 el ON sl.EDCODE = el.mssql_ed_code;
';

-- ============================================================================
-- _023  vw_sys_helb_deductions
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_helb_deductions_023 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY l.LORDID, l.LNAMT DESC) AS id,
    GETDATE()                                             AS date_time,
    NULL                                                  AS created_by_id,
    staff.id                                              AS staff_id,
    l.LNBALANC                                            AS loan_balance,
    l.AMTMONT                                             AS monthly_deduction,
    NULL                                                  AS start_deduction_date,
    CASE
        WHEN l.REPSTAT = 1 THEN 5
        WHEN l.REPSTAT = 0 THEN 6
        ELSE NULL
    END                                                   AS current_status_id,
    l.LORDID                                              AS mssql_staff_number,
    l.REPSTAT                                             AS mssql_status,
    l.VENDORID                                            AS mssql_vendor_id,
    l.BANKN                                               AS mssql_bank,
    l.CHEQNO                                              AS mssql_cheque_no
FROM CHLOAN l
LEFT JOIN vw_sys_staff_015 staff ON l.LORDID = staff.staff_number;
';

-- ============================================================================
-- _024  vw_sys_hr_employee_amortized_deductions
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_employee_amortized_deductions_024 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY l.LORDID, l.LNAMT DESC) AS id,
    GETDATE()                                             AS date_time,
    staff.id                                              AS staff_id,
    l.AMTPRINC                                            AS principal_amount,
    l.INTRATE                                             AS interest_rate,
    NULL                                                  AS deduction_date,
    NULL                                                  AS created_by_id,
    CASE
        WHEN l.REPSTAT = 1 THEN 5
        WHEN l.REPSTAT = 0 THEN 6
        ELSE NULL
    END                                                   AS current_status_id,
    l.AMTMONT                                             AS monthly_deduction,
    l.LNAMT                                               AS total_amount,
    l.AMTINTER                                            AS interest_amount,
    NULL                                                  AS repayment_months,
    NULL                                                  AS deduction_type_id,
    NULL                                                  AS element_id,
    NULL                                                  AS issued_date,
    NULL                                                  AS initial_deduction,
    l.LORDID                                              AS mssql_staff_number,
    l.LNBALANC                                            AS mssql_loan_balance,
    l.REPSTAT                                             AS mssql_status,
    l.VENDORID                                            AS mssql_vendor_id,
    l.BANKN                                               AS mssql_bank,
    l.CHEQNO                                              AS mssql_cheque_no
FROM CHLOAN l
LEFT JOIN vw_sys_staff_015 staff ON l.LORDID = staff.staff_number;
';

-- ============================================================================
-- _025  vw_sys_hr_p9a
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_p9a_025 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.EMPNUMB, p.PRYEAR, p.PRMNTH) AS id,
    GETDATE()   AS date_time,
    staff.id    AS staff_id,
    p.EMPNUMB   AS staff_number,
    p.PRYEAR    AS tax_year,
    p.PRMNTH    AS tax_month,
    p.AMTA      AS gross_pay,
    p.AMTB      AS benefits_value,
    p.AMTD      AS defined_contribution,
    p.AMTE1     AS owner_occupied_interest,
    p.AMTE2     AS retirement_contribution,
    p.AMTE3     AS other_exemptions,
    p.AMTF      AS taxable_pay,
    p.AMTG      AS tax_charged,
    p.AMTH      AS personal_relief,
    p.AMTJ      AS paye_tax
FROM CHP9A p
LEFT JOIN vw_sys_staff_015 staff ON p.EMPNUMB = staff.staff_number;
';

-- ============================================================================
-- _026  vw_rpt_statutory_nssf
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_nssf_026 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    staff.nssf_number   AS nssf_number,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS nssf_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff_015 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00004'';
';

-- ============================================================================
-- _027  vw_rpt_statutory_shif
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_shif_027 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    staff.nhif_number   AS nhif_number,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS shif_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff_015 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00005'';
';

-- ============================================================================
-- _028  vw_rpt_statutory_housing_levy
--       References: vw_sys_staff_015
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_housing_levy_028 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS housing_levy_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff_015 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00022'';
';

-- ============================================================================
-- END OF SCRIPT  (18 views: _011 through _028)
-- ============================================================================

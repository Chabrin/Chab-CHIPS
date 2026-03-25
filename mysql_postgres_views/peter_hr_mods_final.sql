-- ============================================================================
-- HR MODULE — COMBINED FINAL: CREATE OR ALTER VIEW Scripts
-- ============================================================================
-- Sources:   hr_views_mod.sql  (views _P_01 – _P_18)
--            peter_hr_mods.sql (views _P_19 – _P_24, unique additions)
-- Database:  CHABRINS
-- Generated: 2026-03-25
--
-- Exclusion rule: views already present in other_views_mod.sql are excluded.
--   other_views_mod.sql contains PM/CB module views (_001 – _024: properties,
--   tenants, invoices, payments, receipts, returns, officers, vouchers, etc.).
--   None of the HR views below appear in other_views_mod.sql; all are included.
--
-- Execution: Run the entire file as ONE query (no GO separators).
--            Each view is wrapped in EXEC sp_executesql so SQL Server
--            treats each CREATE OR ALTER VIEW as its own batch internally.
--
-- View index
--   ── From hr_views_mod.sql ──────────────────────────────────────────────
--   _P_01  vw_sys_set_departments_P_01
--   _P_02  vw_sys_set_staff_positions_P_02
--   _P_03  vw_sys_set_banks_P_03
--   _P_04  vw_sys_set_banks_branches_P_04
--   _P_05  vw_sys_staff_P_05
--   _P_06  vw_sys_staff_next_of_kin_P_06
--   _P_07  vw_sys_hr_payroll_elements_P_07
--   _P_08  vw_um_tax_bands_P_08
--   _P_09  vw_sys_hr_payroll_calendar_P_09
--   _P_10  vw_sys_hr_payroll_periods_P_10
--   _P_11  vw_sys_hr_payroll_summary_P_11
--   _P_12  vw_sys_hr_payslip_elements_P_12
--   _P_13  vw_sys_helb_deductions_P_13
--   _P_14  vw_sys_hr_employee_amortized_deductions_P_14
--   _P_15  vw_sys_hr_p9a_P_15
--   _P_16  vw_rpt_statutory_nssf_P_16
--   _P_17  vw_rpt_statutory_shif_P_17
--   _P_18  vw_rpt_statutory_housing_levy_P_18
--   ── From peter_hr_mods.sql (unique additions) ───────────────────────────
--   _P_19  vw_sys_pm_clients_17_repeat_P_19
--   _P_20  vw_sys_pm_clients_19_repeat_P_20
--   _P_21  vw_sys_hr_employee_contracts_P_21
--   _P_22  vw_sys_hr_employee_contracts_279_repeat_P_22
--   _P_23  vw_sys_hr_payroll_deductions_register_P_23
--   _P_24  vw_sys_hr_payroll_elements_repeat_staff_positions_id_P_24
-- ============================================================================

USE CHABRINS;

-- ============================================================================
-- _P_01  vw_sys_set_departments_P_01
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_departments_P_01 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DEPTCODE)   AS id,
    GETDATE()                                AS date_time,
    DEPTNAME                                 AS department_name,
    DEPTCODE                                 AS mssql_dept_code
FROM CHDEPT;
';

-- ============================================================================
-- _P_02  vw_sys_set_staff_positions_P_02
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_staff_positions_P_02 AS
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
-- _P_03  vw_sys_set_banks_P_03
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_banks_P_03 AS
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
-- _P_04  vw_sys_set_banks_branches_P_04
--       References: vw_sys_set_banks_P_03
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_banks_branches_P_04 AS
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
LEFT JOIN vw_sys_set_banks_P_03 bk ON br.BANKCODE = bk.mssql_bank_code;
';

-- ============================================================================
-- _P_05  vw_sys_staff_P_05
--       References: vw_sys_set_departments_P_01
--                   vw_sys_set_staff_positions_P_02
--                   vw_sys_set_banks_P_03
--                   vw_sys_set_banks_branches_P_04
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_staff_P_05 AS
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
LEFT JOIN vw_sys_set_departments_P_01       d  ON s.DEPTCODE = d.mssql_dept_code
LEFT JOIN vw_sys_set_staff_positions_P_02   p  ON s.DESIGN   = p.mssql_design_code
LEFT JOIN vw_sys_set_banks_P_03             b  ON s.BANKCODE = b.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches_P_04    br ON s.BANKBRAN = br.mssql_branch_code
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
-- _P_06  vw_sys_staff_next_of_kin_P_06
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_staff_next_of_kin_P_06 AS
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
-- _P_07  vw_sys_hr_payroll_elements_P_07
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_elements_P_07 AS
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
-- _P_08  vw_um_tax_bands_P_08
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_um_tax_bands_P_08 AS
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
-- _P_09  vw_sys_hr_payroll_calendar_P_09
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_calendar_P_09 AS
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
-- _P_10  vw_sys_hr_payroll_periods_P_10
--       References: vw_sys_hr_payroll_calendar_P_09
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_periods_P_10 AS
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
    FROM vw_sys_hr_payroll_calendar_P_09 cal
    WHERE cal.mssql_year  = p.PRYEAR
      AND cal.mssql_month = p.PRMNTH
) cal;
';

-- ============================================================================
-- _P_11  vw_sys_hr_payroll_summary_P_11
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_summary_P_11 AS
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
LEFT JOIN vw_sys_staff_P_05  staff     ON e.EMPNUMB = staff.staff_number
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
-- _P_12  vw_sys_hr_payslip_elements_P_12
--       References: vw_sys_hr_payroll_elements_P_07
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payslip_elements_P_12 AS
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
LEFT JOIN vw_sys_hr_payroll_elements_P_07 el ON sl.EDCODE = el.mssql_ed_code;
';

-- ============================================================================
-- _P_13  vw_sys_helb_deductions_P_13
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_helb_deductions_P_13 AS
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
LEFT JOIN vw_sys_staff_P_05 staff ON l.LORDID = staff.staff_number;
';

-- ============================================================================
-- _P_14  vw_sys_hr_employee_amortized_deductions_P_14
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_employee_amortized_deductions_P_14 AS
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
LEFT JOIN vw_sys_staff_P_05 staff ON l.LORDID = staff.staff_number;
';

-- ============================================================================
-- _P_15  vw_sys_hr_p9a_P_15
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_p9a_P_15 AS
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
LEFT JOIN vw_sys_staff_P_05 staff ON p.EMPNUMB = staff.staff_number;
';

-- ============================================================================
-- _P_16  vw_rpt_statutory_nssf_P_16
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_nssf_P_16 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    staff.nssf_number   AS nssf_number,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS nssf_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff_P_05 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00004'';
';

-- ============================================================================
-- _P_17  vw_rpt_statutory_shif_P_17
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_shif_P_17 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    staff.nhif_number   AS nhif_number,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS shif_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff_P_05 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00005'';
';

-- ============================================================================
-- _P_18  vw_rpt_statutory_housing_levy_P_18
--       References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_housing_levy_P_18 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS housing_levy_amount
FROM CHPYSL sl
LEFT JOIN vw_sys_staff_P_05 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00022'';
';

-- ============================================================================
-- _P_19  vw_sys_pm_clients_17_repeat_P_19
--        Staff Bank Accounts (CHSTFF bank columns → sys_pm_clients_17_repeat)
--        Model: ClientBankAccount  |  Controller: ClientsController::saveBankAccounts
--        CHSTFF stores one bank per staff member (BANKCODE, BANKBRAN, BANKACC).
--        Laravel stores bank accounts as child rows in sys_pm_clients_17_repeat
--        linked to the parent client/staff via parent_id.
--        Only staff with a non-empty BANKACC are included.
--        References: vw_sys_staff_P_05
--                    vw_sys_set_banks_P_03
--                    vw_sys_set_banks_branches_P_04
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_pm_clients_17_repeat_P_19] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY s.EMPNUMB)          AS id,
    staff.id                                         AS parent_id,
    -- FK to sys_staff / sys_pm_clients (resolved via vw_sys_staff_P_05)
    bk.id                                            AS bank_id,
    -- FK to sys_set_banks (resolved from BANKCODE via vw_sys_set_banks_P_03)
    s.FULLNAME                                       AS account_name,
    -- No separate account name in MSSQL; staff full name used
    s.BANKACC                                        AS account_number,
    br.id                                            AS branch,
    -- FK to sys_set_banks_branches (resolved from BANKBRAN via _014)
    CAST(NULL AS DECIMAL(13,2))                      AS return_percentage,
    -- No MSSQL equivalent
    s.EMPNUMB                                        AS mssql_staff_number,
    s.BANKCODE                                       AS mssql_bank_code,
    s.BANKBRAN                                       AS mssql_branch_code
FROM CHSTFF s
LEFT JOIN vw_sys_staff_P_05              staff ON s.EMPNUMB  = staff.staff_number
LEFT JOIN vw_sys_set_banks_P_03          bk    ON s.BANKCODE = bk.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches_P_04 br    ON s.BANKBRAN = br.mssql_branch_code
                                              AND s.BANKCODE = br.mssql_bank_code
WHERE s.BANKACC IS NOT NULL
  AND LTRIM(RTRIM(s.BANKACC)) != '''';
';

-- ============================================================================
-- _P_20  vw_sys_pm_clients_19_repeat_P_20
--        Staff Other Contacts (CHSCNTS → sys_pm_clients_19_repeat)
--        Model: ClientOtherContact  |  Controller: ClientsController::saveOtherContacts
--        CHSCNTS stores alternate contact details (home/company addresses,
--        mobile, email) for staff. Laravel stores these as child rows in
--        sys_pm_clients_19_repeat linked via parent_id.
--        Only rows with at least a MOBNO or CUEMAIL are included.
--        References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_pm_clients_19_repeat_P_20] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY c.EMPNUMB)          AS id,
    staff.id                                         AS parent_id,
    -- FK to sys_staff / sys_pm_clients (resolved via vw_sys_staff_P_05)
    staff.full_names                                 AS contact_names,
    c.CUEMAIL                                        AS other_email,
    c.MOBNO                                          AS other_mobile_number,
    c.EMPNUMB                                        AS mssql_staff_number,
    c.HMPBOX                                         AS mssql_home_po_box,
    c.HMTOWN                                         AS mssql_home_town,
    c.HHSETEL                                        AS mssql_home_tel,
    c.HFAXNO                                         AS mssql_home_fax,
    c.HMPCODE                                        AS mssql_home_postal_code,
    c.CAPBOX                                         AS mssql_company_po_box,
    c.CAPCODE                                        AS mssql_company_postal_code,
    c.CATOWN                                         AS mssql_company_town,
    c.CFAXNO                                         AS mssql_company_fax,
    c.PHYADD                                         AS mssql_physical_address
FROM CHSCNTS c
LEFT JOIN vw_sys_staff_P_05 staff ON c.EMPNUMB = staff.staff_number
WHERE c.MOBNO IS NOT NULL OR c.CUEMAIL IS NOT NULL;
';

-- ============================================================================
-- _P_21  vw_sys_hr_employee_contracts_P_21
--        Employee Contracts (derived from CHPYSL basic salary history)
--        Maps to: sys_hr_employee_contracts (Contract model)
--        The MSSQL system has no explicit contracts table. Contracts are
--        DERIVED from payslip history: each distinct (EMPNUMB, basic_salary)
--        combination represents one contract period.
--          date_start = 1st day of first salary month
--          date_end   = last day of last salary month (NULL = active contract)
--        References: vw_sys_staff_P_05
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_employee_contracts_P_21] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.EMPNUMB, p.first_period)  AS id,
    CAST(GETDATE() AS DATETIME)                              AS date_time,
    s.id                                                     AS staff_id,
    -- FK → sys_staff.id
    CAST(DATEFROMPARTS(
        p.first_period / 100,
        p.first_period % 100, 1
    ) AS DATETIME)                                           AS date_start,
    CASE
        WHEN p.last_period = emp_max.max_period
        THEN CAST(NULL AS DATETIME)
        ELSE CAST(EOMONTH(DATEFROMPARTS(
                 p.last_period / 100,
                 p.last_period % 100, 1
             )) AS DATETIME)
    END                                                      AS date_end,
    CASE
        WHEN p.last_period = emp_max.max_period THEN 5
        ELSE 6
    END                                                      AS current_status_id,
    s.position_id                                            AS position_id,
    -- FK → sys_set_staff_positions.id
    CAST(NULL AS INT)                                        AS created_by,
    p.salary                                                 AS basic_salary,
    s.staff_number                                           AS staff_number,
    p.EMPNUMB                                                AS mssql_emp_number,
    p.first_period                                           AS mssql_first_period,
    p.last_period                                            AS mssql_last_period,
    p.months                                                 AS mssql_months_count
FROM (
    SELECT
        EMPNUMB,
        CAST(EDAMT AS DECIMAL(13,2))   AS salary,
        MIN(PRYEAR * 100 + PRMNTH)     AS first_period,
        MAX(PRYEAR * 100 + PRMNTH)     AS last_period,
        COUNT(*)                        AS months
    FROM CHPYSL
    WHERE EDNAME = ''BASIC SALARY''
    GROUP BY EMPNUMB, CAST(EDAMT AS DECIMAL(13,2))
) p
JOIN (
    SELECT
        EMPNUMB,
        MAX(PRYEAR * 100 + PRMNTH) AS max_period
    FROM CHPYSL
    WHERE EDNAME = ''BASIC SALARY''
    GROUP BY EMPNUMB
) emp_max ON p.EMPNUMB = emp_max.EMPNUMB
LEFT JOIN vw_sys_staff_P_05 s ON TRY_CAST(p.EMPNUMB AS INT) = s.id;
';

-- ============================================================================
-- _P_22  vw_sys_hr_employee_contracts_279_repeat_P_22
--        Contract Element Lines
--        Maps to: sys_hr_employee_contracts_279_repeat (ContractElement model)
--        For each contract period (from _P_03), lists the distinct payroll
--        elements active during that period. Excludes computed/total
--        pseudo-elements. Amount = average monthly value during the contract.
--        References: vw_sys_hr_employee_contracts_P_21
--                    vw_sys_hr_payroll_elements_P_07
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_employee_contracts_279_repeat_P_22] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY ct.id, el.id)                    AS id,
    ct.id                                                         AS parent_id,
    -- FK → vw_sys_hr_employee_contracts_P_21.id
    el.id                                                         AS element_id,
    -- FK → vw_sys_hr_payroll_elements_P_07.id
    CAST(1 AS INT)                                                AS entry_type_id,
    CAST(AVG(CAST(ps.EDAMT AS DECIMAL(13,2))) AS DECIMAL(13,2))  AS amount
FROM vw_sys_hr_employee_contracts_P_21 ct
JOIN CHPYSL ps
    ON  ps.EMPNUMB  = ct.mssql_emp_number
    AND (ps.PRYEAR * 100 + ps.PRMNTH) BETWEEN ct.mssql_first_period
                                           AND ct.mssql_last_period
    AND ps.EDNAME   != ''BASIC SALARY''
    AND ps.EDCODE NOT IN (''DEDUCTIONS'', ''TAXC'', ''TAXR'', ''TEARNINGS'')
    AND ps.EDAMT    != 0
JOIN vw_sys_hr_payroll_elements_P_07 el
    ON ps.EDCODE = el.mssql_ed_code
GROUP BY ct.id, el.id;
';

-- ============================================================================
-- _P_23  vw_sys_hr_payroll_deductions_register_P_23
--        Payroll Deductions Register (audit log)
--        Maps to: sys_hr_payroll_deductions_register
--        One row per deduction element per employee per payroll period.
--        Covers all actual deduction EDCODEs: statutory + voluntary + loans.
--        References: vw_sys_staff_P_05
--                    vw_sys_hr_payroll_elements_P_07
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_payroll_deductions_register_P_23] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY s.id, p.PRYEAR, p.PRMNTH, p.EDCODE) AS id,
    GETDATE()                                                         AS date_time,
    CAST(DATEFROMPARTS(p.PRYEAR, p.PRMNTH, 1) AS DATETIME)           AS payroll_period,
    s.id                                                              AS staff_id,
    -- FK → sys_staff.id
    el.id                                                             AS deduction_type_id,
    -- FK → sys_hr_payroll_elements.id
    CAST(p.EDAMT AS DECIMAL(13,2))                                    AS amount,
    CAST(0 AS DECIMAL(13,2))                                          AS company_amount,
    CAST(p.EDAMT AS DECIMAL(13,2))                                    AS total_amount,
    CAST(ISNULL(bs.EDAMT, 0) AS DECIMAL(13,2))                        AS salary
FROM CHPYSL p
INNER JOIN vw_sys_staff_P_05               s  ON TRY_CAST(p.EMPNUMB AS INT) = s.id
INNER JOIN vw_sys_hr_payroll_elements_P_07 el ON p.EDCODE = el.mssql_ed_code
LEFT JOIN CHPYSL bs
    ON  p.EMPNUMB = bs.EMPNUMB
    AND p.PRYEAR  = bs.PRYEAR
    AND p.PRMNTH  = bs.PRMNTH
    AND bs.EDCODE = ''00001''
WHERE p.EDCODE IN (
    ''00004'',   -- NSSF
    ''00005'',   -- NHIF / SHIF
    ''00007'',   -- Salary Advance
    ''00009'',   -- Akiba Sacco Loan
    ''00010'',   -- Social Welfare
    ''00011'',   -- Akiba Share Contribution
    ''00012'',   -- Other Deductions
    ''00013'',   -- Tax Payable (PAYE)
    ''00015'',   -- Absenteeism
    ''00016'',   -- Unpaid Leave
    ''00017'',   -- Security Deposit
    ''00020'',   -- HELB Loans
    ''00022'',   -- Housing Levy
    ''00024''    -- Loss Due to Negligence
)
AND p.EDAMT != 0;
';

-- ============================================================================
-- _P_24  vw_sys_hr_payroll_elements_repeat_staff_positions_id_P_24
--        Payroll Element ↔ Staff Position Pivot (many-to-many)
--        Maps to: sys_hr_payroll_elements_repeat_staff_positions_id
--        Derived: which payroll elements have historically been applied to
--        which staff positions. parent_id = element_id.
--        Pseudo-element and computed EDCODEs are excluded.
--        References: vw_sys_staff_P_05
--                    vw_sys_hr_payroll_elements_P_07
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_payroll_elements_repeat_staff_positions_id_P_24] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY el.id, s.position_id) AS id,
    el.id                                              AS parent_id,
    -- FK → sys_hr_payroll_elements.id
    CAST(s.position_id AS INT)                         AS staff_positions_id
    -- FK → sys_set_staff_positions.id
FROM CHPYSL p
INNER JOIN vw_sys_staff_P_05               s  ON TRY_CAST(p.EMPNUMB AS INT) = s.id
INNER JOIN vw_sys_hr_payroll_elements_P_07 el ON p.EDCODE = el.mssql_ed_code
WHERE p.EDCODE NOT IN (
    ''00001'',       -- Basic Salary
    ''DEDUCTIONS'',  -- Computed total deductions
    ''NETP'',        -- Net pay
    ''NHIFR'',       -- NHIF relief
    ''TAXC'',        -- Tax credit
    ''TAXP'',        -- Tax payable
    ''TAXR'',        -- Tax relief
    ''TEARNINGS''    -- Total earnings
)
  AND p.EDAMT        != 0
  AND s.position_id  IS NOT NULL
GROUP BY el.id, s.position_id;
';

-- ============================================================================
-- EXCLUDED VIEWS — already present in other_views_mod.sql
-- ============================================================================
-- other_views_mod.sql covers the PM/CB module (_001 – _024). None of the
-- 24 HR views above appear there. The table below confirms zero overlap:
--
--  other_views_mod.sql target tables          HR views target tables
--  ─────────────────────────────────────────  ────────────────────────────────
--  sys_pm_properties                          sys_set_departments
--  sys_pm_clients_landlords                   sys_set_staff_positions
--  sys_pm_units                               sys_set_banks
--  sys_pm_clients_tenants                     sys_set_banks_branches
--  sys_pm_lease_agreements                    sys_staff
--  sys_set_zones                              sys_staff_next_of_kin
--  sys_set_zone_areas                         sys_hr_payroll_elements
--  sys_set_invoiceable_items                  um_tax_bands
--  sys_pm_debtors_invoices                    sys_hr_payroll_calendar
--  sys_pm_tenants_payments_allocated          sys_hr_payroll_periods
--  sys_pm_tenants_payments_unallocated        sys_hr_payroll_summary
--  sys_pm_tenants_accounts_trans              sys_hr_payslip_elements
--  cb_receipts                                sys_helb_deductions
--  cb_returns_summary                         sys_hr_employee_amortized_deductions
--  cb_returns_clients                         sys_hr_p9a
--  josya_users (officers)                     rpt_statutory_nssf
--  sys_pm_service_agreements                  rpt_statutory_shif
--  cb_landlord_account                        rpt_statutory_housing_levy
--  cb_money_recoveries                        sys_pm_clients_17_repeat  (bank accounts)
--  cb_payment_vouchers_headers (new)          sys_pm_clients_19_repeat  (contacts)
--  cb_payment_vouchers_headers (old)          sys_hr_employee_contracts
--  cb_payment_vouchers_lines (new)            sys_hr_employee_contracts_279_repeat
--  cb_payment_vouchers_lines (old)            sys_hr_payroll_deductions_register
--                                             sys_hr_payroll_elements_repeat_…
--
-- ============================================================================
-- END OF SCRIPT  (24 views: _P_01 – _P_18  +  _P_19 – _P_24)
-- ============================================================================

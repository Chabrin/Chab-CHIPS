/*
================================================================================
  CHABRINS (MSSQL) -> chips_db_v3 (PostgreSQL)  —  DATA MIGRATION VIEWS
================================================================================
  Generated: 2026-02-19
  Source DB:  CHABRINS (MSSQL, localhost, Windows Auth)
  Target DB:  chips_db_v3 (PostgreSQL, 127.0.0.1:5432, schema: chips_db_v3)

  PURPOSE:
    These are SELECT queries (views) that read from the MSSQL CHABRINS tables
    and produce output matching the PostgreSQL target table columns.
    Use these with INSERT INTO ... SELECT or via a linked server / ETL tool.

  HOW TO USE:
    Option A — Linked Server (run from MSSQL):
      INSERT INTO [POSTGRES_LINKED_SERVER].chips_db_v3.chips_db_v3.<target_table>
      SELECT ... FROM <view_below>

    Option B — ETL / Python script:
      1. Run SELECT from MSSQL CHABRINS
      2. INSERT into PostgreSQL chips_db_v3.chips_db_v3.<target_table>

    Option C — Create as MSSQL Views (rerunnable):
      CREATE OR ALTER VIEW vw_<target_table> AS SELECT ...

  NOTES ON REFERENCE TABLES:
    Several CHABRINS tables use TEXT keys (TENNAME, BLOCKID, LORDID, etc.)
    while PostgreSQL uses INTEGER foreign keys. These views use LEFT JOINs
    to inline-numbered subqueries on the CHABRINS reference tables
    (CHLRDS, CHBLOCKS, CHBUNITS, CHTENS, CHOFFICERS, CHSTFF, CHBANK, CHCHRS,
     CHFACTV) so that FK columns are resolved directly within each view.
    The ROW_NUMBER() inside each subquery produces the same sequential id
    that the target PG table will receive when loaded in the same order.

  UNMAPPED REFERENCE TABLES IN CHABRINS (not in the mapping CSV):
    - CHLRH    : Landlord-Property links  — maps landlord LORDID to BLOCKID
    - CHOFFICERS: Field officers/staff    — maps to sys_staff or sys_set_system_users
    - CHTNTRS  : Tenant invoices/transactions (1,156,397 rows) — maps to sys_pm_debtors_invoices
    - CHTPAYS  : Tenant payments detail   (1,435,222 rows)   — maps to sys_pm_tenants_payments
    - CHTACC   : Tenant account ledger    — maps to sys_pm_tenants_accounts_transactions
    - CHRHMS   : Returns master           — maps to sys_pm_clients_returns_master or returns_batches
    - CHSTFF   : Staff/employees          — maps to sys_staff
    - CHTAXES  : Tax rates (VAT)          — single row, VAT 16%
    - CHTYPES  : Unit type codes          — maps to unit_type lookups
    (CHLRDS is now mapped via view #6 → sys_pm_clients)
    (CHTENS is now mapped via view #6b → sys_pm_clients / vw_sys_pm_clients_from_chtenants)

  IMPORTANT:
    - All queries use ROW_NUMBER() OVER(...) AS id to generate sequential IDs
    - GETDATE() is used for date_time (creation timestamp)
    - FK columns are resolved via LEFT JOINs to reference subqueries that
      use ROW_NUMBER() OVER (ORDER BY <key>) to produce matching PG ids
    - LEFT JOINs ensure rows are not lost if a reference key is missing
    - NULL appears only where no CHABRINS source column exists for the field

================================================================================
*/


-- ============================================================================
-- 1. BANKS: CHBANK -> sys_set_banks
-- ============================================================================
-- NOTE: Simple master data, no FK dependencies.

CREATE OR ALTER VIEW vw_sys_set_banks AS
SELECT
    ROW_NUMBER() OVER (ORDER BY BANKCODE)          AS id,
    GETDATE()                                       AS date_time,
    RTRIM(LTRIM(BANKNAME))                          AS bank_name,
    BANKCODE                                        AS address,      -- store code in address for reference
    1                                               AS current_status_id  -- Active
FROM CHABRINS.dbo.CHBANK
WHERE BANKNAME IS NOT NULL AND BANKNAME <> '';
GO


-- ============================================================================
-- 2. BANK BRANCHES: CHBBRA -> sys_set_banks_branches
-- ============================================================================
-- NOTE: Requires sys_set_banks to be loaded first (bank_id FK).
-- LOOKUP: bank_id = (SELECT id FROM sys_set_banks WHERE address = CHBBRA.BANKCODE)

CREATE OR ALTER VIEW vw_sys_set_banks_branches AS
SELECT
    ROW_NUMBER() OVER (ORDER BY b.BANKCODE, b.BRANCODE)    AS id,
    GETDATE()                                               AS date_time,
    bk.bank_row_id                                          AS bank_id,
    -- JOINED: CHBANK via BANKCODE to get sequential bank id
    RTRIM(LTRIM(b.BRANNAME))                                AS branch_name,
    1                                                       AS current_status_id,
    RTRIM(LTRIM(b.BRANCODE))                                AS branch_code
FROM CHABRINS.dbo.CHBBRA b
LEFT JOIN (
    SELECT BANKCODE, ROW_NUMBER() OVER (ORDER BY BANKCODE) AS bank_row_id
    FROM CHABRINS.dbo.CHBANK
    WHERE BANKNAME IS NOT NULL AND BANKNAME <> ''
) bk ON bk.BANKCODE = b.BANKCODE
WHERE b.BRANNAME IS NOT NULL;
GO


-- ============================================================================
-- 3. CHART OF ACCOUNTS (Master): CHACCTS -> sys_gl_chart_accounts_master
-- ============================================================================
-- NOTE: Also fed by CHFACTS and CHFACTV. Use CHFACTV as the most complete source.
-- CHACCTS has basic info; CHFACTV has status, currency, grouping.
-- Strategy: Use CHFACTV as primary, CHACCTS as fallback for missing accounts.

CREATE OR ALTER VIEW vw_sys_gl_chart_accounts_master AS
SELECT
    ROW_NUMBER() OVER (ORDER BY ACCNUM)                AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(ACCDESC))                               AS account_name,
    RTRIM(LTRIM(ACCNUM))                                AS account_code,
    NULL                                                AS account_group_id,
    NULL                                                AS category_id,
    NULL                                                AS class_id,
    CASE WHEN ACCSTAT = 'Active' THEN 1 ELSE 2 END     AS current_status_id,
    NULL                                                AS financial_statement_id,
    NULL                                                AS group_id,
    NULL                                                AS sub_group_id,
    CASE WHEN BALTYPE = 'D' THEN 'Debit'
         WHEN BALTYPE = 'C' THEN 'Credit'
         ELSE 'Debit' END                               AS normally
FROM CHABRINS.dbo.CHFACTV
WHERE ACCNUM IS NOT NULL AND ACCNUM <> '';
GO

/*
  NOTE: CHACCTS and CHFACTS also map to this table.
  - CHACCTS has: ACCNTNO (code), ACCNAME, GLCODE, GLACCNO — subset of CHFACTV
  - CHFACTS has: ACCNUM, ACCDESC, LASTDR, LASTCR — similar to CHFACTV
  - Recommendation: Load CHFACTV first (most complete), then UPSERT any missing
    accounts from CHACCTS/CHFACTS using account_code as the match key.
*/


-- ============================================================================
-- 4. INVOICEABLE ITEMS / CHARGES: CHCHRS -> sys_set_invoiceable_items
-- ============================================================================
-- NOTE: Standalone master data. CHRGID is the legacy charge code.

CREATE OR ALTER VIEW vw_sys_set_invoiceable_items AS
SELECT
    ROW_NUMBER() OVER (ORDER BY CHRGID)                AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(CHRDSC))                                AS item_name,
    RTRIM(LTRIM(CHRGID))                                AS billing_code,
    NULL                                                AS feature,
    NULL                                                AS payment_priority,
    CASE WHEN CHRSTT = 'Commissionable' THEN 'Yes' ELSE 'No' END  AS commissionable,
    NULL                                                AS default_cycle_id,
    CASE WHEN CHRSTT IS NOT NULL THEN 1 ELSE 2 END     AS current_status_id,
    NULL                                                AS default_expense_account_id,
    NULL                                                AS advance_payable,
    CASE WHEN CHRGTYP = 'BILL' THEN 'Yes' ELSE 'No' END  AS is_bill,
    'Yes'                                               AS fixed_amount,
    ISNULL(DEFAMT, 0)                                   AS amount
FROM CHABRINS.dbo.CHCHRS
WHERE CHRGID IS NOT NULL;
GO


-- ============================================================================
-- 5. DEPENDENT CHARGES: CHCDC -> sys_set_clients_dependent_charges
-- ============================================================================
-- NOTE: Simple parent-child charge link.
-- LOOKUP: item_type_id would resolve from CHCHRS.CHRGID

CREATE OR ALTER VIEW vw_sys_set_clients_dependent_charges AS
SELECT
    ROW_NUMBER() OVER (ORDER BY dc.PCHRGID, dc.DCHRGID)  AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(dc.PCHRGID)) + ' -> ' + RTRIM(LTRIM(dc.DCHRGID))  AS title,
    ch.item_row_id                                      AS item_type_id,
    -- JOINED: CHCHRS via PCHRGID to get parent charge sequential id
    NULL                                                AS credit_account_id,
    1                                                   AS current_status_id,
    NULL                                                AS created_by_id,
    0                                                   AS amount
FROM CHABRINS.dbo.CHCDC dc
LEFT JOIN (
    SELECT CHRGID, ROW_NUMBER() OVER (ORDER BY CHRGID) AS item_row_id
    FROM CHABRINS.dbo.CHCHRS
    WHERE CHRGID IS NOT NULL
) ch ON ch.CHRGID = dc.PCHRGID
WHERE dc.PCHRGID IS NOT NULL;
GO


-- ============================================================================
-- 6. CLIENTS / LANDLORDS: CHLRDS -> sys_pm_clients
-- ============================================================================
-- NOTE: CHLRDS is the landlord master table. In chips_db_v3, landlords = "clients".
--   CHLRDS was NOT in the mapping CSV but is essential as the client source.
--   BANKN field contains free-text bank names/notes (429 distinct values)
--   which are too messy for reliable automated JOIN to CHBANK.
--   bank_id is set to NULL; BANKN stored in account_name for manual cleanup.
--   VENDORID stores the LAN-xxxxx reference code.
--   LANDSTT: 'Active' (915), 'Inactive' (2), NULL (268).

CREATE OR ALTER VIEW [vw_sys_pm_clients_landlords_002] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY c.LORDID)               AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(c.LFNAME))                              AS names,
    RTRIM(LTRIM(c.ADDRSS1))                             AS address,
    CASE WHEN c.VATSTAT = 1 THEN 'VAT Registered'
         ELSE NULL END                                  AS vat_number,
    NULL                                                AS pin_number,
    RTRIM(LTRIM(c.TLPHON))                              AS mobile_number,
    NULL                                                AS email_address,
    NULL                                                AS bank_id,
    -- NOTE: BANKN contains free-text (bank name + branch + notes).
    -- Cannot reliably JOIN to CHBANK. Needs post-migration manual cleanup.
    RTRIM(LTRIM(c.BANKN))                               AS account_name,
    RTRIM(LTRIM(c.ACCNO))                               AS account_number,
    NULL                                                AS username,
    NULL                                                AS client_password,
    RTRIM(LTRIM(c.LORDID))                              AS uid,
    NULL                                                AS group_id,
    c.LSTEDIT                                           AS registered_date,
    RTRIM(LTRIM(c.VENDORID))                            AS reference_number,
    NULL                                                AS created_by,
    NULL                                                AS nationality_id,
    NULL                                                AS national_id,
    NULL                                                AS passport_number,
    NULL                                                AS client_type_id,
    NULL                                                AS photo,
    NULL                                                AS documents,
    CASE WHEN RTRIM(LTRIM(c.LANDSTT)) = 'Active' THEN 1
         ELSE 2 END                                     AS current_status_id,
    CASE WHEN RTRIM(LTRIM(c.LANDSTT)) = 'Active' THEN 1
         ELSE 2 END                                     AS client_status_id,
    NULL                                                AS lead_id,
    NULL                                                AS type_id,
    RTRIM(LTRIM(c.LORDID))                              AS client_id,
    NULL                                                AS second_name,
    NULL                                                AS last_name,
    NULL                                                AS title,
    NULL                                                AS gender,
    NULL                                                AS prefered_messages_language_id,
    NULL                                                AS property_id,
    NULL                                                AS sla_id,
    NULL                                                AS unit_id,
    NULL                                                AS lease_start_date,
    NULL                                                AS lease_years,
    c.CURRBAL                                           AS rent_amount,
    c.RATETYP                                           AS escalation_rate,
    NULL                                                AS frequency,
    RTRIM(LTRIM(c.ADDRSS2))                             AS address_2,
    RTRIM(LTRIM(c.ADDRSS3))                             AS address_3,
    NULL                                                AS promas_id,
    RTRIM(LTRIM(c.MANHSE))                              AS properties,
    NULL                                                AS overdraft_penalty
FROM CHABRINS.dbo.CHLRDS c
WHERE c.LORDID IS NOT NULL AND RTRIM(LTRIM(c.LORDID)) <> '';



-- ============================================================================
-- 6b. CLIENTS (Tenants): CHTENS -> sys_pm_clients
-- ============================================================================
-- NOTE: CHTENS is the tenant master table. In chips_db_v3, tenants are also
--   stored in sys_pm_clients, distinguished by reference_number prefix 'TEN-'.
--   The Client model uses scopeTenants() -> WHERE reference_number LIKE 'TEN%'.
--   CHTENS has 133,710 rows (IN: 40,892 / OUT: 92,816 / NULL: 2).
--   TENID is a national ID / passport number (68,165 unique, 49,151 NULL).
--   TENANTID is a composite: BLOCKNO+UNITNO+TENNAME+TSTATUS (not a clean key).
--   reference_number = 'TEN-' + ROW_NUMBER to generate unique TEN-xxxxx codes.
--   BLOCKNO/UNITNO are used to resolve property_id and unit_id via JOINs.
--   TSTATUS: 'IN' (active) = status 1, 'OUT' (vacated) = status 2.

CREATE OR ALTER VIEW vw_sys_pm_clients_tenants AS
SELECT
    ROW_NUMBER() OVER (ORDER BY t.TENNAME, t.BLOCKNO, t.UNITNO)  AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(t.TENNAME))                             AS names,
    RTRIM(LTRIM(t.TENADDR))                             AS address,
    NULL                                                AS vat_number,
    NULL                                                AS pin_number,
    RTRIM(LTRIM(CAST(t.TELMOB AS VARCHAR(200))))        AS mobile_number,
    RTRIM(LTRIM(t.TENEMAIL))                            AS email_address,
    NULL                                                AS bank_id,
    NULL                                                AS account_name,
    NULL                                                AS account_number,
    NULL                                                AS username,
    NULL                                                AS client_password,
    RTRIM(LTRIM(t.TENID))                               AS uid,
    NULL                                                AS group_id,
    t.LEASSDAT                                          AS registered_date,
    'TEN-' + RIGHT('00000' + CAST(
        ROW_NUMBER() OVER (ORDER BY t.TENNAME, t.BLOCKNO, t.UNITNO)
        AS VARCHAR(10)), 6)                             AS reference_number,
    -- Generates TEN-000001, TEN-000002, ... to match scopeTenants() pattern
    NULL                                                AS created_by,
    NULL                                                AS nationality_id,
    RTRIM(LTRIM(t.TENID))                               AS national_id,
    NULL                                                AS passport_number,
    RTRIM(LTRIM(t.TENTYPE))                             AS client_type_id,
    NULL                                                AS photo,
    NULL                                                AS documents,
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = 'IN' THEN 1
         ELSE 2 END                                     AS current_status_id,
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = 'IN' THEN 1
         ELSE 2 END                                     AS client_status_id,
    NULL                                                AS lead_id,
    RTRIM(LTRIM(t.TYPLEAS))                             AS type_id,
    RTRIM(LTRIM(t.TENANTID))                            AS client_id,
    NULL                                                AS second_name,
    NULL                                                AS last_name,
    NULL                                                AS title,
    NULL                                                AS gender,
    NULL                                                AS prefered_messages_language_id,
    blk.block_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKNO to get property sequential id
    NULL                                                AS sla_id,
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKNO+UNITNO to get unit sequential id
    t.LEASSDAT                                          AS lease_start_date,
    t.LEASELEN                                          AS lease_years,
    t.CURBAL                                            AS rent_amount,
    t.LEASEREN                                          AS escalation_rate,
    RTRIM(LTRIM(t.LEASLETY))                            AS frequency,
    RTRIM(LTRIM(t.TENTWN))                              AS address_2,
    RTRIM(LTRIM(t.TENNAT))                              AS address_3,
    NULL                                                AS promas_id,
    NULL                                                AS properties,
    NULL                                                AS overdraft_penalty
FROM CHABRINS.dbo.CHTENS t
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS block_row_id
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) blk ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE BLOCKID IS NOT NULL AND UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
      AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(t.UNITNO))
WHERE t.TENNAME IS NOT NULL AND RTRIM(LTRIM(t.TENNAME)) <> '';
GO


-- ============================================================================
-- 8. PROPERTIES / BLOCKS: CHBLOCKS -> sys_pm_properties
-- ============================================================================
-- NOTE: CHBLOCKS.LLORDID maps to landlords (CHLRDS) -> clients.
-- LOOKUP: client_id from CHLRDS.LORDID (must load clients first — see view #6)
--
-- CHLRDS (Landlords) — now mapped via view #6 (vw_sys_pm_clients).
--   client_id is resolved by LEFT JOIN to CHLRDS with ROW_NUMBER().
--
-- EXTRA TABLE NEEDED: CHLRH (Landlord-Property link)
--   CHLRH maps LORDID -> BLOCKID with status. Use to verify client_id assignment.
--
-- EXTRA TABLE NEEDED: CHOFFICERS (Field Officers)
--   CHOFFICERS stores field officer IDs used as OFFID, SFOID, ZMGRID in CHBLOCKS.

CREATE OR ALTER VIEW [vw_sys_pm_properties_001] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY b.BLOCKID)              AS id,
    NULL                                                AS date_time,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHLRDS via LLORDID to get landlord/client sequential id
    -- NOTE: lord_row_id uses identical ROW_NUMBER logic as vw_sys_pm_clients_landlords_002
    --       (ORDER BY LORDID, no GROUP BY dedup) so FK will align correctly.
    RTRIM(LTRIM(b.BLOCKID))                             AS reference_number,
    RTRIM(LTRIM(b.BLOCKDESC))                           AS description,
    NULL                                                AS lat_long,
    NULL                                                AS photos_and_documents,
    NULL                                                AS zone_id,
    NULL                                                AS zone_area_id,
    RTRIM(LTRIM(b.BLOCKDESC))                           AS property_name,
    RTRIM(LTRIM(b.LRNUM))                               AS lr_number,
    NULL                                                AS usage_type_id,
    CASE WHEN b.BSTATUS = 1 THEN 1 ELSE 2 END          AS current_status_id,
    NULL                                                AS acquisition_date,
    NULL                                                AS created_by,
    NULL                                                AS bank_account_id,
    fo.off_row_id                                       AS field_officer_id,
    -- JOINED: CHOFFICERS via OFFID
    sfo.off_row_id                                      AS zone_supervisor_id,
    -- JOINED: CHOFFICERS via SFOID
    zm.off_row_id                                       AS zone_manager_id,
    -- JOINED: CHOFFICERS via ZMGRID
    NULL                                                AS parent_property_id
FROM CHABRINS.dbo.CHBLOCKS b
LEFT JOIN (
    -- ROW_NUMBER matches vw_sys_pm_clients_landlords_002 exactly:
    -- same ORDER BY LORDID, same WHERE, no GROUP BY deduplication.
    SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
           ROW_NUMBER() OVER (ORDER BY LORDID) AS lord_row_id
    FROM CHABRINS.dbo.CHLRDS
    WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
) lr ON lr.lordid_clean = RTRIM(LTRIM(b.LLORDID))           -- exact match first
     OR lr.lordid_clean = REPLACE(RTRIM(LTRIM(b.LLORDID)), 'LAN-', '')  -- fallback: strip LAN- prefix from LLORDID
LEFT JOIN (
    SELECT OFFID, ROW_NUMBER() OVER (ORDER BY OFFID) AS off_row_id
    FROM CHABRINS.dbo.CHOFFICERS
    WHERE OFFID IS NOT NULL AND RTRIM(LTRIM(OFFID)) <> ''
) fo ON RTRIM(LTRIM(fo.OFFID)) = RTRIM(LTRIM(b.OFFID))
LEFT JOIN (
    SELECT OFFID, ROW_NUMBER() OVER (ORDER BY OFFID) AS off_row_id
    FROM CHABRINS.dbo.CHOFFICERS
    WHERE OFFID IS NOT NULL AND RTRIM(LTRIM(OFFID)) <> ''
) sfo ON RTRIM(LTRIM(sfo.OFFID)) = RTRIM(LTRIM(b.SFOID))
LEFT JOIN (
    SELECT OFFID, ROW_NUMBER() OVER (ORDER BY OFFID) AS off_row_id
    FROM CHABRINS.dbo.CHOFFICERS
    WHERE OFFID IS NOT NULL AND RTRIM(LTRIM(OFFID)) <> ''
) zm ON RTRIM(LTRIM(zm.OFFID)) = RTRIM(LTRIM(b.ZMGRID))
WHERE b.BLOCKID IS NOT NULL;



-- ============================================================================
-- 9. UNITS: CHBUNITS -> sys_pm_units
-- ============================================================================
-- NOTE: CHBUNITS.BLOCKID is FK to CHBLOCKS -> property_id in PG.
-- LOOKUP: property_id from sys_pm_properties WHERE reference_number = CHBUNITS.BLOCKID

CREATE OR ALTER VIEW vw_sys_pm_units AS
SELECT
    ROW_NUMBER() OVER (ORDER BY u.BLOCKID, u.UNITNO)   AS id,
    GETDATE()                                           AS date_time,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHBLOCKS -> CHLRDS chain via BLOCKID -> LLORDID -> LORDID
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKID to get property sequential id
    RTRIM(LTRIM(u.UNITTYP))                             AS unit_type_id,
    NULL                                                AS usage_type_id,
    RTRIM(LTRIM(u.BLOCKID)) + '-' + RTRIM(LTRIM(u.UNITNO))  AS unit_code,
    RTRIM(LTRIM(u.UNITDESC))                            AS description,
    NULL                                                AS created_by_id,
    NULL                                                AS unit_uploads,
    NULL                                                AS zone_id,
    CASE WHEN u.USTATUS = 1 THEN 1     -- Occupied
         WHEN u.USTATUS = 2 THEN 2     -- Vacant
         ELSE 3 END                                     AS occupancy_status_id,
    RTRIM(LTRIM(u.UNITDESC))                            AS unit_name,
    NULL                                                AS unit_condition_id,
    RTRIM(LTRIM(u.RCATEGO))                             AS category_id,
    ISNULL(u.RNTAMT, 0)                                 AS rent_amount,
    CASE WHEN ISNULL(u.VATABL, 0) = 1 THEN 'Yes' ELSE 'No' END  AS vat_able,
    CASE WHEN u.USTATUS = 1 THEN 1 ELSE 2 END          AS current_status_id,
    RTRIM(LTRIM(u.UNITNO))                              AS unit_number,
    0                                                   AS initial_water_meter_reading,
    NULL                                                AS topology_id,
    NULL                                                AS block_owner_tenant_id
FROM CHABRINS.dbo.CHBUNITS u
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id, LLORDID
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(u.BLOCKID))
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(prop.LLORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
WHERE u.UNITNO IS NOT NULL;
GO


-- ============================================================================
-- 10. CHEQUE BOOKS: CHCHQ -> sys_fin_cheque_books
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_fin_cheque_books AS
SELECT
    ROW_NUMBER() OVER (ORDER BY cq.CHQNO)              AS id,
    GETDATE()                                           AS date_time,
    bk.bank_row_id                                      AS bank_account_id,
    -- JOINED: CHBANK via LEFT(CHQACC,2) matching BANKCODE (first 2 chars = bank code)
    RTRIM(LTRIM(cq.CHQNO))                              AS first_cheque_number,
    1                                                   AS leafs,
    NULL                                                AS created_by_id,
    CASE WHEN cq.CHQSTAT = 1 THEN 1 ELSE 2 END         AS current_status_id,
    RTRIM(LTRIM(ISNULL(cq.CHQBKNO, cq.CHQNO)))         AS reference_number
FROM CHABRINS.dbo.CHCHQ cq
LEFT JOIN (
    SELECT BANKCODE, ROW_NUMBER() OVER (ORDER BY BANKCODE) AS bank_row_id
    FROM CHABRINS.dbo.CHBANK
    WHERE BANKNAME IS NOT NULL AND BANKNAME <> ''
) bk ON bk.BANKCODE = LEFT(cq.CHQACC, 2)
WHERE cq.CHQNO IS NOT NULL;
GO


-- ============================================================================
-- 11. GL ACCOUNTS SETUP: CHIACCT -> sys_gl_accounts_setup
-- ============================================================================
-- NOTE: Single configuration row mapping income, cash, and control GL accounts.
-- Each CHIACCT column is a GL account code -> must resolve to PG account IDs.

CREATE OR ALTER VIEW vw_sys_gl_accounts_setup AS
SELECT
    1                                                   AS id,
    GETDATE()                                           AS date_time,
    NULL                                                AS retained_earnings_id,
    NULL                                                AS income_summary_account_id,
    RTRIM(LTRIM(ACCINCO))                               AS receivable_control_id,
    -- NOTE: This is the GL account CODE. After migration, resolve to
    -- (SELECT id FROM sys_gl_chart_accounts_master WHERE account_code = ACCINCO)
    RTRIM(LTRIM(ACCAINC))                               AS payable_control_account_id,
    NULL                                                AS bank_charges_account_id,
    RTRIM(LTRIM(ACCVAT))                                AS vat_control_account_id,
    NULL                                                AS other_income_account_id,
    NULL                                                AS bank_interest_account_id,
    NULL                                                AS accrued_payroll_expense_account_id,
    NULL                                                AS payroll_expense_account_id,
    NULL                                                AS net_salaries_account_id,
    NULL                                                AS other_employee_deductions_id,
    RTRIM(LTRIM(ACCLAD))                                AS clients_account_id,
    RTRIM(LTRIM(ACCLIN))                                AS tenants_account_id,
    RTRIM(LTRIM(ACCPINC))                               AS revenues_account_id,
    NULL                                                AS clients_bank_account_id,
    RTRIM(LTRIM(ACCPCSH))                               AS petty_cash_account_id,
    NULL                                                AS loans_receivable_account_id,
    NULL                                                AS interests_receivable_account_id,
    RTRIM(LTRIM(ACCCSH))                                AS business_bank_account_id,
    NULL                                                AS tenants_suspense_account_id,
    16.00                                               AS revenue_vat,
    NULL                                                AS loans_interests_revenue_account_id,
    NULL                                                AS rent_commission_account_id,
    NULL                                                AS active_fiscal_year_id,
    NULL                                                AS returns_payable_account_id,
    NULL                                                AS clients_advance_loans_payable_id,
    NULL                                                AS co_op_bank_id,
    NULL                                                AS equity_bank_account_id,
    NULL                                                AS mpesa_account_id,
    NULL                                                AS earned_commission_acc_id,
    16.00                                               AS clients_vat,
    NULL                                                AS deposits_payable_account_id,
    NULL                                                AS miscallenous_revenue_id,
    NULL                                                AS payment_voucher_control_account_id
FROM CHABRINS.dbo.CHIACCT;
GO

/*
  NOTE: All account columns above store GL CODE strings from CHABRINS.
  After loading sys_gl_chart_accounts_master, run an UPDATE to resolve each
  code to its integer PG id. Example:
    UPDATE sys_gl_accounts_setup
    SET receivable_control_id = (
      SELECT id::text FROM chips_db_v3.sys_gl_chart_accounts_master
      WHERE account_code = receivable_control_id LIMIT 1
    );
*/


-- ============================================================================
-- 12. LEASE AGREEMENTS: CHCNAM -> sys_pm_lease_agreements
-- ============================================================================
-- NOTE: CHCNAM links tenant names to blocks/units.
-- LOOKUP: tenant_id from CHTENS, property_id from CHBLOCKS, unit_id from CHBUNITS
--
-- EXTRA TABLE NEEDED: CHTENS (Tenants) — NOT IN CSV MAPPING
--   CHTENS has TENNAME, BLOCKNO, UNITNO, entry/exit dates, lease info.
--   Must be loaded first so tenant_id can be resolved.

CREATE OR ALTER VIEW vw_sys_pm_lease_agreements AS
SELECT
    ROW_NUMBER() OVER (ORDER BY cn.TENNAME, cn.BLOCKID, cn.UNITNO)  AS id,
    GETDATE()                                           AS date_time,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHBLOCKS -> CHLRDS chain via BLOCKID -> LLORDID
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKID to get property sequential id
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKID + UNITNO to get unit sequential id
    ten.ten_row_id                                      AS tenant_id,
    -- JOINED: CHTENS via TENNAME + BLOCKNO + UNITNO to get tenant sequential id
    t.ENDAT                                             AS date_start,
    t.EXDAT                                             AS date_end,
    CASE WHEN t.TSTATUS = 'IN'  THEN 'Active'
         WHEN t.TSTATUS = 'OUT' THEN 'Terminated'
         ELSE 'Active' END                              AS lease_status,
    cn.BLOCKID + '-' + cn.UNITNO + '-' + LEFT(cn.TENNAME, 20)  AS ref_number,
    NULL                                                AS created_by_id,
    NULL                                                AS sla_id,
    NULL                                                AS zone_id,
    CASE WHEN t.TSTATUS = 'IN'  THEN 1
         WHEN t.TSTATUS = 'OUT' THEN 2
         ELSE 1 END                                     AS current_status_id,
    'No'                                                AS auto_renewal,
    NULL                                                AS years,
    0                                                   AS initial_water_meter_reading,
    0                                                   AS escalation_rate,
    NULL                                                AS escalation_frequency,
    NULL                                                AS documents,
    NULL                                                AS linked_lease_id,
    'No'                                                AS override_rent_calc
FROM CHABRINS.dbo.CHCNAM cn
LEFT JOIN CHABRINS.dbo.CHTENS t
    ON t.TENNAME = cn.TENNAME AND t.BLOCKNO = cn.BLOCKID AND t.UNITNO = cn.UNITNO
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id, LLORDID
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(cn.BLOCKID))
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(prop.LLORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(cn.BLOCKID))
     AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(cn.UNITNO))
LEFT JOIN (
    SELECT TENNAME, BLOCKNO, UNITNO,
           ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO) AS ten_row_id
    FROM CHABRINS.dbo.CHTENS
    WHERE TENNAME IS NOT NULL
) ten ON ten.TENNAME = cn.TENNAME AND ten.BLOCKNO = cn.BLOCKID AND ten.UNITNO = cn.UNITNO
WHERE cn.TENNAME IS NOT NULL AND cn.BLOCKID IS NOT NULL;
GO

/*
  NOTE: CHCNAM has only TENNAME, BLOCKID, UNITNO, OTENNAME.
  The lease dates (date_start, date_end) and status are pulled from CHTENS via JOIN.
  client_id resolved via CHBLOCKS.LLORDID -> CHLRDS.LORDID chain.
  property_id, unit_id, tenant_id resolved via direct JOINs.
*/


-- ============================================================================
-- 13. DEPOSITS: CHDPAY -> sys_pm_deposits
-- ============================================================================
-- LOOKUP: tenant_id, property_id, unit_id from CHTENS/CHBLOCKS/CHBUNITS

CREATE OR ALTER VIEW vw_sys_pm_deposits AS
SELECT
    ROW_NUMBER() OVER (ORDER BY d.BLOCKID, d.UNITNO, d.TENNAME)  AS id,
    GETDATE()                                           AS date_time,
    GETDATE()                                           AS deposit_date,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHBLOCKS -> CHLRDS chain via BLOCKID -> LLORDID
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKID
    NULL                                                AS sla_id,
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKID + UNITNO
    NULL                                                AS lease_id,
    ten.ten_row_id                                      AS tenant_id,
    -- JOINED: CHTENS via TENNAME + BLOCKID + UNITNO
    NULL                                                AS invoice_id,
    NULL                                                AS item_id,
    ISNULL(d.TOTPAY, 0)                                 AS amount,
    NULL                                                AS created_by_id,
    1                                                   AS current_status_id,
    d.BLOCKID + '-' + d.UNITNO + '-DEP'                 AS reference_number,
    NULL                                                AS zone_id,
    0                                                   AS invoice_balance,
    RTRIM(LTRIM(d.TRANSDESC))                           AS narration
FROM CHABRINS.dbo.CHDPAY d
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id, LLORDID
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(d.BLOCKID))
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(prop.LLORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(d.BLOCKID))
     AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(d.UNITNO))
LEFT JOIN (
    SELECT TENNAME, BLOCKNO, UNITNO,
           ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO) AS ten_row_id
    FROM CHABRINS.dbo.CHTENS
    WHERE TENNAME IS NOT NULL
) ten ON ten.TENNAME = d.TENNAME AND RTRIM(LTRIM(ten.BLOCKNO)) = RTRIM(LTRIM(d.BLOCKID))
     AND RTRIM(LTRIM(ten.UNITNO)) = RTRIM(LTRIM(d.UNITNO))
WHERE d.TENNAME IS NOT NULL;
GO


-- ============================================================================
-- 14. DEPOSIT REQUESTS: CHDPS -> sys_pm_deposits_requests
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_pm_deposits_requests AS
SELECT
    ROW_NUMBER() OVER (ORDER BY d.TENNAME, d.BLOCKID, d.UNITNO)  AS id,
    GETDATE()                                           AS date_time,
    d.PAYDATE                                           AS request_date,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHBLOCKS -> CHLRDS chain via BLOCKID -> LLORDID
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKID
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKID + UNITNO
    NULL                                                AS lease_id,
    ten.ten_row_id                                      AS tenant_id,
    -- JOINED: CHTENS via TENNAME + BLOCKID + UNITNO
    ISNULL(d.PAYAMT, 0)                                 AS current_total_balance,
    NULL                                                AS created_by,
    NULL                                                AS vacating_date,
    CASE WHEN d.TSTATUS = 'Paid' THEN 1 ELSE 2 END     AS current_status_id,
    ISNULL(CAST(d.RCNO AS VARCHAR(20)), '') + '-' + d.BLOCKID  AS reference_number,
    0                                                   AS amount_approved,
    NULL                                                AS sla_id,
    ISNULL(d.PAYAMT, 0)                                 AS total_deposits_amount,
    ISNULL(d.PAYAMT, 0)                                 AS net_balance
FROM CHABRINS.dbo.CHDPS d
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id, LLORDID
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(d.BLOCKID))
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(prop.LLORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(d.BLOCKID))
     AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(d.UNITNO))
LEFT JOIN (
    SELECT TENNAME, BLOCKNO, UNITNO,
           ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO) AS ten_row_id
    FROM CHABRINS.dbo.CHTENS
    WHERE TENNAME IS NOT NULL
) ten ON ten.TENNAME = d.TENNAME AND RTRIM(LTRIM(ten.BLOCKNO)) = RTRIM(LTRIM(d.BLOCKID))
     AND RTRIM(LTRIM(ten.UNITNO)) = RTRIM(LTRIM(d.UNITNO))
WHERE d.TENNAME IS NOT NULL;
GO


-- ============================================================================
-- 15. PAYMENT VOUCHER HEADERS: CHBCTH -> sys_fin_payment_vouchers
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_fin_payment_vouchers AS
SELECT
    ROW_NUMBER() OVER (ORDER BY h.BTCHID)              AS id,
    GETDATE()                                           AS date_time,
    CAST(h.BTCHDAT AS DATETIME)                         AS trans_date_time,
    NULL                                                AS recepient_id,
    stf.staff_row_id                                    AS entered_by,
    -- JOINED: CHSTFF via EMPNUMB matching BTCRBY
    CAST(h.BTCHID AS VARCHAR(20))                       AS origin_id,
    'PV-' + CAST(h.BTCHID AS VARCHAR(20))               AS reference_number,
    NULL                                                AS transaction_type_id,
    RTRIM(LTRIM(h.BTCHDES))                             AS narration,
    ISNULL(h.BTCHAMT, 0)                                AS amount,
    CASE WHEN h.BPSTAT = 1 THEN 1 ELSE 2 END           AS current_status_id,
    NULL                                                AS voucher_type_id,
    NULL                                                AS from_bank_id,
    RTRIM(LTRIM(h.BTCHSRC))                             AS source_ref,
    NULL                                                AS debit_account_id,
    NULL                                                AS client_bank_account_id,
    NULL                                                AS documents,
    NULL                                                AS client_id,
    NULL                                                AS requisition_id,
    NULL                                                AS transaction_category_id,
    NULL                                                AS tenant_id,
    NULL                                                AS property_id,
    NULL                                                AS provider_id,
    NULL                                                AS staff_id,
    NULL                                                AS cash_custodian_id,
    0                                                   AS total_charge,
    'No'                                                AS is_recovery,
    ISNULL(h.BTCHAMT, 0)                                AS payable_amount,
    NULL                                                AS payee_names,
    NULL                                                AS cheque_id,
    CAST(h.BTCHID AS INT)                               AS batch_id,
    NULL                                                AS cheque_leaf_id
FROM CHABRINS.dbo.CHBCTH h
LEFT JOIN (
    SELECT EMPNUMB, ROW_NUMBER() OVER (ORDER BY EMPNUMB) AS staff_row_id
    FROM CHABRINS.dbo.CHSTFF
    WHERE EMPNUMB IS NOT NULL AND RTRIM(LTRIM(EMPNUMB)) <> ''
) stf ON RTRIM(LTRIM(stf.EMPNUMB)) = RTRIM(LTRIM(h.BTCRBY))
WHERE h.BTCHID IS NOT NULL;
GO


-- ============================================================================
-- 16. PAYMENT VOUCHER DETAILS: CHBCTD -> sys_fin_payment_vouchers_264_repeat
-- ============================================================================
-- NOTE: CHCHD also maps here. CHBCTD has batch transaction details;
--       CHCHD has cheque payment details. Both are line items on vouchers.
-- LOOKUP: parent_id from sys_fin_payment_vouchers WHERE batch_id = CHBCTD.BTCHID

CREATE OR ALTER VIEW vw_sys_fin_payment_vouchers_264_repeat_FROM_CHBCTD AS
SELECT
    ROW_NUMBER() OVER (ORDER BY d.BTCHID, d.ENTRYID)  AS id,
    pv.pv_row_id                                       AS parent_id,
    -- JOINED: CHBCTH via BTCHID to get payment voucher sequential id
    NULL                                                AS transaction_type_id,
    ISNULL(d.RCAMT, 0)                                  AS amount,
    RTRIM(LTRIM(d.ACCNO))                               AS debit_account_id,
    ISNULL(d.BASEAMT, 0)                                AS miscallenous_charge,
    'No'                                                AS is_recovery,
    NULL                                                AS cash_custodian_id,
    NULL                                                AS client_id,
    NULL                                                AS client_account_id,
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKID
    RTRIM(LTRIM(d.TRANSDESC))                           AS narration,
    'No'                                                AS charge_client,
    NULL                                                AS cheque_number_id
FROM CHABRINS.dbo.CHBCTD d
LEFT JOIN (
    SELECT BTCHID, ROW_NUMBER() OVER (ORDER BY BTCHID) AS pv_row_id
    FROM CHABRINS.dbo.CHBCTH
    WHERE BTCHID IS NOT NULL
) pv ON pv.BTCHID = d.BTCHID
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(d.BLOCKID))
WHERE d.BTCHID IS NOT NULL;
GO


-- ============================================================================
-- 17. CHEQUE PAYMENT DETAILS: CHCHD -> sys_fin_payment_vouchers_264_repeat
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_fin_payment_vouchers_264_repeat_FROM_CHCHD AS
SELECT
    ROW_NUMBER() OVER (ORDER BY h.CHQNO, h.ENTRYID)   AS id,
    pv.pv_row_id                                       AS parent_id,
    -- JOINED: CHBCTH via PAYVOUC matching BTCHID
    NULL                                                AS transaction_type_id,
    ISNULL(h.ENTRYAMT, 0)                               AS amount,
    RTRIM(LTRIM(h.PACCNUM))                             AS debit_account_id,
    0                                                   AS miscallenous_charge,
    'No'                                                AS is_recovery,
    NULL                                                AS cash_custodian_id,
    NULL                                                AS client_id,
    NULL                                                AS client_account_id,
    NULL                                                AS property_id,
    RTRIM(LTRIM(h.ENTDESC))                             AS narration,
    'No'                                                AS charge_client,
    cq.chq_row_id                                       AS cheque_number_id
    -- JOINED: CHCHQ via CHQNO + CHQACC
FROM CHABRINS.dbo.CHCHD h
LEFT JOIN (
    SELECT BTCHID, ROW_NUMBER() OVER (ORDER BY BTCHID) AS pv_row_id
    FROM CHABRINS.dbo.CHBCTH
    WHERE BTCHID IS NOT NULL
) pv ON CAST(pv.BTCHID AS VARCHAR(20)) = RTRIM(LTRIM(h.PAYVOUC))
LEFT JOIN (
    SELECT CHQNO, CHQACC, ROW_NUMBER() OVER (ORDER BY CHQNO) AS chq_row_id
    FROM CHABRINS.dbo.CHCHQ
    WHERE CHQNO IS NOT NULL
) cq ON RTRIM(LTRIM(cq.CHQNO)) = RTRIM(LTRIM(h.CHQNO))
     AND RTRIM(LTRIM(cq.CHQACC)) = RTRIM(LTRIM(h.CHQACC))
WHERE h.CHQNO IS NOT NULL;
GO


-- ============================================================================
-- 18. TENANT PAYMENTS: CHIPAYS -> sys_pm_tenants_payments
-- ============================================================================
-- NOTE: CHIPAYS has summary payment records.
--
-- EXTRA TABLE NEEDED: CHTPAYS — NOT IN CSV MAPPING (1,435,222 rows)
--   CHTPAYS has detailed tenant payment records with RCNO, amounts, batch info.
--   CHTPAYS is the PRIMARY payment source; CHIPAYS appears to be a summary.
--   Consider migrating CHTPAYS instead of (or in addition to) CHIPAYS.

CREATE OR ALTER VIEW vw_sys_pm_tenants_payments AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.CHECKNO)             AS id,
    GETDATE()                                           AS date_time,
    p.RCDATE                                            AS payment_date,
    CAST(p.RCNO AS VARCHAR(50))                         AS reference,
    RTRIM(LTRIM(p.RCMARK))                              AS narration,
    NULL                                                AS created_by,
    NULL                                                AS zone_id,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHBLOCKS -> CHLRDS chain via BLOCKNO -> LLORDID
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKNO
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKNO + UNITNO
    NULL                                                AS lease_id,
    ten.ten_row_id                                      AS tenant_id,
    -- JOINED: CHTENS via TENNAME + BLOCKNO + UNITNO
    1                                                   AS current_status_id,
    ISNULL(p.RCAMT, 0)                                  AS amount,
    NULL                                                AS invoice_id,
    RTRIM(LTRIM(p.RACTION))                             AS trans_details,
    NULL                                                AS sla_id,
    NULL                                                AS channel_id,
    CAST(p.CHECKNO AS VARCHAR(50))                      AS receipt_number,
    NULL                                                AS recovery_type_id,
    NULL                                                AS field_officer_id,
    NULL                                                AS zone_supervisor_id,
    NULL                                                AS zone_manager_id,
    NULL                                                AS extract_id,
    NULL                                                AS tenant_aid
FROM CHABRINS.dbo.CHIPAYS p
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id, LLORDID
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(p.BLOCKNO))
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(prop.LLORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(p.BLOCKNO))
     AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(p.UNITNO))
LEFT JOIN (
    SELECT TENNAME, BLOCKNO, UNITNO,
           ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO) AS ten_row_id
    FROM CHABRINS.dbo.CHTENS
    WHERE TENNAME IS NOT NULL
) ten ON ten.TENNAME = p.TENNAME AND RTRIM(LTRIM(ten.BLOCKNO)) = RTRIM(LTRIM(p.BLOCKNO))
     AND RTRIM(LTRIM(ten.UNITNO)) = RTRIM(LTRIM(p.UNITNO))
WHERE p.CHECKNO IS NOT NULL;
GO

/*
  EXTRA TABLE: CHTPAYS (Tenant Payments Detail)
    - 1,435,222 rows — the main payment transaction table
    - Key columns: TENNAME, BLOCKID, UNITNO, RCNO, RCAMT, PAYDATE, PYMOD,
                   SECURCD (security code/reference), ENTRYREF, TRANTYP
    - CHTPAYS.RCNO is the receipt number
    - CHTPAYS.SECURCD is the MPESA/bank reference code
    - CHTPAYS.TRANTYP = payment type (IBAPS, PAYBILL, etc.)
    - This table should be the PRIMARY migration source for tenant payments.
    - A separate view for CHTPAYS is recommended:

    CREATE VIEW vw_sys_pm_tenants_payments_FROM_CHTPAYS AS
    SELECT
        ROW_NUMBER() OVER (ORDER BY BATCHID, ENTRYID) AS id,
        GETDATE()           AS date_time,
        PAYDATE             AS payment_date,
        SECURCD             AS reference,
        TRANSDESC           AS narration,
        ...
    FROM CHABRINS.dbo.CHTPAYS;
*/


-- ============================================================================
-- 19. CLIENT RETURNS / RECEIPTS: CHCCRST -> sys_pm_clients_returns
-- ============================================================================
-- NOTE: CHCCRST is a small status table. The main returns data is in CHRHMS.
--
-- EXTRA TABLE NEEDED: CHRHMS (Returns Master) — NOT IN CSV MAPPING
--   CHRHMS has full return records with RETURNID, LORDID, BLOCKID, amounts,
--   commission rates, approval workflow, and payment status.
--   Consider creating a separate view for CHRHMS -> sys_pm_clients_returns.

CREATE OR ALTER VIEW vw_sys_pm_clients_returns AS
SELECT
    ROW_NUMBER() OVER (ORDER BY r.RCNO)                AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(r.RCNO))                                AS ref_number,
    NULL                                                AS invoice_id,
    r.ENTDATE                                           AS return_date,
    RTRIM(LTRIM(r.RRMKS))                               AS narration,
    NULL                                                AS created_by,
    NULL                                                AS zone_id,
    NULL                                                AS client_id,
    NULL                                                AS property_id,
    NULL                                                AS unit_id,
    NULL                                                AS lease_id,
    NULL                                                AS tenant_id,
    NULL                                                AS trans_type_id,
    NULL                                                AS commisionable,
    NULL                                                AS batch_id,
    NULL                                                AS unit_status_id,
    NULL                                                AS threshold_percentage,
    0                                                   AS abstract_threshold,
    NULL                                                AS anomally_type_id,
    NULL                                                AS anomally_orig_id,
    CASE WHEN r.PSUCC IS NOT NULL AND r.PSUCC <> '' THEN 1 ELSE 2 END  AS current_status_id,
    0                                                   AS trans_amount,
    0                                                   AS payable_amount,
    NULL                                                AS sla_id,
    0                                                   AS comm_amount,
    0                                                   AS commissionable_amount,
    0                                                   AS vat_amount,
    NULL                                                AS comm_perc,
    NULL                                                AS vat_per,
    NULL                                                AS item_id,
    NULL                                                AS payable_status_id,
    NULL                                                AS approval_status,
    NULL                                                AS charge_id,
    NULL                                                AS paid_status_id,
    NULL                                                AS tenant_payment_id,
    NULL                                                AS tenant_payment_date,
    0                                                   AS tenant_payment_amount,
    0                                                   AS allocated_total,
    0                                                   AS invoice_balance,
    NULL                                                AS fo_id,
    NULL                                                AS is_bill,
    0                                                   AS paid_out,
    NULL                                                AS zone_supervisor_id,
    NULL                                                AS zone_manager_id,
    0                                                   AS comm_vat,
    NULL                                                AS distributions_allocation_id,
    NULL                                                AS return_type_id,
    NULL                                                AS date_paid_out,
    NULL                                                AS invoice_date
FROM CHABRINS.dbo.CHCCRST r
WHERE r.RCNO IS NOT NULL;
GO

/*
  EXTRA TABLE: CHRHMS (Returns Master)
    - Key columns: RETURNID, LORDID, BLOCKID, YEARR, MONTHH, RETDESC,
                   COMMRATE, TOTALCR, SUMCOM, NETAMT, NETBAL
    - Approval workflow: PREPBY/PREPST, CHKBY/CHKST, APPBY/APPST, FINABY/FINAST
    - Payment: PAIDST, VOUCHERNO, CHQNO, BANKACC, AMTPAYD
    - CHRHMS maps most accurately to sys_pm_clients_returns_master or
      sys_pm_clients_returns_batches in PostgreSQL.
    - Needs its own dedicated view for full returns migration.
*/


-- ============================================================================
-- 20. GL JOURNAL VOUCHER HEADERS: CHJVH -> sys_gl_transactions
-- ============================================================================
-- NOTE: CHJVHP (posted journals) also maps here.
--       Strategy: UNION CHJVH (unposted) with CHJVHP (posted).

CREATE OR ALTER VIEW vw_sys_gl_transactions AS
SELECT
    ROW_NUMBER() OVER (ORDER BY src, BATCHID, JVNUM)   AS id,
    GETDATE()                                           AS date_time,
    JVDATE                                              AS journal_date,
    JVDESC                                              AS reference,
    NULL                                                AS currency_id,
    CASE WHEN JVYEAR > 0 AND JVMNTH > 0
         THEN JVYEAR * 100 + JVMNTH
         ELSE NULL END                                  AS accounting_period_id,
    RTRIM(LTRIM(JVDESC))                                AS trans_details,
    NULL                                                AS branch_id,
    JVNUM                                               AS journal_id,
    CASE WHEN STPOST = 1 THEN 1 ELSE 2 END             AS current_status_id,
    NULL                                                AS trans_type_id,
    stf.staff_row_id                                    AS entered_by
    -- JOINED: CHSTFF via EMPNUMB matching CREATEDBY
FROM (
    SELECT *, 'H' AS src FROM CHABRINS.dbo.CHJVH
    UNION ALL
    SELECT *, 'P' AS src FROM CHABRINS.dbo.CHJVHP
) combined
LEFT JOIN (
    SELECT EMPNUMB, ROW_NUMBER() OVER (ORDER BY EMPNUMB) AS staff_row_id
    FROM CHABRINS.dbo.CHSTFF
    WHERE EMPNUMB IS NOT NULL AND RTRIM(LTRIM(EMPNUMB)) <> ''
) stf ON RTRIM(LTRIM(stf.EMPNUMB)) = RTRIM(LTRIM(combined.CREATEDBY))
WHERE BATCHID IS NOT NULL;
GO

/*
  NOTE: CHJVH = unposted journal headers; CHJVHP = posted journal headers.
  Both have identical structure. We UNION them and use the source flag to distinguish.
  If duplicates exist (same BATCHID+JVNUM in both), prefer CHJVHP (posted).
  Alternatively, deduplicate:
    SELECT ... FROM CHJVHP UNION ALL
    SELECT ... FROM CHJVH h WHERE NOT EXISTS (SELECT 1 FROM CHJVHP p WHERE p.BATCHID=h.BATCHID AND p.JVNUM=h.JVNUM)
*/


-- ============================================================================
-- 21. GL JOURNAL VOUCHER DETAILS: CHJVD -> sys_gl_transactions_91_repeat
-- ============================================================================
-- NOTE: These are journal line items.
-- LOOKUP: parent_id from sys_gl_transactions WHERE journal_id = CHJVD.JVNUM

CREATE OR ALTER VIEW vw_sys_gl_transactions_91_repeat AS
SELECT
    ROW_NUMBER() OVER (ORDER BY d.BATCHID, d.JVNUM, d.ENTRYNO)  AS id,
    jv.jv_row_id                                        AS parent_id,
    -- JOINED: CHJVH+CHJVHP union via BATCHID+JVNUM to get journal header sequential id
    acct.acct_row_id                                    AS account_id,
    -- JOINED: CHFACTV via ACCNUM to get chart of accounts sequential id
    CASE WHEN ISNULL(d.AMTDR, 0) > 0 THEN 'Debit'
         WHEN ISNULL(d.AMTCR, 0) > 0 THEN 'Credit'
         ELSE 'Debit' END                               AS entry_type,
    RTRIM(LTRIM(d.ENTDESC))                             AS narration,
    CASE WHEN ISNULL(d.AMTDR, 0) > 0 THEN d.AMTDR
         ELSE d.AMTCR END                               AS amount
FROM CHABRINS.dbo.CHJVD d
LEFT JOIN (
    SELECT BATCHID, JVNUM, ROW_NUMBER() OVER (ORDER BY src, BATCHID, JVNUM) AS jv_row_id
    FROM (
        SELECT BATCHID, JVNUM, 'H' AS src FROM CHABRINS.dbo.CHJVH
        UNION ALL
        SELECT BATCHID, JVNUM, 'P' AS src FROM CHABRINS.dbo.CHJVHP
    ) jv_all
) jv ON jv.BATCHID = d.BATCHID AND jv.JVNUM = d.JVNUM
LEFT JOIN (
    SELECT ACCNUM, ROW_NUMBER() OVER (ORDER BY ACCNUM) AS acct_row_id
    FROM CHABRINS.dbo.CHFACTV
    WHERE ACCNUM IS NOT NULL AND ACCNUM <> ''
) acct ON RTRIM(LTRIM(acct.ACCNUM)) = RTRIM(LTRIM(d.ACCNUM))
WHERE d.BATCHID IS NOT NULL AND d.STDEL = 0;
GO


-- ============================================================================
-- 22. GL ACCOUNTS ENTRIES: CHACCE -> sys_gl_accounts_entries
-- ============================================================================
-- NOTE: Also fed by CHCBMN and CHJVDP. Each represents a different source:
--   CHACCE  = General accounting entries
--   CHCBMN  = Monthly cashbook summaries
--   CHJVDP  = Posted journal voucher detail entries
-- Strategy: Create separate views, load sequentially.

CREATE OR ALTER VIEW vw_sys_gl_accounts_entries_FROM_CHACCE AS
SELECT
    ROW_NUMBER() OVER (ORDER BY a.PYEARR, a.PMONTH, a.ACCNUM, a.ENTRYID)  AS id,
    GETDATE()                                           AS date_time,
    a.ENTDATE                                           AS journal_date,
    RTRIM(LTRIM(a.ENTREF))                              AS reference,
    RTRIM(LTRIM(a.SRCCODE))                             AS type,
    CASE WHEN a.PYEARR > 0 AND a.PMONTH > 0
         THEN a.PYEARR * 100 + a.PMONTH
         ELSE NULL END                                  AS accounting_period_id,
    RTRIM(LTRIM(a.ENTRET))                              AS source_ref,
    NULL                                                AS branch_id,
    RTRIM(LTRIM(a.ACCNUM))                              AS ref_number,
    a.JVNUM                                             AS journal,
    'Yes'                                               AS posted,
    NULL                                                AS trans_type_id,
    NULL                                                AS entered_by_id
FROM CHABRINS.dbo.CHACCE a
WHERE a.ACCNUM IS NOT NULL;
GO


CREATE OR ALTER VIEW vw_sys_gl_accounts_entries_FROM_CHCBMN AS
SELECT
    ROW_NUMBER() OVER (ORDER BY m.YEARR, m.MONTHH, m.BANKACC, m.ENTRYNO)  AS id,
    GETDATE()                                           AS date_time,
    m.ENTDAT                                            AS journal_date,
    RTRIM(LTRIM(m.ENTREF))                              AS reference,
    RTRIM(LTRIM(m.ENTTYPE))                             AS type,
    CASE WHEN m.YEARR > 0 AND m.MONTHH > 0
         THEN m.YEARR * 100 + m.MONTHH
         ELSE NULL END                                  AS accounting_period_id,
    RTRIM(LTRIM(m.ENTREFT))                             AS source_ref,
    NULL                                                AS branch_id,
    RTRIM(LTRIM(m.BANKACC))                             AS ref_number,
    NULL                                                AS journal,
    CASE WHEN m.ENTPST = 1 THEN 'Yes' ELSE 'No' END    AS posted,
    NULL                                                AS trans_type_id,
    NULL                                                AS entered_by_id
FROM CHABRINS.dbo.CHCBMN m
WHERE m.BANKACC IS NOT NULL;
GO


CREATE OR ALTER VIEW vw_sys_gl_accounts_entries_FROM_CHJVDP AS
SELECT
    ROW_NUMBER() OVER (ORDER BY p.BATCHID, p.JVNUM, p.ENTRYNO)  AS id,
    GETDATE()                                           AS date_time,
    p.ENTDATE                                           AS journal_date,
    RTRIM(LTRIM(p.ENTDESC))                             AS reference,
    RTRIM(LTRIM(p.SRCCODE))                             AS type,
    NULL                                                AS accounting_period_id,
    'JV-' + CAST(p.BATCHID AS VARCHAR) + '-' + CAST(p.JVNUM AS VARCHAR)  AS source_ref,
    NULL                                                AS branch_id,
    RTRIM(LTRIM(p.ACCNUM))                              AS ref_number,
    p.JVNUM                                             AS journal,
    'Yes'                                               AS posted,
    NULL                                                AS trans_type_id,
    NULL                                                AS entered_by_id
FROM CHABRINS.dbo.CHJVDP p
WHERE p.BATCHID IS NOT NULL AND p.STDEL = 0;
GO


-- ============================================================================
-- 23. GL LEDGER TRANSACTIONS: CHCBDE -> sys_gl_ledgers_transactions
-- ============================================================================
-- NOTE: CHGLAC also maps here. CHCBDE = cashbook entries; CHGLAC = GL postings.

CREATE OR ALTER VIEW vw_sys_gl_ledgers_transactions_FROM_CHCBDE AS
SELECT
    ROW_NUMBER() OVER (ORDER BY cb.YEARR, cb.MONTHH, cb.BANKACC, cb.ENTRYNO)  AS id,
    GETDATE()                                           AS date_time,
    CAST(cb.ENTDAT AS DATETIME)                         AS trans_date_time,
    NULL                                                AS client_id,
    NULL                                                AS property_id,
    NULL                                                AS sla_id,
    NULL                                                AS unit_id,
    NULL                                                AS lease_id,
    NULL                                                AS tenant_id,
    NULL                                                AS supplier_id,
    bk.bank_row_id                                      AS bank_id,
    -- JOINED: CHBANK via LEFT(BANKACC,2) matching BANKCODE
    RTRIM(LTRIM(cb.CBODOCNO))                           AS origin_id,
    NULL                                                AS transaction_type_id,
    CASE WHEN cb.ENTTYPE = 'CSH' THEN 'Cash'
         WHEN cb.ENTTYPE = 'BNK' THEN 'Bank'
         ELSE 'General' END                             AS ledger_type_id,
    NULL                                                AS control_account_id,
    RTRIM(LTRIM(cb.ENTDESC))                            AS narration,
    CASE WHEN ISNULL(cb.AMTCSHDR, 0) + ISNULL(cb.AMTBNKDR, 0) > 0 THEN 'Debit'
         ELSE 'Credit' END                              AS amount_type,
    CASE WHEN ISNULL(cb.AMTCSHDR, 0) + ISNULL(cb.AMTBNKDR, 0) > 0
         THEN ISNULL(cb.AMTCSHDR, 0) + ISNULL(cb.AMTBNKDR, 0)
         ELSE ISNULL(cb.AMTCSHCR, 0) + ISNULL(cb.AMTBNKCR, 0) END  AS amount
FROM CHABRINS.dbo.CHCBDE cb
LEFT JOIN (
    SELECT BANKCODE, ROW_NUMBER() OVER (ORDER BY BANKCODE) AS bank_row_id
    FROM CHABRINS.dbo.CHBANK
    WHERE BANKNAME IS NOT NULL AND BANKNAME <> ''
) bk ON bk.BANKCODE = LEFT(cb.BANKACC, 2)
WHERE cb.BANKACC IS NOT NULL;
GO


CREATE OR ALTER VIEW vw_sys_gl_ledgers_transactions_FROM_CHGLAC AS
SELECT
    ROW_NUMBER() OVER (ORDER BY g.PYEARR, g.PMONTH, g.ACCNUM, g.BATCHID, g.ENTRYID)  AS id,
    GETDATE()                                           AS date_time,
    g.ENTDATE                                           AS trans_date_time,
    NULL                                                AS client_id,
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKID
    NULL                                                AS sla_id,
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKID + UNITNO
    NULL                                                AS lease_id,
    ten.ten_row_id                                      AS tenant_id,
    -- JOINED: CHTENS via TENNAME + BLOCKID + UNITNO
    NULL                                                AS supplier_id,
    NULL                                                AS bank_id,
    RTRIM(LTRIM(g.BANKACC))                             AS origin_id,
    NULL                                                AS transaction_type_id,
    'General'                                           AS ledger_type_id,
    acct.acct_row_id                                    AS control_account_id,
    -- JOINED: CHFACTV via ACCNUM to get chart of accounts sequential id
    RTRIM(LTRIM(g.ENTDESC))                             AS narration,
    CASE WHEN ISNULL(g.AMTDR, 0) > 0 THEN 'Debit'
         ELSE 'Credit' END                              AS amount_type,
    CASE WHEN ISNULL(g.AMTDR, 0) > 0 THEN g.AMTDR
         ELSE g.AMTCR END                               AS amount
FROM CHABRINS.dbo.CHGLAC g
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(g.BLOCKID))
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(g.BLOCKID))
     AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(g.UNITNO))
LEFT JOIN (
    SELECT TENNAME, BLOCKNO, UNITNO,
           ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO) AS ten_row_id
    FROM CHABRINS.dbo.CHTENS
    WHERE TENNAME IS NOT NULL
) ten ON ten.TENNAME = g.TENNAME AND RTRIM(LTRIM(ten.BLOCKNO)) = RTRIM(LTRIM(g.BLOCKID))
     AND RTRIM(LTRIM(ten.UNITNO)) = RTRIM(LTRIM(g.UNITNO))
LEFT JOIN (
    SELECT ACCNUM, ROW_NUMBER() OVER (ORDER BY ACCNUM) AS acct_row_id
    FROM CHABRINS.dbo.CHFACTV
    WHERE ACCNUM IS NOT NULL AND ACCNUM <> ''
) acct ON RTRIM(LTRIM(acct.ACCNUM)) = RTRIM(LTRIM(g.ACCNUM))
WHERE g.ACCNUM IS NOT NULL;
GO


-- ============================================================================
-- 24. LANDLORD ACCOUNT TRANSACTIONS: CHLACC -> sys_pm_landlord_accounts_transaction
-- ============================================================================
-- NOTE: Also fed by CHIBALS and CHLCAC.
--   CHLACC  = Full landlord transaction ledger (primary)
--   CHIBALS = Tenant balance records
--   CHLCAC  = Closing balance summaries
-- Strategy: Use CHLACC as primary. CHIBALS and CHLCAC are supplementary.
--
-- EXTRA TABLE NEEDED: CHLRDS (Landlords) — NOT IN CSV MAPPING
--   client_id must be resolved from CHLRDS.LORDID

CREATE OR ALTER VIEW vw_sys_pm_landlord_accounts_transaction_FROM_CHLACC AS
SELECT
    ROW_NUMBER() OVER (ORDER BY l.YEARR, l.MONTHH, l.LORDID, l.ENTRYNU)  AS id,
    GETDATE()                                           AS date_time,
    l.ENTRYDA                                           AS trans_date_time,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHLRDS via LORDID to get landlord/client sequential id
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via HOUSEID to get property sequential id
    NULL                                                AS unit_id,
    NULL                                                AS lease_id,
    NULL                                                AS tenant_id,
    RTRIM(LTRIM(l.ENTRYID))                             AS origin_id,
    NULL                                                AS transaction_type_id,
    RTRIM(LTRIM(l.ENTRYDE))                             AS narration,
    CASE WHEN ISNULL(l.AMTDR, 0) > 0 THEN 'Debit'
         ELSE 'Credit' END                              AS amount_type,
    CASE WHEN ISNULL(l.AMTDR, 0) > 0 THEN l.AMTDR
         ELSE l.AMTCR END                               AS amount,
    NULL                                                AS sla_id,
    CAST(ISNULL(l.AMTTOT, 0) AS VARCHAR(50))            AS balance,
    CASE WHEN l.ACCSTAT = 1 THEN 1 ELSE 2 END          AS current_status_id,
    NULL                                                AS invoice_id,
    CASE WHEN ISNULL(l.AMTDR, 0) > 0 THEN l.AMTDR
         ELSE l.AMTCR END                               AS amount_internal,
    NULL                                                AS payable_status,
    l.YEARR * 100 + l.MONTHH                            AS period_id,
    NULL                                                AS period_status_id,
    NULL                                                AS zone_id
FROM CHABRINS.dbo.CHLACC l
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(l.LORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
-- NOTE: CHLACC.LORDID format is 'LAN-00024', CHLRDS.LORDID format is '00024'
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(l.HOUSEID))
WHERE l.LORDID IS NOT NULL;
GO


CREATE OR ALTER VIEW vw_sys_pm_landlord_accounts_transaction_FROM_CHIBALS AS
SELECT
    ROW_NUMBER() OVER (ORDER BY ib.TENANTNAME, ib.BLOCKNO, ib.ROOMNO)  AS id,
    GETDATE()                                           AS date_time,
    CAST(ib.TRANSDATE AS DATETIME)                      AS trans_date_time,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHBLOCKS -> CHLRDS chain via BLOCKNO -> LLORDID
    prop.prop_row_id                                    AS property_id,
    -- JOINED: CHBLOCKS via BLOCKNO
    unt.unit_row_id                                     AS unit_id,
    -- JOINED: CHBUNITS via BLOCKNO + ROOMNO
    NULL                                                AS lease_id,
    ten.ten_row_id                                      AS tenant_id,
    -- JOINED: CHTENS via TENANTNAME + BLOCKNO + ROOMNO
    'BAL-' + ib.BLOCKNO + '-' + ib.ROOMNO               AS origin_id,
    NULL                                                AS transaction_type_id,
    RTRIM(LTRIM(ib.TRANSDESC))                          AS narration,
    CASE WHEN ISNULL(ib.CURRENTBAL, 0) >= 0 THEN 'Debit' ELSE 'Credit' END  AS amount_type,
    ABS(ISNULL(ib.CURRENTBAL, 0))                       AS amount,
    NULL                                                AS sla_id,
    CAST(ISNULL(ib.CURRENTBAL, 0) AS VARCHAR(50))       AS balance,
    1                                                   AS current_status_id,
    NULL                                                AS invoice_id,
    ABS(ISNULL(ib.CURRENTBAL, 0))                       AS amount_internal,
    NULL                                                AS payable_status,
    NULL                                                AS period_id,
    NULL                                                AS period_status_id,
    NULL                                                AS zone_id
FROM CHABRINS.dbo.CHIBALS ib
LEFT JOIN (
    SELECT BLOCKID, ROW_NUMBER() OVER (ORDER BY BLOCKID) AS prop_row_id, LLORDID
    FROM CHABRINS.dbo.CHBLOCKS
    WHERE BLOCKID IS NOT NULL
) prop ON RTRIM(LTRIM(prop.BLOCKID)) = RTRIM(LTRIM(ib.BLOCKNO))
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(prop.LLORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
LEFT JOIN (
    SELECT BLOCKID, UNITNO, ROW_NUMBER() OVER (ORDER BY BLOCKID, UNITNO) AS unit_row_id
    FROM CHABRINS.dbo.CHBUNITS
    WHERE UNITNO IS NOT NULL
) unt ON RTRIM(LTRIM(unt.BLOCKID)) = RTRIM(LTRIM(ib.BLOCKNO))
     AND RTRIM(LTRIM(unt.UNITNO)) = RTRIM(LTRIM(ib.ROOMNO))
LEFT JOIN (
    SELECT TENNAME, BLOCKNO, UNITNO,
           ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO) AS ten_row_id
    FROM CHABRINS.dbo.CHTENS
    WHERE TENNAME IS NOT NULL
) ten ON ten.TENNAME = ib.TENANTNAME AND RTRIM(LTRIM(ten.BLOCKNO)) = RTRIM(LTRIM(ib.BLOCKNO))
     AND RTRIM(LTRIM(ten.UNITNO)) = RTRIM(LTRIM(ib.ROOMNO))
WHERE ib.TENANTNAME IS NOT NULL;
GO


CREATE OR ALTER VIEW vw_sys_pm_landlord_accounts_transaction_FROM_CHLCAC AS
SELECT
    ROW_NUMBER() OVER (ORDER BY c.YEARR, c.MONTHH, c.LORDID)  AS id,
    GETDATE()                                           AS date_time,
    c.CLDAT                                             AS trans_date_time,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHLRDS via LORDID to get landlord/client sequential id
    NULL                                                AS property_id,
    NULL                                                AS unit_id,
    NULL                                                AS lease_id,
    NULL                                                AS tenant_id,
    'CLOSE-' + CAST(c.YEARR AS VARCHAR) + '-' + CAST(c.MONTHH AS VARCHAR)  AS origin_id,
    NULL                                                AS transaction_type_id,
    'Period closing balance'                            AS narration,
    CASE WHEN ISNULL(c.SUMBL, 0) >= 0 THEN 'Debit' ELSE 'Credit' END  AS amount_type,
    ABS(ISNULL(c.SUMBL, 0))                             AS amount,
    NULL                                                AS sla_id,
    CAST(ISNULL(c.SUMBL, 0) AS VARCHAR(50))             AS balance,
    CASE WHEN c.CLSTAT = 1 THEN 1 ELSE 2 END           AS current_status_id,
    NULL                                                AS invoice_id,
    ABS(ISNULL(c.SUMBL, 0))                             AS amount_internal,
    NULL                                                AS payable_status,
    c.YEARR * 100 + c.MONTHH                            AS period_id,
    NULL                                                AS period_status_id,
    NULL                                                AS zone_id
FROM CHABRINS.dbo.CHLCAC c
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(c.LORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
-- NOTE: CHLCAC.LORDID format is 'LAN-00024', CHLRDS.LORDID format is '00024'
WHERE c.LORDID IS NOT NULL;
GO


-- ============================================================================
-- 25. CLIENT ADVANCES / LOANS: CHLNTR -> sys_pm_clients_advances_requisitions
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_pm_clients_advances_requisitions AS
SELECT
    ROW_NUMBER() OVER (ORDER BY ln.LORDID, ln.LNSERIE)  AS id,
    GETDATE()                                           AS date_time,
    ln.REPDATE                                          AS requisition_date,
    'Loan series ' + CAST(ln.LNSERIE AS VARCHAR)        AS narration,
    NULL                                                AS created_by,
    NULL                                                AS zone_id,
    lr.lord_row_id                                      AS client_id,
    -- JOINED: CHLRDS via LORDID to get landlord/client sequential id
    NULL                                                AS property_id,
    CASE WHEN ln.STPAID = 1 THEN 1 ELSE 2 END          AS current_status_id,
    ISNULL(ln.LNAMT, 0)                                 AS amount,
    0                                                   AS repayment_months,
    ISNULL(ln.INTRATE, 0)                               AS interest_rate,
    ln.REPDATE                                          AS principal_start_date,
    ln.REPDATE                                          AS disbursement_date,
    CAST(ISNULL(ln.INTRESTAMT, 0) AS VARCHAR(50))       AS interest_amount,
    CAST(ISNULL(ln.TOTALAMT, 0) AS VARCHAR(50))         AS total_amount,
    ISNULL(ln.DOCNO, 'LN-' + ln.LORDID + '-' + CAST(ln.LNSERIE AS VARCHAR))  AS reference_number,
    NULL                                                AS advance_type_id,
    NULL                                                AS previous_advance_id,
    0                                                   AS top_up,
    NULL                                                AS miscallenous_charge_id,
    0                                                   AS charge_amount,
    ISNULL(ln.BALANC, 0)                                AS current_balance,
    'No'                                                AS one_off,
    NULL                                                AS voucher_id
FROM CHABRINS.dbo.CHLNTR ln
LEFT JOIN (
    SELECT lordid_clean, lord_row_id FROM (
        SELECT RTRIM(LTRIM(LORDID)) AS lordid_clean,
               ROW_NUMBER() OVER (ORDER BY MIN(LORDID)) AS lord_row_id
        FROM CHABRINS.dbo.CHLRDS
        WHERE LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''
        GROUP BY RTRIM(LTRIM(LORDID))
    ) lrd
) lr ON lr.lordid_clean = REPLACE(REPLACE(REPLACE(RTRIM(LTRIM(ln.LORDID)), 'LAN-', ''), 'LAN-', ''), 'LAN-', '')
-- NOTE: CHLNTR.LORDID format is 'LAN-00003', CHLRDS.LORDID format is '00003'
WHERE ln.LORDID IS NOT NULL;
GO


/*
================================================================================
  MIGRATION LOADING ORDER (Recommended)
================================================================================

  Phase 1 — Master/Reference Data (no FK dependencies):
    1. vw_sys_set_banks                              (CHBANK)
    2. vw_sys_set_banks_branches                     (CHBBRA)  - needs bank_id
    3. vw_sys_gl_chart_accounts_master                (CHFACTV)
    4. vw_sys_set_invoiceable_items                  (CHCHRS)
    5. vw_sys_set_clients_dependent_charges          (CHCDC)
    6. vw_sys_gl_accounts_setup                      (CHIACCT)
    7. vw_sys_fin_cheque_books                       (CHCHQ)

  Phase 2 — Clients & Property Structure:
    8. vw_sys_pm_clients                             (CHLRDS)  - landlords = clients
    9. vw_sys_pm_clients_from_chtenants               (CHTENS)  - tenants = clients
    10. vw_sys_pm_properties                          (CHBLOCKS) - needs client_id
    11. vw_sys_pm_units                               (CHBUNITS) - needs property_id

  Phase 3 — Leases (after clients, properties, units):
    12. vw_sys_pm_lease_agreements                    (CHCNAM + CHTENS join)

  Phase 4 — Financial Transactions:
    13. vw_sys_gl_transactions                        (CHJVH + CHJVHP)
    14. vw_sys_gl_transactions_91_repeat              (CHJVD)
    15. vw_sys_gl_accounts_entries_FROM_CHACCE        (CHACCE)
    16. vw_sys_gl_accounts_entries_FROM_CHCBMN        (CHCBMN)
    17. vw_sys_gl_accounts_entries_FROM_CHJVDP        (CHJVDP)
    18. vw_sys_gl_ledgers_transactions_FROM_CHCBDE    (CHCBDE)
    19. vw_sys_gl_ledgers_transactions_FROM_CHGLAC    (CHGLAC)

  Phase 5 — Payment Vouchers:
    20. vw_sys_fin_payment_vouchers                   (CHBCTH)
    21. vw_sys_fin_payment_vouchers_264_repeat_FROM_CHBCTD  (CHBCTD)
    22. vw_sys_fin_payment_vouchers_264_repeat_FROM_CHCHD   (CHCHD)

  Phase 6 — Operations (after tenants, properties, leases):
    23. vw_sys_pm_deposits                            (CHDPAY)
    24. vw_sys_pm_deposits_requests                   (CHDPS)
    25. vw_sys_pm_tenants_payments                    (CHIPAYS)
    26. ** CHTPAYS migration needed **                        (Main payments - NOT IN CSV)
    27. vw_sys_pm_clients_returns                     (CHCCRST)
    28. ** CHRHMS migration needed **                         (Returns master - NOT IN CSV)

  Phase 7 — Landlord Accounts:
    29. vw_sys_pm_landlord_accounts_transaction_FROM_CHLACC   (CHLACC)
    30. vw_sys_pm_landlord_accounts_transaction_FROM_CHIBALS  (CHIBALS)
    31. vw_sys_pm_landlord_accounts_transaction_FROM_CHLCAC   (CHLCAC)
    32. vw_sys_pm_clients_advances_requisitions       (CHLNTR)

  Phase 8 — Post-Migration FK Resolution:
    - Run UPDATE scripts to resolve text-based FK references to integer IDs
    - Update account codes to account IDs in GL setup
    - Verify referential integrity


================================================================================
  UNMAPPED TABLES IN CHABRINS (Documented References)
================================================================================

  The following CHABRINS tables are NOT in the mapping CSV but are referenced
  by the mapped tables and may need their own migration views:

  TABLE         | ROWS      | DESCRIPTION                              | REFERENCED BY
  ------------- | --------- | ---------------------------------------- | -------------------------
  CHLRH         | varies    | Landlord-to-Property link table          | CHBLOCKS (client resolution)
  CHOFFICERS    | varies    | Field officers/staff                     | CHBLOCKS (OFFID, SFOID, ZMGRID)
  CHRHMS        | varies    | Returns master with full workflow        | CHCCRST (complete returns data)
  CHTNTRS       | 1,156,397 | Tenant invoice transactions              | Maps to sys_pm_debtors_invoices
  CHTPAYS       | 1,435,222 | Tenant payment details                   | Maps to sys_pm_tenants_payments
  CHTACC        | varies    | Tenant account ledger                    | Maps to sys_pm_tenants_accounts_transactions
  CHSTFF        | varies    | Staff/employee master                    | Maps to sys_staff
  CHTYPES       | varies    | Unit type lookup codes                   | CHBUNITS.UNITTYP
  CHTAXES       | 1         | Tax rates (VAT 16%)                      | Various VAT calculations

  NOTE: CHLRDS is now mapped via view #6 (vw_sys_pm_clients).
        CHTENS is now mapped via view #6b (vw_sys_pm_clients_from_chtenants).
  Recommendation: Create migration views for at minimum CHTNTRS,
  CHTPAYS, and CHRHMS to ensure complete data migration.

================================================================================
*/

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



-- ════════════════════════════════════════════════════════════════════════
-- NUMBERED VIEWS (_001 to _010) — Migration-critical, ordered by suffix
-- ════════════════════════════════════════════════════════════════════════

-- ============================================================================
-- NOTE: CHBLOCKS.LLORDID maps to landlords (CHLRDS) -> clients.
-- LOOKUP: client_id from CHLRDS.LORDID (must load clients first — see view #6)
--
-- CHLRDS (Landlords) — now mapped via view #6 (vw_sys_pm_clients).
--   client_id is resolved by LEFT JOIN to CHLRDS with ROW_NUMBER().
--
-- zone_id  resolved via CHBLOCKS.BLOC  -> vw_sys_set_zones  (view #7a)
-- zone_area_id resolved via CHBLOCKS.BLOCAL -> vw_sys_set_zone_areas (view #7b)
--
-- EXTRA TABLE NEEDED: CHLRH (Landlord-Property link)
--   CHLRH maps LORDID -> BLOCKID with status. Use to verify client_id assignment.
--
-- EXTRA TABLE NEEDED: CHOFFICERS (Field Officers)
--   CHOFFICERS stores field officer IDs used as OFFID, SFOID, ZMGRID in CHBLOCKS.

CREATE OR ALTER VIEW [vw_sys_pm_properties_001] AS
SELECT
    b.property_id                                       AS id,
    NULL                                                AS date_time,
    b.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id from CHLRDS via LLORDID
    RTRIM(LTRIM(b.BLOCKID))                             AS reference_number,
    RTRIM(LTRIM(b.BLOCKDESC))                           AS description,
    NULL                                                AS lat_long,
    NULL                                                AS photos_and_documents,
    b.zone_id                                          AS zone_id,
    -- PRE-STAMPED: zone_id from _mig_zones via BLOC
    b.zone_area_id                                      AS zone_area_id,
    -- PRE-STAMPED: zone_area_id from _mig_zone_areas via BLOCAL
    RTRIM(LTRIM(b.BLOCKDESC))                           AS property_name,
    RTRIM(LTRIM(b.LRNUM))                               AS lr_number,
    NULL                                                AS usage_type_id,
    CASE WHEN b.BSTATUS = 1 THEN 1 ELSE 2 END          AS current_status_id,
    NULL                                                AS acquisition_date,
    NULL                                                AS created_by,
    NULL                                                AS bank_account_id,
    b.field_officer_id                                  AS field_officer_id,
    -- PRE-STAMPED: officer_id from CHOFFICERS via OFFID
    b.zone_supervisor_id                                AS zone_supervisor_id,
    -- PRE-STAMPED: officer_id from CHOFFICERS via SFOID
    b.zone_manager_id                                   AS zone_manager_id,
    -- PRE-STAMPED: officer_id from CHOFFICERS via ZMGRID
    NULL                                                AS parent_property_id
FROM CHABRINS.dbo.CHBLOCKS b
WHERE b.BLOCKID IS NOT NULL;


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
    c.client_id                                         AS id,
    CASE WHEN c.CURYRR IS NOT NULL AND c.CURMNTH IS NOT NULL
         THEN DATEFROMPARTS(c.CURYRR, c.CURMNTH, 1)
         ELSE NULL END                                  AS date_time,
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
    1                                                   AS type_id,
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
-- NOTE: CHBUNITS.BLOCKID is FK to CHBLOCKS -> property_id in PG.
-- LOOKUP: property_id from sys_pm_properties WHERE reference_number = CHBUNITS.BLOCKID

CREATE OR ALTER VIEW vw_sys_pm_units_003 AS
SELECT
    u.unit_id                                           AS id,
    GETDATE()                                           AS date_time,
    u.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id from CHBLOCKS->CHLRDS chain via BLOCKID
    u.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKID
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
WHERE u.unit_id IS NOT NULL;



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

CREATE OR ALTER VIEW vw_sys_pm_clients_tenants_004 AS
SELECT
    t.tenant_id                                         AS id,
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
    'TEN-' + RIGHT('00000' + CAST(t.tenant_id
        AS VARCHAR(10)), 6)                             AS reference_number,
    -- Uses pre-stamped tenant_id to generate TEN-000001, TEN-000002, ...
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
    2                                                   AS type_id,
    RTRIM(LTRIM(t.TENANTID))                            AS client_id,
    NULL                                                AS second_name,
    NULL                                                AS last_name,
    NULL                                                AS title,
    NULL                                                AS gender,
    NULL                                                AS prefered_messages_language_id,
    t.property_id                                      AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKNO
    NULL                                                AS sla_id,
    t.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id from CHBUNITS via BLOCKNO+UNITNO
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
WHERE t.TENNAME IS NOT NULL AND RTRIM(LTRIM(t.TENNAME)) <> '';



-- ============================================================================
-- NOTE: CHTENS is the authoritative source for lease agreements.
--   Previous version drove from CHCNAM (4,222 rows) which joined to CHTENS
--   on TENNAME+BLOCKNO+UNITNO — producing millions of rows because that combo
--   is not unique in CHTENS (multiple historical records per unit).
--   Driving directly from CHTENS gives exactly 133,710 rows:
--     IN  (active)     : 40,892
--     OUT (terminated) : 92,816
--     NULL             :      2
--   tenant_id self-joins back to CHTENS with identical ORDER BY so FK aligns
--   with vw_sys_pm_clients_tenants_004 row numbers.
--   CHTENS columns used for lease fields:
--     LEASSDAT  -> date_start (lease start date)
--     EXDAT     -> date_end   (lease expiry / exit date)
--     LEASELEN  -> years
--     LEASEREN  -> escalation_rate
--     LEASLETY  -> escalation_frequency
--     TSTATUS   -> lease_status / current_status_id

CREATE OR ALTER VIEW vw_sys_pm_lease_agreements_005 AS
SELECT
    t.tenant_id                                         AS id,
    GETDATE()                                           AS date_time,
    blk.client_id                                       AS client_id,
    -- PRE-STAMPED: client_id via CHBLOCKS (BLOCKNO -> CHBLOCKS chain)
    t.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKNO
    t.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id from CHBUNITS via BLOCKNO+UNITNO
    t.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id (same ORDER BY as vw_sys_pm_clients_tenants_004)
    t.LEASSDAT                                          AS date_start,
    t.EXDAT                                             AS date_end,
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = 'IN'  THEN 'Active'
         WHEN RTRIM(LTRIM(t.TSTATUS)) = 'OUT' THEN 'Terminated'
         ELSE 'Active' END                              AS lease_status,
    RTRIM(LTRIM(t.BLOCKNO)) + '-' + RTRIM(LTRIM(t.UNITNO)) + '-' + LEFT(RTRIM(LTRIM(t.TENNAME)), 20)  AS ref_number,
    NULL                                                AS created_by_id,
    NULL                                                AS sla_id,
    -- NOTE: No SLA/service-agreement table exists in CHABRINS.
    -- sla_id must be assigned manually post-migration by matching
    -- sys_pm_service_agreements.property_id to the migrated property.
    blk.zone_id                                         AS zone_id,
    -- PRE-STAMPED: zone_id from CHBLOCKS via BLOC -> _mig_zones
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = 'IN'  THEN 1
         WHEN RTRIM(LTRIM(t.TSTATUS)) = 'OUT' THEN 2
         ELSE 1 END                                     AS current_status_id,
    'No'                                                AS auto_renewal,
    t.LEASELEN                                          AS years,
    0                                                   AS initial_water_meter_reading,
    ISNULL(t.LEASEREN, 0)                               AS escalation_rate,
    RTRIM(LTRIM(t.LEASLETY))                            AS escalation_frequency,
    NULL                                                AS documents,
    NULL                                                AS linked_lease_id,
    'No'                                                AS override_rent_calc
FROM CHABRINS.dbo.CHTENS t
LEFT JOIN CHABRINS.dbo.CHBLOCKS blk
  ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
WHERE t.TENNAME IS NOT NULL AND RTRIM(LTRIM(t.TENNAME)) <> '';



/*
  NOTE: Source changed from CHCNAM to CHTENS.
  CHCNAM (4,222 rows) caused a row explosion when joined to CHTENS because
  TENNAME+BLOCKNO+UNITNO is not unique in CHTENS (history records).
  Driving from CHTENS directly gives one row per tenancy record (133,710 total).
  Lease dates come directly from CHTENS columns (LEASSDAT, EXDAT).
  client_id resolved via CHBLOCKS.LLORDID -> CHLRDS.LORDID chain.
  property_id, unit_id, tenant_id resolved via direct JOINs.
*/


-- ============================================================================
-- NOTE: CHABRINS has NO dedicated zone table. Zone codes (A–G) are stored
--   in CHBLOCKS.BLOC. The 7 distinct values exactly match sys_set_zones in
--   chips_db_v3 (zones A, B, C, D, E, F, G).
--   SOURCE: CHBLOCKS.BLOC (distinct values)
--   TARGET: sys_set_zones (id, zone_name, current_status_id)

CREATE OR ALTER VIEW vw_sys_set_zones_006 AS
SELECT
    z.zone_id                                           AS id,
    GETDATE()                                           AS date_time,
    NULL                                                AS estates_id,
    RTRIM(LTRIM(z.bloc))                                AS zone_name,
    1                                                   AS current_status_id
FROM CHABRINS.dbo._mig_zones z;



-- ============================================================================
-- NOTE: CHBLOCKS.BLOCAL contains locality/area names (e.g. "Githurai 45",
--   "Kasarani", "Zimmerman"). These map to sys_set_zone_areas.area_name.
--   zone_id is resolved by matching BLOC to the zone ROW_NUMBER (view #7a).
--   115 distinct BLOCAL values exist in CHBLOCKS; chips_2 MySQL has 75
--   zone_areas — manual review may be needed for unmapped localities.
--   SOURCE: CHBLOCKS.BLOCAL (distinct values, grouped with their BLOC)
--   TARGET: sys_set_zone_areas (id, area_name, zone_id)

CREATE OR ALTER VIEW vw_sys_set_zone_areas_007 AS
SELECT
    za.zone_area_id                                     AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(za.blocal))                             AS area_name,
    z.zone_id                                           AS zone_id
    -- PRE-STAMPED: zone_id from _mig_zones via bloc
FROM CHABRINS.dbo._mig_zone_areas za
LEFT JOIN CHABRINS.dbo._mig_zones z ON z.bloc = za.bloc;



-- ============================================================================
-- NOTE: Standalone master data. CHRGID is the legacy charge code.


CREATE OR ALTER VIEW vw_sys_set_invoiceable_items_008 AS
SELECT
    item_id                                             AS id,
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



-- ============================================================================
-- NOTE: CHTNTRS is the tenant transactions table (1,156,397 rows).
--   Each row is a charge or allocation against a tenant/unit combo.
--   TRANSTYP values: RENT (796K), NULL (317K), House Deposit, Service Charge,
--   Water Bill, Water Deposit, Electricity Deposit, Electricity Bill, etc.
--   Maps to sys_pm_debtors_invoices (279,413 rows in MySQL chips_2).
--   All TRANSTYP rows are migrated — the system determines invoice vs payment
--   by other flags (INVPRT, BALAMT, TSTATUS, RECOVD).
--
--   FK RESOLUTION:
--   client_id   → CHBLOCKS.LLORDID → CHLRDS ROW_NUMBER (ORDER BY LORDID)
--   property_id → CHBLOCKS ROW_NUMBER (ORDER BY BLOCKID)
--   unit_id     → CHBUNITS ROW_NUMBER (ORDER BY BLOCKID, UNITNO)
--   tenant_id   → CHTENS ROW_NUMBER (ORDER BY TENNAME, BLOCKNO, UNITNO)
--   zone_id     → CHBLOCKS.BLOC → same DISTINCT subquery as vw_sys_set_zones_006
--   field/zone officers → CHOFFICERS via CHBLOCKS.OFFID, SFOID, ZMGRID

CREATE OR ALTER VIEW vw_sys_pm_debtors_invoices_009 AS
SELECT
    n.invoice_id                                        AS id,
    n.TRADATE                                           AS date_time,
    TRY_CAST(NULLIF(RTRIM(LTRIM(n.DATEDUE)), '') AS DATETIME)  AS date_due,
    -- DATEDUE is varchar(50) in CHTNTRS — TRY_CAST handles malformed values
    n.zone_id                                           AS zone_id,
    -- PRE-STAMPED: zone_id via CHBLOCKS.BLOC -> _mig_zones
    n.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id via CHBLOCKS (BLOCKID)
    n.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id via CHBUNITS (BLOCKID + UNITNO)
    n.tenant_id                                         AS lease_id,
    -- PRE-STAMPED: tenant_id via CHTENS — same as lease_agreements id
    n.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id via CHTENS (TENNAME + BLOCKID + UNITNO)
    n.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id via CHBLOCKS -> CHLRDS chain
    NULL                                                AS created_by,
    -- ENTRYBY is a free-text staff name; cannot reliably JOIN to sys_staff ids
    RTRIM(LTRIM(n.INVONUM))                             AS reference_number,
    RTRIM(LTRIM(n.TRANSDESC))                           AS narration,
    2                                                   AS debtor_type_id,
    ISNULL(n.TRANSAMT, 0)                               AS total_amount,
    RTRIM(LTRIM(n.TRANSDESC))                           AS trans_details,
    CASE WHEN RTRIM(LTRIM(n.DELSTAT)) = 'Del' THEN 2
         WHEN RTRIM(LTRIM(n.TSTATUS)) = 'OUT' THEN 2
         ELSE 1 END                                     AS current_status_id,
    NULL                                                AS sla_id,
    CASE WHEN ISNULL(n.INVPRT, 0) = 1 THEN 'Yes' ELSE 'No' END  AS processed,
    NULL                                                AS comm_ammount,
    CAST(ISNULL(n.PAYPERC, 0) AS VARCHAR(50))           AS comm_per,
    NULL                                                AS vat_ammount,
    NULL                                                AS vat_per,
    NULL                                                AS commisionable_amount,
    ISNULL(n.AMTPAY, 0)                                 AS payable_amount,
    NULL                                                AS item_type_id,
    NULL                                                AS cycle_id,
    ISNULL(n.BALAMT, 0)                                 AS current_balance,
    NULL                                                AS client_remittable,
    NULL                                                AS distributions_allocation_id,
    RTRIM(LTRIM(n.TRANSTYP))                            AS return_type_id,
    n.field_officer_id                                  AS field_officer_id,
    -- PRE-STAMPED: field_officer_id via CHBLOCKS -> CHOFFICERS (OFFID)
    n.zone_supervisor_id                                AS zone_supervisor_id,
    -- PRE-STAMPED: zone_supervisor_id via CHBLOCKS -> CHOFFICERS (SFOID)
    n.zone_manager_id                                   AS zone_manager_id,
    -- PRE-STAMPED: zone_manager_id via CHBLOCKS -> CHOFFICERS (ZMGRID)
    NULL                                                AS paid_to_client,
    NULL                                                AS occupancy_status_id,
    NULL                                                AS vat_on_commission,
    1                                                   AS returns_status_id,
    NULL                                                AS return_report_id,
    NULL                                                AS is_arrear_id,
    NULL                                                AS lease_status_id,
    NULL                                                AS remarks_id,
    1                                                   AS return_report_type_id,
    NULL                                                AS parent_invoice_id,
    NULL                                                AS parent_invoice_rpt_id,
    NULL                                                AS override_period_check,
    CASE WHEN n.TRAYR > 0 AND n.TRAMNTH BETWEEN 1 AND 12
         THEN TRY_CONVERT(DATE, CAST(n.TRAYR AS VARCHAR(4)) + '-' + RIGHT('0' + CAST(n.TRAMNTH AS VARCHAR(2)), 2) + '-01')
         ELSE NULL END                                  AS returns_period
FROM CHABRINS.dbo.CHTNTRS n
WHERE n.TENNAME IS NOT NULL AND n.BLOCKID IS NOT NULL;



-- ============================================================================
-- NOTE: CHRTXS is the returns DETAIL table (1,470,210 rows) — pre-aggregation.
--   Each row is one charge line within a monthly landlord return, at unit/tenant
--   level. CHRHMS (35,217) is the property-level HEADER (one row per return).
--   sys_pm_clients_returns in MySQL (288,196 rows) is at unit/tenant granularity,
--   with unit_id, tenant_id, lease_id all populated — matching CHRTXS rows.
--
--   CHRTXS ENTRYTYP distribution:
--     RENT CURRENT (786,954) | Commission (135,389) | Water Bill (132,699+36,543)
--     Service Charge (112,349+41,879) | Advance Payments (76,475)
--     VAT (51,248) | RENT ARREARS (24,021) | Recoveries (21,315) etc.
--   Commission lines (ENTRYTYP='Commission') have NULL UNITNO/TENNAME — they
--   are property-level deduction lines; unit_id/tenant_id will be NULL for them.
--
--   FK RESOLUTION:
--   client_id   → h.LORDID → CHLRDS ROW_NUMBER (same ORDER BY as clients_landlords view)
--   property_id → x.BLOCKID → CHBLOCKS ROW_NUMBER
--   unit_id     → x.BLOCKID + x.UNITNO → CHBUNITS ROW_NUMBER
--   tenant_id   → x.TENNAME + x.BLOCKID + x.UNITNO → CHTENS ROW_NUMBER
--   lease_id    → same CHTENS ROW_NUMBER (aligns with vw_sys_pm_lease_agreements_005)
--   zone_id     → h.RETZON (BLOC letter A–G) → zones DISTINCT-first subquery
--   field/zone officers → CHOFFICERS via CHBLOCKS.OFFID, SFOID, ZMGRID
--
--   CHRTXS columns → sys_pm_clients_returns fields:
--   TRANSNUM    → ref_number (unique per line)
--   RETNPRD     → date_time (YYYYMM int → DATEFROMPARTS)
--   SRCDATE     → return_date
--   ENTRYDESC   → narration
--   ENTRYTYP    → trans_type_id
--   TRANSCODE   → return_type_id
--   RESAMTNT    → trans_amount (gross amount on this line)
--   AMTPAYBL    → payable_amount (landlord's share)
--   RESAMTNT - AMTPAYBL → comm_amount (commission deducted from this line)
--   COMMBL      → commisionable flag (Yes/No)
--   VATAMT      → vat_amount
--   h.COMMRATE  → comm_perc (from CHRHMS header)
--   h.LORDID    → client_id
--   h.RETZON    → zone_id
--   h.RETSTAT   → current_status_id
--   h.PAIDST    → paid_status_id
--   h.APPST     → approval_status
--   h.VOUCHERNO → batch_id
--   h.PREPDAT   → invoice_date
--   h.PAYDAT    → date_paid_out (varchar 30, TRY_CAST)

CREATE OR ALTER VIEW vw_sys_pm_clients_returns_10 AS
SELECT
    x.return_line_id                                    AS id,
    x.RETNPRD                                           AS date_time,
    -- RETNPRD is YYYYMM integer (e.g. 202502) — convert to date post-migration
    RTRIM(LTRIM(x.TRANSNUM))                            AS ref_number,
    -- TRANSNUM is the unique transaction number for this detail line
    x.invoice_id                                        AS invoice_id,
    -- PRE-STAMPED: invoice_id via CHTNTRS composite key (period+type+block+unit+tenant)
    x.SRCDATE                                           AS return_date,
    -- SRCDATE = the source date of the charge (when the transaction originated)
    RTRIM(LTRIM(x.ENTRYDESC))                           AS narration,
    -- ENTRYDESC e.g. "RENT FOR February 2025", "MONTHLY COMMISSION (10%)"
    NULL                                                AS created_by,
    x.zone_id                                           AS zone_id,
    -- PRE-STAMPED: zone_id via CHBLOCKS.BLOC -> _mig_zones
    x.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id via CHBLOCKS -> CHLRDS chain
    x.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id via CHBLOCKS (BLOCKID)
    x.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id via CHBUNITS (BLOCKID + UNITNO)
    x.tenant_id                                         AS lease_id,
    -- PRE-STAMPED: tenant_id via CHTENS — same as lease_agreements id
    x.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id via CHTENS (TENNAME + BLOCKID + UNITNO)
    RTRIM(LTRIM(x.ENTRYTYP))                            AS trans_type_id,
    -- ENTRYTYP: 'RENT CURRENT', 'Commission', 'Water Bill', 'Service Charge',
    --           'RENT ARREARS', 'Recoveries', 'VAT', 'House Deposit' etc.
    RTRIM(LTRIM(x.COMMBL))                              AS commisionable,
    -- COMMBL: 'Yes' or 'No' — whether this line is subject to commission
    RTRIM(LTRIM(h.VOUCHERNO))                           AS batch_id,
    -- From CHRHMS header: payment voucher number for this return
    NULL                                                AS unit_status_id,
    x.PAYPERC                                           AS threshold_percentage,
    0                                                   AS abstract_threshold,
    NULL                                                AS anomally_type_id,
    NULL                                                AS anomally_orig_id,
    ISNULL(h.RETSTAT, 2)                                AS current_status_id,
    -- From CHRHMS header: 1 = processed, 0/NULL = pending
    ISNULL(x.RESAMTNT, 0)                               AS trans_amount,
    -- RESAMTNT = net/gross amount of this charge line
    ISNULL(x.AMTPAYBL, 0)                               AS payable_amount,
    -- AMTPAYBL = landlord's payable share of this line (after comm deduction)
    NULL                                                AS sla_id,
    ISNULL(x.RESAMTNT, 0) - ISNULL(x.AMTPAYBL, 0)     AS comm_amount,
    -- comm_amount = gross line amount minus landlord payable = commission on this line
    CASE WHEN RTRIM(LTRIM(x.COMMBL)) = 'Yes'
         THEN ISNULL(x.RESAMTNT, 0)
         ELSE 0 END                                     AS commissionable_amount,
    -- commissionable_amount = RESAMTNT when the line is subject to commission
    ISNULL(x.VATAMT, 0)                                 AS vat_amount,
    CAST(ISNULL(h.COMMRATE, 0) AS VARCHAR(50))          AS comm_perc,
    -- From CHRHMS header: commission rate % for this return (e.g. 10.00)
    NULL                                                AS vat_per,
    NULL                                                AS item_id,
    NULL                                                AS payable_status_id,
    ISNULL(h.APPST, 0)                                  AS approval_status,
    -- From CHRHMS header: 0=pending, 1=approved
    NULL                                                AS charge_id,
    ISNULL(h.PAIDST, 0)                                 AS paid_status_id,
    -- From CHRHMS header: 1 = paid out to landlord, 0 = not yet paid
    NULL                                                AS tenant_payment_id,
    NULL                                                AS tenant_payment_date,
    0                                                   AS tenant_payment_amount,
    0                                                   AS allocated_total,
    ISNULL(x.RESAMTDR, 0)                               AS invoice_balance,
    -- RESAMTDR = residual debit amount on this line (outstanding balance component)
    x.field_officer_id                                  AS fo_id,
    -- PRE-STAMPED: field_officer_id via CHBLOCKS -> CHOFFICERS (OFFID)
    NULL                                                AS is_bill,
    ISNULL(x.AMTPAYTN, 0)                               AS paid_out,
    -- AMTPAYTN = amount paid (tenant-side payment amount on this line)
    x.zone_supervisor_id                                AS zone_supervisor_id,
    -- PRE-STAMPED: zone_supervisor_id via CHBLOCKS -> CHOFFICERS (SFOID)
    x.zone_manager_id                                   AS zone_manager_id,
    -- PRE-STAMPED: zone_manager_id via CHBLOCKS -> CHOFFICERS (ZMGRID)
    NULL                                                AS comm_vat,
    NULL                                                AS distributions_allocation_id,
    RTRIM(LTRIM(x.TRANSCODE))                           AS return_type_id,
    -- TRANSCODE: 'RENT', 'COMMISSION', 'Water Bill', 'Service Charge',
    --            'House Deposit', 'VAT AMOUNT', 'Recovery' etc.
    TRY_CAST(NULLIF(RTRIM(LTRIM(h.PAYDAT)), '') AS DATETIME)  AS date_paid_out,
    -- From CHRHMS header: PAYDAT is varchar(30) — TRY_CAST handles malformed dates
    h.PREPDAT                                           AS invoice_date
    -- From CHRHMS header: preparation/creation date of the return
FROM CHABRINS.dbo.CHRTXS x
LEFT JOIN CHABRINS.dbo.CHRHMS h ON h.RETURNID = x.RETURNID
    -- Join CHRHMS header for landlord-level fields (status, dates, comm rate, voucher)
WHERE x.RETURNID IS NOT NULL;


/*
  NOTE: Source changed from CHRHMS to CHRTXS (detail lines) joined to CHRHMS (header).
  CHRHMS (35,217 rows) is property-level summary — unit_id/tenant_id were always NULL.
  CHRTXS (1,470,210 rows) is the per-unit pre-aggregation detail that feeds CHRHMS.
  sys_pm_clients_returns in MySQL has unit_id, tenant_id, lease_id populated —
  confirming it maps to CHRTXS, not the summary CHRHMS.
  Commission lines (ENTRYTYP='Commission') have NULL BLOCKID/UNITNO/TENNAME —
  unit_id/tenant_id will be NULL for those rows (property-level deduction entries).
  The CHRHMS header join provides: client_id (via LORDID), zone_id (via RETZON),
  current_status_id (RETSTAT), paid_status_id (PAIDST), approval_status (APPST),
  batch_id (VOUCHERNO), invoice_date (PREPDAT), comm_perc (COMMRATE), date_paid_out (PAYDAT).
*/



-- ════════════════════════════════════════════════════════════════════════
-- REMAINING VIEWS — Supporting / supplementary
-- ════════════════════════════════════════════════════════════════════════

-- ============================================================================
-- 1. BANKS: CHBANK -> sys_set_banks
-- ============================================================================
-- NOTE: Simple master data, no FK dependencies.

CREATE OR ALTER VIEW vw_sys_set_banks AS
SELECT
    bank_id                                         AS id,
    GETDATE()                                       AS date_time,
    RTRIM(LTRIM(BANKNAME))                          AS bank_name,
    BANKCODE                                        AS address,      -- store code in address for reference
    1                                               AS current_status_id  -- Active
FROM CHABRINS.dbo.CHBANK
WHERE bank_id IS NOT NULL;
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
    b.bank_id                                               AS bank_id,
    -- PRE-STAMPED: bank_id from CHBANK via BANKCODE
    RTRIM(LTRIM(b.BRANNAME))                                AS branch_name,
    1                                                       AS current_status_id,
    RTRIM(LTRIM(b.BRANCODE))                                AS branch_code
FROM CHABRINS.dbo.CHBBRA b
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
    account_id                                          AS id,
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
-- 5. DEPENDENT CHARGES: CHCDC -> sys_set_clients_dependent_charges
-- ============================================================================
-- NOTE: Simple parent-child charge link.
-- LOOKUP: item_type_id would resolve from CHCHRS.CHRGID

CREATE OR ALTER VIEW vw_sys_set_clients_dependent_charges AS
SELECT
    ROW_NUMBER() OVER (ORDER BY dc.PCHRGID, dc.DCHRGID)  AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(dc.PCHRGID)) + ' -> ' + RTRIM(LTRIM(dc.DCHRGID))  AS title,
    dc.item_id                                          AS item_type_id,
    -- PRE-STAMPED: item_id from CHCHRS via PCHRGID
    NULL                                                AS credit_account_id,
    1                                                   AS current_status_id,
    NULL                                                AS created_by_id,
    0                                                   AS amount
FROM CHABRINS.dbo.CHCDC dc
WHERE dc.PCHRGID IS NOT NULL;
GO


-- ============================================================================
-- 6. CLIENTS / LANDLORDS: CHLRDS -> sys_pm_clients
-- ============================================================================
-- 6b. CLIENTS (Tenants): CHTENS -> sys_pm_clients
-- ============================================================================
-- 7a. ZONES: CHBLOCKS.BLOC -> sys_set_zones
-- ============================================================================
-- 7b. ZONE AREAS: CHBLOCKS.BLOCAL -> sys_set_zone_areas
-- ============================================================================
-- 8. PROPERTIES / BLOCKS: CHBLOCKS -> sys_pm_properties
-- ============================================================================
-- 9. UNITS: CHBUNITS -> sys_pm_units
-- ============================================================================
-- 10. CHEQUE BOOKS: CHCHQ -> sys_fin_cheque_books
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_fin_cheque_books AS
SELECT
    cq.cheque_id                                        AS id,
    GETDATE()                                           AS date_time,
    cq.bank_id                                          AS bank_account_id,
    -- PRE-STAMPED: bank_id from CHBANK via LEFT(CHQACC,2)
    RTRIM(LTRIM(cq.CHQNO))                              AS first_cheque_number,
    1                                                   AS leafs,
    NULL                                                AS created_by_id,
    CASE WHEN cq.CHQSTAT = 1 THEN 1 ELSE 2 END         AS current_status_id,
    RTRIM(LTRIM(ISNULL(cq.CHQBKNO, cq.CHQNO)))         AS reference_number
FROM CHABRINS.dbo.CHCHQ cq
WHERE cq.cheque_id IS NOT NULL;
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
-- 12. LEASE AGREEMENTS: CHTENS -> sys_pm_lease_agreements
-- ============================================================================
-- 13. DEPOSITS: CHDPAY -> sys_pm_deposits
-- ============================================================================
-- LOOKUP: tenant_id, property_id, unit_id from CHTENS/CHBLOCKS/CHBUNITS

CREATE OR ALTER VIEW vw_sys_pm_deposits AS
SELECT
    ROW_NUMBER() OVER (ORDER BY d.BLOCKID, d.UNITNO, d.TENNAME)  AS id,
    GETDATE()                                           AS date_time,
    GETDATE()                                           AS deposit_date,
    d.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id via CHBLOCKS chain
    d.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKID
    NULL                                                AS sla_id,
    d.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id from CHBUNITS via BLOCKID+UNITNO
    NULL                                                AS lease_id,
    d.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id from CHTENS via TENNAME+BLOCKID+UNITNO
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
    d.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id via CHBLOCKS chain
    d.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKID
    d.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id from CHBUNITS via BLOCKID+UNITNO
    NULL                                                AS lease_id,
    d.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id from CHTENS via TENNAME+BLOCKID+UNITNO
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
WHERE d.TENNAME IS NOT NULL;
GO


-- ============================================================================
-- 15. PAYMENT VOUCHER HEADERS: CHBCTH -> sys_fin_payment_vouchers
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_fin_payment_vouchers AS
SELECT
    h.voucher_id                                        AS id,
    GETDATE()                                           AS date_time,
    CAST(h.BTCHDAT AS DATETIME)                         AS trans_date_time,
    NULL                                                AS recepient_id,
    h.staff_id                                          AS entered_by,
    -- PRE-STAMPED: staff_id from CHSTFF via BTCRBY
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
    d.voucher_id                                       AS parent_id,
    -- PRE-STAMPED: voucher_id from CHBCTH via BTCHID
    NULL                                                AS transaction_type_id,
    ISNULL(d.RCAMT, 0)                                  AS amount,
    RTRIM(LTRIM(d.ACCNO))                               AS debit_account_id,
    ISNULL(d.BASEAMT, 0)                                AS miscallenous_charge,
    'No'                                                AS is_recovery,
    NULL                                                AS cash_custodian_id,
    NULL                                                AS client_id,
    NULL                                                AS client_account_id,
    d.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKID
    RTRIM(LTRIM(d.TRANSDESC))                           AS narration,
    'No'                                                AS charge_client,
    NULL                                                AS cheque_number_id
FROM CHABRINS.dbo.CHBCTD d
WHERE d.BTCHID IS NOT NULL;
GO


-- ============================================================================
-- 17. CHEQUE PAYMENT DETAILS: CHCHD -> sys_fin_payment_vouchers_264_repeat
-- ============================================================================

CREATE OR ALTER VIEW vw_sys_fin_payment_vouchers_264_repeat_FROM_CHCHD AS
SELECT
    ROW_NUMBER() OVER (ORDER BY h.CHQNO, h.ENTRYID)   AS id,
    h.voucher_id                                       AS parent_id,
    -- PRE-STAMPED: voucher_id from CHBCTH via PAYVOUC
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
    h.cheque_id                                         AS cheque_number_id
    -- PRE-STAMPED: cheque_id from CHCHQ via CHQNO+CHQACC
FROM CHABRINS.dbo.CHCHD h
WHERE h.CHQNO IS NOT NULL;
GO


-- ============================================================================
-- 18. ALLOCATED PAYMENTS: CHTPAYS -> sys_pm_tenants_payments_allocated
-- ============================================================================
-- SOURCE: CHTPAYS (1,435,222 rows) — payments received and allocated to tenants.
-- TARGET: sys_pm_tenants_payments_allocated (new clean DDL, id auto-generated).
--
-- Simple straight mapping — no joins. Text name columns (TENNAME, BLOCKID,
-- UNITNO) dropped in favour of pre-stamped integer FKs.
-- PDTOLL, TSTATUS, ADSH, PAYDATF dropped (empty/zero in all rows).
--
-- All FK integer IDs PRE-STAMPED by generate_migration_ids.py:
--   property_id, client_id, zone_id, unit_id, tenant_id,
--   field_officer_id, zone_supervisor_id, zone_manager_id

CREATE OR ALTER VIEW vw_sys_pm_tenants_payments_allocated_011 AS
SELECT
    p.TRANSDAT                                          AS date_time,
    p.PAYDATE                                           AS payment_date,
    p.BATCHID                                           AS batch_id,
    p.ENTRYID                                           AS entry_id,
    CAST(p.RCNO AS VARCHAR(50))                         AS receipt_number,
    ISNULL(p.RCAMT, 0)                                  AS amount,
    p.TRANSDESC                                         AS narration,
    p.PAYFOR                                            AS pay_for,
    p.TRAMNTH                                           AS payment_month,
    p.TRAYR                                             AS payment_year,
    p.PYMOD                                             AS payment_mode,
    p.PAYMOD                                            AS payment_channel,
    p.SECURCD                                           AS security_code,
    p.REFNUM                                            AS reference_number,
    p.ENTRYREF                                          AS entry_ref,
    ISNULL(p.TRANAMT, 0)                                AS trans_amount,
    p.TRANTYP                                           AS trans_type,
    p.TRANRMK                                           AS trans_remarks,
    p.TRANPART                                          AS trans_part,
    p.RMONTH                                            AS receipt_month,
    p.RYEAR                                             AS receipt_year,
    p.RECOVD                                            AS is_recovery,
    p.INRACC                                            AS is_in_account,
    p.property_id                                       AS property_id,
    p.client_id                                         AS client_id,
    p.unit_id                                           AS unit_id,
    p.tenant_id                                         AS tenant_id,
    p.zone_id                                           AS zone_id,
    p.field_officer_id                                  AS field_officer_id,
    p.zone_supervisor_id                                AS zone_supervisor_id,
    p.zone_manager_id                                   AS zone_manager_id,
    1                                                   AS current_status_id
    -- NOTE: cancelled receipts (CHRCS) are updated post-migration
    -- see Post_Migration_Scripts.sql
FROM CHABRINS.dbo.CHTPAYS p
WHERE p.TENNAME IS NOT NULL
  AND p.BLOCKID IS NOT NULL;
GO


-- ============================================================================
-- 18b. UNALLOCATED PAYMENTS: CHBCTD -> sys_pm_tenants_payments_unallocated
-- ============================================================================
-- SOURCE: CHBCTD (1,472,942 rows) — raw bank batch detail lines (pre-posting).
-- TARGET: sys_pm_tenants_payments_unallocated (new clean DDL, id auto-generated).
--
-- Simple straight mapping — no joins. Text name columns (TENNAME, BLOCKID,
-- UNITNO, OFFID, SFOID, ZMGRID, PRPBY, PSTDBY, SVRFBY) dropped.
-- DSPNO, SFID, ENTRYREF2 dropped (empty in all/most rows).
--
-- PRE-STAMPED FK columns: voucher_id, property_id

CREATE OR ALTER VIEW vw_sys_pm_tenants_payments_unallocated_012 AS
SELECT
    d.TRANSDAT                                          AS date_time,
    d.BTCHID                                            AS batch_id,
    d.ENTRYID                                           AS entry_id,
    d.ENTRYREF                                          AS entry_ref,
    d.HSECODE                                           AS house_code,
    d.ENTDAT                                            AS entry_date,
    d.CHZONE                                            AS zone_code,
    d.RCNO                                              AS receipt_number,
    ISNULL(d.RCAMT, 0)                                  AS amount,
    d.TRANSDESC                                         AS narration,
    d.PAYFOR                                            AS pay_for,
    d.PAYMNTH                                           AS pay_month,
    d.PAYYEAR                                           AS pay_year,
    d.SECURNO                                           AS security_code,
    d.TRANSTYP                                          AS trans_type,
    d.PYMOD                                             AS payment_mode,
    d.TRNRMKS                                           AS trans_remarks,
    d.TRNPART                                           AS trans_part,
    d.ACCNO                                             AS gl_account,
    d.BANKC                                             AS bank_code,
    d.SUBYR                                             AS sub_year,
    d.SUBMNTH                                           AS sub_month,
    ISNULL(d.SPSTD, 0)                                  AS is_posted,
    d.SPSTDAT                                           AS posted_date,
    ISNULL(d.SVRFY, 0)                                  AS is_verified,
    d.SVRDAT                                            AS verified_date,
    ISNULL(d.BASEAMT, 0)                                AS base_amount,
    d.SHEETCNT                                          AS sheet_count,
    d.ENTRMKS                                           AS entry_remarks,
    d.voucher_id                                        AS voucher_id,
    d.property_id                                       AS property_id,
    1                                                   AS current_status_id
FROM CHABRINS.dbo.CHBCTD d
WHERE d.BTCHID IS NOT NULL;
GO


-- ============================================================================
-- 18c. TENANT ACCOUNT LEDGER: CHTACC -> sys_pm_tenants_accounts_transactions
-- ============================================================================
-- SOURCE: CHTACC (2,598,893 rows — DR: 1,162,255 + CR: 1,436,637).
--
-- Full tenant DR/CR ledger. Preserves every original column except TENNAME,
-- BLOCKID, and UNITNO which are replaced by pre-stamped integer FK equivalents.
--
-- DRCR = 'DR' → invoice / charge posting (TRANAMT populated, TRANSAMT = 0)
-- DRCR = 'CR' → payment / receipt posting (TRANSAMT populated, TRANAMT = 0)
--
-- NOTE: id is NOT included in this view — PostgreSQL auto-generates it
--   via BIGSERIAL / IDENTITY on the destination table.
--
-- All FK integer IDs are PRE-STAMPED by generate_migration_ids.py:
--   CHTACC.property_id         via CHBLOCKS (BLOCKID)
--   CHTACC.client_id           via CHBLOCKS -> CHLRDS chain
--   CHTACC.zone_id             via CHBLOCKS.BLOC -> _mig_zones
--   CHTACC.field_officer_id    via CHBLOCKS -> CHOFFICERS (OFFID)
--   CHTACC.zone_supervisor_id  via CHBLOCKS -> CHOFFICERS (SFOID)
--   CHTACC.zone_manager_id     via CHBLOCKS -> CHOFFICERS (ZMGRID)
--   CHTACC.unit_id             via CHBUNITS (BLOCKID + UNITNO)
--   CHTACC.tenant_id           via CHTENS   (TENNAME + BLOCKID + UNITNO)
--   CHTACC.invoice_id          via INVONUM  -> CHTNTRS.invoice_id
--   CHTACC.payment_id          via RCNO     -> CHTPAYS.payment_id (CR rows only; NULL on DR)

CREATE OR ALTER VIEW vw_sys_pm_tenants_accounts_transactions_013 AS
SELECT
    a.TRANSDATE                                         AS date_time,
    -- TRANSDATE = date the transaction was posted to the tenant account
    RTRIM(LTRIM(a.TRANSDESC))                           AS narration,
    -- TRANSDESC = free-text description (e.g. "RENT FOR May 2025", "Payment")
    RTRIM(LTRIM(a.PAYFOR))                              AS pay_for,
    -- PAYFOR = charge category (RENT, Water Bill, Service Charge, etc.)
    RTRIM(LTRIM(a.DRCR))                                AS dr_cr,
    -- DR = invoice/charge posted; CR = payment/receipt applied
    ISNULL(a.TRANAMT, 0)                                AS debit_amount,
    -- TRANAMT = amount on DR rows (invoice/charge face value); 0 on CR rows
    ISNULL(a.TRANSAMT, 0)                               AS credit_amount,
    -- TRANSAMT = amount on CR rows (payment allocated); 0 on DR rows
    a.RCNO                                              AS receipt_number,
    -- RCNO = receipt number for CR rows; NULL on DR rows; links to CHRCS/CHTPAYS
    RTRIM(LTRIM(a.PYMOD))                               AS payment_mode,
    -- PYMOD = payment mode code (CA=Cash, PO=PostOffice, REC=Receipt)
    RTRIM(LTRIM(a.ENTRYREF))                            AS entry_ref,
    -- ENTRYREF = entry reference / batch channel identifier
    RTRIM(LTRIM(a.ENTRYBY))                             AS entry_by,
    -- ENTRYBY = free-text staff name who created the entry
    RTRIM(LTRIM(a.DELSTAT))                             AS del_status,
    -- DELSTAT = deletion status flag ('Del' = soft-deleted)
    RTRIM(LTRIM(a.INVONUM))                             AS invoice_number,
    -- INVONUM = invoice reference string linking to CHTNTRS
    a.invoice_id                                        AS invoice_id,
    -- PRE-STAMPED: invoice_id via INVONUM -> CHTNTRS.invoice_id
    a.payment_id                                        AS payment_id,
    -- PRE-STAMPED: payment_id via RCNO -> CHTPAYS.payment_id (NULL on DR rows)
    a.zone_id                                           AS zone_id,
    -- PRE-STAMPED: zone_id via CHBLOCKS.BLOC -> _mig_zones
    a.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id via CHBLOCKS (BLOCKID)
    a.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id via CHBUNITS (BLOCKID + UNITNO)
    a.tenant_id                                         AS lease_id,
    -- PRE-STAMPED: tenant_id via CHTENS — same sequence as lease_agreements id
    a.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id via BLOCKID -> CHBLOCKS -> CHLRDS chain
    a.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id via CHTENS (TENNAME + BLOCKID + UNITNO)
    NULL                                                AS sla_id,
    a.field_officer_id                                  AS field_officer_id,
    -- PRE-STAMPED: field_officer_id via CHBLOCKS -> CHOFFICERS (OFFID)
    a.zone_supervisor_id                                AS zone_supervisor_id,
    -- PRE-STAMPED: zone_supervisor_id via CHBLOCKS -> CHOFFICERS (SFOID)
    a.zone_manager_id                                   AS zone_manager_id,
    -- PRE-STAMPED: zone_manager_id via CHBLOCKS -> CHOFFICERS (ZMGRID)
    CASE WHEN RTRIM(LTRIM(a.DELSTAT)) = 'Del' THEN 2
         ELSE 1 END                                     AS current_status_id
    -- Active (1) unless soft-deleted (2)
FROM CHABRINS.dbo.CHTACC a
WHERE a.DRCR IS NOT NULL;
GO


-- ============================================================================
-- 18d. RECEIPT BOOK: CHRCS -> cb_receipts
-- ============================================================================
-- SOURCE: CHRCS (1,969,089 rows) — receipt register.
-- TARGET: cb_receipts (new clean DDL, id auto-generated).
--
-- Simple straight mapping — all columns lowercase, no joins.
-- RCSTATUS: OK (1,824,410), CANCELLED (144,678), 0 (1).
-- Post-migration: UPDATE sys_pm_tenants_payments_allocated SET current_status_id=2
--   WHERE receipt_number IN (SELECT rcno FROM cb_receipts WHERE rcstatus='CANCELLED')

CREATE OR ALTER VIEW vw_cb_receipts_014 AS
SELECT
    r.RCNO                                              AS rcno,
    r.RCOF                                              AS rcof,
    r.SUBDATE                                           AS subdate,
    r.SUBMTH                                            AS submth,
    r.SUBYR                                             AS subyr,
    ISNULL(r.SUBAMT, 0)                                 AS subamt,
    r.DPSNO                                             AS dpsno,
    r.RUSED                                             AS rused,
    r.HOUSE                                             AS house,
    r.ACCNO                                             AS accno,
    r.ACCBANK                                           AS accbank,
    r.RCSTATUS                                          AS rcstatus,
    r.CANBY                                             AS canby,
    r.CANREASON                                         AS canreason,
    r.CANDATE                                           AS candate
FROM CHABRINS.dbo.CHRCS r;
GO


-- ============================================================================
-- 19. DEBTORS INVOICES: CHTNTRS -> sys_pm_debtors_invoices
-- ============================================================================
-- 20. CLIENT RETURNS: CHRTXS + CHRHMS -> sys_pm_clients_returns
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
    combined.staff_id                                   AS entered_by
    -- PRE-STAMPED: staff_id via CHSTFF (CREATEDBY -> EMPNUMB) on both CHJVH and CHJVHP
FROM (
    SELECT *, 'H' AS src FROM CHABRINS.dbo.CHJVH
    UNION ALL
    SELECT *, 'P' AS src FROM CHABRINS.dbo.CHJVHP
) combined
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
    d.account_id                                        AS account_id,
    -- PRE-STAMPED: account_id via CHFACTV (ACCNUM)
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
    cb.bank_id                                          AS bank_id,
    -- PRE-STAMPED: bank_id via CHBANK (LEFT(BANKACC,2) -> BANKCODE)
    RTRIM(LTRIM(cb.CBODOCNO))                           AS origin_id,
    NULL                                                AS transaction_type_id,
    RTRIM(LTRIM(cb.ENTTYPE))                             AS ledger_type_id,
    NULL                                                AS control_account_id,
    RTRIM(LTRIM(cb.ENTDESC))                            AS narration,
    CASE WHEN ISNULL(cb.AMTCSHDR, 0) + ISNULL(cb.AMTBNKDR, 0) > 0 THEN 'Debit'
         ELSE 'Credit' END                              AS amount_type,
    CASE WHEN ISNULL(cb.AMTCSHDR, 0) + ISNULL(cb.AMTBNKDR, 0) > 0
         THEN ISNULL(cb.AMTCSHDR, 0) + ISNULL(cb.AMTBNKDR, 0)
         ELSE ISNULL(cb.AMTCSHCR, 0) + ISNULL(cb.AMTBNKCR, 0) END  AS amount
FROM CHABRINS.dbo.CHCBDE cb
WHERE cb.BANKACC IS NOT NULL;
GO


CREATE OR ALTER VIEW vw_sys_gl_ledgers_transactions_FROM_CHGLAC AS
SELECT
    ROW_NUMBER() OVER (ORDER BY g.PYEARR, g.PMONTH, g.ACCNUM, g.BATCHID, g.ENTRYID)  AS id,
    GETDATE()                                           AS date_time,
    g.ENTDATE                                           AS trans_date_time,
    NULL                                                AS client_id,
    g.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id via CHBLOCKS (BLOCKID)
    NULL                                                AS sla_id,
    g.unit_id                                           AS unit_id,
    -- PRE-STAMPED: unit_id via CHBUNITS (BLOCKID + UNITNO)
    NULL                                                AS lease_id,
    g.tenant_id                                         AS tenant_id,
    -- PRE-STAMPED: tenant_id via CHTENS (TENNAME + BLOCKID + UNITNO)
    NULL                                                AS supplier_id,
    NULL                                                AS bank_id,
    RTRIM(LTRIM(g.BANKACC))                             AS origin_id,
    NULL                                                AS transaction_type_id,
    'General'                                           AS ledger_type_id,
    g.account_id                                        AS control_account_id,
    -- PRE-STAMPED: account_id via CHFACTV (ACCNUM)
    RTRIM(LTRIM(g.ENTDESC))                             AS narration,
    CASE WHEN ISNULL(g.AMTDR, 0) > 0 THEN 'Debit'
         ELSE 'Credit' END                              AS amount_type,
    CASE WHEN ISNULL(g.AMTDR, 0) > 0 THEN g.AMTDR
         ELSE g.AMTCR END                               AS amount
FROM CHABRINS.dbo.CHGLAC g
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
    l.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id via CHLRDS (LORDID, strip LAN- prefix)
    l.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id via CHBLOCKS (HOUSEID -> BLOCKID)
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
    25. vw_sys_pm_tenants_payments_011               (CHTPAYS + CHRCS join)
    26. vw_sys_pm_tenants_payments_invoices_012       (CHTACC CR rows + CHTNTRS join)
    27. vw_sys_pm_tenants_accounts_transactions_013  (CHTACC full ledger — DR + CR)
    28. vw_sys_pm_clients_returns                     (CHCCRST)
    29. ** CHRHMS migration needed **                         (Returns master - NOT IN CSV)

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

-- ============================================================================
-- OTHER MODULE: CREATE OR ALTER VIEW Scripts  (mod batch)
-- ============================================================================
-- Source:    chabrins_to_postgres_views.sql
-- Database:  CHABRINS
-- Generated: 2026-03-15
--
-- Execution: Run the entire file as ONE query (no GO separators).
--            Each view is wrapped in EXEC sp_executesql so SQL Server
--            treats each CREATE OR ALTER VIEW as its own batch internally.
--
-- View index
--   _001  vw_sys_pm_properties_001
--   _002  vw_sys_pm_clients_landlords_002
--   _003  vw_sys_pm_units_003
--   _004  vw_sys_pm_clients_tenants_004
--   _005  vw_sys_pm_lease_agreements_005
--   _006  vw_sys_set_zones_006
--   _007  vw_sys_set_zone_areas_007
--   _008  vw_sys_set_invoiceable_items_008
--   _009  vw_sys_pm_debtors_invoices_009
--   _010  vw_sys_pm_clients_returns_10
--   _011  vw_sys_pm_tenants_payments_allocated_011
--   _012  vw_sys_pm_tenants_payments_unallocated_012
--   _013  vw_sys_pm_tenants_accounts_transactions_013
--   _014  vw_cb_receipts_014
-- ============================================================================

USE CHABRINS;

-- ============================================================================
-- _001  vw_sys_pm_properties_001
-- ============================================================================
EXEC sp_executesql N'
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
';

-- ============================================================================
-- _002  vw_sys_pm_clients_landlords_002
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_pm_clients_landlords_002] AS
SELECT
    c.client_id                                         AS id,
    CASE WHEN c.CURYRR IS NOT NULL AND c.CURMNTH IS NOT NULL
         THEN DATEFROMPARTS(c.CURYRR, c.CURMNTH, 1)
         ELSE NULL END                                  AS date_time,
    RTRIM(LTRIM(c.LFNAME))                              AS names,
    RTRIM(LTRIM(c.ADDRSS1))                             AS address,
    CASE WHEN c.VATSTAT = 1 THEN ''VAT Registered''
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
    CASE WHEN RTRIM(LTRIM(c.LANDSTT)) = ''Active'' THEN 1
         ELSE 2 END                                     AS current_status_id,
    CASE WHEN RTRIM(LTRIM(c.LANDSTT)) = ''Active'' THEN 1
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
WHERE c.LORDID IS NOT NULL AND RTRIM(LTRIM(c.LORDID)) <> '''';
';

-- ============================================================================
-- _003  vw_sys_pm_units_003
-- ============================================================================
EXEC sp_executesql N'
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
    RTRIM(LTRIM(u.BLOCKID)) + ''-'' + RTRIM(LTRIM(u.UNITNO))  AS unit_code,
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
    CASE WHEN ISNULL(u.VATABL, 0) = 1 THEN ''Yes'' ELSE ''No'' END  AS vat_able,
    CASE WHEN u.USTATUS = 1 THEN 1 ELSE 2 END          AS current_status_id,
    RTRIM(LTRIM(u.UNITNO))                              AS unit_number,
    0                                                   AS initial_water_meter_reading,
    NULL                                                AS topology_id,
    NULL                                                AS block_owner_tenant_id
FROM CHABRINS.dbo.CHBUNITS u
WHERE u.unit_id IS NOT NULL;
';

-- ============================================================================
-- _004  vw_sys_pm_clients_tenants_004
-- ============================================================================
EXEC sp_executesql N'
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
    ''TEN-'' + RIGHT(''00000'' + CAST(t.tenant_id
        AS VARCHAR(10)), 6)                             AS reference_number,
    -- Uses pre-stamped tenant_id to generate TEN-000001, TEN-000002, ...
    NULL                                                AS created_by,
    NULL                                                AS nationality_id,
    RTRIM(LTRIM(t.TENID))                               AS national_id,
    NULL                                                AS passport_number,
    RTRIM(LTRIM(t.TENTYPE))                             AS client_type_id,
    NULL                                                AS photo,
    NULL                                                AS documents,
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = ''IN'' THEN 1
         ELSE 2 END                                     AS current_status_id,
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = ''IN'' THEN 1
         ELSE 2 END                                     AS client_status_id,
    NULL                                                AS lead_id,
    2                                                    AS type_id,
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
WHERE t.TENNAME IS NOT NULL AND RTRIM(LTRIM(t.TENNAME)) <> '''';
';

-- ============================================================================
-- _005  vw_sys_pm_lease_agreements_005
-- ============================================================================
EXEC sp_executesql N'
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
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = ''IN''  THEN ''Active''
         WHEN RTRIM(LTRIM(t.TSTATUS)) = ''OUT'' THEN ''Terminated''
         ELSE ''Active'' END                              AS lease_status,
    RTRIM(LTRIM(t.BLOCKNO)) + ''-'' + RTRIM(LTRIM(t.UNITNO)) + ''-'' + LEFT(RTRIM(LTRIM(t.TENNAME)), 20)  AS ref_number,
    NULL                                                AS created_by_id,
    NULL                                                AS sla_id,
    -- NOTE: No SLA/service-agreement table exists in CHABRINS.
    -- sla_id must be assigned manually post-migration by matching
    -- sys_pm_service_agreements.property_id to the migrated property.
    blk.zone_id                                         AS zone_id,
    -- PRE-STAMPED: zone_id from CHBLOCKS via BLOC -> _mig_zones
    CASE WHEN RTRIM(LTRIM(t.TSTATUS)) = ''IN''  THEN 1
         WHEN RTRIM(LTRIM(t.TSTATUS)) = ''OUT'' THEN 2
         ELSE 1 END                                     AS current_status_id,
    ''No''                                                AS auto_renewal,
    t.LEASELEN                                          AS years,
    0                                                   AS initial_water_meter_reading,
    ISNULL(t.LEASEREN, 0)                               AS escalation_rate,
    RTRIM(LTRIM(t.LEASLETY))                            AS escalation_frequency,
    NULL                                                AS documents,
    NULL                                                AS linked_lease_id,
    ''No''                                                AS override_rent_calc
FROM CHABRINS.dbo.CHTENS t
LEFT JOIN CHABRINS.dbo.CHBLOCKS blk
  ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
WHERE t.TENNAME IS NOT NULL AND RTRIM(LTRIM(t.TENNAME)) <> '''';
';

-- ============================================================================
-- _006  vw_sys_set_zones_006
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_zones_006 AS
SELECT
    z.zone_id                                           AS id,
    GETDATE()                                           AS date_time,
    NULL                                                AS estates_id,
    RTRIM(LTRIM(z.bloc))                                AS zone_name,
    1                                                   AS current_status_id
FROM CHABRINS.dbo._mig_zones z;
';

-- ============================================================================
-- _007  vw_sys_set_zone_areas_007
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_zone_areas_007 AS
SELECT
    za.zone_area_id                                     AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(za.blocal))                             AS area_name,
    z.zone_id                                           AS zone_id
    -- PRE-STAMPED: zone_id from _mig_zones via bloc
FROM CHABRINS.dbo._mig_zone_areas za
LEFT JOIN CHABRINS.dbo._mig_zones z ON z.bloc = za.bloc;
';

-- ============================================================================
-- _008  vw_sys_set_invoiceable_items_008
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_invoiceable_items_008 AS
SELECT
    item_id                                             AS id,
    GETDATE()                                           AS date_time,
    RTRIM(LTRIM(CHRDSC))                                AS item_name,
    RTRIM(LTRIM(CHRGID))                                AS billing_code,
    NULL                                                AS feature,
    NULL                                                AS payment_priority,
    CASE WHEN CHRSTT = ''Commissionable'' THEN ''Yes'' ELSE ''No'' END  AS commissionable,
    NULL                                                AS default_cycle_id,
    CASE WHEN CHRSTT IS NOT NULL THEN 1 ELSE 2 END     AS current_status_id,
    NULL                                                AS default_expense_account_id,
    NULL                                                AS advance_payable,
    CASE WHEN CHRGTYP = ''BILL'' THEN ''Yes'' ELSE ''No'' END  AS is_bill,
    ''Yes''                                               AS fixed_amount,
    ISNULL(DEFAMT, 0)                                   AS amount
FROM CHABRINS.dbo.CHCHRS
WHERE CHRGID IS NOT NULL;
';

-- ============================================================================
-- _009  vw_sys_pm_debtors_invoices_009
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_pm_debtors_invoices_009 AS
SELECT
    n.invoice_id                                        AS id,
    n.TRADATE                                           AS date_time,
    TRY_CAST(NULLIF(RTRIM(LTRIM(n.DATEDUE)), '''') AS DATETIME)  AS date_due,
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
    CASE WHEN RTRIM(LTRIM(n.DELSTAT)) = ''Del'' THEN 2
         WHEN RTRIM(LTRIM(n.TSTATUS)) = ''OUT'' THEN 2
         ELSE 1 END                                     AS current_status_id,
    NULL                                                AS sla_id,
    CASE WHEN ISNULL(n.INVPRT, 0) = 1 THEN ''Yes'' ELSE ''No'' END  AS processed,
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
    CASE WHEN ISNULL(n.TRAYR, 0) > 0 AND n.TRAMNTH BETWEEN 1 AND 12
         THEN TRY_CONVERT(DATE, CAST(n.TRAYR AS VARCHAR(4)) + ''-'' + RIGHT(''0'' + CAST(n.TRAMNTH AS VARCHAR(2)), 2) + ''-01'')
         ELSE NULL END AS returns_period
FROM CHABRINS.dbo.CHTNTRS n
WHERE n.TENNAME IS NOT NULL AND n.BLOCKID IS NOT NULL;
';

-- ============================================================================
-- _010  vw_sys_pm_clients_returns_10
-- ============================================================================
EXEC sp_executesql N'
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
    -- ENTRYTYP: ''RENT CURRENT'', ''Commission'', ''Water Bill'', ''Service Charge'',
    --           ''RENT ARREARS'', ''Recoveries'', ''VAT'', ''House Deposit'' etc.
    RTRIM(LTRIM(x.COMMBL))                              AS commisionable,
    -- COMMBL: ''Yes'' or ''No'' — whether this line is subject to commission
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
    -- AMTPAYBL = landlord''s payable share of this line (after comm deduction)
    NULL                                                AS sla_id,
    ISNULL(x.RESAMTNT, 0) - ISNULL(x.AMTPAYBL, 0)     AS comm_amount,
    -- comm_amount = gross line amount minus landlord payable = commission on this line
    CASE WHEN RTRIM(LTRIM(x.COMMBL)) = ''Yes''
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
    -- TRANSCODE: ''RENT'', ''COMMISSION'', ''Water Bill'', ''Service Charge'',
    --            ''House Deposit'', ''VAT AMOUNT'', ''Recovery'' etc.
    TRY_CAST(NULLIF(RTRIM(LTRIM(h.PAYDAT)), '''') AS DATETIME)  AS date_paid_out,
    -- From CHRHMS header: PAYDAT is varchar(30) — TRY_CAST handles malformed dates
    h.PREPDAT                                           AS invoice_date
    -- From CHRHMS header: preparation/creation date of the return
FROM CHABRINS.dbo.CHRTXS x
LEFT JOIN CHABRINS.dbo.CHRHMS h ON h.RETURNID = x.RETURNID
    -- Join CHRHMS header for landlord-level fields (status, dates, comm rate, voucher)
WHERE x.RETURNID IS NOT NULL;
';

-- ============================================================================
-- _011  vw_sys_pm_tenants_payments_allocated_011
-- ============================================================================
-- SOURCE: CHTPAYS (1,435,222 rows) — payments received and allocated to tenants.
-- TARGET: sys_pm_tenants_payments_allocated (new clean DDL, id auto-generated).
-- Simple straight mapping — no joins. Text columns dropped for integer FKs.
-- All FK integer IDs PRE-STAMPED by generate_migration_ids.py.
EXEC sp_executesql N'
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
';

-- ============================================================================
-- _012  vw_sys_pm_tenants_payments_unallocated_012
-- ============================================================================
-- SOURCE: CHBCTD (1,472,942 rows) — raw bank batch detail lines (pre-posting).
-- TARGET: sys_pm_tenants_payments_unallocated (new clean DDL, id auto-generated).
-- Simple straight mapping — no joins. Text columns dropped.
-- PRE-STAMPED FK columns: voucher_id, property_id.
EXEC sp_executesql N'
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
';

-- ============================================================================
-- _013  vw_sys_pm_tenants_accounts_transactions_013  (DR + CR — full ledger)
-- ============================================================================
-- SOURCE: CHTACC (2,598,893 rows — DR: 1,162,255 + CR: 1,436,637).
-- Full tenant DR/CR ledger. id is auto-generated by PostgreSQL (BIGSERIAL).
-- TENNAME, BLOCKID, UNITNO replaced by pre-stamped tenant_id, property_id, unit_id.
-- All FK integer IDs PRE-STAMPED by generate_migration_ids.py.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_pm_tenants_accounts_transactions_013 AS
SELECT
    a.TRANSDATE                                         AS date_time,
    RTRIM(LTRIM(a.TRANSDESC))                           AS narration,
    RTRIM(LTRIM(a.PAYFOR))                              AS pay_for,
    RTRIM(LTRIM(a.DRCR))                                AS dr_cr,
    -- DR = invoice/charge posted; CR = payment/receipt applied
    ISNULL(a.TRANAMT, 0)                                AS debit_amount,
    -- TRANAMT = amount on DR rows; 0 on CR rows
    ISNULL(a.TRANSAMT, 0)                               AS credit_amount,
    -- TRANSAMT = amount on CR rows; 0 on DR rows
    a.RCNO                                              AS receipt_number,
    RTRIM(LTRIM(a.PYMOD))                               AS payment_mode,
    RTRIM(LTRIM(a.ENTRYREF))                            AS entry_ref,
    RTRIM(LTRIM(a.ENTRYBY))                             AS entry_by,
    RTRIM(LTRIM(a.DELSTAT))                             AS del_status,
    RTRIM(LTRIM(a.INVONUM))                             AS invoice_number,
    a.invoice_id                                        AS invoice_id,
    -- PRE-STAMPED: invoice_id via INVONUM -> CHTNTRS.invoice_id
    a.payment_id                                        AS payment_id,
    -- PRE-STAMPED: payment_id via RCNO -> CHTPAYS.payment_id (NULL on DR rows)
    a.zone_id                                           AS zone_id,
    a.property_id                                       AS property_id,
    a.unit_id                                           AS unit_id,
    a.tenant_id                                         AS lease_id,
    a.client_id                                         AS client_id,
    a.tenant_id                                         AS tenant_id,
    NULL                                                AS sla_id,
    a.field_officer_id                                  AS field_officer_id,
    a.zone_supervisor_id                                AS zone_supervisor_id,
    a.zone_manager_id                                   AS zone_manager_id,
    CASE WHEN RTRIM(LTRIM(a.DELSTAT)) = ''Del'' THEN 2
         ELSE 1 END                                     AS current_status_id
FROM CHABRINS.dbo.CHTACC a
WHERE a.DRCR IS NOT NULL;
';

-- ============================================================================
-- _014  vw_cb_receipts_014
-- ============================================================================
-- SOURCE: CHRCS (1,969,089 rows) — receipt register.
-- TARGET: cb_receipts (new clean DDL, id auto-generated).
-- RCSTATUS: OK (1,824,410), CANCELLED (144,678), 0 (1).
EXEC sp_executesql N'
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
';


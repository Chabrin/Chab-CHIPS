-- ============================================================================
-- OTHER MODULE: CREATE OR ALTER VIEW Scripts  (mod batch)
-- ============================================================================
-- Source:    CHABRINS_NEW_to_postgres_views.sql
-- Database:  CHABRINS_NEW
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
--   _011  vw_sys_pm_tenants_payments_allocated_011
--   _012  vw_sys_pm_tenants_payments_unallocated_012
--   _013  vw_sys_pm_tenants_accounts_transactions_013  (→ pg: sys_pm_tenants_accounts_trans)
--   _014  vw_cb_receipts_014
--   _015  vw_cb_returns_summary_015
--   _016  vw_cb_returns_clients_016
--   _017  vw_chofficers_to_josya_users_017
--   _018  vw_sys_pm_service_agreements_018
--   _019  vw_cb_landlord_account_019
--   _020  vw_cb_money_recoveries_020
--   _021  vw_cb_payment_vouchers_headers_new_021
--   _022  vw_cb_payment_vouchers_headers_old_022
--   _023  vw_cb_payment_vouchers_lines_new_023
--   _024  vw_cb_payment_vouchers_lines_old_024
--   _026  vw_cb_landlord_charge_pairs_026
--   _027  vw_staff_property_mappings_027  (CHOFALL -> staff_property_mappings)
--   _028  vw_vendors_028  (CHVENDS -> vendors)
--   _029  vw_landlord_property_mappings_029  (CHLRH -> landlord_property_mappings)
--   _030  vw_cheque_register_030  (CHCHQ -> cheque_register)
--   _031  vw_cheque_register_details_031  (CHCHD -> cheque_register_details)
--   _032  vw_landlord_advance_repayment_schedule_032  (CHLNTR -> landlord_advance_repayment_schedule)
--   ── HR module, merged in from peter_hr_mods_final.sql (was _P_01–_P_24) ──
--   _033  vw_sys_set_departments_033
--   _034  vw_sys_set_staff_positions_034
--   _035  vw_sys_set_banks_035
--   _036  vw_sys_set_banks_branches_036
--   _037  vw_sys_staff_037
--   _038  vw_sys_staff_next_of_kin_038
--   _039  vw_sys_hr_payroll_elements_039
--   _040  vw_um_tax_bands_040
--   _041  vw_sys_hr_payroll_calendar_041
--   _042  vw_sys_hr_payroll_periods_042
--   _043  vw_sys_hr_payroll_summary_043
--   _044  vw_sys_hr_payslip_elements_044
--   _045  vw_sys_helb_deductions_045
--   _046  vw_sys_hr_employee_amortized_deductions_046
--   _047  vw_sys_hr_p9a_047
--   _048  vw_rpt_statutory_nssf_048
--   _049  vw_rpt_statutory_shif_049
--   _050  vw_rpt_statutory_housing_levy_050
--   _051  vw_sys_pm_clients_17_repeat_051  (staff bank accounts)
--   _052  vw_sys_pm_clients_19_repeat_052  (staff other contacts)
--   _053  vw_sys_hr_employee_contracts_053
--   _054  vw_sys_hr_employee_contracts_279_repeat_054
--   _055  vw_sys_hr_payroll_deductions_register_055
--   _056  vw_sys_hr_payroll_elements_repeat_staff_positions_id_056
--   _057  vw_employee_deductions_perperiod_057  (CHPEED -> employee_deductions_perperiod)
-- ============================================================================

USE CHABRINS_NEW;

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
    b.BSTATUS                                           AS active,
    b.BSTATUS                                           AS current_status_id,
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
FROM CHABRINS_NEW.dbo.CHBLOCKS b
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
FROM CHABRINS_NEW.dbo.CHLRDS c
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
FROM CHABRINS_NEW.dbo.CHBUNITS u
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
FROM CHABRINS_NEW.dbo.CHTENS t
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
    -- NOTE: No SLA/service-agreement table exists in CHABRINS_NEW.
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
    sc.amt                                              AS service_charges_amount,
    -- DERIVED: no direct service-charge amount on CHTENS — CHTNTRS has no
    -- dedicated table for it either, so we take the most recent CHTNTRS
    -- charge of type ''Service Charge'' for this tenant/lease (tenant_id
    -- doubles as lease_id throughout this migration, see vw_..._009).
    RTRIM(LTRIM(t.LEASLETY))                            AS escalation_frequency,
    NULL                                                AS documents,
    NULL                                                AS linked_lease_id,
    ''No''                                                AS override_rent_calc
FROM CHABRINS_NEW.dbo.CHTENS t
LEFT JOIN CHABRINS_NEW.dbo.CHBLOCKS blk
  ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
OUTER APPLY (
    SELECT TOP 1 n.TRANSAMT AS amt
    FROM CHABRINS_NEW.dbo.CHTNTRS n
    WHERE n.tenant_id = t.tenant_id
      AND RTRIM(LTRIM(n.TRANSTYP)) = ''Service Charge''
    ORDER BY n.TRADATE DESC
) sc
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
FROM CHABRINS_NEW.dbo._mig_zones z;
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
FROM CHABRINS_NEW.dbo._mig_zone_areas za
LEFT JOIN CHABRINS_NEW.dbo._mig_zones z ON z.bloc = za.bloc;
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
FROM CHABRINS_NEW.dbo.CHCHRS
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
FROM CHABRINS_NEW.dbo.CHTNTRS n
WHERE n.TENNAME IS NOT NULL AND n.BLOCKID IS NOT NULL;
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
FROM CHABRINS_NEW.dbo.CHTPAYS p
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
FROM CHABRINS_NEW.dbo.CHBCTD d
WHERE d.BTCHID IS NOT NULL;
';

-- ============================================================================
-- _013  vw_sys_pm_tenants_accounts_transactions_013  (DR + CR — full ledger)  → pg: sys_pm_tenants_accounts_trans
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
FROM CHABRINS_NEW.dbo.CHTACC a
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
FROM CHABRINS_NEW.dbo.CHRCS r;
';

-- ============================================================================
-- _015  vw_cb_returns_summary_015  (CHRHMS -> cb_returns_summary, 35,217 rows)
-- ============================================================================
-- Return header/summary: one row per property per period.
-- Workflow: prepared -> checked -> finalized -> approved -> paid.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_returns_summary_015 AS
SELECT
    h.return_id                                         AS id,
    RTRIM(LTRIM(h.RETURNID))                            AS return_code,
    RTRIM(LTRIM(h.LORDID))                              AS landlord_code,
    RTRIM(LTRIM(h.BLOCKID))                             AS property_code,
    h.YEARR                                             AS year,
    h.MONTHH                                            AS month,
    RTRIM(LTRIM(h.RETDESC))                             AS return_description,
    RTRIM(LTRIM(h.RETNPRD))                             AS return_period,
    RTRIM(LTRIM(h.RETTYPE))                             AS return_type,
    ISNULL(h.COMMRATE, 0)                               AS commission_rate,
    ISNULL(h.TOTALCR, 0)                                AS total_credit,
    ISNULL(h.SUMCOM, 0)                                 AS commission_total,
    ISNULL(h.SUMVATBL, 0)                               AS vatable_total,
    ISNULL(h.TOTALCOM, 0)                               AS commission_amount,
    ISNULL(h.TOTALDR, 0)                                AS total_debit,
    ISNULL(h.NETAMT, 0)                                 AS net_amount,
    ISNULL(h.TRXAMT, 0)                                 AS transaction_amount,
    ISNULL(h.NETBAL, 0)                                 AS net_balance,
    RTRIM(LTRIM(h.PREPBY))                              AS prepared_by,
    ISNULL(h.PREPST, 0)                                 AS prepared_status,
    h.PREPDAT                                           AS prepared_date,
    RTRIM(LTRIM(h.CHKBY))                               AS checked_by,
    ISNULL(h.CHKST, 0)                                  AS checked_status,
    h.CHKDAT                                            AS checked_date,
    ISNULL(h.SAVDST, 0)                                 AS saved_status,
    ISNULL(h.FINAST, 0)                                 AS finalized_status,
    RTRIM(LTRIM(h.FINABY))                              AS finalized_by,
    h.FINADAT                                           AS finalized_date,
    RTRIM(LTRIM(h.APPBY))                               AS approved_by,
    ISNULL(h.APPST, 0)                                  AS approved_status,
    h.APPDAT                                            AS approved_date,
    ISNULL(h.PAIDST, 0)                                 AS paid_status,
    RTRIM(LTRIM(h.VOUCHERNO))                           AS voucher_number,
    RTRIM(LTRIM(h.RETRMKS))                             AS remarks,
    RTRIM(LTRIM(h.RETRMKS2))                            AS remarks_2,
    h.REBYRR                                            AS rebate_year,
    h.REBMNTH                                           AS rebate_month,
    RTRIM(LTRIM(h.RETURNT))                             AS return_tag,
    ISNULL(h.RETSTAT, 0)                                AS return_status,
    RTRIM(LTRIM(h.CHQNO))                               AS cheque_number,
    RTRIM(LTRIM(h.BANKACC))                             AS bank_account,
    ISNULL(h.AMTPAYD, 0)                                AS amount_paid,
    ISNULL(h.AMTOVD, 0)                                 AS amount_overdraft,
    RTRIM(LTRIM(h.PAYDAT))                              AS payment_date,
    RTRIM(LTRIM(h.RETZON))                              AS return_zone,
    h.AUTH2M                                            AS auth_2m,
    h.AUTHUN                                            AS auth_un,
    ISNULL(h.OVDPER, 0)                                 AS overdraft_percentage,
    h.return_id                                         AS return_id
FROM CHABRINS_NEW.dbo.CHRHMS h
WHERE h.RETURNID IS NOT NULL;
';

-- ============================================================================
-- _016  vw_cb_returns_clients_016  (CHRTXS -> cb_returns_clients, 1,470,210 rows)
-- ============================================================================
-- Return detail lines: per unit, per tenant, per charge type within a return.
-- Join to cb_returns_summary via return_code (RETURNID).
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_returns_clients_016 AS
SELECT
    x.return_line_id                                    AS id,
    RTRIM(LTRIM(x.RETURNID))                            AS return_code,
    x.RETNPRD                                           AS return_period,
    RTRIM(LTRIM(x.TRANSNUM))                            AS transaction_number,
    RTRIM(LTRIM(x.TRANSDAT))                            AS transaction_date,
    x.TRANSDATF                                         AS transaction_date_num,
    RTRIM(LTRIM(x.ENTRYDE))                             AS entry_description,
    RTRIM(LTRIM(x.ENTRYTYP))                            AS entry_type,
    RTRIM(LTRIM(x.TRANSCODE))                           AS transaction_code,
    RTRIM(LTRIM(x.ENTRYDESC))                           AS narration,
    RTRIM(LTRIM(x.TRANSREF))                            AS transaction_ref,
    x.SRCDATE                                           AS source_date,
    RTRIM(LTRIM(x.BLOCKID))                             AS property_code,
    RTRIM(LTRIM(x.UNITNO))                              AS unit_code,
    x.UNITID                                            AS unit_seq_id,
    RTRIM(LTRIM(x.DRCR))                                AS dr_cr,
    RTRIM(LTRIM(x.TENNAME))                             AS tenant_name,
    RTRIM(LTRIM(x.TENSTAT))                             AS tenant_status,
    RTRIM(LTRIM(x.ENTAMTT))                             AS amount_text,
    ISNULL(x.AMTPAYBL, 0)                               AS amount_payable_landlord,
    ISNULL(x.AMTPAYTN, 0)                               AS amount_payable_tenant,
    ISNULL(x.RESAMTDR, 0)                               AS residual_debit,
    ISNULL(x.RESAMTCR, 0)                               AS residual_credit,
    ISNULL(x.RESAMTNT, 0)                               AS net_amount,
    RTRIM(LTRIM(x.TREMARKS))                            AS remarks,
    RTRIM(LTRIM(x.TRANSSRC))                            AS transaction_source,
    x.DEDPNT                                            AS deduction_point,
    RTRIM(LTRIM(x.VATABL))                              AS is_vatable,
    RTRIM(LTRIM(x.COMMBL))                              AS is_commissionable,
    RTRIM(LTRIM(x.DEDPNT2))                             AS deduction_point_2,
    x.PPERIOD                                           AS payment_period,
    RTRIM(LTRIM(x.CHQNO))                               AS cheque_number,
    RTRIM(LTRIM(x.BANKACC))                             AS bank_account,
    ISNULL(x.VATAMT, 0)                                 AS vat_amount,
    x.AUTH2M                                            AS auth_2m,
    x.AUTHUN                                            AS auth_un,
    RTRIM(LTRIM(x.PAYPERC))                             AS pay_percentage,
    x.return_line_id                                    AS return_line_id,
    x.property_id                                       AS property_id,
    x.client_id                                         AS client_id,
    x.zone_id                                           AS zone_id,
    x.field_officer_id                                  AS field_officer_id,
    x.zone_supervisor_id                                AS zone_supervisor_id,
    x.zone_manager_id                                   AS zone_manager_id,
    x.unit_id                                           AS unit_id,
    x.tenant_id                                         AS tenant_id,
    x.return_id                                         AS return_id,
    x.invoice_id                                        AS invoice_id
FROM CHABRINS_NEW.dbo.CHRTXS x
WHERE x.RETURNID IS NOT NULL;
';

-- ============================================================================
-- _017  vw_chofficers_to_josya_users_017  (CHOFFICERS -> josya_users, 158 rows)
-- ============================================================================
-- CHOFFICERS field officers / zone managers / property managers inserted
-- into josya_users using officer_id as id so FK references match.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_chofficers_to_josya_users_017 AS
SELECT
    o.officer_id                                        AS id,
    RTRIM(LTRIM(o.OFFFNAME))                            AS name,
    RTRIM(LTRIM(o.OFFTYPE))
        + CASE WHEN RTRIM(LTRIM(ISNULL(o.OFFZON, ''''))) <> ''''
               THEN '' | '' + RTRIM(LTRIM(o.OFFZON))
               ELSE '''' END                              AS username,
    LOWER(
        REPLACE(RTRIM(LTRIM(o.OFFONAME)), '' '', ''.'')
        + ''.'' + REPLACE(RTRIM(LTRIM(o.OFFSNAME)), '' '', '''')
        + ''@chabrinagencies.co.ke''
    )                                                   AS email,
    ''$2y$12$i5DCIlg1wO9KrQerpL0UDuws7SnJnSOZ2W0Xcrdlz/z8pAMVjhJuq''
                                                        AS password,
    0                                                   AS block,
    0                                                   AS [sendEmail],
    GETDATE()                                           AS [registerDate],
    NULL                                                AS [lastvisitDate],
    ''''                                                  AS activation,
    ''''                                                  AS params,
    NULL                                                AS [lastResetTime],
    0                                                   AS [resetCount],
    ''''                                                  AS [otpKey],
    NULL                                                AS reset_token,
    NULL                                                AS reset_token_expires_at,
    ''''                                                  AS otep,
    0                                                   AS [requireReset],
    o.officer_id                                        AS officer_id
FROM CHABRINS_NEW.dbo.CHOFFICERS o
WHERE o.officer_id IS NOT NULL;
';

-- ============================================================================
-- _018  vw_sys_pm_service_agreements_018
-- ============================================================================
-- SOURCE: CHBLOCKS (1,842 rows) — one SLA per property.
--   CHABRINS_NEW has NO dedicated SLA table, but CHBLOCKS stores commission rate
--   (COMRATE) and VAT applicability (VATABL) per property/block.
--   QRYCOMMS (10,009 rows) has monthly commission history but CHBLOCKS.COMRATE
--   holds the current/latest rate directly.
-- TARGET: sys_pm_service_agreements (20 columns, id auto-increment)
-- POST-MIGRATION: After loading, update sys_pm_lease_agreements.sla_id:
--   UPDATE sys_pm_lease_agreements l
--   SET sla_id = s.id
--   FROM sys_pm_service_agreements s
--   WHERE s.property_id = l.property_id;
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_pm_service_agreements_018 AS
SELECT
    GETDATE()                                           AS date_time,
    b.client_id                                         AS client_d,
    NULL                                                AS zone_area_id,
    b.property_id                                       AS property_id,
    ISNULL(b.COMRATE, 0)                                AS commission_percentage,
    CASE WHEN RTRIM(LTRIM(b.VATABL)) = ''Yes'' THEN ''Yes''
         ELSE ''No'' END                                  AS vat,
    NULL                                                AS date_start,
    NULL                                                AS date_end,
    b.zone_id                                           AS zone_id,
    NULL                                                AS [select],
    ''SLA-'' + RTRIM(LTRIM(b.BLOCKID))                    AS reference_number,
    NULL                                                AS created_by_id,
    NULL                                                AS sla_documents,
    NULL                                                AS landlord_payment_date,
    CASE WHEN b.BSTATUS = 1 THEN 1 ELSE 2 END          AS current_status_id,
    ''No''                                                AS auto_renewal,
    NULL                                                AS years,
    16.00                                               AS vat_per,
    0                                                   AS flat_rate_vat
FROM CHABRINS_NEW.dbo.CHBLOCKS b
WHERE b.BLOCKID IS NOT NULL;
';

-- ============================================================================
-- _019  vw_cb_landlord_account_019  (CHLACC -> cb_landlord_account, 128,423 rows)
-- ============================================================================
-- Landlord account ledger: debit/credit entries for landlord accounts.
-- Links to cb_returns_summary via return_code (RETURNID) when entry
-- originates from a return. Direct charges have NULL return_code.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_landlord_account_019 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY l.YEARR, l.MONTHH, l.LORDID, l.ENTRYNU)  AS id,
    l.YEARR                                             AS year,
    l.MONTHH                                            AS month,
    RTRIM(LTRIM(l.LORDID))                              AS landlord_code,
    RTRIM(LTRIM(l.LFNAME))                              AS landlord_name,
    RTRIM(LTRIM(l.HOUSEID))                             AS property_code,
    RTRIM(LTRIM(l.GTTCNUM))                             AS transaction_number,
    RTRIM(LTRIM(l.ENTRYID))                             AS entry_type_code,
    l.ENTRYNU                                           AS entry_sequence,
    RTRIM(LTRIM(l.ENTRYDE))                             AS entry_description,
    l.ENTRYDA                                           AS entry_date,
    ISNULL(l.AMTDR, 0)                                  AS debit_amount,
    ISNULL(l.AMTCR, 0)                                  AS credit_amount,
    ISNULL(l.AMTTOT, 0)                                 AS total_amount,
    l.ACCSTAT                                           AS account_status,
    RTRIM(LTRIM(l.PAYREF))                              AS payment_ref,
    RTRIM(LTRIM(l.PAYREFT))                             AS payment_ref_type,
    ISNULL(l.INTRATE, 0)                                AS interest_rate,
    RTRIM(LTRIM(l.AUDTUSR))                             AS audit_user,
    RTRIM(LTRIM(l.ENTPART))                             AS entry_particulars,
    RTRIM(LTRIM(l.PVNO))                                AS pv_number,
    RTRIM(LTRIM(l.FINACC))                              AS gl_account,
    RTRIM(LTRIM(l.VENDORID))                            AS vendor_id,
    ISNULL(l.RECOVD, 0)                                 AS is_recovered,
    l.RECOYR                                            AS recovery_year,
    l.RECOMN                                            AS recovery_month,
    RTRIM(LTRIM(l.RETTRANS))                            AS return_transaction,
    RTRIM(LTRIM(l.RETURNID))                            AS return_code,
    l.client_id                                         AS client_id,
    l.property_id                                       AS property_id
FROM CHABRINS_NEW.dbo.CHLACC l
WHERE l.LORDID IS NOT NULL;
';

-- ============================================================================
-- _020  vw_cb_money_recoveries_020  (CHRACT -> cb_money_recoveries, 221,468 rows)
-- ============================================================================
-- Returns account transactions: both CREDIT entries (rent/charges collected for
-- landlord) and DEBIT/DEDUCTION entries (recoveries deducted from landlord:
-- SAT DEPOSIT, PAID OWNER, ABSCONDED, OVERPAID, PAID VACANT).
-- TRACLASS = CR (218,213 credits) | DR (3,255 deductions/recoveries).
-- Links to cb_returns_summary via return_id (CHRHMS.RETURNID = CHRACT.RETNPRD).
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_money_recoveries_020 AS
SELECT
    r.recovery_id                                       AS id,
    r.GTTCNUM                                           AS transaction_number,
    r.property_id                                       AS property_id,
    r.client_id                                         AS client_id,
    r.unit_id                                           AS unit_id,
    r.tenant_id                                         AS tenant_id,
    r.return_id                                         AS return_id,
    r.zone_id                                           AS zone_id,
    r.field_officer_id                                  AS field_officer_id,
    r.zone_supervisor_id                                AS zone_supervisor_id,
    r.zone_manager_id                                   AS zone_manager_id,
    r.UNITNO                                            AS unit_code,
    r.ACYEAR                                            AS year,
    r.ACMNTH                                            AS month,
    r.RETNPRD                                           AS return_period,
    r.RTYPE                                             AS return_type,
    r.TENNAME                                           AS tenant_name,
    r.TENSTAT                                           AS tenant_status,
    r.TRATYPE                                           AS transaction_type,
    r.TRACATEG                                          AS transaction_category,
    r.TRACLASS                                          AS transaction_class,
    r.TRASRC                                            AS transaction_source,
    r.PYMOD                                             AS payment_mode,
    r.TRANSREF                                          AS transaction_ref,
    r.TRANSDAT                                          AS transaction_date,
    r.TRANSDESC                                         AS transaction_description,
    ISNULL(r.AMTPAYBL, 0)                               AS amount_payable,
    ISNULL(r.AMTDR, 0)                                  AS debit_amount,
    ISNULL(r.AMTCR, 0)                                  AS credit_amount,
    r.RPERIOD                                           AS return_period_num,
    r.PPERIOD                                           AS payment_period_num,
    ISNULL(r.PRRATE, 0)                                 AS prorate_rate,
    ISNULL(r.AMTRETRN, 0)                               AS amount_returned,
    ISNULL(r.AMTBAL, 0)                                 AS balance_amount,
    CAST(CASE WHEN r.VATABL = ''Yes'' THEN 1 ELSE 0 END AS BIT)  AS is_vatable,
    CAST(CASE WHEN r.COMMBL = ''Yes'' THEN 1 ELSE 0 END AS BIT)  AS is_commissionable,
    r.DEDPNT2                                           AS deduction_point_2,
    r.PLSTATUS                                          AS processing_status,
    r.RSTATUS                                           AS record_status,
    CAST(ISNULL(r.LOCKD, 0) AS BIT)                     AS is_locked
FROM CHABRINS_NEW.dbo.CHRACT r
WHERE r.recovery_id IS NOT NULL;
';

-- ============================================================================
-- _021  vw_cb_payment_vouchers_headers_new_021  (CHPVHH -> cb_payment_vouchers_headers_new, 49,412 rows)
-- ============================================================================
-- Current/active landlord payment voucher headers (2024-2026).
-- PVNO links to CHRHMS.VOUCHERNO. VENDORID is landlord code (LAN-xxxxx).
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_payment_vouchers_headers_new_021 AS
SELECT
    h.PVSNO                                             AS voucher_sequence,
    h.PVNO                                              AS voucher_number,
    h.PYEAR                                             AS year,
    h.PMONTH                                            AS month,
    h.PVDATE                                            AS voucher_date,
    h.PVDESC                                            AS description,
    h.VENDORID                                          AS vendor_id,
    h.VENDNAM                                           AS vendor_name,
    h.VENDTYP                                           AS vendor_type,
    ISNULL(h.AMTDR, 0)                                  AS debit_amount,
    ISNULL(h.AMTCR, 0)                                  AS credit_amount,
    ISNULL(h.PVENTR, 0)                                 AS entry_count,
    h.PVSTAT                                            AS voucher_status,
    ISNULL(h.STDEL, 0)                                  AS is_deleted,
    h.PREPBY                                            AS prepared_by,
    h.CHECKSTT                                          AS checked_status,
    h.CHECKBY                                           AS checked_by,
    h.AUTHSTT                                           AS authorized_status,
    h.AUTHBY                                            AS authorized_by,
    ISNULL(h.STPRINT, 0)                                AS is_printed,
    h.CHECKDAT                                          AS checked_date,
    h.AUTHDAT                                           AS authorized_date
FROM CHABRINS_NEW.dbo.CHPVHH h
WHERE h.PVNO IS NOT NULL;
';

-- ============================================================================
-- _022  vw_cb_payment_vouchers_headers_old_022  (CHPVH -> cb_payment_vouchers_headers_old, 1,777 rows)
-- ============================================================================
-- Legacy payment voucher headers (2009-2017). No overlap with CHPVHH.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_payment_vouchers_headers_old_022 AS
SELECT
    h.YEARR                                             AS year,
    h.MONTHH                                            AS month,
    h.ENTDAT                                            AS entry_date,
    h.PVSNO                                             AS voucher_sequence,
    h.PVNO                                              AS voucher_number,
    h.PVDESC                                            AS description,
    h.BANKN                                             AS bank_name,
    h.BANKB                                             AS bank_branch,
    h.ACCNO                                             AS account_number,
    h.PAYEEN                                            AS payee_name,
    ISNULL(h.AMTFIG, 0)                                 AS amount_figure,
    h.PVSTAT                                            AS voucher_status,
    h.CHEQUE                                            AS cheque_number,
    ISNULL(h.AMTDR, 0)                                  AS debit_amount,
    ISNULL(h.AMTCR, 0)                                  AS credit_amount,
    ISNULL(h.AMTVAT, 0)                                 AS vat_amount,
    ISNULL(h.AMTNET, 0)                                 AS net_amount,
    ISNULL(h.STPAID, 0)                                 AS is_paid,
    ISNULL(h.STPRINT, 0)                                AS is_printed,
    ISNULL(h.STDEL, 0)                                  AS is_deleted,
    h.PREPBY                                            AS prepared_by,
    h.PREPDAT                                           AS prepared_date,
    h.CHECKBY                                           AS checked_by,
    h.CHECKDAT                                          AS checked_date,
    h.AUTHBY                                            AS authorized_by,
    h.AUTHDAT                                           AS authorized_date,
    ISNULL(h.CHECKSTT, 0)                               AS checked_status,
    ISNULL(h.AUTHSTT, 0)                                AS authorized_status,
    h.ENTCNT                                            AS entry_count
FROM CHABRINS_NEW.dbo.CHPVH h
WHERE h.PVNO IS NOT NULL;
';

-- ============================================================================
-- _023  vw_cb_payment_vouchers_lines_new_023  (CHPVDD -> cb_payment_vouchers_lines_new, 81,773 rows)
-- ============================================================================
-- Current/active payment voucher detail lines (2024-2026).
-- Links to cb_payment_vouchers_headers_new via voucher_number.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_payment_vouchers_lines_new_023 AS
SELECT
    d.PVNO                                              AS voucher_number,
    d.ENTRYNO                                           AS entry_number,
    d.ENYEAR                                            AS entry_year,
    d.ENMNTH                                            AS entry_month,
    d.ACCNUM                                            AS account_number,
    d.ACCBNK                                            AS account_bank,
    d.CHQNO                                             AS cheque_number,
    d.CHQTYP                                            AS cheque_type,
    d.BLOCKID                                           AS property_code,
    d.DRCR                                              AS dr_cr,
    d.ENTID                                             AS entry_id,
    d.ENTDESC                                           AS entry_description,
    d.PVAPPR                                            AS is_approved,
    ISNULL(d.AMOUNT, 0)                                 AS amount,
    ISNULL(d.INRATE, 0)                                 AS interest_rate,
    d.GLACC                                             AS gl_account,
    d.GTTCNUM                                           AS transaction_number,
    d.PAYEEN                                            AS payee_name,
    d.CRACC                                             AS credit_account,
    d.CRMNTH                                            AS credit_month,
    d.CRYEAR                                            AS credit_year,
    d.SRCTRA                                            AS source_transaction,
    ISNULL(d.CHQPSTD, 0)                                AS cheque_posted,
    ISNULL(d.CHQOPEN, 0)                                AS cheque_open,
    d.CHQDATE                                           AS cheque_date,
    d.ENDDATE                                           AS end_date,
    d.BLKDSC                                            AS property_description,
    d.UNLCKBY                                           AS unlocked_by,
    d.UNLCKDAT                                          AS unlocked_date,
    d.UNLCKRS                                           AS unlock_reason
FROM CHABRINS_NEW.dbo.CHPVDD d
WHERE d.PVNO IS NOT NULL;
';

-- ============================================================================
-- _024  vw_cb_payment_vouchers_lines_old_024  (CHPVD -> cb_payment_vouchers_lines_old, 1,906 rows)
-- ============================================================================
-- Legacy payment voucher detail lines (2009-2017).
-- Links to cb_payment_vouchers_headers_old via voucher_number.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_payment_vouchers_lines_old_024 AS
SELECT
    d.PVNO                                              AS voucher_number,
    d.ENTNUM                                            AS entry_number,
    d.GTTCNUM                                           AS transaction_number,
    d.ENTRYID                                           AS entry_id,
    d.ENTDES                                            AS entry_description,
    d.ENTDAT                                            AS entry_date,
    d.ENTPART                                           AS entry_particulars,
    d.PAYEEN                                            AS payee_name,
    d.PAYEEID                                           AS payee_id,
    d.PAYEETY                                           AS payee_type,
    ISNULL(d.AMTDR, 0)                                  AS debit_amount,
    ISNULL(d.AMTCR, 0)                                  AS credit_amount,
    ISNULL(d.AMTVAT, 0)                                 AS vat_amount,
    ISNULL(d.AMTNET, 0)                                 AS net_amount,
    d.CHEQUENO                                          AS cheque_number,
    d.SRCACC                                            AS source_account,
    d.SRCACN                                            AS source_account_name,
    d.BLOCKID                                           AS property_code,
    d.UNITNO                                            AS unit_code,
    d.BANKACC                                           AS bank_account,
    ISNULL(d.STPOST, 0)                                 AS is_posted,
    d.PYEAR                                             AS payment_year,
    d.PMONTH                                            AS payment_month
FROM CHABRINS_NEW.dbo.CHPVD d
WHERE d.PVNO IS NOT NULL;
';

-- ============================================================================
-- _026  vw_cb_landlord_charge_pairs_026  (CHCDC -> cb_landlord_charge_pairs, 12 rows)
-- ============================================================================
-- Defines which service charge is automatically paired with a main charge.
-- e.g. CARETAKER SALARY (0012) always incurs CARETAKER SALARY CHARGE (0015),
-- LOAN (0020) always incurs CHEQUE CHARGES (0014)
-- Both PCHRGID and DCHRGID are FKs to sys_set_invoiceable_items.billing_code (via CHCHRS/_008)


EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cb_landlord_charge_pairs_026 AS
SELECT
    c.item_id                                           AS id,
    RTRIM(LTRIM(c.PCHRGID))                             AS primary_charge_code,
    RTRIM(LTRIM(c.DCHRGID))                             AS debit_charge_code
FROM CHABRINS_NEW.dbo.CHCDC c
WHERE c.PCHRGID IS NOT NULL;
';

-- ============================================================================
-- _027  vw_staff_property_mappings_027  (CHOFALL -> staff_property_mappings, 8,202 rows)
-- ============================================================================
-- Officer-to-property allocation history (field officer / senior field officer /
-- property manager / zone manager roles). Multiple rows per OFFID+BLOCKID pair
-- are expected -- each row is a distinct allocation event (re-assignments keep
-- their own ALLDATE), not a single current-state record.
-- officer_id  : OFFID -> CHOFFICERS.officer_id (LEFT JOIN, ~76% match; CHOFALL
--               has 24% OFFID codes with no matching officer record)
-- property_id : BLOCKID -> CHBLOCKS.property_id (LEFT JOIN, ~96% match)
-- property_code is kept as raw text alongside property_id so the mapping stays
-- readable even when the property_id join misses.
-- allocated_by (ALLBY) does NOT match any CHOFFICERS.OFFID (0% join rate) --
-- kept as raw text, not resolved to an FK. Needs manual review post-migration
-- if a hard link to the allocating user is required.
-- NOTE: ALLTYPE has a dirty variant ''Senior Field Officer2'' (likely a data-entry
-- duplicate of ''Senior Field Officer'') -- migrated as-is, not silently corrected.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_staff_property_mappings_027 AS
SELECT
    o.officer_id                                        AS officer_id,
    -- PRE-STAMPED: officer_id from CHOFFICERS via OFFID
    b.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKID
    RTRIM(LTRIM(f.BLOCKID))                             AS property_code,
    RTRIM(LTRIM(f.ALLBY))                               AS allocated_by,
    -- NOTE: does not join to CHOFFICERS.OFFID -- kept as raw text
    f.ALLDATE                                           AS allocated_on,
    RTRIM(LTRIM(f.ALLTYPE))                             AS allocation_type,
    RTRIM(LTRIM(f.MSTATUS))                             AS status
FROM CHABRINS_NEW.dbo.CHOFALL f
LEFT JOIN CHABRINS_NEW.dbo.CHOFFICERS o
  ON RTRIM(LTRIM(o.OFFID)) = RTRIM(LTRIM(f.OFFID))
LEFT JOIN CHABRINS_NEW.dbo.CHBLOCKS b
  ON RTRIM(LTRIM(b.BLOCKID)) = RTRIM(LTRIM(f.BLOCKID))
WHERE f.OFFID IS NOT NULL AND f.BLOCKID IS NOT NULL;
';

-- ============================================================================
-- _028  vw_vendors_028  (CHVENDS -> vendors, 1,787 rows -> 1,091 deduplicated)
-- ============================================================================
-- SOURCE: CHVENDS is a bank/address CHANGE LOG, not a clean vendor master --
-- 1,787 rows cover only 1,091 distinct VENDORID values (some vendors have up
-- to 3 historical revisions). Deduplicated to one row per VENDORID: prefer the
-- revision with a populated ACCNUM (most complete bank details), then the most
-- recent LASTMNT.
-- client_id: only resolved for VENDTYP = ''LAN'' (landlord) vendors. VENDORID
--   is often double-prefixed (''LAN-LAN-00739'') vs CHLRDS.LORDID (''LAN-00739'') --
--   collapsed before matching. ~99% match rate for LAN-type vendors.
-- taxable (TAXABLE): values in the live data are large inconsistent integers,
--   not a usable 0/1 flag -- migrated anyway (not dropped), meaning TBD.
-- NOTE: CHVENDS.BANKACC actually holds the bank NAME in this data (not an
--   account code) -- mapped to bank_name, not account_number.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_vendors_028 AS
WITH ranked AS (
    SELECT
        v.*,
        ROW_NUMBER() OVER (
            PARTITION BY RTRIM(LTRIM(v.VENDORID))
            ORDER BY
                CASE WHEN NULLIF(RTRIM(LTRIM(v.ACCNUM)), '''') IS NOT NULL THEN 0 ELSE 1 END,
                v.LASTMNT DESC
        ) AS rn
    FROM CHABRINS_NEW.dbo.CHVENDS v
    WHERE v.VENDORID IS NOT NULL AND RTRIM(LTRIM(v.VENDORID)) <> ''''
)
SELECT
    RTRIM(LTRIM(r.VENDORID))                            AS vendor_code,
    RTRIM(LTRIM(r.VENDNAM))                             AS vendor_name,
    RTRIM(LTRIM(r.VENDTYP))                             AS vendor_type,
    c.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id from CHLRDS via VENDORID (LAN-type only)
    RTRIM(LTRIM(r.ADDRESS1))                            AS address_line_1,
    RTRIM(LTRIM(r.ADDRESS2))                            AS address_line_2,
    RTRIM(LTRIM(r.ADDRESS3))                            AS address_line_3,
    RTRIM(LTRIM(r.ADDRESS4))                            AS address_line_4,
    RTRIM(LTRIM(r.BANKACC))                             AS bank_name,
    RTRIM(LTRIM(r.BANKBRC))                             AS bank_branch,
    RTRIM(LTRIM(r.ACCNUM))                              AS account_number,
    ISNULL(r.VENDBAL, 0)                                AS current_balance,
    r.LASTMNT                                           AS last_maintained_at,
    RTRIM(LTRIM(r.AUDTUSR))                             AS audit_user,
    ''Active''                                            AS status,
    r.TAXABLE                                           AS taxable
FROM ranked r
LEFT JOIN CHABRINS_NEW.dbo.CHLRDS c
  ON RTRIM(LTRIM(r.VENDTYP)) = ''LAN''
 AND REPLACE(RTRIM(LTRIM(c.LORDID)), '' '', '''') = REPLACE(RTRIM(LTRIM(r.VENDORID)), ''LAN-LAN-'', ''LAN-'')
WHERE r.rn = 1;
';

-- ============================================================================
-- _029  vw_landlord_property_mappings_029  (CHLRH -> landlord_property_mappings, 1,812 rows)
-- ============================================================================
-- Landlord-to-property links. Near-1:1 (only 1 of 1,812 BLOCKIDs has more
-- than one distinct LORDID) -- this is not an ownership-history table.
-- client_id   : LORDID -> CHLRDS.client_id (LEFT JOIN, 99.7% match)
-- property_id : BLOCKID -> CHBLOCKS.property_id (LEFT JOIN, 100% match)
-- landlord_code is kept as raw text alongside client_id deliberately:
-- IMPORTANT -- this table disagrees with CHBLOCKS.LLORDID (the landlord
-- already stamped on the property, used by vw_sys_pm_properties_001 to set
-- client_id) in ~7.7% of rows (140 / 1,812), after normalising the
-- inconsistent ''LAN-'' prefixing on both sides. Matches the note already in
-- chabrins_to_postgres_views.sql: "Use to verify client_id assignment."
-- landlord_code lets a human reconcile the 140 mismatches against
-- sys_pm_properties.client_id post-migration -- do NOT assume this view is
-- authoritative over the existing property.client_id without that review.
-- entry_id (CHLRH.ENTRYID) is 100% NULL in current data -- migrated anyway
-- (not dropped), kept for future use.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_landlord_property_mappings_029 AS
SELECT
    c.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id from CHLRDS via LORDID
    b.property_id                                       AS property_id,
    -- PRE-STAMPED: property_id from CHBLOCKS via BLOCKID
    RTRIM(LTRIM(h.BLOCKID))                             AS property_code,
    RTRIM(LTRIM(h.LORDID))                              AS landlord_code,
    RTRIM(LTRIM(h.MSTATUS))                             AS status,
    h.ENTRYID                                           AS entry_id
FROM CHABRINS_NEW.dbo.CHLRH h
LEFT JOIN CHABRINS_NEW.dbo.CHLRDS c
  ON REPLACE(RTRIM(LTRIM(c.LORDID)), '' '', '''') = REPLACE(RTRIM(LTRIM(h.LORDID)), ''LAN-LAN-'', ''LAN-'')
LEFT JOIN CHABRINS_NEW.dbo.CHBLOCKS b
  ON RTRIM(LTRIM(b.BLOCKID)) = RTRIM(LTRIM(h.BLOCKID))
WHERE h.LORDID IS NOT NULL AND h.BLOCKID IS NOT NULL;
';

-- ============================================================================
-- _030  vw_cheque_register_030  (CHCHQ -> cheque_register, 53,879 rows)
-- ============================================================================
-- No stamping needed: CHCHQ.cheque_id is ALREADY in generate_migration_ids_light.py
-- (added earlier for CHCHD''s FK stamp) -- this table just was not selected as an
-- ''id'' by any view yet. TARGET: cheque_register (new clean DDL, id auto-generated
-- by Postgres). NOTE: views cannot carry ORDER BY in SQL Server -- when this data
-- is loaded, INSERT INTO cheque_register (...) SELECT ... FROM this view ORDER BY
-- cheque_no, so Postgres id enumeration stays aligned with the pre-stamped
-- CHCHQ.cheque_id used internally by the SQL-side pipeline (for CHCHD''s FK, later).
-- cheque_book_no / is_reconciled / reconciled_at / reconciled_by /
-- reconciliation_status are 100% NULL in the current source data but are
-- migrated anyway (not dropped) -- kept for future use once populated.
-- used_flag (CHQUSD) has a 3rd value (2, 20 rows) with no documented meaning --
-- kept as a raw small int (0/1/2), not forced into a boolean.
-- leaf_status (TYPCHQ) is misleadingly named "cheque type" in source but is
-- actually an availability state (''Open'' vs ''Closed''), not a type -- mapped as-is.
-- payment_voucher_number (PAYVOUC) matches cb_payment_vouchers_headers_new
-- (CHPVHH.PVNO) at ~65% (only used cheques have a linked voucher) -- kept as raw
-- text, not resolved to an FK.
-- payee_code (PAYEEID) holds landlord codes (LAN-xxxxx) for ~19% of rows --
-- kept as raw text, not resolved to client_id.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cheque_register_030 AS
SELECT
    RTRIM(LTRIM(q.CHQNO))                               AS cheque_no,
    RTRIM(LTRIM(q.CHQBKNO))                             AS cheque_book_no,
    RTRIM(LTRIM(q.CHQBNK))                              AS bank_name,
    RTRIM(LTRIM(q.CHQACC))                              AS bank_account,
    q.CHQUSD                                            AS used_flag,
    RTRIM(LTRIM(q.CHQUSBY))                             AS used_by,
    q.CHQUDAT                                           AS used_date,
    RTRIM(LTRIM(q.PAYVOUC))                             AS payment_voucher_number,
    RTRIM(LTRIM(q.PAYEEN))                              AS payee_name,
    RTRIM(LTRIM(q.PAYEEID))                             AS payee_code,
    ISNULL(q.CHQAMT, 0)                                 AS amount,
    CASE WHEN q.CHQSTAT = 1 THEN 1 ELSE 0 END           AS is_cancelled,
    RTRIM(LTRIM(q.CANBY))                               AS cancelled_by,
    RTRIM(LTRIM(q.CANREAS))                             AS cancel_reason,
    q.CANDAT                                            AS cancelled_at,
    RTRIM(LTRIM(q.TYPCHQ))                              AS leaf_status,
    q.CHQPDAT                                           AS paid_date,
    RTRIM(LTRIM(q.CHQPOST))                             AS is_posted,
    RTRIM(LTRIM(q.CHQRMKS))                             AS remarks,
    RTRIM(LTRIM(q.CHQRECON))                            AS is_reconciled,
    q.RECNDAT                                           AS reconciled_at,
    RTRIM(LTRIM(q.RECNBY))                              AS reconciled_by,
    RTRIM(LTRIM(q.RECNSTAT))                            AS reconciliation_status
FROM CHABRINS_NEW.dbo.CHCHQ q
WHERE q.CHQNO IS NOT NULL;
';

-- ============================================================================
-- _031  vw_cheque_register_details_031  (CHCHD -> cheque_register_details, 43,301 rows)
-- ============================================================================
-- No stamping needed for this view either -- CHQNO+CHQACC -> CHCHQ.cheque_id
-- and PAYVOUC -> voucher_id are ALREADY in generate_migration_ids_light.py
-- (Phase 3, added earlier). Not used here since cheque_register/cheque_register_details
-- both use raw text keys (no stable transferable id on either side) -- see _030.
-- ⚠ BUG FOUND in the existing script: the pre-written CHCHD.voucher_id stamp
--   joins PAYVOUC to CHBCTH.BTCHID, but PAYVOUC does NOT match CHBCTH at all
--   (0% join rate, verified against live data). PAYVOUC actually matches
--   CHPVHH.PVNO (payment vouchers, new) at ~99.9%. The existing FK-stamping
--   code for CHCHD.voucher_id in generate_migration_ids_light.py will silently
--   produce an all-NULL voucher_id column if run as-is. Flagging, not fixing --
--   this script predates this session and touches a shared pipeline step.
-- entry_sequence (ENTRYID) is a per-voucher line number, not a globally unique
-- row id -- (PAYVOUC, ENTRYID) is only ~99.8% unique, not enforced as a
-- constraint given the near-miss.
-- Columns below are 100% NULL in current source data but are migrated anyway
-- (not dropped): ENTDESC, CODEFT, PAYEEID, PAYENAME, PACCNUM, PBANK, PBANKBRA,
-- EFTENTNO, CHKSTAT, CHKBY, CHKDAT, VERISTAT, VERIBY, VERIDAT.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_cheque_register_details_031 AS
SELECT
    RTRIM(LTRIM(d.CHQNO))                               AS cheque_no,
    RTRIM(LTRIM(d.CHQACC))                              AS bank_account,
    RTRIM(LTRIM(d.PAYVOUC))                             AS payment_voucher_number,
    d.ENTRYID                                           AS entry_sequence,
    ISNULL(d.ENTRYAMT, 0)                               AS entry_amount,
    RTRIM(LTRIM(d.ENTDESC))                             AS entry_description,
    RTRIM(LTRIM(d.CODEFT))                              AS eft_code,
    RTRIM(LTRIM(d.PAYEEID))                             AS payee_code,
    RTRIM(LTRIM(d.PAYENAME))                            AS payee_name,
    RTRIM(LTRIM(d.PACCNUM))                             AS payee_account_number,
    RTRIM(LTRIM(d.PBANK))                               AS payee_bank,
    RTRIM(LTRIM(d.PBANKBRA))                            AS payee_bank_branch,
    d.EFTENTNO                                          AS eft_entry_number,
    RTRIM(LTRIM(d.CHKSTAT))                             AS checked_status,
    RTRIM(LTRIM(d.CHKBY))                               AS checked_by,
    d.CHKDAT                                            AS checked_at,
    RTRIM(LTRIM(d.VERISTAT))                            AS verified_status,
    RTRIM(LTRIM(d.VERIBY))                              AS verified_by,
    d.VERIDAT                                           AS verified_at,
    RTRIM(LTRIM(d.ENTRYBY))                             AS entered_by
FROM CHABRINS_NEW.dbo.CHCHD d
WHERE d.CHQNO IS NOT NULL;
';

-- ============================================================================
-- _032  vw_landlord_advance_repayment_schedule_032  (CHLNTR -> landlord_advance_repayment_schedule, 5,593 rows)
-- ============================================================================
-- Per-period amortised loan/advance repayment ledger per landlord. One row per
-- transaction line (Principal or Interest); a landlord can have several loan
-- series over time (LNSERIE) -- one landlord seen with 12 distinct series.
-- client_id : LORDID -> CHLRDS.client_id (LEFT JOIN, 100% match).
-- loan_amount (LNAMT) vs amount (AMOUNT): differ on ~20% of rows -- not
-- redundant, kept as two separate columns rather than collapsed into one.
-- total_amount (TOTALAMT) / balance (BALANC): appear to be a running
-- opening/closing balance pair but the exact semantics were not fully
-- reverse-engineered -- mapped as-is under their source names rather than
-- guessed at with invented column names.
-- transaction_no (TRANSNO) is 100% unique across all 5,593 rows -- the
-- natural row key (used as the ORDER BY for schedule_id in
-- generate_migration_ids_light.py, alongside LORDID/LNSERIE/REPAYNO).
-- is_paid (STPAID) is 100% = 1 in the current data -- mapped to boolean true,
-- not dropped, but note there is no observed ''unpaid'' case to validate against.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_landlord_advance_repayment_schedule_032 AS
SELECT
    c.client_id                                         AS client_id,
    -- PRE-STAMPED: client_id from CHLRDS via LORDID
    RTRIM(LTRIM(l.LORDID))                              AS landlord_code,
    l.LNSERIE                                           AS loan_series_no,
    l.LNAMT                                             AS loan_amount,
    ISNULL(l.AMOUNT, 0)                                 AS amount,
    l.REPDATE                                           AS repayment_date,
    l.INTRATE                                           AS interest_rate,
    l.MONTHH                                            AS repayment_month,
    l.YEARR                                             AS repayment_year,
    ISNULL(l.INTRESTAMT, 0)                             AS interest_amount,
    l.TOTALAMT                                          AS total_amount,
    l.BALANC                                            AS balance,
    l.REPAYNO                                           AS repayment_no,
    CASE WHEN l.STPAID = 1 THEN 1 ELSE 0 END            AS is_paid,
    RTRIM(LTRIM(l.DOCNO))                               AS document_reference,
    RTRIM(LTRIM(l.TRANSNO))                             AS transaction_no,
    RTRIM(LTRIM(l.TRANTYPE))                            AS transaction_type
FROM CHABRINS_NEW.dbo.CHLNTR l
LEFT JOIN CHABRINS_NEW.dbo.CHLRDS c
  ON REPLACE(RTRIM(LTRIM(c.LORDID)), '' '', '''') = REPLACE(RTRIM(LTRIM(l.LORDID)), ''LAN-LAN-'', ''LAN-'')
WHERE l.LORDID IS NOT NULL AND l.TRANSNO IS NOT NULL;
';

-- ============================================================================
-- HR MODULE — merged in from peter_hr_mods_final.sql (2026-08-05)
-- ============================================================================
-- peter_hr_mods_final.sql was the latest/combined HR views file (superseding
-- hr_views_mod.sql), originally written against database CHABRINS with bare
-- (unqualified) table names under its own "USE CHABRINS;" context. Merged
-- here with two changes only:
--   1) Views renumbered _P_01..._P_24 -> _033..._056 (continuing this file's
--      numbering), including all internal cross-view references.
--   2) All 13 source tables (CHDEPT, CHDESN, CHBANK, CHBBRA, CHSTFF, CHSNOK,
--      CHPREDS, CHPTABS, CHPRDS, CHPYSL, CHLOAN, CHP9A, CHSCNTS) fully
--      qualified as CHABRINS_NEW.dbo.* -- confirmed present with live data in
--      CHABRINS_NEW (the source CHABRINS database is currently in
--      RECOVERY_PENDING state and inaccessible), so the whole file now runs
--      as one consistent batch against CHABRINS_NEW.
-- Two pre-existing stale comment references in the original source were
-- corrected while merging (not logic changes, comments only):
--   - _051''s "resolved from BANKBRAN via _014" corrected to "_036"
--   - _054''s "For each contract period (from _P_03)" corrected to "_053"
--     (both referenced the wrong view number in the original file).
-- No other logic, joins, or column mappings were altered from the source.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_departments_033 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DEPTCODE)   AS id,
    GETDATE()                                AS date_time,
    DEPTNAME                                 AS department_name,
    DEPTCODE                                 AS mssql_dept_code
FROM CHABRINS_NEW.dbo.CHDEPT;
';

-- ============================================================================
-- _034  vw_sys_set_staff_positions_034
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_staff_positions_034 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY DESIGNCODE) AS id,
    GETDATE()                                AS date_time,
    DESGINNAME                               AS position_name,
    NULL                                     AS basic_salary,
    NULL                                     AS salary_account_id,
    1                                        AS current_status_id,
    NULL                                     AS reports_to_id,
    DESIGNCODE                               AS mssql_design_code
FROM CHABRINS_NEW.dbo.CHDESN;
';

-- ============================================================================
-- _035  vw_sys_set_banks_035
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_banks_035 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY BANKCODE)   AS id,
    GETDATE()                                AS date_time,
    BANKNAME                                 AS bank_name,
    NULL                                     AS address,
    1                                        AS current_status_id,
    BANKCODE                                 AS mssql_bank_code
FROM CHABRINS_NEW.dbo.CHBANK;
';

-- ============================================================================
-- _036  vw_sys_set_banks_branches_036
--       References: vw_sys_set_banks_035
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_set_banks_branches_036 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY BANKCODE, BRANCODE) AS id,
    GETDATE()                                        AS date_time,
    BRANCODE                                         AS branch_code,
    BRANNAME                                         AS branch_name,
    bk.id                                            AS bank_id,
    1                                                AS current_status_id,
    br.BANKCODE                                      AS mssql_bank_code,
    br.BRANCODE                                      AS mssql_branch_code
FROM CHABRINS_NEW.dbo.CHBBRA br
LEFT JOIN vw_sys_set_banks_035 bk ON br.BANKCODE = bk.mssql_bank_code;
';

-- ============================================================================
-- _037  vw_sys_staff_037
--       References: vw_sys_set_departments_033
--                   vw_sys_set_staff_positions_034
--                   vw_sys_set_banks_035
--                   vw_sys_set_banks_branches_036
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_staff_037 AS
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
FROM CHABRINS_NEW.dbo.CHSTFF s
LEFT JOIN vw_sys_set_departments_033       d  ON s.DEPTCODE = d.mssql_dept_code
LEFT JOIN vw_sys_set_staff_positions_034   p  ON s.DESIGN   = p.mssql_design_code
LEFT JOIN vw_sys_set_banks_035             b  ON s.BANKCODE = b.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches_036    br ON s.BANKBRAN = br.mssql_branch_code
                                              AND s.BANKCODE = br.mssql_bank_code
OUTER APPLY (
    SELECT TOP 1 NOKNAM
    FROM CHABRINS_NEW.dbo.CHSNOK n
    WHERE n.EMPNUMB = s.EMPNUMB
      AND n.NOKTYPE = ''Next of kin''
    ORDER BY n.NOKNAM
) nok;
';

-- ============================================================================
-- _038  vw_sys_staff_next_of_kin_038
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_staff_next_of_kin_038 AS
SELECT
    ROW_NUMBER() OVER (ORDER BY n.EMPNUMB, n.NOKTYPE) AS id,
    n.EMPNUMB   AS staff_number,
    n.NOKNAM    AS kin_name,
    n.NOKRELA   AS relationship,
    n.NOKTYPE   AS kin_type,
    n.NOKADDR   AS kin_address,
    n.NOKCITY   AS kin_city,
    n.NOKTEL    AS kin_phone
FROM CHABRINS_NEW.dbo.CHSNOK n;
';

-- ============================================================================
-- _039  vw_sys_hr_payroll_elements_039
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_elements_039 AS
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
FROM CHABRINS_NEW.dbo.CHPREDS;
';

-- ============================================================================
-- _040  vw_um_tax_bands_040
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_um_tax_bands_040 AS
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
FROM CHABRINS_NEW.dbo.CHPTABS t
WHERE t.PYEAR  = (SELECT MAX(PYEAR) FROM CHABRINS_NEW.dbo.CHPTABS)
  AND t.PMONTH = (
      SELECT MAX(PMONTH)
      FROM CHABRINS_NEW.dbo.CHPTABS
      WHERE PYEAR = (SELECT MAX(PYEAR) FROM CHABRINS_NEW.dbo.CHPTABS)
  );
';

-- ============================================================================
-- _041  vw_sys_hr_payroll_calendar_041
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_calendar_041 AS
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
FROM CHABRINS_NEW.dbo.CHPRDS;
';

-- ============================================================================
-- _042  vw_sys_hr_payroll_periods_042
--       References: vw_sys_hr_payroll_calendar_041
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_periods_042 AS
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
FROM CHABRINS_NEW.dbo.CHPRDS p
CROSS APPLY (
    SELECT TOP 1 id
    FROM vw_sys_hr_payroll_calendar_041 cal
    WHERE cal.mssql_year  = p.PRYEAR
      AND cal.mssql_month = p.PRMNTH
) cal;
';

-- ============================================================================
-- _043  vw_sys_hr_payroll_summary_043
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payroll_summary_043 AS
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
    FROM CHABRINS_NEW.dbo.CHPYSL
) e
LEFT JOIN vw_sys_staff_037  staff     ON e.EMPNUMB = staff.staff_number
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL basic      ON e.EMPNUMB = basic.EMPNUMB      AND e.PRYEAR = basic.PRYEAR      AND e.PRMNTH = basic.PRMNTH      AND basic.EDCODE      = ''00001''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL sarr       ON e.EMPNUMB = sarr.EMPNUMB       AND e.PRYEAR = sarr.PRYEAR       AND e.PRMNTH = sarr.PRMNTH       AND sarr.EDCODE       = ''00019''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL actall     ON e.EMPNUMB = actall.EMPNUMB     AND e.PRYEAR = actall.PRYEAR     AND e.PRMNTH = actall.PRMNTH     AND actall.EDCODE     = ''00018''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL nssf       ON e.EMPNUMB = nssf.EMPNUMB       AND e.PRYEAR = nssf.PRYEAR       AND e.PRMNTH = nssf.PRMNTH       AND nssf.EDCODE       = ''00004''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL nhif       ON e.EMPNUMB = nhif.EMPNUMB       AND e.PRYEAR = nhif.PRYEAR       AND e.PRMNTH = nhif.PRMNTH       AND nhif.EDCODE       = ''00005''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL ahl        ON e.EMPNUMB = ahl.EMPNUMB        AND e.PRYEAR = ahl.PRYEAR        AND e.PRMNTH = ahl.PRMNTH        AND ahl.EDCODE        = ''00022''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL paye       ON e.EMPNUMB = paye.EMPNUMB       AND e.PRYEAR = paye.PRYEAR       AND e.PRMNTH = paye.PRMNTH       AND paye.EDCODE       = ''00013''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL helb       ON e.EMPNUMB = helb.EMPNUMB       AND e.PRYEAR = helb.PRYEAR       AND e.PRMNTH = helb.PRMNTH       AND helb.EDCODE       = ''00020''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL socwel     ON e.EMPNUMB = socwel.EMPNUMB     AND e.PRYEAR = socwel.PRYEAR     AND e.PRMNTH = socwel.PRMNTH     AND socwel.EDCODE     = ''00010''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL akiba      ON e.EMPNUMB = akiba.EMPNUMB      AND e.PRYEAR = akiba.PRYEAR      AND e.PRMNTH = akiba.PRMNTH      AND akiba.EDCODE      = ''00009''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL sadv       ON e.EMPNUMB = sadv.EMPNUMB       AND e.PRYEAR = sadv.PRYEAR       AND e.PRMNTH = sadv.PRMNTH       AND sadv.EDCODE       = ''00007''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL absent     ON e.EMPNUMB = absent.EMPNUMB     AND e.PRYEAR = absent.PRYEAR     AND e.PRMNTH = absent.PRMNTH     AND absent.EDCODE     = ''00015''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL secdepo    ON e.EMPNUMB = secdepo.EMPNUMB    AND e.PRYEAR = secdepo.PRYEAR    AND e.PRMNTH = secdepo.PRMNTH    AND secdepo.EDCODE    = ''00017''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL otherded   ON e.EMPNUMB = otherded.EMPNUMB   AND e.PRYEAR = otherded.PRYEAR   AND e.PRMNTH = otherded.PRMNTH   AND otherded.EDCODE   = ''00012''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL totded     ON e.EMPNUMB = totded.EMPNUMB     AND e.PRYEAR = totded.PRYEAR     AND e.PRMNTH = totded.PRMNTH     AND totded.EDNAME     = ''TOTAL DEDUCTIONS''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL net        ON e.EMPNUMB = net.EMPNUMB        AND e.PRYEAR = net.PRYEAR        AND e.PRMNTH = net.PRMNTH        AND net.EDCODE        = ''00014''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL akibashare ON e.EMPNUMB = akibashare.EMPNUMB AND e.PRYEAR = akibashare.PRYEAR AND e.PRMNTH = akibashare.PRMNTH AND akibashare.EDCODE = ''00011''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL upleave    ON e.EMPNUMB = upleave.EMPNUMB    AND e.PRYEAR = upleave.PRYEAR    AND e.PRMNTH = upleave.PRMNTH    AND upleave.EDCODE    = ''00016''
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL neglig     ON e.EMPNUMB = neglig.EMPNUMB     AND e.PRYEAR = neglig.PRYEAR     AND e.PRMNTH = neglig.PRMNTH     AND neglig.EDCODE     = ''00024'';
';

-- ============================================================================
-- _044  vw_sys_hr_payslip_elements_044
--       References: vw_sys_hr_payroll_elements_039
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_payslip_elements_044 AS
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
FROM CHABRINS_NEW.dbo.CHPYSL sl
LEFT JOIN vw_sys_hr_payroll_elements_039 el ON sl.EDCODE = el.mssql_ed_code;
';

-- ============================================================================
-- _045  vw_sys_helb_deductions_045
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_helb_deductions_045 AS
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
FROM CHABRINS_NEW.dbo.CHLOAN l
LEFT JOIN vw_sys_staff_037 staff ON l.LORDID = staff.staff_number;
';

-- ============================================================================
-- _046  vw_sys_hr_employee_amortized_deductions_046
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_employee_amortized_deductions_046 AS
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
FROM CHABRINS_NEW.dbo.CHLOAN l
LEFT JOIN vw_sys_staff_037 staff ON l.LORDID = staff.staff_number;
';

-- ============================================================================
-- _047  vw_sys_hr_p9a_047
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_sys_hr_p9a_047 AS
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
FROM CHABRINS_NEW.dbo.CHP9A p
LEFT JOIN vw_sys_staff_037 staff ON p.EMPNUMB = staff.staff_number;
';

-- ============================================================================
-- _048  vw_rpt_statutory_nssf_048
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_nssf_048 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    staff.nssf_number   AS nssf_number,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS nssf_amount
FROM CHABRINS_NEW.dbo.CHPYSL sl
LEFT JOIN vw_sys_staff_037 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00004'';
';

-- ============================================================================
-- _049  vw_rpt_statutory_shif_049
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_shif_049 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    staff.nhif_number   AS nhif_number,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS shif_amount
FROM CHABRINS_NEW.dbo.CHPYSL sl
LEFT JOIN vw_sys_staff_037 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00005'';
';

-- ============================================================================
-- _050  vw_rpt_statutory_housing_levy_050
--       References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_rpt_statutory_housing_levy_050 AS
SELECT
    staff.id            AS staff_id,
    sl.EMPNUMB          AS staff_number,
    staff.full_names    AS staff_name,
    sl.PRYEAR           AS payroll_year,
    sl.PRMNTH           AS payroll_month,
    sl.EDAMT            AS housing_levy_amount
FROM CHABRINS_NEW.dbo.CHPYSL sl
LEFT JOIN vw_sys_staff_037 staff ON sl.EMPNUMB = staff.staff_number
WHERE sl.EDCODE = ''00022'';
';

-- ============================================================================
-- _051  vw_sys_pm_clients_17_repeat_051
--        Staff Bank Accounts (CHABRINS_NEW.dbo.CHSTFF bank columns → sys_pm_clients_17_repeat)
--        Model: ClientBankAccount  |  Controller: ClientsController::saveBankAccounts
--        CHABRINS_NEW.dbo.CHSTFF stores one bank per staff member (BANKCODE, BANKBRAN, BANKACC).
--        Laravel stores bank accounts as child rows in sys_pm_clients_17_repeat
--        linked to the parent client/staff via parent_id.
--        Only staff with a non-empty BANKACC are included.
--        References: vw_sys_staff_037
--                    vw_sys_set_banks_035
--                    vw_sys_set_banks_branches_036
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_pm_clients_17_repeat_051] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY s.EMPNUMB)          AS id,
    staff.id                                         AS parent_id,
    -- FK to sys_staff / sys_pm_clients (resolved via vw_sys_staff_037)
    bk.id                                            AS bank_id,
    -- FK to sys_set_banks (resolved from BANKCODE via vw_sys_set_banks_035)
    s.FULLNAME                                       AS account_name,
    -- No separate account name in MSSQL; staff full name used
    s.BANKACC                                        AS account_number,
    br.id                                            AS branch,
    -- FK to sys_set_banks_branches (resolved from BANKBRAN via _036)
    CAST(NULL AS DECIMAL(13,2))                      AS return_percentage,
    -- No MSSQL equivalent
    s.EMPNUMB                                        AS mssql_staff_number,
    s.BANKCODE                                       AS mssql_bank_code,
    s.BANKBRAN                                       AS mssql_branch_code
FROM CHABRINS_NEW.dbo.CHSTFF s
LEFT JOIN vw_sys_staff_037              staff ON s.EMPNUMB  = staff.staff_number
LEFT JOIN vw_sys_set_banks_035          bk    ON s.BANKCODE = bk.mssql_bank_code
LEFT JOIN vw_sys_set_banks_branches_036 br    ON s.BANKBRAN = br.mssql_branch_code
                                              AND s.BANKCODE = br.mssql_bank_code
WHERE s.BANKACC IS NOT NULL
  AND LTRIM(RTRIM(s.BANKACC)) != '''';
';

-- ============================================================================
-- _052  vw_sys_pm_clients_19_repeat_052
--        Staff Other Contacts (CHABRINS_NEW.dbo.CHSCNTS → sys_pm_clients_19_repeat)
--        Model: ClientOtherContact  |  Controller: ClientsController::saveOtherContacts
--        CHABRINS_NEW.dbo.CHSCNTS stores alternate contact details (home/company addresses,
--        mobile, email) for staff. Laravel stores these as child rows in
--        sys_pm_clients_19_repeat linked via parent_id.
--        Only rows with at least a MOBNO or CUEMAIL are included.
--        References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_pm_clients_19_repeat_052] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY c.EMPNUMB)          AS id,
    staff.id                                         AS parent_id,
    -- FK to sys_staff / sys_pm_clients (resolved via vw_sys_staff_037)
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
FROM CHABRINS_NEW.dbo.CHSCNTS c
LEFT JOIN vw_sys_staff_037 staff ON c.EMPNUMB = staff.staff_number
WHERE c.MOBNO IS NOT NULL OR c.CUEMAIL IS NOT NULL;
';

-- ============================================================================
-- _053  vw_sys_hr_employee_contracts_053
--        Employee Contracts (derived from CHABRINS_NEW.dbo.CHPYSL basic salary history)
--        Maps to: sys_hr_employee_contracts (Contract model)
--        The MSSQL system has no explicit contracts table. Contracts are
--        DERIVED from payslip history: each distinct (EMPNUMB, basic_salary)
--        combination represents one contract period.
--          date_start = 1st day of first salary month
--          date_end   = last day of last salary month (NULL = active contract)
--        References: vw_sys_staff_037
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_employee_contracts_053] AS
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
    FROM CHABRINS_NEW.dbo.CHPYSL
    WHERE EDNAME = ''BASIC SALARY''
    GROUP BY EMPNUMB, CAST(EDAMT AS DECIMAL(13,2))
) p
JOIN (
    SELECT
        EMPNUMB,
        MAX(PRYEAR * 100 + PRMNTH) AS max_period
    FROM CHABRINS_NEW.dbo.CHPYSL
    WHERE EDNAME = ''BASIC SALARY''
    GROUP BY EMPNUMB
) emp_max ON p.EMPNUMB = emp_max.EMPNUMB
LEFT JOIN vw_sys_staff_037 s ON TRY_CAST(p.EMPNUMB AS INT) = s.id;
';

-- ============================================================================
-- _054  vw_sys_hr_employee_contracts_279_repeat_054
--        Contract Element Lines
--        Maps to: sys_hr_employee_contracts_279_repeat (ContractElement model)
--        For each contract period (from _053), lists the distinct payroll
--        elements active during that period. Excludes computed/total
--        pseudo-elements. Amount = average monthly value during the contract.
--        References: vw_sys_hr_employee_contracts_053
--                    vw_sys_hr_payroll_elements_039
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_employee_contracts_279_repeat_054] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY ct.id, el.id)                    AS id,
    ct.id                                                         AS parent_id,
    -- FK → vw_sys_hr_employee_contracts_053.id
    el.id                                                         AS element_id,
    -- FK → vw_sys_hr_payroll_elements_039.id
    CAST(1 AS INT)                                                AS entry_type_id,
    CAST(AVG(CAST(ps.EDAMT AS DECIMAL(13,2))) AS DECIMAL(13,2))  AS amount
FROM vw_sys_hr_employee_contracts_053 ct
JOIN CHABRINS_NEW.dbo.CHPYSL ps
    ON  ps.EMPNUMB  = ct.mssql_emp_number
    AND (ps.PRYEAR * 100 + ps.PRMNTH) BETWEEN ct.mssql_first_period
                                           AND ct.mssql_last_period
    AND ps.EDNAME   != ''BASIC SALARY''
    AND ps.EDCODE NOT IN (''DEDUCTIONS'', ''TAXC'', ''TAXR'', ''TEARNINGS'')
    AND ps.EDAMT    != 0
JOIN vw_sys_hr_payroll_elements_039 el
    ON ps.EDCODE = el.mssql_ed_code
GROUP BY ct.id, el.id;
';

-- ============================================================================
-- _055  vw_sys_hr_payroll_deductions_register_055
--        Payroll Deductions Register (audit log)
--        Maps to: sys_hr_payroll_deductions_register
--        One row per deduction element per employee per payroll period.
--        Covers all actual deduction EDCODEs: statutory + voluntary + loans.
--        References: vw_sys_staff_037
--                    vw_sys_hr_payroll_elements_039
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_payroll_deductions_register_055] AS
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
FROM CHABRINS_NEW.dbo.CHPYSL p
INNER JOIN vw_sys_staff_037               s  ON TRY_CAST(p.EMPNUMB AS INT) = s.id
INNER JOIN vw_sys_hr_payroll_elements_039 el ON p.EDCODE = el.mssql_ed_code
LEFT JOIN CHABRINS_NEW.dbo.CHPYSL bs
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
-- _056  vw_sys_hr_payroll_elements_repeat_staff_positions_id_056
--        Payroll Element ↔ Staff Position Pivot (many-to-many)
--        Maps to: sys_hr_payroll_elements_repeat_staff_positions_id
--        Derived: which payroll elements have historically been applied to
--        which staff positions. parent_id = element_id.
--        Pseudo-element and computed EDCODEs are excluded.
--        References: vw_sys_staff_037
--                    vw_sys_hr_payroll_elements_039
-- ============================================================================
EXEC sp_executesql N'
CREATE OR ALTER VIEW [vw_sys_hr_payroll_elements_repeat_staff_positions_id_056] AS
SELECT
    ROW_NUMBER() OVER (ORDER BY el.id, s.position_id) AS id,
    el.id                                              AS parent_id,
    -- FK → sys_hr_payroll_elements.id
    CAST(s.position_id AS INT)                         AS staff_positions_id
    -- FK → sys_set_staff_positions.id
FROM CHABRINS_NEW.dbo.CHPYSL p
INNER JOIN vw_sys_staff_037               s  ON TRY_CAST(p.EMPNUMB AS INT) = s.id
INNER JOIN vw_sys_hr_payroll_elements_039 el ON p.EDCODE = el.mssql_ed_code
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
-- _057  vw_employee_deductions_perperiod_057  (CHPEED -> employee_deductions_perperiod, 159,099 rows)
-- ============================================================================
-- Per-employee extra one-off/recurring deductions beyond standard payroll
-- elements. (staff_number, payroll_year, payroll_month, element_code) is
-- 100% unique across all rows -- clean natural key, no dedup needed.
-- staff_id      : EMPNUMB -> vw_sys_staff_037.staff_number (LEFT JOIN, ~99.9% match)
--   NOTE: deliberately joined on staff_number (the natural key column the
--   view exposes for this purpose), NOT TRY_CAST(EMPNUMB AS INT) = id like
--   some of the merged HR views (_047/_048/_049/_050/_053/_055/_056) do --
--   EMPNUMB has inconsistent zero-padding in CHSTFF (e.g. "0" vs "001"), so
--   varchar-sorted ROW_NUMBER() id and TRY_CAST-to-int are not guaranteed to
--   line up. That existing pattern was preserved as-is where it was already
--   used (not modified during the HR merge), but not propagated into new work.
-- payroll_element_id : EDCODE -> vw_sys_hr_payroll_elements_039.mssql_ed_code (LEFT JOIN, ~99.99% match)
-- is_locked (LOCKD): 99.1% of rows are 1 (locked); mapped to boolean.
-- audit_user (AUDTUSR) is only ~5% populated and mixed format (numeric ids
-- like "137" alongside usernames like "murungi") -- kept as raw text, not
-- resolved to an FK.
EXEC sp_executesql N'
CREATE OR ALTER VIEW vw_employee_deductions_perperiod_057 AS
SELECT
    staff.id                                            AS staff_id,
    -- PRE-STAMPED: staff_id from CHSTFF via EMPNUMB (vw_sys_staff_037)
    RTRIM(LTRIM(p.EMPNUMB))                             AS staff_number,
    el.id                                                AS payroll_element_id,
    -- PRE-STAMPED: payroll_element_id from CHPREDS via EDCODE (vw_sys_hr_payroll_elements_039)
    RTRIM(LTRIM(p.EDCODE))                              AS element_code,
    p.PRYEAR                                            AS payroll_year,
    p.PRMNTH                                            AS payroll_month,
    ISNULL(p.EDAMT, 0)                                  AS amount,
    CASE WHEN p.LOCKD = 1 THEN 1 ELSE 0 END             AS is_locked,
    RTRIM(LTRIM(p.AUDTUSR))                             AS audit_user,
    p.AUDDATE                                           AS audited_at
FROM CHABRINS_NEW.dbo.CHPEED p
LEFT JOIN vw_sys_staff_037 staff
  ON staff.staff_number = RTRIM(LTRIM(p.EMPNUMB))
LEFT JOIN vw_sys_hr_payroll_elements_039 el
  ON el.mssql_ed_code = RTRIM(LTRIM(p.EDCODE))
WHERE p.EMPNUMB IS NOT NULL AND p.EDCODE IS NOT NULL;
';


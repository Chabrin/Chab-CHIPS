-- ============================================================================
-- POST-MIGRATION SCRIPTS
-- ============================================================================
-- Run these AFTER all migration views have been loaded into chips_db_v3.
-- These scripts fix data that was too expensive to compute during migration
-- (e.g. correlated EXISTS subqueries on 1.4M+ rows).
--
-- Target DB: chips_db_v3 (PostgreSQL, 127.0.0.1:5432, schema: chips_db_v3)
-- ============================================================================


-- ============================================================================
-- 1. Mark cancelled receipts in sys_pm_tenants_payments_allocated
-- ============================================================================
-- During migration, current_status_id is set to 1 (active) for all rows
-- to avoid the slow EXISTS subquery against CHRCS on every row.
-- This UPDATE marks cancelled receipts (current_status_id = 2) post-migration.
--
-- Source: cb_receipts.rcstatus = 'CANCELLED' (migrated from CHRCS)
-- Target: sys_pm_tenants_payments_allocated.current_status_id = 2

UPDATE chips_db_v3.sys_pm_tenants_payments_allocated
SET current_status_id = 2
WHERE receipt_number IN (
    SELECT DISTINCT CAST(rcno AS VARCHAR(50))
    FROM chips_db_v3.cb_receipts
    WHERE rcstatus = 'CANCELLED'
);


-- ============================================================================
-- 2. TRIM whitespace from varchar columns — sys_pm_tenants_payments_allocated
-- ============================================================================
-- RTRIM/LTRIM removed from migration views for speed (1.4M rows).
-- Clean up trailing/leading spaces post-migration in PostgreSQL.

UPDATE chips_db_v3.sys_pm_tenants_payments_allocated SET
    narration        = BTRIM(narration),
    pay_for          = BTRIM(pay_for),
    payment_month    = BTRIM(payment_month),
    payment_mode     = BTRIM(payment_mode),
    payment_channel  = BTRIM(payment_channel),
    security_code    = BTRIM(security_code),
    reference_number = BTRIM(reference_number),
    entry_ref        = BTRIM(entry_ref),
    trans_type       = BTRIM(trans_type),
    trans_remarks    = BTRIM(trans_remarks),
    trans_part       = BTRIM(trans_part)
WHERE narration        != BTRIM(narration)
   OR pay_for          != BTRIM(pay_for)
   OR payment_month    != BTRIM(payment_month)
   OR payment_mode     != BTRIM(payment_mode)
   OR payment_channel  != BTRIM(payment_channel)
   OR security_code    != BTRIM(security_code)
   OR reference_number != BTRIM(reference_number)
   OR entry_ref        != BTRIM(entry_ref)
   OR trans_type       != BTRIM(trans_type)
   OR trans_remarks    != BTRIM(trans_remarks)
   OR trans_part       != BTRIM(trans_part);


-- ============================================================================
-- 3. TRIM whitespace from varchar columns — sys_pm_tenants_payments_unallocated
-- ============================================================================

UPDATE chips_db_v3.sys_pm_tenants_payments_unallocated SET
    date_time        = BTRIM(date_time),
    entry_ref        = BTRIM(entry_ref),
    house_code       = BTRIM(house_code),
    zone_code        = BTRIM(zone_code),
    receipt_number   = BTRIM(receipt_number),
    narration        = BTRIM(narration),
    pay_for          = BTRIM(pay_for),
    pay_month        = BTRIM(pay_month),
    security_code    = BTRIM(security_code),
    trans_type       = BTRIM(trans_type),
    payment_mode     = BTRIM(payment_mode),
    trans_remarks    = BTRIM(trans_remarks),
    trans_part       = BTRIM(trans_part),
    gl_account       = BTRIM(gl_account),
    bank_code        = BTRIM(bank_code),
    posted_date      = BTRIM(posted_date),
    verified_date    = BTRIM(verified_date),
    sheet_count      = BTRIM(sheet_count),
    entry_remarks    = BTRIM(entry_remarks)
WHERE date_time        != BTRIM(date_time)
   OR entry_ref        != BTRIM(entry_ref)
   OR house_code       != BTRIM(house_code)
   OR zone_code        != BTRIM(zone_code)
   OR receipt_number   != BTRIM(receipt_number)
   OR narration        != BTRIM(narration)
   OR pay_for          != BTRIM(pay_for)
   OR pay_month        != BTRIM(pay_month)
   OR security_code    != BTRIM(security_code)
   OR trans_type       != BTRIM(trans_type)
   OR payment_mode     != BTRIM(payment_mode)
   OR trans_remarks    != BTRIM(trans_remarks)
   OR trans_part       != BTRIM(trans_part)
   OR gl_account       != BTRIM(gl_account)
   OR bank_code        != BTRIM(bank_code)
   OR posted_date      != BTRIM(posted_date)
   OR verified_date    != BTRIM(verified_date)
   OR sheet_count      != BTRIM(sheet_count)
   OR entry_remarks    != BTRIM(entry_remarks);



   SELECT setval(pg_get_serial_sequence('cb_returns_summary', 'id'), (SELECT MAX(id) FROM cb_returns_summary));
SELECT setval(pg_get_serial_sequence('cb_returns_clients', 'id'), (SELECT MAX(id) FROM cb_returns_clients));
SELECT setval(pg_get_serial_sequence('cb_landlord_account', 'id'), (SELECT MAX(id) FROM cb_landlord_account));

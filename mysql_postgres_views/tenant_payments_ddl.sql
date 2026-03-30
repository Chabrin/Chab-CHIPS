-- ============================================================================
-- PostgreSQL DDL: tenant payments tables
-- ============================================================================
-- These replace the old sys_pm_tenants_payments and sys_pm_tenants_payments_invoices
-- tables with two clean tables that mirror CHABRINS source tables directly.
--
-- Source: CHTPAYS  -> sys_pm_tenants_payments_allocated   (posted/allocated payments)
-- Source: CHBCTD   -> sys_pm_tenants_payments_unallocated (raw bank batch detail lines)
--
-- Run on: chips_db_v3 (PostgreSQL)
-- ============================================================================


-- ============================================================================
-- 1. sys_pm_tenants_payments_allocated  (source: CHTPAYS — 1,435,222 rows)
-- ============================================================================
-- Each row is a tenant payment that has been received and allocated.
-- Redundant text columns (tenname, blockid, unitno) dropped in favour of
-- pre-stamped integer FK columns (tenant_id, property_id, unit_id).
-- PDTOLL, TSTATUS, ADSH, PAYDATF dropped — empty/zero in all rows.

create table sys_pm_tenants_payments_allocated (
    id                    bigint generated always as identity primary key,
    date_time             timestamp,                -- TRANSDAT: system entry timestamp
    payment_date          timestamp,                -- PAYDATE: tenant's actual payment date
    batch_id              numeric,                  -- BATCHID: source bank batch id
    entry_id              numeric,                  -- ENTRYID: line number within the batch
    receipt_number        varchar(50),              -- RCNO: receipt number (links to CHTACC/CHRCS)
    amount                numeric(19,4) default 0,  -- RCAMT: receipt amount
    narration             varchar(50),              -- TRANSDESC: payment description
    pay_for               varchar(50),              -- PAYFOR: charge category (RENT, Water Bill, etc.)
    payment_month         varchar(20),              -- TRAMNTH: payment month
    payment_year          int,                      -- TRAYR: payment year
    payment_mode          varchar(20),              -- PYMOD: legacy mode (CA, REC, PO)
    payment_channel       varchar(50),              -- PAYMOD: channel (IBAPS, POS, MPESA, CASH, etc.)
    security_code         varchar(50),              -- SECURCD: MPESA/bank security reference
    reference_number      varchar(150),             -- REFNUM: additional reference
    entry_ref             varchar(100),             -- ENTRYREF: batch/channel identifier
    trans_amount          numeric(19,4) default 0,  -- TRANAMT: transaction amount (usually = RCAMT)
    trans_type            varchar(50),              -- TRANTYP: channel type (IBAPS, POS, Pay Bill, etc.)
    trans_remarks         varchar(150),             -- TRANRMK: transaction remarks
    trans_part            varchar(150),             -- TRANPART: transaction party details
    receipt_month         int,                      -- RMONTH: receipt month
    receipt_year          int,                      -- RYEAR: receipt year
    is_recovery           int default 0,            -- RECOVD: 1 = recovery payment, 0 = normal
    is_in_account         int default 0,            -- INRACC: 1 = posted to tenant account, 0 = not yet
    property_id           int,                      -- FK: sys_pm_properties
    client_id             int,                      -- FK: sys_pm_clients (landlord)
    unit_id               int,                      -- FK: sys_pm_units
    tenant_id             int,                      -- FK: sys_pm_clients (tenant) / lease
    zone_id               int,                      -- FK: sys_set_zones
    field_officer_id      int,                      -- FK: sys_staff (field officer)
    zone_supervisor_id    int,                      -- FK: sys_staff (zone supervisor)
    zone_manager_id       int,                      -- FK: sys_staff (zone manager)
    current_status_id     int default 1             -- 1=active, 2=cancelled, 3=reversed
);

-- indexes for common lookups
create index idx_alloc_tenant     on sys_pm_tenants_payments_allocated (tenant_id);
create index idx_alloc_property   on sys_pm_tenants_payments_allocated (property_id);
create index idx_alloc_receipt    on sys_pm_tenants_payments_allocated (receipt_number);
create index idx_alloc_batch      on sys_pm_tenants_payments_allocated (batch_id, entry_id);
create index idx_alloc_paydate    on sys_pm_tenants_payments_allocated (payment_date);


-- ============================================================================
-- 2. sys_pm_tenants_payments_unallocated  (source: CHBCTD — 1,472,942 rows)
-- ============================================================================
-- Each row is a raw bank batch detail line (pre-posting).
-- These are the incoming transactions before they get posted to tenant accounts.
-- Redundant text columns (tenname, blockid, unitno, offid, sfoid, zmgrid,
-- prpby, pstdby, svrfby) dropped — replaced by integer FK equivalents.
-- DSPNO, SFID, ENTRYREF2 dropped — empty in all/most rows.

create table sys_pm_tenants_payments_unallocated (
    id                    bigint generated always as identity primary key,
    date_time             varchar(100),             -- TRANSDAT: entry timestamp (stored as text in source)
    batch_id              numeric not null,         -- BTCHID: bank batch id
    entry_id              int not null,             -- ENTRYID: line number within the batch
    entry_ref             varchar(200),             -- ENTRYREF: reference string
    house_code            varchar(50),              -- HSECODE: property-unit code (e.g. "387B-B7")
    entry_date            timestamp,                -- ENTDAT: entry date
    zone_code             varchar(10),              -- CHZONE: zone letter (A-G)
    receipt_number        varchar(15),              -- RCNO: receipt number (null if unposted)
    amount                numeric(19,4) default 0,  -- RCAMT: receipt amount
    narration             varchar(200),             -- TRANSDESC: transaction description
    pay_for               varchar(50),              -- PAYFOR: charge category
    pay_month             varchar(10),              -- PAYMNTH: payment month
    pay_year              int,                      -- PAYYEAR: payment year
    security_code         varchar(50),              -- SECURNO: security/MPESA reference
    trans_type            varchar(100),             -- TRANSTYP: channel (IBAPS, POS, Pay Bill, etc.)
    payment_mode          varchar(50),              -- PYMOD: payment mode
    trans_remarks         varchar(100),             -- TRNRMKS: remarks
    trans_part            varchar(100),             -- TRNPART: party details
    gl_account            varchar(50),              -- ACCNO: GL account number
    bank_code             varchar(50),              -- BANKC: bank name/code
    sub_year              int,                      -- SUBYR: submission year
    sub_month             int,                      -- SUBMNTH: submission month
    is_posted             int default 0,            -- SPSTD: 1 = posted to tenant account, 0 = not
    posted_date           varchar(50),              -- SPSTDAT: date posted
    is_verified           int default 0,            -- SVRFY: 1 = verified, 0 = not
    verified_date         varchar(50),              -- SVRDAT: verification date
    base_amount           numeric(19,4) default 0,  -- BASEAMT: base/gross amount
    sheet_count           varchar(50),              -- SHEETCNT: sheet count reference
    entry_remarks         varchar(50),              -- ENTRMKS: entry remarks
    voucher_id            int,                      -- FK: sys_fin_payment_vouchers (via CHBCTH)
    property_id           int,                      -- FK: sys_pm_properties
    current_status_id     int default 1             -- 1=active, 2=inactive
);

-- indexes for common lookups
create index idx_unalloc_batch     on sys_pm_tenants_payments_unallocated (batch_id, entry_id);
create index idx_unalloc_property  on sys_pm_tenants_payments_unallocated (property_id);
create index idx_unalloc_receipt   on sys_pm_tenants_payments_unallocated (receipt_number);
create index idx_unalloc_posted    on sys_pm_tenants_payments_unallocated (is_posted);


-- ============================================================================
-- 3. cb_receipts  (source: CHRCS — 1,969,089 rows)
-- ============================================================================
-- Receipt book / receipt register. Each row is a receipt number issued.
-- RCSTATUS: OK (1,824,410), CANCELLED (144,678), 0 (1).
-- Used post-migration to mark cancelled payments in sys_pm_tenants_payments_allocated.

create table cb_receipts (
    id                    bigint generated always as identity primary key,
    rcno                  numeric not null,         -- receipt number
    rcof                  varchar(10),              -- receipt office / book number
    subdate               timestamp,                -- submission date
    submth                int,                      -- submission month
    subyr                 int,                      -- submission year
    subamt                numeric(19,4) default 0,  -- submission amount
    dpsno                 varchar(50),              -- deposit slip number
    rused                 int default 0,            -- receipt used flag
    house                 varchar(10),              -- house / unit code
    accno                 varchar(50),              -- GL account number
    accbank               varchar(50),              -- bank account
    rcstatus              varchar(10),              -- OK / CANCELLED
    canby                 varchar(20),              -- cancelled by (staff name)
    canreason             varchar(100),             -- cancellation reason
    candate               timestamp                 -- cancellation date
);

create index idx_cb_receipts_rcno     on cb_receipts (rcno);
create index idx_cb_receipts_status   on cb_receipts (rcstatus);

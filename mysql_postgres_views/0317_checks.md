# Tenant Payments Mapping Investigation

**Date:** 2025-03-17  
**Scope:** `sys_pm_tenants_payments` and `sys_pm_tenants_payments_invoices`  
**Source DB:** `localhost\SQL2017` — `CHABRINS`  
**Target DB:** `PostgreSQL` — `chips_db_v3`

---

## 1. Source Table Inventory

| MSSQL Table | Rows      | Purpose                                                                        | Migration Status                                               |
| ----------- | --------- | ------------------------------------------------------------------------------ | -------------------------------------------------------------- |
| CHTPAYS     | 1,435,222 | **PRIMARY** tenant payment transaction table                                   | ❌ Not yet mapped (commented stub in SQL)                      |
| CHIPAYS     | 12,874    | Cheque/summary payment records                                                 | ✅ Currently mapped → `sys_pm_tenants_payments` (WRONG source) |
| CHTACC      | 2,598,893 | Tenant account ledger — DR/CR allocations                                      | ❌ Not mapped                                                  |
| CHRCS       | 1,969,089 | Receipt header table (RCNO, RCSTATUS, bank)                                    | ❌ Not mapped (join only)                                      |
| CHTNTRS     | 1,156,397 | Tenant invoice table (pre-stamped invoice_id, tenant_id, unit_id, property_id) | ✅ Used as FK source via INVONUM                               |
| CHTPJV      | 54        | Tenant payment journals (negligible)                                           | Skip                                                           |
| CHPAYMENTS  | 4         | Essentially empty                                                              | Skip                                                           |
| CHRCSD      | 0         | Empty                                                                          | Skip                                                           |

---

## 2. Problem with Current Mapping

The current view `vw_sys_pm_tenants_payments` sources from **CHIPAYS** (12,874 rows — cheque summary table).  
**CHIPAYS is not the payment transaction store.** It records cheque numbers and basic tenant/amount info only.

The **real payment table is CHTPAYS** with 1,435,222 rows containing:

- Full transaction detail (PAYDATE, RCNO, RCAMT, TRANTYP, SECURCD/MPESA ref, batch info)
- Three-key tenant lookup: TENNAME + BLOCKID + UNITNO

CHIPAYS does not have PYMOD (payment mode), TRANTYP, SECURCD, ENTRYREF, BATCHID, or TRANSDAT — all critical for audit and reconciliation.

---

## 3. CHTPAYS Column Schema (30 columns)

| Column    | Type         | Notes                                           |
| --------- | ------------ | ----------------------------------------------- |
| TENNAME   | varchar(60)  | Tenant name — join key                          |
| BLOCKID   | varchar(10)  | Block/property code — join key                  |
| UNITNO    | varchar(10)  | Unit number — join key                          |
| TRANSDESC | varchar(50)  | Transaction description / narration             |
| TRAMNTH   | varchar(20)  | Transaction month label                         |
| TRAYR     | int          | Transaction year                                |
| RCNO      | decimal      | **Receipt number** — links to CHRCS.RCNO        |
| RCAMT     | money        | **Receipt/payment amount**                      |
| PAYDATE   | datetime     | **Payment date**                                |
| PYMOD     | varchar(20)  | Payment mode short code (CA=cash, etc.)         |
| RMONTH    | int          | Month reference                                 |
| RYEAR     | int          | Year reference                                  |
| ADSH      | int          | Additional charges flag                         |
| RECOVD    | int          | Recovery flag                                   |
| INRACC    | int          | In-rack account flag                            |
| PAYDATF   | decimal      | Payment date as float/decimal                   |
| PAYFOR    | varchar(50)  | Payment for (rent, water, etc.)                 |
| PDTOLL    | money        | Paid to date total                              |
| SECURCD   | varchar(50)  | **Security/MPESA/bank reference code**          |
| BATCHID   | decimal      | Batch identifier — use for ordering/ROW_NUMBER  |
| ENTRYID   | decimal      | Entry identifier — use for ordering/ROW_NUMBER  |
| ENTRYREF  | varchar(100) | Entry reference string                          |
| TRANSDAT  | datetime     | Transaction date (may differ from PAYDATE)      |
| TRANAMT   | decimal      | Transaction amount                              |
| TRANTYP   | varchar(50)  | Transaction type (IBAPS, PAYBILL, CREDIT, etc.) |
| REFNUM    | varchar(150) | Reference number                                |
| TRANRMK   | varchar(150) | Transaction remark                              |
| TRANPART  | varchar(150) | Transaction party                               |
| PAYMOD    | varchar(50)  | Payment mode — verbose (mirrors PYMOD)          |
| TSTATUS   | varchar(50)  | Transaction status (NULL = active)              |

**No pre-stamped integer IDs on CHTPAYS.** FK resolution must use ROW_NUMBER() subqueries.

---

## 4. CHRCS Column Schema (15 columns) — Receipt Header

| Column    | Type         | Notes                                                    |
| --------- | ------------ | -------------------------------------------------------- |
| RCNO      | decimal      | **Receipt number** — PK link from CHTPAYS                |
| RCOF      | varchar(10)  | Receipt office/source                                    |
| SUBDATE   | datetime     | Submission/banking date                                  |
| SUBMTH    | int          | Submission month                                         |
| SUBYR     | int          | Submission year                                          |
| SUBAMT    | money        | Submitted amount                                         |
| DPSNO     | varchar(50)  | Deposit slip number                                      |
| RUSED     | int          | Receipt used flag                                        |
| HOUSE     | varchar(10)  | House/block code                                         |
| ACCNO     | varchar(50)  | Bank account number                                      |
| ACCBANK   | varchar(50)  | Bank name                                                |
| RCSTATUS  | varchar(10)  | **OK** or **CANCELLED** (144,678 cancelled of 1,969,089) |
| CANBY     | varchar(20)  | Cancelled by user                                        |
| CANREASON | varchar(100) | Cancellation reason                                      |
| CANDATE   | datetime     | Cancellation date                                        |

CHRCS joins CHTPAYS via `CHRCS.RCNO = CHTPAYS.RCNO` to provide receipt status, bank details, and deposit slip.

---

## 5. CHTACC Column Schema (15 columns) — Account Ledger / Allocations

| Column    | Type         | Notes                                                      |
| --------- | ------------ | ---------------------------------------------------------- |
| TENNAME   | varchar(100) | Tenant name — join key                                     |
| BLOCKID   | varchar(10)  | Block code — join key                                      |
| UNITNO    | varchar(10)  | Unit number — join key                                     |
| TRANSDESC | varchar(100) | Transaction description                                    |
| PAYFOR    | varchar(50)  | Payment category (RENT, WATER, etc.)                       |
| DRCR      | varchar(10)  | **DR** = invoice/charge, **CR** = payment/receipt          |
| TRANSDATE | datetime     | Transaction date                                           |
| TRANAMT   | money        | **Debit amount** — populated for DR rows; zero on CR rows  |
| TRANSAMT  | money        | **Credit amount** — populated for CR rows; zero on DR rows |
| RCNO      | numeric      | Receipt number (populated for CR records)                  |
| PYMOD     | varchar(5)   | Payment mode                                               |
| ENTRYREF  | varchar(100) | Entry reference (receipt/payment ref)                      |
| ENTRYBY   | varchar(100) | Entered by (user)                                          |
| DELSTAT   | varchar(20)  | Delete status ("Not Deleted" / NULL)                       |
| INVONUM   | varchar(100) | **Invoice number** — links to CHTNTRS.INVONUM              |

**CHTACC is the allocation/ledger bridge.** Each row links one payment (CR row with RCNO) to one invoice (INVONUM). This is the `sys_pm_tenants_payments_invoices` source.

---

## 6. CHTNTRS — Invoice Table (Pre-Stamped IDs)

CHTNTRS has **1,156,397 rows** with full pre-stamped integer IDs already stamped:

- `invoice_id` — unique invoice integer PK
- `tenant_id`, `unit_id`, `property_id`, `client_id`, `zone_id`
- `field_officer_id`, `zone_supervisor_id`, `zone_manager_id`
- `INVONUM` varchar(100) — the text invoice number used as join key across tables

INVONUM format: `INV{YYYY}{MM}{BLOCKID}{UNITNO}-{description}`  
Example: `INV202512398D10-RENT FOR December 2025`

---

## 7. FK Resolution Strategy for CHTPAYS

Since CHTPAYS has **no pre-stamped integer IDs**, FK resolution uses subquery ROW_NUMBER() lookups via the existing pattern in `chabrins_to_postgres_views.sql`:

```sql
-- tenant_id: match TENNAME + BLOCKID + UNITNO → CHTENS
(SELECT ROW_NUMBER() OVER (ORDER BY TENNAME, BLOCKNO, UNITNO)
 FROM CHABRINS.dbo.CHTENS t2
 WHERE RTRIM(t2.TENNAME) = RTRIM(p.TENNAME)
   AND RTRIM(t2.BLOCKNO) = RTRIM(p.BLOCKID)
   AND RTRIM(t2.UNITNO)  = RTRIM(p.UNITNO)) AS tenant_id

-- unit_id: match BLOCKID + UNITNO → CHBUNITS
(SELECT ROW_NUMBER() OVER (ORDER BY BLOCKNO, UNITNO)
 FROM CHABRINS.dbo.CHBUNITS u2
 WHERE RTRIM(u2.BLOCKNO) = RTRIM(p.BLOCKID)
   AND RTRIM(u2.UNITNO)  = RTRIM(p.UNITNO)) AS unit_id

-- property_id: match BLOCKID → CHBLOCKS
(SELECT ROW_NUMBER() OVER (ORDER BY BLOCKNO)
 FROM CHABRINS.dbo.CHBLOCKS b2
 WHERE RTRIM(b2.BLOCKNO) = RTRIM(p.BLOCKID)) AS property_id
```

---

## 8. Proposed Mapping: CHTPAYS → `sys_pm_tenants_payments`

| PG Column          | Source                                               | Notes                                   |
| ------------------ | ---------------------------------------------------- | --------------------------------------- |
| id                 | `ROW_NUMBER() OVER (ORDER BY BATCHID, ENTRYID)`      | Stable ordering by batch+entry          |
| date_time          | `p.TRANSDAT`                                         | Use TRANSDAT as the "entry timestamp"   |
| payment_date       | `p.PAYDATE`                                          | Actual payment date                     |
| reference          | `p.SECURCD`                                          | MPESA/bank/security reference code      |
| narration          | `p.TRANSDESC`                                        | Transaction description / narration     |
| created_by         | `NULL`                                               | No entry-by on CHTPAYS                  |
| zone_id            | `NULL`                                               | Not on CHTPAYS — no direct zone link    |
| client_id          | ROW_NUMBER via CHBLOCKS→CHLRDS                       | Derive from BLOCKID chain               |
| property_id        | ROW_NUMBER via CHBLOCKS                              | Match BLOCKID                           |
| unit_id            | ROW_NUMBER via CHBUNITS                              | Match BLOCKID + UNITNO                  |
| lease_id           | `NULL`                                               | No lease FK on CHTPAYS                  |
| tenant_id          | ROW_NUMBER via CHTENS                                | Match TENNAME + BLOCKID + UNITNO        |
| current_status_id  | `CASE WHEN r.RCSTATUS='CANCELLED' THEN 3 ELSE 1 END` | Join CHRCS on RCNO                      |
| amount             | `ISNULL(p.RCAMT, 0)`                                 | Receipt/payment amount                  |
| invoice_id         | `NULL`                                               | Not on CHTPAYS directly — use CHTACC    |
| trans_details      | `p.PAYFOR`                                           | Payment allocation category             |
| sla_id             | `NULL`                                               | Not available                           |
| channel_id         | `NULL`                                               | Consider mapping PYMOD → channel lookup |
| receipt_number     | `CAST(p.RCNO AS VARCHAR(50))`                        | Receipt number from CHTPAYS             |
| recovery_type_id   | `NULL`                                               | No direct mapping                       |
| field_officer_id   | `NULL`                                               | Not on CHTPAYS                          |
| zone_supervisor_id | `NULL`                                               | Not on CHTPAYS                          |
| zone_manager_id    | `NULL`                                               | Not on CHTPAYS                          |
| extract_id         | `NULL`                                               | Migration extract reference — set NULL  |
| tenant_aid         | `p.ENTRYREF`                                         | Entry reference string as aid field     |

---

## 9. Proposed Mapping: CHTACC (CR rows) → `sys_pm_tenants_payments_invoices`

> **Note:** `sys_pm_tenants_payments_invoices` is **not** in `schema_data.json` as a direct MSSQL→PG mapping.  
> This table stores the payment-to-invoice allocation lines.  
> CHTACC CR rows (with INVONUM) are the natural source.  
> The PG target `sys_pm_tenants_payments_invoices` columns are not yet confirmed — **check PG live schema before finalising view.**

### ✅ Many-to-Many & Partial Payments Are Fully Supported

CHTACC naturally models both relationships — each row is **one allocation line** (one payment applied to one invoice):

**Pattern 1 — One payment → multiple invoices** (e.g. a bulk receipt split across tenants):

> RCNO `2203531513` appears on 2 rows: RICHARD OPINDE → `INV2025121048EF28` (50.00) and DEBORAH CHEPKEMOI → `INV20261155G17B` (6840.00)

**Pattern 2 — One invoice → multiple partial payments** (e.g. installments):

> `INV202511109G887-RENT FOR November 2025` (original DR = 25,000) paid via 4 separate receipts: 5,796 + 9,000 + 9,100 + 1,104

Each CHTACC CR row = one row in `sys_pm_tenants_payments_invoices`. The PG table gets a flat junction structure — no special handling needed.

### ⚠️ Critical: TRANAMT vs TRANSAMT Column Behaviour

CHTACC has two **differently-named** money columns with split usage:

| Column   | DR rows        | CR rows                       |
| -------- | -------------- | ----------------------------- |
| TRANAMT  | invoice amount | `.0000` (zero)                |
| TRANSAMT | `.0000` (zero) | **payment allocation amount** |

For `sys_pm_tenants_payments_invoices`, always use **`a.TRANSAMT`** (not `a.TRANAMT`) — CR rows carry the actual allocated amount there.

### Column Mapping

| PG Column (assumed) | Source (CHTACC)                                                     | Notes                                     |
| ------------------- | ------------------------------------------------------------------- | ----------------------------------------- |
| id                  | ROW_NUMBER() OVER (ORDER BY TRANSDATE, ENTRYREF)                    |                                           |
| payment_id          | JOIN CHTPAYS on RCNO → get ROW_NUMBER id                            | Link back to sys_pm_tenants_payments      |
| invoice_id          | JOIN CHTNTRS on INVONUM → CHTNTRS.invoice_id                        | Pre-stamped integer FK                    |
| amount              | `a.TRANSAMT`                                                        | ⚠️ Use TRANSAMT (not TRANAMT) for CR rows |
| date_time           | `a.TRANSDATE`                                                       | Allocation date                           |
| narration           | `a.TRANSDESC`                                                       | Description                               |
| tenant_id           | ROW_NUMBER via CHTENS (TENNAME+BLOCKID+UNITNO) or CHTNTRS.tenant_id |                                           |
| unit_id             | ROW_NUMBER via CHBUNITS or CHTNTRS.unit_id                          |                                           |

**Filtering:** Use only `DRCR = 'CR'` rows (credit = payment lines).  
**Exclude deleted:** `DELSTAT = 'Not Deleted'` or `DELSTAT IS NOT NULL`.

---

## 10. Receipt Status Mapping

CHRCS.RCSTATUS values:
| MSSQL Value | Count | → PG current_status_id |
|-------------|-----------|------------------------|
| OK | 1,824,410 | 1 (active/confirmed) |
| CANCELLED | 144,678 | 3 (cancelled) |
| 0 | 1 | 1 (treat as OK) |

---

## 11. CHTACC — TRANAMT vs TRANSAMT Split Usage

CHTACC has two **differently-named** money columns at positions 8 and 9: `TRANAMT` and `TRANSAMT`. They are **not duplicates** — they serve opposite roles:

- **`TRANAMT`** — populated for **DR** (debit/invoice) rows; zero on CR rows
- **`TRANSAMT`** — populated for **CR** (credit/payment) rows; zero on DR rows

This is a deliberate split-column design (debit amount / credit amount in separate fields). Always use the correct column for the row type:

```sql
-- In the payments_invoices view (CR rows only):
SELECT a.TRANSAMT AS amount   -- use TRANSAMT, not TRANAMT
FROM CHABRINS.dbo.CHTACC a
WHERE a.DRCR = 'CR'

-- If ever querying DR rows for invoice amounts:
SELECT a.TRANAMT AS amount    -- use TRANAMT for DR rows
```

---

## 12. Recommendations

1. **Replace CHIPAYS view with CHTPAYS view** — CHIPAYS has only 12,874 rows vs 1,435,222 in CHTPAYS. The cheque table is insufficient for a complete migration.

2. **Add `vw_sys_pm_tenants_payments`** sourced from CHTPAYS with LEFT JOIN to CHRCS (for cancellation status) and ROW_NUMBER FK lookups for tenant/unit/property IDs.

3. **Build `vw_sys_pm_tenants_payments_invoices`** from CHTACC (DRCR='CR') joined to CHTNTRS via INVONUM to get pre-stamped `invoice_id`. This ties each payment allocation line to an invoice.

4. **Confirm target PG columns for `sys_pm_tenants_payments_invoices`** by querying `chips_db_v3` live before writing the final view.

5. **Index recommendations on CHABRINS** for view performance:

    ```sql
    -- CHTPAYS: join to CHRCS on RCNO (RCNO is decimal, no index observed)
    CREATE INDEX IX_CHTPAYS_RCNO ON CHABRINS.dbo.CHTPAYS (RCNO) INCLUDE (TENNAME, BLOCKID, UNITNO, RCAMT, PAYDATE, SECURCD, TRANTYP);

    -- CHTACC: join/filter on INVONUM and DRCR
    CREATE INDEX IX_CHTACC_INVONUM ON CHABRINS.dbo.CHTACC (INVONUM) INCLUDE (RCNO, TRANAMT, TRANSDATE, DRCR, DELSTAT);
    CREATE INDEX IX_CHTACC_RCNO ON CHABRINS.dbo.CHTACC (RCNO) WHERE DRCR = 'CR';

    -- CHTNTRS: INVONUM lookup (already has invoice_id — confirm if index exists)
    CREATE INDEX IX_CHTNTRS_INVONUM ON CHABRINS.dbo.CHTNTRS (INVONUM) INCLUDE (invoice_id, tenant_id, unit_id, property_id);
    ```

6. **CHTPAYS ordering** — use `BATCHID, ENTRYID` for stable `ROW_NUMBER()` id generation. BATCHID=0 entries may be legacy/unprocessed; consider filtering or handling separately.

---

## 13. Summary of Data Flow

```
CHTPAYS (1.4M rows)          ──► sys_pm_tenants_payments
   └── LEFT JOIN CHRCS         (RCNO → receipt status, bank details)
   └── ROW_NUMBER FK lookups   (TENNAME+BLOCKID+UNITNO → tenant/unit/property)

CHTACC [DRCR='CR'] (2.6M rows) ──► sys_pm_tenants_payments_invoices
   └── JOIN CHTNTRS on INVONUM  (text inv# → pre-stamped integer invoice_id)
   └── JOIN CHTPAYS on RCNO     (link allocation line back to payment record)

CHTNTRS (1.16M rows)          ──► sys_pm_debtors_invoices (already planned)
   └── Has pre-stamped invoice_id, tenant_id, unit_id, property_id, client_id
```

---

_Next step: Confirm `sys_pm_tenants_payments_invoices` PG column list (query live chips_db_v3), then write the two views and add to `chabrins_to_postgres_views.sql` and `other_views_mod.sql`._

# Returns & Landlord Account — Relationships and Flow

## Source Tables (CHABRINS / MSSQL)

| Table      | Rows      | PostgreSQL Target     | Description                                    |
| ---------- | --------- | --------------------- | ---------------------------------------------- |
| **CHRHMS** | 35,217    | `cb_returns_summary`  | Return header — one per property per period    |
| **CHRTXS** | 1,470,210 | `cb_returns_clients`  | Return detail — per unit/tenant charge lines   |
| **CHLACC** | 128,423   | `cb_landlord_account` | Landlord account ledger (debit/credit entries) |
| CHRMES     | 33,516    | _(not migrated)_      | Scheduler messages / auto-log for returns      |
| CHREST     | 188       | _(not migrated)_      | Bank statement reconciliation entries          |
| CHLAP      | 0         | —                     | Empty                                          |
| CHRHMT     | 0         | —                     | Empty                                          |
| CHRTXT     | 0         | —                     | Empty                                          |
| CHPAYMENTS | 4         | —                     | Negligible                                     |

---

## Entity Relationships

```
  CHLRDS (Landlords)
      │
      │ LORDID
      ▼
  ┌──────────────────────────┐
  │  CHRHMS (cb_returns_     │   1 row per property per period
  │          summary)        │   RETURNID (PK, varchar)
  │                          │   LORDID → landlord
  │  Workflow fields:        │   BLOCKID → property
  │  PREP → CHK → FINA → APP│   YEARR / MONTHH
  │  PAIDST, RETSTAT         │   Financial totals: TOTALCR, TOTALDR,
  │                          │   NETAMT, TRXAMT, NETBAL, COMMRATE
  └──────────┬───────────────┘
             │
             │ RETURNID (1:N)
             ▼
  ┌──────────────────────────┐
  │  CHRTXS (cb_returns_     │   ~42 lines per return on average
  │          clients)        │   TRANSNUM (unique line id)
  │                          │   RETURNID → cb_returns_summary
  │  Per unit/tenant:        │   BLOCKID + UNITNO + TENNAME
  │  ENTRYTYP, TRANSCODE     │   RESAMTNT (gross), AMTPAYBL (landlord),
  │  ENTRYDESC (narration)   │   AMTPAYTN (tenant), RESAMTDR/CR (residual)
  │                          │   VATAMT, COMMBL (commissionable Y/N)
  └──────────┬───────────────┘
             │
             │ invoice_id → CHTNTRS
             ▼
  ┌──────────────────────────┐
  │  CHTNTRS (sys_pm_        │   Tenant invoice ledger
  │  debtors_invoices)       │   TRANSAMT, AMTPAY, BALAMT
  └──────────────────────────┘


  CHRHMS ─── RETURNID ───▶ CHLACC (cb_landlord_account)
                            │
                            │  Entry type 0005 = "RETURNS" posting
                            │  AMTDR / AMTCR / AMTTOT
                            │  LORDID, HOUSEID (property)
                            │  PVNO (payment voucher)
                            │
                            │  Other entry types (direct charges):
                            │  0001 Electricity, 0003 Water,
                            │  0006 Garbage, 0009 Agent Fees,
                            │  0014 Cancelled Cheque, 0015 Caretaker,
                            │  0019 Banking Services, 0020 Loan, etc.
                            │
                            │  NULL RETURNID = direct charge (not from return)
                            ▼
                      ┌──────────────┐
                      │  CHBCTH /    │  Payment voucher → cheque
                      │  Bank Batch  │
                      └──────────────┘
```

---

## Returns Processing Flow

```
1. PREPARATION
   ├─ Staff prepares return for a property for a month
   ├─ System pulls charge lines from tenant invoices (CHTNTRS)
   ├─ Creates CHRHMS header (PREPBY, PREPDAT, PREPST=1)
   └─ Creates CHRTXS detail lines per unit/tenant/charge

2. CHECKING
   ├─ Supervisor reviews return
   └─ Updates CHRHMS (CHKBY, CHKDAT, CHKST=1)

3. FINALIZATION
   ├─ Finance finalizes amounts
   ├─ Commission calculated: TOTALCOM = RESAMTNT × COMMRATE for commissionable lines
   ├─ VAT calculated on commission
   └─ Updates CHRHMS (FINABY, FINADAT, FINAST=1)

4. APPROVAL
   ├─ Management approves for payment
   └─ Updates CHRHMS (APPBY, APPDAT, APPST=1)

5. PAYMENT
   ├─ Creates CHLACC entry (ENTRYID='0005', type=RETURNS)
   ├─ Links via RETURNID
   ├─ Creates payment voucher (VOUCHERNO in CHRHMS)
   ├─ Issues cheque (CHQNO, BANKACC)
   └─ Updates CHRHMS (PAIDST=1, AMTPAYD, PAYDAT)

6. ONGOING CHARGES (independent of returns)
   ├─ Direct debit entries in CHLACC
   ├─ Entry types: garbage, banking, agent fees, caretaker, etc.
   └─ These have NULL RETURNID
```

---

## Key Column Mappings

### CHRHMS → cb_returns_summary

| CHABRINS      | PostgreSQL               | Notes                   |
| ------------- | ------------------------ | ----------------------- |
| RETURNID      | return_code              | PK, varchar(50)         |
| LORDID        | landlord_code            | FK → landlords          |
| BLOCKID       | property_code            | FK → properties         |
| YEARR/MONTHH  | year / month             | Period                  |
| COMMRATE      | commission_rate          | % (e.g. 10.00)          |
| TOTALCR       | total_credit             | Total collections       |
| TOTALDR       | total_debit              | Total deductions        |
| NETAMT        | net_amount               | Net = CR - DR           |
| TRXAMT        | transaction_amount       | Transaction amount      |
| NETBAL        | net_balance              | Running balance         |
| TOTALCOM      | commission_amount        | Commission charged      |
| PREPBY/ST/DAT | prepared_by/status/date  | Preparation stage       |
| CHKBY/ST/DAT  | checked_by/status/date   | Check stage             |
| FINABY/ST/DAT | finalized_by/status/date | Finalization stage      |
| APPBY/ST/DAT  | approved_by/status/date  | Approval stage          |
| PAIDST        | paid_status              | 1=paid, 0=pending       |
| RETSTAT       | return_status            | 0=pending, 1=processed  |
| AMTPAYD       | amount_paid              | Amount paid to landlord |
| VOUCHERNO     | voucher_number           | Payment voucher ref     |

### CHRTXS → cb_returns_clients

| CHABRINS  | PostgreSQL              | Notes                         |
| --------- | ----------------------- | ----------------------------- |
| RETURNID  | return_code             | FK → cb_returns_summary       |
| TRANSNUM  | transaction_number      | Unique line ID                |
| RETNPRD   | return_period           | YYYYMM int                    |
| ENTRYTYP  | entry_type              | RENT, Commission, Water, etc. |
| ENTRYDESC | narration               | Full description text         |
| RESAMTNT  | net_amount              | Gross/net line amount         |
| AMTPAYBL  | amount_payable_landlord | Landlord's share              |
| AMTPAYTN  | amount_payable_tenant   | Tenant payment amount         |
| RESAMTDR  | residual_debit          | Outstanding debit component   |
| RESAMTCR  | residual_credit         | Outstanding credit component  |
| VATAMT    | vat_amount              | VAT on this line              |
| COMMBL    | is_commissionable       | Yes/No                        |
| BLOCKID   | property_code           | Property ref (text)           |
| UNITNO    | unit_code               | Unit ref (text)               |
| TENNAME   | tenant_name             | Tenant name (text)            |

### CHLACC → cb_landlord_account

| CHABRINS | PostgreSQL         | Notes                   |
| -------- | ------------------ | ----------------------- |
| GTTCNUM  | transaction_number | Unique transaction ID   |
| LORDID   | landlord_code      | Landlord ref            |
| HOUSEID  | property_code      | Property ref            |
| ENTRYID  | entry_type_code    | Type code (0001-0046+)  |
| ENTRYDE  | entry_description  | Description/narration   |
| AMTDR    | debit_amount       | Debit amount            |
| AMTCR    | credit_amount      | Credit amount           |
| AMTTOT   | total_amount       | Running total           |
| RETURNID | return_code        | FK → cb_returns_summary |
| PVNO     | pv_number          | Payment voucher number  |
| FINACC   | gl_account         | GL account code         |
| RECOVD   | is_recovered       | Recovery flag (0/1)     |

---

## Migration Views

| #   | View Name                  | Source | Target              |
| --- | -------------------------- | ------ | ------------------- |
| 33  | vw_cb_returns_summary_015  | CHRHMS | cb_returns_summary  |
| 34  | vw_cb_returns_clients_016  | CHRTXS | cb_returns_clients  |
| 35  | vw_cb_landlord_account_017 | CHLACC | cb_landlord_account |

All views are in `chabrins_to_postgres_views.sql`.  
DDL is in `returns_ddl.sql`.

---

## Pre-stamped FK Columns

These columns were already added to the CHABRINS source tables by the migration tool:

- **CHRHMS**: `return_id`
- **CHRTXS**: `return_line_id`, `property_id`, `client_id`, `zone_id`, `field_officer_id`, `zone_supervisor_id`, `zone_manager_id`, `unit_id`, `tenant_id`, `return_id`, `invoice_id`
- **CHLACC**: `client_id`, `property_id`

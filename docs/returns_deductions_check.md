# Returns Deductions Check — Landlord Data

**Focus Return:** `R1202603LAN-00601019G`
**Property:** MWALIMU PLAZA | **Landlord:** LAN-00601 | **Period:** March 2026 | **Status:** PAID ✓

---

## 1. Decoding the Return Code

Pattern: `R{n}{YYYYMM}{landlord_code}{property_code}`

| Segment   | Value     | Meaning                             |
| --------- | --------- | ----------------------------------- |
| `R1`      | R1        | MAINRETURN (primary monthly return) |
| `202603`  | 202603    | Period: March 2026                  |
| `LAN-006` | LAN-00601 | Landlord code (reference_number)    |
| `01019G`  | 019G      | Property code (reference_number)    |

> SQL generation code (ClientReturnsController → processGenerate):
> `'R1' || return_period || landlord_reference_number || property_reference_number`

---

## 2. Actual Return Data — Verified from Database

### 2.1 Summary (`cb_returns_summary`)

| Field                | Value                    | Notes                                                           |
| -------------------- | ------------------------ | --------------------------------------------------------------- |
| `id`                 | 21059                    |                                                                 |
| `return_type`        | MAINRETURN               |                                                                 |
| `return_description` | MWALIMU PLAZA-March 2026 |                                                                 |
| `commission_rate`    | 8.50%                    |                                                                 |
| `total_credit`       | 192,000.00               | Sum of all payable CR lines                                     |
| `commission_amount`  | 16,320.00                | 8.5% × 192,000                                                  |
| `total_debit`        | 22,931.00                | ⚠️ See discrepancy note below                                   |
| `net_amount`         | 168,969.00               | What landlord receives                                          |
| `amount_paid`        | 168,969.00               | Cheque 158638, CO-OP bank                                       |
| `approved_by`        | LUCY KARIMI              | Approved 2026-03-09                                             |
| `paid_status`        | 1 (PAID)                 |                                                                 |
| `payable_amount`     | **0.00**                 | ⚠️ Should reflect payable rent — not backfilled (migrated data) |
| `non_payable_amount` | **0.00**                 | ⚠️ Not backfilled (migrated data)                               |
| `payable_units`      | **0**                    | ⚠️ Not backfilled (migrated data)                               |
| `total_units`        | **0**                    | ⚠️ Not backfilled (migrated data)                               |

### 2.2 The 4 Deductions — Confirmed (`cb_returns_clients`, DR lines)

| #                      | entry_type                             | net_amount    | narration                       | unit | tenant         |
| ---------------------- | -------------------------------------- | ------------- | ------------------------------- | ---- | -------------- |
| 1                      | **Commission**                         | 16,320.00     | MONTHLY COMMISSION (8.5%)       | —    | —              |
| 2                      | **VAT**                                | 2,611.00      | MONTHLY VAT (16% OF COMMISSION) | —    | —              |
| 3                      | **Recoveries**                         | 4,000.00      | PAID OWNER                      | S10  | JAMES WAITHAKA |
| 4                      | **Advance Payments-From Landlord Acc** | 100.00        | BANKING SERVICES-MWALIMU PLAZA  | —    | —              |
| **Total DR lines sum** |                                        | **23,031.00** |                                 |      |                |

### 2.3 ⚠️ Data Discrepancy — total_debit vs Net Amount

```
Sum of all 4 DR detail lines:  16,320 + 2,611 + 4,000 + 100 = 23,031.00
total_debit in cb_returns_summary:                              22,931.00  ← 100 short
Difference:                                                        100.00  ← the banking charge

BUT: net_amount IS correctly calculated including the 100:
  192,000 (total_credit) − 23,031 (all DR lines) = 168,969 ✓ matches net_amount
  192,000 (total_credit) − 22,931 (total_debit)  = 169,069 ✗ does NOT match net_amount
```

**Root cause:** This is a CHABRINS migration artefact. In CHABRINS, the `TOTALDR` field (CHRHMS) did NOT include the "Advance Payments / Banking Services" charge — that 100 was deducted separately when computing `NETAMT`. So `total_debit` in the migrated summary is 100 short of what the DR detail lines add up to. `net_amount` is correct; `total_debit` is understated by 100.

### 2.4 Credit Lines — All Tenants

| Unit | Tenant            | entry_type   | net_amount | amount_payable | pay_pct | payable_status | Notes                                      |
| ---- | ----------------- | ------------ | ---------- | -------------- | ------- | -------------- | ------------------------------------------ |
| A01  | LILIAN WAKO       | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| A02  | C/O MWALIMU PLAZA | RENT CURRENT | 0.00       | 0.00           | 0%      | null           | Owner unit — excluded                      |
| A03  | MARTIN KAGIRI     | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| B01  | OWNERS ACCOUNT    | RENT CURRENT | 0.00       | 0.00           | 0%      | null           | Owner unit — excluded                      |
| B02  | SHEM KARAMI       | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| B03  | CATHEINE MUTHONI  | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| S01  | JAPHETH NJAGI     | RENT CURRENT | 15,000.00  | 15,000.00      | 86.67%  | 1 ✓            | Just over 85% threshold                    |
| S02  | CHARLES MWIRIGI   | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| S03  | CHARLES MWIRIGI   | RENT CURRENT | 15,000.00  | 15,000.00      | 93.33%  | 1 ✓            |                                            |
| S04  | MOSES NJOROGE     | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| S05  | JOSEPH GITONGA    | RENT CURRENT | 15,000.00  | 15,000.00      | 100%    | 1 ✓            |                                            |
| S06  | GITAU KARIUKI     | RENT CURRENT | 20,000.00  | 20,000.00      | 100%    | 1 ✓            |                                            |
| S07  | IMELDA NDUKU      | RENT CURRENT | 17,000.00  | 17,000.00      | 100%    | 1 ✓            |                                            |
| S08  | C/O MWALIMU PLAZA | RENT CURRENT | 0.00       | 0.00           | 0%      | null           | Owner unit — excluded                      |
| S09  | C/O MWALIMU PLAZA | RENT CURRENT | 0.00       | 0.00           | 0%      | null           | Owner unit — excluded                      |
| S10  | JAMES WAITHAKA    | RENT CURRENT | 5,000.00   | 5,000.00       | 100%    | 1 ✓            | Reduced rent                               |
| S10  | JAMES WAITHAKA    | RENT ARREARS | 5,000.00   | 5,000.00       | —       | —              | Feb 2026 arrears paid                      |
| S11  | MERCY GATERE      | RENT CURRENT | 10,000.00  | 10,000.00      | 100%    | 1 ✓            |                                            |
| S12  | [VACANT]          | RENT CURRENT | 0.00       | 10,000.00      | 0%      | null           | Vacant — amount_payable recorded but net=0 |
| S13  | C/O MWALIMU PLAZA | RENT CURRENT | 0.00       | 0.00           | 0%      | null           | Owner unit — excluded                      |

**credit total: 187,000 (RENT CURRENT) + 5,000 (RENT ARREARS S10) = 192,000 ✓**

### 2.5 Explanation of 0-net Units

Units A02, B01, S08, S09, S13 — all named "C/O MWALIMU PLAZA" or "OWNERS ACCOUNT" — are owned/operated by the landlord themselves. In CHABRINS, owner-occupied units are included in the return with `RESAMTCR = 0` / `AMTPAYBL = 0`; they never contribute to TOTALCR.

Unit S12 `[VACANT]` has `amount_payable_landlord = 10,000` recorded (the contracted rent) but `net_amount = 0` because there is no paying tenant — standard CHABRINS behaviour for vacant units.

### 2.6 Unit S10 — JAMES WAITHAKA Special Case

This tenant has **3 lines** in this return:

- `RENT CURRENT` CR 5,000 — March 2026 rent (paid at 100%)
- `RENT ARREARS` CR 5,000 — February 2026 rent received late, included in this March return
- `Recoveries` DR 4,000 — "PAID OWNER" — the property owner was paid KES 4,000 directly; the return recovers it from the net payout

Net position for S10: +5,000 + 5,000 − 4,000 = +6,000 contribution to landlord's net.

---

## 3. Tables Involved in This Return

| Table                 | Source (CHABRINS) | Role                                                        |
| --------------------- | ----------------- | ----------------------------------------------------------- |
| `cb_returns_summary`  | CHRHMS            | Return header — one row for this return, financial totals   |
| `cb_returns_clients`  | CHRTXS            | Return detail lines — one row per tenant/unit/charge type   |
| `cb_landlord_account` | CHLACC            | Landlord account ledger — DR/CR entries after payment       |
| `cb_money_recoveries` | CHRACT            | Recoveries/deductions from within a return (DR class lines) |

### SQL to check this return directly:

```sql
-- Return header summary
SELECT * FROM cb_returns_summary
WHERE return_code = 'R1202603LAN-00601019G';

-- All detail lines (credits AND deductions)
SELECT
    entry_type, dr_cr, unit_code, tenant_name,
    amount_payable_landlord, net_amount,
    commission_amount, vat_amount,
    pay_percentage, payable_status,
    deduction_point, deduction_point_2,
    is_commissionable, narration
FROM cb_returns_clients
WHERE return_code = 'R1202603LAN-00601019G'
ORDER BY dr_cr DESC, entry_type, unit_code;

-- Debit/deduction lines only
SELECT
    entry_type, dr_cr, unit_code, tenant_name,
    net_amount, narration
FROM cb_returns_clients
WHERE return_code = 'R1202603LAN-00601019G'
  AND dr_cr = 'DR'
ORDER BY entry_type;

-- Landlord account entries for this property/landlord (March 2026)
SELECT
    entry_type_code, entry_description, entry_date,
    debit_amount, credit_amount, total_amount,
    return_code, pv_number
FROM cb_landlord_account
WHERE landlord_code = 'LAN-006'
  AND property_code = '01019G'
  AND year = 2026 AND month = 3
ORDER BY entry_date;

-- Money recoveries linked to this return
SELECT
    transaction_type, transaction_category,
    transaction_class, transaction_description,
    debit_amount, credit_amount, tenant_name,
    unit_code
FROM cb_money_recoveries cmr
JOIN cb_returns_summary crs ON crs.return_id = cmr.return_id
WHERE crs.return_code = 'R1202603LAN-00601019G';
```

---

## 3. Where Deductions Come From

### 3.1 Inside the Return — `cb_returns_clients` (CHRTXS)

Every return detail line carries a `dr_cr` flag (`CR` = credit to landlord, `DR` = deduction from landlord). Deductions in a MAINRETURN come from the following `entry_type` values:

| entry_type                           | dr_cr | Source / Description                                                                                                      |
| ------------------------------------ | ----- | ------------------------------------------------------------------------------------------------------------------------- |
| `Commission`                         | DR    | Property management commission — % of commissionable rent (from `sys_pm_service_agreements.commission_percentage`)        |
| `VAT`                                | DR    | 16% VAT on commission amount (from `sys_pm_service_agreements.vat_per`, default 16)                                       |
| `Deductions Before Commission`       | DR    | Pre-commission deductions — water bills, service charges, other costs recovered from rent before commission is calculated |
| `Add Back Deductions`                | CR    | Partial reversal of previous deductions (shows as credit)                                                                 |
| `Recoveries`                         | DR    | Misc recoveries — sourced from `cb_money_recoveries` (CHRACT) DR-class lines                                              |
| `Advance Payments-From Landlord Acc` | DR    | Recovery of cash advances previously given to the landlord                                                                |

### 3.2 Commission and VAT — Calculated During Return Generation

Commission and VAT are computed automatically in **Query 1** (INSERT rent lines):

```sql
-- commission_amount per line:
ROUND(i.total_amount * COALESCE(s.commission_percentage, 0) / 100, 2)

-- vat_amount per line:
ROUND(ROUND(i.total_amount * commission_percentage / 100, 2) * COALESCE(s.vat_per, 16) / 100, 2)
```

- `commission_percentage` comes from `sys_pm_service_agreements` (SLA for the property)
- `vat_per` defaults to 16 if not set on the SLA
- Only lines where `is_commissionable = 'Yes'` are commissionable
- If a tenant is **non-payable** (payable_status = 0, threshold < 85%): both `commission_amount` and `vat_amount` are **zeroed out** in Query 2 — the landlord is not charged commission on rent that was withheld

Summary totals in `cb_returns_summary`:

```
total_debit      = SUM(commission_amount) + SUM(vat_amount)   [from cb_returns_clients]
commission_amount = SUM(commission_amount)                    [commission only portion]
net_amount       = total_credit - total_debit
```

### 3.3 The 85% Payability Threshold and its Effect on Deductions

**Query 2** (UPDATE pay_percentage) determines whether a tenant's rent is payable:

```
threshold_percentage = (amount_paid_prev_month / total_prev_month_invoice) × 100

IF threshold_percentage >= 85%:
    payable_status = 1 (payable)
    net_amount     = amount_payable_landlord  (full rent — landlord gets 100%)
    commission_amount = calculated commission
    vat_amount        = calculated VAT
ELSE:
    payable_status = 0 (non-payable / withheld)
    net_amount     = 0   ← rent withheld entirely
    commission_amount = 0 ← no commission charged on withheld rent
    vat_amount        = 0 ← no VAT charged on withheld rent
```

Special cases:

- `payable_status = 2`: No prior invoice found → treated as payable (new tenant)
- `payable_status = 3`: Past invoice exists but passed threshold logic
- `pay_percentage = '100'` means fully paid up; `'0'` means nothing paid

### 3.4 Deduction Tracking Columns

Each line in `cb_returns_clients` has:

| Column              | Purpose                                                                        |
| ------------------- | ------------------------------------------------------------------------------ |
| `deduction_point`   | (int) Which calculation step the deduction occurs at (before/after commission) |
| `deduction_point_2` | (varchar) Secondary deduction classification text                              |
| `is_vatable`        | `Yes`/`No` — whether VAT applies to this line                                  |
| `is_commissionable` | `Yes`/`No` — whether commission applies to this line                           |

Origins from CHABRINS CHRTXS fields `DEDPNT` and `DEDPNT2`.

---

## 4. The Financial Calculation Flow for This Return

```
cb_returns_summary fields for R1202603LAN-00601019G:

  total_credit       = SUM of net_amount WHERE dr_cr = 'CR'
                     = payable rent only (non-payable tenants zeroed out)

  commission_total   = same as total_credit (commissionable base)
  commission_amount  = SUM(commission_amount from cb_returns_clients)
                     = total_credit × commission_rate%

  vatable_total      = commissionable amount subject to VAT
  vat                = commission_amount × 16%

  total_debit        = commission_amount + vat_amount
                       (+ any other DR lines like Deductions Before Commission)

  net_amount         = total_credit − total_debit
                     = what the landlord actually receives

  payable_amount     = SUM of net_amount for RENT CURRENT lines WHERE payable_status IN (1,2,3)
  non_payable_amount = SUM of amount_payable_landlord for RENT CURRENT WHERE payable_status = 0
```

### Verification formula:

```sql
SELECT
    SUM(CASE WHEN dr_cr = 'CR' THEN net_amount ELSE 0 END)                          AS total_cr,
    SUM(CASE WHEN dr_cr = 'DR' THEN net_amount ELSE 0 END)
      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS')
                 THEN COALESCE(commission_amount,0) + COALESCE(vat_amount,0) ELSE 0 END) AS total_dr,
    SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS') THEN net_amount ELSE 0 END) AS rent_total,
    SUM(CASE WHEN entry_type = 'Commission' THEN net_amount ELSE 0 END)
      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS')
                 THEN COALESCE(commission_amount,0) ELSE 0 END)                     AS commission,
    SUM(CASE WHEN entry_type = 'VAT' THEN net_amount ELSE 0 END)
      + SUM(CASE WHEN entry_type IN ('RENT CURRENT','RENT ARREARS')
                 THEN COALESCE(vat_amount,0) ELSE 0 END)                            AS vat,
    SUM(CASE WHEN entry_type = 'Deductions Before Commission' THEN net_amount ELSE 0 END) AS other_deductions,
    SUM(CASE WHEN entry_type = 'Add Back Deductions' THEN net_amount ELSE 0 END)    AS add_back
FROM cb_returns_clients
WHERE return_code = 'R1202603LAN-00601019G';
```

---

## 5. Deductions Outside the Return — `cb_landlord_account` (CHLACC)

The landlord account carries **direct debit entries** that are NOT part of any return. These deductions appear on the landlord's account balance but come from separate charges:

| Entry Code | Description                  | Direction  | Frequency    |
| ---------- | ---------------------------- | ---------- | ------------ |
| `0001`     | Electricity Bill (1st)       | Debit      | Common       |
| `0002`     | Electricity Bill Charge      | Debit      | Common       |
| `0003`     | Water Bill (2nd instalment)  | Debit      | Common       |
| `0004`     | Water Bill Charge            | Debit      | Common       |
| `0005`     | RETURNS (credit from return) | **Credit** | Every return |
| `0006`     | Garbage Payments             | Debit      | 16,874       |
| `0009`     | Agent Fees                   | Debit      | 12,299       |
| `0013`     | Refund of Deposit            | Debit      | 3,706        |
| `0014`     | Cancelled Cheque Charges     | Debit      | 16,895       |
| `0015`     | Caretaker Salary             | Debit      | 3,706        |
| `0019`     | Banking Services             | Debit      | 13,415       |
| `0020`     | Loan                         | Debit      | 2,443        |

**Key distinction:**

- Entries with a `return_code` value → originated from a return (RETURNID in CHLACC)
- Entries with `return_code = NULL` → direct charges, have nothing to do with the return

The `net_amount` in `cb_returns_summary` does NOT include the charges in `cb_landlord_account`. Those come off the landlord's running balance **after** the return is posted.

---

## 6. `cb_money_recoveries` — In-Return DR Deductions (CHRACT)

This table (source: CHABRINS CHRACT) carries the deduction and recovery transactions that are **included within a return's DR lines**. These are the internal DR entries within `cb_returns_clients`:

| transaction_class | transaction_description | Purpose                                          |
| ----------------- | ----------------------- | ------------------------------------------------ |
| DR                | SAT DEPOSIT             | Tenant security/advance deposit recovery         |
| DR                | PAID OWNER              | Amounts already paid directly to owner recovered |
| DR                | ABSCONDED               | Tenant absconded — written off                   |
| DR                | OVERPAID                | Over-payment recovery                            |
| DR                | PAID VACANT             | Paid for vacant unit — recovery                  |
| CR                | RENT FOR [MONTH]        | Standard rent credit (218,213 rows)              |

These DR recovery entries flow into `cb_returns_clients` as `Recoveries` or `Deductions Before Commission` entry types, and they are counted in `total_debit` of `cb_returns_summary`.

---

## 7. Complete Deductions Flow Diagram

```
Return R1202603LAN-00601019G (March 2026 MAINRETURN)
│
├── cb_returns_clients (detail lines)
│   │
│   ├── CREDITS (dr_cr = 'CR')
│   │   ├── RENT CURRENT        → net_amount = full rent (if payable_status ≥ 1)
│   │   │                                   = 0            (if payable_status = 0, < 85%)
│   │   ├── RENT ARREARS        → catch-up arrears lines
│   │   └── Add Back Deductions → partial reversal of prior deductions
│   │
│   └── DEBITS (dr_cr = 'DR')  ← these are the DEDUCTIONS
│       ├── Commission          → commission_percentage% of net_amount for commissionable lines
│       ├── VAT                 → 16% × commission_amount (vat_per from SLA)
│       ├── Deductions Before Commission → water/service charge recoveries (pre-commission)
│       └── Recoveries          → SAT DEPOSIT, PAID OWNER, ABSCONDED (from cb_money_recoveries)
│
├── cb_returns_summary (totals)
│   ├── total_credit      = SUM of all CR net_amount
│   ├── total_debit       = SUM of all DR net_amount + embedded commission & VAT
│   ├── commission_amount = commission portion only
│   └── net_amount        = total_credit − total_debit  ← final landlord payment
│
└── cb_landlord_account (after return is paid)
    ├── 0005 RETURNS (CR)    → net_amount credited to landlord account
    ├── 0006 FINAL PAYMENT (DR) → cheque/voucher paid out to landlord
    └── Direct charges (no return_code):
        0006 Garbage, 0009 Agent Fees, 0014 Cheque Charges,
        0015 Caretaker, 0019 Banking — deducted from account balance
```

---

## 8. Commission Rate Source

Commission rate for a property comes from `sys_pm_service_agreements`:

```sql
SELECT
    sla.commission_percentage,
    sla.vat_per,
    sla.client_d AS landlord_id,
    p.reference_number AS property_code,
    p.property_name
FROM sys_pm_service_agreements sla
JOIN sys_pm_properties p ON p.id = sla.property_id
WHERE p.reference_number = '01019G';
```

- `commission_percentage` — e.g. 10.00 means 10%
- `vat_per` — defaults to 16 (16% VAT on commission)
- One SLA per property (verified — no duplicates)

This rate is carried to `cb_returns_summary.commission_rate` at return generation time.

---

## 9. Why a Tenant Might Show 0 in Deductions

If a tenant's `payable_status = 0` (below 85% threshold):

- `net_amount = 0` — rent withheld
- `commission_amount = 0` — no commission charged
- `vat_amount = 0` — no VAT charged
- The tenant **still appears** in `cb_returns_clients` with `amount_payable_landlord` = full rent (record-keeping only)
- The withheld rent is tracked in `cb_returns_summary.non_payable_amount`

Withheld rent is NOT retroactively paid in a future MAINRETURN. It may be recovered via a **MINIRETURN** (R2/R3/R4) if the tenant later clears arrears, captured as `OTHER PAYMENTS/DEPOSITS` entry type in a separate return.

---

## 10. Summary of Data Sources for Deductions

| Deduction Type            | Where Stored                                 | Column(s)                                                            | Source Table                  |
| ------------------------- | -------------------------------------------- | -------------------------------------------------------------------- | ----------------------------- |
| Commission                | `cb_returns_clients`                         | `commission_amount`                                                  | Calculated from SLA           |
| VAT on Commission         | `cb_returns_clients`                         | `vat_amount`                                                         | Calculated from SLA           |
| Pre-commission deductions | `cb_returns_clients`                         | `net_amount` WHERE entry_type = 'Deductions Before Commission'       | CHRTXS (CHACT)                |
| In-return recoveries      | `cb_returns_clients` / `cb_money_recoveries` | `net_amount` WHERE entry_type = 'Recoveries'                         | CHRTXS / CHRACT               |
| Advance recovery          | `cb_returns_clients`                         | `net_amount` WHERE entry_type = 'Advance Payments-From Landlord Acc' | CHRTXS                        |
| Summary total debit       | `cb_returns_summary`                         | `total_debit`                                                        | Aggregated from above         |
| Direct account charges    | `cb_landlord_account`                        | `debit_amount` (entry 0006,0009,0014,0015,0019...)                   | CHLACC (separate from return) |

---

## 11. Known Data Issues — This Return (Migrated from CHABRINS)

### Issue 1 — `total_debit` understated by 100

- **Symptom:** `total_debit = 22,931` but sum of all DR lines = 23,031
- **Cause:** CHABRINS stored the Banking Services advance charge (100) outside the formal TOTALDR field; NETAMT was still correct. The migration preserved both values as-is.
- **Impact:** Low — `net_amount` (168,969) and `amount_paid` (168,969) are correct. `total_debit` just labels 100 less than it should.
- **Fix query if needed:**
    ```sql
    UPDATE cb_returns_summary
    SET total_debit = 22931 + 100  -- = 23031
    WHERE return_code = 'R1202603LAN-00601019G';
    -- (only if you want the column to be consistent with the DR lines)
    ```

### Issue 2 — `payable_amount`, `non_payable_amount`, `payable_units`, `total_units` all = 0

- **Symptom:** All four threshold-tracking columns are zero despite there being 13 payable units
- **Cause:** These columns were added to the CHIPS system in a later migration and were never backfilled from the migrated CHABRINS data. Only returns generated by the new `processGenerate()` function will populate them.
- **What the correct values should be (calculated from detail lines):**
    ```sql
    SELECT
      SUM(CASE WHEN entry_type = 'RENT CURRENT' AND payable_status IN (1,2,3) THEN net_amount ELSE 0 END) AS payable_amount,
      SUM(CASE WHEN entry_type = 'RENT CURRENT' AND (payable_status = 0 OR payable_status IS NULL) THEN amount_payable_landlord ELSE 0 END) AS non_payable_amount,
      COUNT(DISTINCT CASE WHEN entry_type = 'RENT CURRENT' AND payable_status IN (1,2,3) THEN unit_id END) AS payable_units,
      COUNT(DISTINCT CASE WHEN entry_type = 'RENT CURRENT' THEN unit_id END) AS total_units
    FROM cb_returns_clients
    WHERE return_code = 'R1202603LAN-00601019G';
    ```
    Expected: payable_amount ≈ 182,000 (all paying tenants), non_payable ≈ 10,000 (S12 vacant), payable_units = 13, total_units ≈ 19

### Issue 3 — `commission_amount` and `vat_amount` = 0 on all RENT CURRENT lines

- **Symptom:** Every RENT CURRENT and RENT ARREARS line in `cb_returns_clients` shows `commission_amount = 0` and `vat_amount = 0`
- **Cause:** In CHABRINS (CHRTXS), commission and VAT were NOT stored per-unit; they were a single summary DR line. Commission is stored as its own separate line (`entry_type = 'Commission'`). The per-unit commission columns were only introduced in the new CHIPS generation system.
- **Impact:** Any code relying on `cb_returns_clients.commission_amount` or `vat_amount` for **migrated** returns will get 0. Always look at the Commission DR line for total commission on migrated returns.

---

_Generated: March 2026 | Verified against live DB: March 28, 2026_
_Sources: ClientReturnsController.php, returns_ddl.sql, returns_flow.md, returns_check_plan.md, GeneralDDLs.sql_

# Returns Threshold & Payment Tracking — CHABRINS Analysis

## 1. Overview

This document describes how the old CHABRINS system tracks returns that did **not meet the 85% payment threshold**, how withheld amounts are handled, and how tenants eventually "catch up" in subsequent batches. Findings are backed by actual CHABRINS data.

---

## 2. Table Roles

| Table      | Role                                                                                                                                                                    |
| ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CHRHMS** | Return **summary/header** — one row per property per period. Tracks NETAMT, AMTPAYD, approval workflow, overdraft.                                                      |
| **CHRTXS** | Return **detail lines** — per tenant, per charge type. Contains `PAYPERC` (threshold %), `AMTPAYBL` (amount payable to landlord), `PPERIOD` (payment period reference). |
| **CHLACC** | **Landlord account ledger** — DR/CR entries for returns credits, final payments, advances, deductions.                                                                  |

---

## 3. Return Types (from CHRHMS)

| Code Prefix | RETTYPE      | Description                                                   | Count (2025) |
| ----------- | ------------ | ------------------------------------------------------------- | ------------ |
| **R1**      | MAINRETURN   | Primary monthly return — all tenants, rent + commission + VAT | 15,070       |
| **R1**      | MASTERRETURN | Multi-property landlord aggregate (sums multiple properties)  | 2,459        |
| **R2**      | MINIRETURN   | Catch-up return for additional payments/deposits              | 1,078        |
| **R3**      | MINIRETURN   | Second catch-up batch                                         | 66           |
| **R4**      | MINIRETURN   | Third catch-up batch                                          | 13           |
| **R5–R6**   | MINIRETURN   | Further catch-up batches (rare)                               | 8            |

### Key insight: R1 = main rent return, R2/R3/R4 = incremental catch-up batches

---

## 4. How the 85% Threshold Works

### 4.1 PAYPERC Distribution (CHRTXS, RENT CURRENT lines)

| Category                | Count   | % of Total |
| ----------------------- | ------- | ---------- |
| **Payable (≥ 85%)**     | 201,504 | 72.0%      |
| **Non-payable (< 85%)** | 78,272  | 28.0%      |

Top PAYPERC values: `100%` (175,398), `0%` (69,205), `90%` (692), `85.71%` (516)

### 4.2 Two Key Columns: AMTPAYBL vs RESAMTCR (Net Amount)

Every tenant appears in the **MAINRETURN** regardless of whether they meet the threshold. But there are **two different amount columns** with distinct roles:

| Column       | Purpose                                                       | When payable (≥ 85%) | When non-payable (< 85%) |
| ------------ | ------------------------------------------------------------- | -------------------- | ------------------------ |
| **AMTPAYBL** | Record-keeping — always the full rent amount                  | Full rent            | Full rent                |
| **RESAMTCR** | **Actual net amount** — used for TOTALCR & NETAMT calculation | **Full rent (100%)** | **0**                    |

```
Property 364E, Dec 2025 — RENT CURRENT lines:
┌─────────────────────────────┬─────────┬──────────┬──────────┬───────────┐
│ Tenant                      │ PAYPERC │ AMTPAYBL │ RESAMTCR │ Status    │
├─────────────────────────────┼─────────┼──────────┼──────────┼───────────┤
│ GALAXY MOBILE ACCESSORIES   │ 95.79%  │ 327,584  │ 327,584  │ Payable   │
│ PURPINK GIFTS LIMITED       │ 97.84%  │ 141,694  │ 141,694  │ Payable   │
│ SUPER FOAM LIMITED          │ 98.79%  │ 242,904  │ 242,904  │ Payable   │
│ ...                         │ ...     │ ...      │ ...      │ ...       │
│ BATA SHOE KENYA PLC         │ 0%      │ 319,696  │ 0        │ Withheld  │
│ CKEI HOME DÉCOR             │ 0%      │ 146,647  │ 0        │ Withheld  │
│ CLUTCH & STRUTS INVESTMENTS │ 8.26%   │ 122,803  │ 0        │ Withheld  │
│ EVANGELINE NGUGI            │ 52.28%  │ 103,936  │ 0        │ Withheld  │
│ CITYWALK FRAGRANCE          │ 81.15%  │ 319,696  │ 0        │ Withheld  │
│ [VACANT]                    │ 0%      │ 647,280  │ 0        │ Excluded  │
└─────────────────────────────┴─────────┴──────────┴──────────┴───────────┘
```

### 4.3 Key Rule: Binary Threshold (All or Nothing)

> **When a tenant's pay percentage ≥ 85%, the landlord receives 100% of the full rent — NOT just the 85% collected.**
> **When below 85%, the landlord receives 0 for that tenant — the net amount is zeroed out.**

This is a binary gate, not a proportional calculation.

### 4.4 How RESAMTCR Drives the Summary (CHRHMS)

TOTALCR in the summary = SUM(RESAMTCR) for all CR lines. Since RESAMTCR = 0 for non-payable tenants, TOTALCR **already excludes** withheld rent.

**Formula:** `NETAMT = SUM(all RESAMTCR) - SUM(all RESAMTDR)`

```
Property 364E, Dec 2025:
  TOTALCR (= sum of RESAMTCR for CR lines) = 8,430,987.87
  SUM(RESAMTDR for ALL DR lines)            = 421,549.00
  NETAMT                                    = 8,009,438.87  ✓
```

**Verified across 15 random properties:** NETAMT always = SUM(RESAMTCR) - SUM(RESAMTDR).

### 4.5 Property 556A Example (Before/After Threshold)

**October 2025** (all PAYPERC blank — all tenants payable):

| Field   | Value      | Meaning                                     |
| ------- | ---------- | ------------------------------------------- |
| TOTALCR | 304,151.60 | All rent included (RESAMTCR = full for all) |
| TOTALDR | 24,892.00  | Commission + VAT + Recoveries               |
| NETAMT  | 279,259.60 | = TOTALCR − TOTALDR                         |

**December 2025** (5 tenants at 0% = non-payable):

| Field   | Value      | Meaning                                                   |
| ------- | ---------- | --------------------------------------------------------- |
| TOTALCR | 322,321.25 | Only payable rent included (RESAMTCR = 0 for non-payable) |
| TOTALDR | 21,935.00  | Commission + Recoveries                                   |
| NETAMT  | 122,820.25 | = TOTALCR − TOTALDR − Advance recoveries (177,566)        |

The 3 non-payable tenants (NICHOLUS WAMBUGU, ROSE CHEGE, SHARON MUTUA) each at AMTPAYBL = 6,500 have RESAMTCR = 0, so their rent is excluded from TOTALCR entirely.

---

## 5. The MINIRETURN Catch-Up Mechanism

### 5.1 What is a MINIRETURN?

When tenants make additional payments (deposits, water payments, arrears clearance), a **MINIRETURN** (R2/R3/R4...) is generated to remit those amounts to the landlord. It operates independently of the MAINRETURN.

### 5.2 MINIRETURN Structure

```
R2202512LAN-00409556A (MINIRETURN for property 556A, Dec 2025):
┌──────────────────────┬───────────────────────┬────┬─────────┬─────────┐
│ Tenant               │ ENTRYTYP              │D/C │ Amount  │ PPERIOD │
├──────────────────────┼───────────────────────┼────┼─────────┼─────────┤
│ (property-level)     │ Advance Payments...   │ DR │ 100.00  │ (null)  │
│ ALICE NJOKI MBURU    │ OTHER PAYMENTS/DEPOSITS│ CR │ 322     │ 202511  │
│ ALICE NJOKI MBURU    │ OTHER PAYMENTS/DEPOSITS│ CR │ 150     │ 202512  │
│ AMBROSE MBITHI       │ OTHER PAYMENTS/DEPOSITS│ CR │ 311     │ 202511  │
│ AMBROSE MBITHI       │ OTHER PAYMENTS/DEPOSITS│ CR │ 150     │ 202512  │
│ ANDERSON MATANGI     │ OTHER PAYMENTS/DEPOSITS│ CR │ 281     │ 202511  │
│ ANDERSON MATANGI     │ OTHER PAYMENTS/DEPOSITS│ CR │ 150     │ 202512  │
│ ...                  │ ...                    │ ...│ ...     │ ...     │
└──────────────────────┴───────────────────────┴────┴─────────┴─────────┘
```

### Key fields:

- **`PPERIOD`**: Reference to which historical period the payment covers (e.g., `202511` = Nov 2025)
- **`ENTRYTYP`**: Always `OTHER PAYMENTS/DEPOSITS` for CR lines
- **`Advance Payments-From Landlord Acc`**: DR deduction line for advances previously given

### 5.3 PPERIOD Distribution (How Far Back Catch-Ups Go)

For R2 MINIRETURNs generated Jul–Dec 2025:

| Return Period | Oldest PPERIOD | Newest PPERIOD | Line Count |
| ------------- | -------------- | -------------- | ---------- |
| 202507        | 202502         | 202508         | 4,919      |
| 202508        | 202409         | 202509         | 5,431      |
| 202509        | 202407         | 202510         | 5,418      |
| 202510        | 202504         | 202511         | 5,572      |
| 202511        | 202307         | 202512         | 5,365      |
| 202512        | 202310\*       | 202512         | 3,566      |

**Catch-ups can go back 6+ months, sometimes over a year.**

### 5.4 How Many Properties Get MINIRETURNs?

| Month    | MAINRETURN Count | MINIRETURN Count | Mini Net Value |
| -------- | ---------------- | ---------------- | -------------- |
| Jul 2025 | 1,249            | 82               | 3,435,127      |
| Aug 2025 | 1,261            | 106              | 3,474,402      |
| Sep 2025 | 1,278            | 90               | 3,173,090      |
| Oct 2025 | 1,293            | 108              | 2,795,098      |
| Nov 2025 | 1,292            | 105              | 2,700,339      |
| Dec 2025 | 1,296            | 82               | 2,188,940      |

About **6-8%** of properties receive a MINIRETURN each month.

---

## 6. Entry Types in MINIRETURN Detail Lines

| Entry Type                         | DR/CR | Count  | Purpose                         |
| ---------------------------------- | ----- | ------ | ------------------------------- |
| OTHER PAYMENTS/DEPOSITS            | CR    | 30,552 | Tenant payments being caught up |
| Advance Payments-From Landlord Acc | DR    | 1,010  | Recovery of advances            |
| RENT ARREARS                       | CR    | 199    | Arrears payments received       |
| Recoveries                         | DR    | 187    | Other recoveries                |
| RETURN NET SUM                     | CR    | 124    | Net summary lines               |
| Other Receipts-From Landlord Acc   | CR    | 47     | Misc landlord receipts          |

---

## 7. Landlord Account Flow (CHLACC)

### 7.1 Entry Codes

| Code | Description             | Direction  | Purpose                                 |
| ---- | ----------------------- | ---------- | --------------------------------------- |
| 0005 | RETURNS                 | **Credit** | Return net credited to landlord account |
| 0006 | FINAL PAYMENT           | **Debit**  | Cheque/payment issued to landlord       |
| 0008 | Opening/Closing Balance | Both       | Period opening/closing balance          |
| 0009 | ADVANCE PAYMENTS        | Debit      | Advances given to landlord              |
| 0012 | CARETAKER SALARY        | Debit      | Caretaker salary deduction              |
| 0013 | REFUND OF DEPOSITS      | Debit      | Tenant deposit refunds                  |
| 0014 | CHEQUE CHARGES          | Debit      | Banking charges                         |

### 7.2 Real Example: Landlord LAN-00409 (Property 556A), Oct-Dec 2025

```
October 2025:
  0005 RETURNS (CR): 279,260 + 48,605 + 28,345 = 356,210 credited
  0006 FINAL PAYMENT (DR): 28,245 + 91,716 = 119,961 paid out
  0009 ADVANCE (DR): 170,000 advance given
  0013 REFUNDS (DR): various deposit refunds

November 2025:
  0005 RETURNS (CR): 310,763 + 48,605 + 26,290 = 385,658 credited
  0006 FINAL PAYMENT (DR): 26,190 + 133,040 = 159,230 paid out

December 2025:
  0005 RETURNS (CR): 300,386 + 48,605 + 23,762 = 372,753 credited
  0006 FINAL PAYMENT (DR): 23,662 + 122,820 = 146,482 paid out
```

### 7.3 Flow Summary

```
┌───────────────────────┐     ┌────────────────────────────┐
│  Return Generated     │     │  Landlord Account (CHLACC) │
│  (CHRHMS + CHRTXS)    │────▶│  0005 RETURNS = CR         │
│                       │     │  (full return net credited) │
└───────────────────────┘     └──────────┬─────────────────┘
                                         │
                              ┌──────────▼─────────────────┐
                              │  Approval + Payment        │
                              │  0006 FINAL PAYMENT = DR   │
                              │  (only approved amount)    │
                              └──────────┬─────────────────┘
                                         │
                              ┌──────────▼─────────────────┐
                              │  Remaining Balance         │
                              │  Non-payable rent stays    │
                              │  as credit on landlord acct│
                              └────────────────────────────┘
```

---

## 8. Tenant Lifecycle: Below Threshold → Catch-Up

### 8.1 Example: Tracking Specific Tenants on Property 364E

| Tenant             | Sep 2025 | Oct 2025 | Nov 2025 | Dec 2025 |
| ------------------ | -------- | -------- | -------- | -------- |
| EVANGELINE NGUGI   | 90.53% ✓ | 61.75% ✗ | 100% ✓   | 52.28% ✗ |
| CKEI HOME DÉCOR    | 100% ✓   | 68.19% ✗ | 22.79% ✗ | 0% ✗     |
| CLUTCH & STRUTS    | 100% ✓   | 100% ✓   | 100% ✓   | 8.26% ✗  |
| BATA SHOE KENYA    | 100% ✓   | 100% ✓   | 100% ✓   | 0% ✗     |
| FAITH CLASSIC      | 100% ✓   | 100% ✓   | 46.87% ✗ | 0% ✗     |
| CITYWALK FRAGRANCE | 100% ✓   | 100% ✓   | 100% ✓   | 69-81% ✗ |

✓ = ≥ 85% (payable) ✗ = < 85% (withheld)

**EVANGELINE NGUGI** shows the most interesting pattern:

- Sep: 90.53% → payable → rent included in landlord payment
- Oct: 61.75% → non-payable → rent withheld
- Nov: 100% → payable again → rent included (catches up)
- Dec: 52.28% → non-payable again → rent withheld

### 8.2 What Happens to Withheld Rent?

The withheld rent is **NOT lost** — but the MAINRETURN does NOT retroactively pay past withheld months:

**Verified with EVANGELINE NGUGI on 364E:**

| Month | PAYPERC | RESAMTCR (net_amount) | What Happens                            |
| ----- | ------- | --------------------- | --------------------------------------- |
| Oct   | 61.75%  | **0**                 | Non-payable — rent withheld entirely    |
| Nov   | 100%    | **103,936**           | Payable — ges only November's full rent |
| Dec   | 52.28%  | **0**                 | Non-payable again                       |

**November does NOT include a catch-up for October's withheld 103,936.** Each month's MAINRETURN is independent — it pays only that month's rent if the tenant meets threshold.

Catch-up paths for withheld rent:

1. **Same-month AMTPAYBL**: The full rent is recorded in `AMTPAYBL` for audit trail, but `RESAMTCR = 0` means it doesn't flow to the landlord.
2. **Next month**: If the tenant pays arrears and reaches ≥ 85% next month, only that new month's rent is paid in full. Previous withheld amounts are not re-evaluated.
3. **MINIRETURN catch-up**: Separate R2/R3/R4 returns may capture additional payments (deposits, arrears, etc.) as `OTHER PAYMENTS/DEPOSITS` lines.

---

## 9. Summary: CHABRINS Threshold Payment Flow

```
Month N:
  1. Invoice generated for all tenants
  2. Returns generated (R1 MAINRETURN):
     - Each tenant gets a RENT CURRENT line with PAYPERC
     - PAYPERC = (amount_paid / total_invoiced) × 100 for PREVIOUS month
     - AMTPAYBL = full rent amount (always, for record-keeping)
     - If PAYPERC ≥ 85%: RESAMTCR = full rent → counts toward TOTALCR
     - If PAYPERC < 85%: RESAMTCR = 0 → excluded from TOTALCR
  3. Commission & VAT calculated on TOTALCR (payable-only amounts)
  4. NETAMT = SUM(RESAMTCR) - SUM(RESAMTDR) = payable credit - all debits
  5. Return approved → NETAMT credited to landlord account (CHLACC 0005)
  6. Cheque issued → AMTPAYD debited from landlord account (CHLACC 0006)

  KEY RULE: Landlord gets 100% of rent when threshold met, 0% when not met.
  Each month is independent — no retroactive catch-up in MAINRETURN.

  Separately:
  7. MINIRETURN (R2) generated for additional payments/deposits:
     - Captures misc receipts from ALL tenants
     - PPERIOD tracks which period each payment covers
     - Can catch up payments from 1-12+ months back
     - Goes through same approval/payment workflow

Month N+1:
  8. New invoices → new PAYPERC calculated based on Month N payments
  8. Tenants who paid up → PAYPERC ≥ 85% → now payable
  9. Tenants still delinquent → PAYPERC < 85% → still withheld
  10. Cycle repeats
```

---

## 10. Implications for CHIPS (New System)

### Column Mapping: CHABRINS → CHIPS

| CHABRINS (CHRTXS) | CHIPS (cb_returns_clients) | Rule                                                          |
| ----------------- | -------------------------- | ------------------------------------------------------------- |
| `AMTPAYBL`        | `amount_payable_landlord`  | Always = full rent (record-keeping)                           |
| `RESAMTCR`        | `net_amount`               | **= full rent if payable, = 0 if non-payable**                |
| `PAYPERC`         | `pay_percentage`           | Text: "100%", "85.71%", "0%"                                  |
| —                 | `threshold_percentage`     | Numeric: 100.00, 85.71, 0.00                                  |
| —                 | `payable_status`           | 0=non-payable, 1=payable, 2=no-past-invoice, 3=past-threshold |

### net_amount Logic (Implemented)

```
After calculating pay_percentage:
  IF payable_status IN (1, 2, 3):
    net_amount = amount_payable_landlord  (full rent — landlord gets 100%)
  ELSE:
    net_amount = 0                         (withheld — landlord gets 0)
```

Commission and VAT are then recalculated based on the updated payable net_amount totals so that the landlord is not charged commission on withheld rent.

### Summary Table (cb_returns_summary) Calculations

The summary `net_amount = total_credit - total_debit` will automatically reflect the correct payable amounts because:

- `total_credit = SUM(net_amount WHERE dr_cr='CR')` — which now excludes non-payable rent
- `total_debit = SUM(net_amount WHERE dr_cr='DR')` — recalculated commission + VAT
- `payable_amount` = SUM of net_amount for payable RENT CURRENT lines
- `non_payable_amount` = SUM of amount_payable_landlord for non-payable RENT CURRENT lines

### What Still Needs to Be Built

1. **MAINRETURN generation** (R1): ✅ Implemented in `processGenerate()` with `pay_percentage`, `threshold_percentage`, `payable_status`, and **threshold-aware `net_amount`** (= 0 for non-payable, full rent for payable).

2. **Summary aggregation**: ✅ Implemented — `cb_returns_summary` now has `payable_amount`, `non_payable_amount`, `payable_units`, `total_units`. Summary `net_amount` and `total_credit` reflect payable-only amounts.

3. **MINIRETURN generation** (R2+): **NOT YET IMPLEMENTED**. Needs:
    - A mechanism to scan for additional payments received since the MAINRETURN
    - Entry type `OTHER PAYMENTS/DEPOSITS` equivalent
    - `PPERIOD` tracking to reference which past period payments cover
    - Separate approval workflow
    - A way to generate R2/R3/R4 sequentially

4. **MASTERRETURN aggregation**: **NOT YET IMPLEMENTED**. Multi-property landlord summary.

5. **Landlord account integration**: When returns are approved:
    - Credit `sys_pm_landlord_accounts_transaction` with return net (entry 0005 equivalent)
    - On payment, debit with `FINAL PAYMENT` (entry 0006 equivalent)
    - Track `payable_status` per line for threshold compliance

6. **Historical catch-up**: The system needs to check previous N months' payments and generate catch-up returns for amounts that have since cleared the threshold. PPERIOD values show catch-ups go back 6-12+ months.

### Open Questions

- **Q1**: In CHABRINS, does the MINIRETURN only capture _new_ payments received after the MAINRETURN was generated, or does it also re-evaluate previously withheld rent?
- **Q2**: How does the MASTERRETURN relate to payment — is the cheque issued per MASTERRETURN or per MAINRETURN?
- **Q3**: What triggers MINIRETURN generation — is it automatic on a schedule, or manual?
- **Q4**: How should the 85% threshold interact with the `past_threshold` rule (status 3) — if previous month was ≥ 85% but current is < 85%, is it still payable?

---

## 11. Data Summary Tables

### CHRHMS Columns Relevant to Threshold

| Column  | Type    | Description                                    |
| ------- | ------- | ---------------------------------------------- |
| NETAMT  | decimal | Net payable amount (payable rent − deductions) |
| AMTPAYD | decimal | Actual amount paid to landlord                 |
| AMTOVD  | decimal | Overdraft/withheld amount                      |
| OVDPER  | decimal | Overdraft percentage (e.g., 0.030 = 3%)        |
| AUTH2M  | int     | Authorization flag (always 0 in data)          |
| AUTHUN  | int     | Authorized units (always 0 in data)            |
| PAIDST  | int     | Payment status (0=unpaid, 1=paid)              |
| APPST   | int     | Approval status (0=pending, 1=approved)        |

### CHRTXS Columns Relevant to Threshold

| Column   | Type         | Description                                                          |
| -------- | ------------ | -------------------------------------------------------------------- |
| PAYPERC  | varchar(50)  | Payment percentage (e.g., "100%", "85.71%", "0%")                    |
| AMTPAYBL | decimal      | Amount payable to landlord (always = full rent)                      |
| AMTPAYTN | decimal      | Amount payable by tenant                                             |
| PPERIOD  | int          | Payment period reference (YYYYMM) — used in MINIRETURNs              |
| ENTRYTYP | varchar(100) | Line type: RENT CURRENT / Commission / VAT / OTHER PAYMENTS/DEPOSITS |
| DEDPNT   | int          | Deduction point (always NULL in sampled data)                        |
| DEDPNT2  | varchar(50)  | Deduction point 2 (always NULL in sampled data)                      |

### CHLACC Entry Codes

| Code | Direction | Description                     | 2025 Count |
| ---- | --------- | ------------------------------- | ---------- |
| 0005 | Credit    | Returns credited to landlord    | 14,796     |
| 0006 | Debit     | Final payment (cheque issued)   | 8,756      |
| 0008 | Both      | Opening/Closing Balance B/F/C/F | 1,344      |
| 0009 | Debit     | Advance payments to landlord    | 6,527      |
| 0012 | Debit     | Caretaker salary deductions     | 2,048      |
| 0013 | Debit     | Refund of deposits              | 3,094      |
| 0014 | Debit     | Cheque/banking charges          | 8,976      |
| 0017 | Debit     | Repairs charges                 | 943        |
| 0019 | Debit     | Banking services                | 7,089      |
| 0020 | Debit     | Miscellaneous charges           | 1,243      |

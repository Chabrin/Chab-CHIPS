# CHRHMS, CHRTXS, CHTNTRS — Relationship Research

## Table Sizes

| Table   | Rows      | Role                                     |
| ------- | --------- | ---------------------------------------- |
| CHRHMS  | 35,217    | Returns header (property level)          |
| CHRTXS  | 1,470,210 | Returns detail lines (unit/tenant level) |
| CHTNTRS | 1,156,397 | Tenant invoice transactions              |

---

## What Each Table Is

**CHRHMS** — One row per monthly landlord return per property.  
Holds workflow state, financial totals, and commission rate for the whole return batch.  
Key fields: `RETURNID`, `LORDID`, `RETZON`, `COMMRATE`, `RETSTAT`, `PAIDST`, `APPST`, `VOUCHERNO`, `PREPDAT`, `PAYDAT`

**CHRTXS** — One row per charge line within a return.  
Breaks each return down by unit, tenant, and charge type.  
Commission lines have NULL `UNITNO`/`TENNAME` — they are property-level deductions.  
Key fields: `RETURNID`, `TRANSNUM`, `BLOCKID`, `UNITNO`, `TENNAME`, `RETNPRD`, `ENTRYTYP`, `TRANSCODE`, `RESAMTNT`, `AMTPAYBL`, `VATAMT`, `AMTPAYTN`, `RESAMTDR`

**CHTNTRS** — One row per tenant charge or allocation.  
This is the invoice/debtors ledger: what was billed to tenants.  
Key fields: `BATCHID`, `ENTRYNO`, `BLOCKID`, `UNITNO`, `TENNAME`, `TRANSTYP`, `TRAYR`, `TRAMNTH`, `TRANSAMT`, `BALAMT`, `AMTPAY`, `INVONUM`, `INVPRT`, `DATEDUE`

---

## How They Link

```
CHRHMS (1 header)
  └─ CHRTXS (many detail lines)        JOIN: CHRHMS.RETURNID = CHRTXS.RETURNID
       └─ CHTNTRS (matching invoice)   JOIN: CHRTXS.BLOCKID + UNITNO + TENNAME
                                              + period (RETNPRD→TRAYR/TRAMNTH)
                                              + CHRTXS.TRANSCODE = CHTNTRS.TRANSTYP
```

**CHRHMS → CHRTXS** — Direct FK. `CHRTXS.RETURNID = CHRHMS.RETURNID` (1-to-many).  
**CHRTXS → CHTNTRS** — Logical join. No hard FK. Match on `BLOCKID + UNITNO + TENNAME + year + month + charge type`. Some CHRTXS lines (Commission, VAT) have no matching CHTNTRS row — those are property-level.

---

## CHRTXS.ENTRYTYP Distribution

| Entry Type       | Approx Rows |
| ---------------- | ----------- |
| RENT CURRENT     | 786,954     |
| Commission       | 135,389     |
| Water Bill       | ~169,000    |
| Service Charge   | ~154,000    |
| Advance Payments | 76,475      |
| VAT              | 51,248      |
| RENT ARREARS     | 24,021      |
| Recoveries       | 21,315      |

Commission rows → NULL `UNITNO`/`TENNAME`, no CHTNTRS match, no `unit_id`/`tenant_id`.

---

## Target Mapping

| MSSQL           | Target                  | Granularity             |
| --------------- | ----------------------- | ----------------------- |
| CHTNTRS         | sys_pm_debtors_invoices | Per tenant invoice line |
| CHRTXS + CHRHMS | sys_pm_clients_returns  | Per return detail line  |

`sys_pm_clients_returns.invoice_id` → resolved from CHTNTRS using the logical join above.  
`sys_pm_clients_returns.client_id` → from CHBLOCKS.LLORDID (not CHRHMS.LORDID) so all rows resolve, not just those with a matching CHRHMS header.

---

## How CHTNTRS Tracks Invoice Balances

Each row in CHTNTRS carries its own running balance state — there is no separate payments table linked here; the balance is stored directly on the invoice row.

| Column     | Meaning                                                     |
| ---------- | ----------------------------------------------------------- |
| `TRANSAMT` | Original gross amount charged                               |
| `AMTPAY`   | Amount paid / allocated against this invoice line           |
| `BALAMT`   | Remaining outstanding balance (`TRANSAMT - AMTPAY`)         |
| `INVPRT`   | `1` = invoice has been processed/printed, `0` = pending     |
| `TSTATUS`  | `'OUT'` = written off or vacated tenant — treated as closed |
| `DELSTAT`  | `'Del'` = deleted/cancelled line                            |
| `RECOVD`   | Recovery flag — line has been recovered/settled             |
| `PAYPERC`  | Commission percentage applicable to this invoice line       |
| `DATEDUE`  | Due date (`varchar(50)`, needs `TRY_CAST`)                  |

**Balance logic:** `BALAMT = TRANSAMT - AMTPAY`. A zero `BALAMT` means fully paid. `INVPRT=1` means processed. `DELSTAT='Del'` or `TSTATUS='OUT'` sets status to inactive/closed. There is no separate allocation or payment table linked directly to CHTNTRS — the balance is a stored net figure, not computed from child rows.

---

## How CHRTXS / CHRHMS Track Thresholds

CHABRINS does **not** have an explicit threshold column on either table. Threshold logic is inferred from the combination of these fields:

**On CHRTXS (line level):**

| Column                | Meaning                                                              |
| --------------------- | -------------------------------------------------------------------- |
| `COMMBL`              | `'Yes'` / `'No'` — whether this charge line is subject to commission |
| `RESAMTNT`            | Gross amount of the charge line                                      |
| `AMTPAYBL`            | Landlord's payable share (after commission deducted)                 |
| `RESAMTNT - AMTPAYBL` | Implied commission amount on this line                               |
| `AMTPAYTN`            | Tenant-side payment amount on this line (what tenant actually paid)  |
| `RESAMTDR`            | Residual debit — outstanding balance component on this line          |
| `VATAMT`              | VAT amount on this line                                              |

**On CHRHMS (header level):**

| Column      | Meaning                                                     |
| ----------- | ----------------------------------------------------------- |
| `COMMRATE`  | Commission rate % for the whole return batch (e.g. `10.00`) |
| `RETSTAT`   | `1` = processed, `0`/NULL = pending                         |
| `PAIDST`    | `1` = paid out to landlord, `0` = not yet paid              |
| `APPST`     | `0` = pending approval, `1` = approved                      |
| `VOUCHERNO` | Payment voucher number once paid out                        |
| `PREPDAT`   | Date the return was prepared                                |
| `PAYDAT`    | Date paid out to landlord (`varchar(30)`, needs `TRY_CAST`) |

**How threshold is implied:**  
The commissionable amount per line = `RESAMTNT` where `COMMBL = 'Yes'`.  
Total commission = `SUM(RESAMTNT - AMTPAYBL)` across all lines in a `RETURNID`.  
The effective threshold is driven by `COMMRATE` on the CHRHMS header — any CHRTXS line with `COMMBL='No'` is effectively below or exempt from the threshold (e.g. deposits, recoveries, VAT lines).  
The Laravel target fields `threshold_percentage` and `abstract_threshold` are both mapped as `NULL` / `0` from CHABRINS — meaning CHABRINS stores the commission rate but not an explicit minimum threshold value.

---

## Key Quirks

- `CHRTXS.RETNPRD` is an integer `YYYYMM` (e.g. `202502`) — must be split for year/month joins to CHTNTRS.
- `CHRHMS.PAYDAT` is `varchar(30)` — requires `TRY_CAST` to DATETIME.
- `CHTNTRS.DATEDUE` is `varchar(50)` — also requires `TRY_CAST`.
- Some BLOCKID+UNITNO+TENNAME+period combos have multiple CHTNTRS rows — use `MIN(inv_row_id)` to pick canonical invoice.
- CHRHMS covers ~35K return batches; CHRTXS has ~42× more rows because every batch has many lines.
- `CHTNTRS.BALAMT` is a stored value, not computed — it reflects the balance at time of last update, not a live sum.
- Commission lines in CHRTXS (`ENTRYTYP='Commission'`, `COMMBL='Yes'`, NULL `UNITNO`) are the deduction rows that reduce `AMTPAYBL` from `RESAMTNT` — they represent the commission taken before landlord payout.

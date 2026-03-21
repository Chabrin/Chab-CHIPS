# CHTNTRS, CHTPAYS, CHTACC — How They Link

## The Three Tables

| Table       | Role                                 | Rows      | Key Columns                                                                      |
| ----------- | ------------------------------------ | --------- | -------------------------------------------------------------------------------- |
| **CHTNTRS** | Invoices (charges)                   | 1,156,397 | `INVONUM`, `TENNAME+BLOCKID+UNITNO`, `TRANSAMT` (face value), `BALAMT` (balance) |
| **CHTPAYS** | Payments (allocated to invoices)     | 1,435,222 | `RCNO` (receipt#), `TENNAME+BLOCKID+UNITNO`, `RCAMT` (amount applied)            |
| **CHTACC**  | Tenant account ledger (double-entry) | 2,598,893 | `DRCR` (DR/CR flag), `INVONUM`, `RCNO`, `TRANAMT` (debit), `TRANSAMT` (credit)   |

## The Flow

### 1. Invoice Generated (CHTNTRS + CHTACC DR)

CHTNTRS gets a row:

- `INVONUM` = `"INV20261272EG1-RENT FOR January 2026"`
- `TRANSAMT` = 188,790 (total charge)
- `BALAMT` = 188,790 (starts equal to total)

Simultaneously, CHTACC gets a **DR** row:

- `DRCR` = `"DR"`
- `INVONUM` = `"INV20261272EG1-RENT FOR January 2026"`
- `RCNO` = NULL (no receipt on debit side)
- `TRANAMT` = 188,790 (debit amount)
- `TRANSAMT` = 0 (no credit)

### 2. Payment Received (CHTPAYS + CHTACC CR + CHTNTRS updated)

CHTPAYS gets a row per invoice-line the payment covers:

- `RCNO` = 2203475935 (receipt number, from CHRCS)
- `RCAMT` = 14,990 (amount applied to THIS invoice)
- `TRANSDESC` = `"RENT FOR January 2026"`
- `TENNAME+BLOCKID+UNITNO` identifies the tenant/unit

Simultaneously, CHTACC gets a **CR** row:

- `DRCR` = `"CR"`
- `INVONUM` = `"INV20261272EG1-RENT FOR January 2026"` ← LINKS TO INVOICE
- `RCNO` = 2203475935 ← LINKS TO PAYMENT
- `TRANAMT` = 0 (no debit)
- `TRANSAMT` = 14,990 (credit amount)

CHTNTRS is updated:

- `AMTPAY` = 14,990 (amount paid so far)
- `BALAMT` = 173,800 (188,790 − 14,990)

### 3. Second Payment on Same Invoice

- CHTPAYS: `RCNO` = 2203541737, `RCAMT` = 173,800
- CHTACC: CR row with `INVONUM` + `RCNO` = 2203541737, `TRANSAMT` = 173,800
- CHTNTRS: `BALAMT` updated to 0, `AMTPAY` = 188,790 (fully paid)

## The Key Linking Logic

- **CHTPAYS does NOT store INVONUM.** It has no direct FK to invoices.
- **CHTACC is the bridge.** Its CR rows carry **both** `INVONUM` (→ CHTNTRS) and `RCNO` (→ CHTPAYS), connecting payments to invoices.s
- **CHTACC DR rows** carry `INVONUM` but `RCNO = NULL` — these are the debit/charge postings.
- **CHTACC CR rows** carry both `INVONUM` and `RCNO` — these are the payment postings.
- The natural key for tenant context is always `TENNAME + BLOCKID + UNITNO` across all three tables.
- **CHRCS** (receipts master) holds the receipt lifecycle (OK/CANCELLED status), linked by `RCNO`.

## Summary Diagram

```
CHTNTRS (Invoice)                    CHTPAYS (Payment)
  INVONUM ─────┐                ┌──── RCNO
  TRANSAMT     │                │     RCAMT
  BALAMT       │                │     TENNAME+BLOCKID+UNITNO
               │                │
               ▼                ▼
         ┌─────────────────────────────┐
         │        CHTACC (Ledger)      │
         │─────────────────────────────│
         │ DR row:                     │
         │   INVONUM ✓  RCNO = NULL    │
         │   TRANAMT = charge amount   │
         │   TRANSAMT = 0              │
         │                             │
         │ CR row:                     │
         │   INVONUM ✓  RCNO ✓         │  ← THE BRIDGE
         │   TRANAMT = 0              │
         │   TRANSAMT = payment amount │
         └─────────────────────────────┘
                     │
                     ▼
               CHRCS (Receipt Master)
                 RCNO, RCSTATUS (OK/CANCELLED)
```

## What This Means for Code

1. **When an invoice is generated**: Insert into CHTNTRS + insert a DR row into CHTACC
2. **When a payment is posted**: Insert into CHTPAYS (one row per invoice-line covered) + insert a CR row into CHTACC (with both INVONUM and RCNO) + update CHTNTRS.BALAMT/AMTPAY
3. **To find which payments paid an invoice**: Query `CHTACC WHERE DRCR='CR' AND INVONUM = ?` — the RCNO on those rows gives you the payment receipt numbers
4. **To find which invoices a payment covered**: Query `CHTACC WHERE DRCR='CR' AND RCNO = ?` — the INVONUM on those rows gives you the invoices

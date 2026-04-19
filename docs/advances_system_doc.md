# Client Advances — System Documentation

**Module:** Property Management → Client Advances  
**Controller:** `app/Http/Controllers/ClientAdvanceController.php`  
**Last updated:** April 2026

---

## How the System Works

### The three advance types and what makes them different

We built three types because each has a different recovery pattern.

**Type 1 (Expense)** is the simplest — the agency already paid a bill, so there are no interest calculations and no schedule. The full amount just sits waiting to be swept up by the next return run.

**Type 2 (Client Payment)** can go two ways depending on whether repayment months are specified. Without months, it works like Type 1: one amount, recovered in one return. With months, it behaves more like Type 3 — a full amortization schedule is built and the system posts one ledger entry per installment. The interest calculation on Type 2 without months is time-sensitive: the later in the month the disbursement happens, the less interest is charged (after day 25, none at all).

**Type 3 (Overpayment)** always has a schedule. Repayment months and interest rate are mandatory.

---

### How approval works and why there are two paths

When an advance is created the landlord gets an email with an approval link. Clicking that link hits a public route (no login required) and takes the advance straight from Pending (1) to Posted (6) — skipping the Approved (5) status entirely. This is the intended primary flow.

The Approved (5) status exists for cases where an internal sign-off is needed before the advance goes live. In that flow, the advance sits at status 5 until an admin clicks "Post Advance" on the detail page, which then moves it to 6. Both paths end at the same place and trigger the same ledger write.

---

### How posting to the landlord ledger works

The moment an advance reaches Posted — through either path — the system writes entries into `cb_landlord_account` with entry type code `0009`. This is what connects the advance to the returns process.

**For advances with a repayment schedule (Types 2 and 3 with months):** one ledger row is written per installment, each carrying the year and month of that installment's due date. This is deliberate — the returns generation process matches on `year` and `month` when it picks up advance deductions, so if we posted one big row for the full amount it would only ever be deducted once in the first month. Writing one row per installment means each month's return run finds exactly its share and no more.

**For advances without a schedule (Type 1, Type 2 without months):** one row is written for the full amount, dated to the disbursement month.

In both cases, `is_recovered = 0` when written. The returns process sets it to `1` after the deduction is included in an approved return — preventing any double-deduction.

---

### How recovery happens automatically

During monthly returns generation, the system reads `cb_landlord_account` looking for rows where `debit_amount > 0`, `is_recovered = 0`, and `year/month` match the period being generated. Because advance rows are tagged with the exact month they are due, the right amount appears as a deduction in the right return automatically — no manual intervention needed. After the return is approved, those rows are marked recovered.

---

### How rescheduling fits in

Rescheduling is only available on Posted advances. When it runs:

- The old advance is closed (status 8). Its uncleared schedule rows are also marked 8 — they will never be picked up by future returns runs.
- A new advance record is created at status 1 (Pending), with `previous_advance_id` pointing back to the old one. The reference number gains a `-R1` suffix (or `-R2` for a second reschedule, and so on).
- The new advance goes through the full approval flow again — the landlord gets a fresh email, clicks approve, and new ledger rows are written for the new installment schedule.

The old `0009` ledger entries from the original advance are intentionally left alone. Any that were already recovered (is_recovered = 1) are history. Any that were not yet recovered belong to months that have not yet been processed — and those months will not be processed before the reschedule because rescheduling only happens on Posted advances which by definition have already had their initial returns run.

The full chain is visible on the advance detail page in the Reschedule History table — from reference, to reference, amount paid at the time, outstanding balance at the time, who did it and when.

---

### Flow at a glance

```
Admin creates advance
    → Approval email sent to landlord
        ↓
Landlord clicks link               OR    Admin clicks "Post Advance"
(status 1 → 6 directly)                  (status 5 → 6)
        ↓                                        ↓
        └──────────── Both paths ────────────────┘
                            ↓
        Ledger entries written to cb_landlord_account (0009 DR)
        — one row per installment, tagged to each due month
                            ↓
        Returns run for Month 1
        → picks up Month 1 row → deducted from return → is_recovered = 1
                            ↓
        Returns run for Month 2
        → picks up Month 2 row → deducted from return → is_recovered = 1
                            ↓  (repeats until all installments recovered)
                            ↓  (or reschedule is triggered)
                            ↓
        Reschedule: old advance → status 8, new advance → status 1
        → new approval email → new ledger rows → process restarts
```

---

## 1. Overview

The Advances module allows the agency to issue funds to a landlord (client) ahead of their returns cycle. The advance is subsequently recovered automatically through monthly returns deductions from the landlord's ledger. The system supports three distinct advance types, each with its own interest calculation logic, repayment structure, and ledger posting behaviour.

---

## 2. Advance Types

### Type 1 — Expense Advance

The agency pays an operational expense on the landlord's behalf (e.g. repairs, caretaker salaries, garbage collection). The cost is recovered from the next return.

| Field | Behaviour |
|---|---|
| Interest | None (0%) |
| Repayment months | None — single recovery at next returns |
| Charge amount | Set per expense category |
| Charge billed to | Client or Tenant |
| Schedule rows created | No |

**Total amount** = `amount + charge_amount`

**Expense categories:** repairs & maintenance, caretaker salaries, garbage collection, electricity bills, water bills, printing/photocopying, refund tenant deposits, eviction costs, locking devices, security services.

---

### Type 2 — Client Payment Advance

The agency advances cash directly to the landlord (e.g. emergency payment). Interest is charged and, optionally, the repayment can be spread over multiple months via a reducing-balance amortization schedule.

#### Sub-case A: No repayment months (lump-sum repayment)

Simple interest is applied based on the day of the disbursement date:

| Disbursement day | Interest applied |
|---|---|
| Before day 20 | Full monthly interest (`amount × rate / 100`) |
| Day 20–25 | Prorated for remaining days in month |
| After day 25 | No interest charged |

**Total amount** = `amount + interest_amount + charge_amount`

No schedule rows are created. Recovery happens in one deduction at returns generation.

#### Sub-case B: With repayment months (amortization)

A full reducing-balance amortization schedule is calculated across the specified number of months from `principal_start_date`. If the disbursement day is before day 25, the interest on the first installment is prorated to the remaining days of that month.

**Total amount** = `sum of all principal installments + sum of all interest installments + charge_amount`

Schedule rows are created in `advance_schedule` (one per month).

---

### Type 3 — Overpayment Advance

Used when the landlord has overpaid and the agency is returning the excess as an advance recovered over time. Always uses reducing-balance amortization. Interest rate and repayment months are mandatory.

Same amortization calculation as Type 2 Sub-case B. First installment interest is prorated if disbursement is before day 25.

**Total amount** = `sum of principals + sum of interest + charge_amount`

Schedule rows are always created.

---

## 3. Eligibility Checks (at create time)

Before an advance is saved, two business-logic checks run:

1. **Amount limit** — Maximum advance = 80% of the client's annualised average monthly returns over the last 6 months, minus any existing outstanding balance. If the requested amount exceeds this, the submission is rejected.

2. **Active advances cap** — A client may not hold more than 2 active advances simultaneously (status Pending, Approved, or Posted with a non-zero balance).

3. **Date window** — Disbursement date must be within 30 days of the requisition date.

---

## 4. Reference Number

Generated at create time: `ADV-{YYYY}-{NNN}` (e.g. `ADV-2026-007`), sequential within the calendar year. A database lock (`lockForUpdate`) prevents duplicate numbers under concurrent requests.

Rescheduled advances append `-R1`, `-R2`, etc. (e.g. `ADV-2026-007-R1`).

---

## 5. Status Lifecycle

```
1 (Pending)
    ├─→ 6 (Posted) ← landlord clicks email approval link
    ├─→ 5 (Approved) ← internal approval step (optional flow)
    │       └─→ 6 (Posted) ← admin clicks "Post Advance"
    ├─→ 2 (Cleared)     — advance fully recovered
    ├─→ 3 (Cancelled)
    ├─→ 4 (Suspended)
    └─→ 8 (Rescheduled) — replaced by a new advance
```

---

## 6. Approval Process

### Step 1: Advance created (status 1 — Pending)

An admin creates the advance through the web form. On successful save:
- The advance record is written with `current_status_id = 1`.
- A schedule is created if applicable (Types 2 with months, Type 3 always).
- An approval email is sent to the landlord via `AdvanceApprovalNotification` (mail only; database channel is not used because the custom `notifications` table uses `bigint` PKs which conflict with Laravel's UUID generation).

The email contains a unique encrypted approval link and an attached repayment schedule (PDF/inline table).

---

### Path A: Landlord approves via email link

The landlord clicks the link in their email. This calls `approveAdvance($encryptedId)` on a public route (no authentication required).

```
status: 1 → 6 (Posted directly — no intermediate Approved step)
```

- If the advance is already in any status other than 1, the page shows "already processed" without making changes.
- `postToLandlordAccount()` is called immediately after the status update, using `$advance->created_by` as the user ID (the admin who created it, since there is no authenticated session in this request).

---

### Path B: Admin posts manually (status 5 → 6)

If an internal approval step (status 5 — Approved) exists in the workflow, an admin with the `update_client_advances` permission sees a **Post Advance** button on the show page. Clicking it calls `postAdvance($encryptedId)`.

```
status: 5 → 6 (Posted)
```

- Guard: the advance must be in status 5 before posting is allowed.
- `postToLandlordAccount()` is called immediately after the status update, using `Auth::id()`.
- The advance's `schedule` relationship is eager-loaded before this call so installment rows are available without an additional query.

---

## 7. Posting to the Landlord Ledger (`cb_landlord_account`)

Both approval paths call the private method `postToLandlordAccount(ClientAdvance $advance, int $userId)`. This is the **only** point of entry for advance entries into the landlord ledger. The method never throws — failures are logged and the advance status is preserved.

### Entry type code

All advance ledger entries use `entry_type_code = '0009'` and `entry_description = 'ADVANCE'`. The `entry_particulars` field carries the human-readable description.

### Two posting modes

#### Mode 1 — Multi-installment (Type 2 with months, Type 3)

**Condition:** `advance_schedule` rows exist AND `repayment_months` is set.

One `cb_landlord_account` row is inserted **per schedule installment**, each tagged with the `year` and `month` of that installment's `payment_date`:

| Column | Value |
|---|---|
| `year` | Year of installment `payment_date` |
| `month` | Month of installment `payment_date` |
| `debit_amount` | `principal_amount + interest` for that installment |
| `credit_amount` | 0 |
| `balance` | Running balance (decremented in PHP loop) |
| `is_recovered` | 0 |
| `entry_particulars` | "Advance ADV-2026-001 Instalment 1 of 12 disbursed 05 Apr 2026" (first row), "Advance ADV-2026-001 Instalment 2 of 12" (subsequent rows) |

**Why per-installment?** `insertAdvancePaymentLines()` in `ReturnsDeductionsTrait` filters by `la.year = returnYear AND la.month = returnMonth`. If only one lump-sum row existed it would be picked up entirely in month 1 — months 2–12 would never see a deduction. Posting one row per installment ensures each month's returns generation picks up exactly its share.

#### Mode 2 — Lump sum (Type 1, Type 2 without months)

**Condition:** no schedule rows or no `repayment_months`.

One `cb_landlord_account` row is inserted, tagged with the disbursement date's year/month:

| Column | Value |
|---|---|
| `year` | Disbursement year |
| `month` | Disbursement month |
| `debit_amount` | `total_amount` (full amount including interest and charge) |
| `credit_amount` | 0 |
| `balance` | Last known balance for client/property minus `total_amount` |
| `is_recovered` | 0 |
| `entry_particulars` | "Advance ADV-2026-001 disbursed 05 Apr 2026" |

### Running balance calculation

The method fetches the last `balance` value from `cb_landlord_account` for the same `client_id` and `property_id` (ordered by `id DESC`) once before the loop. For multi-installment advances, the balance is decremented in PHP for each row. This avoids subquery race conditions when inserting multiple rows sequentially.

### How recovery works downstream

`ReturnsDeductionsTrait::insertAdvancePaymentLines()` runs during monthly returns generation. It reads:

```sql
WHERE la.debit_amount > 0
  AND la.is_recovered = 0
  AND la.year = {returnYear}
  AND la.month = {returnMonth}
  AND la.entry_type_code != '0005'
```

The `!= '0005'` condition means it automatically picks up all DR rows including `0009` (advance) entries. No changes to that trait are needed. After returns are approved, `markLandlordAccountRecovered()` flips `is_recovered = 1` on all rows that were consumed.

---

## 8. Rescheduling

Rescheduling is only available on advances in status 6 (Posted). It is accessible via the **Reschedule** button on the advance show page.

### What happens

1. **Outstanding balance is calculated** from the sum of cleared (`current_status_id = 2`) schedule rows (principal + interest). This is the amount already recovered from returns.

2. **Old advance** is set to status 8 (Rescheduled). All its pending schedule rows (`current_status_id = 1`) are also set to status 8.

3. **New advance record** is created with:
   - `current_status_id = 1` (Pending — awaits fresh landlord approval)
   - `previous_advance_id` pointing to the old advance (creates a chain)
   - `reference_number` = old reference appended with `-R1`, `-R2`, etc.
   - Interest and schedule recalculated fresh from the new amount / rate / months
   - All other fields (client, property, zone, charge, category) inherited from the old advance

4. **Reschedule history** is logged to `advance_reschedule_history`, recording:
   - `original_advance_id` (root of the chain)
   - `from_advance_id` / `to_advance_id`
   - `amount_paid_at_reschedule` / `outstanding_balance_at_reschedule`
   - Who rescheduled and when

5. **Landlord is notified** by email of the new advance pending approval (using `AdvanceApprovalNotification`).

### Ledger entries at reschedule

The **old** `cb_landlord_account` entries (`0009` rows) are left untouched at reschedule time. The assumption is that returns will always have been generated for any periods that have passed before a reschedule takes place, so all matured installments will already be `is_recovered = 1`. When the new advance is approved/posted, `postToLandlordAccount()` runs again and writes fresh `0009` entries for the new schedule — one per installment, each tagged to its future `payment_date` year/month.

### Chain navigation

The `previousAdvance` relationship walks the `previous_advance_id` chain. The show page uses this to build a Reschedule History table displaying the full lineage (From Reference → To Reference, paid at reschedule, outstanding at reschedule, rescheduled by, date, narration).

---

## 9. Key Tables

| Table | Role |
|---|---|
| `cb_client_advances` | One row per advance record |
| `advance_schedule` | One row per repayment installment (Types 2 with months, 3) |
| `advance_reschedule_history` | Audit trail for each reschedule event |
| `cb_landlord_account` | Running landlord ledger — advance entries written here on post/approval |
| `cb_returns_clients` | Deduction lines created from `cb_landlord_account` during returns generation |
| `cb_returns_summary` | Monthly return summaries — net amount includes advance deductions |

---

## 10. Notifications

| Notification | Class | When sent | Channel |
|---|---|---|---|
| Advance approval request | `AdvanceApprovalNotification` | On advance create, and on reschedule of a new advance | Mail only |
| Repayment schedule | `AdvanceScheduleNotification` | Manual — admin clicks "Email Landlord" on show page | Mail only |

Both notifications use mail only. The `database` channel is excluded because the custom `notifications` table uses a `bigint` auto-increment primary key, which conflicts with Laravel's `DatabaseChannel` which generates UUID values.

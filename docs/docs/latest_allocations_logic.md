# Payment Allocation Logic

This document explains how the PHP backend matches tenant payments to invoices, how invoice priority is determined, and how GL entries are posted. All references point to the actual source files.

---

## 1. Overview — The Two-Phase Process

Payments go through two phases before money reaches a landlord:

```
Phase 1 — Payment Receipt (GL)
  Bank extract / API log → sys_pm_tenants_payments
  GL: Debit bank account, Credit tenant control account
  GL: Debit client consolidated account, Credit bank account (transfer)

Phase 2 — Allocation
  sys_pm_tenants_payments (unallocated) → matched to sys_pm_debtors_invoices
  Written to sys_pm_tenants_payments_invoices
  Invoice current_balance decremented
  GL posted on allocation (commission split, client funds)
```

---

## 2. Triggering Allocation — `processPayments()`

**File:** `libraries/ipms/Domain/Service/TenantPaymentService.php` — `processPayments()` (line 285)

The entry point for allocation. It fetches every payment in status **Unallocated (11)** or **Partially Allocated (12)** that has at least one invoice with a positive `current_balance` on the same `lease_id`:

```sql
-- TenantPaymentDao::getAll() (line 169)
SELECT pay.*
FROM sys_pm_tenants_payments pay
INNER JOIN sys_pm_debtors_invoices i ON i.lease_id = pay.lease_id
WHERE i.current_balance > 0
  AND (pay.current_status_id = 11 OR pay.current_status_id = 12)
```

For each unallocated payment it runs three passes in order:

| Pass | What it does |
|------|-------------|
| 1 — Previous unit | Checks `sys_pm_movement_requests` for tenant transfers. If tenant moved from an old unit, clears pending invoices on the **old** lease first. |
| 2 — Current lease | Allocates against invoices on the payment's own `lease_id`. |
| 3 — Linked leases | Some tenants occupy multiple adjacent units under linked leases. Any invoice on a linked lease is also settled here. |

After sorting, each invoice is sent to `allocateInvoice()`.

---

## 3. Invoice Sort Order — `sortInvoices()`

**File:** `TenantPaymentService.php` — `sortInvoices()` (line 702)

Invoices are sorted **before** allocation runs, so older debt is always cleared first.

```php
// Primary sort: date_due month/year (oldest first — arrears before current)
$date1 = date('Y-m-1', strtotime($invoice_a->date_due));
$date2 = date('Y-m-1', strtotime($invoice_b->date_due));

if (same month) {
    // Secondary sort: payment_priority column (lower number = higher priority)
    sort by $invoice_a->payment_priority ASC
} else {
    // Different month: sort by date_due ASC (oldest first)
    sort by date_due ASC
}
```

**Result:** Past-due invoices are always prioritised. Within the same month, `payment_priority` on the invoice record determines which item (rent, water, service charge …) is cleared first.

---

## 4. The Allocation Algorithm — `allocateInvoice()`

**File:** `TenantPaymentService.php` — `allocateInvoice()` (line 409)

Called once per invoice per payment. Computes two running balances then decides how much to allocate.

### 4.1 Balance Calculation

```
payment_sum_allocations = SUM(amount_allocated) from sys_pm_tenants_payments_invoices WHERE payment_id = X
unallocated_amount      = payment.amount - payment_sum_allocations

invoice_sum_allocations = SUM(amount_allocated) from sys_pm_tenants_payments_invoices WHERE invoice_id = Y
invoice_balance         = invoice.total_amount - invoice_sum_allocations
```

Both balances are recalculated fresh on each call, so the function is safe to call sequentially across multiple invoices.

If `unallocated_amount <= 0` the payment is force-closed to **Fully Allocated (13)** and the function returns immediately.

### 4.2 Three-Way Decision

| Condition | allocate_amount | Payment status after | Invoice status after |
|-----------|----------------|----------------------|----------------------|
| `unallocated == invoice_balance` | invoice_balance | **13** Fully Allocated | **9** Cleared (or recovery type ID) |
| `unallocated < invoice_balance` | unallocated_amount | **13** Fully Allocated | **8** Partially Cleared |
| `unallocated > invoice_balance` | invoice_balance | **12** Partially Allocated | **9** Cleared (or recovery type ID) |

When the payment has a `recovery_type_id` (e.g. Absconded=36, Sat Deposit=35) that ID is used as the invoice status instead of 9, so the invoice is marked with the recovery reason rather than a plain "Cleared".

### 4.3 What Gets Written

A row is inserted into `sys_pm_tenants_payments_invoices`:

```
date_time, invoice_id, payment_id,
prev_unallocated_balance, invoice_amount, amount_allocated,
new_unallocated_balance, new_invoice_balance,
trans_date_time (= payment.payment_date),
client_id, zone_id, property_id, sla_id, unit_id, tenant_id, lease_id,
field_officer_id, zone_supervisor_id, zone_manager_id
```

Then in sequence:

1. `sys_pm_debtors_invoices.current_balance` set to `new_invoice_balance`
2. `ValidateInvoiceService::checkIfPayableClient()` — if passes, invoice gets status **62 (Cleared for Returns)**
3. `sys_pm_tenants_payments.current_status_id` updated to new payment status
4. `sys_pm_bills` row for this invoice updated (`current_status_id`, `current_balance`, `collected_amount`)
5. `ClientReturnService::updateReturnOnInvoicePayment()` — updates the landlord return record's `threshold_percentage` and `payable_status_id`
6. If the invoice item is a deposit type → `DepositService::createFromInvoice()` activates the deposit record
7. `sys_pm_debtors_arrears` row for this invoice updated with new status

---

## 5. The Returns Gate — `checkIfPayableClient()`

**File:** `libraries/ipms/Domain/Service/Validations/ValidateInvoiceService.php` (line 7)

This function decides whether a cleared invoice can be marked as **ready to pay out to the landlord (status 62)**. It runs immediately after every allocation. The checks run in this exact order; a `false` at any step blocks payment to the landlord:

| # | Check | Block reason |
|---|-------|-------------|
| 1 | `total_amount <= 0` | Zero-value invoice — skip |
| 2 | `current_status_id == returns_cleared (62)` | Already marked, avoid double-processing |
| 3 | `current_status_id == cancelled` | Cancelled invoice |
| 4 | `current_status_id == terminated` OR `lease_status_id == terminated` | Terminated lease (unless auditor-approved) |
| 5 | `current_status_id == internal_audit_approved` | **Fast-pass** — auditor override, pay immediately |
| 6 | Non-rent item has outstanding arrears of same item type on same lease before this invoice's `date_due` | Earlier same-item arrears must be cleared first |
| 7 | Non-rent item: `prepay_client != "Yes"` AND `current_balance > 0` | Non-rent items must be fully paid |
| 8 | `date_due` month < current month (i.e. invoice is an arrear) | **Arrears are blocked from auto-payout** — must be manually approved by auditor |
| 9 | `current_balance <= 0` | Fully paid — allow through |
| 10 | Status not **Pending (1)** or **Posted (6)** | Wrong workflow state |
| 11 | Non-rent item with `current_balance > 0` | Partial payment not allowed for non-rent |
| 12 | No matching row in `sys_pm_clients_returns` | No return record exists yet — can't pay |
| 13 | Rent arrears threshold: sum of earlier unpaid rent balances on same lease > **15% of this invoice's current_balance** | Too much outstanding arrears — landlord cannot receive funds yet |

If all checks pass → invoice status set to **62 (Returns Cleared)** → landlord payout can proceed.

---

## 6. GL Posting

### 6.1 On Payment Receipt — `processPaymentsLogs()` (line 729)

When a bank extract/API log is processed into a payment record, **two balanced journal entries** are created immediately:

**Journal 1 — Receive payment from bank channel**

| Entry | Account | Amount |
|-------|---------|--------|
| Debit | Channel bank account (Equity / Mpesa / Co-op) | +payment amount |
| Credit | Tenants control account (`tenants_account_id`) | −payment amount |

**Journal 2 — Transfer to client consolidated funds**

| Entry | Account | Amount |
|-------|---------|--------|
| Debit | Client consolidated bank (`clients_bank_account_id`) | +payment amount |
| Credit | Channel bank account | −payment amount |

Both journals use `trans_type_id = 12` (Tenant Payment) and `direct_post = false` (balance must equal before posting).

**Suspense path** — if the unit code from the bank extract cannot be matched to a unit in the database, the payment is held in a suspense account and the journal debits `tenants_account_id` and credits `tenants_suspense_account_id` instead.

### 6.2 On Allocation — `postGLAccounts()` (line 614)

Called after allocation to split the collected funds between commission and client payable:

**Journal 3 — Commission and client funds split**

| Entry | Account | Amount |
|-------|---------|--------|
| Debit | Earned commission account (`earned_commission_acc_id`) | commission portion |
| Debit | Client bank/trust account (`clients_bank_account_id`) | payable_amount = payment.amount − commission |
| Credit | Channel bank account | −payment amount |

This uses `direct_post = true` (balance check skipped) because the commission/payable split may not equal exactly in edge cases.

### 6.3 GL Engine — `GLTransService::makeJournalEntry()` (line 170)

All three journals go through the same engine:

1. Validates an active fiscal year exists in `sys_gl_accounts_setup`
2. If `direct_post = false`: validates debits == credits via `validateJournalBalance()`
3. Inserts header row into `sys_gl_transactions`
4. For each line item: inserts into `sys_gl_transactions_91_repeat` with `entry_type` (Debit/Credit) and signed amount (credits are forced negative)
5. Calls `BudgetingService::makeBudgetEntry()` for each line item

---

## 7. Payment & Invoice Status Reference

### Payment statuses (`sys_pm_tenants_payments.current_status_id`)

| ID | Meaning |
|----|---------|
| 1 | Pending / default after creation |
| 6 | Posted (GL entries created, pending unit match) |
| 11 | Unallocated — awaiting `processPayments()` |
| 12 | Partially Allocated — some invoices cleared, payment has remaining balance |
| 13 | Fully Allocated — payment exhausted |
| 43 | Failed (no unit match, no GL posted) |
| 44 | Duplicate reference — skipped |
| Suspended | No unit match — held in suspense |

### Invoice statuses (`sys_pm_debtors_invoices.current_status_id`)

| ID | Meaning |
|----|---------|
| 1 | Pending |
| 6 | Posted |
| 8 | Partially Cleared |
| 9 | Cleared (fully paid) |
| 62 | Cleared for Returns (passed `checkIfPayableClient` — landlord can be paid) |
| cancelled | Cancelled (blocked from payout) |
| terminated | Terminated (blocked from payout) |
| internal_audit_approved | Auditor override — bypasses arrears checks |
| recovery type IDs (34–37) | Recovery-specific close codes (Paid Vacant, Sat Deposit, Absconded, Paid Owner) |

---

## 8. Key Database Tables

| Table | Purpose |
|-------|---------|
| `sys_pm_tenants_payments` | One row per payment received |
| `sys_pm_tenants_payments_invoices` | Allocation ledger — one row per payment×invoice match |
| `sys_pm_debtors_invoices` | Invoice registry; `current_balance` decremented on each allocation |
| `sys_pm_bills` | Billing view of invoices; mirrored with status/balance on allocation |
| `sys_pm_clients_returns` | Landlord return records; threshold updated after each allocation |
| `sys_pm_debtors_arrears` (defaulters) | Defaulter tracking; status updated on allocation |
| `sys_pm_deposits` | Deposit records; activated when deposit invoice is cleared |
| `sys_pm_movement_requests` | Tenant transfer records; used to find old lease for cross-unit clearing |
| `sys_gl_transactions` | GL transaction headers |
| `sys_gl_transactions_91_repeat` | GL journal line items (debit/credit entries) |
| `sys_gl_accounts_setup` | GL configuration — maps account roles to chart-of-accounts IDs |
| `sys_fin_pay_equity_bank_extracts` | Equity Bank daily extract rows |
| `sys_fin_pay_mpesa_coop_bank_extracts` | Mpesa / Co-op daily extract rows |
| `sys_fin_pay_equity_bank` | Equity API payment logs |
| `sys_fin_pay_mpesatransactions` | Mpesa API payment logs |
| `sys_fin_pay_cooperative_bank` | Co-op API payment logs |

---

## 9. End-to-End Flow Diagram

```
Bank extract file uploaded
        │
        ▼
sys_fin_pay_*_extracts  ──► reconcileLogs() matches by reference to API logs
        │                        (Equity: security_number, Mpesa: mpesaTransID, Coop: first 11 chars)
        │
        ▼
processPaymentsLogs()
  ├─ Duplicate reference? → mark status 44, skip
  ├─ Unit code found?
  │    YES → look up lease → recordPayment() → sys_pm_tenants_payments (status 11)
  │    NO  → recordPayment() into suspense (suspended status)
  ├─ GL Journal 1: Debit bank channel, Credit tenants_control
  └─ GL Journal 2: Debit clients_consolidated, Credit bank channel
        │
        ▼
processPayments()  [triggered by cron or workflow step]
  ├─ Fetch all payments with status 11 or 12
  ├─ For each payment:
  │    ├─ Pass 1: previous lease (tenant transfer)
  │    ├─ Pass 2: current lease
  │    └─ Pass 3: linked leases
  │         │
  │         ▼  (for each invoice, sorted oldest date_due first, then payment_priority)
  │    allocateInvoice()
  │      ├─ unallocated_amount = payment.amount - SUM(existing allocations)
  │      ├─ invoice_balance    = invoice.total_amount - SUM(existing allocations)
  │      ├─ Write row to sys_pm_tenants_payments_invoices
  │      ├─ Update invoice current_balance
  │      ├─ checkIfPayableClient() → if pass → invoice status = 62
  │      ├─ Update payment status (12 partial / 13 full)
  │      ├─ Update sys_pm_bills
  │      ├─ ClientReturnService::updateReturnOnInvoicePayment()
  │      └─ Update sys_pm_debtors_arrears
        │
        ▼
postGLAccounts()  [after allocation]
  └─ GL Journal 3: Debit commission + Debit client_bank, Credit bank channel
```

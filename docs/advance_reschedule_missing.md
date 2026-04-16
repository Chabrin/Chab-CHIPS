# Advance Rescheduling — Missing Items

These two items are intentionally stubbed in the codebase with `⚠️ TODO` comments.
They cannot be completed without additional business/DB details.

---

## TODO #1 — Landlord Account Ledger Posting

**Where it's stubbed:**
- `ClientAdvanceController::postAdvance()` — when admin posts an advance (status 5 → 6)

**What needs to happen:**
When an advance is confirmed/posted, a transaction should be recorded in the landlord's account ledger.

**Information needed:**
1. Which table receives the ledger entry?
   - Candidate: `sys_pm_landlord_accounts_transaction` (transaction types 23=Principal, 24=Interest seen in docs)
   - Candidate: `sys_reports_landlord_accounts` (transaction_type 15 = Client Advance seen in docs)
2. What are the debit and credit account IDs/types for an advance disbursement?
3. Does this require a corresponding voucher in `sys_fin_payment_vouchers`?
4. Are GL journal entries (`sys_gl_journal_entries`) required at the same time?

**Affected files:**
- `app/Http/Controllers/ClientAdvanceController.php` → `postAdvance()` method (~line 912)

---

## TODO #2 — Source of Truth for Payments Made

**Where it's stubbed:**
- `ClientAdvanceController::showRescheduleForm()` — computes amount paid from cleared schedule rows
- `ClientAdvanceController::processReschedule()` — same computation used to calculate outstanding balance

**Current implementation (placeholder):**
```php
$amountPaid = $advance->schedule()
    ->where('current_status_id', 2)
    ->sum(DB::raw('COALESCE(total_amount, principal_amount + COALESCE(interest, 0))'));
```

**Information needed:**
1. Is the cleared schedule rows approach correct, or should payments be read from `sys_pm_landlord_accounts_transaction`?
2. Does `AdvanceSchedule.total_amount` always reflect actual cash received, or is it a scheduled amount?
3. If using the ledger table — what transaction_type IDs represent repayments against an advance?

**Affected files:**
- `app/Http/Controllers/ClientAdvanceController.php` → `showRescheduleForm()` and `processReschedule()` methods

---

## Implementation Notes (for when TODOs are resolved)

- TODO #1: Add ledger insert inside the `DB::transaction` block in `postAdvance()`, after `$advance->update(['current_status_id' => 6])`
- TODO #2: Replace the `COALESCE(total_amount ...)` sum with the confirmed query; update both `showRescheduleForm()` and `processReschedule()` in the same edit

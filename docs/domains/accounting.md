# Accounting Domain — Invoicing, Payments, Returns, GL

*Scope: invoicing, payment allocation, returns processing, GL posting, advances,
reconciliation. Property/tenant/lease operational management is a separate domain;
touched here only where it triggers money (e.g. bulk rent invoicing off a lease).*

## Overview

The money pipeline is: **invoice generation → payment receipt → allocation →
GL posting → returns generation → 4-level approval → landlord ledger**. It is
implemented as fat controllers delegating to a thin service
(`PaymentAllocationService`) and a family of traits — no Form Requests, no
Policies, matching the rest of the codebase. The invoicing controller was just
split (commit `3d772e06`, 4 Sept 2026) from one 2,006-line file into a
coordinator plus three domain controllers. Returns processing is the largest
and riskiest subsystem: a working, high-volume generation engine feeding a
4-level approval workflow that has essentially never completed a single item at
scale (99.93% stuck), alongside a parallel "abstract threshold" payability
system that writes to columns nothing downstream reads.

Several reference docs in `docs/docs/` (`PAYMENT_ALLOCATION_HOW_IT_WORKS.md`,
`latest_allocations_logic.md`) describe a `libraries/ipms/Domain/Service/*`
PHP structure (`TenantPaymentService`, `ValidateInvoiceService`,
`GLTransService`) that **does not exist anywhere in this repo**. Their
business-rule descriptions (sort order, three-way allocation scenarios, GL
journal shapes) map closely onto the real code and are useful as design
reference, but every file path and the `checkIfPayableClient()`/status-62
gate they describe are aspirational, not live. The real code —
`PaymentAllocationService` + `PaymentAllocationTrait` — is simpler: three
passes exist in name, but Pass 3 "linked leases" is a hard-coded stub that
always returns an empty collection.

## Key Controllers & Services/Traits

| File | Lines | Role |
|---|---|---|
| `ClientReturnsController.php` | 2,456 | Returns generation, merge batches, PDF statements |
| `AccountsReportsController.php` | 2,076 | 14 finance reports |
| `InvoicingController.php` | 2,006 | Legacy/manual invoicing surface, now a thinner coordinator post-split |
| `ReturnsApprovalController.php` | 1,078 | Real home of the 4-level `cb_approval_items` workflow |
| `TenantPaymentsController.php` | 858 | Payment search/detail UI **plus a second, divergent allocation path** (Known Bugs) |
| `RentInvoicingController.php` | 321 | New (split): bulk monthly rent generation |
| `UtilityInvoicingController.php` | 195 | New (split): utility/non-rent billing |
| `ClientInvoicesController.php` | 121 | New (split): client-side invoice views |
| `Services/PaymentAllocationService.php` | 534 | Orchestrates allocation; owns "Phase 2" only |
| `Traits/ReturnsDeductionsTrait.php` | 532 | Advance/recovery/commission deduction lines |
| `Traits/ReturnsMasterReturnTrait.php` | 528 | MASTERRETURN roll-up across merged properties |
| `Traits/GlPostingTrait.php` | 518 | Shared GL journal posting (`postGlEntry`/`postGlByTransactionType`) |
| `Traits/ReturnThresholdTrait.php` | 426 | The dead "abstract threshold" payability system |
| `Traits/HandlesReturnApprovalWorkflow.php` | 424 | Used by `ProcessReturnsFinalApprovalJob` on level-4 clear |
| `Services/ReturnThresholdService.php` | 383 | Same dead system, service-layer half |
| `Traits/ReturnsSummaryTrait.php` | 345 | Aggregates lines into `cb_returns_summary` |
| `Traits/PaymentAllocationTrait.php` | 191 | SQL helpers: balances, 3-way scenario, invoice sort |
| `Traits/ReturnsRentLinesTrait.php` | 188 | Pulls CR rent lines into a return |
| `Traits/InvoiceCreationTrait.php` | 160 | New (split): shared invoice-row builder |
| `Traits/ReturnsPayThresholdTrait.php` | 153 | The **live, correct** 85% threshold (bulk SQL, `cb_returns_clients.payable_status`) |

(All paths relative to `app/Http/Controllers/`, `app/Traits/`, `app/Services/`.)

`ClientReturnsController` composes 5 of the 7 Returns traits directly
(`ReturnsRentLinesTrait`, `ReturnsPayThresholdTrait`, `ReturnsDeductionsTrait`,
`ReturnsSummaryTrait`, `ReturnsMasterReturnTrait`);
the other two (`ReturnThresholdTrait`, `HandlesReturnApprovalWorkflow`) run
inside `PaymentAllocationService` and `ProcessReturnsFinalApprovalJob`
respectively.

## Core Money Flow

```
1. RentInvoicingController::processGenerateInvoices()          [bulk, hardcoded "Unit Rent" only]
     ↓ INSERT sys_pm_debtors_invoices (status 1, batch_number)
2. PaymentsAllocationController → PaymentAllocationService::processPayments()
     Pass 1 (prev lease) → Pass 2 (current lease) → Pass 3 (linked leases, STUB — always empty)
     → PaymentAllocationTrait::getUnsettledInvoices() [oldest month → payment_priority → date_due]
     → PaymentAllocationService::allocateInvoice()
         → determineAllocationScenario(): EXACT_MATCH | PARTIAL_PAYMENT | EXCESS_PAYMENT
         → INSERT sys_pm_tenants_payments_invoices
         → UPDATE sys_pm_debtors_invoices (balance, status 8/9)
         → syncBillsRecord() / syncArrearsRecord() (mirrors)
         → ReturnThresholdTrait::executeThresholdUpdate()   [dead output — see below]
         → postAllocationGl() → GlPostingTrait::postGlEntry(type: payment_allocation)
3. ClientReturnsController::processGenerate()  (manual, per period/property, one admin click)
     → ReturnsRentLinesTrait::insertRentLines()      [reads returns_period + status=1 invoices]
     → ReturnsPayThresholdTrait::updatePayThresholds() [the REAL 85%-of-last-month rule]
     → ReturnsDeductionsTrait (advances from cb_landlord_account, recoveries, commission)
     → ReturnsSummaryTrait::insertMainReturnSummariesLegacy() → cb_returns_summary row
     → createApprovalWorkflowItems() → INSERT cb_approval_items (total_levels=4, level 1)
4. ReturnsApprovalController (4 sequential levels, each own current_level bump)
     → on level 4 clear → ProcessReturnsFinalApprovalJob (queued, chunked 100)
         → createLandlordLedgerCredit() → cb_landlord_account
         → createCommissionDebit() + createCommissionGLEntry()
```

Advances feed the same ledger sideways: `ClientAdvanceController` posts one
`cb_landlord_account` row (`entry_type_code = '0009'`) per installment when an
advance reaches Posted (status 6), and `ReturnsDeductionsTrait::insertAdvancePaymentLines()`
picks up exactly the rows matching the return's `year`/`month` during step 3
above — the one clean, well-reasoned piece of month-matching design in this
subsystem.

## Returns Processing & Its Approval Bottleneck

Generation itself works at real volume: 28,394 `cb_returns_summary` rows and
1,317,681 `cb_returns_clients` lines exist live today (2026-09-08). But the
4-level `cb_approval_items` chain behind it is functionally non-operational:
of 9,677 return-summary approval items, **9,670 (99.93%) are still sitting at
level 1**, and only 4 have ever reached "Approved" — essentially unchanged
from the 2026-07-31 redesign proposal's 9,668/9,675. Each level needs a
distinct human to act in sequence; level 1 never clears in practice, so
nothing downstream (`ProcessReturnsFinalApprovalJob`, the landlord ledger
credit, the commission GL entry) fires for the overwhelming majority of
returns. `paid_status` on `cb_returns_summary` is read in two places but
written nowhere — whatever marks a return as actually paid out is either
manual or lives outside this codebase. Merging remains a rare edge case (8
merge batches; 0 of 28,394 current summaries flagged `is_merged`, down from
14 in July, consistent with merged rows aging out or being regenerated
non-merged).

## What's Designed But Dead vs. What's Actually Live

- **Dead: the dual-column "abstract threshold" system.** `ReturnThresholdTrait`
  fires on every single payment allocation (`PaymentAllocationService.php:410`)
  and writes `threshold_percentage`/`abstract_threshold`/`payable_status_id` to
  `sys_pm_clients_returns`, then tries to sync `sys_pm_landlord_accounts_transaction`.
  Live check today: `payable_status_id` is **NULL on all 1,448,709 rows**, and
  `sys_pm_landlord_accounts_transaction` has 0 rows to sync into — so this
  entire code path (`ReturnThresholdTrait`, `ReturnThresholdService`, plus a
  presumed `UpdateAbstractThresholdsJob`) runs real work on every payment for
  zero observable effect, on every single row, unchanged since the July audit.
- **Live and correct: `ReturnsPayThresholdTrait`.** This is the trait that
  actually implements the 85%-of-last-month rule landlords are paid against,
  via one bulk `UPDATE ... FROM` SQL statement over `cb_returns_clients` at
  return-generation time. It has barely been exercised at scale — the one real
  bulk run (July 2026, 37,665 invoices) hit a 30-second timeout mid-pipeline —
  but it is the correct implementation, not the dead one.
- **Dead/unbuilt: reconciliation.** `docs/reconciliation_module.md` (634
  lines) is an unbuilt proposal — no matching controller, model, or migration
  exists in `app/` for it. Bank-extract-to-payment matching currently happens
  only informally inside the (aspirational, non-existent-in-this-repo)
  `processPaymentsLogs()` description; there is no reconciliation UI or job in
  the live Laravel app.
- **Dead: `finalized_status`** on `ReturnSummary` (0 on all rows) and
  `MINIRETURN` generation (1,571 legacy rows, zero live code produces one).

## Known Bugs

1. **Two incompatible payment-allocation implementations run concurrently.**
   `PaymentAllocationService` (routed via `PaymentsAllocationController`,
   `/payments/process*`) is the canonical engine — writes
   `sys_pm_tenants_payments_invoices`, uses invoice status 8/9 and payment
   status 11/12/13. Separately, `TenantPaymentsController::allocatePayment()`
   (live route `POST /ajax/tenant/payments/{id}/allocate`) and
   `deleteAllocation()` write/delete against a *different* table
   (`sys_pm_tenants_payments_87_repeat`, via the `TenantPaymentAllocation`
   model) and use invoice status **6** ("Partially Paid" per this
   controller's comment, but `DebtorInvoice::getStatusNameAttribute()` maps 6
   to "Posted") and payment status 9 ("Fully Allocated" here, vs. "Cleared"
   everywhere else 9 is used). Live check: the shadow table currently holds
   **0 rows**, so no real allocation has gone through this path yet — but the
   route is wired and reachable, and its sibling UI page (`allocatePage()` →
   `allocate-payment.blade.php`) calls a route name
   (`ajax.tenant.payments.allocate.store`) that is commented out in
   `routes/web.php`, so that particular page would fatal on load if anyone
   ever routed to it (currently no route points at `allocatePage()` either —
   fully orphaned).
2. **Invoice status 62 ("Cleared for Returns") is documented but never set.**
   `PaymentAllocationService`'s own header comment lists it; grep confirms
   zero occurrences of it being assigned anywhere. Returns eligibility runs
   purely on `current_status_id = 1` + `returns_period` match.
3. **GL posting docs disagree with the GL code on failure behavior.**
   `PaymentAllocationService::postAllocationGl()`'s docblock calls Journal 3
   "FATAL — a GL failure throws and rolls back," but `GlPostingTrait`'s own
   balance check (`validateGlBalance()`) only **warns**, never throws, on an
   imbalance. The class-level docblock and the method-level docblock actually
   contradict each other in the same file.
4. **Bulk rent generation only creates "Unit Rent" line items** — the
   per-lease invoiceable-item catalog (parking, service charge, etc.) is
   ignored — and **lease escalations never update `Unit.rent_amount`**, the
   column bulk generation reads from, so an applied escalation has no
   automatic effect on the next invoice run.

## Open Questions

- Is the `sys_pm_tenants_payments_87_repeat` allocation path (Known Bug 1)
  genuinely abandoned code, or a half-migrated replacement someone intended
  to finish? Worth a direct question to whoever wrote `TenantPaymentsController`'s
  allocation methods before either wiring it up fully or deleting it.
- What, concretely, is supposed to set `cb_returns_summary.paid_status`?
  Nothing in `app/` does; either a manual process exists outside the repo or
  the payout confirmation step was never built.
- Given approval is 99.93% stuck at level 1, is the 4-level chain actually
  providing oversight anywhere, or would collapsing to 1–2 levels (per the
  existing redesign proposal) simply be net-safer given money is currently
  paid out to essentially nobody through the "proper" channel?
- `docs/fix_summary.md`, referenced in the task brief, does not exist in the
  repo — likely renamed/removed; the closest surviving equivalents are the
  "Known issues (verified)" sections embedded in `111_LATEST_MODULE_FLOWS/returns-generation.md`
  and `invoice-generation.md`.

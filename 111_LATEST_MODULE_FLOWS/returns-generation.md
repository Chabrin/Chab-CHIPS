# Returns Generation — Flow & Dependencies

"Returns" = money owed back to a landlord/client after rent is collected and commission/deductions are taken out — not a product-return concept. Manual, admin-triggered process (like invoice generation); no cron job runs it.

## Trigger

`GET /returns/generate` (`ClientReturnsController::showGenerate`, line 791) renders a period/property picker → `POST /returns/generate` (`ClientReturnsController::processGenerate`, line 816). Supports single-property, all-properties, and merge-batch modes.

## Core models

- **`ReturnSummary`** (`cb_returns_summary`) — one row per property per period ("MAINRETURN"), or one rolled-up row per merge batch ("MASTERRETURN"). Carries `prepared_status`/`checked_status`/`finalized_status`/`approved_status`/`paid_status` fields, each with its own `_by`/`_date` — but only `approved_status` was confirmed as actually driven by the current approval flow (see Known Issues #4).
- **`ReturnClient`** (`cb_returns_clients`) — line items (rent CR, deductions DR, etc.). `invoice_id` FKs back to `sys_pm_debtors_invoices`; `summary()` belongs-to `ReturnSummary` via `return_code`.
- **`ReturnsMergeBatch`** / **`ReturnsMergeBatchItem`** (`cb_returns_merge_batches`) — groups multiple properties under one anchor landlord so they get a single combined payout instead of separate per-property returns.

## Generation — 7 steps, per property, one DB transaction

1. **`insertRentLines`** (`ReturnsRentLinesTrait.php:29`) — pulls CR rent lines from `sys_pm_debtors_invoices` where `returns_period::date = <selected month>`, `current_status_id = 1`, `debtor_type_id = 2`, and the invoice isn't already inside an *approved* return for that period. Computes `commission_amount`/`vat_amount` per line from the property's `sys_pm_service_agreements`.
2. **`updatePayThresholds`** (`ReturnsPayThresholdTrait.php:28`) — the **85% rule**: a tenant is only payable if they paid ≥85% of *last* month's invoice; a brand-new tenant (no prior invoice) is payable by default; otherwise `net_amount` is forced to 0 (rent withheld from the landlord payout, not the return line itself).
3. **Deductions** — `insertAdvancePaymentLines` (advances from `cb_landlord_account`), `insertRecoveryLines`/`insertCollectionLines` (from `cb_money_recoveries`), `insertCommissionLines`.
4. **`insertMainReturnSummariesLegacy`** (line 1167) — aggregates that property's lines into one `cb_returns_summary` row.
6. **`markLandlordAccountRecovered`** / **`linkMoneyRecoveriesToReturns`** — marks the deduction source rows as consumed.
7. **`createApprovalWorkflowItems`** (line 1249) — inserts one `cb_approval_items` row (`item_type = 'return_summary'`, `total_levels = 4`) and a level-1 `cb_item_approvers` row auto-assigned to the property's `field_officer_id`.

*(Step numbering in the code jumps 1-4, 6-7 — there is no step 5 inside the per-property loop.)*

After the loop, **once** (only in all-properties or merge-batch mode): **`generateMasterReturns`** (`ReturnsMasterReturnTrait.php:41`) rolls up the per-property MAINRETURNs sharing a merge batch into one MASTERRETURN — `net_amount = SUM(child net_amount) − landlord-level deductions` — and stamps each child's `parent_return_code`/`is_merged` (added by migration `2026_07_09_000001`).

**Regeneration guard:** if an approved return already exists for a period/property, generation is rejected outright (`approvedReturnsExistForPeriod`); if unapproved ones exist, they're deleted and rebuilt from scratch on every run.

## Approval workflow

A **4-level sequential approval**, run through its own `ReturnsApprovalController` (not the generic `WorkflowApprovalController` used elsewhere in the app) against `cb_approval_items`/`cb_item_approvers`. Each level's approver acts in turn (`current_level` advances by 1 per approval); rejecting sets `cb_returns_summary.approved_status = 2`. Only once level 4 clears does `approved_status` get set to `1` — this happens inside **`ProcessReturnsFinalApprovalJob`** (queued, uses `HandlesReturnApprovalWorkflow`), which chunks 100 returns at a time and, per return: `updateReturnStatus` (approved_status=1) → `createLandlordLedgerCredit` → `createCommissionDebit` → `createCommissionGLEntry` → `recordWorkflowActivity`.

A landlord statement PDF is generated per return (`Pdf::loadView('property.processed-returns.pdf', ...)`, `ClientReturnsController.php:1852`) and a separate master version for merged returns (line 1688).

## Dependencies

**Reads:** `sys_pm_debtors_invoices` (rent source, filtered on `returns_period` + `current_status_id=1`), `sys_pm_properties`, `sys_pm_units`, `sys_pm_clients` (tenant + landlord via service agreement), `sys_pm_service_agreements` (commission %, VAT %), `cb_landlord_account` (advances/opening balance), `cb_money_recoveries` (recoveries/deposits).

**Writes:** `cb_returns_clients`, `cb_returns_summary`, `cb_approval_items`, `cb_item_approvers`, plus marking consumed rows in `cb_landlord_account`/`cb_money_recoveries`.

## Known issues (verified)

1. **~~Bulk-generated rent invoices can never be picked up into a return.~~ Fixed 2026-07-31.** The bulk rent-invoice generator (`InvoicingController::processGenerateInvoices`) did not set `returns_period` in its `INSERT`, so `insertRentLines`'s `i.returns_period::date = ?::date` filter never matched (`NULL`). This was caught in production: a July 2026 bulk run created 37,660 invoices, and returns generation for that period only picked up ~4 (the handful of older manually-created invoices, which do set `returns_period` via `storeInvoiceItem`'s validation). Fixed by adding `returns_period` (set to the same billing month as `date_due`) to the generator's `INSERT`/`SELECT` column lists. The existing 37,660 July 2026 rows were backfilled (`returns_period = '2026-07-01'`, scoped precisely by `batch_number = '202607311728'`) — confirmed 37,665 July invoices now match the returns-eligibility filter, up from ~5.

2. **Invoice status 62 ("Cleared for Returns") is documented but never used.** `PaymentAllocationService.php`'s own header comment lists it as a status code the service assigns — verified there are **zero** occurrences of `current_status_id` being set to `62` anywhere in the codebase. Returns eligibility is actually driven purely by `current_status_id = 1` (still Pending) + a `returns_period` date match + not already in an approved return — not by any dedicated "cleared for returns" state. Treat that line in `PaymentAllocationService`'s comment (and the earlier note in `invoice-generation.md`'s Known Issues) as aspirational/stale, not actual behavior.

3. **`paid_status` is read but never written.** `cb_returns_summary.paid_status` is checked in `ClientReturnsController.php:287` and reported on in `AccountsReportsController.php:578`, but no code path in this app sets it to `1`. Whatever marks a return as actually paid out (after `ProcessReturnsFinalApprovalJob` posts the GL/ledger entries) is either missing, manual, or lives outside this codebase.

4. **`prepared_status`/`checked_status`/`finalized_status` on `ReturnSummary` are unconfirmed.** They exist as fillable fields alongside `approved_status`, suggesting an earlier or parallel review workflow, but this pass didn't find where (or whether) they're actually written by the current 4-level `cb_approval_items` flow. Don't assume they reflect real state without checking further.

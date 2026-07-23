# Invoice Generation — Flow & Dependencies

Two separate paths create rows in `sys_pm_debtors_invoices` (model `DebtorInvoice`, `debtor_type_id = 2` for tenant invoices). There is **no scheduled/cron job** for invoice generation — both paths are user-triggered.

## 1. Bulk monthly rent generation (the real "generation" logic)

**Trigger:** `GET /generate/invoices` (`InvoicingController::showGenerateInvoices`, line 1503) renders a page where an admin picks a billing month → `POST /generate/invoices` (`InvoicingController::processGenerateInvoices`, line 1581-1889). Manual button click only; not cron-driven.

**Idempotency:** checks `sys_pm_generated_invoice_months` (`GeneratedInvoiceMonth`) for that month first; rejects with HTTP 422 if already generated.

**Core logic** (raw SQL `INSERT ... SELECT`, lines ~1613-1770):
- Reads `sys_pm_lease_agreements` (`lease_status = 'Active'`) joined to `sys_pm_units`, `sys_pm_properties`, `sys_pm_service_agreements`.
- Cross-joined to `sys_set_invoiceable_items` **hardcoded to `item_name = 'Unit Rent'` only** — no other invoiceable item type is auto-generated.
- Only leases where `u.rent_amount > 0`.
- Amount source: `sys_pm_units.rent_amount` directly (not the per-lease item catalog — see Known Issues).
- VAT hardcoded at 16%; commission pulled from `sla.commission_percentage`.
- Sets: `current_status_id = 1` (Pending), `total_amount`/`current_balance = rent_amount`, `cycle_id = 2` (hardcoded), `date_due` = 1st of selected month, `processed = 'No'`.
- Reference number pattern: `INV{year}{month}{unit_code}-Unit Rent for {Month Year}-{lease_id}`.

**Batch tracking (fixed 2026-07-23):** every run generates a minute-precision `batch_number` (`YmdHi`, e.g. `202607231742`; a 2-digit disambiguator is appended on the rare within-the-same-minute collision, checked manually against `sys_pm_generated_invoice_months.batch_number` before use). A placeholder tracking row is inserted into `sys_pm_generated_invoice_months` *first*, then every invoice created by the run is stamped with that `batch_number` (`sys_pm_debtors_invoices.batch_number`), and the tracking row is updated with the final count once generation completes. All of it — placeholder insert, invoice insert, ledger insert, tracking update — runs inside one `DB::beginTransaction()`/`DB::commit()`; any failure rolls back the whole run, including the placeholder row.

**Immediately after:**
- A second raw SQL insert writes debit ledger rows into `sys_pm_tenants_accounts_trans` for the invoices just created — matched by `WHERE inv.batch_number = ?` (previously matched by `created_by` + a 10-second time window, which could bleed across concurrent/overlapping runs; replaced with an exact batch match).
- The `sys_pm_generated_invoice_months` tracking row (created as a placeholder at the start of the run) is updated with the final invoice count.

## 2. Manual single-invoice creation

`InvoicingController::storeInvoiceItem` (`POST` via `property/invoices/tenant/items`) — a form-driven create for one-off/non-rent charges. Validates `tenant_id`, `property_id`, `unit_id`, `lease_id`, `item_type_id`, amount, etc.; creates one `DebtorInvoice` row directly, also starting at `current_status_id = 1`.

## Status lifecycle

Starts at **1 (Pending)**. No approval workflow gates it — `InvoiceWorkflow` (`sys_pm_debtors_invoices_60_repeat`) is only ever written to as an audit trail (who/when), not a gate; `WorkflowApprovalController` never references this table. From Pending, status is advanced entirely by **payment allocation** (see Dependencies below) — there is no intermediate approval step.

## Dependencies

**Reads:** `sys_pm_lease_agreements`, `sys_pm_units` (rent_amount), `sys_pm_properties`, `sys_pm_service_agreements` (commission %), `sys_set_invoiceable_items` (filtered to "Unit Rent"), `sys_set_global_settings` (VAT rate).

**Writes:** `sys_pm_debtors_invoices`, `sys_pm_tenants_accounts_trans`, `sys_pm_generated_invoice_months`.

**Models involved:** `DebtorInvoice`, `LeaseAgreement`, `Unit`, `Property`, `ServiceAgreementInvoiceClient`, `InvoiceableItem`, `InvoiceableItemCycle`, `LeaseAgreementInvoiceableItem` (per-lease item catalog — see below), `GeneratedInvoiceMonth`.

**Downstream consumers:** `PaymentAllocationService` / `TenantPaymentsController` (payment allocation — advances invoice status), `ClientReturnsController` + `Returns*Trait` family (landlord returns processing), `AccountsReportsController` (statements/reporting).

## Known issues (verified, not yet fixed)

1. **Escalations don't reach generated invoices.** `LeaseEscalationController::applyEscalation()` (line 570, also called by the `escalations:process` cron command) updates `LeaseAgreementInvoiceableItem.amount` and `lease.escalation_rate`/`escalation_frequency` — it never touches `sys_pm_units.rent_amount`. Bulk generation reads `u.rent_amount` directly. Net effect: an applied rent escalation has no automatic effect on the next generated invoice unless `Unit.rent_amount` is updated separately by hand.

2. **Bulk generation ignores the per-lease item catalog.** `LeaseAgreementInvoiceableItem` (`sys_pm_lease_agreements_27_repeat`) is the real source of truth for what's chargeable per lease (rent, parking, service charge, etc. — each with its own `amount`/`cycle_id`). The escalation UI reads from it correctly for display, but bulk generation bypasses it entirely and only ever invoices the single "Unit Rent" item type read from `Unit.rent_amount`.

3. **Two competing status-code schemes on the same column**, both live:
   - `PaymentAllocationService` (`app/Services/PaymentAllocationService.php:33-37`, routes `payments/process*`): invoice 8 = Partially Cleared, 9 = Cleared, 62 = Cleared for Returns.
   - `TenantPaymentsController` (`ajax/tenant/payments/{id}/allocate`, still an active route): invoice **6** = "Partially Paid" — but `DebtorInvoice::getStatusNameAttribute()` (line 156) maps 6 to **"Posted"**, and 8/9 to "Partially Cleared"/"Cleared".
   - Which meaning an invoice's `current_status_id` actually has depends on which allocation endpoint touched it. Any reporting/UI that trusts `status_name` for status 6/8/9 should be treated as unreliable until this is reconciled.

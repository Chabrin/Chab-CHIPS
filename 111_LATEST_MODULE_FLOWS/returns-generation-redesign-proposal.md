# Returns Generation — Simplification & Redesign Proposal

**Status: PROPOSAL — not yet implemented.** Grounded in a direct study of the live/migrated data (queried 2026-07-31), not just the code. See `returns-generation.md` for how the current system works.

## What the data actually shows

| Finding | Evidence |
|---|---|
| The 4-level approval workflow essentially never completes | 9,675 `cb_approval_items` (return_summary) spanning **2026-03-24 to 2026-07-31** (real usage). **9,668 (99.93%) stuck at level 1 "Pending."** Only 4 have ever reached "Approved." |
| The parallel "abstract threshold" system is dead weight, but still runs on every payment | `sys_pm_clients_returns.payable_status_id` is **NULL on all 1,448,709 rows**. `sys_pm_landlord_accounts_transaction` (its downstream ledger sync target) has **0 rows**. `ReturnThresholdTrait` fires on every payment allocation (`PaymentAllocationService.php:410`) and, per prior analysis, uses an inverted formula — for zero benefit. |
| Merging is a rare edge case, not a core need | Only **7** `cb_returns_merge_batches` exist. Only **14 of 23,808** (0.06%) return summaries are `is_merged`. |
| A third return type exists in data but nothing generates it | `MINIRETURN` = 1,571 of 23,808 summary rows (6.6%), but grep confirms it's **only referenced in a PDF/Excel download whitelist** — no generation code produces it. Migrated/legacy only. |
| Most status-tracking fields are migrated snapshots, not live app state | `cb_returns_summary.finalized_status` is **0 on all 23,808 rows** (fully dead). `prepared_status`/`checked_status`/`paid_status` do vary (91% paid, 93% prepared/checked) — but no code in this app ever writes `paid_status`, so this variation is almost certainly carried over from the data migration, not produced by current logic. |
| The live threshold trait itself has barely run at real scale | `cb_returns_clients.payable_status` (what `ReturnsPayThresholdTrait` — the *correct* implementation — is supposed to populate) is set on only **125 of 1,063,405 rows (0.01%)**. Matches what we've observed directly: the only real bulk run (July 2026, 37,665 invoices) errored out mid-pipeline on a 30-second timeout. |
| Entry-type naming has legacy duplicates | `cb_returns_clients.entry_type`: `Commission` (139,391 rows) vs `COMMISSION` (6 rows); similar tiny-count legacy variants `WABILL`/`ELBILL`/`HODE`/`ELDE`/`WADE`/`OTHERRECS` (3–363 rows each) alongside their canonical full-word equivalents. |

**Bottom line:** almost everything that looks like rich historical behavior in this data (varied approval/paid/prepared/checked status, MINIRETURN records) is a frozen snapshot from data migration. The *current, live* generation+approval code has only been meaningfully exercised a handful of times since going live, and the one real attempt at scale (this week) broke. The redesign should optimize for correctness and reliability at real volume, not for preserving quirks of migrated data.

## Proposed design

### 1. Generation — async, chunked, idempotent
Move generation off the synchronous HTTP request entirely (root cause of the timeout) into a queued job, chunked per property — mirrors the pattern already used for `ProcessReturnsFinalApprovalJob`. Track the run with a single row (reusing the `batch_number` pattern already added to invoice generation) instead of scattered log lines. One return type going forward: **MAINRETURN per property, full stop.** Merging becomes a separate, explicit, manual action ("merge these N properties into one payout") triggered only when actually needed — not baked into every generation run. Drop `MINIRETURN` generation entirely (nothing produces it today); keep old MINIRETURN rows read-only for historical statements if landlords need them.

### 2. Threshold — one formula, one place, computed at generation time
Delete `ReturnThresholdTrait`, `ReturnThresholdService`, `UpdateAbstractThresholdsJob` (already confirmed unreachable), and stop writing to `sys_pm_clients_returns`/`sys_pm_landlord_accounts_transaction` for this purpose — confirmed dead output, wasted work on every payment. Keep exactly one threshold calculation — the verified-correct formula (`% paid = (total_amount − current_balance) / total_amount`) — computed once, at return-generation time, straight from `sys_pm_debtors_invoices`. No live per-payment duplicate needed; generation already reads real invoice state at run time.

**Business rule** (per your description): if last month's collection was ≥85%, pay the landlord the full current-month rent; otherwise pay only what's actually been collected this period. **This is a behavior change from today** — the current live trait pays literal **zero** on failure, not the collected percentage. Flagging explicitly since it affects real payout amounts.

### 3. Approval — 1 level, not 4
Data shows the 4-level chain essentially never clears (99.93% stuck at level 1 for 4+ months of real use) — it's not protecting anything, just creating a backlog. Replace with a single approve/reject action, immediately triggering the ledger/GL posting that today only fires after level 4. If a genuine second check matters, cap at 2 levels — not 4. Worth reconsidering whether a dedicated `ReturnsApprovalController` is even needed at 1-2 levels, versus reusing the app's existing generic `WorkflowApprovalController`.

### 4. Status fields — one source of truth
Drop `finalized_status` (confirmed 100% dead). Drop `prepared_status`/`checked_status` (not driven by any live code path — migrated noise, not real state) unless a real prepare/check step is wanted, in which case build it deliberately rather than keep the unused field. Keep `approved_status` (0/1/2) as the one authoritative approval state, and add a `paid_status`/`paid_at` that the app *actually sets* when a payout is confirmed — today nothing sets it, it just reflects migration history.

### 5. Entry types — normalize
Collapse the legacy duplicate/abbreviated entry-type codes into their canonical full-word equivalents (`COMMISSION`→`Commission`, `WABILL`→`Water Bill`, etc.) — each legacy variant is under 400 rows against 50k+ for the canonical form.

## What this deliberately does NOT change
- The 85% threshold *concept* itself, beyond fixing the zero-on-failure payout and consolidating to one implementation.
- The financial line-item granularity in `cb_returns_clients` (rent/deductions/commission/VAT breakdown) — that stays as-is.

## Suggested build order
1. **Async/chunked generation** — unblocks the immediate timeout, no behavior change.
2. **Delete the dead abstract-threshold system + dead status fields** — pure risk/complexity reduction, touches nothing that currently produces correct output.
3. **Consolidate the threshold formula + implement "pay collected %, not zero"** — the one real behavior change; needs your explicit confirmation before building since it changes payout amounts.
4. **Collapse approval to 1-2 levels.**

Each step is independently shippable and rollback-able — none require the others to be done first, though 1 unblocks actually testing 2-4 at real scale.

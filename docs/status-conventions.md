# Status Field Conventions — Landlord, Property, SLA, Unit, Tenant, Lease

## Purpose

This document maps how `current_status_id` (and related status/lifecycle fields) are actually
used today across the onboarding chain — Landlord → Property → Service Agreement (SLA) → Unit →
Tenant → Lease — and defines the standard those fields should follow going forward.

It exists because `current_status_id` is a **shared, generic column name reused across dozens of
unrelated tables in this app** (invoices, payroll, leave requests, cheque books, HR — all
out of scope here), and on several of the 6 entities above it has been made to answer more than
one question at once. A status column should answer exactly **one** question. Every conflation
documented below is a violation of that rule and has already caused, or will cause, a real bug.

This is a report and reference guide, produced from a full project audit. **It does not itself
change any code or data** — several of the findings below are flagged as open risks precisely
because fixing them safely requires its own dedicated, scoped piece of work (see "Open risks —
not remediated here").

## The standard

Across Landlord, Property, SLA, Unit, Tenant, and Lease, `current_status_id` should mean **one
thing only: maker-checker approval state**, using the same three values everywhere:

| Value | Meaning |
|---|---|
| `1` | Pending — awaiting approval at `/property/confirmations` |
| `5` | Approved |
| `41` | Rejected |

Nothing else should ever be written into this column for these 6 entities — not occupancy, not a
merge/rename sentinel, not a lease renewal/termination flag, not a payment state. Each entity's
*other* lifecycle concerns already have (or should have) their own dedicated column:

- **Unit** → `occupancy_status_id` (Vacant=`31`/Occupied=`38`) owns occupancy. `current_status_id`
  owns approval only.
- **Lease** → `lease_status` (string: `Active`/`Terminated`/`Expired`/`Renewed`) owns lease
  lifecycle. `current_status_id` owns approval only.
- **Property** → merge state should live in its own column (e.g. a future `merged_at`/`is_merged`),
  not share `current_status_id`. The existing `active` boolean is a separate, legitimate third
  flag (see below) and should stay separate too.

## Per-entity status inventory

### Landlord & Tenant (`Client.current_status_id`, table `sys_pm_clients`)

| Source | Meaning | Scale (live data) |
|---|---|---|
| `ClientsController::store` (landlord creation) | Sets `1` (Pending) — correct | — |
| `TenantsController::createTenant` (tenant creation) | Sets `1` (Pending) — correct, fixed this session (previously unset → `NULL`) | — |
| `PropertyConfirmationController` / `OnboardingPipelineController` | Approve→`5`, Reject→`41` | — |
| **Legacy ETL data** (`mysql_postgres_views/chabrins_to_postgres_views.sql:155-158,261-264`) | Populated `current_status_id` from the *old* system's Active/Inactive flag — `1`=legacy-Active, `2`=legacy-Inactive. Unrelated to Pending/Approved/Rejected. | **915 landlords at `1`, 291 at `2`**; **41,359 tenants at `1`, 27,697 at `2`** |

No report, invoicing, or listing page filters on this column outside the two controllers above —
a Pending/Approved/Rejected landlord or tenant looks identical in every normal listing today.

A second, unused column `client_status_id` also exists in `$fillable`, populated by the same
legacy ETL logic, and is never read anywhere in `app/`. Dead weight, not currently harmful.

### Property (`Property.current_status_id`, table `sys_pm_properties`)

| Source | Meaning | Scale |
|---|---|---|
| `PropertiesController::store` | Sets `1` (Pending) — correct | — |
| `PropertyConfirmationController` | Approve→`5`, Reject→`41` | — |
| **`PropertyMergeController.php:313`** | Sets `current_status_id = 0` to mark a property "merged away" — a fourth value with no meaning in the approval scheme | — |
| **`PropertyMergeController.php:417-421`** (merge reversal) — **bug** | Hardcodes the restored property back to `current_status_id = 1` instead of restoring its actual prior value from the merge snapshot. A previously **Approved** property that gets merged then un-merged silently comes back as **Pending**. | — |
| **Legacy ETL data** | Same Active(1)/Inactive(2) collision as Client | **542 properties at `1`, 1,334 at `2`** |

`Property` also has a separate `active` boolean (a global model scope, `where('active', 1)`) —
a legitimate third, distinct flag (see "Returns Config" below), not itself a conflation, but easy
to confuse with `current_status_id` if not documented.

`PropertiesController::getProperties` (the main `/property/listings` source) applies no
`current_status_id` filter — properties of any approval status appear in normal listings.

### Service Agreement / SLA (`ServiceAgreement.current_status_id`, table `sys_pm_service_agreements`)

The cleanest of the six. `SLAController::store` now sets `1` (was hardcoded `5` — fixed this
session). No conflation found anywhere. The one dropdown that used to filter by status
(`SLAController::getSLAForProperty`) already has that filter commented out, so nothing downstream
assumes SLAs are pre-approved. No legacy-ETL landmine (no equivalent old-system field existed to
collide with).

### Unit (`Unit.current_status_id` + `Unit.occupancy_status_id`, table `sys_pm_units`)

| Source | Issue |
|---|---|
| `UnitsController::store` | **Fixed this session** — used to copy the occupancy value into `current_status_id`; now sets `current_status_id = 1` (Pending) independently of `occupancy_status_id`. |
| `InspectionsController::getUnitsByProperty`, `UnitsController::getUnits` | **Fixed this session** — used to filter on the (wrongly conflated) `current_status_id = 31`; now correctly filter on `occupancy_status_id = 31`. |
| **`BulkUploadController.php` — still open** | Unit creation (~line 576-577) and unit update (~line 127-128) still perform the **exact same conflation** the main creation path had before this session's fix. Also uses `32` for "Occupied" — a fourth, inconsistent constant (the real convention is `38`). Bulk-uploaded units never get `current_status_id = 1`, so they skip the maker-checker queue entirely. |
| **`TenantExitController.php:703-706`** | Sets a vacated unit's `occupancy_status_id = 3` — matches neither the real write-path convention (`31`) nor any other convention found. Units become invisible to standard vacant/occupied filters after a tenant exits. **The same statement also sets the Unit's `current_status_id = 2`** — a value with no meaning in the Pending(1)/Approved(5)/Rejected(41) scheme, on the *approval* column this time, not just occupancy. A second, distinct conflation on the same table, found during the verification pass for this doc. |
| **`CheckExpiringLeases` console command** | Marks the lease Expired but never updates the unit's occupancy back to Vacant — an expired lease's unit stays "Occupied" indefinitely. |
| **Dashboard/report occupancy-vocabulary split** | `HomeController.php`, `ClientsController.php`, `ReportsController.php` read occupancy using a *different* numbering (`1`=Occupied/`3`=Vacant, or `30`/`32` with a comment admitting uncertainty). Since almost nothing writes those values, **dashboard/report occupancy counts are almost certainly wrong today**, independent of anything touched this session. |

**Positive example**: `InspectionsController::getPropertiesByZone` correctly gates on
`Property::where('current_status_id', 5)` before allowing inspections to be scheduled, and only
offers Vacant (`occupancy_status_id = 31`) units — the convention working as intended.

### Lease (`LeaseAgreement.current_status_id` + `LeaseAgreement.lease_status` string, table `sys_pm_lease_agreements`)

The most serious conflation found — **`current_status_id = 2` has three different, contradictory
meanings** depending which code wrote it:

| Writer | Meaning of `2` |
|---|---|
| `LeasesController` renewal (`bulkRenew`, old lease after creating its replacement) | "Renewed" |
| `TenantExitController` termination | "Terminated" |
| Shared status-label map used elsewhere (`HasWorkflowStatuses` trait) | **"Paid"** — an invoice/payment-domain meaning with nothing to do with leases |

At scale, existing data happens to be internally consistent purely by accident of which code path
has actually run: **27,695 leases at `current_status_id = 2` are all `lease_status = 'Terminated'`**
— but the renewal code path writes the same value `2` for a *different* meaning, so the next lease
renewal will produce a row that means something else at the exact same status value as 27,695
existing "Terminated" rows.

Separately: **41,361 leases sit at `current_status_id = 1` with `lease_status = 'Active'`** — i.e.
nearly every real, currently-active lease reads as "Pending" under the maker-checker scheme.
`LeasesController::store` has a comment bug calling this `// Active status`, and the lease
creation flow marks the unit Occupied (`occupancy_status_id = 38`) immediately at creation, before
any approval — occupancy currently tracks "a lease was submitted," not "a lease was approved."

`lease_status` (string) and `current_status_id` (numeric) are maintained independently with no
single source of truth: invoicing and most reports filter on the **string**; maker-checker,
renewal, and expiry logic use the **numeric** column.

Separately, `ReportsController.php:38` queries a `lease_status_id` column that doesn't exist on
this table at all (real columns are `current_status_id`/`lease_status`) — a broken query,
unrelated to the conflation but found in the same audit.

**Positive example**: `LeasesController::bulkRenew` correctly gates on `current_status_id = 5`
(Approved) before allowing a lease to be renewed.

## Feature dependency map — what's built on top of each entity

The status-field problems above don't stay contained to one controller; every feature built on
top of these entities inherits whatever inconsistency exists upstream.

**Landlord** (`clients/details.blade.php` tabs): Profile, Stats, Properties, SLA, Units, Tenants,
Leases, Recoveries, Inspections, Repairs & Maintenance, Returns Config — the root of the tree;
everything else traces back to a `client_id`.

**Property** (`properties/details.blade.php`) — only 5 tabs, a subset of Landlord's: Property
Info, Statistics, Units, Tenants, Leases, Repairs & Maintenance. **SLA, Recoveries, and
Inspections have no property-scoped view** despite all three carrying `property_id` — an existing
feature gap, not something this doc fixes, but relevant to how big a future gating pass would be.

**Unit — has no detail page at all.** No `show()` method exists on `UnitsController`; units only
ever render as rows inside the Property/Landlord Units tabs. Any future per-unit history view is
net-new work.

**Recovery**: a financial recovery/offset record, belongs primarily to Property+Landlord with
optional SLA/Unit/Lease/Tenant cross-references. No status gate on any of those.

**Repairs & Maintenance**: belongs strictly to Property (no `unit_id` column at all, despite being
conceptually unit-level). No property-status gate on creation.

**Inspections**: belongs to Property, with per-unit sub-records. The one feature that **correctly
enforces** the convention (see Unit section above).

**Returns Config** (property-merge-for-payout feature, under the Landlord tab): depends on
`Property.active` — a third, separate Property flag, not `current_status_id` — and has no SLA
dependency at all.

**Tenant** (`tenants/details.blade.php` tabs): Profile, Statistics (stub — not built, placeholder
message only), Payments, Leases. None filter by tenant or lease status.

**Tenant Exit** (Initiate → Inspection → Field Visit Log → Complete Vacate → Deposit-as-Rent
eligibility → Settlement → legacy single-step `finaliseExit`): initiating an exit does **not**
check the tenant's or lease's approval status. Sub-gates exist (zero-arrears check, a
landlord-approval flag) but they gate a sub-feature, not exit eligibility.

**Tenant Movements**: creates a new `LeaseAgreement` linked to the old one. **Gate exists only at
the UI dropdown-population layer, not on submit** — the dropdown endpoints filter by status when
populating the form, but `store()` trusts whatever the client actually submits, with no
server-side re-check.

**Tenant Blacklist**: same gap as Movements — the create-form dropdown filters by status, but
`store()` performs no server-side check.

**Lease signing/sending**: no status check at all — a Pending (unapproved) lease can be sent for
e-signature and signed, same as an Approved one.

**Lease bulk renewal**: correctly enforces the convention (see Lease section above).

**Invoicing / Tenant Statements / Payments**: the bulk monthly invoice generation is the *only*
bulk-invoice code path in the app — filters purely on the `lease_status` string `= 'Active'` plus
`rent_amount > 0`, never `current_status_id`. Statements and payments likewise never filter by
tenant/lease `current_status_id`.

**Returns Processing**: reads exclusively from ledger/invoice/deposit tables — zero references to
`LeaseAgreement`/`lease_status` anywhere. Fully decoupled from lease status.

### What this means

Two real, working examples of the convention already exist (Inspections scheduling, Lease bulk
renewal) — so the convention itself is sound and worth keeping as the single source of truth. The
problem is inconsistent application:

- **Correctly enforced**: Inspections scheduling, Lease bulk renewal.
- **Checked only at the UI layer, not re-verified on submit** (a data-integrity gap distinct from
  the naming/conflation issues above): Tenant Movements, Tenant Blacklist.
- **Not checked at all** (not necessarily wrong — a product decision for later, not decided here):
  Tenant Exit, Recoveries, Repairs & Maintenance, Lease signing/sending, Invoicing, Returns
  Processing.

## Open risks — flagged, not remediated here

These two require their own dedicated, careful pass — each touches either large volumes of live
data or live financial/lifecycle logic, and deserves focused attention rather than a rushed fix
bundled into a documentation pass:

1. **Legacy Active/Inactive data collision.** Tens of thousands of landlord, tenant, and property
   records carry a `current_status_id` value that means "Active" or "Inactive" in the old system,
   not Pending/Approved/Rejected in the new one. Any UI or report that treats `current_status_id
   = 1` as "awaiting approval" will surface most of the portfolio as pending when it isn't. No
   backfill/reinterpretation approach is proposed here — it needs its own discussion given the
   record counts and the financial/legal weight of touching production tenant and landlord data.

2. **Lease `current_status_id = 2` triple meaning.** Existing data is currently self-consistent
   only because every row at that value happens to have gone through the same (termination) code
   path — but the renewal code path writes the same value for a different meaning, and the shared
   label map calls it something else again. This will produce genuinely incorrect data the next
   time a lease is renewed. No specific fix is proposed here.

## Quick reference — don't do this

- Don't copy an occupancy/lifecycle value into `current_status_id` "because it's convenient" —
  this created the Unit conflation (partially fixed, still live in `BulkUploadController`).
- Don't reuse `current_status_id` for a merge sentinel, a renewal/termination flag, or a payment
  state — each of those needs its own column, not a borrowed slot on an existing one.
- Don't introduce a new meaning for a status value already used elsewhere on the same table (the
  Lease value-`2` problem) — check the shared `HasWorkflowStatuses` trait and every other writer
  of that column on that table before picking a value.
- Don't treat a dropdown/UI-layer status filter as a substitute for a server-side check on
  submit — Tenant Movements and Tenant Blacklist both have this gap today.
- When reversing/undoing an action that changed a status (see the Property merge-reversal bug),
  restore the actual prior value from a snapshot — don't hardcode a "default" status.

## Scope note

HR, payroll, leave-request, and other unrelated-domain status codes present in the shared
`HasWorkflowStatuses` trait are intentionally excluded from this document — different domain,
out of scope for this audit.

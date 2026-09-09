# Management Domain

Covers the non-financial Property/Landlord/Tenant/Lease operational core: onboarding,
confirmations, tenant lifecycle, zone assignments, and the new bulk-upload feature.
Accounting/Returns/Invoicing/Payments are a separate domain — see that write-up for
`PaymentAllocationService`, `ClientReturnsController`, invoicing, etc.

## Overview

This domain implements the onboarding chain **Landlord → Property → SLA/Unit → Tenant → Lease**,
each stage gated by a shared maker-checker queue (`current_status_id`: Pending `1` / Approved `5`
/ Rejected `41`), plus everything downstream: tenant exit, unit-to-unit movement, blacklisting,
property merging, lease escalation, recoveries, repairs, and inspections. It has no service layer
of its own — business logic lives directly in controllers (several exceed 1,000 lines), with only
a handful of shared traits (e.g. `Unit::generateUnitCode()` is reused by manual unit creation and
both bulk-upload paths).

The most consequential open issue here isn't new: it's the `current_status_id` overload documented
in `docs/status-conventions.md`. The two newest features (`TenantBulkUploadController`,
`UnitBulkUploadController`) show both a correction of a previously-documented instance of that bug
and a fresh example of the same class of gap elsewhere (see Known Issues).

## Key Controllers & Models

| Controller | Path | Lines |
|---|---|---|
| ClientsController | `app/Http/Controllers/ClientsController.php` | 2,389 |
| PropertyReportsController | `app/Http/Controllers/PropertyReportsController.php` | 2,086 |
| TenantExitController | `app/Http/Controllers/TenantExitController.php` | 1,712 |
| TenantsController | `app/Http/Controllers/TenantsController.php` | 1,694 |
| LeasesController | `app/Http/Controllers/LeasesController.php` | 1,527 |
| PropertyConfirmationController | `app/Http/Controllers/PropertyConfirmationController.php` | 1,397 |
| PropertySettingsController | `app/Http/Controllers/PropertySettingsController.php` | 928 |
| BulkUploadController (legacy, generic) | `app/Http/Controllers/BulkUploadController.php` | 916 |
| SLAController | `app/Http/Controllers/SLAController.php` | 753 |
| TenantBulkUploadController (new) | `app/Http/Controllers/TenantBulkUploadController.php` | 672 |
| LeaseEscalationController | `app/Http/Controllers/LeaseEscalationController.php` | 606 |
| InspectionsController | `app/Http/Controllers/InspectionsController.php` | 562 |
| TenantMovementController | `app/Http/Controllers/TenantMovementController.php` | 567 |
| UnitBulkUploadController (new) | `app/Http/Controllers/UnitBulkUploadController.php` | 556 |
| PropertiesController | `app/Http/Controllers/PropertiesController.php` | 588 |
| PropertyMergeController | `app/Http/Controllers/PropertyMergeController.php` | 456 |
| RepairAndMaintenanceRequestController | `app/Http/Controllers/RepairAndMaintenanceRequestController.php` | 405 |
| TenantBlacklistController | `app/Http/Controllers/TenantBlacklistController.php` | 389 |
| UnitsController | `app/Http/Controllers/UnitsController.php` | 292 |
| OnboardingPipelineController | `app/Http/Controllers/OnboardingPipelineController.php` | 257 |
| RecoveryController | `app/Http/Controllers/RecoveryController.php` | 234 |
| MoveInInspectionsController | `app/Http/Controllers/MoveInInspectionsController.php` | 219 |
| RecoveryLandlordPostingController | `app/Http/Controllers/RecoveryLandlordPostingController.php` | 199 |
| PropertyActivationController | `app/Http/Controllers/PropertyActivationController.php` | 175 |
| LeaseContractTemplateController | `app/Http/Controllers/LeaseContractTemplateController.php` | 150 |
| RepairsController | `app/Http/Controllers/RepairsController.php` | 10 (stub/legacy shell) |

(`RepairRequestController`/`InspectionController` map to `RepairAndMaintenanceRequestController`
and `InspectionsController`; no separately-named singular variants exist.)

**Models** (`app/Models/`, lines): `Client.php` (266 — landlord/tenant via `type_id`, scopes
`tenants()`/`clients()`/`landlords()`, `isTenant()`/`isClient()`), `Property.php` (201),
`Unit.php` (169), `ServiceAgreement.php` (101), `Recovery.php` (97), `LeaseAgreement.php` (90),
`ZoneAssignment.php` (77, table `sys_pm_zones_assignments`), `RepairRequest.php` (66),
`Inspection.php` (43), `InspectionUnit.php` (36), `ConfirmationLog.php` (32),
`TempUploadedTenant.php` (40, new), `TempUploadedUnit.php` (31, new) — plus related satellite
models (`RepairRequestItem/Element/Type`, `MoveInInspection`, `InspectionPicture`,
`TenantExitInspection`, `Zone`, `ZoneArea`, `ZoneAssignmentZone/Type`).

## Core Business Flows

**Onboarding chain.** Each entity is created Pending (`current_status_id = 1`) by its own
controller's `store()`, then approved/rejected via `PropertyConfirmationController` (per-entity
`approve($id)`/`reject($id)`, plus `bulkApprove`/`bulkReject`) or the read-oriented
`OnboardingPipelineController`, whose `index()` builds a personalized "what's pending, what's the
next step" worklist per user across landlords, properties, SLAs, units, tenants, and leases
(`/property/my-onboarding`). Two verified gaps: `LeasesController::store()`'s "Active status"
comment is wrong (leases are created Pending, but occupancy is set to Occupied immediately, before
approval), and `UnitsController`/`InspectionsController` are the only write paths independently
confirmed to keep `occupancy_status_id` and `current_status_id` fully separate.

**Tenant lifecycle.** Exit is the heaviest flow (`TenantExitController`, 1,712 lines, ~20 endpoints):
`initiateExit` → `saveInspection` → `saveFieldVisit` → `completeVacate` →
`checkDepositAsRentEligibility` → settlement, alongside a legacy one-shot `finaliseExit`. None of
these gate on the tenant's or lease's approval status before allowing an exit to start. Movement
(`TenantMovementController::store`) creates a new `LeaseAgreement` tied to the old one and sets
`current_status_id = 1` on the movement record — but the vacant/occupied dropdown filters that
populate the move-in unit picker are UI-layer only; `store()` does not re-verify the submitted
`unit_to_id`'s status server-side. Blacklist (`TenantBlacklistController`) has the identical
UI-only-gate shape.

**Confirmations.** `PropertyConfirmationController` (1,397 lines) is the single shared
maker-checker surface for the whole onboarding chain — one `approve`/`reject` pair per entity type
plus bulk variants, all writing `current_status_id` 5/41.

**Zone assignments.** `ZoneAssignment` (`sys_pm_zones_assignments`) is the one real cross-domain
join to HR: `staff_id` + `property_id` + `zone_id` + `assigned_as`. Property's own approval routing
(field-officer/zone-manager lookups used across confirmations and exit communications) resolves
through this table to `Staff`/`User` — noted here but out of scope to deep-dive (see the HR
agent's write-up for the `Staff` side).

## The Bulk Upload Feature

There are now **two parallel bulk-upload systems** live side by side, which is itself worth
flagging:

1. **Legacy `BulkUploadController`** (916 lines, routes `/property/bulk/*`) — an older, single-pass
   Excel (PhpSpreadsheet/`Xlsx`) importer with `downloadSample`/`upload` for Properties, Units, and
   Tenants. It parses a spreadsheet and calls `Property::create`/`Unit::create`/`Client::create`
   **directly** — no staging step; good rows land straight in the live tables in the same request
   a bad row is merely logged and skipped.

2. **New `TenantBulkUploadController` + `UnitBulkUploadController`** (added in commits `00c6965c`,
   `b6cc2fe1`, `ab1824e7`, this week) — a two-stage, per-property design:
   - `downloadTemplate` generates an Excel template (units: blank; tenants: **pre-filled**, one
     row per currently-vacant unit on that property).
   - `import` parses the uploaded file into a **staging table** (`TempUploadedTenant`/
     `TempUploadedUnit`, new migrations this week) — nothing touches `sys_pm_units`/
     `sys_pm_clients`/`sys_pm_lease_agreements` yet. Row-level validation happens here
     (`missingRequiredFields`, `unitCodeIssue`/`unitNumberIssue` duplicate checks, date
     parsability); issues are stored per row, not thrown.
   - `uploadsData`/`updateUpload`/`destroyUpload`/`destroySelectedUploads` let a user review and
     correct staged rows in a grid before committing — an explicit human checkpoint the legacy
     importer never had.
   - `pushToMain` is the commit step: iterates staged rows for a property, wrapping each real
     write in `DB::transaction` (`pushOneRow`), reusing the **same shared paths** as manual entry
     — the tenant importer reuses `LeaseCreationTrait` (shared with `LeasesController::store()`)
     rather than duplicating lease-creation logic, and the unit importer reuses
     `Unit::generateUnitCode()`. Per-row push failures land on the staging row (`push_error`)
     rather than aborting the whole batch.
   - The unit importer correctly sets `occupancy_status_id = 31` (Vacant) and
     `current_status_id = 1` (Pending) on push — it does **not** repeat the historical conflation
     bug once attributed to bulk unit creation (see Known Issues).

Net effect: the new pipeline (stage → validate → human review → transactional commit) is
meaningfully safer than the legacy parse-and-write-immediately one, but three separate code paths
can now create a `Unit` or `Client` row (manual `store()`, legacy `BulkUploadController`, new
`*BulkUploadController`), each maintained independently.

## Known Issues & Open Questions

- **PropertyMergeController reversal bug — still present.** `reverse()` (~line 417) hardcodes the
  un-merged property back to `current_status_id = 1` (Pending) instead of restoring whatever value
  the snapshot in `PropertyMergeItem.original_data` actually captured. A previously **Approved**
  property that gets merged and reversed silently comes back as Pending. Confirmed unchanged
  against `docs/status-conventions.md`'s prior finding.
- **RecoveryController create/store routes — confirmed present; the prior "missing" finding is
  stale.** `routes/web.php:1184-1185` registers `GET /property/recovery/create` and
  `POST /property/recovery` against `RecoveryController::create`/`store`, and both methods exist
  (lines 65, 83). The architecture-overview.md claim that "how `sys_pm_recoveries` rows actually
  get created was not located anywhere in `app/`" no longer holds — worth correcting that doc.
- **Lease escalation never updates `Unit.rent_amount` — still present.**
  `LeaseEscalationController::applyEscalation()` (line 570) updates
  `LeaseAgreementInvoiceableItem.amount` and the lease's `escalation_rate`/`escalation_frequency`,
  but never writes `sys_pm_units.rent_amount`. Since bulk monthly invoice generation reads
  `Unit.rent_amount` directly, an applied escalation has zero automatic effect on the next
  generated invoice.
- **Legacy `BulkUploadController`'s unit-conflation bug appears fixed, contradicting
  `status-conventions.md`.** That doc says unit creation/update there still copies occupancy into
  `current_status_id` and uses a stray `32` for Occupied. Current code (line 651-652) sets
  `occupancy_status_id = 31` / `current_status_id = 1` correctly, and no unit-update method exists
  in the controller at all anymore. `git log` shows a later commit ("Fix unit codes not reflecting
  the actual unit number") postdating the audit — the fix likely landed incidentally. Worth
  updating that doc.
- **TenantExitController's occupancy/status conflation on vacate — still present.** Lines ~706-708
  set `occupancy_status_id = 3` (no documented convention uses this; the real Vacant value is
  `31`) and, in the same statement, `current_status_id = 2` on the Unit (meaningless on the
  approval scale). Confirmed unchanged.
- **Two bulk-upload systems now coexist with no deprecation signal** — routes for both the legacy
  `BulkUploadController` and the new per-entity controllers remain registered; unclear whether
  Properties bulk upload (only on the legacy path) will ever get a new-style equivalent.
- **Open question, not investigated further**: whether `TempUploadedTenant`/`TempUploadedUnit`
  rows are purged after a successful `pushToMain`, or accumulate indefinitely.

## Status Code Reference (Management domain)

| Entity | Column | Values found in code | Meaning |
|---|---|---|---|
| Client (landlord/tenant) | `current_status_id` | `1`/`5`/`41` (new writes) vs. `1`/`2` (legacy ETL Active/Inactive) | Two incompatible scales collide on the same column — see `status-conventions.md` for record counts |
| Property | `current_status_id` | `1` Pending, `5` Approved, `41` Rejected, `0` "merged away" (PropertyMergeController-only, undocumented elsewhere) | Merge sentinel `0` is a 4th value with no place in the approval scheme |
| Property | `active` (boolean) | separate flag, drives Returns Config eligibility | Not a status code — don't confuse with `current_status_id` |
| Unit | `occupancy_status_id` | `31` Vacant, `38` Occupied (correct convention); `3` also seen (TenantExitController — contradicts `31`) | Should own occupancy exclusively; the `3` write is a bug, not an alternate valid value |
| Unit | `current_status_id` | `1`/`5`/`41` (approval, correct on manual/bulk creation); `2` also seen (TenantExitController on vacate — meaningless here) | Should own approval only |
| Lease | `lease_status` (string) | `Active`, `Terminated`, `Expired`, `Renewed` | Source of truth for invoicing/most reports |
| Lease | `current_status_id` | `1` (in practice means "Active" for ~41k real leases, despite the name), `2` (means "Renewed" in `bulkRenew`, "Terminated" in `TenantExitController`, and "Paid" in `HasWorkflowStatuses` — three contradictory meanings on one value), `5` Approved (correctly gates `bulkRenew`) | The single riskiest status field in this domain — next renewal on a `2`-valued row is a real, not hypothetical, collision |
| PropertyMerge | `current_status_id` | `2` = "Completed" (merge-specific scale, distinct from the Pending/Approved/Rejected scale used elsewhere) | Locally consistent within `PropertyMergeController` but a 4th independent vocabulary sharing the same column name |
| TenantMovement | `current_status_id` | `1` Pending on creation | No approve/reject path was located for movement records themselves within this file — worth confirming against `PropertyConfirmationController` if movements are meant to flow through it |

All contradictions above are pre-existing and documented in more depth in
`docs/status-conventions.md`; this table exists to give this domain's controllers a quick
cross-reference without re-reading that full document.

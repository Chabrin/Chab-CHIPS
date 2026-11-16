# Chab-CHIPS — Architecture Report

*Read-only analysis. No code was changed to produce this report. Synthesized from extensive
hands-on work (returns processing, tenant lifecycle, property confirmations, onboarding
pipeline, status-conventions audit) plus 6 targeted exploration passes covering every area not
already touched directly.*

## 1. Executive Summary

Chab-CHIPS is a Laravel 12 / PHP 8.2 monolith running Property, HR, Payroll, Discipline, and
Finance/Returns management for a Kenyan property management company, on a single shared database
(no microservices, no separate API layer). It is a **server-rendered Blade application** — despite
a configured Vite + Tailwind v4 pipeline, the entire live app actually runs on a vendored
jQuery + Bootstrap 5.3 + DataTables + Select2 stack loaded via plain `<script>` tags; Vite/Tailwind
compile only an unrouted `welcome.blade.php` and are effectively dead weight in production.

The database is a hybrid: ~141 tables were bulk-imported from a legacy MySQL system called
"CHABRINS" with zero Laravel migration history, while 126 migrations layered on top since
Nov 2025 either patch those legacy tables or create genuinely new features (Discipline Tracker,
Property confirmations, Tenant Exit, Lease Escalation). This split is the single most important
fact about the codebase: a table's presence in `database/migrations/` is not a reliable signal of
its schema, and repeated bug fixes this session found application code assuming a column existed,
or meant one thing, that the live legacy table didn't support.

Authorization runs on two independent, uncoordinated systems: a path-based `AccessChecker`
middleware gating whole routes via DB tables (`cb_sub_modules`/`cb_sub_module_permissions`), and
Laratrust's permission system (`->hasPermission()`) used inside controllers/Blade for finer-grained
checks — plus a third, silently broken pattern (`Gate::forUser()->check()`) that always evaluates
false because Laratrust's Gate integration is disabled in config. There is no single, consistent
approval-workflow abstraction either: at least four independent implementations exist (Property/
HR-Leave's shared `ConfirmationLog`-based maker-checker; Returns' 4-level `cb_approval_items` chain;
Discipline's linear 14-step tracker; Lease Escalation's own approval flow), each with its own
tables and status semantics.

## 2. High-Level Architecture

- **Framework**: Laravel 12 (`^12.0`), PHP `^8.2`, single-file `bootstrap/app.php` (no `Http/Kernel.php`).
- **Pattern**: Fat controllers, thin services (8 total in `app/Services/`), business logic
  concentrated in traits (20 in `app/Traits/`) composed into controllers. No Repository pattern,
  no Actions layer, no Policies, no Events/Listeners.
- **Database**: single connection per environment (SQLite dev/test per CLAUDE.md, MySQL or
  Postgres in other environments — `config/database.php` defaults to `sqlite` but is clearly
  built for MySQL/Postgres in practice, given `chips`/`chips_db_v3` database names configured).
- **Queue**: database-backed (`QUEUE_CONNECTION=database`), 16 job classes, all mail/notification
  dispatch is async via `ShouldQueue`.
- **Scheduling**: **effectively broken** — `app/Console/Kernel.php` defines 3 scheduled commands,
  but Laravel 12's `bootstrap/app.php` never calls `->withSchedule()` nor rebinds the Kernel
  contract to this legacy class, so the framework's own scheduler runs none of them (see §11).
- **Frontend**: 100% server-rendered Blade (356 files), jQuery + Bootstrap5 + DataTables + Select2
  vendored as static assets; Vite/Tailwind configured but unused by any reachable page.
- **Auth/RBAC**: Laratrust (custom `cb_*` tables) + Google2FA (TOTP) + a bespoke path-based
  `AccessChecker` middleware + a bespoke `OneLogin` single-session-enforcement middleware.

## 3. Directory Structure Overview

```
app/
  Console/Commands/     4 custom artisan commands
  Console/Kernel.php     legacy scheduler definition — NOT actually wired up (see §11)
  Exports/               11 Maatwebsite Excel export classes
  Http/Controllers/      74 controllers (fat; some 2,000+ lines)
  Http/Middleware/        2 custom middleware: AccessChecker, OneLogin
  Jobs/                  16 queued job classes
  Models/                213 model files
  Notifications/          5 notification classes (3 mail+database, 2 mail-only)
  Observers/              AuditLogsObserver — attached per-model in ~90/213 models' boot()
  Providers/              2 providers: AppServiceProvider (empty), ViewServiceProvider (sidebar menu)
  Services/               8 thin services
  Traits/                20 traits carrying most business logic
database/
  migrations/            126 files (2025-11-21 → 2026-07-10), additive/patch-only for legacy tables
docs/                    ~18 internal markdown/SQL docs (some stale — see §18)
mysql_postgres_views/    legacy CHABRINS→Postgres table-mapping docs + views (141 legacy tables)
mysql_to_postgres/       separate, smaller schema-migration/optimization toolkit
resources/
  views/                 356 Blade files across 32 top-level domain folders
  js/, css/              minimal — 2 JS files, 1 CSS file (Vite/Tailwind entry only, unused in prod)
routes/
  web.php                ~1,093 route definitions, no api.php
  console.php            only the stock `inspire` command
public/assets/           ~50 vendored JS/CSS plugin folders (jQuery, Bootstrap, DataTables, etc.)
```

## 4. Business Domains

1. **Property Management** — Landlord (Client)/Property/Unit/Service Agreement/Tenant (Client)/
   Lease onboarding chain, maker-checker approval, tenant lifecycle (exit, movements, blacklist),
   inspections, repairs, recoveries.
2. **Invoicing & Payments** — manual and bulk tenant/client invoicing, payment allocation,
   tenant statements, deposits.
3. **Returns Processing** — periodic landlord returns generation, merging, 4-level approval,
   landlord ledger posting.
4. **Reporting** — 29 distinct reports across two large controllers (Accounts, Property).
5. **HR/Staff** — staff master records, contracts, zone assignments, user-account linking.
6. **Payroll** — payroll cycles, Kenyan statutory tax calculation (PAYE/NSSF/SHA/Housing Levy).
7. **Leave Management** — leave requests/balances, its own two-stage approval chain.
8. **Discipline Tracking** — a purpose-built 14-step disciplinary case workflow.
9. **Finance/GL** — chart of accounts, GL postings, cheques, vouchers, transaction types.

## 5. Core Modules — Controller Map (representative, not exhaustive of all 74)

| Domain | Controller(s) | Approx. size |
|---|---|---|
| Property/Landlord | `ClientsController`, `PropertiesController`, `UnitsController`, `SLAController` | large |
| Tenant/Lease | `TenantsController`, `LeasesController`, `TenantExitController`, `TenantMovementController` | large |
| Confirmations | `PropertyConfirmationController`, `WorkflowApprovalController` (HR/Leave-side, shares `ConfirmationLog`) | medium |
| Onboarding tracker | `OnboardingPipelineController` | small |
| Invoicing | `InvoicingController` | **1,836 lines** |
| Returns | `ClientReturnsController` | **2,272 lines**, uses 8 `Returns*Trait` files |
| Returns approval | `ReturnsApprovalController` (the actual home of the 4-level `cb_approval_items` workflow — not `WorkflowApprovalController`) | medium |
| Reports | `AccountsReportsController` (2,076 lines, 14 reports), `PropertyReportsController` (2,086 lines, 15 reports) | very large |
| HR | `StaffController`, `HrSettingsController` (1,826 lines) | large |
| Payroll | `PayrollController`, `PayslipsController` | large |
| Leave | `LeaveManagementController` | large |
| Discipline | `DisciplineTrackerController` (1,512 lines, 14-step case flow) | large |

## 6. Request Lifecycle

Every authenticated request: `auth` → `onelogin` (`OneLogin.php`, single-session enforcement via
cache-stored session ID comparison) → `acess_checker` (sic — `AccessChecker.php`, path-based:
looks up the request path in `cb_sub_modules`, checks `cb_sub_module_permissions` for the user's
single role; **fails open** — unregistered paths are allowed by default). Inside a controller,
finer-grained checks use Laratrust's `Auth::user()->hasPermission('perm_name')` directly (the
`@permission`/`@endpermission` Blade directive is stock Laratrust, resolving to the same call) —
**not** `Gate::forUser()->check()`, which is silently broken app-wide (`permissions_as_gates` is
`false` in `config/laratrust.php` and nothing `Gate::define`s these ability names, so every such
check evaluates `false` regardless of actual permission — found live in `ClientsController`'s
`$can_read`/`$can_update`).

No Form Request classes were found in general use (validation is inline `$request->validate([...])`
in controllers); no Repository/Action layer; a controller method typically calls straight into
Eloquent models or a trait method, occasionally a Service (`PaymentAllocationService`,
`TenantBalanceService`, `LeaseContractService`, `ErrorDispatcher`, `ReturnThresholdService`, and 3
more), then returns a Blade view or JSON (most listing pages are DataTables-driven AJAX endpoints
returning `{data: [...]}`/`{records: [...], recordsTotal: N}` shapes).

## 7. Database Architecture

- **126 migrations**, heavily skewed toward being *patches* on pre-existing tables rather than
  schema-defining `Schema::create` calls. Coverage by prefix: `discipline_*` ~100% migration-managed
  (built migration-first, the one clean exception); `cb_*` (auth/returns/approvals) ~30-40%;
  `sys_*` (property/finance) <15%; `hr_*`/`payroll_*` <10%.
- **141 legacy tables** bulk-imported from the old "CHABRINS" system (short-code names like
  `CHBLOCKS`, `CHSTFF`, `CHRHMS`), documented in `mysql_postgres_views/table_mapping.md`. No
  Laravel migration ever created `sys_pm_properties`, `sys_pm_clients`, `sys_staff`,
  `sys_hr_payroll_summary`, `cb_returns_summary`, or `cb_landlord_account` — these came from raw
  SQL import and are only ever patched by later migrations.
- **`$timestamps` mismatches are a recurring, real bug class**, found in *both* directions:
  `ServiceAgreement` had `$timestamps=true` on a table with neither `created_at` nor
  `updated_at` (fixed, broke live SLA creation until fixed); the broader audit found
  13 more models with the same inverse pattern (`Company`, `Country`, `SystemControl`, etc.) still
  unaddressed, plus the opposite case (`DisciplineCase` has `$timestamps=false` despite its
  migration correctly including `timestamps()` — harmless but inconsistent).
- **Table-name mismatch bug found**: `ApprovalAction` model declares `$table = 'approval_actions'`
  but its own migration creates `cb_approval_actions` — the model would fail to resolve unless
  something outside the codebase (a view/rename) reconciles this; none was found.
- **FK enforcement is mostly conventional, not DB-enforced**: `references()`/`constrained()` appear
  in only ~44/7 of 126 migrations respectively, concentrated in the newest (Discipline, Approvals)
  features. Legacy `sys_*`/`cb_*` tables have zero DB-level foreign keys — referential integrity
  is entirely an application-code convention (`_id` columns + Eloquent relationship definitions).
- **Pivot tables are rare** (only 3 `belongsToMany` usages found: `Role`/module-permissions,
  `Position`, `Deposit`/invoice-allocations); **polymorphism is used exactly once**
  (`Notification::morphTo()`); **no UUIDs anywhere** (all auto-increment integer PKs);
  **soft deletes are almost unused** (2 of 213 models: `Gender`, `MemberBankDetail` — everything
  else hard-deletes).
- **Audit logging is per-model opt-in, not centralized**: `AuditLogsObserver` is attached via each
  model's own `boot()` calling `static::observe()` (89 models) or the newer `#[ObservedBy]`
  attribute (1 model) — **90/213 (~42%)**, not "all sensitive models" as CLAUDE.md states. Two
  specific defects: the observer hard-excludes `App\Models\User` from logging in
  `shouldLogModel()`, so `User` changes are silently never audited despite the model calling
  `static::observe()` (contradicts CLAUDE.md's claim it *is* observed); and its excluded-table
  list references `um_*`-prefixed table names that don't exist anywhere in the schema (the real
  Laravel queue/session tables are `jobs`/`sessions`, unprefixed) — dead exclusion logic.

## 8. Entity Relationship Overview

The Property domain's core chain:

```
Client (landlord, type_id=1)
  └─ Property (client_id)
       ├─ ServiceAgreement (property_id)
       ├─ Unit (property_id, client_id)
       │    └─ Client (tenant, type_id=2) — via unit_id/property_id on the tenant's own row
       │         └─ LeaseAgreement (tenant_id, unit_id, property_id, client_id=landlord)
       ├─ Recovery (property_id, client_id, optional sla/unit/lease/tenant refs)
       ├─ RepairRequest (property_id only — no unit_id despite being unit-level conceptually)
       └─ Inspection (property_id, hasMany InspectionUnit)
```

Financial chain: `DebtorInvoice` (tenant/client invoices) ←allocated-by→
`PaymentAllocationService` →writes→ `sys_pm_tenants_payments_invoices` (allocation),
`sys_pm_bills`/`sys_pm_debtors_arrears` (mirrors), `sys_pm_tenants_accounts_trans` (ledger),
GL entries (`GlPostingTrait`) → feeds → `ClientReturnsController`'s Returns pipeline
(`cb_returns_clients` → `cb_returns_summary`, MAINRETURN/MASTERRETURN) → `cb_landlord_account`
ledger postings → 4-level approval
(`cb_approval_items`/`cb_item_approvers`/`cb_approval_actions`, `ReturnsApprovalController`).

HR chain: `Staff` (`sys_staff`) ←optionally linked via→ `UserStaffLink` →→ `User` (`josya_users`,
login account) — a staff member and a user account are deliberately separate until explicitly
converted. `Staff` also links directly into the Property domain via `ZoneAssignment`
(`sys_pm_zones_assignments`, `staff_id`+`property_id`+`zone_id`), which is the one real
cross-domain integration point — Property's own approval-routing (field officer/zone manager
lookups) resolves to `Staff`/`User` IDs set on `sys_pm_properties`.

## 9. Authentication & Authorization

- **Guard**: single `web` session guard, Eloquent provider on `App\Models\User`.
- **2FA**: Google2FA (TOTP), "eternal" lifetime once verified (`lifetime=0`), SVG QR via
  `bacon/bacon-qr-code`.
- **RBAC**: Laratrust, fully remapped to `cb_*`-prefixed tables (`cb_roles`, `cb_permissions`,
  `cb_role_user`, `cb_permission_user`, `cb_permission_role`). A user has exactly one role in
  practice (`AccessChecker` looks up a single `role_id`). Middleware mode is `abort` (403), not
  redirect; the Laratrust admin panel is disabled.
- **Path-based secondary gate**: `AccessChecker` — independent of Laratrust, checks whether the
  *current route path* is registered in `cb_sub_modules` and whether the user's role has a
  matching `cb_sub_module_permissions` row. Fails open for any path not registered — a newly
  added route is accessible to everyone until someone explicitly registers it via the
  `/add/submodule` admin screen.
- **Single-session enforcement**: `OneLogin` middleware compares the live session ID against one
  cached per-user; a mismatch force-logs-out the older session.
- **Known-broken pattern**: `Gate::forUser($user)->check('some_permission')` always returns
  `false` (found in `ClientsController`) because Laratrust's Gate integration
  (`permissions_as_gates`) is disabled and nothing else defines those abilities — a trap for any
  future code that copies this pattern instead of `->hasPermission()`.

## 10. Business Logic Distribution

Business logic lives predominantly in **traits composed into fat controllers**, not in models,
services, or a dedicated domain layer:
- **Traits (20 total)**: carry the heaviest logic — 8 Returns traits (rent-line extraction,
  85%-threshold rule, deductions, summary aggregation, master-return merging, landlord-ledger
  posting, plus 2 more used elsewhere), `PaymentAllocationTrait`, `GlPostingTrait`,
  `HandlesApprovalWorkflow`/`HandlesReturnApprovalWorkflow`, `HasWorkflowStatuses`,
  `PdfGenerationTrait`/`PdfEncryptionTrait`.
- **Services (8, genuinely thin)**: `PaymentAllocationService` orchestrates (but the *algorithm*
  detail lives in its paired trait), `TenantBalanceService`, `LeaseContractService`,
  `ErrorDispatcher`, `ReturnThresholdService`, and 3 more.
- **Models**: mostly data + relationships + scopes; a few carry meaningful logic in accessors/global
  scopes (`Property`'s `active`/`user_properties`/`status_active` global scopes are the most
  consequential example found).
- **Controllers**: routing + validation + direct orchestration of the above — genuinely fat (up to
  2,272 lines for `ClientReturnsController`), consistent with CLAUDE.md's stated "no thin-controller
  pattern" convention.
- **No Policies, no Form Requests, no Actions layer, no Repository pattern** anywhere in the codebase.

## 11. Background Processing

- **Queue**: `QUEUE_CONNECTION=database`, standard `jobs` table (migration present, idempotent).
  16 job classes, all `ShouldQueue` — covering account activation, password reset, welcome
  letters, bulk transaction confirmation, payslip email (with optional PDF password-encryption),
  attendance-upload processing, returns final-approval, discipline/approval notifications, annual
  leave-calendar generation, error-log persistence, user-access logging, abstract-threshold
  recalculation, and generic communication dispatch.
- **Scheduling is broken under the current bootstrap**: `app/Console/Kernel.php` defines 3
  scheduled commands (`communications:dispatch` every minute, `leases:check-expiring` daily 08:00,
  `escalations:process` daily 07:00), but Laravel 12's `bootstrap/app.php` never calls
  `->withSchedule(...)`, and the framework unconditionally binds the Kernel contract to its own
  `Illuminate\Foundation\Console\Kernel`, not `App\Console\Kernel` — meaning `php artisan
  schedule:run`/`schedule:list` would show **none** of these three tasks. The underlying artisan
  commands themselves still work if invoked directly (cron/hosting-panel entry outside the repo,
  or manually) — but nothing inside the repo currently proves that happens. This is a significant,
  concrete finding: lease-expiry checks, escalation processing, and scheduled communications may
  not be running automatically at all today.
- **Notifications (5 classes)**: 2 mail-only (`AdvanceApprovalNotification`,
  `AdvanceScheduleNotification`), 3 mail+database (`LeaseContractNotification`,
  `LeaseEscalationDueNotification`, `LeaseExpiringNotification`) — the database channel implies an
  in-app notification store/bell UI consuming the standard `notifications` table.
- **No Events/Listeners layer exists at all** (confirmed empty `app/Events`/`app/Listeners` —
  matches CLAUDE.md's claim). Async work is exclusively Jobs + Notifications.
- **SMS is a stub, not a real integration**: every "SMS" send path found (`DispatchCommunication`,
  `SendApprovalNotification`, `SendDisciplineNotification`) just `Log::info`s the message rather
  than calling a real gateway — no SMS provider is configured in `config/services.php`.

## 12. External Integrations

- **PDF**: three PDF libraries coexist — `barryvdh/laravel-dompdf` (primary HTML→PDF), plus
  low-level `setasign/fpdi`/`tecnickcom/tcpdf` (used for manipulating/encrypting existing PDFs,
  e.g. password-protecting payslips in `PayslipEmailJob`).
- **Excel**: `maatwebsite/excel`, 11 export classes (not 7 as CLAUDE.md states — a documentation
  drift; likely written when fewer existed) plus a generic `GenericReportExport` reused across
  `AccountsReportsController`'s many report exports.
- **GeoIP**: `torann/geoip`, default service `ipdata` (API-key based), used for
  location-tagging (login/access logs).
- **Mail**: SMTP-configured (not the `log` driver), implying real outbound email in whichever
  environment's `.env` supplies real SMTP credentials.
- **No SMS gateway, no payment gateway, no maps, no AI service, no search service (no
  Scout/Elasticsearch/Algolia)** integrated anywhere in this codebase.
- **QR codes**: `bacon/bacon-qr-code`, used only to render Google2FA setup QR codes.

## 13. Frontend Architecture

**Two entirely separate, non-integrated asset pipelines coexist:**

1. **Vite pipeline** (configured, effectively unused in production): `vite.config.js` compiles
   exactly one entry pair — `resources/css/app.css` (a bare Tailwind v4 `@import`) and
   `resources/js/app.js` (imports a `bootstrap.js` that wires up global `axios` + CSRF header).
   The only Blade file that calls `@vite(...)` is `welcome.blade.php`, which **is not reachable
   through any registered route** — dead Laravel-starter boilerplate. `axios`, the one JS npm
   dependency besides Vite's own tooling, never executes in any real page for the same reason.
   `public/build/` (compiled output) is gitignored and doesn't exist on disk — `npm run build` is
   not actually required to run the app as currently used. A third layout,
   `layouts/dashboard.blade.php`, is matched by **zero** views and contains commented-out `@vite`
   calls with an explicit comment ("Vite disabled here to avoid manifest error") — direct evidence
   of an abandoned attempt to wire Vite output into a real layout.
2. **Vendored pipeline** (what the entire live app actually runs on): `layouts/main.blade.php`
   (292 of 356 views) and `layouts/auth.blade.php` (8 views) load Bootstrap 5.3, jQuery, DataTables,
   Select2, and ~50 other vendored plugin folders (SweetAlert, Flatpickr, Dropzone, FilePond,
   OwlCarousel, etc.) purely via `<script src="{{ asset(...) }}">`/`<link>` tags — zero `@vite`
   involvement. Notably, `layouts/main.blade.php` defines its own inline JS object named `toastr`
   (a Bootstrap-alert wrapper) rather than loading the real toastr.js library — every one of the
   ~220 Blade files calling `toastr.*` is actually calling this local shim.

**No SPA framework**: no Livewire, no Alpine.js, no Vue, no React anywhere in the codebase
(confirmed via exhaustive grep — zero matches for `wire:`, `x-data`, `Alpine`, `livewire`).

**AJAX pattern, confirmed consistent app-wide** (not just Property views): 259 Blade files use
jQuery `$.ajax`/`$.post`/`$.get` against Laravel routes returning JSON; 173 initialize a
DataTable; 56 use Select2. Sampled and confirmed identical in HR/Leave, Discipline Tracker, and
Finance domain views, not just Property.

## 14. Configuration Overview

- **`app.php`**: timezone hardcoded `Africa/Nairobi`; providers registered via the legacy
  config-array style for `ViewServiceProvider` and the Google2FA provider (not
  `bootstrap/providers.php`, which lists only `AppServiceProvider`) — a hybrid Laravel
  11/12-bootstrap-style mixed with pre-11 provider registration.
- **`database.php`**: default connection `sqlite` with a stray `um_` table prefix configured on
  that connection specifically (unused/leftover — doesn't match any real table naming convention
  used elsewhere); MySQL connection targets a `chips` database; Postgres connection targets schema
  `chips_db_v3`.
- **`cache.php`/`session.php`**: both database-backed, custom table names `cb_cache`/`cb_sessions`
  (following the app's `cb_`-prefix convention for its own infrastructure tables).
- **`laratrust.php`**: extensively customized (see §9); `permissions_as_gates=false` is the root
  cause of the broken `Gate::forUser()` pattern found in `ClientsController`.
- **`services.php`**: only mail-provider stubs (Postmark/Resend/SES/Slack) — no SMS/payment/maps
  config block exists, consistent with §12's finding that no such integrations exist.
- **No `broadcasting.php`, `cors.php`, or `sanctum.php`** — no broadcast, cross-origin, or API
  token auth configured; consistent with there being no `routes/api.php`.

## 15. Data Flow Between Components

Representative flow — a tenant payment being received and allocated:
```
Controller receives payment → PaymentAllocationService::processPayment()
  → 3 ordered passes (previous lease → current lease → linked leases [stub, unimplemented])
    → PaymentAllocationTrait::getUnsettledInvoices() (oldest month → payment_priority → due date)
      → allocateInvoice(): EXACT_MATCH | PARTIAL_PAYMENT | EXCESS_PAYMENT
        → writes: sys_pm_tenants_payments_invoices (allocation row)
                  sys_pm_debtors_invoices (balance/status: 1/6/8/9, or 34-37 if a recovery)
                  sys_pm_tenants_payments (status: 11/12/13)
                  sys_pm_bills, sys_pm_debtors_arrears (mirrors, via ReturnThresholdTrait)
                  GL entries (GlPostingTrait::postGlEntry — FATAL: exception rolls back everything)
```
This later feeds Returns processing (invoice data → `cb_returns_clients` → `cb_returns_summary`),
which feeds the landlord ledger (`cb_landlord_account`) and the 4-level Returns approval chain.

## 16. Dependency Map (key cross-cutting shared code)

- **`ConfirmationLog` model** — shared between `PropertyConfirmationController` (property/tenant
  domain) and `WorkflowApprovalController` (HR/Leave domain: Staff Contracts, Discipline, Zone
  Assignments, Employee Termination, Communication Templates, Leave Applications/Adjustments) —
  the one genuine cross-domain code-sharing example found for approval workflows.
- **`HasWorkflowStatuses` trait** — the shared status-label map (`1`=Pending...`41`=Rejected etc.)
  used by Property confirmations; `WorkflowApprovalController` independently defines its own,
  separate `$workflowStatuses` array with the same values — **not yet unified with the trait**, a
  duplicate-definition finding worth noting for anyone continuing the status-standardization work
  from `docs/status-conventions.md`.
- **`Staff` ↔ `ZoneAssignment` ↔ `Property`** — the one real HR↔Property integration point;
  Property's own approval-routing depends on `sys_pm_properties.field_officer_id`/`zone_manager_id`,
  which are `Staff`/`User` IDs.
- **`AuditLogsObserver`** — attached ad hoc per-model, not centrally, to ~42% of models.
- **`ErrorDispatcher` service + `ErrorDispatchJob`** — the app-wide error-logging path, called from
  nearly every controller's `catch` block.

## 17. Major User Workflows

1. **Onboarding chain**: Landlord → Property → SLA/Unit → Tenant → Lease, each gated by the shared
   Pending(1)/Approved(5)/Rejected(41) confirmation queue, trackable via
   `/property/my-onboarding`.
2. **Tenant lifecycle**: creation (with optional deposit capture, ledger dual-write) → payments/
   invoicing → exit (multi-stage: initiate → inspection → field visit → vacate → deposit
   settlement) or movement (unit-to-unit transfer, new lease created) or blacklist.
3. **Returns cycle**: periodic generation → rent-line extraction → threshold rule → deductions →
   summary aggregation → (optional) master-return merging across properties → 4-level approval
   (`ReturnsApprovalController`) → landlord ledger posting → payment/voucher.
4. **Payroll cycle**: period selection → per-contract PAYE/NSSF/SHA/Housing-Levy calculation →
   payslip generation → (separately, manually triggered) payslip email with optional PDF
   password-encryption.
5. **Discipline case**: 14 fixed steps from incident report through investigator assignment,
   evidence, hearing, outcome, to case closure — with its own activity log and per-step
   notification job, structurally unlike every other approval pattern in the app.
6. **Leave request**: submission (checked against calendar-plan balance) → two-stage approval
   (`1`→`24` Partially Approved →`5`) via the shared `WorkflowApprovalController`/`ConfirmationLog`
   mechanism also used by Property confirmations.

## 18. Known Technical Debt (observational only)

- **Scheduler likely not running** (§11) — the single highest-impact finding in this report if
  true in production; needs verification against actual server cron config (outside this repo).
- **Vite/Tailwind pipeline is dead code** in the live app (§13) — configured but unreachable.
- **`TaxCalculator` trait is dead code**, diverging from the actual live PAYE logic inline in
  `PayrollController` — a maintenance trap if someone edits the trait expecting it to be live.
- **`$timestamps` mismatches** — a recurring bug class found in both directions across at least 15
  models (one fixed: `ServiceAgreement`; 13+ still open, per §7).
- **`ApprovalAction` table-name mismatch** — model and migration disagree on the table name.
- **`Gate::forUser()->check()` is silently always-false** app-wide — a live authorization bug
  wherever it's used (`ClientsController` confirmed).
- **`AuditLogsObserver` gaps**: `User` model changes are silently never logged despite appearing
  observed; dead exclusion-list entries reference nonexistent tables.
- **Four+ independent, non-unified approval-workflow implementations** (Property/HR-Leave shared
  `ConfirmationLog`; Returns' `cb_approval_items` chain; Discipline's linear step-tracker; Lease
  Escalation) — duplicated status-label arrays (`HasWorkflowStatuses` vs.
  `WorkflowApprovalController`'s own copy), no shared abstraction.
- **`RecoveryController` has no `store()`/create route** — how `sys_pm_recoveries` rows actually
  get created was not located anywhere in `app/`; a genuine open question, not just a stylistic gap.
- **CLAUDE.md documentation drift**: states 7 Exports (actual: 11), "10+ Returns traits" (actual:
  8), "migration_tool/" directory (doesn't exist — the real directories are
  `mysql_postgres_views/`/`mysql_to_postgres/`), and that `AuditLogsObserver` covers "all sensitive
  models" (actual: ~42%, and `User` specifically is silently excluded).
- **Legacy status-data collisions** — fully documented separately in `docs/status-conventions.md`:
  tens of thousands of landlord/tenant/property/lease records carry status values whose meaning
  collided across the old system and the new maker-checker convention.
- **No tests were exercised or read in this pass** — `tests/` exists per CLAUDE.md's documented
  `composer test`/`php artisan test` commands, but this report did not audit test coverage;
  flagging as an explicit gap in this analysis rather than assuming either good or poor coverage.

## 19. Overall Mental Model

Think of Chab-CHIPS as **a large, actively-growing Laravel skin wrapped around an older, non-Laravel
property/HR/finance database.** The "new" parts of the app (Discipline Tracker, Property
Confirmations, Tenant Exit, Lease Escalation, the onboarding pipeline) are clean, migration-first,
and internally consistent — each is a good template for how new features in this codebase should
be built. The "old" parts (core property/client/staff/returns/payroll tables) are legacy data with
Laravel controllers and traits layered on top, patched incrementally, and prone to exactly the
kind of silent semantic mismatch repeated bug fixes found: a status column meaning one thing to
the code that just wrote it and something else to the code that reads it next, a `$timestamps`
flag that doesn't match the physical table, a dropdown filter that only happens to work because
of an unrelated coincidence in how a value was set elsewhere.

There is no single "front door" abstraction for approval workflows, background scheduling only
partially wired up, and two entirely separate frontend asset systems where only one is actually
live. None of this is exotic or undocumented mystery-code — it is a legible, if inconsistent,
pattern: **verify before trusting** applies to almost every cross-cutting concern in this app
(status values, timestamps, table names, scheduled jobs, which asset pipeline a given page
actually uses) rather than any single hidden landmine. Future work in a genuinely new domain
(matching Discipline Tracker's shape) can move confidently; future work touching an existing
legacy-backed feature should assume nothing about status semantics, FK integrity, or column
existence until checked directly against the live schema.

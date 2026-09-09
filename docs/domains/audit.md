# Audit Domain — Governance, Security & Oversight

*Cross-cutting survey of RBAC, audit logging, approval-workflow consistency, auth
infrastructure, scheduling, and error handling. Verified against the live Postgres dev DB
(`chips` / `chips_db_v3`) on 2026-09-08.*

## Overview

This domain is the shared plumbing every other domain plugs into: who can do what (Laratrust +
a bespoke path-based middleware), what gets recorded when they do it (`AuditLogsObserver`), how
multi-step sign-off works (four unrelated implementations), how people authenticate (Google2FA +
`OneLogin`), and whether scheduled jobs that keep data consistent actually run. Each mechanism
was built adequately in isolation, but none talk to each other — the gaps are invisible until
someone hits them in production.

## RBAC & Permissions

Three uncoordinated authorization layers exist:

1. **`AccessChecker` middleware** (path-based) — looks up the request path in `cb_sub_modules`;
   if no row matches, it **fails open**. Any route not yet registered as a sub-module is
   reachable by any authenticated user regardless of role.
2. **Laratrust `->hasPermission()` / `@permission()`** — fine-grained checks against
   `cb_permissions` via `cb_permission_role`/`cb_role_user`.
3. **`Gate::forUser($user)->check(...)`** — confirmed still present at `ClientsController.php:68-69`
   and `:242-243`. Laratrust never registers with Laravel's `Gate`, so all four calls always
   evaluate `false`; the `$can_read`/`$can_update` flags they populate are dead on arrival.

**Just-completed work this session (confirmed via DB):** `cb_permissions` now has **226** rows
(verified count). `directors` (role id 39) was missing 37 permission names referenced in code
(`hasPermission()`/`@permission()`) that had no `cb_permissions` row at all — those checks
evaluated `false` for everyone. `database/seeders/MissingPermissionsSeeder.php` now upserts the
37 names and grants them to `directors` only, via idempotent `cb_permission_role` upserts.

**Still open:** the other 12 roles (`qc_manager`, `it_department`, `accountant`,
`assistant_accountant`, `property_manager`, `hr_manager`, `office_admin_payments`,
`office_assistant`, `internal_auditor`, `zone_manager`, `field_officer`, `senior_field_officer`)
got **none** of the 37 permissions. Checks like `read_properties`, `read_tenants`,
`create_lease_agreements` still silently deny functionality to the staff who actually need it —
menu access via `cb_sub_modules` may allow the page to load while the in-controller permission
check fails, producing an inconsistent "page loads, action silently no-ops" experience.

**`App\Models\Permission` — investigated, corrected finding:** it declares
`protected $table = 'permissions'`, a table confirmed not to exist (`to_regclass` → null). But
`vendor/santigarcor/laratrust/src/Models/Permission.php`'s constructor runs
`$this->table = Config::get('laratrust.tables.permissions')` (→ `cb_permissions`) *after* the
child's property default applies — every `new Permission()` ends up pointed at `cb_permissions`
regardless. Corroborated by `cb_audit_logs`: 72 real `create` operations against `cb_permissions`
via the only Eloquent write path, `RolesController::addPermission` (`POST /permissions/add`).
**Net: not a live bug** — the hardcoded `$table` is dead code, but misleading enough that a
future edit or Laratrust upgrade could turn it into a real break. Worth a cleanup, not urgent.

## Audit Logging Coverage

`AuditLogsObserver` is wired to 91 models. It still hard-excludes `App\Models\User` from all
three lifecycle hooks — account creation, role changes, deactivation generate zero trail. Its
`$excludedTables` list (`um_failed_jobs`, `um_sessions`, `um_jobs`, `um_activity_logs`,
`um_password_resets`, `um_error_logs`, `um_email_logs`) references tables that don't exist in
the live schema at all — stale/cargo-culted, currently excludes nothing real.

Real usage (`cb_audit_logs`, 1,516 rows total, 2026-03-22 to 2026-09-03 — under 6 months despite
91 wired models): `sys_pm_zones_assignments` creates alone account for **1,182 of 1,516 (78%)**.
Everything else is thin: `cb_permissions` (72), `cb_sub_modules` (32), `approval_requests` (41),
`workflow_approvers` (48), `clients_bank_details` (26), single digits for `sys_pm_units`,
`sys_pm_properties`, `sys_staff`. There is effectively **no** audit trail for landlords/tenants
(`sys_pm_clients`), lease agreements, tenant payments, invoices, or returns — the domains that
matter most for a finance/property system have the thinnest coverage. Coverage is structural
(91 `boot()` wire-ups) but not behavioral: those models' real write paths mostly use bulk
`DB::table()` operations, which never fire Eloquent observer events.

## Approval-Workflow Fragmentation

| Implementation | Domain | Storage | Status model |
|---|---|---|---|
| `ConfirmationLog` maker-checker | Property/HR-Leave onboarding | `confirmation_logs` (JSON) | `current_status_id` on target entity (1/5/41) |
| `cb_approval_items` chain | Returns processing | `cb_approval_items` + `ApprovalAction`/`ItemApprover` | free-text `status`, 4-level chain |
| Discipline step-tracker | Discipline Tracker | `sys_discipline_*`, `DisciplineCase.current_step` | linear 14-step integer, bespoke labels |
| Lease Escalation approval | Lease Escalation | `cb_lease_escalation_approvals` | `approval_step` int + `action` string via `match()` |

Only `cb_approval_items` (`ApprovalItem` model) has `AuditLogsObserver` attached directly. Status
vocabularies are duplicated, not shared: `app/Traits/HasWorkflowStatuses` defines a canonical
60+ entry id→label map meant to be the shared vocabulary, but `WorkflowApprovalController` does
not `use` it — it hardcodes an identical copy of the same pairs inline (`1 => 'Pending'`,
`41 => 'Rejected'`, etc.). A future status fix (e.g. the `current_status_id = 2` "Paid" vs
"Terminated" vs "Renewed" collision in `docs/status-conventions.md`) must be edited in both
places or they diverge. No shared interface/base model unifies the four systems — a cross-cutting
query like "all pending approvals for this user across every module" needs four separate
integrations that will keep drifting independently.

## Authentication Infrastructure

- **Google2FA**: `'lifetime' => env('OTP_LIFETIME', 0)` — `0` means **eternal**; once a session
  passes 2FA it is never re-challenged again, and `keep_alive` (default `true`) extends it
  further on every request.
- **`OneLogin`**: single-active-session enforcement via a cache lookup compared against the
  current session id; on mismatch it logs the user out. Fails open on cache errors (logs and
  calls `$next($request)`) — reasonable degradation, but a cache outage silently disables the
  single-session guarantee.
- **Session/cache backing**: `cb_sessions` and `cb_cache` both exist and are populated
  (`SESSION_DRIVER=database`/`CACHE_STORE=database`); no `um_*`-prefixed equivalents exist
  anywhere, reinforcing that the observer's exclusion list is stale.

## Scheduler & Background-Job Risk

Re-confirmed: `bootstrap/app.php` never calls `->withSchedule(...)` and never rebinds
`App\Console\Kernel`. That legacy `Kernel` class still defines three correctly-configured
schedule entries (`communications:dispatch` every minute, `leases:check-expiring` daily 08:00,
`escalations:process` daily 07:00), but under Laravel 12's single-file bootstrap it is dead code
unless something instantiates it — nothing does. Unless an undocumented external cron calls
these artisan commands directly, **lease-expiry detection, escalation processing, and scheduled
communications do not run on their own**. This is the single highest-severity operational risk
in the domain: it fails silently, compounds daily, and produces no audit trail (nothing runs, so
there's nothing to log).

## Error Handling / Observability

`ErrorDispatcher::dispatch($e, $user)`, called from most controllers' `catch` blocks, dispatches
`ErrorDispatchJob` (`ShouldQueue`) which writes to `ErrorLogs`. That model is **not** among the
91 audited models — no trail of who resolved an error or when. Both `ErrorDispatcher` and
`ErrorDispatchJob` self-swallow their own failures (`catch (\Exception $ex) { Log::error(...) }`);
if the pipeline itself breaks, the only trace is a plain log line, and the original exception
that triggered the dispatch may already have been swallowed by the calling controller.

Re-verified per `docs/permissions_and_menus.txt`: `cb_sub_modules`/`cb_sub_module_permissions`
(the page-level gates `AccessChecker` reads) are still not backed by any seeder or migration.
`database/seeders/` has `SuperModuleSeeder` (seeds only the top-level `cb_super_modules` nav
groups) and the new `MissingPermissionsSeeder` — no `SubModuleSeeder` equivalent exists. The
docs file remains the closest thing to a spec, and it's plain text, not code.

## Risk Register (most severe first)

- **Scheduler not wired**: `bootstrap/app.php` never calls `->withSchedule()`; lease-expiry,
  escalation processing, and scheduled communications do not run automatically. Silent,
  compounding, unaudited.
- **12 of 13 roles lack the 37 recently-discovered permissions**: only `directors` was granted
  them; `property_manager`, `hr_manager`, `accountant`, etc. still fail these checks, causing
  "visible menu, non-functional action" behavior for the staff who need them most.
- **`AccessChecker` fails open** for any path missing from `cb_sub_modules` — a new route
  shipped without a menu-registration INSERT is reachable by any authenticated user, no warning.
- **Audit coverage is structural, not behavioral**: 91 models wired, but 78% of all logged
  events are zone assignments; core financial/tenant/lease/property writes mostly bypass
  Eloquent (`DB::table()`), so they generate no audit trail despite being "observed" models.
- **Dead `Gate::forUser()->check()` calls** in `ClientsController` (4 sites) always evaluate
  `false` — silently broken permission flags in that controller.
- **Approval-workflow fragmentation** across 4 systems with duplicated (not shared) status-label
  vocabularies (`HasWorkflowStatuses` vs `WorkflowApprovalController`'s own copy) — a future
  status fix applied in one place silently diverges from the other.
- **`User` model excluded from audit logging entirely** — no trail for account creation, role
  changes, or deactivation, the events most relevant to security review.
- **Eternal 2FA lifetime** (`OTP_LIFETIME=0`, `keep_alive` on) — a compromised long-lived session
  is never forced to re-verify the second factor.
- **No seeder/migration source of truth for `cb_sub_modules`** — page-level permission gates are
  hand-maintained via undocumented raw SQL, impossible to diff, review, or reproduce cleanly.
- **`ErrorLogs` not audited, and the error-dispatch pipeline self-swallows failures** — if
  error-logging itself breaks, there is no secondary alert.
- **`App\Models\Permission::$table = 'permissions'`** — confirmed dead code (Laratrust's parent
  constructor overwrites it to `cb_permissions` on every instantiation), not currently a
  functional bug, but worth removing so it can't be "fixed" into an actual break later.

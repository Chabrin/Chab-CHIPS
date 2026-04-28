# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Chab-CHIPS** is a comprehensive Property, HR, and Finance Management System built with Laravel 12. It manages staff/payroll, property leases, tenant payments, returns processing, approval workflows, and discipline tracking for a property management company in Kenya (timezone: Africa/Nairobi).

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Blade templates, Vite, Tailwind CSS v4
- **Auth/Access:** Laratrust (RBAC), Google2FA (MFA), custom `AccessChecker` and `OneLogin` middleware
- **Database:** SQLite (dev/test), MySQL/PostgreSQL (production)
- **Queue:** Database-backed queue (`QUEUE_CONNECTION=database`)
- **PDF:** barryvdh/laravel-dompdf + tecnickcom/tcpdf + setasign/fpdi
- **Excel:** maatwebsite/excel
- **Other:** torann/geoip, bacon/bacon-qr-code, maennchen/zipstream-php

## Common Commands

```bash
# Start all dev services (server + queue + logs + Vite) concurrently
composer run dev

# Run Vite only
npm run dev

# Build frontend assets
npm run build

# Run all tests (SQLite in-memory)
composer test
php artisan test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run a single test method
php artisan test --filter=test_method_name

# Code style (Laravel Pint)
./vendor/bin/pint

# Queue worker
php artisan queue:work

# Scheduled jobs
php artisan schedule:run

# Key Artisan commands defined in the app
php artisan app:check-expiring-leases
php artisan app:dispatch-scheduled-communications
php artisan app:process-escalations
```

## Architecture

### Request Lifecycle

Every authenticated request passes through three middleware in order:
1. `auth` — standard Laravel authentication
2. `onelogin` — enforces single active session per user (Cache-based); logs out duplicate sessions
3. `acess_checker` (sic) — path-based RBAC: checks `cb_sub_modules` and `cb_sub_module_permissions` tables; returns 403 or redirects to dashboard on failure

Authorization is **entirely middleware/DB-driven** — there are no Laravel Policy classes.

### Module Structure

The system is divided into major functional domains, each with large controllers (40–96 KB):

| Domain | Controller | Key Service/Trait |
|---|---|---|
| Returns Processing | `ClientReturnsController` | `PaymentAllocationService`, 10+ `Returns*Trait` files |
| Invoicing | `InvoicingController` | — |
| Staff/HR | `StaffController`, `HrSettingsController` | — |
| Payroll | `PayrollController` | `TaxCalculator` trait |
| Leave | `LeaveManagementController` | — |
| Discipline | `DisciplineTrackerController` | `HandlesApprovalWorkflow` trait |
| Workflow Approvals | `WorkflowApprovalController` | `HandlesReturnApprovalWorkflow` trait |
| Leases | `LeasesController`, `LeaseEscalationController` | `LeaseContractService` |
| Tenant Payments | `TenantPaymentsController` | `PaymentAllocationService` |
| Properties | `PropertiesController`, `PropertyConfirmationController` | — |

### Service Layer (`app/Services/`)

Thin — only 8 services. Complex business logic lives in traits (`app/Traits/`) that are composed into controllers and services. When adding logic that spans multiple controllers, prefer a trait over a service.

### Models (`app/Models/` — 179 files)

- `User` → `josya_users` table; uses Laratrust, observed by `AuditLogsObserver`
- `Client` → `sys_pm_clients`; scope methods `tenants()`, `clients()`, `landlords()`; `isTenant()` / `isClient()` helpers
- `Staff` → `sys_staff`; links to HR, payroll, discipline, and zone assignment relationships
- All sensitive models observed by `AuditLogsObserver` (logs old/new data on create/update/delete to `audit_logs` table)

### Queued Jobs (`app/Jobs/`)

16 jobs all implement `ShouldQueue`. Jobs handle: account activation, payslip emails, bulk transaction confirmation, returns final approval, discipline/approval notifications, welcome letters, attendance processing.

### Exports (`app/Exports/`)

7 Maatwebsite Excel export classes for: advance schedules, client invoices, landlord statements, statutory deductions, tenant invoices, tenant payments allocated, tenant statements.

### PDF Generation

`PdfGenerationTrait` and `PdfEncryptionTrait` (in `app/Traits/`) provide PDF output. Controllers compose these traits directly.

## Conventions Observed

- **Table naming:** Mix of prefixes — `sys_*` (property/client), `cb_*` (auth/roles), `hr_*` (HR), `payroll_*`, `discipline_*`
- **Route naming:** Kebab-case, named with dots (e.g., `clients.index`). All 726 routes are in `routes/web.php` (no API routes file)
- **Controller methods:** Standard resourceful (`index`, `store`, `update`, `destroy`) plus many custom action methods. Controllers are large by design — no thin-controller pattern used
- **Timestamps:** Some models disable timestamps (`public $timestamps = false`) — check per model before assuming `created_at`/`updated_at` exist
- **Encrypted IDs:** `Client` model exposes `encrypted_id` via accessor for URL obfuscation
- **No Policies:** Access control is middleware + DB permissions, not Policy classes
- **No Events/Listeners:** Async work goes through Jobs and Notifications directly

## Key Files

| File | Purpose |
|---|---|
| `routes/web.php` | All 726 route definitions |
| `bootstrap/app.php` | Middleware aliases, route registration |
| `app/Http/Middleware/AccessChecker.php` | Path-based RBAC middleware |
| `app/Http/Middleware/OneLogin.php` | Single-session enforcement |
| `app/Services/PaymentAllocationService.php` | Core returns/payment allocation logic |
| `app/Observers/AuditLogsObserver.php` | Audit trail for all sensitive models |
| `app/Traits/` | 20 traits containing shared business logic |
| `config/laratrust.php` | Role/permission configuration |
| `config/app.php` | Timezone (Africa/Nairobi), cipher |

## Environment Variables

Key variables from `.env.example`:

```
APP_NAME, APP_ENV, APP_KEY, APP_URL, APP_DEBUG
DB_CONNECTION=sqlite  (switch to mysql/pgsql for production)
DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_FROM_ADDRESS
REDIS_HOST, REDIS_PASSWORD, REDIS_PORT
AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET
VITE_APP_NAME
```

## Notes for Development

- **Zone assignments** (`check_zone_assignments_schema.php` at root) is a standalone diagnostic utility — not part of the app bootstrap
- The `migration_tool/`, `mysql_to_postgres/`, and `mysql_postgres_views/` directories are data-migration utilities from a MySQL→PostgreSQL migration, not part of the running app
- `docs/` contains internal documentation
- The `scripts/` directory has ad-hoc utility scripts
- Two-factor auth is configured via `config/google2fa.php`; the old `routes/2fa.php` is a deprecated stub
- Tests use SQLite in-memory with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`

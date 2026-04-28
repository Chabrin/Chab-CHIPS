# Client Returns Reconciliation Module

## Overview

### Business Context

Clients (landlords) own properties that the company manages under a Service Level Agreement (SLA). Each month, rent is collected from tenants, and after deducting the management commission, VAT, any advances the company has paid on the landlord's behalf, and any utility or other recovery charges, the net amount is paid out to the landlord.

For clients whose SLA includes utility bill management (water, electricity, garbage, etc.), the company collects those utility payments from tenants and either passes them through or deducts what it has advanced on the landlord's behalf. This means the reconciliation statement is more complex for those clients — it must clearly separate rent income from utility-related lines so the landlord can see exactly what was collected on their behalf and what was charged back.

The reconciliation module is a formal **pre-payment review step**. A staff member generates the reconciliation statement from the approved return data, reviews it for accuracy, and confirms it before the payment voucher (cheque) is created. This ensures nothing is paid without a signed-off breakdown.

### Workflow Position

```
Returns Generated
        ↓
Returns Approved (via ReturnsApprovalController — 4-level workflow)
        ↓
[NEW] Reconciliation Created (Draft) ← this module
        ↓
[NEW] Reconciliation Confirmed ← this module
        ↓
Payment Voucher Generated (PaymentVouchersController — existing)
        ↓
Cheque Cut / Bank Transfer
```

### Key Rules

- **One reconciliation per client per period.** If a client has multiple properties, each property has its own `MAINRETURN` in `cb_returns_summary`. The reconciliation aggregates all of them into a single statement for that month — mirroring how `MASTERRETURN` works but without requiring the merge-batch configuration.
- **No recalculation.** All figures come directly from the already-generated and approved `cb_returns_clients` lines. The reconciliation reads and organises; it does not recompute.
- **Confirming ≠ paying.** Confirming a reconciliation (`reconciliation_status = 1`) is a sign-off action. It does not set `paid_status = 1` on the return. The payment voucher step does that.
- **Voiding.** If a return is subsequently rejected or regenerated after a Draft reconciliation exists, the Draft must be voided. A `void_reason` is required. A voided reconciliation cannot be confirmed.
- **Duplicate guard.** Only one active (Draft or Confirmed) reconciliation may exist per `client_id + period` combination. A second attempt returns a validation error pointing to the existing record.

---

## Data Sources

| Source Table | Model | Key Fields Used | Purpose |
|---|---|---|---|
| `cb_returns_summary` | `ReturnSummary` | `landlord_code`, `property_code`, `return_period`, `approved_status`, `paid_status`, `return_type`, `net_amount`, `return_code` | Find all approved, unpaid returns for the client+period being reconciled |
| `cb_returns_clients` | `ReturnClient` | `return_code`, `entry_type`, `dr_cr`, `net_amount`, `transaction_ref`, `property_code`, `unit_code`, `tenant_name`, `payable_status` | Every individual transaction line within the return — the raw material for categorisation |
| `cb_landlord_account` | `LandlordAccount` | `payment_ref`, `entry_type_code`, `vendor_id`, `return_code` | Bridge between advance deduction lines in `cb_returns_clients` and the source `ClientAdvance` record — joined via `transaction_ref = payment_ref` |
| `sys_pm_clients_advances_requisitions` | `ClientAdvance` | `expense_category`, `narration`, `reference_number`, `advance_type_id` | Provides the human-readable category of each advance (e.g. `water_bills`, `repairs_maintenance`) so deduction lines can be labelled properly |
| `sys_pm_service_agreements` | `ServiceAgreement` | `property_id`, `client_d`, `current_status_id` | Links a property to its active SLA, confirming whether any utility billing is configured for that property |
| `sys_pm_service_agreements_186_repeat` | `ServiceAgreementBillCharge` | `parent_id` (SLA id), `item_id`, `commissionable` | Each row is one utility/billing service the company manages for the landlord under the SLA (water, electricity, garbage, etc.) — identified by `item_id` → `InvoiceableItem.item_name` |
| `sys_set_invoiceable_items` | `InvoiceableItem` | `item_name`, `is_bill`, `feature` | Provides the display name for utility items (e.g. "Water Bills", "Electricity Bills") and the `is_bill` flag which marks utility-type items |
| `sys_pm_clients_17_repeat` | `ClientBankAccount` | `account_name`, `account_number`, `bank_id`, `branch`, `return_percentage` | The landlord's payout bank account(s) shown in the statement header. A client can have multiple accounts with split percentages (e.g. 60% to account A, 40% to account B). |
| `sys_pm_clients` | `Client` | `names`, `reference_number`, `email_address`, `mobile_number` | Client/landlord display name and reference for the statement header |

---

## Line Categorisation Logic

All transaction lines are read from `cb_returns_clients` filtered to the `return_code`s that belong to this client's approved returns for the period. Each line is classified into exactly one category.

### Categories and Filter Rules

| Category | `entry_type` | `dr_cr` | Additional condition |
|---|---|---|---|
| **Rent Income** | `RENT CURRENT` or `RENT ARREARS` | `CR` | Only lines where `payable_status IN (1,2,3)` — i.e. actually payable. Lines with `payable_status=0` (below 85% threshold) are withheld and shown separately as "Non-Payable Rent" |
| **Non-Payable Rent** | `RENT CURRENT` or `RENT ARREARS` | `CR` | `payable_status = 0` — rent withheld because tenant paid less than 85% of prior month's invoice |
| **Management Commission** | `Commission` | `DR` | — |
| **VAT on Commission** | `VAT` | `DR` | — |
| **Advance Deductions** | `Advance Payments-From Landlord Acc` | `DR` | See advance sub-labelling below |
| **Utility Collections** | Any `CR` line | `CR` | Where the line's `transaction_ref` or `transaction_source` links to a `cb_money_recoveries` entry whose `item_id` is in the property's `ServiceAgreementBillCharge` item list |
| **Utility Deductions** | `Deductions Before Commission` | `DR` | Where the underlying recovery item's `item_id` matches a utility item in the SLA bill charges |
| **Add-Back Adjustments** | `Add Back Deductions` | `CR` | Lines that reverse a prior deduction |
| **Other Deductions** | `Deductions Before Commission` | `DR` | Any DR deduction not matched to a utility item |

### Non-Payable Rent — Display Logic

Lines where `payable_status = 0` still appear on the statement (so the landlord can see what was withheld and why) but are shown in a separate greyed-out section labelled "Withheld — Tenant Below 85% Payment Threshold". The `payable_status_reason` field provides the narrative for each withheld line. These amounts do **not** contribute to `gross_payable`.

### Advance Deduction Sub-Labelling

When a line has `entry_type = 'Advance Payments-From Landlord Acc'`, the `transaction_ref` on the line contains the `payment_ref` value from `cb_landlord_account`. The join chain is:

```
cb_returns_clients.transaction_ref
    → cb_landlord_account.payment_ref  (where entry_type_code indicates an advance disbursement)
        → sys_pm_clients_advances_requisitions.id  (via cb_landlord_account.vendor_id or return_code)
            → ClientAdvance.expense_category
```

`expense_category` values and their display labels:

| `expense_category` value | Display Label |
|---|---|
| `water_bills` | Water Bills |
| `electricity_bills` | Electricity Bills |
| `garbage_collection` | Garbage Collection |
| `repairs_maintenance` | Repairs & Maintenance |
| `caretaker_salaries` | Caretaker Salaries |
| `other` | Other Expense |
| *(null or unmatched)* | Advance Recovery |

Each advance deduction line is displayed as: **"{Display Label} — {ClientAdvance.reference_number}"** so the landlord can cross-reference the advance that is being recovered.

### SLA Utility Indicator

For each property in the reconciliation:
1. Load the active `ServiceAgreement` for that `property_id` (`current_status_id = 5`)
2. Load its `ServiceAgreementBillCharge` records → each has an `item_id` → load `InvoiceableItem` to get `item_name`
3. Build map: `property_code → ['Water Bills', 'Electricity Bills', ...]`

This map is used two ways:
- On the statement, properties where utility management is active get a badge: **"Utility Collections Managed"** next to the property name
- Utility-related lines for those properties are grouped under the **Utility Collections / Utility Deductions** sections instead of "Other"

Properties where **no** `ServiceAgreementBillCharge` records exist are managed for rent only, and no utility sections appear for them.

---

## Database — New Table

**Table name:** `cb_returns_reconciliations`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `reconciliation_number` | VARCHAR(60) UNIQUE | Format: `REC-YYYYMM-{client_ref}` e.g. `REC-202604-LAN0025`. If a client somehow needs a second reconciliation for the same period (after voiding the first), a suffix `-2` is appended. |
| `client_id` | BIGINT UNSIGNED NOT NULL | FK → `sys_pm_clients.id` (the landlord) |
| `period` | INT UNSIGNED NOT NULL | `YYYYMM` integer matching `cb_returns_summary.return_period`, e.g. `202604` |
| `return_codes` | TEXT NOT NULL | Comma-separated `return_code` values from `cb_returns_summary` that are covered by this reconciliation. E.g. `R1202604LAN0025P001,R1202604LAN0025P002`. Used to re-load line data on the show page. |
| `total_rent_income` | DECIMAL(15,2) DEFAULT 0 | Sum of `net_amount` for all payable rent lines (`payable_status IN (1,2,3)`) |
| `total_commission` | DECIMAL(15,2) DEFAULT 0 | Sum of all commission DR lines |
| `total_vat` | DECIMAL(15,2) DEFAULT 0 | Sum of all VAT DR lines |
| `total_advance_deductions` | DECIMAL(15,2) DEFAULT 0 | Sum of all `Advance Payments-From Landlord Acc` DR lines |
| `total_utility_deductions` | DECIMAL(15,2) DEFAULT 0 | Sum of utility-related DR deduction lines (matched via SLA bill charge item_ids) |
| `total_utility_collections` | DECIMAL(15,2) DEFAULT 0 | Sum of utility-related CR collection lines (money collected from tenants for utilities, passed through to landlord) |
| `total_other_deductions` | DECIMAL(15,2) DEFAULT 0 | Sum of DR lines not matching any other specific category |
| `gross_payable` | DECIMAL(15,2) DEFAULT 0 | `total_rent_income + total_utility_collections + total_add_backs − total_commission − total_vat − total_advance_deductions − total_utility_deductions − total_other_deductions`. This is the amount the landlord will be paid. |
| `reconciliation_status` | TINYINT DEFAULT 0 | `0` = Draft (created, under review), `1` = Confirmed (signed off, ready for voucher), `-1` = Voided (cancelled, no longer valid) |
| `notes` | TEXT NULL | Optional free-text notes added by the preparer, visible on the statement |
| `prepared_by` | BIGINT UNSIGNED NOT NULL | FK → `users.id` — the user who created the reconciliation |
| `prepared_at` | TIMESTAMP NULL | When it was created |
| `confirmed_by` | BIGINT UNSIGNED NULL | FK → `users.id` — the user who confirmed it |
| `confirmed_at` | TIMESTAMP NULL | When it was confirmed |
| `voided_by` | BIGINT UNSIGNED NULL | FK → `users.id` — the user who voided it |
| `voided_at` | TIMESTAMP NULL | When it was voided |
| `void_reason` | TEXT NULL | Mandatory text explaining why it was voided |
| `created_at` | TIMESTAMP | Laravel auto |
| `updated_at` | TIMESTAMP | Laravel auto |

**Indexes:**
- `idx_crr_client` on `client_id`
- `idx_crr_period` on `period`
- `idx_crr_status` on `reconciliation_status`
- `idx_crr_client_period` composite on `(client_id, period)` — used by the duplicate guard check

---

## New Files

### Phase 1 — Migration

**File:** `database/migrations/2026_04_27_000001_create_cb_returns_reconciliations_table.php`

Standard Laravel anonymous migration class. Uses `Schema::create('cb_returns_reconciliations', ...)` with the columns and indexes defined in the table specification above. The `down()` method calls `Schema::dropIfExists('cb_returns_reconciliations')`.

Run with: `php artisan migrate`

---

### Phase 2 — Model

**File:** `app/Models/ReturnsReconciliation.php`

```
Namespace: App\Models
Table:      cb_returns_reconciliations
PK:         id (auto-increment)
Timestamps: true (uses created_at / updated_at)
```

#### Status Constants

```php
const STATUS_DRAFT     = 0;
const STATUS_CONFIRMED = 1;
const STATUS_VOIDED    = -1;
```

#### Casts

All `total_*` and `gross_payable` columns cast to `decimal:2`. Date columns `prepared_at`, `confirmed_at`, `voided_at` cast to `datetime`.

#### Scopes

| Scope | Where clause | Use case |
|---|---|---|
| `scopeDraft($query)` | `reconciliation_status = 0` | Show only pending reconciliations |
| `scopeConfirmed($query)` | `reconciliation_status = 1` | Show only confirmed reconciliations |
| `scopeActive($query)` | `reconciliation_status >= 0` | Excludes voided — used by the duplicate guard |

#### Accessors

| Accessor | Returns | Logic |
|---|---|---|
| `getReturnCodesArrayAttribute()` | `string[]` | `array_filter(array_map('trim', explode(',', $this->return_codes)))` |
| `getStatusLabelAttribute()` | `string` | `match` on `reconciliation_status`: `0` → `'Draft'`, `1` → `'Confirmed'`, `-1` → `'Voided'` |
| `getStatusBadgeClassAttribute()` | `string` | `0` → `'badge-light-warning'`, `1` → `'badge-light-success'`, `-1` → `'badge-light-danger'` |
| `getPeriodLabelAttribute()` | `string` | Converts `202604` → `'April 2026'` using `substr()` to extract year and month, then a month-name array |

#### Relations

| Method | Type | Foreign Key | Notes |
|---|---|---|---|
| `client()` | `belongsTo(Client::class)` | `client_id` → `sys_pm_clients.id` | The landlord |
| `preparedBy()` | `belongsTo(User::class)` | `prepared_by` → `users.id` | Who created the reconciliation |
| `confirmedBy()` | `belongsTo(User::class)` | `confirmed_by` → `users.id` | Who confirmed it — `null` if still Draft |

---

### Phase 3 — Service Class

**File:** `app/Services/ReturnsReconciliationService.php`

This is the core business logic. The controller calls this service; the service has no HTTP dependencies and can be tested independently.

#### `previewForClientPeriod(int $clientId, int $period): array`

Full step-by-step logic:

**Step 1 — Load the client**
Query `sys_pm_clients` for the given `$clientId`. Read `reference_number` (e.g. `LAN0025`). This reference number is used as `landlord_code` in `cb_returns_summary`.

**Step 2 — Find approved, unpaid returns for this client+period**
```sql
SELECT * FROM cb_returns_summary
WHERE landlord_code = '{client.reference_number}'
  AND return_period = {period}
  AND approved_status = 1
  AND paid_status = 0
  AND return_type IN ('MAINRETURN', 'MINIRETURN')
ORDER BY property_code
```
- If no rows found: throw `\RuntimeException('No approved returns found for this client and period.')` — the controller catches this and returns a 422 JSON error.
- If results found: collect `return_code` values into `$returnCodes[]` and `property_code` values into `$propertyCodes[]`. Note: `MASTERRETURN` rows are intentionally excluded — the reconciliation reads the underlying `MAINRETURN`/`MINIRETURN` rows directly, not the aggregate.

**Step 3 — Load all transaction lines**
```sql
SELECT * FROM cb_returns_clients
WHERE return_code IN ('{code1}', '{code2}', ...)
ORDER BY property_code, entry_type, dr_cr
```
Returns every individual transaction line for all the returns in scope.

**Step 4 — Build advance sub-label map**
For lines where `entry_type = 'Advance Payments-From Landlord Acc'`:
- The line's `transaction_ref` field contains the `payment_ref` value from `cb_landlord_account`
- Query: `SELECT vendor_id FROM cb_landlord_account WHERE payment_ref IN (...)`
- The `vendor_id` in `cb_landlord_account` for advance entries links to `sys_pm_clients_advances_requisitions.id`
- Load those `ClientAdvance` records to get `expense_category` and `reference_number`
- Build map: `[ transaction_ref_value => [ 'label' => 'Water Bills', 'ref' => 'ADV-2026-001' ] ]`

**Step 5 — Build SLA utility item map**
For each `property_code` in `$propertyCodes`:
- Look up `sys_pm_properties.id` from the property code
- Load the active `ServiceAgreement` (`current_status_id = 5`) for that `property_id`
- Load `ServiceAgreementBillCharge` records (from `sys_pm_service_agreements_186_repeat`) for that SLA
- For each charge, load the `InvoiceableItem` name via `item_id`
- Build map: `[ property_code => [ item_id => item_name, ... ] ]`

Properties with no `ServiceAgreementBillCharge` records will have an empty array in this map, indicating rent-only management.

**Step 6 — Categorise each line**
Iterate over every line from Step 3. Apply the categorisation rules in order:

```
if entry_type = 'RENT CURRENT' or 'RENT ARREARS':
    if dr_cr = 'CR' and payable_status IN (1,2,3): → rent_income
    if dr_cr = 'CR' and payable_status = 0:        → non_payable_rent
elif entry_type = 'Commission' and dr_cr = 'DR':   → commission
elif entry_type = 'VAT' and dr_cr = 'DR':          → vat
elif entry_type = 'Advance Payments-From Landlord Acc' and dr_cr = 'DR': → advance_deductions
    attach label from Step 4 map as $line->_advance_label, $line->_advance_ref
elif entry_type = 'Add Back Deductions' and dr_cr = 'CR': → add_backs
elif (entry_type = 'Deductions Before Commission' or entry_type matches a recovery-type):
    look up property's SLA utility item_ids from Step 5 map
    if line's item_id is in that map: → utility_deductions (DR) or utility_collections (CR)
    else: → other_deductions
else: → other_deductions
```

**Step 7 — Sum each category**
```php
$totals = [
    'total_rent_income'         => sum of net_amount from rent_income lines,
    'total_non_payable_rent'    => sum from non_payable_rent (not in gross_payable),
    'total_commission'          => sum from commission lines,
    'total_vat'                 => sum from vat lines,
    'total_advance_deductions'  => sum from advance_deductions lines,
    'total_utility_deductions'  => sum from utility_deductions lines,
    'total_utility_collections' => sum from utility_collections lines,
    'total_add_backs'           => sum from add_backs lines,
    'total_other_deductions'    => sum from other_deductions lines,
    'gross_payable'             => total_rent_income
                                   + total_utility_collections
                                   + total_add_backs
                                   - total_commission
                                   - total_vat
                                   - total_advance_deductions
                                   - total_utility_deductions
                                   - total_other_deductions,
];
```

**Step 8 — Load client bank accounts**
Query `sys_pm_clients_17_repeat` where `parent_id = $clientId`. Eager-load `bank` and `branch` relations for display. A client with no bank accounts will still return a result (empty array) — the view handles this with a warning.

**Step 9 — Return**
```php
return [
    'client'                    => Client model instance,
    'returns'                   => ReturnSummary[] (the matched summary rows),
    'return_codes'              => string[],
    'property_codes'            => string[],
    'lines_by_category'         => [
        'rent_income'           => ReturnClient[] (with payable_status IN 1,2,3),
        'non_payable_rent'      => ReturnClient[] (payable_status=0, shown greyed out),
        'commission'            => ReturnClient[],
        'vat'                   => ReturnClient[],
        'advance_deductions'    => ReturnClient[] (each has _advance_label, _advance_ref),
        'utility_deductions'    => ReturnClient[],
        'utility_collections'   => ReturnClient[],
        'add_backs'             => ReturnClient[],
        'other_deductions'      => ReturnClient[],
    ],
    'totals'                    => $totals (array as above),
    'sla_utilities_by_property' => [ property_code => [ item_id => item_name ] ],
    'bank_accounts'             => ClientBankAccount[],
];
```

#### `buildReconciliationNumber(int $clientId, int $period): string`

1. Load client `reference_number` (e.g. `LAN0025`)
2. Attempt `REC-{period}-{reference_number}` (e.g. `REC-202604-LAN0025`)
3. Check `cb_returns_reconciliations` for an existing row with that `reconciliation_number`
4. If taken, try `REC-202604-LAN0025-2`, then `-3`, etc. (up to 10 attempts, then throw an exception)
5. Return the first available number

---

### Phase 4 — Controller

**File:** `app/Http/Controllers/ReturnsReconciliationController.php`

Injects `ReturnsReconciliationService` via the constructor.

#### `index()`
- Builds `$years = range(currentYear, currentYear - 4)` and `$currentYear`, `$currentMonth`
- Returns view `property.reconciliations.index` with those variables
- The actual data is loaded by the DataTable via `getReconciliationsData()`

#### `create()`
- Returns view `property.reconciliations.create` with no data (the form is entirely AJAX-driven)

#### `preview(Request $request)` — AJAX POST
- Validates: `client_id` required integer, `period` required 6-digit integer
- Calls `$this->service->previewForClientPeriod($request->client_id, $request->period)`
- On success: returns JSON `{ success: true, data: { client, returns, lines_by_category, totals, sla_utilities_by_property, bank_accounts } }`
- On `\RuntimeException` (no approved returns found): returns JSON `{ success: false, message: '...' }` with HTTP 422
- On any other exception: logs it, returns JSON `{ success: false, message: 'An error occurred' }` with HTTP 500

#### `store(Request $request)` — POST
- Validates:
  - `client_id`: required, integer, exists in `sys_pm_clients`
  - `period`: required, integer, exactly 6 digits
  - `notes`: nullable, string, max 2000 characters
  - `return_codes`: required, string (comma-separated, passed from the preview panel hidden field)
  - `totals.*`: required numeric values for each total field (passed as hidden fields from the preview panel)
- **Duplicate guard:** Checks `ReturnsReconciliation::active()->where('client_id', $clientId)->where('period', $period)->exists()`. If true, returns back with error: _"An active reconciliation already exists for this client and period."_ and the existing record's ID so the user can be redirected to it.
- Builds `reconciliation_number` via `buildReconciliationNumber()`
- Creates the `ReturnsReconciliation` record with `reconciliation_status = 0`, `prepared_by = auth()->id()`, `prepared_at = now()`
- Redirects to `reconciliations.show` with success flash message

#### `show(int $id)`
- Finds `ReturnsReconciliation::findOrFail($id)` with `client`, `preparedBy`, `confirmedBy` relations eager-loaded
- Passes `$reconciliation` to view `property.reconciliations.show`
- The line data is loaded separately via AJAX `getLines($id)` when the page loads

#### `confirm(int $id)` — POST
- Finds the reconciliation or 404
- Guard: if `reconciliation_status !== 0` (not Draft), returns back with error: _"Only a Draft reconciliation can be confirmed."_
- Updates: `reconciliation_status = 1`, `confirmed_by = auth()->id()`, `confirmed_at = now()`
- Redirects back to `reconciliations.show` with success flash

#### `void(int $id)` — POST
- Validates: `void_reason` required, string, min 10 characters
- Finds the reconciliation or 404
- Guard: if `reconciliation_status === -1`, returns back with error: _"This reconciliation is already voided."_
- Updates: `reconciliation_status = -1`, `voided_by = auth()->id()`, `voided_at = now()`, `void_reason = $request->void_reason`
- Redirects back to `reconciliations.show` with info flash

#### `getReconciliationsData(Request $request)` — AJAX GET
- Accepts query params: `year` (default current year), `month` (default current month), `status` (optional: `0`, `1`, `-1`)
- Queries `cb_returns_reconciliations` joined with `sys_pm_clients` for `client_name`
- Filters by `YEAR(period / 100)` and `MOD(period, 100)` (or use integer range: `period BETWEEN 202600 AND 202699` for year 2026)
- If `$request->status !== null`: adds `WHERE reconciliation_status = ?`
- Returns JSON array with fields: `id`, `encrypted_id`, `reconciliation_number`, `client_name`, `client_ref`, `period_label`, `gross_payable` (formatted), `reconciliation_status`, `status_label`, `status_badge_class`, `prepared_by_name`, `prepared_at`, `confirmed_by_name`, `confirmed_at`

#### `getLines(int $id)` — AJAX GET
- Finds the reconciliation or 404
- Calls `$this->service->previewForClientPeriod($reconciliation->client_id, $reconciliation->period)`
- Returns the same JSON structure as `preview()` — the show page uses this to render the line tables dynamically

#### `getClientsWithApprovedReturns()` — AJAX GET
- Queries `cb_returns_summary` for distinct `landlord_code` values where `approved_status = 1` AND `paid_status = 0`
- Joins to `sys_pm_clients` on `reference_number = landlord_code` to get `id` and `names`
- Returns JSON array: `[ { id, reference_number, names } ]` — used to populate the client dropdown on the create form

#### `getPeriodsForClient(int $clientId)` — AJAX GET
- Loads the client's `reference_number`
- Queries `cb_returns_summary` for distinct `return_period` values where `landlord_code = reference_number`, `approved_status = 1`, `paid_status = 0`
- Excludes periods that already have an active (`status >= 0`) reconciliation in `cb_returns_reconciliations` for this client
- Returns JSON array of period objects: `[ { period: 202604, label: 'April 2026' } ]`

#### `exportPdf(int $id)`
- Finds the reconciliation (with client eager-loaded)
- Calls `$this->service->previewForClientPeriod(...)` to get all line data
- Loads view `property.reconciliations.pdf` with all data
- Renders via `Pdf::loadView(...)` (Barryvdh DomPDF, same package used in `ClientReturnsController`)
- Returns `$pdf->download("Reconciliation-{$reconciliation->reconciliation_number}.pdf")`

#### `exportExcel(int $id)`
- Same data loading as PDF export
- Uses PhpSpreadsheet (same pattern as existing `downloadExcel()` in `ClientReturnsController`)
- Builds a spreadsheet with the same sections as the PDF (Rent Income, Commission, Advances, etc.)
- Returns `response()->streamDownload(...)` with the XLSX binary

---

### Phase 5 — Views

All views extend `layouts.main`. All use the same Bootstrap/card style and DataTables version as the existing returns module. Font is the same Material Symbols icon set already in use.

---

#### `resources/views/property/reconciliations/index.blade.php`

**Filter bar** (same row layout as `property/returns/list.blade.php`):
- Year dropdown — `range(currentYear, currentYear - 4)`, default current year
- Month dropdown — January through December, default current month
- Status dropdown — All / Draft / Confirmed / Voided (maps to values `''`, `0`, `1`, `-1`)
- Search button with spinner

**DataTable** (client-side data load, same pattern as returns list):
- Columns: `#` | `Reconciliation No.` | `Client` | `Period` | `Net Payable (KES)` | `Status` | `Actions`
- `Net Payable` column: right-aligned, formatted with thousand separators
- `Status` column: renders a Bootstrap badge using `status_badge_class` and `status_label` from the JSON
- `Actions` column: three icon buttons — View (eye icon → `reconciliations.show`), PDF (download icon → `reconciliations.export.pdf`), Excel (table icon → `reconciliations.export.excel`)
- A "New Reconciliation" button in the card header links to `reconciliations.create`

**JavaScript behaviour:**
- On page load, automatically fires a search for the current year/month
- Search button calls `GET /ajax/reconciliations/data?year=&month=&status=` and reloads the DataTable

---

#### `resources/views/property/reconciliations/create.blade.php`

**Layout:** A single card with two logical sections. Section 2 is hidden until the preview AJAX returns successfully.

**Section 1 — Selection form:**
- **Client dropdown** (Select2 / searchable): populated via AJAX `GET /ajax/reconciliations/clients` on page load. Each option shows `{names} ({reference_number})`
- **Period dropdown**: initially empty and disabled. When a client is selected, fires AJAX `GET /ajax/reconciliations/clients/{clientId}/periods` and populates with available periods. Each option shows `{period_label}` (e.g. "April 2026") with `value = {period integer}`.
- If the client has no available periods (all already reconciled or no approved returns), the dropdown shows "No available periods for this client" and the Preview button is disabled.
- **Preview button**: triggers AJAX `POST /ajax/reconciliations/preview` with `{ client_id, period }`. Shows a spinner while loading.

**Section 2 — Preview panel (shown after successful preview):**

*Header strip:*
- Client name + reference number
- Period label (e.g. "April 2026")
- List of bank accounts with bank name, account number, and percentage (e.g. "Equity Bank — 0012345678 — 100%")

*Per-property indicator row:* For each property in the reconciliation, show the property code/name and (if SLA has bill charges) a green badge "Utility Collections Managed".

*Collapsible sections (each uses a Bootstrap accordion or collapse):*

1. **Rent Income** — table with columns: Property | Unit | Tenant | Entry Type | Amount. Footer shows section total.
2. **Non-Payable Rent (Withheld)** — same columns but greyed out, with a column showing `payable_status_reason`. Only shown if there are withheld lines. Footer shows withheld total.
3. **Management Commission & VAT** — table with columns: Property | Description | Amount. Shows one commission line and one VAT line per property.
4. **Advance Deductions** — table with columns: Property | Advance Reference | Category | Amount. Each line shows the `_advance_label` (e.g. "Water Bills") and `_advance_ref`. If no advance deductions exist, this section is hidden.
5. **Utility Collections** — table with columns: Property | Description | Amount. Only shown if utility collections exist.
6. **Utility Deductions** — same layout. Only shown if utility deductions exist.
7. **Other Adjustments** — remaining DR/CR lines not in any other category.

*Totals summary table:*
| | Amount |
|---|---|
| Rent Income | xxx |
| Utility Collections | xxx |
| Add-Back Adjustments | xxx |
| **Total Credits** | **xxx** |
| Management Commission | (xxx) |
| VAT on Commission | (xxx) |
| Advance Deductions | (xxx) |
| Utility Deductions | (xxx) |
| Other Deductions | (xxx) |
| **Net Payable to Client** | **xxx** (highlighted in primary colour) |

*Notes textarea:* Optional, max 2000 characters.

*Save button:* `POST /reconciliations` — submits hidden fields: `client_id`, `period`, `return_codes` (comma-joined), all `total_*` values. On success, redirected to the show page.

---

#### `resources/views/property/reconciliations/show.blade.php`

**Header card** (compact, same style as `unprocessed-details.blade.php` header strip):
- Reconciliation Number | Client Name (ref) | Period | Status badge
- Prepared by (name + date) | Confirmed by (name + date, or "—" if Draft)

**Bank Accounts panel:** Small card listing the client's payout accounts (account name, bank, account number, percentage split). If no bank accounts configured, shows a warning alert.

**Line Sections:** Same accordion layout as the create preview, but data loaded via AJAX `GET /ajax/reconciliations/{id}/lines` on page load. Shows a loading spinner while fetching.

**Totals card:** Same summary table as in the create preview.

**Notes:** If `notes` is not empty, shown in a light-background text box below the totals.

**Action area** (bottom of card, rendered conditionally by `reconciliation_status`):

- **If Draft (`status = 0`):**
  - Primary button: "Confirm Reconciliation" — opens a confirmation modal before submitting `POST /reconciliations/{id}/confirm`
  - Danger button: "Void" — opens a modal with a required textarea for `void_reason`, then submits `POST /reconciliations/{id}/void`

- **If Confirmed (`status = 1`):**
  - Success button: "Generate Payment Voucher" — links to `/payment/vouchers` (the existing `PaymentVouchersController@index` page) with a query string hint `?client_id={client_id}&amount={gross_payable}&ref={reconciliation_number}` (the voucher controller can read these as prefill hints for a future integration)
  - Secondary buttons: "Export PDF" and "Export Excel"

- **If Voided (`status = -1`):**
  - No action buttons
  - Danger alert box showing: "Voided on {voided_at} by {voided_by name} — Reason: {void_reason}"

---

#### `resources/views/property/reconciliations/pdf.blade.php`

Designed for DomPDF A4 portrait output. Uses inline CSS (no external stylesheets — same approach as `property/processed-returns/pdf.blade.php`).

**Structure:**
1. **Letterhead row:** Company name (from `config('app.name')`), company address/contact if available
2. **Document title:** "CLIENT RETURNS RECONCILIATION STATEMENT" — centred, bold
3. **Header table:** two-column layout
   - Left: Client Name, Client Reference, Payout Bank Account(s)
   - Right: Reconciliation No., Period, Date Generated, Status
4. **Line sections:** one table per category (Rent Income, Commission, Advances, etc.). Each table has a shaded header row with the section name and a subtotal row.
5. **Totals table:** credits, debits by type, and bold "Net Payable to Client" row with double underline
6. **Notes section:** if present
7. **Signature footer:** three columns — Prepared By (name + date), Confirmed By (name + date or blank), Authorised Signature (blank line for manual signing)

---

### Phase 6 — Routes

To be added in `routes/web.php` in the auth middleware group, immediately after the `// Processed Returns` block:

```php
// Client Returns Reconciliation
// Specific AJAX routes BEFORE parameterized routes
Route::get('/ajax/reconciliations/data', [App\Http\Controllers\ReturnsReconciliationController::class, 'getReconciliationsData'])->name('ajax.reconciliations.data');
Route::get('/ajax/reconciliations/clients', [App\Http\Controllers\ReturnsReconciliationController::class, 'getClientsWithApprovedReturns'])->name('ajax.reconciliations.clients');
Route::post('/ajax/reconciliations/preview', [App\Http\Controllers\ReturnsReconciliationController::class, 'preview'])->name('ajax.reconciliations.preview');
Route::get('/ajax/reconciliations/clients/{clientId}/periods', [App\Http\Controllers\ReturnsReconciliationController::class, 'getPeriodsForClient'])->name('ajax.reconciliations.client.periods');
Route::get('/ajax/reconciliations/{id}/lines', [App\Http\Controllers\ReturnsReconciliationController::class, 'getLines'])->name('ajax.reconciliations.lines');

// Page routes
Route::get('/reconciliations', [App\Http\Controllers\ReturnsReconciliationController::class, 'index'])->name('reconciliations.index');
Route::get('/reconciliations/create', [App\Http\Controllers\ReturnsReconciliationController::class, 'create'])->name('reconciliations.create');
Route::post('/reconciliations', [App\Http\Controllers\ReturnsReconciliationController::class, 'store'])->name('reconciliations.store');
Route::get('/reconciliations/{id}/export/pdf', [App\Http\Controllers\ReturnsReconciliationController::class, 'exportPdf'])->name('reconciliations.export.pdf');
Route::get('/reconciliations/{id}/export/excel', [App\Http\Controllers\ReturnsReconciliationController::class, 'exportExcel'])->name('reconciliations.export.excel');
Route::post('/reconciliations/{id}/confirm', [App\Http\Controllers\ReturnsReconciliationController::class, 'confirm'])->name('reconciliations.confirm');
Route::post('/reconciliations/{id}/void', [App\Http\Controllers\ReturnsReconciliationController::class, 'void'])->name('reconciliations.void');
// Parameterized show LAST among page routes
Route::get('/reconciliations/{id}', [App\Http\Controllers\ReturnsReconciliationController::class, 'show'])->name('reconciliations.show');
```

**Note on ordering:** The static AJAX routes (`/ajax/reconciliations/clients`, `/ajax/reconciliations/data`) must appear before the parameterized `/ajax/reconciliations/{id}/lines` — same principle as the existing payment routes in this file. Similarly, `/reconciliations/create` must appear before `/reconciliations/{id}`.

---

### Phase 7 — Integration Points

#### 7a. Add "Create Reconciliation" Button to Unprocessed Return Detail Page

**File:** `resources/views/property/returns/unprocessed-details.blade.php`

In the card header (`<div class="card-header">`), add a button alongside the existing "Back" button — but only when `$return->approved_status == 1`:

```blade
@if($return->approved_status == 1)
    <a href="{{ route('reconciliations.create') }}?client_id={{ $return->landlord_code }}&period={{ $return->return_period }}"
       class="btn btn-success btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">receipt_long</span>
        Create Reconciliation
    </a>
@endif
```

When the create form receives `client_id` (as a `reference_number` here, so the JS must handle the lookup) and `period` query params, it should pre-select the client and period in the form automatically via JavaScript on page load.

#### 7b. "Generate Payment Voucher" Link on Confirmed Reconciliation

On the confirmed reconciliation show page, the "Generate Payment Voucher" button links to `/payment/vouchers` (the existing `PaymentVouchersController@index` page). For now this is just a navigation link. In a future iteration the voucher creation form can be enhanced to accept `?reconciliation_id=X` as a pre-fill param.

#### 7c. Future — Back-Reference Voucher Number on Reconciliation (Low Priority)

When a payment voucher is created for a landlord and the `vendor_id` / `vendor_name` matches the client, optionally add a `reconciliation_id` column to `cb_payment_vouchers_headers_new` and populate it. This closes the traceability loop from return → reconciliation → voucher. Not in scope for the initial build.

---

## Verification Checklist

1. **Migration:** `php artisan migrate` runs without errors. `cb_returns_reconciliations` table exists with all columns and indexes.
2. **Preview — utility client:** For a client whose SLA includes `ServiceAgreementBillCharge` entries, the preview endpoint returns utility lines in the correct `utility_collections` / `utility_deductions` categories, and the `sla_utilities_by_property` map is populated.
3. **Preview — rent-only client:** For a client with no `ServiceAgreementBillCharge` entries, no utility sections appear, no errors thrown.
4. **Advance labelling:** Advance deduction lines correctly show the `expense_category` display label and advance reference number, not just the generic `entry_type` text.
5. **Non-payable rent:** Lines with `payable_status = 0` appear in the withheld section and are excluded from `gross_payable`.
6. **Duplicate guard:** Attempting to create a second reconciliation for the same `client_id + period` returns a validation error and points to the existing record.
7. **Confirm guard:** Calling `confirm()` on an already-Confirmed or Voided reconciliation returns an error.
8. **Void guard:** Calling `void()` without `void_reason`, or on an already-Voided reconciliation, returns an error.
9. **DataTable filtering:** Year/month/status filters on the index page correctly narrow the results.
10. **PDF export:** All sections render, totals are correct, signature footer is present.
11. **"Create Reconciliation" button:** Appears on the unprocessed return detail page when `approved_status = 1`, and is absent when `approved_status = 0`.
12. **Period dropdown exclusion:** Periods that already have an active reconciliation do not appear in the period dropdown on the create form.

---

## Out of Scope

- Reconciliation does **not** set `paid_status = 1` on any `cb_returns_summary` row — that is done by the payment voucher/processing flow
- No multi-level approval workflow on the reconciliation itself — a single Confirm action by an authorised staff member is sufficient
- Reconciliation does **not** generate the payment voucher itself
- No changes to the returns generation logic (the 7-step `processGenerate()` flow is untouched)
- No changes to the existing approval workflow (`ReturnsApprovalController`)


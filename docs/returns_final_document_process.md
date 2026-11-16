# Client Returns Generation Process

> **Last Updated:** April 2026  
> **Controller:** `ClientReturnsController.php`  
> **Endpoint:** `POST /returns/generate`

---

## Overview

The returns generation process calculates landlord payments based on approved tenant invoices, deducting commissions, advances, recoveries, and other charges. Returns can be generated for **all properties** or a **single property**.

---

## Input Parameters

| Parameter       | Format    | Required | Description                                  |
| --------------- | --------- | -------- | -------------------------------------------- |
| `returns_month` | `YYYY-MM` | Yes      | Target period for returns (e.g., `2025-01`)  |
| `property_id`   | Integer   | No       | Optional - generate for single property only |

---

## Key Database Tables

### Core Returns Tables

| Table                | Purpose                                                  |
| -------------------- | -------------------------------------------------------- |
| `cb_returns_clients` | Individual return line items (rent, deductions)          |
| `cb_returns_summary` | Aggregated return per property (MAINRETURN/MASTERRETURN) |

### Source Data Tables

| Table                     | Purpose                                              |
| ------------------------- | ---------------------------------------------------- |
| `ta_tenant_invoices`      | Approved tenant invoices (`approve_status = 1`)      |
| `ta_tenant_invoice_lines` | Invoice line details with rent amounts               |
| `cb_landlord_account`     | Advance payments to landlords (entry_type `0009`)    |
| `cb_money_recoveries`     | Pending recoveries (`type` = Recoveries/Collections) |

### Configuration Tables

| Table                          | Purpose                                     |
| ------------------------------ | ------------------------------------------- |
| `cb_returns_merge_batches`     | Defines property batches for MASTERRETURN   |
| `cb_returns_merge_batch_items` | Properties belonging to each batch          |
| `sys_pm_properties`            | Property details including commission rates |
| `sys_pm_clients`               | Landlord information                        |

### Approval Tables

| Table                 | Purpose                       |
| --------------------- | ----------------------------- |
| `cb_approval_items`   | Tracks return approval status |
| `cb_item_approvers`   | Assigned approvers per level  |
| `cb_approval_actions` | Approval action history       |

---

## Entry Type Codes

| Code   | Name                    | DR/CR | Description               |
| ------ | ----------------------- | ----- | ------------------------- |
| `0005` | RETURNS                 | CR    | Landlord payment credit   |
| `0006` | FINAL PAYMENT           | DR    | Final payment debit       |
| `0008` | Opening/Closing Balance | -     | Balance entries           |
| `0009` | ADVANCE                 | DR    | Advance payment deduction |
| `0014` | CHEQUE CHARGES          | DR    | Cheque processing fee     |

---

## Sequential Process Flow

### Pre-Validation

1. **Validate Input** - Check `returns_month` format and optional `property_id` exists
2. **Count Approved Invoices** - Query `ta_tenant_invoices` where `approve_status = 1`
3. **Check Existing Returns** - Ensure no returns exist for this period (prevents duplicates)

### Step 1: Insert Rent Lines

**Trait:** `ReturnsRentLinesTrait`  
**Method:** `insertRentLines()`

- Source: Approved invoices from `ta_tenant_invoices` + `ta_tenant_invoice_lines`
- Creates one `cb_returns_clients` row per unit with rent
- Calculates: `gross_amount`, `commission_amount`, `vat_amount`, `net_amount`
- Sets `dr_cr = 'CR'` (credit to landlord)
- Generates unique `return_code` per property

### Step 2: Update Pay Thresholds

**Trait:** `ReturnsPayThresholdTrait`  
**Method:** `updatePayThresholds()`

- Calculates **85% payment threshold** per unit
- Compares `amount_paid` vs `amount_due` from invoice
- Sets `payable_status`:
    - `1, 2, 3` = Payable (≥85% paid)
    - `0` = Withheld (below threshold, `net_amount` set to 0)
- Also calculates `pay_percentage` for reporting

### Step 3: Insert Deductions

**Trait:** `ReturnsDeductionsTrait`

#### 3a. Advance Payments

**Method:** `insertAdvancePaymentLines()`

- Source: `cb_landlord_account` where `entry_type = '0009'` and `recovered = false`
- Creates DR line in `cb_returns_clients`
- Entry type: `ADVANCE RECOVERY`

#### 3b. Recoveries

**Method:** `insertRecoveryLines()`

- Source: `cb_money_recoveries` where `type = 'Recoveries'` and `status = 'Pending'`
- Creates DR line per recovery
- Entry type: `RECOVERY`

#### 3c. Collections

**Method:** `insertCollectionLines()`

- Source: `cb_money_recoveries` where `type = 'Collections'` and `status = 'Pending'`
- Creates DR line per collection
- Entry type: `COLLECTION`

### Step 4: Create MAINRETURN Summaries

**Method:** `insertMainReturnSummariesLegacy()`

- Groups all lines in `cb_returns_clients` by `return_code`
- Inserts one row per property into `cb_returns_summary`
- Calculates:
    - `total_credit` = Sum of CR amounts
    - `total_debit` = Sum of DR amounts + commissions + VAT
    - `net_amount` = `total_credit - total_debit`
    - `commission_rate` = Derived from totals
    - `payable_amount` / `non_payable_amount` = Based on threshold results
- Sets `return_type = 'MAINRETURN'`
- Sets `approved_status = 0` (pending approval)

### Step 5: Generate MASTERRETURNs

**Trait:** `ReturnsMasterReturnTrait`  
**Method:** `generateMasterReturns()`

> **Note:** Skipped when processing a single property

- Reads batch configuration from `cb_returns_merge_batches` + `cb_returns_merge_batch_items`
- For each active batch:
    1. Aggregates all child property MAINRETURNs
    2. Creates parent MASTERRETURN summary
    3. Copies all line items with reference to master
    4. Marks child MAINRETURNs as `is_merged = true`
- MASTERRETURN uses the batch's designated landlord (`client_id`) for payment

### Step 6: Mark Items as Recovered

**Trait:** `ReturnsDeductionsTrait`

#### 6a. Landlord Account Entries

**Method:** `markLandlordAccountRecovered()`

- Updates `cb_landlord_account` where `entry_type = '0009'`
- Sets `recovered = true`, `recovered_date`, `recovered_return_period`

#### 6b. Money Recoveries

**Method:** `linkMoneyRecoveriesToReturns()`

- Updates `cb_money_recoveries`
- Sets `status = 'Recovered'`, `return_id`, `return_period`

### Step 7: Create Approval Workflow

**Method:** `createApprovalWorkflowItems()`

- Creates `cb_approval_items` record for each return summary
- Assigns Level 1 approver (Property's `field_officer_id`)
- Creates initial "Initiated" action in `cb_approval_actions`
- Workflow has 4 approval levels total

### Final Commit

- All database changes committed in single transaction
- If any step fails, entire transaction rolls back

---

## Return Types

| Type           | Description              | When Created                      |
| -------------- | ------------------------ | --------------------------------- |
| `MAINRETURN`   | Per-property return      | Always (Step 4)                   |
| `MASTERRETURN` | Multi-property aggregate | When batch config exists (Step 5) |
| `MINIRETURN`   | Legacy/special cases     | Not in standard flow              |

---

## Commission Calculation

Per rent line:

```
commission_amount = gross_amount × (commission_rate / 100)
vat_amount = commission_amount × 0.16  (if VAT applies)
net_amount = gross_amount - commission_amount - vat_amount
```

Commission rate comes from `sys_pm_properties.commission_percentage` (typically 10%).

---

## 85% Payment Threshold Logic

For each unit's rent line:

```
pay_percentage = (amount_paid / amount_due) × 100

if pay_percentage >= 85%:
    payable_status = 1, 2, or 3  (based on tier)
    net_amount = calculated amount
else:
    payable_status = 0
    net_amount = 0  (withheld from landlord)
```

---

## API Response

**Success:**

```json
{
    "success": true,
    "message": "Client returns generated successfully: 15 MAINRETURN(s), 3 MASTERRETURN(s) (120 rent + 5 advance + 3 recovery + 2 collection lines)",
    "count": 120,
    "properties_count": 15,
    "master_count": 3,
    "property_id": null
}
```

**Single Property Mode:**

```json
{
    "success": true,
    "message": "Client returns generated successfully for property ID 123: 1 MAINRETURN(s) (8 rent + 1 advance + 0 recovery + 0 collection lines)",
    "count": 8,
    "properties_count": 1,
    "master_count": 0,
    "property_id": 123
}
```

---

## Trait Organization

| Trait                         | Responsibility                                     |
| ----------------------------- | -------------------------------------------------- |
| `ReturnsRentLinesTrait`       | Insert rent CR lines from invoices                 |
| `ReturnsPayThresholdTrait`    | 85% payment threshold calculation                  |
| `ReturnsDeductionsTrait`      | Advances, recoveries, collections                  |
| `ReturnsSummaryTrait`         | MAINRETURN summaries, existence checks             |
| `ReturnsMasterReturnTrait`    | MASTERRETURN aggregation                           |

---

## Landlord Account Balances

The system maintains running balances for each landlord per property in `cb_landlord_account`.

### Entry Type 0008 - Balance Entries

| Entry Description   | Purpose                                      |
| ------------------- | -------------------------------------------- |
| Opening Balance B/F | Balance brought forward from previous period |
| Closing Balance C/F | Balance to be carried forward to next period |

### Balance Computation

The trait that used to compute and insert these balance entries (`ReturnsLandlordAccountTrait`) had no callers and was
removed on 2026-11-16. Returns generation does not create Opening Balance B/F or Closing Balance C/F rows. It only reads
the ledger's debits, and the return statement leaves the 0008 and 00014 balance rows out of its landlord-account deductions.

---

## Key Business Rules

1. **No Regeneration** - Returns cannot be regenerated for a period that already has returns
2. **Approved Invoices Only** - Only processes invoices with `approve_status = 1`
3. **85% Threshold** - Rent below 85% paid is withheld from landlord
4. **Commission First** - Commission deducted before landlord receives payment
5. **Recovery Priority** - Advances and recoveries deducted from current returns
6. **Batch Merging** - Properties in a batch get combined MASTERRETURN per configuration
7. **Single Property** - When `property_id` provided, skips MASTERRETURN generation
8. **Atomic Transaction** - All-or-nothing; any failure rolls back entire process
9. **Balance Computation** - Opening/Closing balances must be computed before/after returns

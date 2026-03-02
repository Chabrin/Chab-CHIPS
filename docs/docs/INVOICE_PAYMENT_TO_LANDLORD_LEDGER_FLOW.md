# INVOICE PAYMENT TO LANDLORD LEDGER FLOW DOCUMENTATION

## Overview

This document provides a high-level overview of the automated flow that occurs when a tenant makes a payment on an invoice, triggering updates to the return records and landlord ledger entries.

---

## 🔄 COMPLETE FLOW DIAGRAM

```
┌─────────────────────────────────────────────────────────────────────┐
│                   TENANT PAYMENT TRIGGER FLOW                        │
└─────────────────────────────────────────────────────────────────────┘

STAGE 1: Tenant Makes Payment
│
├─> Table: sys_pm_debtors_invoices
│   └─> UPDATE invoice
│       - current_balance = total_amount - paid_amount
│       - current_status_id = 8 (Partially Paid) or 9 (Cleared)
│
▼

STAGE 2: Return Update Triggered
│
├─> Service: ClientReturnService::updateReturnOnInvoicePayment($invoice_id)
│
├─> Table: sys_pm_clients_returns
│   └─> SELECT return WHERE invoice_id = $invoice_id
│
├─> Calculate Threshold:
│   ├─> paid_so_far = total_amount - current_balance
│   └─> threshold_percentage = (paid_so_far / total_amount) × 100
│
├─> Determine Payable Status:
│   │
│   ├─> IF invoice.current_status_id = 9 (Cleared)
│   │   └─> payable_status_id = 1 (Payable)
│   │       threshold_percentage = 100
│   │
│   ├─> ELSE IF invoice.current_status_id IN (37,35,36,34) [Recoveries]
│   │   └─> payable_status_id = 0 (Unpayable)
│   │       threshold_percentage = 100
│   │       CREATE recovery record
│   │
│   ├─> ELSE IF item.advance_payable = 'Yes' AND threshold_percentage ≥ 85
│   │   └─> payable_status_id = 1 (Payable)
│   │
│   ├─> ELSE IF previous_month_threshold ≥ 85 (Historical Check)
│   │   └─> payable_status_id = 3 (Past Passed Threshold)
│   │
│   └─> ELSE
│       └─> payable_status_id = 0 (Unpayable)
│
├─> Table: sys_pm_clients_returns
│   └─> UPDATE return
│       SET threshold_percentage = calculated_value,
│           payable_status_id = determined_status
│       WHERE invoice_id = $invoice_id
│
▼

STAGE 3: Landlord Ledger Update
│
├─> Service: ClientReturnService::updateLandlordLedger($payable_status_id, $invoice_id)
│
├─> IF payable_status_id = 1 (Payable)
│   │
│   └─> Table: sys_pm_landlord_accounts_transaction
│       └─> UPDATE landlord_ledger
│           SET payable_status = 1
│           WHERE invoice_id = $invoice_id
│               AND amount_type = 'Credit'
│
└─> Result: Landlord ledger credit entry now INCLUDED in balance calculation
```

---

## 📊 TABLES & FIELDS INVOLVED

### 1. **sys_pm_debtors_invoices** - Invoice Table

**Updated Fields:**

- `current_balance` - Remaining unpaid amount
- `current_status_id` - Invoice payment status
  - `8` = Partially Paid
  - `9` = Cleared (Fully Paid)
  - `37,35,36,34` = Recovery statuses

### 2. **sys_pm_clients_returns** - Returns Table

**Read Fields:**

- `id` - Return ID
- `invoice_id` - Linked invoice
- `trans_amount` - Total return amount
- `payable_amount` - Amount payable to client

**Updated Fields:**

- `threshold_percentage` - Payment threshold (0-100%)
- `payable_status_id` - Payability status
  - `0` = Unpayable
  - `1` = Payable
  - `2` = No Past Invoice
  - `3` = Past Passed Threshold
  - `17` = Processed (Already Paid)

### 3. **sys_pm_landlord_accounts_transaction** - Landlord Ledger Table

**Key Fields:**

- `invoice_id` - Links to invoice
- `client_id` - Landlord ID
- `amount` - Transaction amount (positive for credits)
- `amount_type` - 'Credit' or 'Debit'
- `transaction_type_id` - `13` = Client Return
- `current_status_id` - Posting status
  - `1` = Pending
  - `6` = Posted

**Updated Field:**

- `payable_status` - Payability flag
  - `0` = Unpayable (EXCLUDED from balance)
  - `1` = Payable (INCLUDED in balance)
  - `17` = Processed (Already paid out)

---

## 🎯 DETAILED STAGE BREAKDOWN

### STAGE 1: Invoice Payment Recorded

**Trigger:** Tenant makes payment via Payment Service

**What Happens:**

```sql
-- Invoice balance updated
UPDATE sys_pm_debtors_invoices
SET current_balance = total_amount - total_payments_received,
    current_status_id = CASE
        WHEN current_balance = 0 THEN 9      -- Cleared
        WHEN current_balance < total_amount THEN 8  -- Partially Paid
        ELSE 1                                -- Pending
    END
WHERE id = :invoice_id
```

**Result:** Invoice reflects new payment status

---

### STAGE 2: Return Threshold & Payability Update

**Trigger:** Automatically called after invoice payment allocation

**Service Method:** `ClientReturnService::updateReturnOnInvoicePayment($invoice_id)`

**Code Location:** `libraries/ipms/Domain/Service/ClientReturnService.php:560-720`

**Step-by-Step Process:**

#### Step 2.1: Retrieve Invoice & Return

```php
$invoice = $invoice_dao->getById($invoice_id);
$return = $client_return_dao->getByInvoice($invoice_id);
```

#### Step 2.2: Calculate Payment Threshold

```php
$invoice_amount = $invoice->total_amount;  // e.g., 50,000
$current_balance = $invoice->current_balance;  // e.g., 7,000
$paid_so_far = $invoice_amount - $current_balance;  // 43,000

$threshold = round(($paid_so_far * 100) / $invoice_amount, 2);
// Result: (43,000 / 50,000) × 100 = 86%
```

#### Step 2.3: Determine Payable Status

**Decision Tree:**

```
1. Is invoice FULLY CLEARED (status_id = 9)?
   YES → payable_status_id = 1, threshold = 100%
   NO  → Continue to 2

2. Is invoice a RECOVERY type (status IN 37,35,36,34)?
   YES → payable_status_id = 0, threshold = 100, CREATE recovery
   NO  → Continue to 3

3. Is item marked as "advance_payable = Yes"?
   YES → Continue to 3a
   NO  → payable_status_id = 0

   3a. Is current threshold ≥ 85%?
       YES → payable_status_id = 1
       NO  → Continue to 3b

   3b. Did previous month pass 85% threshold?
       YES → payable_status_id = 3
       NO  → payable_status_id = 0
```

**Configuration Values:**

```php
// From ToolsService::getGlobalSettings()['return_statuses']
'allowed_thresh' => 85              // Minimum threshold percentage
'unpayable' => 0                    // Not eligible for payment
'payable' => 1                      // Eligible for payment
'no_past_invoice_status_id' => 2   // First invoice (no history)
'past_passed_thresh_status_id' => 3 // Previous month passed threshold
'payment_processed' => 17           // Already paid to client
```

#### Step 2.4: Update Return Record

```sql
UPDATE sys_pm_clients_returns
SET threshold_percentage = :calculated_threshold,
    payable_status_id = :determined_status
WHERE invoice_id = :invoice_id
```

**Example Update:**

```
threshold_percentage: 86.00
payable_status_id: 1 (Changed from 0 to 1 - NOW PAYABLE!)
```

---

### STAGE 3: Landlord Ledger Sync

**Trigger:** Immediately after return update

**Service Method:** `ClientReturnService::updateLandlordLedger($payable_status_id, $invoice_id)`

**Code Location:** `libraries/ipms/Domain/Service/ClientReturnService.php:967-977`

**Logic:**

```php
private static function updateLandlordLedger($payable_status_id, $invoice_id)
{
    // Only updates if return is NOW PAYABLE
    if($payable_status_id == 1) {
        UPDATE sys_pm_landlord_accounts_transaction
        SET payable_status = 1
        WHERE invoice_id = :invoice_id
          AND amount_type = 'Credit'
    }
}
```

**SQL Execution:**

```sql
UPDATE sys_pm_landlord_accounts_transaction
SET payable_status = 1
WHERE invoice_id = 123
  AND amount_type = 'Credit'
```

**Result:** The credit entry in landlord ledger is now flagged as `payable_status = 1`

---

## 💡 KEY CONCEPTS

### 1. **Initial Landlord Ledger Entry Creation**

When a return is **first generated** (on return approval):

**Service:** `LandlordTransService::createFromReturn($return_id)`

**What Gets Created:**

```sql
INSERT INTO sys_pm_landlord_accounts_transaction
(
    date_time,
    trans_date_time,
    client_id,
    property_id,
    invoice_id,
    origin_id,
    transaction_type_id,  -- 13 = Client Return
    narration,
    amount_type,          -- 'Credit'
    amount,               -- e.g., 50,000
    payable_status,       -- 0 (Unpayable by default)
    current_status_id,    -- 1 (Pending)
    lease_id,
    tenant_id,
    unit_id,
    sla_id
)
VALUES (...)
```

**Initial State:**

- `payable_status = 0` (EXCLUDED from balance calculation)
- `amount_type = 'Credit'`
- `amount = 50,000` (return amount)

---

### 2. **Balance Calculation Logic**

**How Landlord Balance is Calculated:**

```sql
SELECT SUM(amount) as balance
FROM sys_pm_landlord_accounts_transaction
WHERE client_id = :client_id
  AND (
    (amount_type = 'Debit')  -- All debits count
    OR (payable_status = 1 AND amount_type = 'Credit')  -- ✅ Payable credits
    OR (payable_status = 17 AND amount_type = 'Credit') -- ✅ Processed credits
  )
```

**Key Point:** Credits with `payable_status = 0` are **EXCLUDED** from balance!

---

### 3. **Status Transition Example**

**Scenario: Tenant pays 86% of invoice**

```
┌─────────────────────────────────────────────────────────────┐
│ BEFORE Payment (Invoice Balance = 50,000, Paid = 0)         │
├─────────────────────────────────────────────────────────────┤
│ sys_pm_clients_returns:                                     │
│   - threshold_percentage: 0                                 │
│   - payable_status_id: 0 (Unpayable)                        │
│                                                             │
│ sys_pm_landlord_accounts_transaction:                       │
│   - amount: 50,000                                          │
│   - amount_type: Credit                                     │
│   - payable_status: 0 (EXCLUDED from balance)              │
│                                                             │
│ Client Balance Calculation:                                 │
│   Credits with payable_status=1: 0                          │
│   Client Balance: 0                                         │
└─────────────────────────────────────────────────────────────┘

                         ↓ TENANT PAYS 43,000 ↓

┌─────────────────────────────────────────────────────────────┐
│ AFTER Payment (Invoice Balance = 7,000, Paid = 43,000)      │
├─────────────────────────────────────────────────────────────┤
│ sys_pm_debtors_invoices:                                    │
│   - current_balance: 7,000                                  │
│   - current_status_id: 8 (Partially Paid)                   │
│                                                             │
│ ✅ updateReturnOnInvoicePayment() TRIGGERED                 │
│                                                             │
│ sys_pm_clients_returns:                                     │
│   - threshold_percentage: 86.00 ✅ UPDATED                   │
│   - payable_status_id: 1 (Payable) ✅ CHANGED FROM 0        │
│                                                             │
│ ✅ updateLandlordLedger() TRIGGERED                          │
│                                                             │
│ sys_pm_landlord_accounts_transaction:                       │
│   - amount: 50,000                                          │
│   - amount_type: Credit                                     │
│   - payable_status: 1 ✅ UPDATED (NOW INCLUDED IN BALANCE)  │
│                                                             │
│ Client Balance Calculation:                                 │
│   Credits with payable_status=1: 50,000 ✅ NOW INCLUDED!    │
│   Client Balance: 50,000                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔁 RETROACTIVE PAYABILITY

**Important:** Past returns can become payable when tenant catches up!

**Example Timeline:**

```
JANUARY 2026:
├─ Invoice: 50,000 (Tenant pays 30% = 15,000)
├─ Return: payable_status_id = 0 (Unpayable)
└─ Landlord Ledger: payable_status = 0 (Excluded)

FEBRUARY 2026:
├─ Tenant pays additional 30,000 (Total: 45,000 / 50,000 = 90%)
├─ updateReturnOnInvoicePayment() recalculates January's return
├─ Return: payable_status_id = 1 ✅ (NOW PAYABLE!)
├─ updateLandlordLedger() updates January's ledger entry
└─ Landlord Ledger: payable_status = 1 ✅ (NOW INCLUDED!)

MARCH 2026:
└─ Payment distribution created → January's return NOW ELIGIBLE! ✅
```

---

## 📋 PAYABLE STATUS VALUES REFERENCE

| Value | Status Name           | Meaning                             |
| ----- | --------------------- | ----------------------------------- |
| 0     | Unpayable             | Not eligible for payment (excluded) |
| 1     | Payable               | Eligible for payment (included)     |
| 2     | No Past Invoice       | First invoice (no payment history)  |
| 3     | Past Passed Threshold | Previous month met 85% threshold    |
| 17    | Processed             | Already paid to landlord            |

---

## ⚙️ CONFIGURATION SETTINGS

**Location:** `ToolsService::getGlobalSettings()`

```php
'return_statuses' => array(
    'allowed_thresh' => 85,              // Minimum payment threshold
    'unpayable' => 0,
    'payable' => 1,
    'no_past_invoice_status_id' => 2,
    'past_passed_thresh_status_id' => 3,
    'fully_paid_status_id' => 1,
    'payment_processed' => 17
)
```

---

## 🔍 VERIFICATION QUERIES

### Check Return & Landlord Ledger Sync

```sql
-- Verify return status matches landlord ledger
SELECT
    r.id as return_id,
    r.invoice_id,
    r.threshold_percentage,
    r.payable_status_id as return_payable_status,
    l.payable_status as ledger_payable_status,
    CASE
        WHEN r.payable_status_id = l.payable_status THEN '✅ SYNCED'
        ELSE '❌ OUT OF SYNC'
    END as sync_status
FROM sys_pm_clients_returns r
JOIN sys_pm_landlord_accounts_transaction l
    ON r.invoice_id = l.invoice_id
WHERE r.client_id = :client_id
  AND l.amount_type = 'Credit'
  AND l.transaction_type_id = 13
ORDER BY r.return_date DESC
```

### Find Unpayable Returns

```sql
-- Returns that are unpayable (excluded from balance)
SELECT
    r.id,
    r.ref_number,
    r.return_date,
    r.trans_amount,
    r.threshold_percentage,
    r.payable_status_id,
    i.current_balance,
    i.total_amount,
    ROUND((i.total_amount - i.current_balance) / i.total_amount * 100, 2) as actual_paid_percent
FROM sys_pm_clients_returns r
JOIN sys_pm_debtors_invoices i ON r.invoice_id = i.id
WHERE r.payable_status_id = 0
  AND r.current_status_id = 1
ORDER BY r.return_date DESC
```

### Check Client Available Balance

```sql
-- Calculate client's payable balance
SELECT
    client_id,
    SUM(CASE WHEN amount_type = 'Credit' AND payable_status = 1 THEN amount ELSE 0 END) as payable_credits,
    SUM(CASE WHEN amount_type = 'Credit' AND payable_status = 0 THEN amount ELSE 0 END) as unpayable_credits,
    SUM(CASE WHEN amount_type = 'Debit' THEN amount ELSE 0 END) as debits,
    SUM(CASE
        WHEN amount_type = 'Debit' THEN amount
        WHEN amount_type = 'Credit' AND payable_status IN (1, 17) THEN amount
        ELSE 0
    END) as available_balance
FROM sys_pm_landlord_accounts_transaction
WHERE client_id = :client_id
GROUP BY client_id
```

---

## 🚨 IMPORTANT NOTES

1. **Automatic Trigger:** The flow is **AUTOMATIC** - triggered every time a tenant payment is allocated to an invoice

2. **Real-Time Updates:** Threshold and payability are recalculated in **real-time** on every payment

3. **Single Ledger Entry:** There is only **ONE** landlord ledger entry per return - the `payable_status` field just gets updated from 0→1

4. **Balance Impact:** Changing `payable_status` from 0 to 1 immediately makes the return **visible** in balance calculations

5. **Historical Correction:** Past returns automatically become payable when tenants catch up on payments

6. **No Manual Intervention:** System handles all updates automatically - no manual flagging needed

---

## 📞 RELATED SERVICES

- **ClientReturnService** - Return record management
- **LandlordTransService** - Landlord ledger transactions
- **TenantPaymentService** - Tenant payment allocation
- **InvoiceDao** - Invoice data operations
- **ClientReturnDao** - Return data operations
- **LandlordTransDao** - Landlord ledger data operations

---

## 📚 RELATED DOCUMENTATION

- [RETURNS_PROCESS_FLOW_DOCUMENTATION.md](RETURNS_PROCESS_FLOW_DOCUMENTATION.md) - Complete returns process
- [PaymentsInvoicesEndtoEnd.md](PaymentsInvoicesEndtoEnd.md) - Payment processing flow
- [INVOICE_SYSTEM_DOCUMENTATION.md](INVOICE_SYSTEM_DOCUMENTATION.md) - Invoice system details

---

**Document Version:** 1.0  
**Last Updated:** February 2, 2026  
**Maintained By:** System Documentation Team

# THRESHOLD LOGIC IMPLEMENTATION SUMMARY

## Changes Made

### 1. Migration Created

**File:** `database/migrations/2026_02_02_170000_add_abstract_threshold_to_clients_returns.php`

- Added `abstract_threshold` column to `sys_pm_clients_returns` table
- Type: `DECIMAL(10,2)`, Default: `0.00`
- Positioned after `threshold_percentage` column
- Tracks estimated threshold based on historical payment patterns

**Run with:**

```bash
php artisan migrate
```

---

### 2. ClientReturnsController Updated

**File:** `app/Http/Controllers/ClientReturnsController.php`

**Method:** `processGenerate()`

**New Logic Added After Return Generation:**

1. **Abstract Threshold Update (Bulk Query)**
    - Runs immediately after returns are generated
    - For items marked as `advance_payable = 'Yes'`
    - Uses previous month threshold or historical average
    - Updates both `abstract_threshold` and `payable_status_id`
    - Also syncs `threshold_percentage` from invoice if already paid

2. **Logic Flow:**

    ```
    IF previous month threshold = 100%:
        abstract_threshold = 100%
        payable_status_id = 1 (Payable)

    ELSE IF no previous month (first invoice):
        abstract_threshold = historical average or 0
        payable_status_id = 2 (No Past Invoice - Payable)

    ELSE:
        abstract_threshold = historical average
        IF historical average >= 85%:
            payable_status_id = 1 (Payable)
        ELSE:
            payable_status_id = 0 (Unpayable)
    ```

3. **Landlord Ledger Update**
    - Updates `payable_status` in landlord ledger
    - Only for returns with `payable_status_id IN (1, 2)`
    - Makes credits INCLUDED in landlord balance

**All within single transaction:**

- Delete existing returns/ledgers
- Generate new returns
- Create landlord ledger entries
- Update abstract thresholds
- Update landlord ledger payable_status
- Create batch records

---

### 3. ReturnThresholdTrait Updated

**File:** `app/Traits/ReturnThresholdTrait.php`

**Method:** `updateReturnRecord()`

**Changes:**

- Now updates **BOTH** `threshold_percentage` and `abstract_threshold`
- When tenant makes payment, real data overrides historical estimate
- Both columns become equal after payment received

**Before:**

```php
'threshold_percentage' => $threshold,
'payable_status_id' => $payableStatusId
```

**After:**

```php
'threshold_percentage' => $threshold,        // REAL threshold
'abstract_threshold' => $threshold,          // Update abstract too
'payable_status_id' => $payableStatusId
```

---

## Dual Threshold System Explained

### Column 1: `threshold_percentage` (REAL THRESHOLD)

- Reflects **actual tenant payments** on the invoice
- Updated when tenant makes payment
- Calculated from: `(invoice.total_amount - invoice.current_balance) / invoice.total_amount × 100`
- **Purpose:** Final payability determination based on reality

### Column 2: `abstract_threshold` (ESTIMATED THRESHOLD)

- Reflects **predicted payment** based on historical lease performance
- Updated at return generation time
- Calculated from: Historical payment patterns for this lease + item
- **Purpose:** Initial payability estimation before tenant pays

### Why Two Columns?

**Problem:** At return generation (e.g., February 1st), the invoice for February hasn't been paid yet.

- `threshold_percentage = 0` (no payments yet)
- But we need to estimate if landlord should expect payment this month

**Solution:** Use `abstract_threshold` based on historical data

- If tenant historically pays 90% every month → `abstract_threshold = 90`
- Landlord knows to expect payment even before tenant actually pays
- When tenant pays, `threshold_percentage` updates with real data

---

## Integration Points

### 1. Return Generation Flow (ClientReturnsController)

```
processGenerate() →
    Generate Returns →
    Create Landlord Ledgers →
    UPDATE Abstract Thresholds (NEW) →
    Update Landlord Ledger Payable Status (NEW) →
    Create Batches
```

### 2. Invoice Payment Flow (PaymentAllocationService)

```
PaymentAllocationService::processPayments() →
    Allocate Payment to Invoice →
    executeThresholdUpdate() (ReturnThresholdTrait) →
        updateReturnRecord() → Updates BOTH thresholds (NEW) →
        updateLandlordLedger() → If payable_status_id = 1
```

---

## Example Scenarios

### Scenario 1: Perfect Payment History

**Timeline:**

- January 2026: Tenant paid 100% (threshold = 100%)
- February 2026: Return generated, invoice not yet paid

**On Generation:**

```sql
abstract_threshold = 100.00    -- Previous month was perfect
payable_status_id = 1          -- Payable
threshold_percentage = 0.00    -- Invoice not paid yet
```

**When Tenant Pays 86% (February 15):**

```sql
threshold_percentage = 86.00   -- Actual payment
abstract_threshold = 86.00     -- Overwritten with real data
payable_status_id = 1          -- Still payable (>= 85%)
```

### Scenario 2: Poor Payment History

**Timeline:**

- January 2026: Tenant paid 40% (threshold = 40%)
- Historical average: 75% over last 6 months
- February 2026: Return generated

**On Generation:**

```sql
abstract_threshold = 75.00     -- Historical average
payable_status_id = 0          -- Unpayable (< 85%)
threshold_percentage = 0.00    -- Invoice not paid yet
```

**When Tenant Pays 90% (February 20):**

```sql
threshold_percentage = 90.00   -- Actual payment exceeds threshold!
abstract_threshold = 90.00     -- Updated with real data
payable_status_id = 1          -- NOW PAYABLE!
landlord ledger payable_status = 1  -- Updated
```

### Scenario 3: First Invoice Ever

**Timeline:**

- New lease, first month
- No previous months, no historical data
- February 2026: First return generated

**On Generation:**

```sql
abstract_threshold = 0.00      -- No history
payable_status_id = 2          -- No Past Invoice (Payable by default)
threshold_percentage = 0.00    -- Invoice not paid yet
```

---

## Testing Steps

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Test Return Generation

```bash
# Generate returns for a property
# Check logs to verify abstract threshold update runs
```

Expected logs:

```
✓ Created landlord ledger entries via INSERT...SELECT
✓ Updated abstract thresholds for advance_payable items
✓ Updated landlord ledger payable_status
```

### 3. Test Invoice Payment

```bash
# Allocate a tenant payment
# Check that both thresholds update
```

Expected logs:

```
✓ Threshold updated
  threshold: 86.5
  payable_status_id: 1
```

### 4. Verify Database

```sql
-- Check dual thresholds exist
SELECT
    ref_number,
    threshold_percentage,
    abstract_threshold,
    payable_status_id
FROM sys_pm_clients_returns
WHERE return_date LIKE '2026-02%'
LIMIT 10;
```

---

## Key Benefits

1. **Risk Management** - Landlords only get paid when tenants demonstrate consistent payment behavior
2. **Predictive Payability** - Know if return will be payable before tenant pays
3. **Real-time Updates** - Both thresholds update when actual payment received
4. **Gaming Prevention** - Can't alternate good/bad months (uses historical average)
5. **Transaction Safety** - All updates within single transaction

---

## Notes

- **advance_payable = 'Yes'** items use abstract threshold logic
- **advance_payable = 'No'** items use simple >= 85% threshold
- Abstract threshold is **overwritten** by real threshold when tenant pays
- First invoice defaults to payable (status 2) until payment history established
- All updates happen within existing transactions (no separate transactions)

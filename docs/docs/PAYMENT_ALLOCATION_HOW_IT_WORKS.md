# PAYMENT ALLOCATION SERVICE - HOW IT WORKS

## YES! It loops through EVERYTHING automatically like the guide! ✅

### Main Backend Service Method

```php
// This processes ALL unallocated payments system-wide
$service = new PaymentAllocationService();
$results = $service->processAllPayments();
```

### What Happens Step-by-Step:

#### STEP 1: Get ALL Unallocated Payments

```sql
SELECT * FROM sys_pm_tenants_payments
WHERE current_status_id IN (11, 12)  -- Unallocated or Partially Allocated
ORDER BY payment_date
```

- Status 11 = Unallocated (has money available)
- Status 12 = Partially Allocated (some money left)
- Gets EVERY payment in the entire system that has unallocated balance

#### STEP 2: Loop Through EACH Payment

```php
foreach($payments as $payment) {
    // Process this payment
}
```

#### STEP 3: For EACH Payment, Get ALL Invoices

##### 3a. Check if tenant moved (old lease invoices FIRST)

```sql
-- Check movement
SELECT * FROM sys_pm_movement_requests
WHERE new_lease_id = {payment->lease_id}

-- If moved, get OLD lease invoices
SELECT * FROM sys_pm_debtors_invoices
WHERE lease_id = {old_lease_id}
  AND current_balance > 0
ORDER BY date_due, payment_priority
```

##### 3b. Get current lease invoices

```sql
SELECT * FROM sys_pm_debtors_invoices
WHERE lease_id = {payment->lease_id}
  AND current_balance > 0
ORDER BY date_due, payment_priority
```

##### 3c. Get linked lease invoices (multi-unit tenants)

```sql
-- Get linked leases
SELECT * FROM sys_pm_lease_agreements_27_repeat
WHERE linked_lease_id = {payment->lease_id}

-- For each linked lease, get its invoices
SELECT * FROM sys_pm_debtors_invoices
WHERE lease_id = {linked_lease_id}
  AND current_balance > 0
ORDER BY date_due, payment_priority
```

#### STEP 4: Loop Through EACH Invoice

```php
foreach($invoices as $invoice) {
    // Allocate payment to this invoice
    allocateInvoice($invoice, $payment);

    // Check if payment exhausted
    if(payment has no balance left) {
        break; // Stop, payment fully used
    }
}
```

#### STEP 5: For EACH Allocation

1. Calculate payment remaining balance
2. Calculate invoice remaining balance
3. Determine scenario:
    - **Exact Match**: payment = invoice → Allocate all
    - **Partial Payment**: payment < invoice → Use all payment
    - **Excess Payment**: payment > invoice → Clear invoice, continue
4. Create allocation record in `sys_pm_tenants_payments_invoices`
5. Update invoice `current_balance`
6. Update payment `current_status_id` (12 or 13)
7. **Update return threshold and payable status** ⭐

### Complete Example Flow:

```
System has 3 unallocated payments:
├─ Payment #5001: KES 80,000 (Tenant A, Lease 123)
├─ Payment #5002: KES 50,000 (Tenant B, Lease 456)
└─ Payment #5003: KES 30,000 (Tenant C, Lease 789)

processAllPayments() is called:

┌─────────────────────────────────────────┐
│ PAYMENT #5001 (KES 80,000)             │
└─────────────────────────────────────────┘
   ├─ Find invoices for Lease 123
   │  ├─ Invoice #1001: Jan Rent (50,000)
   │  ├─ Invoice #1002: Jan Water (5,000)
   │  └─ Invoice #1003: Feb Rent (50,000)
   │
   ├─ Allocate to Invoice #1001: 50,000
   │  ├─ Payment balance: 80,000 - 50,000 = 30,000
   │  ├─ Invoice cleared (status 9)
   │  └─ Return threshold: 100% → Payable ✅
   │
   ├─ Allocate to Invoice #1002: 5,000
   │  ├─ Payment balance: 30,000 - 5,000 = 25,000
   │  ├─ Invoice cleared (status 9)
   │  └─ Return threshold: 100% → Payable ✅
   │
   └─ Allocate to Invoice #1003: 25,000
      ├─ Payment balance: 25,000 - 25,000 = 0
      ├─ Invoice partial (status 8)
      ├─ Payment fully allocated (status 13)
      └─ Return threshold: 50% → Check prev month
         └─ Prev month 100% → Payable ✅

┌─────────────────────────────────────────┐
│ PAYMENT #5002 (KES 50,000)             │
└─────────────────────────────────────────┘
   ├─ Find invoices for Lease 456
   │  └─ Invoice #2001: Jan Rent (50,000)
   │
   └─ Allocate to Invoice #2001: 50,000
      ├─ Payment balance: 50,000 - 50,000 = 0
      ├─ Invoice cleared (status 9)
      ├─ Payment fully allocated (status 13)
      └─ Return threshold: 100% → Payable ✅

┌─────────────────────────────────────────┐
│ PAYMENT #5003 (KES 30,000)             │
└─────────────────────────────────────────┘
   └─ (Same process...)

RESULT:
✅ 3 payments processed
✅ 5 allocations created
✅ 4 invoices cleared
✅ 1 invoice partially paid
✅ 5 return thresholds updated
```

### API Endpoints:

#### Backend Service (Process ALL - No Parameters)

```bash
# This is what you use for cron jobs / scheduled tasks
POST /payments/process/all

# Response:
{
  "success": true,
  "message": "All unallocated payments processed successfully",
  "results": {
    "payments_processed": 3,
    "allocations_created": 5,
    "invoices_cleared": 4,
    "invoices_partial": 1,
    "returns_updated": 5,
    "errors": []
  }
}
```

#### With Filters (Optional - for specific processing)

```bash
# Process only for specific client
POST /payments/process
{
  "client_id": 14
}

# Process only for specific unit
POST /payments/process
{
  "unit_id": 789
}

# Process only for specific lease
POST /payments/process/lease
{
  "lease_id": 123
}
```

### Key Points:

1. ✅ **Loops through ALL payments** - Every unallocated payment in the system
2. ✅ **Loops through ALL invoices** - Every unsettled invoice for each lease
3. ✅ **Handles tenant movement** - Clears old lease invoices first
4. ✅ **Handles multi-unit tenants** - Processes linked leases
5. ✅ **Updates return thresholds** - After EVERY allocation
6. ✅ **Exactly like the guide** - Same logic, same flow, same results

### How to Use as Backend Service:

#### Option 1: Laravel Scheduler (Recommended)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run every hour
    $schedule->call(function () {
        $service = new PaymentAllocationService();
        $service->processAllPayments();
    })->hourly();
}
```

#### Option 2: Cron Job

```bash
# Add to crontab
0 * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

#### Option 3: Direct API Call

```bash
# Call from external system
curl -X POST https://your-domain.com/payments/process/all \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Summary:

**YES! It does EXACTLY what the guide does:**

- ✅ Gets ALL unallocated payments (no limit)
- ✅ Loops through EACH payment
- ✅ For each payment, gets ALL unsettled invoices
- ✅ Loops through EACH invoice
- ✅ Allocates until payment exhausted
- ✅ Updates balances and thresholds
- ✅ Works as a complete backend service

**No manual intervention needed - it's fully automatic! 🚀**

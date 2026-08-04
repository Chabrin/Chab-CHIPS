# June Returns Zero Totals - Root Cause & Fix

## Problem
June 2026 returns were generated with all zeros in summary totals (total_credit, commission_amount, net_amount), even though 19 rent line items existed.

## Root Cause
**Bug in `app/Traits/ReturnsPayThresholdTrait.php`** (line 113-121)

The LATERAL join was querying for invoices **only within the specific previous month** (May for June returns):
```sql
pi2.date_due >= ?::date AND pi2.date_due < ?::date  -- looked only for May invoices
ORDER BY pi2.id DESC LIMIT 1
```

**The Problem:**
- Property 324B had NO May invoices (only April, March, etc.)
- The query returned NULL (no May invoice found)
- Fallback logic treated "no May invoice but has old invoices" as NOT PAYABLE
- All 19 tenants were marked `payable_status = 0`
- Therefore all `net_amount` = 0
- Summary aggregation showed all zeros (correct calculation of zero values)

## Solution
Changed the LATERAL join to get **the most recent invoice BEFORE the return period**, regardless of month:

```sql
-- OLD (broken): looked only in May (2026-05-01 to 2026-06-01)
AND pi2.date_due >= ?::date AND pi2.date_due < ?::date

-- NEW (fixed): looks for most recent invoice before June 1
AND pi2.date_due < ?::date
ORDER BY pi2.date_due DESC, pi2.id DESC LIMIT 1
```

**Changes:**
1. `ReturnsPayThresholdTrait.php` line 116-120: Updated LATERAL join query
2. Removed `$prevMonthStart` from bindings array (line 36-46)
3. Reordered by `date_due DESC` to get truly most recent invoice

## Impact
Now the threshold check correctly uses April invoices (the most recent available) to determine if June rent is payable. Tenants are marked:
- `payable_status = 1` if previous month paid ≥ 85%
- `payable_status = 0` if previous month paid < 85%
- `payable_status = 1` if they have NO payment history (new tenants)

## Test Case
Tenant FATUMA TAIB AHMED (ID 22761) for Property 324B:
- **April Invoice**: 15,500 KSh
- **April Balance (Unpaid)**: 15,000 KSh  
- **Paid %**: 3.23%
- **Result**: NOT PAYABLE (correctly, since < 85%)
- **June Net Amount**: 0 (withheld)

## Regeneration
To apply this fix:
1. ✓ Updated `ReturnsPayThresholdTrait.php`
2. ✓ Deleted June 2026 returns (30 line items, 9 summaries cleared)
3. Regenerate via `/returns/generate` → June 2026 → (leave property blank for all)

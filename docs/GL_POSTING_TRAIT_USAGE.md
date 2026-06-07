# GL Posting Trait Usage Guide

The `GlPostingTrait` provides a reusable, standardized way to post double-entry journal entries to the GL system.

## Quick Start

### 1. Add the Trait to Your Class

```php
use App\Traits\GlPostingTrait;

class YourController extends Controller
{
    use GlPostingTrait;
    
    // Your methods...
}
```

## Usage Methods

### Method 1: Post by Transaction Type (Recommended - Simplest)

This method automatically looks up the transaction type and uses its configured debit/credit accounts.

```php
// Example: Post a client return
$this->postGlByTransactionType('2009', [
    'amount'        => 50000.00,
    'date'          => now()->toDateString(),
    'reference'     => 'RETURN-123',
    'narration'     => 'Client return payment - John Doe',
    'trans_details' => 'return_id:123:code:RETURN-123',
]);
```

**Required Transaction Type Setup:**
- The transaction type with `item_code = '2009'` must exist in `sys_set_transactions_types`
- It must have `debit_account_id` and `credit_account_id` configured

### Method 2: Post with Full Configuration

For more control, use the main `postGlEntry()` method:

```php
$transactionType = TransactionType::where('item_code', '2009')->first();

$this->postGlEntry([
    'type'             => 'client_return',      // Transaction type key
    'amount'           => 50000.00,             // Transaction amount
    'date'             => now()->toDateString(),// Journal date (optional, defaults to today)
    'reference'        => 'RETURN-123',         // External reference
    'narration'        => 'Client return payment - John Doe',
    'trans_type_id'    => $transactionType->id,
    'trans_details'    => 'return_id:123',      // Internal details
    'transaction_type' => $transactionType,     // Full model (for account resolution)
    'entered_by'       => Auth::id(),           // Optional, defaults to Auth::id()
]);
```

### Method 3: Explicit GL Lines (Advanced)

For complex transactions or custom journal entries:

```php
$this->postGlEntry([
    'date'          => now()->toDateString(),
    'reference'     => 'CUSTOM-001',
    'narration'     => 'Custom journal entry',
    'trans_type_id' => $transactionType->id,
    'lines'         => [
        [
            'account_id' => 1022,  // GL account ID
            'type'       => 'Debit',
            'amount'     => 50000.00,
            'narration'  => 'Custom debit narration',
        ],
        [
            'account_id' => 1019,
            'type'       => 'Credit',
            'amount'     => 50000.00,
            'narration'  => 'Custom credit narration',
        ],
    ],
]);
```

## Available Transaction Types

Add mappings in the `postGlByTransactionType()` method as you implement more:

| Item Code | Type | Description | DR Account | CR Account |
|-----------|------|-------------|------------|------------|
| 2009 | client_return | Client Returns | 1022 | 1019 |
| *Any other* | generic | Any simple DR/CR | From TransactionType | From TransactionType |

**Note:** The trait includes a generic fallback that works for any transaction type with `debit_account_id` and `credit_account_id` configured. You only need custom implementations for complex transactions (e.g., with commission splits, multiple lines, etc.).

## Adding New Transaction Types

### Step 1: Add to the Trait Dispatcher

Edit `app/Traits/GlPostingTrait.php`:

```php
protected function resolveGlLines(array $config, GlAccountSetup $setup): array
{
    return match ($config['type'] ?? '') {
        'invoice_payment' => $this->glLinesInvoicePayment($config, $setup),
        'client_return'   => $this->glLinesClientReturn($config, $setup),
        'your_new_type'   => $this->glLinesYourNewType($config, $setup),  // Add here
        default => [],
    };
}
```

### Step 2: Implement the Line Builder Method

```php
protected function glLinesYourNewType(array $config, GlAccountSetup $setup): array
{
    $amount = (float) ($config['amount'] ?? 0);
    if ($amount <= 0) {
        return [];
    }

    // Get accounts from transaction_type or explicit config
    $debitAccId  = $config['debit_account_id'] ?? null;
    $creditAccId = $config['credit_account_id'] ?? null;

    if ((!$debitAccId || !$creditAccId) && isset($config['transaction_type'])) {
        $transType = $config['transaction_type'];
        $debitAccId  = $debitAccId ?? $transType->debit_account_id;
        $creditAccId = $creditAccId ?? $transType->credit_account_id;
    }

    if (!$debitAccId || !$creditAccId) {
        Log::warning('GlPostingTrait: your_new_type missing account IDs');
        return [];
    }

    $narration = $config['narration'] ?? 'Your new type description';

    return [
        [
            'account_id' => $debitAccId,
            'type'       => 'Debit',
            'amount'     => $amount,
            'narration'  => $narration,
        ],
        [
            'account_id' => $creditAccId,
            'type'       => 'Credit',
            'amount'     => $amount,
            'narration'  => $narration,
        ],
    ];
}
```

### Step 3: Add Item Code Mapping

In `postGlByTransactionType()`, add your mapping:

```php
$type = match ($itemCode) {
    '2009' => 'client_return',
    '1234' => 'your_new_type',  // Add here
    default => 'generic',
};
```

## Real-World Example: Client Return Approval

From `app/Services/ApprovalService.php`:

```php
protected function postReturnToLandlordAccountAndGl(ReturnSummary $return): void
{
    try {
        DB::transaction(function () use ($return) {
            // 1. Post to landlord account
            LandlordAccount::create([
                'year'              => $return->year,
                'month'             => $return->month,
                'landlord_code'     => $return->landlord_code,
                'entry_description' => 'RETURNS',
                'credit_amount'     => $return->net_balance,
                // ... other fields
            ]);

            // 2. Post to GL using the trait - ONE SIMPLE LINE!
            $this->postGlByTransactionType('2009', [
                'amount'        => $return->net_balance,
                'date'          => now()->toDateString(),
                'reference'     => $return->return_code,
                'narration'     => "RETURNS - {$landlordName}",
                'trans_details' => "return_id:{$return->id}",
            ]);

            Log::info("Return posted to GL", ['return_code' => $return->return_code]);
        });
    } catch (Exception $e) {
        Log::error("Failed to post return: " . $e->getMessage());
    }
}
```

## Configuration Requirements

### 1. GL Account Setup

The `sys_gl_account_setup` table must have one active record with fiscal year and account mappings.

### 2. Transaction Types

Each transaction type in `sys_set_transactions_types` should have:
- `item_code` (unique identifier, e.g., '2009')
- `debit_account_id` (FK to `sys_gl_chart_accounts_master`)
- `credit_account_id` (FK to `sys_gl_chart_accounts_master`)

### 3. Chart of Accounts

Accounts must exist in `sys_gl_chart_accounts_master` with unique `account_code` values.

## Benefits

✅ **Consistency**: All GL postings use the same logic and validation
✅ **Maintainability**: Update GL logic in one place
✅ **Reusability**: Use anywhere in the application
✅ **Safety**: Automatic balance validation (warns if DR ≠ CR)
✅ **Auditability**: Automatic logging and transaction wrapping
✅ **Flexibility**: Support for simple, complex, and custom entries

## Troubleshooting

### GL Entry Not Posted

Check logs for warnings:
- `GL account setup` - Ensure `sys_gl_account_setup` has an active record
- `Transaction type not found` - Verify the item_code exists
- `Missing account IDs` - Check debit/credit account configuration
- `Unbalanced journal` - Debits don't equal credits (review custom lines)

### Where Are My Entries?

- **Headers**: `sys_gl_transactions` table
- **Lines**: `sys_gl_transactions_91_repeat` table

Query example:
```sql
SELECT 
    h.id, h.journal_date, h.reference, h.trans_type_id,
    l.account_id, l.entry_type, l.amount, l.narration
FROM sys_gl_transactions h
JOIN sys_gl_transactions_91_repeat l ON l.parent_id = h.id
WHERE h.reference = 'RETURN-123'
ORDER BY l.id;
```

# Payslip System — Investigation & Fix Summary

---

## Background

The payslip system generates PDF payslips for employees. The complaint was that payslips were showing incomplete information — only a basic salary line and a lump-sum total for deductions, with no breakdown of what was actually being deducted or earned.

---

## What We Found (Root Causes)

### 1. Empty Deduction Breakdown on All Payslips

When payroll is processed, the system tries to look up master `PayrollElement` records in the database (for NSSF, SHA, Housing Levy, PAYE) before storing them in the payslip. If those master records weren't present or weren't marked as approved at the time payroll was run, the breakdown was saved as an empty array. The totals (NSSF amount, SHA amount, etc.) were stored correctly in separate columns on the record — they just weren't being used for display.

**Effect:** Every historical payslip shows no individual deduction lines, only a lump-sum "TOTAL DEDUCTIONS" figure.

### 2. Allowances Completely Ignored in Payroll Calculation

The gross pay calculation was hardcoded to basic salary only. There was a comment in the code itself saying *"for now just basic."* This meant any allowances set on an employee's contract (house allowance, transport, etc.) were never factored into gross pay, PAYE, or the payslip display.

**Effect:** Employees with contractual allowances had incorrect PAYE calculated and those allowances did not appear on payslips.

### 3. Akiba Sacco Loan, Akiba Share Contribution, Social Welfare Not Displaying

These three deductions have dedicated columns on the payroll summary record. However, neither the PDF template nor the preview template was reading those columns — so even when values existed in the database, nothing appeared on the payslip.

**Additional finding:** No code in the system currently writes to these columns during payroll processing. If values exist in records they were entered directly into the database. This will need a follow-up to wire up the automatic calculation.

### 4. Position and Department Showing "N/A"

The payslip was trying to resolve the position and department from the payroll summary record's own `position_id` / `department_id` columns. If those were null (common when a contract or staff record didn't have them fully set), it showed N/A with no fallback — even though the position and department are correctly set on the staff record itself.

### 5. Bank Account Details Missing Entirely

The payslip had no section for bank name, account name, or account number. The data exists on the staff record but was never included in the template.

### 6. No PAYE Tax Breakdown

There was no section showing how PAYE was calculated — employees couldn't see their taxable earnings, the tax chargeable before relief, or the personal relief amount applied.

---

## What Was Fixed

### PayslipsController.php

- **Historical payslip fallback:** When `payslip_elements` is empty on an existing payslip record, the system now reconstructs the deduction breakdown from the individual stored columns (`nssf`, `nhif`, `housing_levy`, `tax_payable`, `helb_loans`, `akiba_sacco_loan`, `akiba_share_contribution`, `social_welfare`). All historical payslips now show the correct breakdown without re-running payroll.
- The payslip loader now eager-loads the staff member's bank, position, and department so that data is available in the template.

### PayrollController.php (affects future payroll runs only)

- The system now reads each employee's contract elements (allowances) before calculating deductions. Allowances are added to gross pay and appear as line items under Earnings on the payslip.
- SHA (2.75%) and Housing Levy (1.5%) are now calculated on full gross pay (basic + allowances), which is correct per Kenya regulations. NSSF remains on basic salary only (pensionable pay).
- PAYE taxable income now correctly includes allowances — employees with allowances will pay the appropriate tax going forward.
- Payslip element data is now stored with full details (name, type, display order) inside the JSON, removing the dependency on master PayrollElement records being present in the database.

### payslip-pdf.blade.php + payslip-preview.blade.php (both templates updated)

| Item | Fix Applied |
|---|---|
| **Position / Department N/A** | Falls back to the staff record's own position/department if the payroll summary's values are null |
| **Bank Details** | New rows added showing Bank, Account Number, and Account Name |
| **Akiba Sacco Loan** | Reads from `akiba_sacco_loan` column and shows as a deduction line when non-zero |
| **Akiba Share Contribution** | Reads from `akiba_share_contribution` column and shows as a deduction line when non-zero |
| **Social Welfare** | Reads from `social_welfare` column and shows as a deduction line when non-zero |
| **PAYE Tax Breakdown** | New section added showing: Taxable Earnings, Tax Chargeable (before relief), Personal Relief (KSh 2,400), and PAYE Payable |

---

## Outstanding Item — Akiba / Social Welfare Calculation

The display of Akiba Sacco Loan, Akiba Share Contribution, and Social Welfare is now in place on the payslip. However, during payroll processing these values are not being calculated and saved automatically.

**Action required:** Confirm where these deduction amounts are recorded per employee (is there a separate screen or table for Akiba and Social Welfare deductions?) so they can be hooked into the payroll run automatically, the same way HELB loans and prorated deductions are currently handled.

---

## Files Changed

| File | What Changed |
|---|---|
| `app/Http/Controllers/PayslipsController.php` | Fallback display for historical payslips; eager-load bank/position/department; Akiba/Social Welfare in fallback map |
| `app/Http/Controllers/PayrollController.php` | Contract allowances included in gross pay; inline element storage; correct SHA/Housing Levy base |
| `resources/views/payroll/partials/payslip-pdf.blade.php` | Bank details, position/dept fallback, Akiba columns, PAYE tax breakdown section |
| `resources/views/payroll/partials/payslip-preview.blade.php` | Same as PDF template |

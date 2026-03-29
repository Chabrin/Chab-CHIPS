-- PM MODULE (other_views_mod.sql)
CHBLOCKS -> sys_pm_properties + sys_pm_service_agreements
CHLRDS -> sys_pm_clients (landlords)
CHBUNITS -> sys_pm_units
CHTENS -> sys_pm_clients (tenants) + sys_pm_lease_agreements
\_mig_zones -> sys_set_zones
\_mig_zone_areas -> sys_set_zone_areas
CHCHRS -> sys_set_invoiceable_items
CHTNTRS -> sys_pm_debtors_invoices
CHTPAYS -> sys_pm_tenants_payments_allocated
CHBCTD -> sys_pm_tenants_payments_unallocated
CHTACC -> sys_pm_tenants_accounts_trans
CHRCS -> cb_receipts
CHRHMS -> cb_returns_summary
CHRTXS -> cb_returns_clients
CHOFFICERS -> josya_users
CHLACC -> cb_landlord_account
CHRACT -> cb_money_recoveries
CHPVHH -> cb_payment_vouchers_headers (new)
CHPVH -> cb_payment_vouchers_headers (old)
CHPVDD -> cb_payment_vouchers_lines (new)
CHPVD -> cb_payment_vouchers_lines (old)
CHCDC -> cb_landlord_charge_pairs

-- HR MODULE (peter_hr_mods_final.sql)
CHDEPT -> sys_set_departments
CHDESN -> sys_set_staff_positions
CHBANK -> sys_set_banks
CHBBRA -> sys_set_banks_branches
CHSTFF -> sys_staff
CHSNOK -> sys_staff_next_of_kin
CHPREDS -> sys_hr_payroll_elements
CHPTABS -> um_tax_bands + sys_hr_payroll_calendar
CHPRDS -> sys_hr_payroll_periods
CHPYSL -> sys_hr_payroll_summary + sys_hr_payslip_elements + sys_hr_p9a + sys_hr_payroll_deductions_register + rpt_statutory_nssf + rpt_statutory_shif + rpt_statutory_housing_levy + sys_hr_employee_contracts (derived)
CHLOAN -> sys_helb_deductions + sys_hr_employee_amortized_deductions
CHP9A -> sys_hr_p9a
CHSCNTS -> sys_hr_employee_contracts

-- PENDING_1 (no view yet — to be created)
CHBCTH -> Batch Headers (payment channel transaction batches)
CHLRH -> Landlord-Property Links
CHRMES -> Returns Scheduler
CHREST -> Bank Statement Recon Entries
CHRDED -> Deduction Config
CHCHQ -> Cheques

-- PENDING_1 (empty / negligible — skipped)
CHLAP -> empty
CHRHMT -> empty
CHRTXT -> empty
CHPAYMENTS -> 4 rows (negligible)

-- ============================================================
-- UN_MAPPED (in CHABRINS, not yet in any view/migration)
-- ============================================================

-- >> LANDLORD ACCOUNTS & LOANS (⚠ review for migration)
CHLNTR -> Landlord Loan/Advance Repayment Schedule — 5,593 rows
Per-period amortised loan rows per landlord (LNAMT, INTRATE, REPDATE, INTRESTAMT, BALANC, REPAYNO). KEY missing table.
CHLCAC -> Landlord Account Period Close Summary — 58,797 rows
Monthly closing Dr/Cr/Balance per landlord (SUMDR, SUMCR, SUMBL, CLSTAT).
CHLSTM -> Landlord Statement Entries — 49,238 rows
Dr/Cr statement lines per landlord (ENTRYDR, ENTRYCR, ENTRYREF). Possibly legacy of CHLACC.

-- >> ACCOUNTING / GENERAL LEDGER
CHACCE -> Main Accounting Ledger Entries — 16,777 rows
Double-entry Dr/Cr by account, period, batch, property, tenant. Core GL.
CHFACTV -> Chart of Accounts Master — 854 rows
Account number, name, type, balance type, posting mode, group.
CHJVH -> Journal Voucher Headers — 179,007 rows
JV batch control: JVNUM, date, period, totals, posted/deleted status.
CHJVD -> Journal Voucher Lines — 888,791 rows
Per-account Dr/Cr lines under each JV (CHJVH).
CHACINV -> Account Invoice Entries — 6,557 rows
Invoices posted to landlord accounts (INVCNO, INVCAMTDR/CR, LORDID).
CHGLAC -> GL Account Ledger — 1,118 rows
GL-level Dr/Cr entries (similar to CHACCE but GL-account focused).
CHTPJV -> Tenant Payment Auto-JV — 54 rows
Auto-generated JV lines from tenant payment posting.
CHIACCT -> Income Account Config — 1 row
Single-row mapping of income types to system GL codes.

-- >> CASHBOOK & BANK RECONCILIATION
CHCBDE -> Cashbook Detail Entries — 779,124 rows
Per-entry bank vs cash amounts; reconciliation status and remarks.
CHCBMN -> Cashbook Main Entries — 166 rows
Cashbook header/summary (posted, deleted flags).
CHACCTS -> Bank Account Master — 3 rows
Company bank accounts (accno, bank, branch, balance, GL code).
CHBEFTS -> EFT Payment Records — 1 row
Outgoing EFT: code, date, bank, amount, checker/approver chain.

-- >> TENANT PAYMENTS, DEPOSITS & ARREARS
CHSUBS -> Recurring Subscription / Direct Debit Payments — 774,116 rows
Payment entries with INVONUM, PAYFOR, PAYMNTH, verify/post status. Very significant.
CHDPS -> Security Deposit Records — 36,307 rows
Per-tenant deposit type, amount, payment mode, receipt no, deposit no.
CHDPAY -> Tenant Deposit Payment Tracker — 127,157 rows
Outstanding deposit entries per tenant/unit.
CHIPAYS -> Receipt Inspection / Verification Checks — 12,874 rows
Marks receipts as checked/actioned before posting.
CHPOS -> POS / Walk-in Receipts — 17,826 rows
Quick payment receipt entries from front desk.
CHUNPD -> Unpaid Arrears Register — 41,776 rows
Snapshot of outstanding amounts per tenant/unit.
CHIBALS -> Opening Migration Balances — 332 rows
Imported opening balances for tenants at migration.
CHCCRST -> EFT / Online Payment Status — 1,828 rows
Tracks success/failure of electronic receipt transactions.

-- >> TENANT ACCOUNT EXTRAS & HISTORY
CHTTAB -> Tenant Transaction Report Snapshot — 3,095,597 rows
Denormalised reporting table: period, officer, block, unit, tenancy status, amounts.
CHRACC -> Returns Rent Actual vs Payable Summary — 451,927 rows
Per-period per-unit: rent payable, paid, deposits, balances.
CHRINC -> Rent Increment Log — 137,886 rows
Records each rent increase (old/new amount, date) per unit.
CHRVAT -> Returns VAT Lines — 3,326 rows
VAT computed per return period per unit (rate, base, credit).
CHRCVS -> Returns Cross-Verification Snapshot — 3,756 rows
Cross-check of tenant invoice vs payment for a returns period.
CHTENH -> Tenant Notes / History — 66,172 rows
Free-text history entries per tenant (date, description, by whom).
CHCNAM -> Tenant Name Change Log — 4,222 rows
Records old-name to new-name changes per unit.
CHTRX -> Tenant Unit Transfer Log — 10,742 rows
Moves tenant between units, carries balance (BALATT).
CHTNHF -> Tenant Housing Fund Rate Bands — 8,607 rows
Category/band rate table (min, max, rate) by period.

-- >> LANDLORD PAYMENT BATCHES
CHPBCTH -> Landlord Payment Batch Headers — 183,272 rows
Landlord remittance batches (date, amount, posted/verified status, source).
CHPBCTG -> Landlord Payment Batch Lines — 857 rows
Detail lines per landlord batch (payee, amount, cheque, bank).
CHBTCH -> Tenant Receipt Posting Batch Headers — 7,645 rows
Batch control for posting collected receipts (zone, amount, status).

-- >> CHEQUES & PETTY CASH
CHCHD -> Cheque Register Detail — 32,967 rows
Per-cheque: account, voucher link, payee, EFT code, verified/checked status.
CHPCVH -> Petty Cash Voucher Headers — 2,511 rows
CHPCVD -> Petty Cash Voucher Lines — 2,487 rows
CHPCENT -> Petty Cash Entries — 2,545 rows
Individual petty cash transactions posted to bank/GL.

-- >> COMMISSION
CHHCOM -> Commission Rate History per Block/Zone — 10,041 rows
Stores the commission % applied per block per period.
CHCOMH -> Commission Adjustment Log — 9,458 rows
Adjustment records (from/to rate, by whom, date) per block/month.
CHCOMA -> Commission per Invoice/Period — 259 rows
Expected vs actual vs paid commission per invoice.
CHRRUN -> Returns Processing Run Log — 110 rows
Records each returns generation run (month, year).

-- >> VENDORS & EXPENSES
CHVENDS -> Vendor / Supplier Master — 1,787 rows
Vendor name, type, address, bank account, current balance.
CHEXPS -> Property Expense Entries — 10 rows
Expenses paid to/by landlord per unit.

-- >> SYSTEM SETUP / CONFIG
CHOFALL -> Officer-Property Allocation — 5,363 rows
Links officer to property (OFFID, BLOCKID, ALLTYPE, ALLDATE).
CHTAXES -> Tax / VAT Rates — 1 row
CHTCHRG -> Tenant Charge Type to GL Account mapping — 7 rows
CHTYPES -> Transaction Type Codes — 9 rows
CHUOMS -> Units of Measurement — 2 rows
CHPSEQ -> Payment Posting Sequence Rules — 10 rows
Defines how receipts auto-allocate across accounts.
CHPSTQ -> Posting Category Codes — 7 rows
CHRCODE -> Returns Reference Codes — 8 rows
CHLOC -> Office Locations — 7 rows
CHSECTS -> HR Department Sections — 32 rows
CHEXS -> Allocation / Exchange Settings — 6 rows

-- >> HR EXTRAS
CHPEED -> Per-Employee Extra Deduction per Period — 153,660 rows
Additional one-off/recurring deductions beyond payroll elements.
CHGRDS -> Salary Grades — 2 rows
CHEMPT -> Employment Types — 3 rows
CHSTFFE -> Staff Employment Change History — 74 rows
CHPYRP -> Payroll Report Config Entries — 6 rows

-- >> SYSTEM / SECURITY / LOGGING
USERLOG -> System Activity Audit Log — 754,564 rows (user, IP, module, timestamp)
SCUSERSN -> System Users (new app) — 21 rows
SCUSERSS -> System Users (shared/legacy) — 2 rows
CHSGRP -> Security Groups — 15 rows
CHSGRPA -> Security Group Menu Access — 206 rows
MNURAN -> Menu Access Ranges — 121 rows
SMCINF -> Company / System Config — 1 row
CHNXD -> Auto-Number Sequences (main) — 1 row
CHNXDE -> Auto-Number Sequences (E-series) — 1 row
CHNXDI -> Auto-Number Sequences (I-series) — 1 row
CHNXDL -> Auto-Number Sequences (L-series) — 1 row
CHRPID -> Returns Process ID Log — 14 rows
USERLOG -> System Activity Audit Log — 754,564 rows

-- >> JUNK / EMPTY / ARTIFACTS (skip)
Sheet2 -> Excel import artifact (117 rows)
CHFACTS -> Old chart of accounts, replaced by CHFACTV (13 rows)
CHBINF -> Block info notes — empty
CHCAPI -> Capital Advance Payments — empty (unused)
CHEAPI -> EFT Advance Payments — empty (unused)
CHEDITS -> Receipt Edit Log — empty
CHICH -> Internal Check records — empty
CHJVDP -> JV Lines staging duplicate — empty
CHBTDD -> Receipt Batch Lines (legacy) — 3 rows
CHBTDR -> Receipt Batch Headers variant (legacy) — 15 rows
CHTN -> Tenant quick-name ref — empty
CHVACT -> Vacated tenants — empty
CHRCSD -> Receipt Banking/Deposit records — empty

-- ============================================================
-- ⚠ LANDLORD RECOVERIES / ADVANCES / LOANS — KEY GAPS
-- ============================================================
-- CHLNTR — 5,593 rows — Landlord Loan Amortisation Schedule
-- LORDID, LNAMT, INTRATE, REPDATE, AMOUNT (per period), INTRESTAMT,
-- TOTALAMT, BALANC, REPAYNO, TRANTYPE (advance vs loan)
-- Full amortised repayment schedule per landlord loan/advance.
-- Feeds CHLACC (landlord account debits for loan recovery).
-- → Proposed PG table: cb_landlord_loans
--
-- CHRACT (already mapped → cb_money_recoveries) handles charge recoveries.
-- CHLNTR handles structured loan/advance repayment specifically.
--
-- CHLCAC — 58,797 rows — Landlord Period Close Summaries
-- Monthly Dr/Cr/Balance per landlord — useful for period reporting.
-- → Proposed PG table: cb_landlord_account_period_close
--
-- CHSUBS — 774,116 rows — Recurring Subscriptions / Advance Payments
-- Has INVONUM, PAYFOR, PAYMNTH — likely advance/prepayment entries.
-- May overlap with CHBCTD unallocated payments. Needs investigation.

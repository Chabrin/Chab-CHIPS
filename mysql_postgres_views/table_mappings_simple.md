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
CHOFALL -> staff_property_mappings
CHVENDS -> vendors
CHLRH -> landlord_property_mappings
CHCHQ -> cheque_register
CHCHD -> cheque_register_details

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
CHRMES -> Returns Scheduler
CHREST -> Bank Statement Recon Entries
CHRDED -> Deduction Config

-- PENDING_1 (empty / negligible — skipped)
CHLAP -> empty
CHRHMT -> empty
CHRTXT -> empty
CHPAYMENTS -> 4 rows (negligible)

-- ============================================================
-- UN_MAPPED (in CHABRINS, not yet in any view/migration)
-- ============================================================

-- >> LANDLORD ACCOUNTS & LOANS (⚠ review for migration)
CHLNTR -> MAPPED (2026-08-04) -> landlord_advance_repayment_schedule (see other_views_mod.sql _032)
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
CHVENDS -> MAPPED (2026-08-04) -> vendors (see other_views_mod.sql _028)
CHEXPS -> Property Expense Entries — 10 rows
Expenses paid to/by landlord per unit.

-- >> SYSTEM SETUP / CONFIG
CHOFALL -> MAPPED (2026-08-04) -> staff_property_mappings (see other_views_mod.sql _027)
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
CHJVHP -> JV Headers staging duplicate — 2 rows
CHBTDD -> Receipt Batch Lines (legacy) — 3 rows
CHBTDR -> Receipt Batch Headers variant (legacy) — 15 rows
CHTN -> Tenant quick-name ref — empty
CHVACT -> Vacated tenants — empty
CHRCSD -> Receipt Banking/Deposit records — empty
CHPUSS -> PV approval chain users — empty
CHPSEQv -> Payment sequence variant (duplicate of CHPSEQ) — empty
CHLOCA -> Office locations variant (1 row) — near-empty

-- >> PROPERTY / UNIT INFO (missing from earlier listing)
CHUINF -> Unit Info Notes — 20,820 rows
Free-text notes/status entries per unit/block (ENTRYID, BLOCKID, UNITNO, ENTDESC, ESTATUS).
CHUNICO -> Unit Recurring Non-Rental Charges — 42 rows
Defines standing non-rent charges per unit (PAYDESC, PAYAMT, PAYMOD, PAYAFT).

-- >> PROPERTY ISSUES / MAINTENANCE (missing from earlier listing)
CHISRE -> Property Issue / Complaint Register — 1 row
Logs issues per unit (ISSUEDES, CORRACT, priority, corrective action dates).

-- >> STANDALONE INVOICE SYSTEM (legacy, near-empty)
CHIVH -> Invoice Headers (legacy) — 3 rows
CHIVD -> Invoice Detail Lines (legacy) — 4 rows

-- ============================================================
-- ⚠ LANDLORD RECOVERIES / ADVANCES / LOANS — KEY GAPS
-- ============================================================
-- CHLNTR -> MAPPED (2026-08-04) -> landlord_advance_repayment_schedule
-- (see other_views_mod.sql _032). Feeds CHLACC (landlord account debits for
-- loan recovery) -- CHRACT (already mapped -> cb_money_recoveries) handles
-- charge recoveries; CHLNTR handles structured loan/advance repayment.
--
-- CHLCAC — 58,797 rows — Landlord Period Close Summaries
-- Monthly Dr/Cr/Balance per landlord — useful for period reporting.
-- → Proposed PG table: cb_landlord_account_period_close
--
-- CHSUBS — 774,116 rows — Recurring Subscriptions / Advance Payments
-- Has INVONUM, PAYFOR, PAYMNTH — likely advance/prepayment entries.
-- May overlap with CHBCTD unallocated payments. Needs investigation.

-- ============================================================
-- CHLRH STUDY NOTES (2026-08-04) — landlord-property links
-- MAPPED (2026-08-04) -> landlord_property_mappings (see other_views_mod.sql _029)
-- ============================================================
-- Columns: LORDID (varchar20), ENTRYID (int, 100% NULL — migrated anyway, not dropped),
--          BLOCKID (varchar20), MSTATUS (varchar50, 100% 'Active' in this data)
-- 1,812 rows. Near-1:1 property->landlord (only 1 BLOCKID has >1 distinct LORDID).
-- BLOCKID -> CHBLOCKS: 100% match (1812/1812).
-- LORDID  -> CHLRDS:   99.7% match (1809/1814).
--
-- IMPORTANT: this is NOT purely additive — CHBLOCKS already carries LLORDID
-- (the landlord currently stamped on the property, used by vw_sys_pm_properties_001
-- to set client_id). Comparing CHLRH.LORDID vs CHBLOCKS.LLORDID for the same
-- BLOCKID (after stripping the inconsistent 'LAN-' prefix on both sides):
--   92.3% agree (1,672 / 1,812)
--   7.7% DISAGREE (140 properties) — CHLRH names a different landlord than
--   the one currently stamped on CHBLOCKS.LLORDID.
-- This matches the note already in chabrins_to_postgres_views.sql:
--   "CHLRH maps LORDID -> BLOCKID with status. Use to verify client_id assignment."
-- i.e. CHLRH's primary value is as a RECONCILIATION check against
-- sys_pm_properties.client_id, not a fresh mapping table — the 140 disagreeing
-- rows need a human call (which source is right?) before building anything
-- that writes client_id changes.
--
-- Built as a plain `landlord_property_mappings` table (same shape as
-- staff_property_mappings), mirroring CHLRH 1:1. landlord_code is kept raw
-- alongside client_id specifically so the 140 mismatches can still be
-- reconciled against sys_pm_properties.client_id later — do not treat this
-- table as authoritative over the existing property.client_id without that
-- manual review.

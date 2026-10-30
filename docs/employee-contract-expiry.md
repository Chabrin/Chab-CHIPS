# Employee contract expiry report and summary

HR → Contracts is a live report with a generated-at timestamp in Africa/Nairobi. Opening or refreshing it calculates current figures; viewing it does not change contracts, employee status or notifications.

## Report period and filters

- The Contracts page defaults to all contracts/all dates in the existing listing, including older, unapproved, superseded and undated records. The original Date/Time, Staff, Basic Salary, Created By and View/Edit icons are retained, together with the original table search. Department, approval and expiry fields are additions. Employee coverage remains the existing active/on-leave coverage; no database query population is changed. The dashboard remains a 30-day expiry overview; its links explicitly open the 30-day subset. There are no 7/14-day UI shortcuts.
- Choose Custom range and use the From/To calendar inputs to filter contract **end dates**. Both dates are included. Use a past range to report on expired contracts. Empty, impossible or reversed ranges are rejected.
- Department and employee name/staff-number filters combine with the dates. Apply filters updates the table, matching total and downloads together. Reset to all contracts restores all dates and original contract scope, and clears department, employee, table search and individual link filters.
- Current approved contracts (status 5) cover active/on-leave staff (1/4), with valid start/end dates and a start date already reached. A newer approved, valid contract that has started supersedes older contracts for the same employee. Future renewals do not suppress the current contract until they start.
- Include contract history allows superseded and other approval states for active/on-leave staff, still within the selected expiry dates. It is not an all-employee archive.
- Records needing date review shows missing/invalid dates separately, without a date range. Department and employee filters still apply. A warning shows how many records need date review, rather than silently treating them as zero expirations.
- The top 30-day total is across all departments; the matching total below follows the applied filters. Counts are contracts, not distinct employees. Dashboard and notification eligibility use the same EmployeeContractReport service.
- Applied filters are saved in the page URL, so refreshing or sharing a link restores them. Legacy individual contract links resolve to the contract's exact expiry date/history or date-review scope.
- Changing controls disables downloads until Apply filters. Refresh reloads the applied report, discarding unapplied control edits. A refresh failure blocks exports; stale rows, if present, are explicitly labelled.
- The main table retains the original columns and actions. Full Export CSV and Print / Save as PDF retain salary, creator and creation timestamp as well as the additional expiry fields. Missing departments show Not set. A separate Expiry report CSV is available after applying Next 30 days or Custom range with Current approved contracts; only that focused expiry report omits salary and creator fields.

## One summary instead of individual alerts

Run:

```powershell
php artisan hr:check-expiring-contracts --dry-run
```

The preview prints the live counts and proposed notification actions without writing anything. It does not generate a download; use Export CSV on the report.

Run the command without --dry-run to create/update the in-system summary for unblocked users in the hr_manager or directors roles. One user with both roles receives one copy. The message states the count expiring within 30 days and links to /hr/contracts?window=30.

Unread summaries are updated in place, even over multiple days. After a summary is read, another is not generated on the same Nairobi day. A later day's check can create the next summary. If the upcoming count reaches zero, the unread summary is resolved (marked read), without generating alerts for old expired contracts. Notifications show the last check; opening the report retrieves current data.

Old individual EmployeeContractExpiry alerts are marked read on the next non-preview run for eligible recipients, preserving their history. No new individual alerts are created. The old employee_contract_reminders table is retained as history and is no longer used by this command; do not delete it or roll back its migration as part of this change. No new migration is required.

There is no email delivery or queue dependency for these summaries. Employment, payroll and approval statuses are not modified. Use test data before enabling scheduled delivery against a restored database.

## Daily scheduling

The command remains scheduled at 08:00 Africa/Nairobi in routes/console.php. php artisan serve alone does not run the scheduler. On a local workstation, keep php artisan schedule:work running in another terminal; deployment needs a scheduler invocation every minute. php artisan schedule:list verifies registration. Starting/stopping a scheduler is an operational step, not something viewing the report does.

## Dashboard overview

The HR Manager and Director dashboards show Contracts expiring within 30 days at the bottom, after Leave Overview. One total links to the same 30-day report, followed by up to five upcoming contracts in earliest-end-date order. View all opens the full report; View beside an employee inspects their contract. The section inherits the system typography and card/table styles with blue/navy accents. It loads on opening and has a Refresh button. Failures display Unavailable, not zero. The endpoint independently checks HR Manager/Directors roles and returns no salary data. Viewing or refreshing never creates notifications or changes records.

## CSV and printable report

Full Export CSV, the separate Expiry report CSV, and Print / Save as PDF use all matching rows across every page, in current sort order. Both include department, position, approval status, expiry dates/status and remaining/overdue days, plus the selected dates, scope, department, employee search, matching total and generation time in Africa/Nairobi. Negative day counts mean overdue. Full contract outputs retain salary and creator fields; the separate expiry CSV omits them. CSV escaping, UTF-8 BOM and protection against formula-like text are preserved; print output escapes all record and filter text. Read-contracts permission gates both buttons.

Print opens a separate A4 landscape report and the browser print dialog. Choose Save as PDF there to download a PDF; no PDF server/package is needed. Permit pop-ups for the local site if blocked. Print repeats table headings across pages. Both outputs represent the loaded report; Refresh first for changed records. Exports do not write database records.

## Verification

Use local test contracts and an eligible HR Manager/Director account:

1. Check end dates today, +30, +31 and yesterday against the default 30-day view. Check both boundaries of a custom past/future range, equal From/To dates and rejected reversed/impossible dates.
2. Verify a started renewal excludes the older contract from normal report counts, but Include contract history shows it when its end date is in the selected range.
3. Verify inactive staff, invalid/missing dates and unapproved contracts are excluded from the normal report.
4. Combine department and employee filters, Apply, sort, then export CSV and Print / Save as PDF. Compare both outputs with matching rows across all pages. Refresh/reopen the URL to confirm filters persist. Reset clears the filters. Review missing/invalid dates separately.
5. Preview, then run the command. Confirm one summary, not one alert per contract.
6. Change eligible contracts, rerun and confirm the same unread summary is updated.
7. Read it, rerun that day and confirm no new summary. Test a later day with temporary test time rather than changing Windows time.
8. Verify zero upcoming contracts resolves an unread summary; expired contracts remain available through a custom past date range.

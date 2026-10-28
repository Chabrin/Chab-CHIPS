# Employee contract expiry tracking

HR → Contracts shows date-based status, days remaining, filters, and summary counts for contracts belonging to active employees. These labels do not change approval status, payroll, or employment status. An end date remains valid throughout that date in Africa/Nairobi.

## Setup

Run `php artisan migrate` to create the reminder history table. No existing contracts are changed by this migration.

Preview with `php artisan hr:check-expiring-contracts --dry-run`. This reports the number of reminders that would be created, without writing notifications or reminder history.

Run `php artisan hr:check-expiring-contracts` to create in-system notifications. Recipients are unblocked users with the `hr_manager` or `directors` role. A user with both roles receives only one copy of each reminder. No emails are sent and no queue worker is needed for these reminders.

The daily check is registered in `routes/console.php` for 08:00 Africa/Nairobi. On a local workstation, keep `php artisan schedule:work` running in a separate terminal. In deployment, the host must invoke Laravel's scheduler every minute. `php artisan serve` alone does not run scheduled commands. `php artisan schedule:list` verifies registration.

## Reminder rules

- Approved contracts (`current_status_id = 5`) for active employees (`current_status_id = 1`) are eligible once their start date has arrived.
- Reminder windows are 30–15 days remaining, 14–8, 7–1, and expiry day or overdue. A late scheduler run catches up within the current window rather than sending every missed reminder.
- Each recipient receives a reminder once per contract, end date, and window, even if the command is rerun or the notification is deleted. Changing the end date allows reminders for the new date.
- Older contracts are suppressed if a newer approved contract for that employee has already started. Future renewals do not suppress the current contract's reminder.
- Missing or invalid dates are flagged in the list and do not generate expiry reminders.
- On the first run, overdue contracts that meet these rules also generate one alert per recipient. Use the preview before enabling the scheduler against restored historical data.
- Alert links open the Contracts list filtered to the relevant contract; Show all contracts clears this filter.

## Manual verification

The Export CSV button is available with `read_contracts` permission. It downloads the already-loaded contracts matching the current table search, expiry filter, and notification contract filter, across all pages and in the current sort order. The file includes employee and contract fields displayed in the table, including basic salary, but no action links. It does not query additional records or write to the database. The button stays disabled while loading and when no matching rows exist. Text fields are escaped for CSV and protected against spreadsheet formula execution.

Use local test data: check contracts ending in 31, 30, 14, 7, and 0 days, yesterday, a future start, and missing dates. Verify labels, counts, filters, and the distinction between approval and expiry. Preview reminders, then run the command twice and confirm only one alert per eligible recipient/window. Check that an inactive employee, unapproved contract, blocked recipient, and superseded contract do not create alerts. Test with an HR Manager or Directors account; another user's notification bell will not show that recipient's alerts.

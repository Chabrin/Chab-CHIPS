# Staff password expiry and reminders

Staff accounts (linked through `staff_id` or `sys_user_staff_links`) must change their password every 30 days. Staff change their password in **My Account > Security & Password**. Expired sessions open `/user/profile?tab=security`; other application requests remain blocked on the server until the password changes. Administrator-required changes use the same form, without a second password modal over My Account. Non-staff accounts retain their existing expiry behaviour.

The former `/staff/password` address redirects to My Account so existing reminder links keep working. There is no separate Change Password entry in the account menu or standalone password screen.

The model saves each password change, its hashed history, and the change date in one database transaction. This covers the My Account form, forgotten-password resets, account activation and administrator changes. All recorded passwords are retained; previous plaintext passwords cannot be recovered or compared retrospectively. Current passwords are seeded into history at activation. System-account password hashes are tracked even before an account is linked to staff.

Existing known password-change dates are preserved. Accounts without a recorded date receive 30 days from migration. Staff with an existing date older than 30 days must change their password when they next access the application.

## Activate on the application server

Deploy the files and run the migration before accepting application traffic:

```sh
php artisan migrate --path=database/migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php
```

Ensure the server runs Laravel's scheduler every minute (keep an existing scheduler rather than installing another):

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, an equivalent scheduled task can run `php artisan schedule:run` every minute from the application directory.

The command `staff:password-reminders` runs daily at 08:00 Africa/Nairobi. It creates private notification-bell alerts 3 days and 1 day before expiry, linking directly to My Account's Security & Password tab. It skips blocked accounts, expired passwords and accounts already required to reset. A persistent unique reminder record prevents duplicate alerts for the same expiry and threshold, including after a notification is read or deleted. A password change starts a new reminder cycle. No email or SMS is sent.

To check delivery in a staging environment with a staff password due in 3 days or 1 day (last changed 27 or 29 days ago):

```sh
php artisan staff:password-reminders
```

Sign in as that staff member and open the notification bell. Run the command again and confirm no duplicate. Open **My Account > Security & Password** to change a password before expiry. Test expiry using a staging account and confirm that the same tab opens automatically, that other modules stay blocked, and that a previously recorded password is rejected by both My Account and forgotten-password reset.

## Automated checks

```sh
php vendor/phpunit/phpunit/phpunit --filter 'StaffPasswordPolicyTest|NotificationFeedTest'
```

These tests use an isolated SQLite memory database and require PDO SQLite. They do not migrate or change the configured application database.

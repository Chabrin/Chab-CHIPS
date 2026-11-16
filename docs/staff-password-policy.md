# Password security

Staff accounts (linked through `staff_id` or `sys_user_staff_links`) can be made to change their password on a schedule, choose strong passwords, and never go back to an old or similar password. Staff change their password in **My Account > Security & Password**.

## Settings

Everything is set on **System Controls > Password Security** (table `cb_system_configs`, category `password_security`). Nothing is hard-coded. **Password security ships switched off**, so deploying it changes nothing until someone turns it on.

| Setting | Default | What it does |
|---|---|---|
| Enforce password security | Off | Master switch. While off, none of the rules below are enforced and no reminders are sent. |
| Password expires after (days) | 30 | 1 to 365. Staff must change their password this many days after the last change. |
| Remind before expiry (days, comma separated) | 3,1 | Up to 5 days, each 1 to 60, for example `7,3,1`. Empty means no reminders. |
| Minimum password length | 8 | 8 to 64. While security is off the forms keep their old fixed length (8, and 10 on account activation). |
| Require upper case, lower case and a number | On | Applies to every password typed into the change, reset and activation forms. |
| Recent passwords that cannot be reused (0 = all) | 0 | 0 blocks every password the account has ever had. A number blocks only that many recent ones, counting the current one. |
| Block a password similar to the current one | On | Refuses a new password that has the same letters with only the ending changed (`Summer2025!` to `Summer2026!`) or is at most 2 characters different. |

A wrong value is refused when you save (for example 0 days or a length of 5). If a value is ever unreadable or missing the default above is used, so a bad row can never lock anyone out.

Expiry, reminders, reuse and similarity apply to staff accounts. Length and complexity apply to every account that uses the change, reset or activation forms while the switch is on. Passwords typed by an administrator for a new user keep their own form rule, and generated temporary passwords are not checked for complexity (staff must change them at first login).

Similarity can only be checked against the current password, which the change form asks for. The forgot-password reset and activation have no current password, so they check length, complexity and reuse only. Older passwords are stored as hashes and cannot be compared.

## What happens

Expired sessions open `/user/profile?tab=security`; other application requests stay blocked on the server until the password changes. Administrator-required changes use the same form, without a second password modal over My Account.

The former `/staff/password` address redirects to My Account so existing reminder links keep working.

The model saves each password change, its hashed history, and the change date in one database transaction. This covers the My Account form, forgotten-password resets, account activation and administrator changes. **Changes are recorded even while the switch is off**, so switching it on later starts from real dates and real history.

Existing known password-change dates are preserved. Accounts without a recorded date receive the full period counted from the migration. When the switch is turned on, staff whose last change is already older than the expiry period must change their password at their next visit, so check password dates first if that would catch many people at once.

## Activate on the application server

1. Deploy the files and run the migrations before accepting traffic:

```sh
php artisan migrate --path=database/migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php
php artisan migrate --path=database/migrations/2026_11_16_000001_add_password_security_settings.php
```

The second migration adds the Password Security tab and its rows (switched off). It never overwrites a value that was already changed, and `down()` removes only those rows.

2. Ensure the server runs Laravel's scheduler every minute (keep an existing scheduler rather than installing another):

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, an equivalent scheduled task can run `php artisan schedule:run` every minute from the application directory.

3. When ready, open **System Controls > Password Security**, adjust the numbers, switch **Enforce password security** on and press **Save Changes**.

## Reminders

The command `staff:password-reminders` runs daily at 08:00 Africa/Nairobi. It creates private notification-bell alerts on the days set under *Remind before expiry*, linking directly to My Account's Security & Password tab. It does nothing while the switch is off. It skips blocked accounts, expired passwords and accounts already required to reset. A persistent unique reminder record prevents duplicate alerts for the same expiry and day, including after a notification is read or deleted. A password change starts a new reminder cycle. No email or SMS is sent.

To check delivery in a staging environment, switch the setting on and use a staff password due on a reminder day (for example last changed 27 days ago with the default 30 and 3,1):

```sh
php artisan staff:password-reminders
```

Sign in as that staff member and open the notification bell. Run the command again and confirm no duplicate. Test expiry using a staging account and confirm that the same tab opens automatically, that other modules stay blocked, and that a previously recorded password is rejected by both My Account and forgotten-password reset.

## Automated checks

```sh
php vendor/phpunit/phpunit/phpunit --filter 'StaffPasswordPolicyTest|PasswordSecuritySettingsTest|NotificationFeedTest'
```

These tests use an isolated SQLite memory database and require PDO SQLite. They do not migrate or change the configured application database.

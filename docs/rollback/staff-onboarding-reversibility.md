# Staff onboarding: undo and redo

The onboarding changes are isolated from the existing leave-management edits and the earlier staff-index filter edits.

## Undo

1. If the onboarding migration has been run, first run:

   `php artisan migrate:rollback --path=database/migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php`

   This drops only the onboarding invitation table and removes pending onboarding upload files. Approved staff records and their copied employee documents remain in their normal tables and private document directory.
2. Remove the six `staff.onboarding.*` management routes and the public `/staff/onboarding/{token}` route group from `routes/web.php`.
3. Remove only the four-line **Onboarding** button from `resources/views/staff/index.blade.php`. Keep the active staff filter edits in that file.
4. Remove the files listed in `staff-onboarding-files.zip` from the project root paths contained in the archive.

## Redo

1. Expand `staff-onboarding-files.zip` into the project root, preserving its directory paths.
2. Re-add the six authenticated `staff.onboarding.*` routes beside the staff management routes in `routes/web.php` and the throttled public GET/POST token routes outside the authenticated groups.
3. Add the **Onboarding** button beside **Add Staff** in `resources/views/staff/index.blade.php`, inside the existing `create_staff` permission block.
4. Run `php artisan migrate` in the intended local/test database.

Do not use a whole-file restore for `routes/web.php` or the staff index view: both already contained unrelated working-tree changes before this feature was added.
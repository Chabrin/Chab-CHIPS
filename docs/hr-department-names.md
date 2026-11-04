# Shared department names

> **Status (2026-11-04): the migration is disabled.** Its body is commented out because staging and
> prod hold both `FIELD` and a separate `PROPERTY MANAGEMENT` department, which stops the rename.
> The three names are changed by hand in HR Settings > Departments instead (after moving staff and
> positions off the duplicate). The migration now records as run and changes nothing; the rest of
> this document describes what it did when it was active.

Migration `2026_10_28_000001_standardize_hr_department_names` renames existing departments:

| Previous name | Shared name |
| --- | --- |
| ADMINISTRATION | Administration & General |
| ACCOUNTS | Accounting & Finance |
| FIELD | Property Management |

Only `sys_set_departments.department_name` changes. Department IDs, staff and position assignments,
reporting relationships, salaries, and permissions stay unchanged. Pages, new exports, and other
output reading the live department name will display the shared names. Existing exported files
are not rewritten. HR, Test, and all other departments are left alone.

The update matches old labels without regard to case or surrounding whitespace. Missing or already
renamed departments are skipped. If an old label matches multiple records, or both the old and new
records exist, the entire update stops without renaming anything. Review assignments before
resolving duplicates; this migration does not merge or delete departments.

## Applying after review

Commit and share the migration through the team's agreed Git workflow. Each developer pulls the
shared commit, checks `php artisan migrate:status`, and runs migrations against their own intended
database. Git alone does not apply this update to anyone's database.

To apply only this update after approval:

```powershell
php artisan migrate --path=database/migrations/2026_10_28_000001_standardize_hr_department_names.php
```

Refresh the Departments and Staff Positions pages afterward. This migration does not run
`OrgStructureHierarchySeeder` or populate missing reporting assignments. That seeder continues
to recognize the canonical names after the rename.

## Rollback

`down()` deliberately leaves names unchanged: canonical names might already have existed before
this migration. A blanket reverse rename could alter those pre-existing records. If names need
reverting later, prepare a reviewed follow-up migration targeting the intended department IDs.

## Verification

`DepartmentNamesMigrationTest` uses an isolated in-memory SQLite connection. It checks that staff
and position links survive, unrelated records remain unchanged, retries are harmless, and duplicate
records prevent partial renames. It does not connect to the local PostgreSQL database.

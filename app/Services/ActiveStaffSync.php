<?php

namespace App\Services;

use App\Models\Staff;
use App\Support\HireDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings the staff list in line with the legacy system's "active staff" export.
 *
 *  - Staff on the list are matched by staff number (leading zeros ignored). For each field, a real value in the file
 *    replaces the one in the system; a blank, "NULL" or invalid value in the file never erases anything.
 *  - Staff on the list who are marked Terminated become Active again. Suspended and On Leave are left as they are.
 *  - People on the list who are not in the system are added as Active staff.
 *  - Staff not on the list who are Active become Terminated, which is how the system records someone who has left
 *    (the termination workflow sets the same status). Any other status they hold is left alone, and so is anyone
 *    with no staff number, since they cannot be checked against the list at all.
 *
 * Nothing is written unless $apply is true; the report is the same either way. Passwords, employment type, address
 * and the legacy codes in the file are never read.
 */
class ActiveStaffSync
{
    public const ACTIVE = 1;
    public const TERMINATED = 3;

    private const MARITAL = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'divorced' => 'Divorced', 'separated' => 'Separated'];

    /** What the export writes for "nothing here" */
    private const EMPTY_WORDS = ['NULL', 'NIL', 'N/A', 'NA', '-'];

    /** Job titles in the export that the position list spells differently */
    private const TITLE_ALIASES = ['quality assurance & valuation manager' => 'qa & valuation manager'];

    private const COLUMNS = [
        'id', 'staff_number', 'current_status_id', 'full_names', 'gender', 'marital', 'national_id', 'district', 'location',
        'sub_location', 'spouse_name', 'birth_place', 'birth_date', 'pin_number', 'nhif_number', 'nssf_number', 'bank_name',
        'branch_id', 'account_number', 'mobile_number', 'email_address', 'personal_email', 'disability', 'citizenship',
        'position_id', 'department_id', 'hire_date',
    ];

    /**
     * @param array<int, array<string, mixed>> $rows one array per person, keyed by the export's column names
     * @return array{applied: bool, file_rows: int, matched: int, created: array, ambiguous: array, duplicate_in_file: array, staff_changed: int, field_counts: array, skipped: array, reactivated: array, made_inactive: array, left_alone: array, no_number: array, unknown_titles: array, changes: array}
     */
    public function run(array $rows, bool $apply = false): array
    {
        $report = [
            'applied' => $apply, 'file_rows' => 0, 'matched' => 0, 'created' => [], 'ambiguous' => [], 'duplicate_in_file' => [],
            'staff_changed' => 0, 'field_counts' => [], 'skipped' => [], 'reactivated' => [], 'made_inactive' => [],
            'left_alone' => [], 'no_number' => [], 'unknown_titles' => [], 'changes' => [],
        ];

        $staff = Staff::query()->get(self::COLUMNS);
        $byNumber = $staff->filter(fn ($member) => $this->key($member->staff_number) !== '')->groupBy(fn ($member) => $this->key($member->staff_number));
        [$positions, $branches] = [$this->positions(), $this->branches()];

        $updates = [];
        $creates = [];
        $onList = [];
        $seen = [];

        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_UPPER);
            $number = $this->clean($row['EMPNUMB'] ?? null);
            if ($number === null) {
                continue;
            }

            $key = $this->key($number);
            if (isset($seen[$key])) {
                $report['duplicate_in_file'][] = $number;
                continue;
            }
            $seen[$key] = true;
            $report['file_rows']++;

            [$values, $skipped] = $this->valuesFrom($row, $positions, $branches, $report['unknown_titles']);
            foreach ($skipped as [$field, $reason]) {
                $report['skipped'][] = [$number, $field, $reason];
            }

            $matches = $byNumber->get($key, collect());

            if ($matches->count() > 1) {
                $report['ambiguous'][] = $number;
                foreach ($matches as $member) {
                    $onList[$member->id] = true;
                }
                continue;
            }

            if ($matches->isEmpty()) {
                if (!isset($values['full_names'])) {
                    $report['skipped'][] = [$number, 'FULLNAME', 'not added: the file has no name for this person'];
                    continue;
                }
                $creates[] = $values + ['staff_number' => $number, 'current_status_id' => self::ACTIVE, 'date_time' => now()];
                $report['created'][] = $number;
                continue;
            }

            $member = $matches->first();
            $onList[$member->id] = true;
            $report['matched']++;

            $changes = [];
            foreach ($values as $column => $new) {
                if (!$this->same($column, $member->getRawOriginal($column), $new)) {
                    $changes[$column] = $new;
                    $report['changes'][] = [$number, $column, $member->getRawOriginal($column), $new];
                }
            }

            if ((int) $member->current_status_id === self::TERMINATED) {
                $changes['current_status_id'] = self::ACTIVE;
                $report['reactivated'][] = $number;
            }

            if ($changes) {
                $updates[] = [$member, $changes];
            }
        }

        foreach ($staff as $member) {
            if (isset($onList[$member->id])) {
                continue;
            }

            if ($this->key($member->staff_number) === '') {
                $report['no_number'][] = $member->id;
            } elseif ((int) $member->current_status_id === self::ACTIVE) {
                $updates[] = [$member, ['current_status_id' => self::TERMINATED]];
                $report['made_inactive'][] = $member->staff_number ?: '#' . $member->id;
            } else {
                $status = (string) ($member->current_status_id ?? 'none');
                $report['left_alone'][$status] = ($report['left_alone'][$status] ?? 0) + 1;
            }
        }

        foreach ($updates as [, $changes]) {
            foreach (array_keys($changes) as $column) {
                $report['field_counts'][$column] = ($report['field_counts'][$column] ?? 0) + 1;
            }
        }
        $report['staff_changed'] = count($updates);
        ksort($report['field_counts']);

        if ($apply) {
            // One save per person through the model, so the audit log records what changed
            DB::transaction(function () use ($updates, $creates) {
                foreach ($updates as [$member, $changes]) {
                    $member->forceFill($changes)->save();
                }
                foreach ($creates as $attributes) {
                    Staff::forceCreate($attributes);
                }
            });
        }

        return $report;
    }

    /**
     * The values a row of the export gives for each staff column, and the ones it gives that cannot be used.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array{0: string, 1: string}>}
     */
    private function valuesFrom(array $row, Collection $positions, Collection $branches, array &$unknownTitles): array
    {
        $values = [];
        $skipped = [];

        $take = function (string $column, string $field, callable $normalise) use ($row, &$values, &$skipped) {
            $raw = $this->clean($row[$field] ?? null);
            if ($raw === null) {
                return;
            }

            $value = $normalise($raw);
            if ($value === null) {
                $skipped[] = [$field, "'" . mb_substr($raw, 0, 40) . "' is not a usable value, so the system's value was kept"];
            } else {
                $values[$column] = $value;
            }
        };

        $text = fn ($value) => $value;
        $take('full_names', 'FULLNAME', fn ($value) => mb_strtoupper($value));
        $take('gender', 'GENDER', fn ($value) => ['male' => 'Male', 'female' => 'Female'][mb_strtolower($value)] ?? null);
        $take('marital', 'MSTATUS', fn ($value) => self::MARITAL[mb_strtolower($value)] ?? null);
        $take('national_id', 'NATIDNO', fn ($value) => preg_match('/^[A-Za-z0-9]{5,12}$/', $value) ? strtoupper($value) : null);
        $take('district', 'DISTRCT', $text);
        $take('location', 'IDLOCAT', $text);
        $take('sub_location', 'IDSUBLOC', $text);
        $take('spouse_name', 'SPOUSNAM', $text);
        $take('birth_place', 'PLACEOB', $text);
        $take('birth_date', 'EMPDOB', fn ($value) => $this->birthDate($value));
        $take('pin_number', 'NUMPIN', fn ($value) => preg_match('/^[A-Za-z]\d{9}[A-Za-z0-9]$/', $value) ? strtoupper($value) : null);
        $take('nhif_number', 'NUMNHIF', fn ($value) => strtoupper($value));
        $take('nssf_number', 'NUMNSSF', fn ($value) => strtoupper($value));
        $take('mobile_number', 'MOBNUM1', function ($value) {
            $number = preg_replace('/[\s-]/', '', $value);

            return preg_match('/^\+?\d{9,13}$/', $number) ? $number : null;
        });
        $take('email_address', 'WEMAIL', fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : null);
        $take('personal_email', 'PEMAIL', fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : null);
        $take('disability', 'PWDSTAT', fn ($value) => ['yes' => 'YES', 'no' => 'NO'][mb_strtolower($value)] ?? null);
        $take('citizenship', 'CITIZEN', fn ($value) => ucfirst(mb_strtolower($value)));
        $take('account_number', 'BANKACC', fn ($value) => preg_match('/^\d{6,20}$/', $value) ? $value : null);
        $take('hire_date', 'APPDATE', function ($value) {
            $date = HireDate::parse($value);

            return $date && $date->year >= 1980 && $date->lte(now()->addDay()) ? $date->toDateString() : null;
        });

        // The bank is worked out from the branch code, which names the bank and the branch exactly
        $branchCode = $this->clean($row['BANKBRAN'] ?? null);
        if ($branchCode !== null) {
            $branch = $branches->get(str_pad(preg_replace('/\D/', '', $branchCode), 5, '0', STR_PAD_LEFT));
            if ($branch) {
                $values['branch_id'] = (int) $branch->id;
                $values['bank_name'] = (int) $branch->bank_id;
            } else {
                $skipped[] = ['BANKBRAN', "branch code '{$branchCode}' is not in the bank branch list, so the bank was kept"];
            }
        }

        // The position (and so the department) comes from the job title
        $title = $this->clean($row['RTNCAT'] ?? null);
        if ($title !== null) {
            $position = $positions->get($this->titleKey($title));
            if ($position) {
                $values['position_id'] = (int) $position->id;
                if ($position->department_id !== null) {
                    $values['department_id'] = (int) $position->department_id;
                }
            } else {
                $unknownTitles[$title] = ($unknownTitles[$title] ?? 0) + 1;
                $skipped[] = ['RTNCAT', "job title '{$title}' is not in the position list, so the position was kept"];
            }
        }

        return [$values, $skipped];
    }

    private function birthDate(string $value): ?string
    {
        $date = \DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));

        return $date && $date->format('Y-m-d') === substr($value, 0, 10) && (int) $date->format('Y') >= 1930 && $date <= new \DateTime('-15 years')
            ? $date->format('Y-m-d')
            : null;
    }

    /** Positions by job title, the first one winning when two read the same */
    private function positions(): Collection
    {
        $found = collect();
        foreach (DB::table('sys_set_staff_positions')->orderBy('id')->get(['id', 'position_name', 'department_id']) as $position) {
            $key = $this->titleKey((string) $position->position_name);
            if (!$found->has($key)) {
                $found->put($key, $position);
            }
        }

        return $found;
    }

    private function branches(): Collection
    {
        return DB::table('sys_set_banks_branches')->get(['id', 'bank_id', 'branch_code'])->keyBy(fn ($branch) => (string) $branch->branch_code);
    }

    /** A job title with case, spacing and a trailing plural removed ("Field Officers" and "FIELD OFFICER" match) */
    private function titleKey(string $title): string
    {
        $key = preg_replace('/\s+/', ' ', mb_strtolower(trim($title)));
        $key = self::TITLE_ALIASES[$key] ?? $key;

        return preg_replace('/s$/', '', $key);
    }

    /** A staff number for matching: upper case, without leading zeros ("002" and "2" are the same person) */
    private function key(mixed $number): string
    {
        $number = strtoupper(trim((string) $number));
        $trimmed = ltrim($number, '0');

        return $trimmed === '' ? ($number === '' ? '' : '0') : $trimmed;
    }

    /** A cell's text with spacing tidied, or null when the export wrote nothing useful there */
    private function clean(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $text === '' || in_array(strtoupper($text), self::EMPTY_WORDS, true) ? null : $text;
    }

    /** Whether the system already holds this value (case, spacing and date format aside) */
    private function same(string $column, mixed $old, mixed $new): bool
    {
        if ($old === null || trim((string) $old) === '') {
            return false;
        }

        if ($column === 'hire_date') {
            return HireDate::parse($old)?->toDateString() === $new;
        }

        if ($column === 'birth_date') {
            return substr((string) $old, 0, 10) === $new;
        }

        $normalise = fn ($value) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));

        return $normalise($old) === $normalise($new);
    }
}

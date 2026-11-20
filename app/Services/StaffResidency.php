<?php

namespace App\Services;

use Illuminate\Validation\Rule;

/**
 * Citizenship and residency for a staff member: the country of citizenship first, then Resident or Non-resident.
 *
 * - Kenya: Resident by default (HR can change it) and the National ID is required.
 * - Any other country: residency must be chosen, and the passport number, work permit (or Alien ID) number and the permit's expiry
 *   date are required. The National ID is then optional.
 *
 * Only the answers are kept here. How a non-resident is taxed is not changed by this: the accountant decides that rule.
 */
class StaffResidency
{
    public const RESIDENT = 'RESIDENT';
    public const NON_RESIDENT = 'NON_RESIDENT';

    public const LABELS = [self::RESIDENT => 'Resident', self::NON_RESIDENT => 'Non-resident'];

    /**
     * The countries to choose from, Kenya first and the rest in alphabetical order.
     *
     * @return string[]
     */
    public static function countries(): array
    {
        $home = (string) config('countries.home', 'Kenya');
        $others = array_values(array_unique(array_filter((array) config('countries.list', []), fn ($country) => $country !== $home)));
        sort($others, SORT_NATURAL | SORT_FLAG_CASE);

        return array_merge([$home], $others);
    }

    public static function isHome(?string $nationality): bool
    {
        return strcasecmp(trim((string) $nationality), (string) config('countries.home', 'Kenya')) === 0;
    }

    /**
     * The answers a form did not send at all, so an older form or an import that only knows the old Resident / NO answer keeps working:
     * Kenya, and the residency that goes with the old answer (Resident unless it said NO).
     *
     * @return array{nationality: string, residency_status: string}
     */
    public function defaults(array $input): array
    {
        $legacy = strtoupper(trim((string) ($input['citizenship'] ?? '')));

        // Only an answer the form did not send at all is filled in. An answer that was sent blank stays blank, so HR is asked for it.
        return [
            'nationality' => array_key_exists('nationality', $input) ? $input['nationality'] : (string) config('countries.home', 'Kenya'),
            'residency_status' => array_key_exists('residency_status', $input) ? $input['residency_status'] : ($legacy === 'NO' ? self::NON_RESIDENT : self::RESIDENT),
        ];
    }

    /**
     * The validation rules for these fields, for the country chosen. $ignoreStaffId is the person being edited, so their own ID
     * number is not a duplicate of itself.
     */
    public function rules(?string $nationality, ?int $ignoreStaffId = null, bool $newStaff = true): array
    {
        $home = self::isHome($nationality);
        $unique = 'unique:sys_staff,national_id' . ($ignoreStaffId ? ',' . $ignoreStaffId : '');

        return [
            'nationality' => ['required', 'string', Rule::in(self::countries())],
            'residency_status' => ['required', Rule::in([self::RESIDENT, self::NON_RESIDENT])],
            'national_id' => $home ? ['required', 'string', 'max:255', $unique] : ['nullable', 'string', 'max:255', $unique],
            'passport' => $home ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'work_permit_number' => $home ? ['nullable', 'string', 'max:100'] : ['required', 'string', 'max:100'],
            // A permit that has already run out cannot be entered for someone being added; for an existing record it may be on file as it is
            'work_permit_expiry' => $home ? ['nullable', 'date'] : ($newStaff ? ['required', 'date', 'after_or_equal:today'] : ['required', 'date']),
            'citizenship' => ['nullable', 'string'],
        ];
    }

    /**
     * What the old citizenship column holds for a residency, so anything that still reads it sees the same kind of answer as before.
     */
    public static function legacyCitizenship(?string $residency): string
    {
        return $residency === self::NON_RESIDENT ? 'NO' : 'RESIDENT';
    }
}

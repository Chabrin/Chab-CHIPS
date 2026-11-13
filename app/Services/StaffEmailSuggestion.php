<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\User;
use App\Support\StaffContact;

/**
 * The official email to suggest for a new staff member: firstname.surname@chabrinagencies.co.ke. When that address already
 * belongs to a current or former staff member (or a system login), the first middle name's initial is added
 * (mary.a.wanjiku@...), and with no middle name to use a number goes on the surname (mary.wanjiku2@...). The notice says
 * why, so HR knows the address was changed and can pick another.
 */
class StaffEmailSuggestion
{
    /**
     * @return array{email: ?string, notice: ?string} email is null when there is only one name to build it from
     */
    public function suggest(?string $fullNames, ?int $exceptStaffId = null): array
    {
        $plain = StaffContact::emailFor($fullNames);
        if ($plain === null) {
            return ['email' => null, 'notice' => null];
        }

        $owner = $this->ownerOf($plain, $exceptStaffId);
        if ($owner === null) {
            return ['email' => $plain, 'notice' => null];
        }

        $email = $this->firstFree($fullNames, $exceptStaffId);

        return [
            'email' => $email,
            'notice' => "{$plain} is already used by {$owner}, so {$email} was suggested. You can change it.",
        ];
    }

    private function firstFree(?string $fullNames, ?int $exceptStaffId): string
    {
        $withInitial = StaffContact::emailWithInitial($fullNames);
        if ($withInitial !== null && $this->ownerOf($withInitial, $exceptStaffId) === null) {
            return $withInitial;
        }

        // Two people with the same names and middle initial, or no middle name: a number tells them apart
        for ($number = 2; $number <= 99; $number++) {
            $numbered = StaffContact::emailWithNumber($fullNames, $number);
            if ($this->ownerOf($numbered, $exceptStaffId) === null) {
                return $numbered;
            }
        }

        // Nothing free in the format: leave HR the plain one to change by hand
        return StaffContact::emailFor($fullNames);
    }

    /**
     * Who holds this address, in words ("MARY WANJIKU (former staff)"), or null when it is free. Terminated staff count,
     * because their address may not be handed to someone else.
     */
    private function ownerOf(string $email, ?int $exceptStaffId): ?string
    {
        $staff = Staff::whereRaw('LOWER(email_address) = ?', [strtolower($email)])
            ->when($exceptStaffId, fn ($query) => $query->where('id', '!=', $exceptStaffId))
            ->first();

        if ($staff) {
            $name = trim((string) $staff->full_names) !== '' ? $staff->full_names : 'another staff member';

            return (int) $staff->current_status_id === 3 ? "{$name} (former staff)" : $name;
        }

        return User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists() ? 'a system user' : null;
    }
}

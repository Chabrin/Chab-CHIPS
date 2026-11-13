<?php

namespace App\Rules;

use App\Support\StaffContact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A staff mobile number written with its country code, as StaffContact::mobile() returns it. */
class StaffMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!StaffContact::isMobile((string) $value)) {
            $fail('Enter the mobile number with its country code, like +254 7XX XXX XXX.');
        }
    }
}

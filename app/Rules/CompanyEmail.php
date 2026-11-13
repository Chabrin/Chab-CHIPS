<?php

namespace App\Rules;

use App\Support\StaffContact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A staff email in the company format: firstname.surname@chabrinagencies.co.ke. */
class CompanyEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!StaffContact::isCompanyEmail((string) $value)) {
            $fail('Use the company email format: ' . StaffContact::emailExample() . '.');
        }
    }
}

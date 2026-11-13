<?php

namespace App\Services;

use App\Models\Staff;
use App\Rules\CompanyEmail;
use App\Rules\StaffMobile;
use App\Support\StaffContact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class StaffContactInput
{
    /**
     * Put the mobile number and the emails in a request into the company's format (country code, lower case) and
     * return the rules to add after each field's own rules. When $current is given (editing), a value HR has not
     * changed is left exactly as it is, so older records written another way can still be saved.
     *
     * @return array<string, array<int, ValidationRule>> by field
     */
    public function prepare(Request $request, ?Staff $current = null): array
    {
        $rules = [];

        if ($request->has('mobile_number') && $this->changed($request, $current, 'mobile_number', false)) {
            $request->merge(['mobile_number' => StaffContact::mobile($request->input('mobile_number'))]);
            $rules['mobile_number'] = [new StaffMobile()];
        }

        foreach (['email_address', 'login_email'] as $field) {
            if ($request->has($field) && $this->changed($request, $current, $field, true)) {
                $request->merge([$field => $this->email($request->input($field))]);
                $rules[$field] = [new CompanyEmail()];
            }
        }

        return $rules;
    }

    private function changed(Request $request, ?Staff $current, string $field, bool $isEmail): bool
    {
        if ($current === null) {
            return true;
        }

        $tidy = fn ($value) => $isEmail ? strtolower(trim((string) $value)) : trim((string) $value);

        return $tidy($request->input($field)) !== $tidy($current->getRawOriginal($field));
    }

    private function email($value): ?string
    {
        $email = strtolower(trim((string) $value));

        return $email === '' ? null : $email;
    }
}
